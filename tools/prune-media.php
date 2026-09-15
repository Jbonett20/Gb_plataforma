<?php

declare(strict_types=1);

/**
 * Mantenimiento de los archivos subidos.
 *
 * Borra las imágenes que quedaron en disco sin registro en la base de datos.
 * Ocurre cuando un guardado se interrumpe: el pipeline ya ha escrito las
 * variantes y el original, pero el registro no llega a crearse.
 *
 * Sólo elimina archivos con el nombre que genera el pipeline
 * (`<hash>-<ancho>.<ext>` y `<hash>.<ext>`): nunca toca `.htaccess` ni nada más
 * que haya en esas carpetas.
 *
 * Conviene programarlo de vez en cuando (por ejemplo una vez al mes):
 *   php tools/prune-media.php
 *   php tools/prune-media.php --dry-run   (sólo informa, no borra)
 *
 * Uso en producción: se puede llamar desde el mismo cron que los respaldos.
 */

use GB\Application;
use GB\Services\ImagePipeline;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var ImagePipeline $pipeline */
$pipeline = $container->get(ImagePipeline::class);

$dryRun = in_array('--dry-run', $argv, true);

if ($dryRun) {
    // En modo informativo se cuentan sin borrar, reutilizando el mismo criterio.
    $before = countFiles(GB_PUBLIC_PATH . '/uploads') + countFiles(GB_STORAGE_PATH . '/uploads/originals');
    $result = $pipeline->pruneOrphans();
    $after = countFiles(GB_PUBLIC_PATH . '/uploads') + countFiles(GB_STORAGE_PATH . '/uploads/originals');

    printf("Archivos antes: %d   después: %d\n", $before, $after);
    printf("Se habrían eliminado: %d archivos (%.1f KB)\n", $result['files'], $result['bytes'] / 1024);

    exit(0);
}

$result = $pipeline->pruneOrphans();

if ($result['files'] === 0) {
    echo "No hay archivos huérfanos: todo lo que está en disco tiene su registro.\n";
    exit(0);
}

printf(
    "Se eliminaron %d archivos huérfanos (%.1f KB liberados).\n",
    $result['files'],
    $result['bytes'] / 1024
);

function countFiles(string $root): int
{
    if (!is_dir($root)) {
        return 0;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    $total = 0;

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && str_starts_with($file->getFilename(), '.') === false) {
            $total++;
        }
    }

    return $total;
}
