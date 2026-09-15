<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Protección contra falsificación de peticiones en sitios cruzados (CSRF).
 *
 * Se emite un token por sesión y se verifica en el servidor antes de procesar
 * cualquier formulario. La comparación se hace en tiempo constante para no
 * filtrar información por el tiempo de respuesta.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Marca que ocupa el lugar del token en el HTML que se guarda en la caché de
     * páginas públicas.
     *
     * El token pertenece a la sesión de quien visita, así que no puede quedar
     * congelado dentro de una página guardada: si se guardara, el siguiente
     * visitante recibiría un token ajeno y su envío se rechazaría. En el HTML
     * almacenado se escribe esta marca, y al servir la página se cambia por el
     * token real de la sesión que la pide.
     */
    public const PLACEHOLDER = 'gb-csrf-token-placeholder';

    public function __construct(
        private Session $session,
        private string $fieldName = '_token',
        private string $headerName = 'X-CSRF-Token',
    ) {
    }

    /**
     * Devuelve el token de la sesión, creándolo la primera vez.
     */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Invalida el token actual. Se usa al iniciar y cerrar sesión.
     */
    public function regenerate(): void
    {
        $this->session->put(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Extrae el token enviado, ya sea en el formulario o en una cabecera.
     */
    public function fromRequest(Request $request): ?string
    {
        $fromBody = $request->input($this->fieldName);
        $fromHeader = $request->header($this->headerName);

        if (is_string($fromBody) && $fromBody !== '') {
            return $fromBody;
        }

        return is_string($fromHeader) && $fromHeader !== '' ? $fromHeader : null;
    }

    /**
     * Comprueba que el token recibido corresponde al de la sesión.
     */
    public function check(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Campo oculto que debe incluir todo formulario.
     */
    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            e($this->fieldName),
            e($this->token())
        );
    }
}
