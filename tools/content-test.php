<?php

declare(strict_types=1);

/**
 * Verificación de la carga de contenido (grupo 6 del plan).
 *
 * Comprueba tres cosas:
 *
 *   1. Compara la página con los archivos del sitio anterior, título por título.
 *      Esta comparación es la que detecta que una sección entera se quedara sin
 *      migrar, porque no depende de una lista escrita a mano.
 *
 *   2. Revisa que el contenido está cargado y publicado: cada sección declarada
 *      existe, las que tienen algo publicado se dibujan y las que están vacías
 *      no aparecen.
 *
 *   3. Deja por escrito las diferencias deliberadas con el sitio anterior, para
 *      que no se confundan con un olvido.
 *
 * Uso:  php tools/content-test.php
 */

use GB\Application;
use GB\Models\BlockRepository;
use GB\Models\ModuleRepository;
use GB\Services\PageComposer;
use GB\Services\ViewCache;
use GB\Support\Config;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var BlockRepository $blocks */
$blocks = $container->get(BlockRepository::class);

/** @var ModuleRepository $modules */
$modules = $container->get(ModuleRepository::class);

/** @var PageComposer $composer */
$composer = $container->get(PageComposer::class);

/** @var ViewCache $cache */
$cache = $container->get(ViewCache::class);

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$passed = 0;
$failed = 0;

/**
 * Anota el resultado de una comprobación.
 */
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
 * Deja el texto comparable: sin etiquetas, sin entidades y con un solo espacio.
 */
function plain(string $html): string
{
    $text = preg_replace('/<[^>]*>/', ' ', $html) ?? $html;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

/**
 * Títulos y rótulos visibles de un archivo del sitio anterior.
 *
 * @return array<int, string>
 */
function headingsOf(string $file): array
{
    $source = (string) file_get_contents($file);
    $found = [];

    if (preg_match_all('/<(h[1-4])\b[^>]*>(.*?)<\/\1>/is', $source, $matches)) {
        foreach ($matches[2] as $inner) {
            $found[] = plain($inner);
        }
    }

    // Los rótulos de una sección van dentro de `section-title`. Se leen uno por
    // uno: un mismo bloque puede llevar rótulo pequeño y texto de apoyo.
    if (preg_match_all(
        '/class="[^"]*section-title[^"]*"(.*?)(?:<!--\s*End Section Title|<\/section>)/is',
        $source,
        $chunks
    )) {
        foreach ($chunks[1] as $chunk) {
            if (preg_match_all('/<span\b[^>]*>(.*?)<\/span>/is', $chunk, $spans)) {
                foreach ($spans[1] as $inner) {
                    $found[] = plain($inner);
                }
            }
        }
    }

    // Se descartan los vacíos y los que no aportan texto real.
    return array_values(array_filter(
        array_unique($found),
        static fn (string $text): bool => $text !== '' && preg_match('/\p{L}/u', $text) === 1
    ));
}

$legacyRoot = dirname(__DIR__, 2) . '/proyectoold';
$legacyIndex = $legacyRoot . '/index.php';

if (!is_file($legacyIndex)) {
    fwrite(STDERR, sprintf("No se encuentra el sitio anterior en %s\n", $legacyRoot));
    exit(1);
}

// La caché se vacía para comprobar el HTML recién generado, no una copia previa.
$cache->flush();
$page = $composer->home();
$html = $page['sections'] . $page['footer'];

echo "Verificación del contenido cargado\n";
echo str_repeat('=', 78) . "\n";

// -----------------------------------------------------------------------------
// 1. Comparación con los archivos del sitio anterior, uno por uno
//
// La lista de archivos se lee del propio index.php anterior: si allí se añadía
// una sección, aquí aparece automáticamente.
// -----------------------------------------------------------------------------
echo "Comparación con el sitio anterior\n";

$legacySource = (string) file_get_contents($legacyIndex);
preg_match_all('#include_once\("\./modulos/([a-z0-9_-]+)\.php"\)#i', $legacySource, $included);

/** @var array<int, string> $includedFiles */
$includedFiles = array_values(array_unique($included[1] ?? []));
$absent = [];
$missingTexts = [];

foreach ($includedFiles as $name) {
    $file = $legacyRoot . '/modulos/' . $name . '.php';

    if (!is_file($file)) {
        $absent[] = $name . '.php';
        $failed++;
        printf("  [FALLA] %-18s el archivo no existe\n", $name . '.php');

        continue;
    }

    $headings = headingsOf($file);

    if ($headings === []) {
        continue;
    }

    $lost = array_values(array_filter($headings, static fn (string $text): bool => !str_contains($html, $text)));

    if ($lost === []) {
        $passed++;
        printf("  [ok]    %-18s %d títulos, todos presentes\n", $name . '.php', count($headings));

        continue;
    }

    $failed++;
    printf("  [FALLA] %-18s no aparece: %s\n", $name . '.php', implode(' | ', $lost));
    $missingTexts = array_merge($missingTexts, $lost);
}

/** @var array<int, string> $unusedFiles */
$unusedFiles = [];

foreach (glob($legacyRoot . '/modulos/*.php') ?: [] as $file) {
    if (!in_array(basename($file, '.php'), $includedFiles, true)) {
        $unusedFiles[] = basename($file, '.php');
    }
}

check(
    'los archivos que el sitio anterior no usaba tampoco aparecen ('
        . (implode(', ', $unusedFiles) ?: 'ninguno') . ')',
    static function () use ($unusedFiles, $legacyRoot, $html): bool {
        foreach ($unusedFiles as $name) {
            $file = $legacyRoot . '/modulos/' . $name . '.php';

            if (!is_file($file)) {
                continue;
            }

            foreach (headingsOf($file) as $text) {
                if (str_contains($html, $text)) {
                    return false;
                }
            }
        }

        return true;
    }
);

// -----------------------------------------------------------------------------
// 2. Párrafos y datos concretos que conviene vigilar
//
// Los títulos ya se comparan solos; aquí se añaden textos largos y datos que es
// fácil perder sin que salte ninguna otra comprobación.
// -----------------------------------------------------------------------------
echo "\nTextos y datos concretos\n";

/** @var array<string, string> $details origen => texto que debe aparecer */
$details = [
    'about.php → propósito' => 'Propósito de Liderazgo Legal',
    'futures.php → área 1' => 'DERECHO LABORAL INDIVIDUAL Y COLECTIVO',
    'futures.php → área 4' => 'DERECHO CIVIL, FAMILIA Y COMERCIAL',
    'services.php → descripción' => 'negociaciones amistosas y acciones legales',
    'testimonials.php → cliente' => 'Perfumería Cosméticos y Farmacéuticos Limitada',
    'calls.php → botón' => 'Agendar cita',
    'portafolio.php → caso' => 'Negociación de retiro laboral',
    'team.php → cargo' => 'Socia Fundadora',
    'faq.php → pregunta' => 'Reglamento Interno de Trabajo',
    'contact.php → teléfonos' => '3188517817 - 3017543649',
    'footer.php → correo' => 'gerencia@gonzalezballesteros.com',
];

foreach ($details as $origin => $text) {
    check(sprintf('%s → "%s"', $origin, mb_substr($text, 0, 40)), static fn (): bool => str_contains($html, $text));
}

// -----------------------------------------------------------------------------
// 3. Secciones: existen, y sólo se dibujan las que tienen contenido
// -----------------------------------------------------------------------------
echo "\nSecciones\n";

/** @var array<string, array<string, mixed>> $declared */
$declared = Config::array('admin_labels.blocks');
$missing = [];
$publishedButNotDrawn = [];
$blank = [];

foreach ($declared as $key => $definition) {
    $block = $blocks->findByKey($key);

    if ($block === null) {
        $missing[] = $key;

        continue;
    }

    // El ancla es el identificador de la sección dentro de la página.
    $anchor = ltrim((string) ($definition['anchor'] ?? '#' . $key), '#');
    $drawn = str_contains($html, 'id="' . $anchor . '"');
    $visible = ($block['status'] ?? '') === 'active';
    $published = !empty($block['published_version_id']);

    if ($visible && $published && !$drawn) {
        $publishedButNotDrawn[] = $key;
    }

    if (!$published) {
        $blank[$key] = $drawn;
    }
}

check(sprintf('las %d secciones declaradas existen en la base de datos', count($declared)), fn () => $missing === []);
check('toda sección publicada y visible se dibuja en la página', fn () => $publishedButNotDrawn === []);
check(
    'las secciones todavía vacías no se dibujan (' . (implode(', ', array_keys($blank)) ?: 'ninguna') . ')',
    static fn (): bool => !in_array(true, $blank, true)
);

if ($missing !== []) {
    echo '        Faltan en la base de datos: ' . implode(', ', $missing) . "\n";
}

if ($publishedButNotDrawn !== []) {
    echo '        Publicadas y sin dibujar: ' . implode(', ', $publishedButNotDrawn) . "\n";
}

/** @var array<string, int> $expectedItems elementos esperados por sección */
$expectedItems = [
    'hero' => 3, 'about_vision' => 3, 'services' => 4, 'debt_recovery' => 4,
    'clients' => 3, 'cases' => 3, 'team' => 2, 'faq' => 4, 'contact_info' => 3, 'footer' => 9,
];

foreach ($expectedItems as $key => $expected) {
    $block = $blocks->findByKey($key);

    check(
        sprintf('%s tiene sus %d elementos', $key, $expected),
        static function () use ($pdo, $block, $expected): bool {
            if ($block === null) {
                return false;
            }

            return (int) $pdo->query(
                'SELECT COUNT(*) FROM block_items WHERE block_id = ' . (int) $block['id'] . ' AND deleted_at IS NULL'
            )->fetchColumn() === $expected;
        }
    );
}

// -----------------------------------------------------------------------------
// 4. Orden de las secciones, menú y accesibilidad
// -----------------------------------------------------------------------------
echo "\nOrden, menú y accesibilidad\n";

/** @var array<int, string> $legacyOrder como bajaban las secciones antes */
$legacyOrder = ['hero', 'about', 'services', 'recuperacion', 'testimonials', 'cta', 'casos', 'team', 'faq', 'contact'];

$drawnOrder = [];

foreach ($legacyOrder as $index => $anchor) {
    $position = strpos($html, 'id="' . $anchor . '"');

    if ($position !== false) {
        $drawnOrder[$position] = $anchor;
    }
}

ksort($drawnOrder);

check(
    'las secciones bajan en el mismo orden que antes',
    static function () use ($drawnOrder, $legacyOrder): bool {
        return array_values($drawnOrder) === $legacyOrder;
    }
);

if (array_values($drawnOrder) !== $legacyOrder) {
    echo '        Orden encontrado: ' . implode(' › ', array_values($drawnOrder)) . "\n";
}

$menuLabels = array_column($page['nav'], 'label');

check(
    'el menú reproduce los enlaces del sitio anterior',
    static fn (): bool => array_slice($menuLabels, 0, 7) === [
        'Inicio', 'Acerca de', 'Servicios', 'Nuestros clientes', 'Portafolio', 'Equipo', 'Contacto',
    ]
);

check(
    'cada enlace del menú lleva a una sección que existe',
    static function () use ($page, $html): bool {
        foreach ($page['nav'] as $link) {
            $anchor = ltrim((string) ($link['href'] ?? ''), '#');

            if ($anchor !== '' && $anchor !== '/' && !str_contains($html, 'id="' . $anchor . '"')) {
                return false;
            }
        }

        return true;
    }
);

$imageTags = [];

check(
    'todas las imágenes llevan texto alternativo',
    static function () use ($html, &$imageTags): bool {
        preg_match_all('/<img\b[^>]*>/i', $html, $matches);
        $imageTags = $matches[0];

        foreach ($imageTags as $tag) {
            if (!str_contains($tag, 'alt="')) {
                return false;
            }
        }

        return $imageTags !== [];
    }
);

printf("        %d imágenes en la página\n", count($imageTags));

// -----------------------------------------------------------------------------
// 5. Módulos de contenido
// -----------------------------------------------------------------------------
echo "\nMódulos de contenido\n";

$publicMenu = implode(' ', $menuLabels);

check(
    'los módulos sin página propia no se anuncian todavía',
    static fn (): bool => !str_contains($publicMenu, 'Cursos') && !str_contains($publicMenu, 'Noticias')
);

check('los cuatro módulos existen, listos para activarse', static fn (): bool => count($modules->all()) === 4);

// -----------------------------------------------------------------------------
// 6. Imágenes heredadas
// -----------------------------------------------------------------------------
echo "\nImágenes\n";

$legacy = (int) $pdo->query('SELECT COUNT(*) FROM media WHERE is_legacy = 1')->fetchColumn();

check(sprintf('las %d imágenes del sitio anterior están registradas', $legacy), static fn (): bool => $legacy >= 9);
check(
    'todas tienen variantes generadas y recortadas',
    static fn (): bool => (int) $pdo->query(
        'SELECT COUNT(*) FROM media WHERE is_legacy = 1 AND variants IS NOT NULL'
    )->fetchColumn() === $legacy
);
check(
    'conservan su original y su versión pública',
    static fn (): bool => (int) $pdo->query(
        'SELECT COUNT(*) FROM media WHERE is_legacy = 1 AND path <> "" AND public_path <> ""'
    )->fetchColumn() === $legacy
);

// -----------------------------------------------------------------------------
// 7. El panel ofrece un nombre legible para cada sección
// -----------------------------------------------------------------------------
echo "\nPanel de administración\n";

$withoutLabel = [];

foreach ($declared as $key => $definition) {
    $label = (string) ($definition['label'] ?? '');
    $help = (string) ($definition['help'] ?? '');

    if ($label === '' || $help === '' || str_contains($label . $help, '_')) {
        $withoutLabel[] = $key;
    }
}

check(
    'cada sección tiene nombre y explicación en lenguaje corriente'
        . ($withoutLabel !== [] ? ' — ' . implode(', ', $withoutLabel) : ''),
    static fn (): bool => $withoutLabel === []
);

// -----------------------------------------------------------------------------
// 8. Repetición de la carga: sin duplicados y sin tocar los archivos originales
//
// La carga se ejecuta dos veces seguidas con `php database/seed.php`, que no
// debe crear nada la segunda vez. Estas comprobaciones vigilan el rastro que
// dejaría una repetición defectuosa.
// -----------------------------------------------------------------------------
echo "\nRepetición de la carga\n";

/** @var array<string, string> $keyed tabla => columna que no puede repetirse */
$keyed = ['settings' => 'key_name', 'modules' => 'key_name', 'blocks' => 'key_name'];

foreach ($keyed as $table => $column) {
    check(
        sprintf('%s no tiene claves repetidas', $table),
        static function () use ($pdo, $table, $column): bool {
            $repeated = (int) $pdo->query(
                sprintf(
                    'SELECT COUNT(*) FROM (SELECT %s FROM %s GROUP BY %s HAVING COUNT(*) > 1) AS d',
                    $column,
                    $table,
                    $column
                )
            )->fetchColumn();

            return $repeated === 0;
        }
    );
}

check(
    'ninguna sección tiene dos elementos con el mismo título',
    static function () use ($pdo): bool {
        $repeated = (int) $pdo->query(
            'SELECT COUNT(*) FROM ('
            . ' SELECT block_id, title FROM block_items WHERE deleted_at IS NULL AND title IS NOT NULL'
            . ' GROUP BY block_id, title HAVING COUNT(*) > 1'
            . ') AS d'
        )->fetchColumn();

        return $repeated === 0;
    }
);

check(
    'ninguna imagen está registrada dos veces',
    static function () use ($pdo): bool {
        $repeated = (int) $pdo->query(
            'SELECT COUNT(*) FROM ('
            . ' SELECT checksum, profile, focal_x, focal_y FROM media'
            . ' GROUP BY checksum, profile, focal_x, focal_y HAVING COUNT(*) > 1'
            . ') AS d'
        )->fetchColumn();

        return $repeated === 0;
    }
);

check(
    'ninguna imagen comparte el mismo archivo público',
    static function () use ($pdo): bool {
        $repeated = (int) $pdo->query(
            'SELECT COUNT(*) FROM ('
            . ' SELECT public_path FROM media WHERE public_path IS NOT NULL AND public_path <> ""'
            . ' GROUP BY public_path HAVING COUNT(*) > 1'
            . ') AS d'
        )->fetchColumn();

        return $repeated === 0;
    }
);

// Las imágenes del sitio anterior se copian a storage, nunca se mueven: si
// desaparecieran de public/assets/img, el sitio antiguo quedaría roto.
$missingSources = [];

preg_match_all("/'file'\s*=>\s*'([^']+)'/", (string) file_get_contents(__DIR__ . '/../database/seeds/content.php'), $files);

foreach (array_unique($files[1] ?? []) as $relative) {
    if (!is_file(dirname(__DIR__) . '/public/assets/' . $relative)) {
        $missingSources[] = $relative;
    }
}

check(
    'las imágenes de origen siguen en su sitio (' . count($missingSources) . ' ausentes de '
        . count(array_unique($files[1] ?? [])) . ')',
    static fn (): bool => $missingSources === []
);

if ($missingSources !== []) {
    echo '        Ausentes: ' . implode(', ', $missingSources) . "\n";
}

// -----------------------------------------------------------------------------
// Resumen
// -----------------------------------------------------------------------------
echo str_repeat('=', 78) . "\n";
printf("Comprobaciones superadas: %d   fallidas: %d\n\n", $passed, $failed);

if ($missingTexts !== []) {
    echo "Falta trasladar estos títulos del sitio anterior:\n";

    foreach ($missingTexts as $text) {
        printf("  · %s\n", $text);
    }

    echo "\n";
}

echo "Diferencias deliberadas con el sitio anterior\n";
echo str_repeat('-', 78) . "\n";

/** @var array<int, string> $differences */
$differences = [
    'Los iconos de la sección de visión usan el color de la marca en los tres recuadros; '
        . 'antes iban de amarillo, azul y verde por separado.',
    'Las tarjetas de servicios llevaban un icono de visto bueno en verde; ahora usan el color de la marca.',
    'Los enlaces del pie "Aviso Legal" y "Política de Privacidad" apuntaban a "#" porque su página no '
        . 'existe. No se cargaron: se añadirán cuando se redacten.',
    'Los módulos Cursos, Plantillas, Videos y Noticias existen pero están desactivados, porque sus páginas '
        . 'se construyen en las etapas siguientes. Un enlace sin página sería un enlace roto.',
    'La franja de cifras y la tabla de precios eran archivos sueltos que ninguna página del sitio anterior '
        . 'incluía, y sus números estaban todos en cero. No se cargaron como contenido: la franja de cifras '
        . 'queda disponible y vacía en el panel para cuando el despacho decida qué cifras publicar.',
    'Cuatro imágenes del sitio anterior no llegan a la resolución recomendada: las tres del inicio '
        . '(612 px de ancho) y el fondo de la franja de invitación. Se cargaron sin ampliarlas para no '
        . 'perder nitidez; conviene reemplazarlas desde el panel.',
];

foreach ($differences as $index => $difference) {
    printf("%d. %s\n", $index + 1, $difference);
}

exit($failed === 0 ? 0 : 1);
