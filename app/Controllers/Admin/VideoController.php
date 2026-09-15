<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\VideoRepository;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;

final class VideoController extends Controller
{
    public function __construct(View $view, private VideoRepository $videos, private Auth $auth)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.videos', ['title' => 'Videos | Panel', 'videos' => $this->videos->adminList()]);
    }

    public function create(Request $request): Response
    {
        return $this->form([], [], '', 'Nuevo video');
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function edit(Request $request): Response
    {
        $video = $this->videos->findForAdmin((int) $request->routeParam('id'));
        return $video === null ? $this->redirect('/admin/videos') : $this->form($video, [], '', 'Editar video');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, (int) $request->routeParam('id'));
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $video = $this->videos->findForAdmin($id);

        if ($video === null) {
            return $this->redirectWith('/admin/videos', 'Ese video ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);
        $this->videos->moveToTrash($id, $days);

        return $this->redirectWith('/admin/videos', sprintf(
            'El video «%s» se retiró del sitio. No se ha borrado: puedes restaurarlo durante %d días.',
            (string) $video['title'],
            $days
        ), 'warning');
    }

    private function save(Request $request, ?int $id): Response
    {
        $input = $request->all();
        $validator = new Validator($input, [
            'title' => 'Título', 'slug' => 'Dirección del video', 'summary' => 'Resumen',
            'description' => 'Descripción', 'category' => 'Categoría', 'source' => 'Origen',
            'external_id' => 'Identificador del video', 'duration_seconds' => 'Duración',
            'access_type' => 'Tipo de acceso', 'cover_media_id' => 'Miniatura', 'status' => 'Estado',
        ]);
        $validator->validate([
            'title' => 'required|max:200', 'slug' => 'required|max:190|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'summary' => 'max:500', 'description' => 'max:50000', 'category' => 'max:80',
            'source' => 'required|in:youtube,vimeo', 'external_id' => 'required|max:120',
            'duration_seconds' => 'integer', 'access_type' => 'required|in:free,paid',
            'cover_media_id' => 'integer', 'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->form($input, $validator->errors(), $validator->firstError() ?? '', $id === null ? 'Nuevo video' : 'Editar video', 422);
        }

        $data = $validator->validated();
        $data['cover_media_id'] = ($data['cover_media_id'] ?? '') === '' ? null : (int) $data['cover_media_id'];
        $data['duration_seconds'] = ($data['duration_seconds'] ?? '') === '' ? null : (int) $data['duration_seconds'];
        $data['updated_by'] = $this->auth->id();
        if ($id === null) {
            $data['created_by'] = $this->auth->id();
            $this->videos->createAdmin($data);
        } else {
            $this->videos->updateAdmin($id, $data);
        }

        return $this->redirectWith('/admin/videos', sprintf(
            $id === null ? 'El video «%s» se creó.' : 'Los cambios del video «%s» se guardaron.',
            (string) $data['title']
        ) . ((string) $data['status'] === 'active'
            ? ' Ya aparece en la sección pública de videos.'
            : ' Queda oculto: no se lista en el sitio público.'));
    }

    /** @param array<string, mixed> $values @param array<string, array<int, string>> $errors */
    private function form(array $values, array $errors, string $message, string $heading, int $status = 200): Response
    {
        return $this->admin('admin.video-form', ['title' => $heading . ' | Panel', 'heading' => $heading, 'values' => $values, 'errors' => $errors, 'message' => $message], $status);
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}