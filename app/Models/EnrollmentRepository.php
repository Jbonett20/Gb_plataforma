<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Inscripciones: la única fuente de verdad sobre quién puede ver qué curso.
 *
 * Ninguna pantalla decide por su cuenta si alguien tiene acceso; todas
 * preguntan aquí. Un curso gratuito se inscribe al instante; uno de pago se
 * inscribe cuando la pasarela confirma el cobro (tarea 9.7).
 */
final class EnrollmentRepository extends Model
{
    protected string $table = 'enrollments';

    /**
     * Cursos en los que está inscrita una persona, con el resumen del curso y
     * su avance. El área personal se dibuja con esto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->select(
            'SELECT e.id, e.source, e.status, e.progress_percent, e.last_access_at, e.created_at,
                    e.granted_at, e.revoked_at,
                    c.id AS course_id, c.slug, c.title, c.subtitle, c.summary, c.cover_media_id,
                    c.access_type, c.level, c.duration_minutes, c.lessons_count
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE e.user_id = :user AND e.status = :status AND c.deleted_at IS NULL
             ORDER BY e.last_access_at DESC, e.created_at DESC',
            ['user' => $userId, 'status' => 'active']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $userId, int $courseId): ?array
    {
        return $this->selectOne(
            'SELECT id, user_id, course_id, source, status, progress_percent, last_access_at,
                    granted_at, revoked_at, revoke_reason
             FROM enrollments
             WHERE user_id = :user AND course_id = :course
             LIMIT 1',
            ['user' => $userId, 'course' => $courseId]
        );
    }

    /**
     * Inscripción vigente: existe, está activa y no ha vencido.
     */
    public function hasActiveAccess(int $userId, int $courseId): bool
    {
        return $this->exists(
            'SELECT 1 FROM enrollments
             WHERE user_id = :user AND course_id = :course AND status = :status
               AND (expires_at IS NULL OR expires_at > NOW())',
            ['user' => $userId, 'course' => $courseId, 'status' => 'active']
        );
    }

    /**
     * Inscribe en un curso gratuito. Si ya existía una inscripción revocada, se
     * reactiva en lugar de crear otra: la tabla sólo admite una por persona y
     * curso.
     */
    public function grantFree(int $userId, int $courseId): int
    {
        $existing = $this->find($userId, $courseId);

        if ($existing !== null) {
            if ($existing['status'] !== 'active') {
                $this->run(
                    'UPDATE enrollments
                     SET status = :status, revoked_at = NULL, revoked_by = NULL, revoke_reason = NULL,
                         source = :source, granted_at = NOW()
                     WHERE id = :id',
                    ['status' => 'active', 'source' => 'free', 'id' => (int) $existing['id']]
                );
            }

            return (int) $existing['id'];
        }

        return $this->insert([
            'user_id' => $userId,
            'course_id' => $courseId,
            'source' => 'free',
            'status' => 'active',
            'granted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateProgress(int $enrollmentId, int $percent): void
    {
        $this->run(
            'UPDATE enrollments SET progress_percent = :percent WHERE id = :id',
            ['percent' => max(0, min(100, $percent)), 'id' => $enrollmentId]
        );
    }

    public function touch(int $enrollmentId): void
    {
        $this->run('UPDATE enrollments SET last_access_at = NOW() WHERE id = :id', ['id' => $enrollmentId]);
    }

    /**
     * Revoca el acceso conservando el historial.
     */
    public function revoke(int $enrollmentId, ?int $byUserId, string $reason): void
    {
        $this->run(
            'UPDATE enrollments
             SET status = :status, revoked_at = NOW(), revoked_by = :by, revoke_reason = :reason
             WHERE id = :id',
            [
                'status' => 'revoked',
                'by' => $byUserId,
                'reason' => mb_substr($reason, 0, 255),
                'id' => $enrollmentId,
            ]
        );
    }

    /**
     * Comprueba que la inscripción pertenece a esa persona antes de dejar
     * tocar su avance.
     */
    public function belongsTo(int $enrollmentId, int $userId): bool
    {
        return $this->exists(
            'SELECT 1 FROM enrollments WHERE id = :id AND user_id = :user AND status = :status',
            ['id' => $enrollmentId, 'user' => $userId, 'status' => 'active']
        );
    }

    /**
     * Historial completo de un estudiante, incluidas las inscripciones
     * revocadas: el panel necesita conservar la trazabilidad de lo ocurrido.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        return $this->select(
            'SELECT e.id, e.source, e.status, e.progress_percent, e.granted_at, e.revoked_at,
                    e.revoke_reason, e.created_at,
                    c.id AS course_id, c.title AS course_title, c.access_type
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE e.user_id = :user AND c.deleted_at IS NULL
             ORDER BY e.created_at DESC, e.id DESC',
            ['user' => $userId]
        );
    }

    /**
     * Concede acceso por compra confirmada. Queda ligado al pago que lo
     * originó, de modo que un reembolso pueda revocar exactamente ese acceso.
     */
    public function grantPurchase(int $userId, int $courseId, int $paymentId): int
    {
        $existing = $this->find($userId, $courseId);

        if ($existing !== null) {
            if ($existing['status'] !== 'active') {
                $this->run(
                    'UPDATE enrollments
                     SET status = :status, source = :source, payment_id = :payment,
                         revoked_at = NULL, revoked_by = NULL, revoke_reason = NULL, granted_at = NOW()
                     WHERE id = :id',
                    [
                        'status' => 'active',
                        'source' => 'purchase',
                        'payment' => $paymentId,
                        'id' => (int) $existing['id'],
                    ]
                );
            }

            return (int) $existing['id'];
        }

        return $this->insert([
            'user_id' => $userId,
            'course_id' => $courseId,
            'source' => 'purchase',
            'payment_id' => $paymentId,
            'status' => 'active',
            'granted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByPayment(int $paymentId): ?array
    {
        return $this->selectOne(
            'SELECT id, user_id, course_id, status FROM enrollments WHERE payment_id = :payment LIMIT 1',
            ['payment' => $paymentId]
        );
    }

    /**
     * Revoca el acceso concedido por una compra concreta.
     *
     * Se apoya en `payment_id` y no en el par usuario/curso: así un reembolso
     * retira exactamente el acceso que originó esa compra.
     */
    public function revokeByPayment(int $paymentId, ?int $byUserId, string $reason): int
    {
        return $this->run(
            "UPDATE enrollments
             SET status = 'revoked', revoked_at = NOW(), revoked_by = :by, revoke_reason = :reason
             WHERE payment_id = :payment AND status = 'active'",
            ['payment' => $paymentId, 'by' => $byUserId, 'reason' => mb_substr($reason, 0, 255)]
        );
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, user_id, course_id, source, status, revoked_at, revoke_reason
             FROM enrollments WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Concede acceso desde el panel. Queda registrado quién lo hizo y cuándo,
     * y la inscripción se identifica como concesión manual (source = granted),
     * no como compra: la auditoría debe poder distinguirlas.
     */
    public function grantByAdmin(int $userId, int $courseId, ?int $adminId): int
    {
        $existing = $this->find($userId, $courseId);

        if ($existing !== null) {
            if ($existing['status'] !== 'active') {
                $this->run(
                    'UPDATE enrollments
                     SET status = :status, revoked_at = NULL, revoked_by = NULL, revoke_reason = NULL,
                         source = :source, granted_by = :admin, granted_at = NOW()
                     WHERE id = :id',
                    [
                        'status' => 'active',
                        'source' => 'granted',
                        'admin' => $adminId,
                        'id' => (int) $existing['id'],
                    ]
                );
            }

            return (int) $existing['id'];
        }

        return $this->insert([
            'user_id' => $userId,
            'course_id' => $courseId,
            'source' => 'granted',
            'granted_by' => $adminId,
            'status' => 'active',
            'granted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
