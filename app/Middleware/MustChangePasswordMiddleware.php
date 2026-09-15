<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Obliga a cambiar la contraseña inicial antes de usar el sistema.
 *
 * Cuando se crea una cuenta, quien la crea conoce la contraseña: por eso la
 * cuenta arranca marcada y no puede hacer nada más hasta cambiarla. Se aplica
 * desde el primer momento, en cada petición, y no depende de que cada pantalla
 * se acuerde de comprobarlo.
 *
 * La comprobación se resuelve con un dato de la sesión, así que las páginas
 * públicas no pagan ninguna consulta adicional.
 */
final class MustChangePasswordMiddleware implements MiddlewareInterface
{
    /** Direcciones a las que sí se puede ir mientras el cambio está pendiente. */
    private const ALLOWED = [
        'admin/clave',
        'mi-cuenta/perfil',
        'mi-cuenta/contrasena',
        'salir',
    ];

    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        if (!$this->auth->mustChangePassword()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if (in_array($path, self::ALLOWED, true)) {
            return $next($request);
        }

        // Cada rol tiene su pantalla de contraseña; se envía a la que puede usar.
        $target = $this->auth->isAdmin() ? 'admin/clave' : 'mi-cuenta/perfil';

        return Response::redirect(url($target));
    }
}
