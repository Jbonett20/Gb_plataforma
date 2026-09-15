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

        // «guest:student» marca las puertas del área de estudiantes (entrar y
        // crear cuenta). Quien tiene abierta la sesión del panel puede abrirlas
        // —para entrar con otra cuenta, por ejemplo—, pero un clic en un botón
        // del sitio público nunca debe terminar dentro del panel: sería pasar
        // por una puerta para aparecer en otra habitación.
        if ($this->auth->isAdmin() && in_array('student', $args, true)) {
            return $next($request);
        }

        $destination = $this->auth->isAdmin() ? $this->adminPath : $this->studentPath;

        return Response::redirect(url($destination));
    }
}
