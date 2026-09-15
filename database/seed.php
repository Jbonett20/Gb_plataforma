<?php

declare(strict_types=1);

/**
 * Carga del contenido inicial.
 *
 * Toma los textos e imágenes del sitio actual desde `database/seeds/content.php`
 * y los deja como bloques administrables desde el panel.
 *
 * Se puede ejecutar las veces que haga falta:
 *
 *   php database/seed.php            Sólo crea lo que falta. NO sobrescribe
 *                                    nada que ya exista, así que volver a
 *                                    ejecutarlo nunca deshace un cambio hecho
 *                                    desde el panel.
 *   php database/seed.php --force    Vuelve a aplicar el contenido del archivo
 *                                    sobre lo que ya hay. Útil tras corregir un
 *                                    texto en content.php.
 *
 * Las imágenes se reprocesan con el pipeline: se recortan a la proporción de su
 * destino y se aligeran. La misma imagen no se guarda dos veces.
 */

use GB\Application;
use GB\Models\BlockItemRepository;
use GB\Models\BlockRepository;
use GB\Models\MediaRepository;
use GB\Models\ModuleRepository;
use GB\Models\SettingRepository;
use GB\Services\BlockPublisher;
use GB\Services\ImagePipeline;
use GB\Support\Config;
use GB\Support\UploadException;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

/**
 * Error de la carga inicial.
 *
 * Se lanza ANTES de escribir nada: si falta una imagen, es preferible detenerse
 * que escribir el bloque sin ella y borrar la referencia que ya estaba bien.
 */
final class SeedException extends RuntimeException
{
}

$container = Application::boot();

$blocks = $container->get(BlockRepository::class);
$items = $container->get(BlockItemRepository::class);
$modules = $container->get(ModuleRepository::class);
$settings = $container->get(SettingRepository::class);
$media = $container->get(MediaRepository::class);
$pipeline = $container->get(ImagePipeline::class);
$publisher = $container->get(BlockPublisher::class);

$force = in_array('--force', $argv, true);
$content = require GB_BASE_PATH . '/database/seeds/content.php';
$labels = Config::array('admin_labels');

$counters = [
    'settings' => 0,
    'modules' => 0,
    'blocks' => 0,
    'items' => 0,
    'media_reused' => 0,
    'media_created' => 0,
    'published' => 0,
];
$warnings = [];

/** @var array<string, int> $mediaCache */
$mediaCache = [];

/**
 * Registra (o reutiliza) una imagen del sitio actual.
 *
 * @param array{file: string, profile: string, alt?: string} $spec
 */
function seedMedia(array $spec, ImagePipeline $pipeline, MediaRepository $media, array &$counters, array &$warnings, array &$cache): int
{
    $relative = (string) ($spec['file'] ?? '');
    $profile = (string) ($spec['profile'] ?? '');

    if ($relative === '' || $profile === '') {
        throw new SeedException('Hay una imagen sin archivo o sin destino definido en content.php.');
    }

    $cacheKey = $relative . '|' . $profile;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $path = GB_PUBLIC_PATH . '/assets/' . ltrim($relative, '/');

    if (!is_file($path)) {
        throw new SeedException(sprintf(
            "No se encuentra la imagen \"%s\".\n"
            . "  Se esperaba en: %s\n"
            . "  Cópiala ahí (o corrige la ruta en database/seeds/content.php) y vuelve a ejecutar.\n"
            . '  No se ha modificado nada.',
            $relative,
            $path
        ));
    }

    // Las medidas se leen del archivo del sitio actual. Se hace antes de
    // cualquier escritura, para poder avisar incluso cuando la imagen ya estaba
    // registrada de una ejecución anterior.
    $info = @getimagesize($path);
    $profileConfig = $pipeline->profile($profile);
    $minWidth = (int) ($profileConfig['min_width'] ?? 0);
    $minHeight = (int) ($profileConfig['min_height'] ?? 0);

    $adviseSmall = function () use (&$warnings, $info, $relative, $profileConfig, $minWidth, $minHeight): void {
        if (!is_array($info) || $minWidth <= 0 || $minHeight <= 0) {
            return;
        }

        if ($info[0] >= $minWidth && $info[1] >= $minHeight) {
            return;
        }

        $warnings[] = sprintf(
            '%s mide %d × %d y para "%s" se recomiendan %d × %d: se verá con menos nitidez. '
            . 'Conviene reemplazarla desde el panel.',
            $relative,
            $info[0],
            $info[1],
            $profileConfig['label'] ?? '',
            $minWidth,
            $minHeight
        );
    };

    $checksum = hash_file('sha256', $path) ?: null;

    if ($checksum !== null) {
        $existing = $media->findDuplicate($checksum, $profile, 0.5, 0.5);

        if ($existing !== null) {
            $counters['media_reused']++;
            $adviseSmall();

            return $cache[$cacheKey] = (int) $existing['id'];
        }
    }

    try {
        // Las imágenes heredadas se aceptan aunque no lleguen al mínimo
        // recomendado: ya están publicadas y no se pueden pedir más grandes.
        $id = $pipeline->store([
            'name' => basename($path),
            'tmp_name' => $path,
            'size' => (int) filesize($path),
            'error' => UPLOAD_ERR_OK,
            'type' => (string) (mime_content_type($path) ?: 'image/jpeg'),
        ], $profile, null, (string) ($spec['alt'] ?? ''), 0.5, 0.5, true);

        $media->markAsLegacy($id);
        $counters['media_created']++;
    } catch (UploadException $exception) {
        throw new SeedException(sprintf('No se pudo procesar "%s": %s', $relative, $exception->getMessage()));
    }

    $adviseSmall();

    return $cache[$cacheKey] = $id;
}

/**
 * Compara la lista de elementos que hay con la que debería haber, para no
 * reescribirlos si ya coinciden. Sin esto, cada ejecución crearía filas nuevas
 * aunque el contenido fuera idéntico.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function comparableItems(array $rows): array
{
    $keys = ['title', 'subtitle', 'body', 'link_url', 'link_label', 'icon', 'media_id', 'status', 'data'];

    return array_map(static function (array $row) use ($keys): array {
        $result = [];

        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            // La base de datos devuelve un arreglo vacío donde el archivo tiene
            // `null`: sin unificar ambos casos, cada ejecución creería que el
            // contenido cambió y reescribiría los elementos.
            if (is_array($value)) {
                $value = $value === [] ? null : $value;
            }

            $result[$key] = $value === '' ? null : $value;
        }

        return $result;
    }, array_values($rows));
}

/**
 * Convierte un elemento del archivo de contenido a la forma que espera el
 * repositorio.
 *
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function prepareItem(array $raw, ImagePipeline $pipeline, MediaRepository $media, array &$counters, array &$warnings, array &$cache): array
{
    $mediaId = isset($raw['media']) && is_array($raw['media'])
        ? seedMedia($raw['media'], $pipeline, $media, $counters, $warnings, $cache)
        : null;

    return [
        'title' => $raw['title'] ?? null,
        'subtitle' => $raw['subtitle'] ?? null,
        'body' => $raw['body'] ?? null,
        'link_url' => $raw['link_url'] ?? null,
        'link_label' => $raw['link_label'] ?? null,
        'icon' => $raw['icon'] ?? null,
        'media_id' => $mediaId,
        'status' => $raw['status'] ?? 'active',
        'data' => $raw['data'] ?? null,
    ];
}

echo "Carga del contenido inicial\n";
echo str_repeat('=', 78) . "\n";
echo $force
    ? "Modo --force: se reescribirá el contenido con el del archivo.\n\n"
    : "Modo conservador: sólo se crea lo que falta; nada existente se sobrescribe.\n\n";

// -----------------------------------------------------------------------------
// Paso 1 — Resolver TODAS las imágenes antes de escribir nada.
//
// Si falta un archivo, el guion se detiene aquí y no toca la base de datos.
// -----------------------------------------------------------------------------
$blocksContent = (array) ($content['blocks'] ?? []);

try {
    foreach ($blocksContent as $key => $data) {
        if (isset($data['media']) && is_array($data['media'])) {
            seedMedia($data['media'], $pipeline, $media, $counters, $warnings, $mediaCache);
        }

        foreach ((array) ($data['items'] ?? []) as $raw) {
            if (is_array($raw) && isset($raw['media']) && is_array($raw['media'])) {
                seedMedia($raw['media'], $pipeline, $media, $counters, $warnings, $mediaCache);
            }
        }
    }

    // `prepareItem` también resuelve imágenes: se recorren una vez para que un
    // elemento con imagen ausente detenga el proceso antes de cualquier escritura.
    foreach ($blocksContent as $key => $data) {
        foreach ((array) ($data['items'] ?? []) as $raw) {
            if (is_array($raw)) {
                prepareItem($raw, $pipeline, $media, $counters, $warnings, $mediaCache);
            }
        }
    }
} catch (SeedException $exception) {
    fwrite(STDERR, "\n" . $exception->getMessage() . "\n");
    exit(1);
}

printf(
    "  Imágenes resueltas: %d nuevas, %d reutilizadas\n",
    $counters['media_created'],
    $counters['media_reused']
);

// -----------------------------------------------------------------------------
// 1. Ajustes del sitio
// -----------------------------------------------------------------------------
$existingSettings = $settings->all();

foreach ((array) ($content['settings'] ?? []) as $key => $value) {
    if (isset($existingSettings[$key]) && !$force) {
        continue;
    }

    $settings->set((string) $key, (string) $value);
    $counters['settings']++;
}

printf("  Ajustes del sitio: %d %s\n", $counters['settings'], $force ? 'reescritos' : 'creados');

// -----------------------------------------------------------------------------
// 2. Módulos
// -----------------------------------------------------------------------------
foreach ((array) ($labels['modules'] ?? []) as $key => $definition) {
    $status = (string) ($content['modules'][$key]['status'] ?? 'active');
    $existing = $modules->findByKey($key);

    if ($existing !== null) {
        // Se actualizan los textos y el orden, pero NO el estado: puede haberlo
        // cambiado alguien desde el panel a propósito.
        $modules->updateModule((int) $existing['id'], [
            'label' => $definition['label'] ?? $key,
            'menu_label' => $definition['menu_label'] ?? null,
            'description' => $definition['description'] ?? null,
            'sort_order' => (int) ($definition['sort_order'] ?? 0),
            'show_in_menu' => !empty($definition['show_in_menu']) ? 1 : 0,
            'show_on_home' => !empty($definition['show_on_home']) ? 1 : 0,
        ]);

        if ($force) {
            $modules->setStatus((int) $existing['id'], $status);
        }

        continue;
    }

    $modules->create([
        'key_name' => $key,
        'label' => $definition['label'] ?? $key,
        'menu_label' => $definition['menu_label'] ?? null,
        'description' => $definition['description'] ?? null,
        'status' => $status,
        'sort_order' => (int) ($definition['sort_order'] ?? 0),
        'show_in_menu' => !empty($definition['show_in_menu']) ? 1 : 0,
        'show_on_home' => !empty($definition['show_on_home']) ? 1 : 0,
    ]);

    $counters['modules']++;
}

printf("  Módulos de contenido: %d creados\n", $counters['modules']);

// -----------------------------------------------------------------------------
// 3. Bloques y sus elementos
//
// Se recorre la lista declarada en config/admin_labels.php, no la del contenido:
// así el panel siempre muestra todas las secciones, aunque alguna todavía esté
// vacía y a la espera de que el despacho la rellene.
// -----------------------------------------------------------------------------
foreach ((array) ($labels['blocks'] ?? []) as $key => $definition) {
    $data = (array) ($content['blocks'][$key] ?? []);

    $blockMediaId = isset($data['media']) && is_array($data['media'])
        ? seedMedia($data['media'], $pipeline, $media, $counters, $warnings, $mediaCache)
        : null;

    $values = [
        'label' => (string) ($definition['label'] ?? $key),
        'help_text' => $definition['help'] ?? null,
        'type' => (string) ($definition['type'] ?? 'list'),
        'sort_order' => (int) ($definition['sort_order'] ?? 0),
        'eyebrow' => $data['eyebrow'] ?? null,
        'heading' => $data['heading'] ?? null,
        'intro' => $data['intro'] ?? null,
        'media_id' => $blockMediaId,
        'cta_label' => $data['cta_label'] ?? null,
        'cta_url' => $data['cta_url'] ?? null,
        'settings' => $data['settings'] ?? null,
    ];

    $block = $blocks->findByKey($key);

    if ($block === null) {
        $blockId = $blocks->create($values + [
            'key_name' => $key,
            'status' => $data['status'] ?? 'active',
        ]);
        $counters['blocks']++;
    } else {
        $blockId = (int) $block['id'];

        if ($force) {
            $blocks->updateContent($blockId, $values + ['status' => $data['status'] ?? $block['status']]);
        }
    }

    // --- Elementos del bloque ------------------------------------------------
    $desired = [];

    foreach ((array) ($data['items'] ?? []) as $raw) {
        if (is_array($raw)) {
            $desired[] = prepareItem($raw, $pipeline, $media, $counters, $warnings, $mediaCache);
        }
    }

    if ($desired !== []) {
        $current = comparableItems($items->forBlock($blockId, true));
        $wanted = comparableItems($desired);

        if ($current !== $wanted) {
            $items->replaceAll($blockId, $desired);
            $counters['items'] += count($desired);
        }
    }

    // --- Publicación --------------------------------------------------------
    // Un bloque sin elementos no se publica: no hay nada que mostrar. Queda en
    // el panel para que se rellene cuando haya datos reales.
    if ($desired !== [] && $publisher->hasUnpublishedChanges($blockId)) {
        $publisher->publish($blockId, null, 'Carga inicial del contenido del sitio actual');
        $counters['published']++;
    }
}

// Un bloque con contenido pero no declarado no se cargaría nunca: conviene
// avisar en vez de dejarlo en silencio.
foreach (array_diff(array_keys((array) ($content['blocks'] ?? [])), array_keys((array) ($labels['blocks'] ?? []))) as $orphan) {
    $warnings[] = sprintf(
        'El contenido define el bloque "%s", que no está declarado en config/admin_labels.php. Se omitió.',
        $orphan
    );
}

printf("  Bloques: %d creados, %d publicados\n", $counters['blocks'], $counters['published']);
printf("  Elementos escritos: %d\n", $counters['items']);
printf(
    "  Imágenes: %d nuevas, %d ya existentes (reutilizadas)\n",
    $counters['media_created'],
    $counters['media_reused']
);

// -----------------------------------------------------------------------------
// Avisos
// -----------------------------------------------------------------------------
if ($warnings !== []) {
    echo "\nAvisos:\n";

    foreach ($warnings as $warning) {
        echo '  - ' . $warning . "\n";
    }
}

echo str_repeat('=', 78) . "\n";
echo "Listo. Revisa el sitio y, si falta algo, corrígelo en database/seeds/content.php\n";
echo "y vuelve a ejecutar:  php database/seed.php --force\n";

exit(0);
