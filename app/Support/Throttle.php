<?php

declare(strict_types=1);

namespace GB\Support;

use PDO;

/**
 * Limitación de intentos abusivos.
 *
 * Cuenta los intentos por acción y origen (dirección IP, correo o ambos) y
 * bloquea temporalmente cuando se supera el límite. Se usa en el inicio de
 * sesión, el registro, la recuperación de contraseña y los formularios públicos.
 */
final class Throttle extends Model
{
    protected string $table = 'throttle';

    /** @param array<string, array{attempts?: int, minutes?: int}> $limits */
    public function __construct(PDO $pdo, private array $limits = [])
    {
        parent::__construct($pdo);
    }

    /**
     * @return array{attempts: int, minutes: int}
     */
    public function limitsFor(string $action): array
    {
        $limits = $this->limits[$action] ?? $this->limits['default'] ?? [];

        return [
            'attempts' => (int) ($limits['attempts'] ?? 10),
            'minutes' => (int) ($limits['minutes'] ?? 15),
        ];
    }

    public function attempts(string $action, string $identifier): int
    {
        $value = $this->scalar(
            'SELECT attempts FROM throttle WHERE action = :action AND identifier = :identifier',
            ['action' => $action, 'identifier' => $identifier]
        );

        return (int) ($value ?? 0);
    }

    public function tooManyAttempts(string $action, string $identifier): bool
    {
        return $this->exists(
            'SELECT 1 FROM throttle
             WHERE action = :action
               AND identifier = :identifier
               AND blocked_until IS NOT NULL
               AND blocked_until > NOW()',
            ['action' => $action, 'identifier' => $identifier]
        );
    }

    /**
     * Segundos que faltan para poder volver a intentarlo.
     */
    public function availableIn(string $action, string $identifier): int
    {
        $seconds = $this->scalar(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) FROM throttle
             WHERE action = :action AND identifier = :identifier AND blocked_until > NOW()',
            ['action' => $action, 'identifier' => $identifier]
        );

        return max(0, (int) ($seconds ?? 0));
    }

    /**
     * Registra un intento y bloquea si se alcanzó el límite.
     */
    public function hit(string $action, string $identifier): void
    {
        $limits = $this->limitsFor($action);

        $this->run(
            'INSERT INTO throttle (action, identifier, attempts, first_attempt_at, last_attempt_at)
             VALUES (:action, :identifier, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()',
            ['action' => $action, 'identifier' => $identifier]
        );

        if ($this->attempts($action, $identifier) < $limits['attempts']) {
            return;
        }

        $this->run(
            'UPDATE throttle SET blocked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE)
             WHERE action = :action AND identifier = :identifier',
            ['action' => $action, 'identifier' => $identifier, 'minutes' => $limits['minutes']]
        );
    }

    /**
     * Borra el contador tras un intento correcto.
     */
    public function clear(string $action, string $identifier): void
    {
        $this->run(
            'DELETE FROM throttle WHERE action = :action AND identifier = :identifier',
            ['action' => $action, 'identifier' => $identifier]
        );
    }

    /**
     * Elimina registros antiguos. Devuelve cuántos borró.
     */
    public function prune(int $days = 7): int
    {
        return $this->run(
            'DELETE FROM throttle
             WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL :days DAY)
               AND (blocked_until IS NULL OR blocked_until < NOW())',
            ['days' => $days]
        );
    }

    /**
     * Texto humano del tiempo de espera restante.
     */
    public function waitMessage(string $action, string $identifier): string
    {
        $seconds = $this->availableIn($action, $identifier);

        if ($seconds <= 0) {
            return 'Vuelve a intentarlo.';
        }

        if ($seconds < 60) {
            return sprintf('Espera %d segundos e inténtalo de nuevo.', $seconds);
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes === 1
            ? 'Espera 1 minuto e inténtalo de nuevo.'
            : sprintf('Espera %d minutos e inténtalo de nuevo.', $minutes);
    }
}
