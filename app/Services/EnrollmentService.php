<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\CourseRepository;
use GB\Models\EnrollmentRepository;
use GB\Models\LessonProgressRepository;
use GB\Support\Audit;
use GB\Support\FormResult;
use Throwable;

/**
 * Inscripciones y avance del estudiante.
 *
 * Un curso gratuito entra al área personal al instante. Uno de pago NO se
 * inscribe aquí: espera a que la pasarela confirme el cobro (tarea 9.7). Este
 * servicio se niega explícitamente a inscribir en un curso de pago, para que esa
 * regla no dependa de que cada pantalla se acuerde de comprobarla.
 */
final class EnrollmentService
{
    public function __construct(
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private LessonProgressRepository $progress,
        private Audit $audit,
    ) {
    }

    /**
     * Inscribe en un curso gratuito publicado.
     */
    public function grantFreeCourse(int $userId, string $slug): FormResult
    {
        $course = $this->courses->findBySlug($slug);

        if ($course === null) {
            return FormResult::failed([], message: 'Ese curso ya no está disponible.');
        }

        if (($course['access_type'] ?? '') !== 'free') {
            return FormResult::failed([], message: 'Este curso es de pago: primero hay que completar la compra.');
        }

        $courseId = (int) $course['id'];
        $already = $this->enrollments->hasActiveAccess($userId, $courseId);

        $this->enrollments->grantFree($userId, $courseId);

        if (!$already) {
            $this->record(
                'course_enrolled',
                $courseId,
                sprintf('Inscripción al curso gratuito "%s"', (string) $course['title'])
            );
        }

        return FormResult::ok(
            ['slug' => $slug],
            $already
                ? 'Ya tenías este curso en tu área.'
                : 'Listo: el curso ya está en tu área personal.'
        );
    }

    /**
     * Marca o desmarca una lección y recalcula el avance del curso.
     *
     * Comprueba dos cosas antes de tocar nada: que la inscripción es de esa
     * persona y que la lección pertenece al curso. Sin lo primero, cualquiera
     * podría alterar el avance de otro cambiando un número en la dirección; sin
     * lo segundo, podría inflar su porcentaje con lecciones ajenas.
     */
    public function setLessonCompleted(int $userId, int $courseId, int $lessonId, bool $completed): FormResult
    {
        $enrollment = $this->enrollments->find($userId, $courseId);

        if ($enrollment === null || $enrollment['status'] !== 'active') {
            return FormResult::failed([], message: 'No tienes este curso en tu área personal.');
        }

        if (!$this->courses->ownsLesson($courseId, $lessonId)) {
            return FormResult::failed([], message: 'Esa lección no pertenece a este curso.');
        }

        $enrollmentId = (int) $enrollment['id'];

        if ($completed) {
            $this->progress->complete($userId, $enrollmentId, $lessonId);
        } else {
            $this->progress->reopen($enrollmentId, $lessonId);
        }

        $percent = $this->recalculate($enrollmentId, $courseId);
        $this->enrollments->touch($enrollmentId);

        return FormResult::ok(
            ['percent' => $percent],
            $completed ? 'Lección marcada como completada.' : 'Lección marcada como pendiente.'
        );
    }

    /**
     * Recalcula el porcentaje y lo guarda. Devuelve el valor nuevo.
     */
    public function recalculate(int $enrollmentId, int $courseId): int
    {
        $total = $this->courses->publishedLessonCount($courseId);

        if ($total === 0) {
            $this->enrollments->updateProgress($enrollmentId, 0);

            return 0;
        }

        $done = $this->progress->completedCount($enrollmentId);
        $percent = (int) round($done / $total * 100);

        $this->enrollments->updateProgress($enrollmentId, $percent);

        return $percent;
    }

    /**
     * Cursos del área personal, con su avance y su temario resuelto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function myCourses(int $userId): array
    {
        $courses = [];

        foreach ($this->enrollments->forUser($userId) as $enrollment) {
            $courseId = (int) $enrollment['course_id'];
            $enrollmentId = (int) $enrollment['id'];

            $courses[] = $enrollment + [
                'syllabus' => $this->courses->syllabus($courseId),
                'completed_lesson_ids' => $this->progress->completedLessonIds($enrollmentId),
                'lesson_total' => $this->courses->publishedLessonCount($courseId),
            ];
        }

        return $courses;
    }

    /**
     * Acceso vigente a un curso. Es la única pregunta que deben hacer las
     * pantallas que muestran contenido.
     */
    public function hasAccess(int $userId, int $courseId): bool
    {
        return $this->enrollments->hasActiveAccess($userId, $courseId);
    }

    /**
     * Historial de un estudiante, para el panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyFor(int $userId): array
    {
        return $this->enrollments->allForUser($userId);
    }

    /**
     * Concede acceso a un curso desde el panel.
     *
     * Sirve para los casos que la pasarela no cubre: una venta acordada por
     * fuera, una compensación o un acceso de cortesía. Se registra siempre como
     * concesión manual y queda en auditoría con su autor.
     */
    public function grantManually(int $userId, int $courseId, ?int $adminId): FormResult
    {
        $course = $this->courses->findForAdmin($courseId);

        if ($course === null) {
            return FormResult::failed([], message: 'Ese curso no existe o fue eliminado.');
        }

        $already = $this->enrollments->hasActiveAccess($userId, $courseId);

        // El autor puede no existir (guiones, tareas automáticas). Se conserva
        // nulo en lugar de convertirlo en 0, que apuntaría a un usuario que no
        // existe y rompería la referencia.
        $this->enrollments->grantByAdmin($userId, $courseId, $adminId);

        if (!$already) {
            $this->record(
                'access_granted',
                $courseId,
                sprintf('Acceso concedido a mano al curso "%s"', (string) $course['title'])
            );
        }

        return FormResult::ok(
            ['course_id' => $courseId],
            $already
                ? 'El estudiante ya tenía acceso a ese curso.'
                : 'Acceso concedido y registrado en la auditoría.'
        );
    }

    /**
     * Revoca el acceso conservando el historial de la inscripción.
     */
    public function revokeManually(int $enrollmentId, int $userId, ?int $adminId, string $reason): FormResult
    {
        if (!$this->enrollments->belongsTo($enrollmentId, $userId)) {
            return FormResult::failed([], message: 'Esa inscripción no está vigente.');
        }

        $enrollment = $this->enrollments->findById($enrollmentId);
        $courseId = $enrollment === null ? 0 : (int) $enrollment['course_id'];

        $reason = trim($reason);
        $this->enrollments->revoke(
            $enrollmentId,
            $adminId,
            $reason === '' ? 'Revocado desde el panel' : $reason
        );

        $this->record(
            'access_revoked',
            $courseId,
            $reason === ''
                ? 'Acceso revocado a mano desde el panel del estudiante'
                : 'Acceso revocado: ' . $reason
        );

        return FormResult::ok([], 'El acceso quedó revocado. El historial se conserva.');
    }

    private function record(string $action, int $courseId, string $summary): void
    {
        try {
            $this->audit->log(action: $action, entityType: 'course', entityId: $courseId, summary: $summary);
        } catch (Throwable) {
            // La auditoría es informativa: la inscripción ya está hecha.
        }
    }
}
