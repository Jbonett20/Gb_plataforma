<?php

declare(strict_types=1);

/**
 * Comprobación de las confirmaciones del panel (tarea 11.6).
 *
 * Verifica tres cosas que no se ven en una inspección rápida del código:
 *   1. que cada operación deja un aviso visible y que ese aviso se muestra una
 *      sola vez;
 *   2. que antes de una acción destructiva se avisa de su efecto;
 *   3. que el borrado definitivo exige dos pasos, un pase emitido por el
 *      servidor y la casilla de confirmación marcada.
 *
 * Crea datos de prueba y los borra al terminar, restaurando el estado del módulo
 * que utiliza. Uso:
 *   php tools/admin-confirmations-test.php
 */

use GB\Application;
use GB\Models\UserRepository;
use GB\Support\Auth;
use GB\Support\Csrf;
use GB\Support\Request;
use GB\Support\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$_SERVER = [
    'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin', 'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1',
];
$_GET = [];

$container = Application::boot();
$users = $container->get(UserRepository::class);
$pdo = $container->get(PDO::class);
$auth = $container->get(Auth::class);
$router = $container->get(Router::class);
$csrf = $container->get(Csrf::class);
(require GB_BASE_PATH . '/config/routes.php')($router);

$failures = 0;

$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? '  [ok]    ' : '  [FALLA] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$email = 'zz-aviso-prueba@example.invalid';
$slug = 'zz-aviso-noticia-prueba';
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);

$adminId = $users->create([
    'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Prueba', 'last_name' => 'Avisos',
    'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
    'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
]);
$users->completeOnboarding($adminId);
$auth->attempt($email, 'Temporal-2026!', '127.0.0.1', ['SuperAdmin']);

$moduleBefore = $pdo->query('SELECT * FROM modules WHERE key_name = "news"')->fetch(PDO::FETCH_ASSOC);
// Hasta dónde llegaba la auditoría antes de la prueba: la limpieza no puede
// borrar el historial que ya existía en la base de datos.
$auditBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();

$post = static function (string $uri, array $data) use ($router, $csrf): \GB\Support\Response {
    $server = [
        'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1',
    ];

    return $router->dispatch(new Request([], $data + ['_token' => $csrf->token()], $server, [], []));
};

$get = static function (string $uri) use ($router): \GB\Support\Response {
    $server = [
        'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1',
    ];

    return $router->dispatch(new Request([], [], $server, [], []));
};

$cleanup = static function () use ($pdo, $adminId, $moduleBefore, $slug, $auditBefore): void {
    $pdo->exec('DELETE FROM news WHERE slug = ' . $pdo->quote($slug));
    $pdo->exec('DELETE FROM news');

    $pdo->exec('DELETE FROM modules WHERE key_name = ' . $pdo->quote((string) $moduleBefore['key_name']));
    $pdo->exec('INSERT INTO modules
        (id, key_name, label, menu_label, description, status, sort_order, show_in_menu, show_on_home,
         deleted_at, purge_after, created_at, updated_at)
        VALUES ('
        . (int) $moduleBefore['id'] . ', '
        . $pdo->quote((string) $moduleBefore['key_name']) . ', '
        . $pdo->quote((string) $moduleBefore['label']) . ', '
        . $pdo->quote((string) $moduleBefore['menu_label']) . ', '
        . $pdo->quote((string) $moduleBefore['description']) . ', '
        . $pdo->quote((string) $moduleBefore['status']) . ', '
        . (int) $moduleBefore['sort_order'] . ', '
        . (int) $moduleBefore['show_in_menu'] . ', '
        . (int) $moduleBefore['show_on_home'] . ', NULL, NULL, '
        . $pdo->quote((string) $moduleBefore['created_at']) . ', '
        . $pdo->quote((string) $moduleBefore['updated_at']) . ')');

    $pdo->exec('DELETE FROM audit_logs WHERE id > ' . $auditBefore);
    $pdo->exec('DELETE FROM users WHERE id = ' . $adminId);

    $file = GB_STORAGE_PATH . '/sessions/active/user-' . $adminId . '.json';

    if (is_file($file)) {
        unlink($file);
    }

    $pdo->exec('DELETE FROM throttle WHERE identifier = "0.0.0.0"');
};

echo 'Confirmaciones del panel (tarea 11.6)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // 1. Aviso al crear una noticia
    echo 'Aviso de confirmación' . PHP_EOL;
    $response = $post('/admin/noticias', [
        'title' => 'Noticia de prueba de avisos', 'slug' => $slug, 'body' => 'Contenido de prueba.',
        'status' => 'draft', 'category' => 'Pruebas',
    ]);
    $check('al crear se redirige a la lista', $response->status() === 302);

    $body = $get('/admin/noticias')->content();
    $check('se confirma la creación en pantalla', str_contains($body, 'Noticia de prueba de avisos')
        && str_contains($body, 'se cre'));
    $check('se avisa de que sigue como borrador', str_contains($body, 'borrador'));

    $body = $get('/admin/noticias')->content();
    $check('el aviso no se repite al recargar', !str_contains($body, 'se cre'));

    $newsId = (int) $pdo->query('SELECT id FROM news WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
    $check('la noticia quedó guardada', $newsId > 0);

    // 2. Advertencia de impacto al retirar contenido
    echo 'Advertencia antes de una acción destructiva' . PHP_EOL;
    $post('/admin/noticias/' . $newsId, ['_method' => 'DELETE']);
    $body = $get('/admin/noticias')->content();
    $check('al retirar una noticia se confirma con aviso de papelera', str_contains($body, 'restaurarla durante'));
    $check('la noticia ya no se lista', !str_contains($body, $slug));

    // Las listas vacías no dibujan botones: se comprueba con un curso temporal y,
    // para el resto, que la vista que los dibuja lleve la advertencia puesta.
    $courseId = $container->get(\GB\Models\CourseRepository::class)->createAdmin([
        'title' => 'Curso temporal de prueba', 'slug' => 'zz-curso-temporal-prueba',
        'access_type' => 'free', 'price' => '0.00', 'currency' => 'EUR', 'status' => 'draft',
        'created_by' => $adminId, 'updated_by' => $adminId,
    ]);
    $check('la lista de cursos advierte al retirar un curso',
        str_contains($get('/admin/cursos')->content(), 'onclick="return confirm('));
    $pdo->exec('DELETE FROM courses WHERE id = ' . $courseId);

    foreach (['templates' => 'plantilla', 'videos' => 'video', 'news' => 'noticia', 'syllabus' => 'lección'] as $view => $que) {
        $source = (string) file_get_contents(GB_APP_PATH . '/Views/admin/' . $view . '.php');
        $check('la vista de ' . $view . ' advierte al retirar ' . $que, substr_count($source, 'return confirm(') >= 1);
    }

    // 3. El borrado definitivo exige dos pasos
    echo 'Borrado definitivo en dos pasos' . PHP_EOL;
    $moduleId = (int) $moduleBefore['id'];

    $response = $get('/admin/modulos/' . $moduleId . '/borrar');
    $check('sin pasar por la papelera no se ofrece borrar', $response->status() === 302);
    $check('se explica que hacen falta dos pasos',
        str_contains($get('/admin/modulos')->content(), 'primero marca el m'));

    $post('/admin/modulos/' . $moduleId, ['status' => 'deleted']);
    $body = $get('/admin/modulos')->content();
    $check('mandar a la papelera confirma y avisa del plazo', str_contains($body, '30 d'));
    $check('aparece el botón de borrado definitivo', str_contains($body, 'Borrar definitivamente'));

    $body = $get('/admin/modulos/' . $moduleId . '/borrar')->content();
    $check('la pantalla de advertencia se abre', $body !== '' && str_contains($body, 'irreversible'));
    $check('cuenta lo que se pierde', str_contains($body, 'noticia'));
    $check('pide marcar una casilla', str_contains($body, 'acknowledge'));
    $check('incluye un pase de confirmación',
        preg_match('/name="token" value="[0-9a-f]{32}"/', $body) === 1);

    preg_match('/name="token" value="([0-9a-f]{32})"/', $body, $matches);
    $pass = (string) ($matches[1] ?? '');

    // 4. Sin la casilla marcada no se borra
    $post('/admin/modulos/' . $moduleId . '/borrar', ['token' => $pass]);
    $check('sin marcar la casilla no se borra',
        (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE id = ' . $moduleId)->fetchColumn() === 1);
    $check('se avisa de la casilla que falta',
        str_contains($get('/admin/modulos/' . $moduleId . '/borrar')->content(), 'casilla'));

    // 5. Con un pase inventado tampoco
    $post('/admin/modulos/' . $moduleId . '/borrar', ['token' => str_repeat('a', 32), 'acknowledge' => '1']);
    $check('un pase inventado se rechaza', str_contains($get('/admin/modulos')->content(), 'no es v'));
    $check('el módulo sigue existiendo',
        (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE id = ' . $moduleId)->fetchColumn() === 1);
    $check('el contenido sigue intacto',
        (int) $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn() === 1);

    // 6. Con el pase correcto sí se borra
    $post('/admin/modulos/' . $moduleId . '/borrar', ['token' => $pass, 'acknowledge' => '1']);
    $body = $get('/admin/modulos')->content();
    $check('se confirma el borrado definitivo', str_contains($body, 'definitivamente'));
    $check('el módulo desapareció',
        (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE id = ' . $moduleId)->fetchColumn() === 0);
    $check('su contenido desapareció',
        (int) $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn() === 0);

    $audit = $pdo->query('SELECT action FROM audit_logs WHERE entity_type = "module" ORDER BY id DESC LIMIT 1')->fetchColumn();
    $check('queda registrado en la auditoría', $audit === 'module_purged');

    // 7. El pase no se puede reutilizar
    $post('/admin/modulos/9/borrar', ['token' => $pass, 'acknowledge' => '1']);
    $check('el pase no sirve dos veces',
        (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE id = 9')->fetchColumn() === 1);
} finally {
    $cleanup();
}

echo str_repeat('-', 70) . PHP_EOL;
echo $failures === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 ? 0 : 1);
