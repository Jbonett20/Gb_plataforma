<?php

declare(strict_types=1);

namespace GB\Support;

use PDO;

/**
 * Registro de acciones para auditoría.
 *
 * Responde a "¿quién hizo esto y cuándo?" sin que el SuperAdmin tenga que leer
 * registros técnicos: `summary` se guarda redactado en lenguaje natural.
 */
final class Audit extends Model
{
    protected string $table = 'audit_logs';

    public function __construct(
        PDO $pdo,
        private Auth $auth,
        private Request $request,
    ) {
        parent::__construct($pdo);
    }

    /**
     * @param array<string, mixed> $changes valores anteriores y nuevos
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $summary = null,
        array $changes = [],
    ): void {
        $user = $this->auth->user();

        $this->insert([
            'user_id' => $user === null ? null : (int) $user['id'],
            'actor_label' => $this->actorLabel($user),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => $summary,
            'changes' => $changes === [] ? null : (json_encode($changes, JSON_UNESCAPED_UNICODE) ?: null),
            'ip' => $this->request->ip(),
            'user_agent' => mb_substr($this->request->userAgent(), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Últimas acciones registradas, para mostrarlas en el panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        return $this->select(
            'SELECT id, actor_label, action, entity_type, entity_id, summary, ip, created_at
             FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Historial de un elemento concreto (por ejemplo un curso o un bloque).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forEntity(string $entityType, int $entityId, int $limit = 30): array
    {
        return $this->select(
            'SELECT id, actor_label, action, summary, changes, created_at
             FROM audit_logs
             WHERE entity_type = :type AND entity_id = :id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . max(1, $limit),
            ['type' => $entityType, 'id' => $entityId]
        );
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function actorLabel(?array $user): string
    {
        if ($user === null) {
            return 'sistema';
        }

        $name = trim(sprintf('%s %s', $user['name'] ?? '', $user['last_name'] ?? ''));

        return $name === '' ? (string) ($user['email'] ?? 'desconocido') : $name;
    }
}
