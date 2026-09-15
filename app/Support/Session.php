<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Manejo de la sesión.
 *
 * La cookie se configura como no accesible desde JavaScript, no enviada en
 * peticiones de terceros y sólo por conexión cifrada cuando el sitio opera en
 * HTTPS. El identificador de sesión se renueva al iniciar sesión para impedir
 * la fijación de sesión.
 */
final class Session
{
    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // En línea de comandos (pruebas, guiones) no hay cookies: se trabaja
        // sobre el arreglo para poder simular una sesión.
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }

            return;
        }

        session_name($this->name());
        session_set_cookie_params([
            'lifetime' => 0,               // la sesión termina al cerrar el navegador
            'path' => '/',
            'domain' => '',
            'secure' => $this->isSecure(),
            'httponly' => true,            // no accesible desde JavaScript
            'samesite' => 'Lax',           // no se envía en peticiones de terceros
        ]);

        session_start();
    }

    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    public function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function id(): string
    {
        return session_status() === PHP_SESSION_ACTIVE ? (string) session_id() : 'cli-session';
    }

    public function name(): string
    {
        return (string) ($this->config['name'] ?? 'gb_session');
    }

    /**
     * Minutos de inactividad permitidos para el rol indicado.
     */
    public function lifetimeForRole(?string $role): int
    {
        $admin = (int) ($this->config['admin_lifetime_minutes'] ?? 20);
        $student = (int) ($this->config['student_lifetime_minutes'] ?? 120);

        return $role === 'SuperAdmin' ? $admin : $student;
    }

    /**
     * Registra actividad. Devuelve falso si la sesión ya había caducado.
     */
    public function touch(?string $role, int $now): bool
    {
        $last = $this->get('_last_activity');

        if (!is_int($last)) {
            $this->put('_last_activity', $now);

            return true;
        }

        if (($now - $last) > $this->lifetimeForRole($role) * 60) {
            return false;
        }

        $this->put('_last_activity', $now);

        return true;
    }

    /**
     * Segundos que faltan para la caducidad por inactividad.
     */
    public function secondsUntilExpiry(?string $role, int $now): int
    {
        $last = $this->get('_last_activity');

        if (!is_int($last)) {
            return $this->lifetimeForRole($role) * 60;
        }

        return max(0, ($this->lifetimeForRole($role) * 60) - ($now - $last));
    }

    private function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
