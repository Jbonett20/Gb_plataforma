<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

/**
 * Cursos: catálogo público y temario.
 *
 * Esta clase cubre lo que necesita el área del estudiante (listar sus cursos y
 * recorrer sus lecciones). La edición desde el panel se completa en la tarea 8.1.
 */
final class CourseRepository extends Model
{
    use Trashable;

    protected string $table = 'courses';

    /**
     * Columnas que puede ver el sitio público.
     */
    private const PUBLIC_COLUMNS = 'c.id, c.slug, c.title, c.subtitle, c.summary, c.cover_media_id,
               c.instructor_name, c.access_type, c.price, c.currency, c.level, c.category,
               c.duration_minutes, c.lessons_count, c.published_at';

    /**
     * Cursos visibles en el sitio, del orden indicado en el panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function published(int $limit = 0): array
    {
        $sql = 'SELECT ' . self::PUBLIC_COLUMNS . '
                FROM courses c
                WHERE c.deleted_at IS NULL AND c.status = :status
                  AND (c.published_at IS NULL OR c.published_at <= NOW())
                ORDER BY c.sort_order ASC, c.title ASC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return $this->select($sql, ['status' => 'published']);
    }

    /**
     * Catálogo público filtrable y paginado.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function publishedCatalog(string $search = '', string $access = '', int $page = 1, int $perPage = 12): array
    {
        $where = [
            'c.deleted_at IS NULL',
            'c.status = :status',
            '(c.published_at IS NULL OR c.published_at <= NOW())',
        ];
        $bindings = ['status' => 'published'];

        $search = trim($search);
        if ($search !== '') {
            $where[] = '(LOWER(c.title) LIKE :search_title OR LOWER(c.subtitle) LIKE :search_subtitle
                OR LOWER(c.summary) LIKE :search_summary OR LOWER(c.category) LIKE :search_category)';
            $term = '%' . mb_strtolower($search) . '%';
            $bindings['search_title'] = $term;
            $bindings['search_subtitle'] = $term;
            $bindings['search_summary'] = $term;
            $bindings['search_category'] = $term;
        }

        if (in_array($access, ['free', 'paid'], true)) {
            $where[] = 'c.access_type = :access_type';
            $bindings['access_type'] = $access;
        }

        $sql = 'SELECT ' . self::PUBLIC_COLUMNS . '
                FROM courses c
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.sort_order ASC, c.title ASC';

        return $this->paginate($sql, $bindings, $perPage, $page);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->selectOne(
            'SELECT ' . self::PUBLIC_COLUMNS . '
             FROM courses c
             WHERE c.slug = :slug AND c.deleted_at IS NULL AND c.status = :status',
            ['slug' => $slug, 'status' => 'published']
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function adminList(): array
    {
        return $this->select(
            'SELECT ' . self::PUBLIC_COLUMNS . ', c.description, c.requirements,
                    c.instructor_bio, c.status, c.sort_order, c.updated_at
             FROM courses c
             WHERE c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.updated_at DESC, c.title ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findForAdmin(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, slug, title, subtitle, summary, description, requirements,
                    cover_media_id, instructor_name, instructor_bio, access_type,
                    price, currency, level, category, duration_minutes, lessons_count,
                    status, sort_order, meta_title, meta_description
             FROM courses WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** Cursos visibles hoy en el catálogo público. */
    public function countPublished(): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM courses
             WHERE deleted_at IS NULL AND status = 'published'
               AND (published_at IS NULL OR published_at <= NOW())"
        );
    }

    /** @param array<string, mixed> $data */
    public function createAdmin(array $data): int
    {
        return $this->insert($data + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateAdmin(int $id, array $data): void
    {
        $sets = [];
        $bindings = ['id' => $id];

        foreach ($data as $field => $value) {
            $sets[] = $field . ' = :' . $field;
            $bindings[$field] = $value;
        }

        if ($sets !== []) {
            $this->run('UPDATE courses SET ' . implode(', ', $sets) . ' WHERE id = :id', $bindings);
        }
    }

    /**
     * Comprueba los mínimos necesarios antes de hacer visible un curso.
     *
     * @param array<string, mixed> $candidate Datos que todavía no se han guardado.
     * @return array<int, string>
     */
    public function publicationIssues(?int $id, array $candidate = []): array
    {
        $course = $id === null ? [] : ($this->findForAdmin($id) ?? []);
        $course = array_merge($course, $candidate);
        $issues = [];

        if (trim((string) ($course['title'] ?? '')) === '') {
            $issues[] = 'Añade un título.';
        }

        if (trim((string) ($course['description'] ?? '')) === '') {
            $issues[] = 'Completa la descripción del curso.';
        }

        if ((int) ($course['cover_media_id'] ?? 0) <= 0) {
            $issues[] = 'Selecciona una portada.';
        }

        $lessonCount = $id === null ? 0 : $this->publishedLessonCount($id);
        if ($lessonCount < 1) {
            $issues[] = 'Añade al menos una lección activa al temario.';
        }

        if (!in_array((string) ($course['access_type'] ?? ''), ['free', 'paid'], true)) {
            $issues[] = 'Elige si el curso es gratuito o de pago.';
        }

        return $issues;
    }

    /**
     * Módulos y lecciones de un curso, en orden.
     *
     * Devuelve una lista de módulos, cada uno con sus lecciones colgando de la
     * clave `lessons`, que es como los recorre la vista.
     *
     * @return array<int, array<string, mixed>>
     */
    public function syllabus(int $courseId): array
    {
        $rows = $this->select(
            'SELECT m.id AS module_id, m.title AS module_title, m.summary AS module_summary,
                    m.sort_order AS module_order,
                    l.id AS lesson_id, l.title AS lesson_title, l.summary AS lesson_summary,
                    l.content_type, l.duration_minutes, l.is_preview, l.sort_order AS lesson_order
             FROM course_modules m
             LEFT JOIN lessons l ON l.course_module_id = m.id AND l.deleted_at IS NULL AND l.status = :lesson_status
             WHERE m.course_id = :course AND m.deleted_at IS NULL AND m.status = :module_status
             ORDER BY m.sort_order ASC, m.id ASC, l.sort_order ASC, l.id ASC',
            ['course' => $courseId, 'module_status' => 'active', 'lesson_status' => 'active']
        );

        $modules = [];

        foreach ($rows as $row) {
            $moduleId = (int) $row['module_id'];

            if (!isset($modules[$moduleId])) {
                $modules[$moduleId] = [
                    'id' => $moduleId,
                    'title' => (string) $row['module_title'],
                    'summary' => $row['module_summary'],
                    'lessons' => [],
                ];
            }

            if ($row['lesson_id'] === null) {
                continue;
            }

            $modules[$moduleId]['lessons'][] = [
                'id' => (int) $row['lesson_id'],
                'title' => (string) $row['lesson_title'],
                'summary' => $row['lesson_summary'],
                'content_type' => (string) $row['content_type'],
                'duration_minutes' => $row['duration_minutes'] === null ? null : (int) $row['duration_minutes'],
                'is_preview' => (int) $row['is_preview'] === 1,
            ];
        }

        return array_values($modules);
    }

    /**
     * Cuenta las lecciones publicadas de un curso. Es la base del porcentaje de
     * avance: sin lecciones no se puede calcular nada.
     */
    public function publishedLessonCount(int $courseId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*)
             FROM lessons l
             INNER JOIN course_modules m ON m.id = l.course_module_id
             WHERE m.course_id = :course AND m.deleted_at IS NULL AND m.status = :module_status
               AND l.deleted_at IS NULL AND l.status = :lesson_status',
            ['course' => $courseId, 'module_status' => 'active', 'lesson_status' => 'active']
        );
    }

    /**
     * Comprueba que una lección pertenece al curso indicado. Evita que alguien
     * marque como completada una lección de un curso que no ha adquirido.
     */
    public function ownsLesson(int $courseId, int $lessonId): bool
    {
        return $this->exists(
            'SELECT 1
             FROM lessons l
             INNER JOIN course_modules m ON m.id = l.course_module_id
             WHERE l.id = :lesson AND m.course_id = :course AND l.deleted_at IS NULL',
            ['lesson' => $lessonId, 'course' => $courseId]
        );
    }

    /** @return array<string, mixed>|null */
    public function protectedLesson(int $lessonId): ?array
    {
        return $this->selectOne(
            'SELECT l.id, l.course_module_id, l.title, l.content_type, l.protected_path,
                    m.course_id
             FROM lessons l INNER JOIN course_modules m ON m.id = l.course_module_id
             WHERE l.id = :lesson AND l.deleted_at IS NULL AND l.status = :lesson_status
               AND m.deleted_at IS NULL AND m.status = :module_status',
            ['lesson' => $lessonId, 'lesson_status' => 'active', 'module_status' => 'active']
        );
    }
}
