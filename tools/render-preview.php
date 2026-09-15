<?php

declare(strict_types=1);

/**
 * Vista previa de bloques para comprobar la maquetación con imágenes reales.
 *
 * Genera una imagen de prueba, la pasa por el pipeline, publica unos bloques
 * temporales con esa imagen y escribe una página en `public/_preview.html` con
 * los estilos reales del sitio. Sirve para revisar en el navegador, a distintos
 * anchos de pantalla, que ningún bloque se deforma ni desborda.
 *
 * No toca la base de datos más allá de la imagen de prueba, que se elimina con
 * `--cleanup`. La página generada también se borra.
 *
 * Uso:
 *   php tools/render-preview.php            Genera la página de vista previa.
 *   php tools/render-preview.php --cleanup  Borra la imagen y la página.
 */

use GB\Application;
use GB\Services\BlockRenderer;
use GB\Services\ImagePipeline;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var ImagePipeline $pipeline */
$pipeline = $container->get(ImagePipeline::class);

/** @var BlockRenderer $renderer */
$renderer = $container->get(BlockRenderer::class);

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$previewFile = GB_PUBLIC_PATH . '/_preview.html';

if (in_array('--cleanup', $argv, true)) {
    $statement = $pdo->prepare("SELECT id FROM media WHERE file_name = 'preview.jpg'");
    $statement->execute();

    foreach (array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)) as $id) {
        $pipeline->delete($id);
    }

    if (is_file($previewFile)) {
        @unlink($previewFile);
    }

    echo "Vista previa eliminada.\n";
    exit(0);
}

// --- Imagen de prueba: muy panorámica, para forzar el recorte ---------------
$image = imagecreatetruecolor(2400, 800);
imagefilledrectangle($image, 0, 0, 1199, 799, imagecolorallocate($image, 200, 60, 60) ?: 0);
imagefilledrectangle($image, 1200, 0, 2399, 799, imagecolorallocate($image, 40, 80, 180) ?: 0);
imagefilledrectangle($image, 0, 0, 2399, 90, imagecolorallocate($image, 30, 160, 90) ?: 0);

$temporary = tempnam(sys_get_temp_dir(), 'gbprev') . '.jpg';
imagejpeg($image, $temporary, 90);
imagedestroy($image);

$file = [
    'name' => 'preview.jpg',
    'tmp_name' => $temporary,
    'size' => (int) filesize($temporary),
    'error' => UPLOAD_ERR_OK,
    'type' => 'image/jpeg',
];

$mediaId = $pipeline->store($file, 'hero_slide', null, 'Imagen de prueba de maquetación');
$media = $container->get(\GB\Models\MediaRepository::class)->findById($mediaId);

// --- Bloques de muestra, con las formas reales del sitio --------------------
$blocks = [
    [
        'id' => 9001, 'key_name' => 'hero', 'type' => 'hero', 'status' => 'active',
        'settings' => [], 'items' => [
            [
                'title' => 'Diapositiva con imagen muy panorámica',
                'body' => 'La imagen original mide 2400 × 800 y se recorta a 16:9 sin deformarse.',
                'media_id' => $mediaId, 'link_label' => 'EMPEZAR', 'link_url' => '#', 'status' => 'active',
            ],
        ],
    ],
    [
        'id' => 9002, 'key_name' => 'cases', 'type' => 'card_grid', 'status' => 'active',
        'eyebrow' => 'CASOS DESTACADOS', 'heading' => 'Con imagen encima',
        'intro' => 'Comprueba que las tarjetas mantienen su altura y proporción.',
        'settings' => ['columns' => 3, 'media_on_top' => true],
        'items' => [
            ['title' => 'Caso uno', 'body' => 'Descripción del primer caso destacado.', 'media_id' => $mediaId, 'status' => 'active'],
            ['title' => 'Caso dos', 'body' => 'Descripción del segundo caso destacado.', 'media_id' => $mediaId, 'status' => 'active'],
            ['title' => 'Caso tres', 'body' => 'Descripción del tercer caso destacado.', 'media_id' => $mediaId, 'status' => 'active'],
        ],
    ],
    [
        'id' => 9003, 'key_name' => 'team', 'type' => 'team', 'status' => 'active',
        'eyebrow' => 'Equipo', 'heading' => 'Retratos en formato vertical.',
        'settings' => [],
        'items' => [
            ['title' => 'Ana Paola Ballesteros', 'subtitle' => 'Socia Fundadora', 'media_id' => $mediaId, 'status' => 'active'],
            ['title' => 'Claudia Marcela González', 'subtitle' => 'Socia Fundadora', 'media_id' => $mediaId, 'status' => 'active'],
        ],
    ],
    [
        'id' => 9004, 'key_name' => 'services', 'type' => 'card_grid', 'status' => 'active',
        'eyebrow' => 'Servicios', 'heading' => 'Tarjetas sin imagen',
        'settings' => ['columns' => 4, 'media_on_top' => false],
        'items' => [
            ['title' => 'Derecho laboral', 'body' => 'Asesoría integral a empresas.', 'icon' => 'bi bi-briefcase', 'status' => 'active'],
            ['title' => 'Seguridad social', 'body' => 'Acompañamiento pensional.', 'icon' => 'bi bi-people', 'status' => 'active'],
            ['title' => 'Procesal laboral', 'body' => 'Defensa en procesos y tutelas.', 'icon' => 'bi bi-shield-check', 'status' => 'active'],
            ['title' => 'Civil y comercial', 'body' => 'Contratación y gobierno corporativo.', 'icon' => 'bi bi-file-earmark-text', 'status' => 'active'],
        ],
    ],
    [
        'id' => 9005, 'key_name' => 'cta', 'type' => 'cta', 'status' => 'active',
        'heading' => '¿Necesitas orientación legal?', 'intro' => 'Franja con imagen de fondo muy panorámica.',
        'cta_label' => 'Agendar cita', 'cta_url' => '#', 'media_id' => $mediaId, 'settings' => [], 'items' => [],
    ],
];

$html = $renderer->renderAll($blocks);

$page = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
    . '<title>Vista previa de bloques</title>'
    . '<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">'
    . '<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">'
    . '<link href="assets/css/main.css" rel="stylesheet">'
    . '<link href="assets/css/theme.css" rel="stylesheet">'
    . '</head><body class="index-page">'
    . '<main class="main">' . $html . '</main>'
    . '</body></html>';

file_put_contents($previewFile, $page);

echo "Vista previa generada.\n";
echo 'Imagen de prueba: id ' . $mediaId
    . ' (' . ($media['width'] ?? '?') . ' × ' . ($media['height'] ?? '?') . " recortada desde 2400 × 800)\n";
echo 'Página: ' . $previewFile . "\n";
echo "Ábrela en el navegador a varios anchos y luego ejecuta:  php tools/render-preview.php --cleanup\n";
