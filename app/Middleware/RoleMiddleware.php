<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Audit;
use GB\Support\Auth;
use GB\Support\ErrorResponder;
use GB\Support\Request;
use GB\Support\Response;
use Throwable;

/**
 * Control de acceso por rol.
 *
 * Se usa en las rutas así: ['auth', 'role:SuperAdmin'] o ['auth', 'role:SuperAdmin,Student'].
 *
 * Deniega por defecto: si no se indica ningún rol permitido, nadie pasa. Así un
 * olvido al declarar una ruta protegida falla del lado seguro y no al revés.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Auth $auth,
        private ErrorResponder $responder,
        private ?Audit $audit = null,
    ) {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        $allowed = array_values(array_filter(array_map('trim', $args), static fn (string $r): bool => $r !== ''));

        if ($allowed === []) {
            return $this->deny($request, 'Ruta sin roles declarados');
        }

        if (!$this->auth->check()) {
            return Response::redirect(url('ingresar'));
        }

        if (!$this->auth->hasRole(...$allowed)) {
            return $this->deny($request, sprintf(
                'El rol %s intentó acceder a una ruta reservada a %s',
                (string) $this->auth->role(),
                implode(', ', $allowed)
            ));
        }

        return $next($request);
    }

    private function deny(Request $request, string $reason): Response
    {
        $this->recordAttempt($request, $reason);

        return $this->responder->respond($request, 403);
    }

    /**
     * Deja constancia del intento, sin que un fallo al registrar impida
     * responder con la página de acceso denegado.
     */
    private function recordAttempt(Request $request, string $reason): void
    {
        if ($this->audit === null) {
            return;
        }

        try {
            $this->audit->log(
                action: 'access_denied',
                summary: $reason . ' en ' . $request->path(),
                changes: ['path' => $request->path(), 'method' => $request->method()]
            );
        } catch (Throwable) {
            // El registro es informativo: nunca debe romper la respuesta.
        }
    }
}
