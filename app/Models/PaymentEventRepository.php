<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;
use PDOException;

/**
 * Notificaciones recibidas de la pasarela.
 *
 * La tabla tiene un índice único por proveedor e identificador de evento, que
 * es lo que hace posible la idempotencia: un reenvío del mismo evento se
 * reconoce y no vuelve a producir efectos.
 */
final class PaymentEventRepository extends Model
{
    protected string $table = 'webhook_events';

    /**
     * Registra el evento. Devuelve su identificador y si era nuevo.
     *
     * @param array<string, mixed> $data
     * @return array{id: int, is_new: bool}
     */
    public function record(array $data): array
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            return ['id' => $this->insert($data), 'is_new' => true];
        } catch (PDOException $exception) {
            // 23000 = violación de restricción: el evento ya estaba registrado.
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $id = (int) $this->scalar(
                'SELECT id FROM webhook_events WHERE provider = :provider AND external_event_id = :event',
                ['provider' => $data['provider'], 'event' => $data['external_event_id']]
            );

            $this->run(
                'UPDATE webhook_events SET attempt_count = attempt_count + 1, updated_at = NOW() WHERE id = :id',
                ['id' => $id]
            );

            return ['id' => $id, 'is_new' => false];
        }
    }

    public function markProcessed(int $id, string $result): void
    {
        $this->run(
            'UPDATE webhook_events
             SET processed = 1, processed_at = NOW(), result = :result, updated_at = NOW()
             WHERE id = :id',
            ['id' => $id, 'result' => mb_substr($result, 0, 255)]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->select(
            'SELECT provider, external_event_id, event_type, payment_reference, signature_valid,
                    processed, result, attempt_count, created_at
             FROM webhook_events
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min($limit, 200))
        );
    }
}
