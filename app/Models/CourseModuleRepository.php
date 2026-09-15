<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

final class CourseModuleRepository extends Model
{
    use Trashable;

    protected string $table = 'course_modules';

    /** @return array<int, array<string, mixed>> */
    public function forCourse(int $courseId): array
    {
        return $this->select(
            'SELECT id, course_id, title, summary, sort_order, status
             FROM course_modules
             WHERE course_id = :course AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC',
            ['course' => $courseId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findForCourse(int $id, int $courseId): ?array
    {
        return $this->selectOne(
            'SELECT id, course_id, title, summary, sort_order, status
             FROM course_modules
             WHERE id = :id AND course_id = :course AND deleted_at IS NULL',
            ['id' => $id, 'course' => $courseId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createForCourse(int $courseId, array $data): int
    {
        return $this->insert($data + [
            'course_id' => $courseId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateForCourse(int $id, int $courseId, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->run(
            'UPDATE course_modules SET title = :title, summary = :summary,
                    sort_order = :sort_order, status = :status, updated_at = :updated_at
             WHERE id = :id AND course_id = :course',
            $data + ['id' => $id, 'course' => $courseId]
        );
    }
}