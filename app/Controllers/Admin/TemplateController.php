<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\TemplateRepository;
use GB\Services\ProtectedFileStorage;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;
use RuntimeException;

final class TemplateController extends Controller
{
    public function __construct(
        View $view,
        private TemplateRepository $templates,
        private ProtectedFileStorage $files,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.templates', [
            'title' => 'Plantillas | Panel', 'templates' => $this->templates->adminList(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form([], [], '', 'Nueva plantilla');
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function edit(Request $request): Response
    {
        $template = $this->templates->findForAdmin((int) $request->routeParam('id'));
        return $template === null
            ? $this->redirect('/admin/plantillas')
            : $this->form($template, [], '', 'Editar plantilla');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, (int) $request->routeParam('id'));
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $template = $this->templates->findForAdmin($id);

        if ($template === null) {
            return $this->redirectWith('/admin/plantillas', 'Esa plantilla ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);
        $this->templates->moveToTrash($id, $days);

        return $this->redirectWith('/admin/plantillas', sprintf(
            'La plantilla «%s» se retiró del sitio. El archivo se conserva %d días por si necesitas recuperarla.',
            (string) $template['title'],
            $days
        ), 'warning');
    }

    private function save(Request $request, ?int $id): Response
    {
        $input = $request->all();
        $validator = new Validator($input, [
            'title' => 'Título', 'slug' => 'Dirección de la plantilla', 'summary' => 'Resumen',
            'description' => 'Descripción', 'category' => 'Categoría', 'preview_media_id' => 'Vista previa',
            'status' => 'Estado', 'sort_order' => 'Orden',
        ]);
        $validator->validate([
            'title' => 'required|max:200', 'slug' => 'required|max:190|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'summary' => 'max:500', 'description' => 'max:50000', 'category' => 'max:80',
            'preview_media_id' => 'integer', 'status' => 'required|in:active,inactive', 'sort_order' => 'integer',
            'file' => $id === null ? 'required' : '',
        ]);

        $file = $request->file('file');

        if ($validator->fails()) {
            return $this->form($input, $validator->errors(), $validator->firstError() ?? '', $id === null ? 'Nueva plantilla' : 'Editar plantilla', 422);
        }

        $data = $validator->validated();
        $data['preview_media_id'] = ($data['preview_media_id'] ?? '') === '' ? null : (int) $data['preview_media_id'];
        $data['requires_registration'] = $request->bool('requires_registration') ? 1 : 0;
        $data['updated_by'] = $this->auth->id();

        if ($file !== null) {
            try {
                $stored = $this->files->store($file);
            } catch (RuntimeException $exception) {
                return $this->form($input, ['file' => [$exception->getMessage()]], $exception->getMessage(), $id === null ? 'Nueva plantilla' : 'Editar plantilla', 422);
            }

            $data['file_path'] = $stored['path'];
            $data['original_file_name'] = $stored['name'];
            $data['file_mime'] = $stored['mime'];
            $data['file_size_bytes'] = $stored['size'];
        }

        if ($id === null) {
            $data['created_by'] = $this->auth->id();
            $this->templates->createAdmin($data);
        } else {
            $this->templates->updateAdmin($id, $data);
        }

        return $this->redirectWith('/admin/plantillas', sprintf(
            $id === null ? 'La plantilla «%s» se creó y ya está disponible.' : 'Los cambios de la plantilla «%s» se guardaron.',
            (string) $data['title']
        ));
    }

    /** @param array<string, mixed> $values @param array<string, array<int, string>> $errors */
    private function form(array $values, array $errors, string $message, string $heading, int $status = 200): Response
    {
        return $this->admin('admin.template-form', [
            'title' => $heading . ' | Panel', 'heading' => $heading,
            'values' => $values, 'errors' => $errors, 'message' => $message,
        ], $status);
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}