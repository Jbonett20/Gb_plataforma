<?php

declare(strict_types=1);

/**
 * Comprobación de los ajustes editables del sitio.
 *
 * Comprueba lo que promete la pantalla de Ajustes: que los textos del encabezado
 * salen de la base de datos, que se pueden cambiar desde el panel y que el sitio
 * público cambia en el momento, sin tocar código ni cachés a mano.
 *
 * Deja los ajustes como estaban (los borra si no existían). Uso:
 *   php tools/settings-test.php
 */

use GB\Application;
use GB\Models\SettingRepository;
use GB\Models\UserRepository;
use GB\Support\Auth;
use GB\Support\Csrf;
use GB\Support\Request;
use GB\Support\Router;

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
$csrf = $container->get(Csrf::class);
$settings = $container->get(SettingRepository::class);
$flash = $container->get(\GB\Support\Flash::class);
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

// Estado previo: al terminar se deja igual
$claves = ['header_login_label', 'header_register_label', 'header_buttons_visible'];
$antes = [];

foreach ($claves as $clave) {
    $row = $pdo->query('SELECT value FROM settings WHERE key_name = ' . $pdo->quote($clave))->fetchColumn();
    $antes[$clave] = $row === false ? null : (string) $row;
}

$auditBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
$ip = '203.0.113.80';

$send = static function (string $method, string $uri, array $data = []) use ($router, $csrf, $ip): \GB\Support\Response {
    return $router->dispatch(new Request([], $method === 'GET' ? [] : $data + ['_token' => $csrf->token()], [
        'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => $ip,
    ], [], []));
};

$session = $container->get(\GB\Support\Session::class);
$adminEmail = 'zz-ajustes@example.invalid';

/**
 * Mira el sitio como un visitante cualquiera: sin sesión abierta.
 *
 * Es la única forma de comprobar lo que ve quien llega de fuera: el pie cambia
 * según haya sesión o no, así que se mira siempre desde fuera.
 */
$comoVisitante = static function (string $uri) use ($send, $session): string {
    $session->flush();

    return $send('GET', $uri)->content();
};

$encabezado = static function () use ($comoVisitante): string {
    preg_match('/<div class="header-account.*?<\/div>/s', $comoVisitante('/'), $matches);

    return (string) ($matches[0] ?? '');
};

$lastFlash = static function () use ($flash): string {
    $messages = $flash->pull();

    return (string) ($messages === [] ? '' : $messages[count($messages) - 1]['message']);
};

/** Vuelve a dejar los ajustes como estaban antes de la prueba. */
$restore = static function () use ($pdo, $antes): void {
    foreach ($antes as $clave => $valor) {
        if ($valor === null) {
            $pdo->exec('DELETE FROM settings WHERE key_name = ' . $pdo->quote($clave));

            continue;
        }

        $pdo->exec('UPDATE settings SET value = ' . $pdo->quote($valor) . ' WHERE key_name = ' . $pdo->quote($clave));
    }
};

echo 'Ajustes editables desde el panel' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // ------------------------------------------------- Ajustes por defecto
    echo 'Lo que se ve sin cambiar nada' . PHP_EOL;

    foreach (glob(GB_STORAGE_PATH . '/cache/*') ?: [] as $file) {
        @unlink($file);
    }

    $html = $encabezado();
    $check('sin tocar nada, el encabezado ofrece entrar', str_contains($html, 'Inicia sesión'));
    $check('y ofrece crear cuenta', str_contains($html, 'Regístrate'));
    $check('los dos apuntan al área de estudiantes',
        str_contains($html, '/ingresar') && str_contains($html, '/registro'));

    $contenido = $comoVisitante('/');
    $check('el encabezado no anuncia el panel de administración',
        !str_contains($html, '/admin'));
    $check('y el sitio público no enlaza la puerta del panel en ningún sitio',
        !str_contains($contenido, '/admin/ingresar'));
    $check('ya no hay un botón EMPEZAR en el encabezado',
        !str_contains($html, 'EMPEZAR'));

    // ------------------------------------------------------- Editar el texto
    echo 'Cambiar los textos desde el panel' . PHP_EOL;

    $email = 'zz-ajustes@example.invalid';
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    $adminId = $users->create([
        'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Prueba', 'last_name' => 'Ajustes',
        'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
        'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
    ]);
    $users->completeOnboarding($adminId);

    /**
     * Envía un formulario del panel.
     *
     * Vuelve a entrar antes de cada envío: las comprobaciones del sitio público
     * vacían la sesión a propósito, y sin sesión el panel redirige sin guardar.
     *
     * @param array<string, mixed> $data
     */
    $panelPost = static function (string $uri, array $data) use ($send, $auth, $adminEmail, $ip): \GB\Support\Response {
        $auth->attempt($adminEmail, 'Temporal-2026!', $ip, ['SuperAdmin']);

        return $send('POST', $uri, $data);
    };

    $auth->attempt($email, 'Temporal-2026!', $ip, ['SuperAdmin']);

    $check('la pantalla de ajustes se abre', $send('GET', '/admin/ajustes/encabezado')->status() === 200);

    $panelPost('/admin/ajustes/encabezado', [
        'header_login_label' => 'Entrar',
        'header_register_label' => 'Quiero mi cuenta',
        'header_buttons_visible' => '1',
    ]);
    $check('se confirma el guardado', str_contains($lastFlash(), 'guardado'));

    $html = $encabezado();
    $check('el encabezado usa el texto nuevo para entrar', str_contains($html, 'Entrar') && !str_contains($html, 'Inicia sesión'));
    $check('y el texto nuevo para crear cuenta', str_contains($html, 'Quiero mi cuenta'));
    $check('los datos quedaron guardados en la base',
        $settings->get('header_login_label') === 'Entrar');

    $acciones = array_column($pdo->query('SELECT DISTINCT action FROM audit_logs WHERE id > ' . $auditBefore)->fetchAll(PDO::FETCH_ASSOC), 'action');
    $check('el cambio queda en la auditoría', in_array('setting_changed', $acciones, true));

    // ------------------------------------------------- Con la sesión abierta
    // Con sesión abierta el encabezado no invita a entrar: dice quién eres y
    // deja cerrar la sesión. Las dos puertas son para quien no ha entrado.
    $auth->attempt($email, 'Temporal-2026!', $ip, ['SuperAdmin']);

    $paginaConSesion = $send('GET', '/')->content();

    $check('con la sesión abierta el encabezado muestra el nombre', str_contains($paginaConSesion, 'Prueba Ajustes'));
    $check('y dice que hay una sesión abierta', str_contains($paginaConSesion, 'Sesión abierta como'));
    $check('y deja cerrarla desde cualquier página', str_contains($paginaConSesion, 'Cerrar sesión')
        && str_contains($paginaConSesion, '/salir'));
    $check('y ya no invita a crear cuenta', !str_contains($paginaConSesion, 'Quiero mi cuenta'));

    // -------------------------------------------------------- Ocultar botones
    echo 'Apagar los botones' . PHP_EOL;

    // La casilla sin marcar no llega en el envío: apagarla es simplemente no enviarla.
    $panelPost('/admin/ajustes/encabezado', [
        'header_login_label' => 'Entrar', 'header_register_label' => 'Quiero mi cuenta',
    ]);
    $html = $encabezado();
    $check('al apagar la casilla desaparecen los botones del encabezado',
        !str_contains($html, 'Quiero mi cuenta') && !str_contains($html, 'Entrar'), $html);
    $check('el ajuste quedó apagado', $settings->get('header_buttons_visible', '1') === '0');
    $check('pero el formulario de acceso sigue existiendo por su dirección',
        $send('GET', '/ingresar')->status() === 200);
    $check('y el pie sigue ofreciendo crear cuenta', str_contains($comoVisitante('/'), '/registro'));

    // -------------------------------------------------------- Datos inválidos
    echo 'Lo que no se acepta' . PHP_EOL;

    $antesDeInvalidos = $settings->get('header_login_label');
    $respuesta = $panelPost('/admin/ajustes/encabezado', [
        'header_login_label' => str_repeat('x', 60),
        'header_register_label' => 'Quiero mi cuenta',
        'header_buttons_visible' => '1',
    ]);
    $check('un texto demasiado largo se rechaza', $respuesta->status() === 422);
    $check('y no se guardó nada', $settings->get('header_login_label') === $antesDeInvalidos);

    $panelPost('/admin/ajustes/encabezado', [
        'header_login_label' => 'Entrar', 'header_register_label' => 'Quiero mi cuenta',
        'header_buttons_visible' => '1', 'campo_inventado' => 'esto no existe',
    ]);
    $check('un campo que no está declarado no se guarda',
        (int) $pdo->query('SELECT COUNT(*) FROM settings WHERE key_name = "campo_inventado"')->fetchColumn() === 0);
} finally {
    $restore();

    // La caché guardada puede tener los textos de prueba: se vacía
    foreach (glob(GB_STORAGE_PATH . '/cache/*') ?: [] as $file) {
        @unlink($file);
    }

    if (isset($adminId)) {
        $pdo->exec('DELETE FROM audit_logs WHERE entity_type = "user" AND entity_id = ' . $adminId);
        $pdo->exec('DELETE FROM users WHERE id = ' . $adminId);
        $session = GB_STORAGE_PATH . '/sessions/active/user-' . $adminId . '.json';

        if (is_file($session)) {
            unlink($session);
        }
    }

    $pdo->exec('DELETE FROM audit_logs WHERE id > ' . $auditBefore);
    $pdo->exec('DELETE FROM throttle WHERE identifier LIKE "203.0.113.80%"');
}

$restantes = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email LIKE "zz-%"')->fetchColumn();
$ajustesDePrueba = (int) $pdo->query('SELECT COUNT(*) FROM settings WHERE key_name LIKE "zz-%"')->fetchColumn();

// La cuenta de administración real del despacho puede seguir ahí: esta prueba
// sólo responde de las suyas.
echo str_repeat('-', 70) . PHP_EOL;
echo 'Cuentas de prueba restantes: ' . $restantes . ' — ajustes de prueba: ' . $ajustesDePrueba . PHP_EOL;
echo $failures === 0 && $restantes === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $restantes === 0 ? 0 : 1);
