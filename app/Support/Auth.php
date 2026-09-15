<?php

declare(strict_types=1);

namespace GB\Support;

use GB\Models\UserRepository;
use GB\Services\SessionRegistry;

/**
 * Autenticación de usuarios.
 *
 * Distingue dos roles: SuperAdmin (panel de administración) y Student (área
 * personal). La sesión caduca por inactividad, con un margen más corto para la
 * administración que para el área de estudiantes.
 *
 * El motivo del fallo se guarda en `failureReason()` para poder mostrar un
 * mensaje útil sin revelar si un correo existe o no.
 */
final class Auth
{
    public const FAILURE_INVALID = 'invalid';
    public const FAILURE_INACTIVE = 'inactive';
    public const FAILURE_LOCKED = 'locked';
    public const FAILURE_WRONG_ROLE = 'wrong_role';

    private const SESSION_USER = 'auth_user_id';
    private const SESSION_ROLE = 'auth_role';
    private const SESSION_INTENDED = 'auth_intended_url';
    private const SESSION_LAST_ACTIVITY = '_last_activity';
    private const SESSION_MUST_CHANGE = 'auth_must_change_password';

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    private bool $resolved = false;

    private ?string $failureReason = null;

    /**
     * Hash de descarte: se verifica cuando el correo no existe para que el
     * tiempo de respuesta no delate qué cuentas están registradas.
     */
    private const DUMMY_HASH = '$2y$10$usesomesillystringforeachowhashing1234567890abcdefghijklmnopqrstuvwx';

    /** @param array<string, mixed> $security config('app.security') */
    public function __construct(
        private Session $session,
        private UserRepository $users,
        private SessionRegistry $sessions,
        private array $security = [],
    ) {
    }

    public function check(): bool
    {
        if (!is_int($this->session->get(self::SESSION_USER))) {
            return false;
        }

        if (!$this->sessions->isActive((int) $this->session->get(self::SESSION_USER), $this->session->id())) {
            $this->logout();
            return false;
        }

        $role = $this->session->get(self::SESSION_ROLE);

        if (!$this->session->touch(is_string($role) ? $role : null, time())) {
            $this->logout();

            return false;
        }

        return $this->user() !== null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $id = $this->session->get(self::SESSION_USER);

        if (!is_int($id)) {
            return $this->user = null;
        }

        $user = $this->users->findById($id);

        // Cuenta borrada, desactivada o bloqueada: se cierra la sesión.
        if ($user === null || $user['status'] !== 'active') {
            $this->logout();

            return $this->user = null;
        }

        return $this->user = $user;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function role(): ?string
    {
        $role = $this->session->get(self::SESSION_ROLE);

        if (is_string($role) && $role !== '') {
            return $role;
        }

        $user = $this->user();

        return $user === null ? null : (string) ($user['role_key'] ?? '');
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'SuperAdmin';
    }

    public function isStudent(): bool
    {
        return $this->role() === 'Student';
    }

    public function hasRole(string ...$roles): bool
    {
        $current = $this->role();

        return $current !== null && in_array($current, $roles, true);
    }

    /**
     * Intenta iniciar sesión.
     *
     * `$requiredRoles` permite exigir un rol concreto sin duplicar la
     * comprobación de contraseñas: cuando las credenciales son correctas pero el
     * rol no corresponde, no se abre sesión y no se cuenta como intento fallido,
     * porque no fue un error de credenciales.
     *
     * @param array<int, string>|null $requiredRoles
     */
    public function attempt(string $identifier, string $password, string $ip = '0.0.0.0', ?array $requiredRoles = null): bool
    {
        $this->failureReason = null;
        $record = $this->users->findForAuthentication($identifier);

        if ($record === null) {
            // Se verifica contra un hash de descarte para igualar el tiempo.
            password_verify($password, self::DUMMY_HASH);
            $this->failureReason = self::FAILURE_INVALID;

            return false;
        }

        $userId = (int) $record['id'];

        if ($record['status'] !== 'active') {
            $this->failureReason = self::FAILURE_INACTIVE;

            return false;
        }

        if ($this->users->isLocked($userId)) {
            $this->failureReason = self::FAILURE_LOCKED;

            return false;
        }

        if (!password_verify($password, (string) $record['password_hash'])) {
            $this->users->recordFailedLogin(
                $userId,
                (int) ($this->security['login_max_attempts'] ?? 5),
                (int) ($this->security['login_lockout_minutes'] ?? 15)
            );

            $this->failureReason = self::FAILURE_INVALID;

            return false;
        }

        $role = (string) ($record['role_key'] ?? '');

        if ($requiredRoles !== null && !in_array($role, $requiredRoles, true)) {
            $this->failureReason = self::FAILURE_WRONG_ROLE;

            return false;
        }

        $this->users->recordSuccessfulLogin($userId, $ip);
        $this->login($record);

        return true;
    }

    /**
     * Marca la sesión como autenticada.
     *
     * @param array<string, mixed> $user
     */
    public function login(array $user): void
    {
        // Se vacía y se renueva el identificador para impedir la fijación de sesión.
        $this->session->flush();
        $this->session->regenerate();

        $this->session->put(self::SESSION_USER, (int) $user['id']);
        $this->session->put(self::SESSION_ROLE, (string) ($user['role_key'] ?? ''));
        $this->session->put(self::SESSION_LAST_ACTIVITY, time());
        // Se guarda en la sesión para poder exigir el cambio en cada petición
        // sin consultar la base de datos en las páginas públicas.
        $this->session->put(self::SESSION_MUST_CHANGE, (int) ($user['must_change_password'] ?? 0));
        $this->sessions->register((int) $user['id'], $this->session->id(), time());

        $this->user = null;
        $this->resolved = false;
    }

    /**
     * Refresca los datos de la cuenta tras una edición de perfil.
     */
    public function refresh(): void
    {
        $this->user = null;
        $this->resolved = false;
    }

    public function logout(): void
    {
        $userId = $this->session->get(self::SESSION_USER);
        if (is_int($userId)) {
            $this->sessions->forget($userId, $this->session->id());
        }
        $this->session->forget(self::SESSION_USER, self::SESSION_ROLE, self::SESSION_LAST_ACTIVITY, self::SESSION_MUST_CHANGE);
        $this->session->regenerate();
        $this->user = null;
        $this->resolved = false;
    }

    /**
     * Si la cuenta debe cambiar la contraseña antes de seguir usando el sistema.
     *
     * Se responde desde la sesión a propósito: es una comprobación que ocurre en
     * cada petición y no debe costar una consulta en las páginas públicas.
     */
    public function mustChangePassword(): bool
    {
        return (int) $this->session->get(self::SESSION_MUST_CHANGE, 0) === 1;
    }

    /**
     * Deja constancia de que el cambio ya se hizo, sin volver a iniciar sesión.
     */
    public function clearMustChangePassword(): void
    {
        $this->session->put(self::SESSION_MUST_CHANGE, 0);
        $this->refresh();
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function failureMessage(): ?string
    {
        return match ($this->failureReason) {
            self::FAILURE_INVALID => 'El correo o la contraseña no son correctos.',
            self::FAILURE_INACTIVE => 'Esta cuenta está desactivada. Escríbenos para reactivarla.',
            self::FAILURE_LOCKED => 'La cuenta está bloqueada temporalmente por varios intentos fallidos.',
            // No se confirma si la cuenta existe: sólo se dice que esas
            // credenciales no sirven para entrar aquí.
            self::FAILURE_WRONG_ROLE => 'Estas credenciales no tienen acceso al panel de administración.',
            default => null,
        };
    }

    /**
     * Dirección que la persona intentaba abrir antes de autenticarse.
     */
    public function setIntendedUrl(string $url): void
    {
        $this->session->put(self::SESSION_INTENDED, $url);
    }

    public function consumeIntendedUrl(): ?string
    {
        $url = $this->session->get(self::SESSION_INTENDED);
        $this->session->forget(self::SESSION_INTENDED);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Segundos que quedan antes de que la sesión caduque por inactividad.
     */
    public function secondsUntilExpiry(): int
    {
        return $this->session->secondsUntilExpiry($this->role(), time());
    }
}
