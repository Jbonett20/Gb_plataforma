<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Support\Session;

/**
 * Confirmación en dos pasos para acciones irreversibles.
 *
 * Borrar contenido definitivamente no puede ocurrir de un solo clic. El panel
 * pide la confirmación y esta clase emite un pase de un solo uso, ligado a la
 * sesión y con caducidad. El pase demuestra que la persona vio la advertencia
 * (cuántos elementos se pierden y que no se puede deshacer).
 */
final class TrashService
{
    private const SESSION_KEY = 'trash_confirmations';

    /** Minutos que vale una confirmación pendiente. */
    private const TTL_SECONDS = 900;

    public function __construct(private Session $session)
    {
    }

    /**
     * Emite el pase de confirmación y devuelve su identificador.
     */
    public function requestConfirmation(string $type, int $id, string $label, string $consequence): string
    {
        $token = bin2hex(random_bytes(16));

        $pending = $this->all();
        $pending[$token] = [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'consequence' => $consequence,
            'expires_at' => time() + self::TTL_SECONDS,
        ];

        $this->save($pending);

        return $token;
    }

    /**
     * Datos de una confirmación pendiente, sin consumirla.
     *
     * @return array{type: string, id: int, label: string, consequence: string, expires_at: int}|null
     */
    public function pending(string $token): ?array
    {
        $pending = $this->all();

        if (!isset($pending[$token]) || !is_array($pending[$token])) {
            return null;
        }

        $entry = $pending[$token];

        if (($entry['expires_at'] ?? 0) < time()) {
            unset($pending[$token]);
            $this->save($pending);

            return null;
        }

        return [
            'type' => (string) $entry['type'],
            'id' => (int) $entry['id'],
            'label' => (string) $entry['label'],
            'consequence' => (string) $entry['consequence'],
            'expires_at' => (int) $entry['expires_at'],
        ];
    }

    /**
     * Comprueba y consume la confirmación. Devuelve los datos si era válida.
     *
     * @return array{type: string, id: int, label: string, consequence: string, expires_at: int}|null
     */
    public function consume(string $token): ?array
    {
        $entry = $this->pending($token);

        if ($entry !== null) {
            $this->forget($token);
        }

        return $entry;
    }

    public function forget(string $token): void
    {
        $pending = $this->all();
        unset($pending[$token]);
        $this->save($pending);
    }

    /**
     * Descarta las confirmaciones vencidas.
     */
    public function prune(): void
    {
        $pending = array_filter(
            $this->all(),
            static fn (array $entry): bool => ($entry['expires_at'] ?? 0) >= time()
        );

        $this->save($pending);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function all(): array
    {
        $pending = $this->session->get(self::SESSION_KEY);

        return is_array($pending) ? $pending : [];
    }

    /**
     * @param array<string, array<string, mixed>> $pending
     */
    private function save(array $pending): void
    {
        $this->session->put(self::SESSION_KEY, $pending);
    }
}
