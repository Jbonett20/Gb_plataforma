<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

final class ContentAccessRepository extends Model
{
    protected string $table = 'content_access_log';

    public function record(?int $userId, ?int $courseId, ?int $lessonId, string $action, bool $delivered, ?string $reason, string $ip, string $userAgent): void
    {
        $this->insert([
            'user_id' => $userId, 'course_id' => $courseId, 'lesson_id' => $lessonId,
            'action' => $action, 'delivered' => $delivered ? 1 : 0, 'reason' => $reason,
            'ip' => mb_substr($ip, 0, 45), 'user_agent' => mb_substr($userAgent, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        return $this->select(
            'SELECT l.created_at, l.action, l.delivered, l.reason, l.ip,
                    l.user_id, l.course_id, l.lesson_id,
                    c.title AS course_title, le.title AS lesson_title
             FROM content_access_log l
             LEFT JOIN courses c ON c.id = l.course_id
             LEFT JOIN lessons le ON le.id = l.lesson_id
             ORDER BY l.created_at DESC, l.id DESC LIMIT ' . max(1, min($limit, 500))
        );
    }
}