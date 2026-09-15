<?php

declare(strict_types=1);

/**
 * Comprobación del rendimiento del sitio público con la caché activa y
 * desactivada (tarea 12.9).
 *
 * Mide el tiempo que tarda el servidor en generar las páginas públicas más
 * visitadas, primero sin caché (cada visita consulta la base de datos y arma el
 * HTML) y después con la caché en marcha. La diferencia es lo que se gana al
 * mantener la caché activa, y también sirve para detectar que la caché esté
 * guardando algo que no debería: sólo se cachean páginas de visitantes sin
 * sesión.
 *
 * Uso:
 *   php tools/performance-test.php            (con caché, como en producción)
 *   php tools/performance-test.php --sin-cache
 */

use GB\Application;
use GB\Support\Config;
use GB\Support\Request;
use GB\Support\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$withoutCache = in_array('--sin-cache', $argv, true);

$container = Application::boot();

// Se apaga la caché antes de que el contenedor la construya (se construye en la
// primeraconsulta, y lee la configuración en ese momento).
if ($withoutCache) {
    Config::set('app.cache_enabled', false);
}

$router = $container->get(Router::class);
(require GB_BASE_PATH . '/config/routes.php')($router);

$cachePath = GB_STORAGE_PATH . '/cache';
$rounds = 12;

$pages = [
    'Portada' => '/',
    'Catálogo de cursos' => '/cursos',
    'Blog' => '/noticias',
    'Plantillas' => '/plantillas',
    'Contacto (formulario)' => '/contacto',
];

$request = static function (string $uri) use ($router): \GB\Support\Response {
    return $router->dispatch(new Request([], [], [
        'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '203.0.113.20',
    ], [], []));
};

echo 'Rendimiento del sitio público (tarea 12.9)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;
echo 'Modo: ' . ($withoutCache ? 'SIN caché' : 'CON caché') . PHP_EOL;

$checks = $container->get(\GB\Services\ViewCache::class)->enabled();
echo 'La caché está ' . ($checks ? 'encendida' : 'apagada') . PHP_EOL . PHP_EOL;

if (!$withoutCache) {
    // Se vacía para medir siempre lo mismo: la primera visita de cada página.
    foreach (glob($cachePath . '/*.html') ?: [] as $file) {
        @unlink($file);
    }
}

printf("%-24s %10s %10s %10s\n", 'Página', '1ª (ms)', 'media (ms)', 'límite (ms)');
echo str_repeat('-', 70) . PHP_EOL;

$slowest = 0.0;
$problems = [];
$results = [];

foreach ($pages as $label => $uri) {
    $durations = [];

    for ($i = 0; $i < $rounds; $i++) {
        $start = microtime(true);
        $response = $request($uri);
        $durations[] = (microtime(true) - $start) * 1000;

        if ($response->status() >= 500) {
            $problems[] = $label . ' responde ' . $response->status();
        }
    }

    $first = $durations[0];
    $average = array_sum($durations) / count($durations);
    $slowest = max($slowest, $average);
    $results[$label] = ['first' => $first, 'average' => $average];

    printf("%-24s %10.1f %10.1f %10.1f\n", $label, $first, $average, 400.0);
}

echo str_repeat('-', 70) . PHP_EOL;

$cacheFiles = count(glob($cachePath . '/*.html') ?: []);

echo 'Páginas guardadas en la caché: ' . $cacheFiles . PHP_EOL;
echo 'Página más lenta en promedio: ' . number_format($slowest, 1) . ' ms' . PHP_EOL;

$failures = count($problems);

foreach ($problems as $problem) {
    echo '  [FALLA] ' . $problem . PHP_EOL;
}

if ($slowest > 400.0) {
    echo '  [FALLA] Hay una página que supera los 400 ms de media.' . PHP_EOL;
    $failures++;
}

if (!$withoutCache && $cacheFiles === 0) {
    echo '  [FALLA] Con la caché encendida no se guardó ninguna página.' . PHP_EOL;
    $failures++;
}

if ($withoutCache && $cacheFiles > 0) {
    echo '  [FALLA] Con la caché apagada se guardaron páginas de todos modos.' . PHP_EOL;
    $failures++;
}

// Lo más importante de esta caché no es la velocidad, es lo que NO guarda: la
// página de alguien con sesión abierta no puede quedar almacenada para el
// siguiente visitante. Se comprueba que la caché no se toca con sesión.
if (!$withoutCache) {
    $users = $container->get(\GB\Models\UserRepository::class);
    $auth = $container->get(\GB\Support\Auth::class);

    $email = 'zz-rendimiento@example.invalid';
    $pdo = $container->get(PDO::class);
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    $userId = $users->create([
        'role_id' => $users->roleId('Student'), 'name' => 'Prueba', 'last_name' => 'Rendimiento',
        'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
        'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
    ]);

    try {
        $filesBefore = [];
        foreach (glob($cachePath . '/*.html') ?: [] as $file) {
            $filesBefore[basename($file)] = (int) filemtime($file);
        }

        $auth->attempt($email, 'Temporal-2026!', '203.0.113.20', ['Student']);
        $conSesion = $request('/');

        $filesAfter = [];
        foreach (glob($cachePath . '/*.html') ?: [] as $file) {
            $filesAfter[basename($file)] = (int) filemtime($file);
        }

        echo 'Con sesión abierta la caché no cambió: ' . ($filesBefore == $filesAfter ? 'sí' : 'no') . PHP_EOL;
        echo 'La página con sesión se genera al momento (' . number_format(strlen($conSesion->content()) / 1024, 1) . ' kB)' . PHP_EOL;

        if ($filesBefore != $filesAfter) {
            echo '  [FALLA] Una visita con sesión escribió en la caché pública.' . PHP_EOL;
            $failures++;
        }

        if ($conSesion->status() !== 200) {
            echo '  [FALLA] La portada con sesión responde ' . $conSesion->status() . PHP_EOL;
            $failures++;
        }
    } finally {
        $pdo->exec('DELETE FROM audit_logs WHERE entity_type = "user" AND entity_id = ' . $userId);
        $pdo->exec('DELETE FROM users WHERE id = ' . $userId);
        $session = GB_STORAGE_PATH . '/sessions/active/user-' . $userId . '.json';

        if (is_file($session)) {
            unlink($session);
        }
    }
}

echo $failures === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 ? 0 : 1);
