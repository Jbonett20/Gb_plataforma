<?php

declare(strict_types=1);

namespace GB\Support;

use PDO;
use RuntimeException;

/**
 * Ejecución de guiones SQL.
 *
 * Divide un archivo .sql en sentencias respetando comillas y comentarios, para
 * poder ejecutarlas una a una con PDO. Es lo bastante estricto para el DDL de
 * este proyecto (no hay procedimientos ni delimitadores personalizados).
 */
final class SqlScript
{
    /**
     * @return array<int, string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $index = 0;

        // Normaliza el fin de línea ANTES de medir la cadena: si se mide antes,
        // los índices quedan desfasados y se leen posiciones inexistentes.
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $length = strlen($sql);

        while ($index < $length) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            // Comentario de línea: se descarta hasta el salto de línea.
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }

                continue;
            }

            // Comentario de bloque.
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $index + 2);
                $index = $end === false ? $length : $end + 2;

                continue;
            }

            // Cadena o identificador entrecomillado: se copia tal cual.
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                $index++;

                while ($index < $length) {
                    $current = $sql[$index];

                    // Barra invertida de escape (sólo dentro de comillas simples o dobles).
                    if ($current === '\\' && $quote !== '`') {
                        $buffer .= $current;

                        if ($index + 1 < $length) {
                            $buffer .= $sql[$index + 1];
                        }

                        $index += 2;

                        continue;
                    }

                    $buffer .= $current;

                    if ($current === $quote) {
                        // Comilla duplicada: sigue siendo la misma cadena.
                        if ($index + 1 < $length && $sql[$index + 1] === $quote) {
                            $buffer .= $quote;
                            $index += 2;

                            continue;
                        }

                        $index++;

                        break;
                    }

                    $index++;
                }

                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $buffer = '';
                $index++;

                continue;
            }

            $buffer .= $char;
            $index++;
        }

        $statement = trim($buffer);

        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * Ejecuta todas las sentencias de un archivo.
     *
     * @return int número de sentencias ejecutadas
     */
    public static function runFile(PDO $pdo, string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('No se encuentra el guion SQL "%s".', $path));
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException(sprintf('No se pudo leer el guion SQL "%s".', $path));
        }

        $statements = self::statements($sql);

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        return count($statements);
    }
}
