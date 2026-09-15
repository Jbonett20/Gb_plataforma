<?php

declare(strict_types=1);

/**
 * Comprobación de las cuentas y los roles del panel (tarea 11.11).
 *
 * Recorre lo que puede hacer quien administra con las cuentas de los demás:
 * crear una, entregar la contraseña temporal, cambiar un rol, desactivar y
 * volver a activar, y las dos negativas que protegen el propio acceso —no
 * cambiarse el rol a uno mismo y no quedarse sin ningún administrador—.
 *
 * Crea cuentas de prueba y las borra al terminar. Uso:
 *   php tools/accounts-test.php
 */

use GB\Application;
use GB\Models\UserRepository;
use GB\Support\Auth;
use GB\Support\Csrf;
use GB\Support\PasswordPolicy;
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
$policy = $container->get(PasswordPolicy::class);
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

$auditBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
$creadas = [];

$ip = '203.0.113.60';

$send = static function (string $method, string $uri, array $data = []) use ($router, $csrf, $ip): \GB\Support\Response {
    return $router->dispatch(new Request([], $data + ['_token' => $csrf->token()], [
        'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => $ip,
    ], [], []));
};

// Comprueba los avisos del panel: es donde se muestra la contraseña temporal.
$cuentas = $container->get(\GB\Services\AccountAdminService::class);

/** Último aviso del panel. */
$lastFlash = static function () use ($flash): string {
    $messages = $flash->pull();

    return (string) ($messages === [] ? '' : $messages[count($messages) - 1]['message']);
};

$crear = static function (string $email, string $role) use ($send): void {
    $send('POST', '/admin/usuarios', [
        'name' => 'Cuenta', 'last_name' => 'De Prueba', 'email' => $email, 'role' => $role,
    ]);
};

echo 'Cuentas y roles del panel (tarea 11.11)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // -------------------------------------------------- Primer administrador
    echo 'Crear la primera cuenta de administración' . PHP_EOL;

    $jefe = 'zz-jefe-' . bin2hex(random_bytes(3)) . '@example.invalid';
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$jefe]);
    $jefeId = $users->create([
        'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Jefe', 'last_name' => 'Prueba',
        'email' => $jefe, 'password_hash' => $password = $policy->hash('Temporal-2026!'),
        'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
    ]);
    $creadas[] = $jefeId;
    $users->completeOnboarding($jefeId);

    $check('entra al panel con la cuenta creada',
        $auth->attempt($jefe, 'Temporal-2026!', $ip, ['SuperAdmin']));

    // --------------------------------------------------- Crear otra cuenta
    echo 'Crear cuentas desde el panel' . PHP_EOL;

    $nuevoAdmin = 'zz-admin-nuevo-' . bin2hex(random_bytes(3)) . '@example.invalid';
    $crear($nuevoAdmin, 'SuperAdmin');
    $aviso = $lastFlash();

    $check('la cuenta de administración se crea', str_contains($aviso, 'Cuenta creada para'));
    $check('el aviso trae la contraseña temporal una sola vez',
        preg_match('/Contraseña temporal: (Gb-[a-z0-9]+)/', $aviso, $matches) === 1);

    $temporal = (string) ($matches[1] ?? '');
    $check('la contraseña temporal cumple la política', $temporal !== '' && $policy->passes($temporal, $nuevoAdmin),
        'generada: ' . ($temporal === '' ? '(vacía)' : 'sí'));

    $nuevoId = (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote($nuevoAdmin))->fetchColumn();
    $creadas[] = $nuevoId;

    $fila = $users->findById($nuevoId);
    $check('la cuenta queda con el rol indicado', ($fila['role_key'] ?? '') === 'SuperAdmin');
    $check('queda activa', ($fila['status'] ?? '') === 'active');
    $check('y obliga a cambiar la contraseña al entrar',
        (int) $pdo->query('SELECT must_change_password FROM users WHERE id = ' . $nuevoId)->fetchColumn() === 1);

    // La contraseña temporal sirve para entrar, aunque sólo una vez
    $session = $container->get(\GB\Support\Session::class);
    $session->flush();
    $check('la persona entra con la contraseña temporal', $auth->attempt($nuevoAdmin, $temporal, $ip, ['SuperAdmin']));
    $check('y el sistema le exige cambiarla en ese primer ingreso', $auth->mustChangePassword());

    // Volver a la sesión de quien administra
    $session->flush();
    $auth->attempt($jefe, 'Temporal-2026!', $ip, ['SuperAdmin']);

    // Datos que no deben crearse
    echo 'Lo que no se permite al crear' . PHP_EOL;

    $repetido = $lastFlash();
    $crear($nuevoAdmin, 'SuperAdmin');
    $check('no se puede repetir el correo', str_contains($lastFlash(), 'Ya existe una cuenta'));
    $check('y no se creó una segunda cuenta',
        (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email = ' . $pdo->quote($nuevoAdmin))->fetchColumn() === 1);

    $crear('zz-correo-malo@example.invalid', 'Root');
    $check('no se puede poner un rol que no existe', stripos($lastFlash(), 'opción válida') !== false
        || stripos($lastFlash(), 'rol') !== false);
    $check('y no se creó nada',
        (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email = "zz-correo-malo@example.invalid"')->fetchColumn() === 0);

    $send('POST', '/admin/usuarios', ['name' => 'X', 'email' => 'no-es-un-correo', 'role' => 'SuperAdmin']);
    $check('no se puede crear con un correo mal escrito', str_contains($lastFlash(), 'Revisa'));

    // ------------------------------------------------------- Roles y estados
    echo 'Cambiar rol y estado' . PHP_EOL;

    $send('POST', '/admin/usuarios/' . $nuevoId . '/rol', ['role' => 'Student']);
    $check('se puede cambiar el rol a estudiante',
        (string) $pdo->query('SELECT r.key_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ' . $nuevoId)->fetchColumn() === 'Student');

    $send('POST', '/admin/usuarios/' . $nuevoId . '/rol', ['role' => 'SuperAdmin']);
    $check('y volver a administrador',
        (string) $pdo->query('SELECT r.key_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ' . $nuevoId)->fetchColumn() === 'SuperAdmin');

    $send('POST', '/admin/usuarios/' . $nuevoId . '/estado', ['status' => 'inactive']);
    $check('se puede desactivar una cuenta',
        (string) $pdo->query('SELECT status FROM users WHERE id = ' . $nuevoId)->fetchColumn() === 'inactive');

    $session->flush();
    $check('y con la cuenta desactivada ya no se entra',
        !$auth->attempt($nuevoAdmin, $temporal, $ip, ['SuperAdmin']));

    $session->flush();
    $auth->attempt($jefe, 'Temporal-2026!', $ip, ['SuperAdmin']);

    $send('POST', '/admin/usuarios/' . $nuevoId . '/estado', ['status' => 'active']);
    $check('se puede volver a activar',
        (string) $pdo->query('SELECT status FROM users WHERE id = ' . $nuevoId)->fetchColumn() === 'active');

    // ------------------------------------------- Protección del propio acceso
    echo 'Protección del propio acceso' . PHP_EOL;

    $send('POST', '/admin/usuarios/' . $jefeId . '/rol', ['role' => 'Student']);
    $check('nadie se quita su propio rol', str_contains($lastFlash(), 'tu propio rol'));
    $check('y el rol no cambió',
        (string) $pdo->query('SELECT r.key_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ' . $jefeId)->fetchColumn() === 'SuperAdmin');

    $send('POST', '/admin/usuarios/' . $jefeId . '/estado', ['status' => 'inactive']);
    $check('nadie desactiva su propia cuenta', str_contains($lastFlash(), 'tu propia cuenta'));

    // Con dos administradores activos se puede degradar al otro
    $check('hay dos administradores activos', $users->countActiveAdmins() === 2);
    $send('POST', '/admin/usuarios/' . $nuevoId . '/rol', ['role' => 'Student']);
    $check('con dos administradores sí se puede degradar a otro', $users->countActiveAdmins() === 1);

    // La red de seguridad del servicio: no se puede degradar al último
    // administrador aunque la petición llegue por otra vía. Desde el panel esta
    // situación no se alcanza, porque quien la pide es también administrador
    // activo (y si es el último, es él mismo, lo que ya se impide antes).
    $resultadoServicio = $cuentas->changeRole($jefeId, 'Student', 0);
    $check('la red de seguridad impide quitar el rol al último administrador',
        !$resultadoServicio->succeeded() && str_contains($resultadoServicio->message(), 'última cuenta'),
        $resultadoServicio->message());
    $check('y el administrador sigue siéndolo', $users->countActiveAdmins() === 1);

    $resultadoServicio = $cuentas->changeStatus($jefeId, 'inactive', 0);
    $check('tampoco se puede desactivar al último administrador',
        !$resultadoServicio->succeeded() && $users->countActiveAdmins() === 1);

    // ----------------------------------------------------------- Auditoría
    echo 'Registro de lo ocurrido' . PHP_EOL;

    $acciones = array_column($pdo->query(
        'SELECT DISTINCT action FROM audit_logs WHERE id > ' . $auditBefore
    )->fetchAll(PDO::FETCH_ASSOC), 'action');

    $check('queda registrada la creación de la cuenta', in_array('account_created', $acciones, true));
    $check('queda registrado el cambio de rol', in_array('role_changed', $acciones, true));
} finally {
    foreach (array_unique($creadas) as $id) {
        $pdo->exec('DELETE FROM audit_logs WHERE entity_type = "user" AND entity_id = ' . $id);
        $session = GB_STORAGE_PATH . '/sessions/active/user-' . $id . '.json';

        if (is_file($session)) {
            unlink($session);
        }
    }

    $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute(['zz-%@example.invalid']);
    $pdo->prepare('DELETE FROM audit_logs WHERE id > ?')->execute([$auditBefore]);
    $pdo->exec('DELETE FROM throttle WHERE identifier LIKE "203.0.113.60%"');
}

$restantes = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

echo str_repeat('-', 70) . PHP_EOL;
echo 'Cuentas restantes: ' . $restantes . PHP_EOL;
echo $failures === 0 && $restantes === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $restantes === 0 ? 0 : 1);
