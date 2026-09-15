<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

final class PaymentRepository extends Model
{
    protected string $table = 'payments';

    /** @param array<string, mixed> $data */
    public function createPending(array $data): int
    {
        return $this->insert($data + [
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        return $this->selectOne(
            'SELECT id, reference, user_id, course_id, gateway, external_id, amount, currency,
                    status, status_message, paid_at, refunded_at, created_at
             FROM payments WHERE reference = :reference',
            ['reference' => $reference]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $userId): array
    {
        return $this->select(
            'SELECT p.id, p.reference, p.amount, p.currency, p.status, p.gateway,
                    p.created_at, c.title AS course_title
             FROM payments p INNER JOIN courses c ON c.id = p.course_id
             WHERE p.user_id = :user ORDER BY p.created_at DESC, p.id DESC',
            ['user' => $userId]
        );
    }

    /**
     * Ventas confirmadas de un período.
     *
     * @return array{count: int, total: float, currency: string}
     */
    public function paidSummary(string $from, string $to): array
    {
        $row = $this->selectOne(
            "SELECT COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total, COALESCE(MAX(currency), '') AS currency
             FROM payments
             WHERE status = 'paid' AND paid_at IS NOT NULL
               AND paid_at >= :from AND paid_at < :to",
            ['from' => $from, 'to' => $to]
        );

        return [
            'count' => (int) ($row['count'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
            'currency' => (string) ($row['currency'] ?? ''),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, reference, user_id, course_id, gateway, external_id, amount, currency, status
             FROM payments WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Marca la orden como pagada.
     *
     * La condición `status = 'pending'` es la que impide que dos notificaciones
     * simultáneas confirmen la misma compra: la segunda no encuentra nada que
     * actualizar y no vuelve a conceder acceso.
     */
    public function markPaid(int $id, ?string $externalId, ?string $payload = null): bool
    {
        return $this->run(
            "UPDATE payments
             SET status = 'paid', paid_at = NOW(), external_id = :external_id,
                 gateway_payload = :payload, updated_at = NOW()
             WHERE id = :id AND status = 'pending'",
            ['id' => $id, 'external_id' => $externalId, 'payload' => $payload]
        ) > 0;
    }

    public function markFailed(int $id, string $message): void
    {
        $this->run(
            "UPDATE payments
             SET status = 'failed', status_message = :message, updated_at = NOW()
             WHERE id = :id AND status = 'pending'",
            ['id' => $id, 'message' => mb_substr($message, 0, 255)]
        );
    }

    /**
     * Registra el reembolso.
     *
     * La condición `status = 'paid'` evita reembolsar dos veces la misma compra
     * o reembolsar una orden que nunca se cobró.
     */
    public function markRefunded(int $id, ?int $byUserId, string $reason): bool
    {
        return $this->run(
            "UPDATE payments
             SET status = 'refunded', refunded_at = NOW(), refunded_by = :by,
                 refund_reason = :reason, updated_at = NOW()
             WHERE id = :id AND status = 'paid'",
            ['id' => $id, 'by' => $byUserId, 'reason' => mb_substr($reason, 0, 255)]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function findDetail(int $id): ?array
    {
        return $this->selectOne(
            'SELECT p.id, p.reference, p.user_id, p.course_id, p.amount, p.currency,
                    p.status, p.gateway, p.created_at, c.title AS course_title
             FROM payments p INNER JOIN courses c ON c.id = p.course_id
             WHERE p.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Listado de ventas con filtros, para la sección de ventas del panel.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function salesPage(string $from, string $to, string $status, int $page = 1, int $perPage = 25): array
    {
        $where = ['p.created_at >= :from', 'p.created_at < :to'];
        $bindings = ['from' => $from, 'to' => $to];

        if (in_array($status, ['pending', 'paid', 'failed', 'refunded', 'cancelled'], true)) {
            $where[] = 'p.status = :status';
            $bindings['status'] = $status;
        }

        $sql = 'SELECT p.id, p.reference, p.amount, p.currency, p.status, p.gateway, p.created_at,
                       c.title AS course_title, u.name AS student_name, u.last_name AS student_last_name,
                       u.email AS student_email
                FROM payments p
                INNER JOIN courses c ON c.id = p.course_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.created_at DESC, p.id DESC';

        return $this->paginate($sql, $bindings, $perPage, $page);
    }

    /**
     * Resumen por estado del período, para enseñarlo sobre el listado.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function statusTotals(string $from, string $to): array
    {
        $rows = $this->select(
            'SELECT status, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
             FROM payments WHERE created_at >= :from AND created_at < :to
             GROUP BY status',
            ['from' => $from, 'to' => $to]
        );

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row['status']] = [
                'count' => (int) $row['count'],
                'total' => (float) $row['total'],
            ];
        }

        return $totals;
    }
}