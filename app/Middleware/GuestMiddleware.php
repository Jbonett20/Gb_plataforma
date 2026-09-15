<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Evita que alguien ya autenticado vuelva a las pantallas de ingreso o registro.
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Auth $auth,
        private string $adminPath = 'admin',
        private string $studentPath = 'mi-cuenta',
    ) {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        if (!$this->auth->check()) {
            return $next($request);
        }

        $destination = $this->auth->isAdmin() ? $this->adminPath : $this->studentPath;

        return Response::redirect(url($destination));
    }
}
