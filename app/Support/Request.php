<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Petición HTTP normalizada.
 *
 * Los controladores nunca leen $_POST, $_GET ni $_SERVER directamente: todo
 * pasa por esta clase, de modo que la validación y el escapado sean uniformes.
 */
final class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];

    private ?string $routeName = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, mixed> $cookies
     * @param string $rawBody cuerpo tal cual llegó, sin interpretar
     */
    public function __construct(
        private array $query = [],
        private array $body = [],
        private array $server = [],
        private array $files = [],
        private array $cookies = [],
        private string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        // El cuerpo crudo sólo se lee para peticiones con contenido: las
        // notificaciones de la pasarela se firman sobre el cuerpo original y no
        // sirve el arreglo ya interpretado.
        $raw = '';

        if (in_array(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['POST', 'PUT', 'PATCH'], true)) {
            $read = file_get_contents('php://input');

            if (is_string($read)) {
                $raw = $read;
            }
        }

        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, $raw);
    }

    /**
     * Cuerpo original de la petición. Lo necesitan las firmas de webhook, que
     * se calculan sobre los bytes recibidos y no sobre una reinterpretación.
     */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        // Los formularios HTML sólo envían GET y POST: se permite declarar el
        // verbo real con un campo oculto `_method`.
        if ($method === 'POST') {
            $override = $this->body['_method'] ?? null;

            if (is_string($override) && $override !== '') {
                $candidate = strtoupper($override);

                if (in_array($candidate, ['PUT', 'PATCH', 'DELETE'], true)) {
                    return $candidate;
                }
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $base = $this->basePath();

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * Ruta tal como llegó del navegador, incluyendo el subdirectorio y los
     * parámetros. Se usa para reenviar a otra dirección sin perder nada.
     */
    public function fullPath(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $query = parse_url($uri, PHP_URL_QUERY);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        return $path . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    public function queryString(): string
    {
        $query = parse_url((string) ($this->server['REQUEST_URI'] ?? ''), PHP_URL_QUERY);

        return is_string($query) ? $query : '';
    }

    /**
     * Prefijo del subdirectorio donde vive el proyecto, para que las rutas
     * funcionen igual en la raíz del dominio o en /gbplataforma/public/.
     */
    public function basePath(): string
    {
        $script = str_replace('\\', '/', (string) ($this->server['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');

        return $dir === '/' ? '' : $dir;
    }

    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        // Detrás de un proxy o balanceador.
        return strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /** Indica si la petición modifica datos y por tanto exige token CSRF. */
    public function isStateChanging(): bool
    {
        return in_array($this->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);

        return in_array($value, ['1', 'on', 'true', 'yes', 'si', 'sí', true], true);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) ($this->server['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $name, ?string $default = null): ?string
    {
        $value = $this->routeParams[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    /** @return array<string, string> */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function setRouteName(?string $name): void
    {
        $this->routeName = $name;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }
}
