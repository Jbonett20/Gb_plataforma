<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Este archivo es el único punto de entrada del sitio. Recibe la petición, la
 * entrega al enrutador y emite la respuesta.
 */

use GB\Application;
use GB\Support\ConfigurationException;
use GB\Support\ErrorResponder;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Router;
use GB\Support\Session;

require dirname(__DIR__) . '/app/bootstrap.php';

$container = Application::boot();

// La sesión se inicia antes de generar cualquier salida, para que la cookie
// pueda enviarse con sus atributos de seguridad.
$container->get(Session::class)->start();

$request = $container->get(Request::class);
$responder = $container->get(ErrorResponder::class);

$router = $container->get(Router::class);

$router->fallback(static fn (Request $current): Response => $responder->respond($current, 404));

(require GB_BASE_PATH . '/config/routes.php')($router);

try {
    $response = $router->dispatch($request);
} catch (ConfigurationException $exception) {
    // Falla de configuración: es para el administrador, no para el visitante.
    error_log('[gbplataforma] ' . $exception->getMessage());
    $response = renderConfigurationError($exception);
} catch (Throwable $exception) {
    error_log(sprintf(
        '[gbplataforma] %s en %s:%d%s%s',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        PHP_EOL,
        $exception->getTraceAsString()
    ));

    $response = config('app.debug', false) && !$request->wantsJson()
        ? renderDebugError($exception)
        : $responder->respond($request, 500);
}

$response->send();

/**
 * Aviso de configuración incompleta, redactado para el administrador técnico.
 */
function renderConfigurationError(ConfigurationException $exception): Response
{
    $message = e($exception->getMessage());
    $hint = e('Revisa el archivo .env y vuelve a cargar esta página. Si el problema continúa, '
        . 'consulta la documentación de instalación.');

    $html = <<<HTML
        <section class="section bg-light-section">
          <div class="container py-5">
            <div class="row justify-content-center">
              <div class="col-lg-8">
                <h1 class="h3 mb-3">Falta completar la configuración</h1>
                <p>{$message}</p>
                <p class="text-muted small mb-0">{$hint}</p>
              </div>
            </div>
          </div>
        </section>
        HTML;

    return Response::html($html, 500);
}

/**
 * Detalle técnico del error. Sólo se muestra con APP_DEBUG activo.
 */
function renderDebugError(Throwable $exception): Response
{
    $class = e($exception::class);
    $message = e($exception->getMessage());
    $file = e($exception->getFile());
    $line = $exception->getLine();
    $trace = e($exception->getTraceAsString());

    $html = <<<HTML
        <section class="section bg-light-section">
          <div class="container py-5">
            <h1 class="h3 mb-3">{$class}</h1>
            <p class="fw-bold">{$message}</p>
            <p class="text-muted small">{$file}:{$line}</p>
            <pre class="small bg-white p-3 border rounded" style="overflow-x:auto">{$trace}</pre>
            <p class="text-muted small mb-0">
              Este detalle sólo se muestra porque APP_DEBUG está activado. En producción se
              desactiva y el visitante ve una página amable.
            </p>
          </div>
        </section>
        HTML;

    return Response::html($html, 500);
}
