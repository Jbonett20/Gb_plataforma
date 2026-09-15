<?php

declare(strict_types=1);

/**
 * Ejecutor de migraciones.
 *
 * Crea la tabla `migrations` y aplica, en orden alfabético, cada archivo de
 * database/migrations que todavía no se haya ejecutado. Cada archivo corre una
 * sola vez, por lo que puede contener ALTER TABLE sin riesgo de duplicados.
 *
 * Uso:
 *   php database/migrate.php            Aplica las migraciones pendientes.
 *   php database/migrate.php --status   Muestra qué está aplicado y qué falta.
 */

use GB\Application;
use GB\Support\SqlScript;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$statusOnly = in_array('--status', $argv, true);

$container = Application::boot();

try {
    $pdo = $container->get(PDO::class);
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo conectar a la base de datos.\n" . $exception->getMessage() . "\n");
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        file_name    VARCHAR(190) NOT NULL,
        statements   INT UNSIGNED NOT NULL DEFAULT 0,
        duration_ms  INT UNSIGNED NOT NULL DEFAULT 0,
        run_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_file_name (file_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

/** @var array<int, string> $applied */
$applied = $pdo->query('SELECT file_name FROM migrations ORDER BY file_name')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(GB_BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($files);

echo "Base de datos: " . (string) config('app.name') . "\n";
echo str_repeat('=', 78) . "\n";

if ($statusOnly) {
    printf("%-34s %s\n", 'ARCHIVO', 'ESTADO');

    foreach ($files as $file) {
        $name = basename($file);
        printf("%-34s %s\n", $name, in_array($name, $applied, true) ? 'aplicado' : 'PENDIENTE');
    }

    echo str_repeat('=', 78) . "\n";
    printf("Total: %d archivos, %d aplicados, %d pendientes.\n", count($files), count($applied), count($files) - count($applied));

    exit(0);
}

$pending = array_values(array_filter($files, static fn (string $file): bool => !in_array(basename($file), $applied, true)));

if ($pending === []) {
    echo "No hay migraciones pendientes. La base de datos está al día.\n";
    exit(0);
}

$failures = 0;

foreach ($pending as $file) {
    $name = basename($file);
    $startedAt = microtime(true);

    try {
        $count = SqlScript::runFile($pdo, $file);
        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        $statement = $pdo->prepare('INSERT INTO migrations (file_name, statements, duration_ms) VALUES (:file, :count, :duration)');
        $statement->execute(['file' => $name, 'count' => $count, 'duration' => $duration]);

        printf("  [ok]   %-34s %2d sentencias  %4d ms\n", $name, $count, $duration);
    } catch (Throwable $exception) {
        $failures++;
        printf("  [fallo] %-33s %s\n", $name, $exception->getMessage());
        break;
    }
}

echo str_repeat('=', 78) . "\n";
echo $failures === 0
    ? "Migraciones aplicadas correctamente.\n"
    : "La migración se detuvo por un error. Corrígelo y vuelve a ejecutar el comando.\n";

exit($failures === 0 ? 0 : 1);
