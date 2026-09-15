<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Avance de la persona por cada lección.
 *
 * Marcar una lección como completada es reversible: si alguien se equivoca,
 * puede desmarcarla y el porcentaje se recalcula.
 */
final class LessonProgressRepository extends Model
{
    protected string $table = 'lesson_progress';

    /**
     * Identificadores de las lecciones ya completadas dentro de una inscripción.
     *
     * @return array<int, int>
     */
    public function completedLessonIds(int $enrollmentId): array
    {
        $ids = [];

        foreach ($this->select(
            'SELECT lesson_id FROM lesson_progress WHERE enrollment_id = :enrollment AND completed_at IS NOT NULL',
            ['enrollment' => $enrollmentId]
        ) as $row) {
            $ids[] = (int) $row['lesson_id'];
        }

        return $ids;
    }

    /**
     * Marca una lección como completada. Si ya lo estaba, no cambia nada.
     */
    public function complete(int $userId, int $enrollmentId, int $lessonId): void
    {
        $this->run(
            'INSERT INTO lesson_progress (user_id, enrollment_id, lesson_id, completed_at, created_at, updated_at)
             VALUES (:user, :enrollment, :lesson, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE completed_at = COALESCE(completed_at, NOW()), updated_at = NOW()',
            ['user' => $userId, 'enrollment' => $enrollmentId, 'lesson' => $lessonId]
        );
    }

    public function reopen(int $enrollmentId, int $lessonId): void
    {
        $this->run(
            'UPDATE lesson_progress SET completed_at = NULL
             WHERE enrollment_id = :enrollment AND lesson_id = :lesson',
            ['enrollment' => $enrollmentId, 'lesson' => $lessonId]
        );
    }

    /**
     * Cuántas lecciones de este curso ha completado la persona, contando sólo
     * las que siguen publicadas (si una lección se retira, no debe bajar el
     * porcentaje de nadie).
     */
    public function completedCount(int $enrollmentId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*)
             FROM lesson_progress p
             INNER JOIN lessons l ON l.id = p.lesson_id
             INNER JOIN course_modules m ON m.id = l.course_module_id
             WHERE p.enrollment_id = :enrollment AND p.completed_at IS NOT NULL
               AND l.deleted_at IS NULL AND l.status = :lesson_status
               AND m.deleted_at IS NULL AND m.status = :module_status',
            [
                'enrollment' => $enrollmentId,
                'lesson_status' => 'active',
                'module_status' => 'active',
            ]
        );
    }
}
