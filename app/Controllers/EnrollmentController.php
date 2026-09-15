<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Services\EnrollmentService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Inscripción a cursos y marcado de lecciones.
 *
 * Sólo la inscripción a cursos GRATUITOS pasa por aquí. El acceso a un curso de
 * pago no se concede nunca desde una pantalla: lo concede la confirmación de la
 * pasarela (tarea 9.7). Así, manipular esta dirección no da acceso a nada.
 */
final class EnrollmentController extends Controller
{
    public function __construct(
        View $view,
        private EnrollmentService $enrollments,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    /**
     * Inscribe en el curso gratuito y lleva al área personal.
     */
    public function store(Request $request): Response
    {
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $slug = (string) $request->routeParam('slug');
        $result = $this->enrollments->grantFreeCourse($userId, $slug);

        // Se responde a la ficha del curso, donde ya aparece el acceso.
        return $this->redirect('/cursos/' . $slug . ($result->succeeded() ? '' : '?aviso=1'));
    }

    /**
     * Marca o desmarca una lección. Responde en JSON porque la acción se hace sin
     * recargar la página y sólo hay que actualizar la barra de avance.
     */
    public function completeLesson(Request $request): Response
    {
        $userId = (int) ($this->auth->user()['id'] ?? 0);

        $courseId = (int) $request->routeParam('course');
        $lessonId = (int) $request->routeParam('lesson');

        // El campo viaja en el formulario; si no llega, se entiende "completar".
        $completed = $request->input('completed');
        $completed = $completed === null ? true : filter_var($completed, FILTER_VALIDATE_BOOLEAN);

        $result = $this->enrollments->setLessonCompleted($userId, $courseId, $lessonId, (bool) $completed);

        return $this->respondJson([
            'ok' => $result->succeeded(),
            'message' => $result->succeeded() ? $result->message() : ($result->firstError() ?? 'No se pudo actualizar la lección.'),
            'percent' => $result->values()['percent'] ?? null,
        ], $result->succeeded() ? 200 : 422);
    }
}
