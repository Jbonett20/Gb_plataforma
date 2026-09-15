<?php

declare(strict_types=1);

/**
 * Comprobación de que todas las pantallas del panel responden (tarea 12.1).
 *
 * Recorre una a una las direcciones del panel con una cuenta de administración
 * y comprueba que ninguna devuelve un error, que el enlace del menú existe y que
 * la pantalla explica en español qué controla. Es la forma barata de detectar una
 * pantalla rota o un enlace del menú que apunta a la nada.
 *
 * Crea una cuenta de prueba y la borra al terminar. Uso:
 *   php tools/admin-screens-test.php
 */

use GB\Application;
use GB\Models\UserRepository;
use GB\Support\AdminMenu;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Router;
use GB\Support\Session;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();
$users = $container->get(UserRepository::class);
$pdo = $container->get(PDO::class);
$auth = $container->get(Auth::class);
$router = $container->get(Router::class);
$menu = $container->get(AdminMenu::class);
(require GB_BASE_PATH . '/config/routes.php')($router);

$failures = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '  [ok]    ' : '  [FALLA] ') . $label . PHP_EOL;

    if ($detail !== '') {
        echo '           ' . $detail . PHP_EOL;
    }

    if (!$ok) {
        $failures++;
    }
};

$email = 'zz-pantallas@example.invalid';
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$adminId = $users->create([
    'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Prueba', 'last_name' => 'Pantallas',
    'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
    'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
]);
$users->completeOnboarding($adminId);

$auditBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();

$send = static function (string $uri) use ($router): \GB\Support\Response {
    return $router->dispatch(new Request([], [], [
        'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '203.0.113.30',
    ], [], []));
};

echo 'Pantallas del panel (tarea 12.1)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // Sin sesión, ninguna pantalla se entrega
    $abiertas = 0;

    foreach ($menu->groups() as $group) {
        foreach ($group['items'] as $item) {
            if ($send('/' . $item['path'])->status() !== 302) {
                $abiertas++;
            }
        }
    }

    $check('sin sesión no se abre ninguna pantalla del menú', $abiertas === 0);

    $auth->attempt($email, 'Temporal-2026!', '203.0.113.30', ['SuperAdmin']);
    $check('la cuenta de administración entra', $auth->check());

    // Todas las pantallas del menú responden y explican qué hacen
    echo 'Enlaces del menú' . PHP_EOL;

    $sinRespuesta = [];
    $sinAyuda = [];
    $total = 0;

    foreach ($menu->groups() as $group) {
        foreach ($group['items'] as $item) {
            $total++;
            $response = $send('/' . $item['path']);

            if ($response->status() !== 200) {
                $sinRespuesta[] = $item['path'] . ' (' . $response->status() . ')';

                continue;
            }

            $help = (string) ($menu->helpFor($item['path']) ?? '');

            if ($help === '' || !str_contains($response->content(), $help)) {
                $sinAyuda[] = $item['path'];
            }
        }
    }

    $check('los ' . $total . ' enlaces del menú responden', $sinRespuesta === [],
        implode(', ', $sinRespuesta));
    $check('cada pantalla muestra su explicación', $sinAyuda === [], implode(', ', $sinAyuda));

    // El resumen y las pantallas de detalle que no cuelgan del menú
    echo 'Otras pantallas del panel' . PHP_EOL;

    foreach ([
        '/admin' => 'Resumen',
        '/admin/cursos/nuevo' => 'Nuevo curso',
        '/admin/plantillas/nueva' => 'Nueva plantilla',
        '/admin/videos/nuevo' => 'Nuevo video',
        '/admin/noticias/nueva' => 'Nueva noticia',
        '/admin/clave' => 'Cambiar contraseña',
    ] as $uri => $queEs) {
        $response = $send($uri);
        $check($queEs . ' (' . $uri . ') responde', $response->status() === 200, 'estado ' . $response->status());
    }

    // Las direcciones de detalle con un identificador inventado no rompen nada
    echo 'Direcciones con un identificador que no existe' . PHP_EOL;

    foreach ([
        '/admin/bloques/999999',
        '/admin/cursos/999999/editar',
        '/admin/cursos/999999/temario',
        '/admin/plantillas/999999/editar',
        '/admin/videos/999999/editar',
        '/admin/noticias/999999/editar',
        '/admin/estudiantes/999999',
        '/admin/modulos/999999/borrar',
    ] as $uri) {
        $response = $send($uri);
        $check('un identificador inventado no rompe ' . $uri,
            $response->status() < 500, 'estado ' . $response->status());
    }

    // Y tampoco un identificador que no es un número
    foreach (['/admin/bloques/abc', '/admin/cursos/abc/editar', '/admin/estudiantes/abc'] as $uri) {
        $response = $send($uri);
        $check('un identificador con letras no rompe ' . $uri, $response->status() < 500,
            'estado ' . $response->status());
    }
} finally {
    $pdo->prepare('DELETE FROM audit_logs WHERE id > ?')->execute([$auditBefore]);
    $pdo->exec('DELETE FROM users WHERE id = ' . $adminId);
    $pdo->exec('DELETE FROM throttle WHERE identifier LIKE "203.0.113.30%"');

    $session = GB_STORAGE_PATH . '/sessions/active/user-' . $adminId . '.json';

    if (is_file($session)) {
        unlink($session);
    }
}

$restantes = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

echo str_repeat('-', 70) . PHP_EOL;
echo 'Cuentas restantes: ' . $restantes . PHP_EOL;
echo $failures === 0 && $restantes === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $restantes === 0 ? 0 : 1);
