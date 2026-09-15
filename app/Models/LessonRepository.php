<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

final class LessonRepository extends Model
{
    use Trashable;

    protected string $table = 'lessons';

    /** @return array<int, array<string, mixed>> */
    public function forModule(int $moduleId): array
    {
        return $this->select(
            'SELECT id, course_module_id, title, summary, content_type, duration_minutes,
                    is_preview, sort_order, status
             FROM lessons
             WHERE course_module_id = :module AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC',
            ['module' => $moduleId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createForModule(int $moduleId, array $data): int
    {
        return $this->insert($data + [
            'course_module_id' => $moduleId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findForModule(int $id, int $moduleId): ?array
    {
        return $this->selectOne(
            'SELECT id, course_module_id, title, summary, content_type, duration_minutes,
                    is_preview, sort_order, status
             FROM lessons
             WHERE id = :id AND course_module_id = :module AND deleted_at IS NULL',
            ['id' => $id, 'module' => $moduleId]
        );
    }

    /** @param array<string, mixed> $data */
    public function updateForModule(int $id, int $moduleId, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->run(
            'UPDATE lessons SET title = :title, summary = :summary,
                    content_type = :content_type, duration_minutes = :duration_minutes,
                    is_preview = :is_preview, sort_order = :sort_order,
                    status = :status, updated_at = :updated_at
             WHERE id = :id AND course_module_id = :module',
            $data + ['id' => $id, 'module' => $moduleId]
        );
    }
}