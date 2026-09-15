<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\CourseRepository;
use GB\Services\EnrollmentService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class CourseController extends Controller
{
    public function __construct(
        View $view,
        private CourseRepository $courses,
        private EnrollmentService $enrollments,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $courses = [];
        $userId = $this->auth->id();
        $search = trim($request->string('q'));
        $access = $request->string('access');
        $access = in_array($access, ['free', 'paid'], true) ? $access : '';
        $page = max(1, $request->int('page', 1));
        $catalog = $this->courses->publishedCatalog(
            $search,
            $access,
            $page,
            (int) config('app.pagination.per_page', 12)
        );

        foreach ($catalog['items'] as $course) {
            $courseId = (int) ($course['id'] ?? 0);
            $course['is_enrolled'] = $userId !== null && $this->enrollments->hasAccess($userId, $courseId);
            $course['access_label'] = (($course['access_type'] ?? 'free') === 'paid') ? 'Curso de pago' : 'Curso gratuito';
            $courses[] = $course;
        }

        return $this->respond('public.courses', $this->pageData([
            'title' => 'Cursos | ' . (string) config('app.name'),
            'metaDescription' => 'Descubre los cursos y contenidos de González & Ballesteros.',
            'courses' => $courses,
            'search' => $search,
            'access' => $access,
            'pagination' => $catalog,
            'bodyClass' => 'page-courses',
        ]));
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug', '');
        $course = $this->courses->findBySlug($slug);

        if ($course === null) {
            $error = \GB\Support\ErrorResponder::copyFor(404);

            return Response::html(
                $this->view->render('errors.404', $this->pageData([
                    'error' => $error,
                    'title' => $error['title'] . ' | ' . (string) config('app.name'),
                ]), 'layouts.public'),
                404
            );
        }

        $courseId = (int) ($course['id'] ?? 0);
        $userId = $this->auth->id();
        $isEnrolled = $userId !== null && $this->enrollments->hasAccess($userId, $courseId);
        $syllabus = $this->courses->syllabus($courseId);
        $previewOnly = !$isEnrolled;

        $visibleModules = [];
        foreach ($syllabus as $module) {
            $lessons = is_array($module['lessons'] ?? null) ? $module['lessons'] : [];

            if ($previewOnly) {
                $visibleLessons = [];
                foreach ($lessons as $lesson) {
                    if (!empty($lesson['is_preview'])) {
                        $visibleLessons[] = $lesson;
                    }
                }

                if ($visibleLessons === []) {
                    continue;
                }

                $module['lessons'] = $visibleLessons;
            }

            $visibleModules[] = $module;
        }

        return $this->respond('public.course', $this->pageData([
            'title' => (string) ($course['title'] ?? 'Curso') . ' | ' . (string) config('app.name'),
            'metaDescription' => trim((string) ($course['meta_description'] ?? $course['summary'] ?? '')),
            'course' => $course,
            'syllabus' => $visibleModules,
            'isEnrolled' => $isEnrolled,
            'previewOnly' => $previewOnly,
            'bodyClass' => 'page-course',
        ]));
    }
}
