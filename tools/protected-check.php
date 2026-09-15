<?php

declare(strict_types=1);

/**
 * Comprobación del aislamiento del material de pago.
 *
 * Verifica tres cosas que no dependen de que alguien se acuerde:
 *   1. `storage/` está fuera del directorio público.
 *   2. `storage/` deniega el acceso web mediante .htaccess.
 *   3. Ninguna ruta guardada en la base de datos (lecciones, plantillas y
 *      videos) resuelve dentro de `public/`.
 *
 * Uso:  php tools/protected-check.php
 */

use GB\Application;

require dirname(__DIR__) . '/app/bootstrap.php';

$failures = [];
$checks = [];

function report(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;

    $checks[] = [$label, $ok, $detail];

    if (!$ok) {
        $failures[] = $label . ($detail === '' ? '' : ' (' . $detail . ')');
    }
}

$publicPath = realpath(GB_PUBLIC_PATH);
$storagePath = realpath(GB_STORAGE_PATH);

report('storage/ existe', $storagePath !== false, GB_STORAGE_PATH);
report('public/ existe', $publicPath !== false, GB_PUBLIC_PATH);

if ($storagePath !== false && $publicPath !== false) {
    $insidePublic = str_starts_with($storagePath . DIRECTORY_SEPARATOR, $publicPath . DIRECTORY_SEPARATOR);
    report('storage/ está fuera de public/', !$insidePublic, $storagePath);
}

$htaccess = GB_STORAGE_PATH . '/.htaccess';
$denyRules = is_file($htaccess) ? (string) file_get_contents($htaccess) : '';
report(
    'storage/.htaccess deniega el acceso web',
    str_contains($denyRules, 'Require all denied') || str_contains($denyRules, 'Deny from all'),
    is_file($htaccess) ? $htaccess : 'no existe'
);

$protectedPath = GB_STORAGE_PATH . '/protected';
if (!is_dir($protectedPath) && !@mkdir($protectedPath, 0750, true) && !is_dir($protectedPath)) {
    report('storage/protected/ disponible', false, $protectedPath);
} else {
    report('storage/protected/ disponible', true, $protectedPath);
}

// Ninguna ruta registrada puede vivir dentro del directorio público.
$references = [];
$pdo = Application::boot()->get(PDO::class);

$sources = [
    'lessons' => 'SELECT id, protected_path AS path FROM lessons WHERE protected_path IS NOT NULL',
    'templates' => 'SELECT id, file_path AS path FROM templates WHERE file_path IS NOT NULL',
    'videos' => 'SELECT id, file_path AS path FROM videos WHERE file_path IS NOT NULL',
];

foreach ($sources as $table => $sql) {
    try {
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $references[] = [$table, (int) $row['id'], (string) $row['path']];
        }
    } catch (Throwable $exception) {
        report('Consulta de rutas protegidas (' . $table . ')', false, $exception->getMessage());
    }
}

$leaks = [];
foreach ($references as [$table, $id, $path]) {
    $normalized = str_replace('\\', '/', $path);

    if (str_starts_with($normalized, 'public/')) {
        $leaks[] = $table . '#' . $id . ' -> ' . $path;
        continue;
    }

    $resolved = realpath(GB_STORAGE_PATH . '/' . ltrim($path, '/\\'));
    if ($resolved !== false && $publicPath !== false
        && str_starts_with($resolved . DIRECTORY_SEPARATOR, $publicPath . DIRECTORY_SEPARATOR)) {
        $leaks[] = $table . '#' . $id . ' -> ' . $path;
    }
}

report(
    'Ninguna ruta apunta a public/',
    $leaks === [],
    $leaks === [] ? $references === [] ? 'sin referencias todavía' : count($references) . ' rutas revisadas' : implode(', ', $leaks)
);

echo "Contenido protegido\n";

foreach ($checks as [$label, $ok, $detail]) {
    printf("  %s %-42s %s\n", $ok ? '[ok]  ' : '[fail]', $label, $detail);
}

echo "\n";

if ($failures !== []) {
    echo count($failures) . " comprobación(es) fallaron:\n";

    foreach ($failures as $failure) {
        echo '  - ' . $failure . "\n";
    }

    exit(1);
}

echo "El material de pago queda aislado del directorio público.\n";
