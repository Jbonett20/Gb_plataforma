<?php

declare(strict_types=1);

/**
 * Comprobación de inyección, código incrustado y falsificación de peticiones
 * (tarea 12.3).
 *
 * Los tres ataques se lanzan contra los formularios y las búsquedas reales del
 * sitio, no contra funciones sueltas:
 *   1. inyección de consultas por campos de texto, incluida la búsqueda pública
 *      y el identificador del inicio de sesión;
 *   2. código incrustado (XSS): se publica una noticia con una etiqueta de
 *      script y se comprueba que sale escapada, nunca ejecutable;
 *   3. falsificación de peticiones (CSRF): un token de otra sesión no sirve.
 *
 * Crea datos de prueba y los borra al terminar. Uso:
 *   php tools/injection-test.php
 */

use GB\Application;
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
(require GB_BASE_PATH . '/config/routes.php')($router);

$failures = 0;

$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? '  [ok]    ' : '  [FALLA] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

/** Estado inicial de todo lo que esta prueba puede tocar. */
$state = [
    'appointments' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM appointments')->fetchColumn(),
    'subscribers' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM subscribers')->fetchColumn(),
    'audit' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn(),
];

$injection = "' OR 1=1 -- ";
$xss = '<script>alert("inyectado")</script>';

$server = static fn (string $method, string $uri): array => [
    'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1',
];

$send = static function (string $method, string $uri, array $post = [], array $query = []) use ($router, $server): \GB\Support\Response {
    return $router->dispatch(new Request($query, $post, $server($method, $uri), [], []));
};

$admin = null;

echo 'Inyección, código incrustado y falsificación de peticiones (tarea 12.3)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // ---------------------------------------------- 1. Inyección de consultas
    echo 'Inyección de consultas' . PHP_EOL;

    $response = $send('GET', '/cursos?q=' . rawurlencode($injection));
    $check('la búsqueda pública no se rompe con comillas y guiones', $response->status() === 200);
    $check('y no devuelve cursos por el truco de la condición siempre verdadera',
        !str_contains($response->content(), 'Curso de prueba completo'));

    $response = $send('POST', '/contacto', [
        '_token' => (string) $container->get(\GB\Support\Csrf::class)->token(),
        'name' => 'Prueba ' . $injection,
        'email' => 'zz-inyeccion@example.invalid',
        'phone' => '3000000000',
        'message' => 'Mensaje de prueba ' . $injection,
        'terms' => '1',
    ]);
    $check('el formulario de contacto acepta el texto sin ejecutarlo', $response->status() !== 500);

    $response = $send('POST', '/suscripcion', [
        '_token' => (string) $csrf->token(),
        'name' => 'Prueba',
        'last_name' => 'Inyección',
        'email' => $injection . '@example.invalid',
        'phone' => '3000000000',
        'terms' => '1',
    ]);
    $check('un correo con inyección se rechaza por formato', $response->status() !== 500);

    $response = $send('POST', '/ingresar', [
        '_token' => (string) $csrf->token(),
        'email' => $injection,
        'password' => $injection,
    ]);
    $check('el inicio de sesión no se autentica con una inyección', $response->status() !== 200
        || str_contains($response->content(), 'no son correctas')
        || str_contains($response->content(), 'credenciales'));
    $check('y no deja sesión abierta', $auth->check() === false);

    // Un administrador de verdad, para probar el panel
    $email = 'zz-inyeccion@example.invalid';
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    $adminId = $users->create([
        'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Prueba', 'last_name' => 'Inyección',
        'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
        'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
    ]);
    $users->completeOnboarding($adminId);
    $auth->attempt($email, 'Temporal-2026!', '127.0.0.1', ['SuperAdmin']);

    $response = $send('GET', '/admin/estudiantes?q=' . rawurlencode($injection));
    $check('la búsqueda del panel tampoco se rompe', $response->status() === 200);

    $response = $send('GET', '/admin/usuarios?q=' . rawurlencode($injection) . '&role=SuperAdmin');
    $check('la búsqueda de cuentas tampoco se rompe', $response->status() === 200);

    // ------------------------------------------- 2. Código incrustado (XSS)
    echo 'Código incrustado (XSS)' . PHP_EOL;

    $response = $send('POST', '/admin/noticias', [
        '_token' => (string) $csrf->token(),
        'title' => 'Noticia ' . $xss,
        'slug' => 'zz-noticia-xss',
        'summary' => 'Resumen ' . $xss,
        'body' => 'Contenido ' . $xss,
        'status' => 'published',
        'category' => '<script>alert("categoria")</script>',
    ]);
    $check('la noticia con código incrustado se guarda sin romper nada', $response->status() === 302);

    $newsId = (int) $pdo->query('SELECT id FROM news WHERE slug = "zz-noticia-xss"')->fetchColumn();
    $check('la noticia quedó guardada', $newsId > 0);

    $blog = $send('GET', '/noticias')->content();
    $detail = $send('GET', '/noticias/zz-noticia-xss')->content();
    $panel = $send('GET', '/admin/noticias')->content();

    foreach (['el blog público' => $blog, 'la ficha de la noticia' => $detail, 'el panel' => $panel] as $where => $html) {
        $check('en ' . $where . ' el código sale escapado', str_contains($html, '&lt;script&gt;'));
        $check('en ' . $where . ' el código no queda ejecutable',
            !str_contains($html, '<script>alert')
            && !str_contains($html, '<script>alert("inyectado")</script>'));
    }

    // Un intento de cerrar un atributo tampoco debe colarse
    $response = $send('POST', '/admin/noticias', [
        '_token' => (string) $csrf->token(),
        'title' => '"><script>alert(1)</script>',
        'slug' => 'zz-noticia-xss-atributo',
        'body' => 'Contenido',
        'status' => 'published',
    ]);
    $attributeNews = $send('GET', '/noticias')->content();
    $check('no se puede romper un atributo con comillas', !str_contains($attributeNews, '"><script'));

    // ------------------------------------------------- 3. Falsificación (CSRF)
    echo 'Falsificación de peticiones (CSRF)' . PHP_EOL;

    $response = $send('POST', '/admin/noticias', ['title' => 'Sin token', 'slug' => 'zz-sin-token', 'body' => 'x']);
    $check('un envío sin token se rechaza', $response->status() === 419);
    $check('y no se guardó nada',
        (int) $pdo->query('SELECT COUNT(*) FROM news WHERE slug = "zz-sin-token"')->fetchColumn() === 0);

    $response = $send('POST', '/admin/noticias', [
        '_token' => str_repeat('b', 64), 'title' => 'Token ajeno', 'slug' => 'zz-token-ajeno', 'body' => 'x',
    ]);
    $check('un token de otra sesión se rechaza', $response->status() === 419);
    $check('y tampoco se guardó nada',
        (int) $pdo->query('SELECT COUNT(*) FROM news WHERE slug = "zz-token-ajeno"')->fetchColumn() === 0);

    $response = $send('POST', '/contacto', ['name' => 'Sin token', 'email' => 'zz@example.invalid', 'message' => 'x']);
    $check('el límite también protege los formularios públicos', $response->status() === 419);

    // Los formularios de la portada tienen que funcionar de verdad: la página se
    // guarda en la caché pública, y si el token de seguridad viajara dentro de
    // esa copia, todos los visitantes recibirían el token de otra sesión y sus
    // envíos se rechazarían. Se hace lo mismo que un navegador: se lee el token
    // del formulario que llega y se envía con él.
    echo 'Formularios de la portada con la página en caché' . PHP_EOL;

    $session = $container->get(\GB\Support\Session::class);
    $pageTokens = [];

    for ($visit = 0; $visit < 2; $visit++) {
        $session->flush();
        $html = $send('GET', '/')->content();
        preg_match('/data-newsletter-form>.*?name="_token" value="([^"]+)"/s', $html, $matches);
        $pageTokens[] = (string) ($matches[1] ?? '');
        $sinSustituir = str_contains($html, \GB\Support\Csrf::PLACEHOLDER);
    }

    $check('cada visitante recibe su propio token aunque la página venga de la caché',
        $pageTokens[0] !== '' && $pageTokens[1] !== '' && $pageTokens[0] !== $pageTokens[1]);
    $check('no queda ninguna marca de token sin sustituir en el HTML', !$sinSustituir);

    $response = $send('POST', '/contacto', [
        '_token' => $pageTokens[1],
        'name' => 'Visitante Prueba', 'email' => 'zz-formulario@example.invalid',
        'phone' => '3000000000', 'subject' => 'Consulta de prueba',
        'message' => 'Mensaje enviado con el token que trae la propia página.',
        'terms' => '1',
    ]);
    $check('el formulario de contacto de la portada se acepta', $response->status() === 200,
        'estado ' . $response->status());
    $check('y el mensaje quedó guardado',
        (int) $pdo->query('SELECT COUNT(*) FROM appointments WHERE email = "zz-formulario@example.invalid"')->fetchColumn() === 1);

    // ------------------------------------------------------ 4. Rutas de archivo
    echo 'Rutas y archivos' . PHP_EOL;

    foreach (['/contenido/lecciones/../../.env', '/contenido/lecciones/%2e%2e%2f%2e%2e%2f.env'] as $uri) {
        $response = $send('GET', $uri);
        $check('una ruta con salto de carpeta no entrega el archivo de entorno (' . $uri . ')',
            $response->status() !== 200 || !str_contains($response->content(), 'DB_PASSWORD'));
    }
} finally {
    // Limpieza: sólo lo que ha creado esta prueba
    $pdo->exec('DELETE FROM news WHERE slug IN ("zz-noticia-xss", "zz-noticia-xss-atributo", "zz-sin-token", "zz-token-ajeno")');
    $pdo->prepare('DELETE FROM appointments WHERE id > ?')->execute([$state['appointments']]);
    $pdo->prepare('DELETE FROM subscribers WHERE id > ?')->execute([$state['subscribers']]);
    $pdo->prepare('DELETE FROM audit_logs WHERE id > ?')->execute([$state['audit']]);

    if (isset($adminId)) {
        $pdo->exec('DELETE FROM audit_logs WHERE entity_type = "user" AND entity_id = ' . $adminId);
        $pdo->exec('DELETE FROM users WHERE id = ' . $adminId);
        $session = GB_STORAGE_PATH . '/sessions/active/user-' . $adminId . '.json';

        if (is_file($session)) {
            unlink($session);
        }
    }

    $pdo->exec('DELETE FROM throttle WHERE identifier LIKE "0.0.0.0%"');
}

$leftovers = (int) $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM appointments WHERE id > ' . $state['appointments'])->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM subscribers WHERE id > ' . $state['subscribers'])->fetchColumn();

echo str_repeat('-', 70) . PHP_EOL;
echo 'Registros de prueba restantes: ' . $leftovers . PHP_EOL;
echo $failures === 0 && $leftovers === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $leftovers === 0 ? 0 : 1);
