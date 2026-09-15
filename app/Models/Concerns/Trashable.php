<?php

declare(strict_types=1);

namespace GB\Models\Concerns;

/**
 * Borrado lógico reutilizable.
 *
 * Ningún contenido publicado se borra con un DELETE directo: primero pasa a la
 * papelera y se conserva durante `purge_after` (30 días por defecto). Desde el
 * panel se puede restaurar; sólo el borrado definitivo, con confirmación en dos
 * pasos, ejecuta el DELETE.
 *
 * Lo usan los repositorios cuyo contenido se administra desde el panel.
 */
trait Trashable
{
    public function moveToTrash(int $id, int $retentionDays = 30): int
    {
        return $this->run(
            sprintf(
                'UPDATE %s SET status = :status, deleted_at = NOW(),
                        purge_after = DATE_ADD(NOW(), INTERVAL :days DAY)
                 WHERE id = :id',
                $this->tableName()
            ),
            ['id' => $id, 'status' => 'deleted', 'days' => $retentionDays]
        );
    }

    public function restoreFromTrash(int $id): int
    {
        return $this->run(
            sprintf(
                'UPDATE %s SET status = :status, deleted_at = NULL, purge_after = NULL WHERE id = :id',
                $this->tableName()
            ),
            ['id' => $id, 'status' => 'inactive']
        );
    }

    public function deletePermanently(int $id): int
    {
        return $this->run(
            sprintf('DELETE FROM %s WHERE id = :id', $this->tableName()),
            ['id' => $id]
        );
    }

    /**
     * Elementos que están en la papelera.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trashed(): array
    {
        return $this->select(
            sprintf(
                'SELECT id, deleted_at, purge_after FROM %s WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC',
                $this->tableName()
            )
        );
    }

    public function daysUntilPurge(int $id): ?int
    {
        $days = $this->scalar(
            sprintf('SELECT DATEDIFF(purge_after, NOW()) FROM %s WHERE id = :id AND purge_after IS NOT NULL', $this->tableName()),
            ['id' => $id]
        );

        return $days === null ? null : max(0, (int) $days);
    }

    /**
     * Elementos cuyo plazo de conservación ya venció.
     *
     * @return array<int, int>
     */
    public function dueForPurge(int $limit = 100): array
    {
        $rows = $this->select(
            sprintf(
                'SELECT id FROM %s WHERE deleted_at IS NOT NULL AND purge_after IS NOT NULL AND purge_after <= NOW()
                 ORDER BY purge_after LIMIT %d',
                $this->tableName(),
                max(1, $limit)
            )
        );

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Borra definitivamente lo que ya cumplió su plazo. Devuelve cuántos eran.
     */
    public function purgeDue(int $limit = 100): int
    {
        return count(array_filter(
            $this->dueForPurge($limit),
            fn (int $id): bool => $this->deletePermanently($id) > 0
        ));
    }

    /**
     * Nombre de la tabla entrecomillado. Procede de la propia clase, nunca de
     * datos recibidos.
     */
    protected function tableName(): string
    {
        return '`' . $this->table . '`';
    }
}
