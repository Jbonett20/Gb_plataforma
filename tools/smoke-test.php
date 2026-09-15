<?php

declare(strict_types=1);

/**
 * Prueba de humo sin navegador.
 *
 * Arranca la aplicación, despacha varias direcciones y reporta el código de
 * estado de cada una. Sirve para comprobar que el enrutador, los controladores
 * y las vistas funcionan después de cada cambio.
 *
 * Uso:  php tools/smoke-test.php
 */

use GB\Application;
use GB\Support\Request;
use GB\Support\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$container = Application::boot();
$router = $container->get(Router::class);

// Mismo comportamiento que el front controller para las direcciones no encontradas.
$router->fallback(static function (Request $request) use ($container): \GB\Support\Response {
    $view = $container->get(\GB\Support\View::class);

    return \GB\Support\Response::html(
        $view->render('errors.404', [
            'siteName' => (string) config('app.name'),
            'company' => (array) config('app.company', []),
            'title' => 'Contenido no disponible',
            'metaDescription' => '',
        ], 'layouts.public'),
        404
    );
});

(require GB_BASE_PATH . '/config/routes.php')($router);

// Sesión de prueba. En línea de comandos la sesión es un arreglo en memoria, así
// que se puede emitir un token de seguridad real y comprobar que se exige.
$container->get(\GB\Support\Session::class)->start();
$container->get(\GB\Support\Csrf::class)->regenerate();
$token = $container->get(\GB\Support\Csrf::class)->token();

$testEmail = 'prueba-smoke@example.test';

// El límite de envíos del formulario de contacto se cuenta en la base de datos,
// así que hay que limpiar el contador propio para que la prueba se pueda
// ejecutar varias veces seguidas sin bloquearse a sí misma.
$contactIdentifier = '127.0.0.1';
$container->get(PDO::class)
    ->prepare('DELETE FROM throttle WHERE action = ? AND identifier = ?')
    ->execute(['contact', $contactIdentifier]);

$cases = [
    ['GET', '/', [], 200],
    ['GET', '/instalacion', [], 200],
    ['GET', '/esta-direccion-no-existe', [], 404],
    ['POST', '/', [], 405],
    // Formulario de contacto: sin token no debe ejecutarse nada.
    ['POST', '/contacto', [], 419],
    ['POST', '/contacto', ['_token' => $token], 422],
    ['POST', '/contacto', [
        '_token' => $token,
        'name' => 'Prueba automática',
        'email' => $testEmail,
        'subject' => 'Asunto de prueba',
        'message' => 'Mensaje de prueba con la longitud mínima necesaria.',
    ], 200],
];

$failures = 0;

printf("%-6s %-34s %-8s %-10s %s\n", 'MÉTODO', 'RUTA', 'ESTADO', 'BYTES', 'OBSERVACIÓN');
echo str_repeat('-', 92) . "\n";

foreach ($cases as [$method, $path, $body, $expected]) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    $request = new Request([], $body, $_SERVER, [], []);

    try {
        $response = $router->dispatch($request);
        $status = $response->status();
        $bytes = strlen($response->content());
        $note = '';

        if ($path === '/' && $status === 200) {
            $note = $bytes > 1000 ? 'Portada renderizada' : 'Portada sin secciones publicadas aún';
        } elseif ($path === '/esta-direccion-no-existe' && $status === 404) {
            $note = 'Página amigable de contenido no disponible';
        } elseif ($method === 'POST' && $expected === 405) {
            $note = 'Método no permitido';
        } elseif ($path === '/contacto') {
            $note = match ($status) {
                419 => 'Sin token de seguridad: no ejecuta nada',
                422 => 'Datos incompletos: explica qué falta',
                200 => 'Mensaje recibido y guardado',
                default => 'Revisar',
            };
        }

        if ($status !== $expected) {
            $note = 'Se esperaba ' . $expected . '. ' . $note;
            $failures++;
        }
    } catch (Throwable $exception) {
        $status = 'EXCEPCIÓN';
        $bytes = 0;
        $note = $exception::class . ': ' . $exception->getMessage();
        $failures++;
    }

    printf("%-6s %-34s %-8s %-10s %s\n", $method, $path, (string) $status, (string) $bytes, $note);
}

echo str_repeat('-', 92) . "\n";

// La solicitud de contacto creada durante la prueba no debe quedar guardada.
$container->get(PDO::class)
    ->prepare('DELETE FROM appointments WHERE email = ?')
    ->execute([$testEmail]);

$container->get(PDO::class)
    ->prepare('DELETE FROM throttle WHERE action = ? AND identifier = ?')
    ->execute(['contact', $contactIdentifier]);

echo $failures === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) con error.\n";

exit($failures === 0 ? 0 : 1);
