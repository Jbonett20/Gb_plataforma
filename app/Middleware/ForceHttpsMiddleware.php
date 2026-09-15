<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Obliga a usar conexión cifrada en producción.
 *
 * En desarrollo no hace nada, para no romper las direcciones de localhost.
 * En producción redirige permanentemente a la versión https conservando la
 * ruta y los parámetros.
 */
final class ForceHttpsMiddleware implements MiddlewareInterface
{
    public function __construct(private string $environment = 'production')
    {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        if ($this->environment !== 'production' || $request->isSecure()) {
            return $next($request);
        }

        // Se toma la dirección de la propia petición, no de variables globales,
        // para conservar el subdirectorio y los parámetros.
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $target = 'https://' . $host . $request->fullPath();

        return Response::redirect($target, 301);
    }
}
