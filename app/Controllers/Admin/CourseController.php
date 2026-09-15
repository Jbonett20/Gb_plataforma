<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\CourseRepository;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;

final class CourseController extends Controller
{
    public function __construct(View $view, private CourseRepository $courses, private Auth $auth)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.courses', [
            'title' => 'Cursos | Panel',
            'courses' => $this->courses->adminList(),
            'message' => '',
            'errors' => [],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form([], [], '', 'Nuevo curso');
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function edit(Request $request): Response
    {
        $course = $this->courses->findForAdmin((int) $request->routeParam('id'));

        if ($course === null) {
            return $this->redirectWith('/admin/cursos', 'Ese curso ya no existe.', 'warning');
        }

        return $this->form($course, [], '', 'Editar curso');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, (int) $request->routeParam('id'));
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $course = $this->courses->findForAdmin($id);

        if ($course === null) {
            return $this->redirectWith('/admin/cursos', 'Ese curso ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);
        $this->courses->moveToTrash($id, $days);

        return $this->redirectWith('/admin/cursos', sprintf(
            'El curso «%s» se movió a la papelera. No se ha borrado: sus datos se conservan %d días y puedes restaurarlo antes.',
            (string) $course['title'],
            $days
        ), 'warning');
    }

    private function save(Request $request, ?int $id): Response
    {
        $input = $request->all();
        $validator = new Validator($input, [
            'title' => 'Título', 'slug' => 'Dirección del curso', 'summary' => 'Resumen',
            'description' => 'Descripción', 'access_type' => 'Tipo de acceso',
            'price' => 'Precio', 'currency' => 'Moneda', 'level' => 'Nivel',
            'category' => 'Categoría', 'duration_minutes' => 'Duración', 'status' => 'Estado',
            'cover_media_id' => 'Portada',
        ]);
        $validator->validate([
            'title' => 'required|max:200', 'slug' => 'required|max:190|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'summary' => 'max:500', 'description' => 'max:50000', 'access_type' => 'required|in:free,paid',
            'price' => 'numeric', 'currency' => 'required|max:3', 'level' => 'max:20',
            'category' => 'max:80', 'duration_minutes' => 'integer', 'cover_media_id' => 'integer',
            'status' => 'required|in:draft,published,hidden',
        ]);

        if ($validator->fails()) {
            return $this->form($input, $validator->errors(), $validator->firstError() ?? '', $id === null ? 'Nuevo curso' : 'Editar curso', $id === null ? 422 : 422);
        }

        $data = $validator->validated();
        $data['price'] = number_format((float) ($data['price'] ?? 0), 2, '.', '');
        $data['duration_minutes'] = ($data['duration_minutes'] ?? '') === '' ? null : (int) $data['duration_minutes'];
        $data['updated_by'] = $this->auth->id();

        if ($data['status'] === 'published') {
            $issues = $this->courses->publicationIssues($id, $data);

            if ($issues !== []) {
                return $this->form(
                    $input,
                    ['status' => $issues],
                    'No se puede publicar todavía: ' . implode(' ', $issues),
                    $id === null ? 'Nuevo curso' : 'Editar curso',
                    422
                );
            }

            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if ($id === null) {
            $data['created_by'] = $this->auth->id();
            $this->courses->createAdmin($data);
        } else {
            $this->courses->updateAdmin($id, $data);
        }

        return $this->redirectWith('/admin/cursos', $this->savedMessage($id, (string) $data['title'], (string) $data['status']));
    }

    /**
     * Confirmación de guardado. Dice también si el cambio se ve ya en el sitio o
     * todavía no, que es la duda inmediata de quien acaba de guardar.
     */
    private function savedMessage(?int $id, string $title, string $status): string
    {
        $what = $id === null ? 'El curso «%s» se creó.' : 'Los cambios del curso «%s» se guardaron.';

        return sprintf($what, $title) . ' ' . match ($status) {
            'published' => 'Ya aparece en el catálogo público.',
            'hidden' => 'Está oculto: no se lista, pero su enlace directo sigue funcionando.',
            default => 'Sigue como borrador: todavía no aparece en el catálogo público.',
        };
    }

    /** @param array<string, mixed> $values @param array<string, array<int, string>> $errors */
    private function form(array $values, array $errors, string $message, string $heading, int $status = 200): Response
    {
        return $this->admin('admin.course-form', [
            'title' => $heading . ' | Panel', 'heading' => $heading, 'values' => $values,
            'errors' => $errors, 'message' => $message,
        ], $status);
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}