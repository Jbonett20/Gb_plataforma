<?php

declare(strict_types=1);

/**
 * Verificación de bloques, publicación y visibilidad (grupo 4 del plan).
 *
 * Comprueba sin navegador que:
 *
 *   - cada tipo de bloque declarado tiene su plantilla;
 *   - guardar un borrador NO cambia lo que ve el visitante;
 *   - publicar sí lo cambia, y queda registrado en el historial;
 *   - "Deshacer" devuelve el contenido anterior;
 *   - ocultar un bloque o un módulo lo saca del sitio y del menú al instante;
 *   - la papelera conserva el contenido y la confirmación es de un solo uso;
 *   - la caché sirve la página y se vacía al publicar.
 *
 * Crea bloques de prueba y los elimina al terminar.
 *
 * Uso:  php tools/blocks-test.php
 */

use GB\Application;
use GB\Models\BlockItemRepository;
use GB\Models\BlockRepository;
use GB\Models\ModuleRepository;
use GB\Services\BlockPublisher;
use GB\Services\BlockRenderer;
use GB\Services\NavigationBuilder;
use GB\Services\TrashService;
use GB\Services\ViewCache;
use GB\Support\Config;
use GB\Support\Session;
use GB\Support\View;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$container->get(Session::class)->start();

$blocks = $container->get(BlockRepository::class);
$items = $container->get(BlockItemRepository::class);
$modules = $container->get(ModuleRepository::class);
$publisher = $container->get(BlockPublisher::class);
$renderer = $container->get(BlockRenderer::class);
$navigation = $container->get(NavigationBuilder::class);
$cache = $container->get(ViewCache::class);
$trash = $container->get(TrashService::class);
$view = $container->get(View::class);

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

$testKey = '__prueba_hero';
$auditStartId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();

// Limpieza de ejecuciones anteriores interrumpidas.
$pdo->prepare('DELETE FROM blocks WHERE key_name = ?')->execute([$testKey]);
$cache->flush();

echo "Verificación de bloques y visibilidad\n";
echo str_repeat('=', 78) . "\n";

// -----------------------------------------------------------------------------
// 1. Cada tipo declarado tiene su plantilla
// -----------------------------------------------------------------------------
echo "Plantillas de bloque\n";

$registry = Config::array('admin_labels.blocks');
$missing = [];

foreach ($registry as $key => $definition) {
    $type = (string) ($definition['type'] ?? '');

    if ($type === '' || !$view->exists('blocks.' . $type)) {
        $missing[] = $key . ' (tipo ' . $type . ')';
    }
}

check(
    sprintf('los %d bloques declarados tienen plantilla', count($registry)),
    fn () => $missing === []
);

if ($missing !== []) {
    echo '        Sin plantilla: ' . implode(', ', $missing) . "\n";
}

check('un tipo desconocido cae en la plantilla de aviso', function () use ($renderer): bool {
    $template = $renderer->templateFor(['key_name' => 'x', 'type' => 'no-existe']);

    return $template === 'blocks.unknown';
});

// -----------------------------------------------------------------------------
// 2. Publicar es lo que hace visible un cambio
// -----------------------------------------------------------------------------
echo "\nBorrador y publicación\n";

$blockId = $blocks->create([
    'key_name' => $testKey,
    'label' => 'Bloque de prueba',
    'type' => 'hero',
    'sort_order' => 500,
    'status' => 'active',
]);

$publisher->saveDraft($blockId, ['heading' => 'Título original'], [
    ['title' => 'Primera', 'body' => 'Texto de la primera', 'media_id' => null, 'link_label' => 'Ir', 'link_url' => '#'],
    ['title' => 'Segunda', 'body' => 'Texto de la segunda', 'media_id' => null, 'link_label' => 'Ir', 'link_url' => '#'],
]);

check('el bloque se crea con sus elementos', fn () => $items->countForBlock($blockId) === 2);
check('un bloque sin publicar no aparece en el sitio', function () use ($blocks, $testKey): bool {
    foreach ($blocks->publishedBlocks() as $block) {
        if ($block['key_name'] === $testKey) {
            return false;
        }
    }

    return true;
});

$version = $publisher->publish($blockId, null, 'Publicación inicial');
check('al publicar se crea la versión 1', fn () => $version === 1);

$published = null;

foreach ($blocks->publishedBlocks() as $block) {
    if ($block['key_name'] === $testKey) {
        $published = $block;
    }
}

check('el bloque publicado aparece con su contenido', fn () => $published !== null
    && count($published['items']) === 2
    && $published['items'][0]['title'] === 'Primera');

$html = $renderer->render($published ?? []);
check('el HTML generado contiene el contenido publicado', fn () => str_contains($html, 'Primera')
    && str_contains($html, 'Texto de la primera'));
check('el HTML usa el ancla del bloque', fn () => str_contains($html, 'id="__prueba_hero"'));

// --- Cambio en borrador -------------------------------------------------------
$publisher->saveDraft($blockId, ['heading' => 'Título nuevo'], [
    ['title' => 'Cambiada', 'body' => 'Texto nuevo', 'media_id' => null, 'link_label' => 'Ir', 'link_url' => '#'],
]);

$afterDraft = null;

foreach ($blocks->publishedBlocks() as $block) {
    if ($block['key_name'] === $testKey) {
        $afterDraft = $block;
    }
}

check('guardar un borrador NO cambia el sitio', fn () => $afterDraft !== null
    && $afterDraft['items'][0]['title'] === 'Primera');
check('el sistema detecta que hay cambios sin publicar', fn () => $publisher->hasUnpublishedChanges($blockId) === true);

$version = $publisher->publish($blockId, null, 'Segunda publicación');
check('la segunda publicación crea la versión 2', fn () => $version === 2);
check('ya no hay cambios pendientes', fn () => $publisher->hasUnpublishedChanges($blockId) === false);

$afterPublish = null;

foreach ($blocks->publishedBlocks() as $block) {
    if ($block['key_name'] === $testKey) {
        $afterPublish = $block;
    }
}

check('ahora sí se ve el contenido nuevo', fn () => $afterPublish !== null
    && $afterPublish['items'][0]['title'] === 'Cambiada');

// -----------------------------------------------------------------------------
// 3. Deshacer
// -----------------------------------------------------------------------------
echo "\nDeshacer un cambio publicado\n";

check('se conserva el historial de versiones', fn () => count($publisher->versions($blockId)) === 2);
check('deshacer devuelve la versión anterior', fn () => $publisher->undo($blockId) === true);

$afterUndo = null;

foreach ($blocks->publishedBlocks() as $block) {
    if ($block['key_name'] === $testKey) {
        $afterUndo = $block;
    }
}

check('el contenido vuelve a ser el anterior', fn () => $afterUndo !== null
    && $afterUndo['items'][0]['title'] === 'Primera');
check('el historial conserva las tres versiones', fn () => count($publisher->versions($blockId)) === 3);
check('deshacer queda registrado en la auditoría', function () use ($pdo, $auditStartId): bool {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE id > ' . $auditStartId)->fetchColumn();

    return $count >= 3;
});

// -----------------------------------------------------------------------------
// 4. Ocultar sin perder el contenido
// -----------------------------------------------------------------------------
echo "\nVisibilidad de bloques y módulos\n";

$blocks->setStatus($blockId, 'inactive');

$stillThere = false;

foreach ($blocks->publishedBlocks() as $block) {
    if ($block['key_name'] === $testKey) {
        $stillThere = true;
    }
}

check('un bloque oculto desaparece del sitio', fn () => $stillThere === false);
check('pero su contenido se conserva en el panel', fn () => $items->countForBlock($blockId) === 2);

$blocks->setStatus($blockId, 'active');

$testModuleKey = '__prueba_modulo';
$pdo->prepare('DELETE FROM modules WHERE key_name = ?')->execute([$testModuleKey]);

$moduleId = $modules->create([
    'key_name' => $testModuleKey,
    'label' => 'Módulo de prueba',
    'menu_label' => 'Módulo de prueba',
    'description' => 'Sólo para esta verificación.',
    'status' => 'active',
    'sort_order' => 900,
    'show_in_menu' => 1,
    'show_on_home' => 1,
]);

check('un módulo activo aparece en el menú', function () use ($modules, $testModuleKey): bool {
    foreach ($modules->menuItems() as $item) {
        if ($item['key'] === $testModuleKey) {
            return true;
        }
    }

    return false;
});

check('un módulo activo aparece en la portada', fn () => in_array($testModuleKey, $modules->homeSectionKeys(), true));

$modules->setStatus($moduleId, 'inactive');

check('un módulo desactivado desaparece del menú', function () use ($modules, $testModuleKey): bool {
    foreach ($modules->menuItems() as $item) {
        if ($item['key'] === $testModuleKey) {
            return false;
        }
    }

    return true;
});

check('un módulo desactivado no deja enlaces en la navegación', function () use ($navigation, $testModuleKey): bool {
    foreach ($navigation->publicMenu() as $link) {
        if (str_contains($link['href'], $testModuleKey)) {
            return false;
        }
    }

    return true;
});

check('un módulo oculto sí responde a su enlace directo', function () use ($modules, $moduleId, $testModuleKey): bool {
    $modules->setStatus($moduleId, 'hidden');

    return $modules->isPubliclyVisible($testModuleKey) === true
        && $modules->menuItems() === [];
});

$modules->deletePermanently($moduleId);

// -----------------------------------------------------------------------------
// 5. Papelera y confirmación en dos pasos
// -----------------------------------------------------------------------------
echo "\nPapelera y confirmación\n";

$token = $trash->requestConfirmation('block', $blockId, 'Bloque de prueba', 'Se eliminarán 1 bloque y 2 elementos.');
$pending = $trash->pending($token);

check('la confirmación pendiente identifica el elemento', fn () => $pending !== null
    && $pending['type'] === 'block'
    && $pending['id'] === $blockId);
check('la confirmación explica qué se pierde', fn () => $pending !== null
    && str_contains($pending['consequence'], '2 elementos'));
check('la confirmación se consume una sola vez', function () use ($trash, $token): bool {
    $first = $trash->consume($token);
    $second = $trash->consume($token);

    return $first !== null && $second === null;
});
check('un pase inventado no sirve', fn () => $trash->pending(str_repeat('a', 32)) === null);

$blocks->moveToTrash($blockId, 30);

$trashed = $blocks->findById($blockId);
check('el bloque pasa a la papelera', fn () => $trashed !== null && $trashed['status'] === 'deleted'
    && $trashed['deleted_at'] !== null);
check('la papelera indica cuántos días quedan', fn () => $blocks->daysUntilPurge($blockId) === 30);
check('el contenido sigue guardado mientras está en la papelera', fn () => $items->countForBlock($blockId) === 2);

$blocks->restoreFromTrash($blockId);
$restored = $blocks->findById($blockId);
check('se puede restaurar desde la papelera', fn () => $restored !== null && $restored['deleted_at'] === null);

// -----------------------------------------------------------------------------
// 6. Caché de páginas públicas
// -----------------------------------------------------------------------------
echo "\nCaché de páginas públicas\n";

$cache->flush();
$cache->put('prueba', '<p>contenido guardado</p>');

check('la caché devuelve lo guardado', fn () => $cache->get('prueba') === '<p>contenido guardado</p>');
check('al publicar se vacía la caché', function () use ($publisher, $cache, $blockId): bool {
    $publisher->publish($blockId, null, 'Publicación para probar la caché');

    return $cache->get('prueba') === null;
});

// -----------------------------------------------------------------------------
// Limpieza
// -----------------------------------------------------------------------------
$pdo->prepare('DELETE FROM audit_logs WHERE id > ?')->execute([$auditStartId]);
$blocks->deletePermanently($blockId);
$cache->flush();

check('la limpieza deja la base como estaba', fn () => $blocks->findByKey($testKey) === null);

echo str_repeat('=', 78) . "\n";
printf("Comprobaciones superadas: %d   fallidas: %d\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
