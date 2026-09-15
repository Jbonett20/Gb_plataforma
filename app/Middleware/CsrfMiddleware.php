<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Csrf;
use GB\Support\ErrorResponder;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Exige el token de seguridad en toda petición que modifique datos.
 *
 * Las consultas de sólo lectura pasan sin comprobación. Si el token falta, no
 * corresponde a la sesión o la página estuvo abierta demasiado tiempo, se
 * responde con una explicación y la acción NO se ejecuta.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Csrf $csrf,
        private ErrorResponder $responder,
    ) {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        if (!$request->isStateChanging()) {
            return $next($request);
        }

        if ($this->csrf->check($this->csrf->fromRequest($request))) {
            return $next($request);
        }

        return $this->responder->respond($request, 419);
    }
}
