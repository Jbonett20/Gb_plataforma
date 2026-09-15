<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\ErrorResponder;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Throttle;

/**
 * Limita los intentos abusivos.
 *
 * Se declara en la ruta así:
 *   'throttle:contact'          cuenta por dirección IP.
 *   'throttle:login,email'      cuenta por IP y por el valor del campo indicado.
 *
 * Criterio de uso, importante para no bloquear a gente legítima:
 *
 *   - En acciones de CUENTA (iniciar sesión, recuperar contraseña) interesa la
 *     clave compuesta: así un ataque contra una cuenta concreta se frena sin
 *     castigar a todos los que comparten la misma salida a internet.
 *   - En formularios PÚBLICOS (contacto, suscripción) se cuenta sólo por IP.
 *     Si se incluyera un campo escrito por quien envía el formulario, un envío
 *     sin ese campo abriría un contador distinto y los intentos se repartirían
 *     entre cubetas, que es justo lo contrario de lo que se busca.
 *
 * El contador suma en cada intento; quien atiende la acción debe borrarlo cuando
 * el intento sea correcto (por ejemplo `Auth::attempt` al autenticar bien).
 */
final class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Throttle $throttle,
        private ErrorResponder $responder,
    ) {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        $action = $args[0] ?? 'default';
        $identifier = self::identifierFor($request, $args[1] ?? null);

        if ($this->throttle->tooManyAttempts($action, $identifier)) {
            return $this->responder->respond(
                $request,
                429,
                'Por seguridad hemos pausado esta acción. ' . $this->throttle->waitMessage($action, $identifier)
            );
        }

        $this->throttle->hit($action, $identifier);

        return $next($request);
    }

    /**
     * Identifica el origen del intento. Se limita el largo para respetar el
     * tamaño de la columna.
     *
     * Es público y estático a propósito: quien atiende la acción necesita la
     * misma clave para borrar el contador cuando el intento sale bien. Si cada
     * lado la calculara por su cuenta, acabarían contando en cubetas distintas.
     */
    public static function identifierFor(Request $request, ?string $field): string
    {
        $identifier = $request->ip();

        if ($field !== null && $field !== '') {
            $value = $request->input($field);

            if (is_scalar($value) && (string) $value !== '') {
                $identifier .= '|' . mb_strtolower(trim((string) $value));
            }
        }

        return mb_substr($identifier, 0, 190);
    }
}
