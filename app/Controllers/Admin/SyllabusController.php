<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\CourseModuleRepository;
use GB\Models\CourseRepository;
use GB\Models\LessonRepository;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;

final class SyllabusController extends Controller
{
    public function __construct(
        View $view,
        private CourseRepository $courses,
        private CourseModuleRepository $modules,
        private LessonRepository $lessons,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function show(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        $course = $this->courses->findForAdmin($courseId);

        if ($course === null) {
            return $this->redirect('/admin/cursos');
        }

        $modules = $this->modules->forCourse($courseId);
        foreach ($modules as &$module) {
            $module['lessons'] = $this->lessons->forModule((int) $module['id']);
        }
        unset($module);

        return $this->respond('admin.syllabus', $this->pageData([
            'title' => 'Temario | Panel', 'course' => $course, 'modules' => $modules,
            'message' => '', 'errors' => [],
        ]), 'layouts.admin');
    }

    public function storeModule(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        if ($this->courses->findForAdmin($courseId) === null) {
            return $this->redirect('/admin/cursos');
        }

        $input = $request->all();
        $validator = new Validator($input, ['title' => 'Módulo', 'summary' => 'Resumen', 'sort_order' => 'Orden']);
        $validator->validate(['title' => 'required|max:200', 'summary' => 'max:500', 'sort_order' => 'integer']);
        if ($validator->fails()) {
            return $this->redirectWith(
                '/admin/cursos/' . $courseId . '/temario',
                'No se añadió el módulo: ' . (string) ($validator->firstError() ?? 'revisa los datos.'),
                'danger'
            );
        }

        $data = $validator->validated();
        $this->modules->createForCourse($courseId, [
            'title' => $data['title'], 'summary' => $data['summary'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'status' => 'active',
        ]);

        return $this->redirectWith(
            '/admin/cursos/' . $courseId . '/temario',
            'Módulo «' . (string) $data['title'] . '» añadido al temario.'
        );
    }

    public function storeLesson(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        $moduleId = (int) $request->routeParam('module');
        if ($this->modules->findForCourse($moduleId, $courseId) === null) {
            return $this->redirect('/admin/cursos/' . $courseId . '/temario');
        }

        $input = $request->all();
        $validator = new Validator($input, ['title' => 'Lección', 'summary' => 'Resumen', 'content_type' => 'Tipo de contenido', 'duration_minutes' => 'Duración', 'sort_order' => 'Orden']);
        $validator->validate([
            'title' => 'required|max:200', 'summary' => 'max:500',
            'content_type' => 'required|in:video,document,text,link',
            'duration_minutes' => 'integer', 'sort_order' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->redirectWith(
                '/admin/cursos/' . $courseId . '/temario',
                'No se añadió la lección: ' . (string) ($validator->firstError() ?? 'revisa los datos.'),
                'danger'
            );
        }

        $data = $validator->validated();
        $this->lessons->createForModule($moduleId, [
            'title' => $data['title'], 'summary' => $data['summary'] ?? null,
            'content_type' => $data['content_type'],
            'duration_minutes' => ($data['duration_minutes'] ?? '') === '' ? null : (int) $data['duration_minutes'],
            'is_preview' => $request->bool('is_preview') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'status' => 'active',
        ]);

        return $this->redirectWith(
            '/admin/cursos/' . $courseId . '/temario',
            'Lección «' . (string) $data['title'] . '» añadida.'
        );
    }

    public function updateModule(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        $moduleId = (int) $request->routeParam('module');
        $module = $this->modules->findForCourse($moduleId, $courseId);

        if ($module === null) {
            return $this->redirect('/admin/cursos/' . $courseId . '/temario');
        }

        $input = $request->all();
        $validator = new Validator($input, ['title' => 'Módulo', 'summary' => 'Resumen', 'sort_order' => 'Orden']);
        $validator->validate(['title' => 'required|max:200', 'summary' => 'max:500', 'sort_order' => 'integer']);

        if (!$validator->fails()) {
            $data = $validator->validated();
            $this->modules->updateForCourse($moduleId, $courseId, [
                'title' => $data['title'], 'summary' => $data['summary'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0), 'status' => 'active',
            ]);

            return $this->redirectWith('/admin/cursos/' . $courseId . '/temario', 'Cambios del módulo guardados.');
        }

        return $this->redirectWith(
            '/admin/cursos/' . $courseId . '/temario',
            'No se guardó el módulo: ' . (string) ($validator->firstError() ?? 'revisa los datos.'),
            'danger'
        );
    }

    public function destroyModule(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        $moduleId = (int) $request->routeParam('module');
        $module = $this->modules->findForCourse($moduleId, $courseId);

        if ($module === null) {
            return $this->redirectWith('/admin/cursos/' . $courseId . '/temario', 'Ese módulo ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);
        $this->modules->moveToTrash($moduleId, $days);

        return $this->redirectWith('/admin/cursos/' . $courseId . '/temario', sprintf(
            'El módulo «%s» se retiró del temario. Se conserva %d días por si necesitas recuperarlo.',
            (string) $module['title'],
            $days
        ), 'warning');
    }

    public function updateLesson(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        $moduleId = (int) $request->routeParam('module');
        $lessonId = (int) $request->routeParam('lesson');
        $lesson = $this->lessons->findForModule($lessonId, $moduleId);

        if ($lesson === null || $this->modules->findForCourse($moduleId, $courseId) === null) {
            return $this->redirect('/admin/cursos/' . $courseId . '/temario');
        }

        $input = $request->all();
        $validator = new Validator($input, ['title' => 'Lección', 'summary' => 'Resumen', 'content_type' => 'Tipo de contenido', 'duration_minutes' => 'Duración', 'sort_order' => 'Orden']);
        $validator->validate([
            'title' => 'required|max:200', 'summary' => 'max:500',
            'content_type' => 'required|in:video,document,text,link',
            'duration_minutes' => 'integer', 'sort_order' => 'integer',
        ]);

        if (!$validator->fails()) {
            $data = $validator->validated();
            $this->lessons->updateForModule($lessonId, $moduleId, [
                'title' => $data['title'], 'summary' => $data['summary'] ?? null,
                'content_type' => $data['content_type'],
                'duration_minutes' => ($data['duration_minutes'] ?? '') === '' ? null : (int) $data['duration_minutes'],
                'is_preview' => $request->bool('is_preview') ? 1 : 0,
                'sort_order' => (int) ($data['sort_order'] ?? 0), 'status' => 'active',
            ]);

            return $this->redirectWith('/admin/cursos/' . $courseId . '/temario', 'Cambios de la lección guardados.');
        }

        return $this->redirectWith(
            '/admin/cursos/' . $courseId . '/temario',
            'No se guardó la lección: ' . (string) ($validator->firstError() ?? 'revisa los datos.'),
            'danger'
        );
    }

    public function destroyLesson(Request $request): Response
    {
        $courseId = (int) $request->routeParam('id');
        $moduleId = (int) $request->routeParam('module');
        $lessonId = (int) $request->routeParam('lesson');
        $lesson = $this->lessons->findForModule($lessonId, $moduleId);

        if ($lesson === null) {
            return $this->redirectWith('/admin/cursos/' . $courseId . '/temario', 'Esa lección ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);
        $this->lessons->moveToTrash($lessonId, $days);

        return $this->redirectWith('/admin/cursos/' . $courseId . '/temario', sprintf(
            'La lección «%s» se retiró del temario. Se conserva %d días por si necesitas recuperarla.',
            (string) $lesson['title'],
            $days
        ), 'warning');
    }
}