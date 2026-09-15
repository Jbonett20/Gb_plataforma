<?php

declare(strict_types=1);

namespace GB\Support;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Base de todos los repositorios de datos.
 *
 * Regla de seguridad: los valores NUNCA se concatenan a la consulta. Se pasan
 * siempre como parámetros vinculados a través de los métodos de esta clase.
 *
 * Para que un descuido no pase inadvertido:
 *   - se rechaza cualquier consulta que contenga un punto y coma (evita
 *     encadenar sentencias);
 *   - se exige que el número de parámetros coincida con los marcadores.
 *
 * Ejemplo de uso:
 *     $this->selectOne('SELECT * FROM users WHERE email = :email', ['email' => $email]);
 */
abstract class Model
{
    /** Nombre de la tabla principal. Las clases hijas lo sobrescriben. */
    protected string $table = '';

    protected string $primaryKey = 'id';

    public function __construct(protected PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    protected function select(string $sql, array $bindings = []): array
    {
        return $this->statement($sql, $bindings)->fetchAll();
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    protected function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->statement($sql, $bindings)->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    protected function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->statement($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    protected function exists(string $sql, array $bindings = []): bool
    {
        return $this->statement($sql, $bindings)->fetchColumn() !== false;
    }

    /**
     * Ejecuta una sentencia de escritura y devuelve las filas afectadas.
     *
     * @param array<string, mixed> $bindings
     */
    protected function run(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    /**
     * Inserta un registro y devuelve su identificador.
     *
     * @param array<string, mixed> $values
     */
    protected function insert(array $values): int
    {
        if ($values === []) {
            throw new RuntimeException('No se puede insertar un registro sin datos.');
        }

        $columns = array_keys($values);
        $table = $this->quoteIdentifier($this->table);

        $placeholders = array_map(
            static fn (string $column): string => ':' . $column,
            $columns
        );

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders)
        );

        $this->statement($sql, $values);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un registro por su identificador.
     *
     * @param array<string, mixed> $values
     */
    protected function update(int $id, array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $assignments = [];

        foreach (array_keys($values) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :__id',
            $this->quoteIdentifier($this->table),
            implode(', ', $assignments),
            $this->quoteIdentifier($this->primaryKey)
        );

        $bindings = $values;
        $bindings['__id'] = $id;

        return $this->run($sql, $bindings);
    }

    protected function deleteById(int $id): int
    {
        return $this->run(
            sprintf(
                'DELETE FROM %s WHERE %s = :__id',
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->primaryKey)
            ),
            ['__id' => $id]
        );
    }

    /**
     * Marca un registro como eliminado sin borrarlo.
     */
    protected function softDeleteById(int $id, int $retentionDays = 30): int
    {
        return $this->run(
            sprintf(
                'UPDATE %s SET deleted_at = NOW(), purge_after = DATE_ADD(NOW(), INTERVAL :__days DAY) WHERE %s = :__id',
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->primaryKey)
            ),
            ['__id' => $id, '__days' => $retentionDays]
        );
    }

    /**
     * Ejecuta varias operaciones como una sola unidad. Si algo falla, no se
     * guarda nada.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Ejecuta una consulta con paginación.
     *
     * @param array<string, mixed> $bindings
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    protected function paginate(string $sql, array $bindings, int $perPage = 12, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->scalar(
            sprintf('SELECT COUNT(*) FROM (%s) AS __total', $sql),
            $bindings
        );

        $items = $this->select(
            sprintf('%s LIMIT %d OFFSET %d', $sql, $perPage, $offset),
            $bindings
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * Comparte una conexión ya abierta con otra clase de datos.
     */
    protected function connection(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function statement(string $sql, array $bindings): PDOStatement
    {
        $this->assertSafe($sql, $bindings);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function assertSafe(string $sql, array $bindings): void
    {
        if (str_contains($sql, ';')) {
            throw new RuntimeException(
                'La consulta contiene un punto y coma. Cada consulta debe ser una sola sentencia.'
            );
        }

        // Los marcadores posicionales (?) y nombrados (:nombre) deben tener un
        // valor por cada uno: así se detecta un olvido antes de tocar la base.
        $positional = substr_count($sql, '?');
        $named = preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $sql) ?: 0;
        $expected = $positional + $named;

        if ($expected !== count($bindings)) {
            throw new RuntimeException(sprintf(
                'La consulta declara %d parámetros pero se enviaron %d valores. '
                . 'Nunca construyas consultas uniendo texto: usa parámetros vinculados.',
                $expected,
                count($bindings)
            ));
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException(sprintf('Nombre de columna o tabla no válido: "%s".', $identifier));
        }

        return '`' . $identifier . '`';
    }
}
