<?php

declare(strict_types=1);

/**
 * Comprobación de la limitación de intentos (tarea 12.5).
 *
 * Recorre los cinco formularios que pueden usarse para molestar o para probar
 * contraseñas —inicio de sesión de estudiante y de administración, registro,
 * recuperación de contraseña, contacto y suscripción— y comprueba que cada uno
 * corta al superar su límite y dice cuánto hay que esperar.
 *
 * Se usa una dirección de origen propia de la prueba para no interferir con los
 * límites reales, y se borran sus contadores al terminar. Uso:
 *   php tools/rate-limit-test.php
 */

use GB\Application;
use GB\Support\Csrf;
use GB\Support\Request;
use GB\Support\Router;
use GB\Support\Session;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();
$pdo = $container->get(PDO::class);
$router = $container->get(Router::class);
$csrf = $container->get(Csrf::class);
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

// Dirección de origen propia: los contadores de esta prueba son sólo suyos.
$ip = '203.0.113.42';
$stamp = bin2hex(random_bytes(4));

$state = [
    'audit' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn(),
    'appointments' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM appointments')->fetchColumn(),
    'subscribers' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM subscribers')->fetchColumn(),
];

/**
 * Envía una petición y devuelve el estado de la respuesta.
 *
 * Antes de cada envío se vacía la sesión: así se comprueba lo que ve alguien
 * que llega de fuera sin cuenta, que es justo el caso que hay que frenar. Sin
 * eso, el primer registro con éxito deja la sesión abierta y las siguientes
 * peticiones ni siquiera llegan al límite, porque el middleware de invitado las
 * desvía antes.
 *
 * @return array{status: int, body: string}
 */
$send = static function (string $uri, array $post) use ($router, $container, $ip): array {
    $container->get(Session::class)->flush();

    $token = (string) $container->get(Csrf::class)->token();

    $server = [
        'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => $ip,
    ];

    $response = $router->dispatch(new Request([], $post + ['_token' => $token], $server, [], []));

    return ['status' => $response->status(), 'body' => $response->content()];
};

/**
 * Insiste hasta pasarse del límite y devuelve los estados obtenidos.
 *
 * @return array<int, int>
 */
$hammer = static function (string $uri, array $post, int $times) use ($send): array {
    $statuses = [];

    for ($i = 0; $i < $times; $i++) {
        $statuses[] = $send($uri, $post)['status'];
    }

    return $statuses;
};

echo 'Limitación de intentos (tarea 12.5)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // ------------------------------------------------------ Inicio de sesión
    echo 'Inicio de sesión de estudiante (5 intentos cada 15 minutos)' . PHP_EOL;
    $email = 'zz-limite-' . $stamp . '@example.invalid';
    $statuses = $hammer('/ingresar', ['email' => $email, 'password' => 'intento-fallido'], 7);
    $check('los primeros intentos se atienden y el sexto corta',
        !in_array(429, array_slice($statuses, 0, 5), true) && end($statuses) === 429,
        'estados: ' . implode(' ', $statuses));

    $blocked = $send('/ingresar', ['email' => $email, 'password' => 'intento-fallido']);
    $check('el aviso dice cuánto hay que esperar',
        str_contains($blocked['body'], 'Espera') && str_contains($blocked['body'], 'minutos'),
        trim((string) preg_replace('/\s+/', ' ', strip_tags($blocked['body']))));
    $check('y no revela si la cuenta existe',
        !str_contains($blocked['body'], 'no existe'));

    // El límite es por cuenta: otra cuenta no queda bloqueada por el mismo origen
    $otro = $send('/ingresar', ['email' => 'zz-otra-' . $stamp . '@example.invalid', 'password' => 'x']);
    $check('el límite se cuenta por cuenta, no sólo por origen', $otro['status'] !== 429);

    // ------------------------------------------------------ Panel de gestión
    echo 'Inicio de sesión de administración' . PHP_EOL;
    $adminEmail = 'zz-admin-' . $stamp . '@example.invalid';
    $statuses = $hammer('/admin/ingresar', ['email' => $adminEmail, 'password' => 'intento-fallido'], 7);
    $check('el panel también corta al superar el límite', end($statuses) === 429,
        'estados: ' . implode(' ', $statuses));

    // -------------------------------------------------------------- Registro
    echo 'Registro de estudiante (5 intentos cada hora)' . PHP_EOL;
    $registro = 'zz-registro-' . $stamp . '@example.invalid';
    $statuses = $hammer('/registro', [
        'name' => 'Prueba', 'last_name' => 'Límite', 'email' => $registro,
        'password' => 'ClaveTemporal-2026!', 'password_confirmation' => 'ClaveTemporal-2026!',
        'phone' => '3000000000', 'terms' => '1',
    ], 7);
    $check('el registro corta al superar el límite', in_array(429, $statuses, true),
        'estados: ' . implode(' ', $statuses));
    $check('no se crearon cuentas de más',
        (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email = ' . $pdo->quote($registro))->fetchColumn() <= 1);

    // -------------------------------------------------- Recuperación de clave
    echo 'Recuperación de contraseña (3 envíos cada hora)' . PHP_EOL;
    $recuperar = 'zz-recuperar-' . $stamp . '@example.invalid';
    $statuses = $hammer('/recuperar-contrasena', ['email' => $recuperar], 5);
    $check('la recuperación corta al superar el límite', end($statuses) === 429,
        'estados: ' . implode(' ', $statuses));

    // ------------------------------------------------- Formularios públicos
    echo 'Formularios públicos' . PHP_EOL;
    $statuses = $hammer('/contacto', [
        'name' => 'Prueba Límite', 'email' => 'zz-contacto-' . $stamp . '@example.invalid',
        'phone' => '3000000000', 'message' => 'Mensaje de prueba del límite de intentos.', 'terms' => '1',
    ], 7);
    $check('el formulario de contacto corta al superar el límite', in_array(429, $statuses, true),
        'estados: ' . implode(' ', $statuses));

    $statuses = $hammer('/suscripcion', [
        'name' => 'Prueba', 'last_name' => 'Límite', 'email' => 'zz-suscripcion-' . $stamp . '@example.invalid',
        'phone' => '3000000000', 'terms' => '1',
    ], 7);
    $check('la suscripción corta al superar el límite', in_array(429, $statuses, true),
        'estados: ' . implode(' ', $statuses));

    // ---------------------------------------------------- Registro del corte
    $marcados = (int) $pdo->query('SELECT COUNT(*) FROM throttle WHERE blocked_until IS NOT NULL')->fetchColumn();
    $check('los cortes quedan registrados para poder revisarlos', $marcados > 0);
} finally {
    $pdo->prepare('DELETE FROM throttle WHERE identifier LIKE ?')->execute(['%' . $stamp . '%']);
    $pdo->prepare('DELETE FROM throttle WHERE identifier LIKE ?')->execute([$ip . '%']);
    $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute(['zz-%' . $stamp . '%']);
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute(['zz-registro-' . $stamp . '@example.invalid']);
    $pdo->prepare('DELETE FROM subscribers WHERE id > ?')->execute([$state['subscribers']]);
    $pdo->prepare('DELETE FROM appointments WHERE id > ?')->execute([$state['appointments']]);
    $pdo->prepare('DELETE FROM password_resets WHERE email LIKE ?')->execute(['zz-%' . $stamp . '%']);
    $pdo->prepare('DELETE FROM audit_logs WHERE id > ?')->execute([$state['audit']]);
}

$leftoverUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email LIKE "zz-%"')->fetchColumn();
$leftoverRows = (int) $pdo->query('SELECT COUNT(*) FROM subscribers WHERE id > ' . $state['subscribers'])->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM appointments WHERE id > ' . $state['appointments'])->fetchColumn();

echo str_repeat('-', 70) . PHP_EOL;
echo 'Cuentas de prueba restantes: ' . $leftoverUsers . ' — otros registros: ' . $leftoverRows . PHP_EOL;
echo $failures === 0 && $leftoverUsers === 0 && $leftoverRows === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $leftoverUsers === 0 && $leftoverRows === 0 ? 0 : 1);
