<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Mensajes de confirmación entre una pantalla y la siguiente (tarea 11.6).
 *
 * Cuando se guarda, se publica o se borra algo, el panel no muestra el
 * resultado en la misma respuesta: redirige para que al recargar no se repita
 * la operación. El mensaje viaja en la sesión y se consume una sola vez, de
 * modo que aparece al llegar y desaparece al recargar.
 *
 * Nada de esto es para el visitante del sitio: sólo se dibuja en el panel.
 */
final class Flash
{
    private const KEY = 'flash_messages';

    /** Tipos admitidos, alineados con las clases de Bootstrap. */
    private const TYPES = ['success', 'danger', 'warning', 'info'];

    public function __construct(private Session $session)
    {
    }

    public function success(string $message): void
    {
        $this->put('success', $message);
    }

    public function error(string $message): void
    {
        $this->put('danger', $message);
    }

    public function warning(string $message): void
    {
        $this->put('warning', $message);
    }

    public function info(string $message): void
    {
        $this->put('info', $message);
    }

    public function put(string $type, string $message): void
    {
        $message = trim($message);

        if ($message === '') {
            return;
        }

        $this->session->start();
        $messages = $this->session->get(self::KEY, []);
        $messages = is_array($messages) ? $messages : [];
        $messages[] = [
            'type' => in_array($type, self::TYPES, true) ? $type : 'info',
            'message' => $message,
        ];

        $this->session->put(self::KEY, $messages);
    }

    /**
     * Devuelve los mensajes acumulados y los borra: se muestran una sola vez.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public function pull(): array
    {
        $this->session->start();
        $messages = $this->session->get(self::KEY, []);
        $this->session->forget(self::KEY);

        if (!is_array($messages)) {
            return [];
        }

        return array_values(array_filter($messages, static fn (mixed $item): bool => is_array($item)
            && isset($item['message'])
            && trim((string) $item['message']) !== ''));
    }
}
