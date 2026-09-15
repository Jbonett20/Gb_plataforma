<?php

declare(strict_types=1);

/**
 * Verificación del pipeline de imágenes (grupo 5 del plan).
 *
 * Comprueba sin navegador que:
 *
 *   - el recorte devuelve EXACTAMENTE la proporción del destino, sea cual sea
 *     la forma de la imagen original (cuadrada, muy ancha, muy alta);
 *   - nunca se agranda una imagen pequeña;
 *   - se rechazan los archivos que no son imágenes, los que pesan demasiado y
 *     los que se verían borrosos, con mensajes en español;
 *   - un SVG con código incrustado se rechaza;
 *   - el punto focal cambia de verdad el trozo que se conserva;
 *   - se generan variantes en formato moderno y compatible;
 *   - el original queda fuera del alcance web;
 *   - reemplazar una imagen conserva su destino.
 *
 * Crea imágenes de prueba y las elimina al terminar.
 *
 * Uso:  php tools/images-test.php
 */

use GB\Application;
use GB\Models\MediaRepository;
use GB\Services\ImagePipeline;
use GB\Support\UploadException;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var ImagePipeline $pipeline */
$pipeline = $container->get(ImagePipeline::class);

/** @var MediaRepository $media */
$media = $container->get(MediaRepository::class);

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$passed = 0;
$failed = 0;

function check(string $name, callable $condition): void
{
    global $passed, $failed;

    try {
        $ok = (bool) $condition();
    } catch (Throwable $exception) {
        $ok = false;
        $name .= ' — ' . $exception::class . ': ' . $exception->getMessage();
    }

    if ($ok) {
        $passed++;
        printf("  [ok]    %s\n", $name);
    } else {
        $failed++;
        printf("  [FALLA] %s\n", $name);
    }
}

/**
 * Crea una imagen de prueba con un patrón asimétrico, para poder detectar que
 * el recorte cambia según el punto focal.
 *
 * @return array<string, mixed> entrada con la forma de $_FILES
 */
function makeImage(int $width, int $height, string $name = 'prueba.jpg'): array
{
    $image = imagecreatetruecolor(max(1, $width), max(1, $height));
    $left = imagecolorallocate($image, 200, 30, 30);
    $right = imagecolorallocate($image, 30, 60, 200);
    $top = imagecolorallocate($image, 30, 160, 60);
    $bottom = imagecolorallocate($image, 240, 210, 20);

    if ($left !== false) {
        imagefilledrectangle($image, 0, 0, (int) ($width / 2), $height, $left);
    }

    if ($right !== false) {
        imagefilledrectangle($image, (int) ($width / 2) + 1, 0, $width, $height, $right);
    }

    if ($top !== false) {
        imagefilledrectangle($image, 0, 0, $width, (int) ($height / 4), $top);
    }

    if ($bottom !== false) {
        imagefilledrectangle($image, 0, (int) ($height / 4) + 1, $width, (int) ($height / 4) + 30, $bottom);
    }

    $path = tempnam(sys_get_temp_dir(), 'gbtest') . '.jpg';
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return [
        'name' => $name,
        'tmp_name' => $path,
        'size' => (int) filesize($path),
        'error' => UPLOAD_ERR_OK,
        'type' => 'image/jpeg',
    ];
}

function makeSvg(string $extra = ''): array
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 80">'
        . '<rect width="200" height="80" fill="#602350"/>' . $extra . '</svg>';

    $path = tempnam(sys_get_temp_dir(), 'gbsvg') . '.svg';
    file_put_contents($path, $svg);

    return [
        'name' => 'logo.svg',
        'tmp_name' => $path,
        'size' => (int) filesize($path),
        'error' => UPLOAD_ERR_OK,
        'type' => 'image/svg+xml',
    ];
}

$createdIds = [];

// Limpieza de restos de una ejecución anterior interrumpida, para que las
// huellas de los archivos de prueba no coincidan con registros viejos.
$testFileNames = ['prueba.jpg', 'izquierda.jpg', 'derecha.jpg', 'reemplazo.jpg', 'logo.svg'];
$placeholders = implode(',', array_fill(0, count($testFileNames), '?'));

$statement = $pdo->prepare("SELECT id FROM media WHERE file_name IN ({$placeholders})");
$statement->execute($testFileNames);

foreach (array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)) as $staleId) {
    $pipeline->delete($staleId);
}

echo "Verificación del pipeline de imágenes\n";
echo str_repeat('=', 78) . "\n";

// -----------------------------------------------------------------------------
// 1. El recorte respeta la proporción del destino
// -----------------------------------------------------------------------------
echo "Recorte sin deformación\n";

$shapes = [
    'cuadrada' => [1600, 1600],
    'muy ancha' => [4000, 1000],
    'muy alta' => [1000, 4000],
    'justa' => [1920, 1080],
];

foreach ($shapes as $label => $dimensions) {
    [$width, $height] = $dimensions;
    $rect = ImagePipeline::cropRect($width, $height, [16, 9], 0.5, 0.5);

    $ratio = $rect['width'] / $rect['height'];
    $expected = 16 / 9;

    check(
        sprintf('imagen %s %d×%d → recorte 16:9 exacto', $label, $width, $height),
        fn () => abs($ratio - $expected) < 0.01
            && $rect['width'] <= $width
            && $rect['height'] <= $height
            && $rect['x'] >= 0
            && $rect['y'] >= 0
    );
}

check('en una imagen muy ancha se recortan los lados', function (): bool {
    $rect = ImagePipeline::cropRect(4000, 1000, [16, 9], 0.5, 0.5);

    return $rect['height'] === 1000 && $rect['width'] < 4000 && $rect['y'] === 0;
});

check('en una imagen muy alta se recorta arriba y abajo', function (): bool {
    $rect = ImagePipeline::cropRect(1000, 4000, [16, 9], 0.5, 0.5);

    return $rect['width'] === 1000 && $rect['height'] < 4000 && $rect['x'] === 0;
});

check('sin proporción definida se conserva la imagen completa', function (): bool {
    $rect = ImagePipeline::cropRect(1234, 567, null, 0.5, 0.5);

    return $rect['width'] === 1234 && $rect['height'] === 567;
});

// --- Nunca agrandar -----------------------------------------------------------
echo "\nSin agrandar imágenes pequeñas\n";

check('no se generan medidas mayores que la imagen', function (): bool {
    $widths = ImagePipeline::planVariants(['width' => 1920, 'sizes' => [640, 1280, 1920]], 900);

    foreach ($widths as $width) {
        if ($width > 900) {
            return false;
        }
    }

    return $widths !== [] && end($widths) === 900;
});

check('una imagen diminuta genera una sola medida', function (): bool {
    $widths = ImagePipeline::planVariants(['width' => 1920, 'sizes' => [640, 1280, 1920]], 120);

    return count($widths) === 1 && $widths[0] === 120;
});

// -----------------------------------------------------------------------------
// 2. Validación con mensajes comprensibles
// -----------------------------------------------------------------------------
echo "\nValidación de archivos\n";

$notImage = tempnam(sys_get_temp_dir(), 'gbtxt') . '.jpg';
file_put_contents($notImage, 'esto no es una imagen, sólo texto plano');

$fakeImage = ['name' => 'falsa.jpg', 'tmp_name' => $notImage, 'size' => (int) filesize($notImage), 'error' => UPLOAD_ERR_OK];

check('rechaza un archivo que no es imagen', function () use ($pipeline, $fakeImage): bool {
    try {
        $pipeline->validate($fakeImage, 'hero_slide');

        return false;
    } catch (UploadException $exception) {
        return str_contains($exception->getMessage(), 'no es una imagen');
    }
});

$small = makeImage(200, 200);
check('avisa cuando la imagen se vería borrosa', function () use ($pipeline, $small): bool {
    try {
        $pipeline->validate($small, 'hero_slide');

        return false;
    } catch (UploadException $exception) {
        return $exception->context()['code'] === 'too_small'
            && str_contains($exception->getMessage(), '1280');
    }
});

check('permite continuar con una imagen pequeña si se insiste', function () use ($pipeline, $small): bool {
    $info = $pipeline->validate($small, 'hero_slide', true);

    return $info['width'] === 200 && $info['height'] === 200 && $info['extension'] === 'jpg';
});

$huge = tempnam(sys_get_temp_dir(), 'gbbig') . '.jpg';
file_put_contents($huge, str_repeat('x', 3 * 1048576));
$hugeFile = ['name' => 'enorme.jpg', 'tmp_name' => $huge, 'size' => (int) filesize($huge), 'error' => UPLOAD_ERR_OK];

check('rechaza un archivo que pesa demasiado', function () use ($pipeline, $hugeFile): bool {
    try {
        $pipeline->validate($hugeFile, 'site_logo');

        return false;
    } catch (UploadException $exception) {
        return str_contains($exception->getMessage(), 'MB');
    }
});

$badSvg = makeSvg('<script>alert(1)</script>');
check('rechaza un SVG con código incrustado', function () use ($pipeline, $badSvg): bool {
    try {
        $pipeline->validate($badSvg, 'site_logo');

        return false;
    } catch (UploadException $exception) {
        return str_contains($exception->getMessage(), 'seguridad');
    }
});

$goodSvg = makeSvg();
check('acepta un SVG que sólo dibuja', fn () => $pipeline->validate($goodSvg, 'site_logo')['extension'] === 'svg');

// -----------------------------------------------------------------------------
// 3. Guardado y variantes
// -----------------------------------------------------------------------------
echo "\nGuardado y variantes\n";

$source = makeImage(2400, 1200);
$mediaId = $pipeline->store($source, 'hero_slide', null, 'Imagen de prueba');
$createdIds[] = $mediaId;

$record = $media->findById($mediaId);

check('se registra el recurso en la base de datos', fn () => $record !== null
    && $record['profile'] === 'hero_slide');
check('las medidas guardadas respetan la proporción 16:9', fn () => abs(($record['width'] / $record['height']) - (16 / 9)) < 0.01);

$variants = $media->variants($record);
check('se generan varias medidas', fn () => count($variants) >= 3);
check('se genera formato moderno (webp)', fn () => count(array_filter($variants, fn (array $v): bool => $v['format'] === 'webp')) > 0);
check('se genera un formato compatible de respaldo', fn () => count(array_filter($variants, fn (array $v): bool => in_array($v['format'], ['jpeg', 'png'], true))) > 0);

$missing = [];

foreach ($variants as $variant) {
    if (!is_file(GB_PUBLIC_PATH . '/' . $variant['path'])) {
        $missing[] = $variant['path'];
    }
}

check('los archivos generados existen en disco', fn () => $missing === []);

if ($missing !== []) {
    echo '        Faltan: ' . implode(', ', $missing) . "\n";
}

$original = GB_STORAGE_PATH . '/uploads/originals/' . $record['path'];
check('el original se guarda fuera del alcance web', fn () => is_file($original)
    && !str_contains((string) realpath($original), (string) realpath(GB_PUBLIC_PATH)));

check('la ruta pública apunta a una variante generada', fn () => str_starts_with((string) $record['public_path'], 'uploads/hero_slide/'));
check('se elige la variante adecuada para el ancho pedido', function () use ($media, $mediaId): bool {
    $small = $media->publicUrl($mediaId, 700);
    $large = $media->publicUrl($mediaId, 1900);

    return $small !== null && $large !== null && $small !== $large;
});

check('la misma imagen no se guarda dos veces', function () use ($pipeline, $mediaId): bool {
    $identical = makeImage(2400, 1200);
    $duplicateId = $pipeline->store($identical, 'hero_slide', null, 'Imagen de prueba');
    @unlink((string) $identical['tmp_name']);

    return $duplicateId === $mediaId;
});

check('la misma imagen en otro destino sí es un recurso distinto', function () use ($pipeline, $mediaId): bool {
    $identical = makeImage(2400, 1200);
    $otherId = $pipeline->store($identical, 'course_cover', null, 'Imagen de prueba');
    @unlink((string) $identical['tmp_name']);

    if ($otherId === $mediaId) {
        return false;
    }

    $pipeline->delete($otherId);

    return true;
});

// -----------------------------------------------------------------------------
// 4. El punto focal cambia el encuadre
// -----------------------------------------------------------------------------
echo "\nAjuste del encuadre\n";

$leftSource = makeImage(2400, 1200, 'izquierda.jpg');
$leftId = $pipeline->store($leftSource, 'hero_slide', null, '', 0.0, 0.5);
$createdIds[] = $leftId;

$rightSource = makeImage(2400, 1200, 'derecha.jpg');
$rightId = $pipeline->store($rightSource, 'hero_slide', null, '', 1.0, 0.5);
$createdIds[] = $rightId;

$leftRecord = $media->findById($leftId);
$rightRecord = $media->findById($rightId);

check('el punto focal se guarda', fn () => (float) $leftRecord['focal_x'] === 0.0 && (float) $rightRecord['focal_x'] === 1.0);
check('encuadrar a la izquierda o a la derecha da imágenes distintas', fn () => file_get_contents(GB_PUBLIC_PATH . '/' . $leftRecord['public_path'])
    !== file_get_contents(GB_PUBLIC_PATH . '/' . $rightRecord['public_path']));
check('el punto focal fuera de rango se ajusta a los límites', function () use ($pipeline): bool {
    $rect = ImagePipeline::cropRect(2400, 1200, [16, 9], 5.0, -3.0);

    return $rect['x'] >= 0 && $rect['y'] >= 0 && $rect['x'] + $rect['width'] <= 2400;
});

// -----------------------------------------------------------------------------
// 5. Reemplazo conservando el destino
// -----------------------------------------------------------------------------
echo "\nReemplazo de imagen\n";

$replacementSource = makeImage(2000, 1500, 'reemplazo.jpg');
$replacementId = $pipeline->replaceWith($mediaId, $replacementSource);
$createdIds[] = $replacementId;

$replacement = $media->findById($replacementId);

check('el reemplazo conserva el destino', fn () => $replacement['profile'] === 'hero_slide');
check('el reemplazo conserva la proporción del destino', fn () => abs(($replacement['width'] / $replacement['height']) - (16 / 9)) < 0.01);
check('el reemplazo usa una imagen nueva', fn () => (int) $replacement['id'] !== $mediaId);

// -----------------------------------------------------------------------------
// 6. Eliminación
// -----------------------------------------------------------------------------
echo "\nEliminación de recursos\n";

$filesBefore = (int) $pdo->query('SELECT COUNT(*) FROM media WHERE id = ' . $leftId)->fetchColumn();
$pipeline->delete($leftId);
$createdIds = array_values(array_diff($createdIds, [$leftId]));

check('el registro se elimina', fn () => $filesBefore === 1 && $media->findById($leftId) === null);
check('sus archivos también se eliminan', fn () => !is_file(GB_PUBLIC_PATH . '/' . $leftRecord['public_path']));

// -----------------------------------------------------------------------------
// Limpieza
// -----------------------------------------------------------------------------
foreach ($createdIds as $id) {
    $pipeline->delete($id);
}

$leftover = (int) $pdo->query('SELECT COUNT(*) FROM media WHERE profile IS NOT NULL AND uploaded_by IS NULL
    AND alt_text = "Imagen de prueba"')->fetchColumn();

check('la limpieza deja la base como estaba', fn () => $media->findById($mediaId) === null && $leftover === 0);

echo str_repeat('=', 78) . "\n";
printf("Comprobaciones superadas: %d   fallidas: %d\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
