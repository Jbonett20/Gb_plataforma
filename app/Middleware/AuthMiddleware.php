<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Auth;
use GB\Support\ErrorResponder;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Exige una sesión activa.
 *
 * Si la persona no ha iniciado sesión, se guarda a dónde quería ir y se le
 * lleva a la página de ingreso; al autenticarse vuelve exactamente ahí.
 *
 * La sesión caduca por inactividad: 20 minutos en administración y 2 horas en
 * el área de estudiantes (valores configurables en .env).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Auth $auth,
        private ErrorResponder $responder,
        private string $loginPath = 'ingresar',
    ) {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        if ($request->wantsJson() || $request->isAjax()) {
            return $this->responder->json(401, 'Tu sesión terminó. Vuelve a iniciar sesión para continuar.');
        }

        $query = $request->queryString();
        $this->auth->setIntendedUrl($request->path() . ($query === '' ? '' : '?' . $query));

        return Response::redirect(url($this->loginPath));
    }
}
