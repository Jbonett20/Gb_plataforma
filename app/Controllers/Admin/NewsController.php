<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\NewsRepository;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;

final class NewsController extends Controller
{
    public function __construct(View $view, private NewsRepository $news, private Auth $auth)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.news', ['title' => 'Noticias | Panel', 'news' => $this->news->adminList()]);
    }

    public function create(Request $request): Response
    {
        return $this->form([], [], '', 'Nueva noticia');
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function edit(Request $request): Response
    {
        $article = $this->news->findForAdmin((int) $request->routeParam('id'));
        return $article === null ? $this->redirect('/admin/noticias') : $this->form($article, [], '', 'Editar noticia');
    }

    public function update(Request $request): Response
    {
        return $this->save($request, (int) $request->routeParam('id'));
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $article = $this->news->findForAdmin($id);

        if ($article === null) {
            return $this->redirectWith('/admin/noticias', 'Esa noticia ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);
        $this->news->moveToTrash($id, $days);

        return $this->redirectWith('/admin/noticias', sprintf(
            'La noticia «%s» se retiró del blog. No se ha borrado: puedes restaurarla durante %d días.',
            (string) $article['title'],
            $days
        ), 'warning');
    }

    private function save(Request $request, ?int $id): Response
    {
        $input = $request->all();
        $validator = new Validator($input, [
            'title' => 'Título', 'slug' => 'Enlace permanente', 'summary' => 'Resumen', 'body' => 'Contenido',
            'category' => 'Categoría', 'tags' => 'Etiquetas', 'cover_media_id' => 'Imagen destacada',
            'author_name' => 'Autor', 'status' => 'Estado', 'scheduled_at' => 'Fecha programada',
            'meta_title' => 'Título SEO', 'meta_description' => 'Descripción SEO',
        ]);
        $validator->validate([
            'title' => 'required|max:200', 'slug' => 'required|max:190|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'summary' => 'max:500', 'body' => 'required|max:100000', 'category' => 'max:80', 'tags' => 'max:255',
            'cover_media_id' => 'integer', 'author_name' => 'max:150',
            'status' => 'required|in:draft,published,scheduled,hidden', 'scheduled_at' => 'date',
            'meta_title' => 'max:190', 'meta_description' => 'max:320',
        ]);

        if ($validator->fails()) {
            return $this->form($input, $validator->errors(), $validator->firstError() ?? '', $id === null ? 'Nueva noticia' : 'Editar noticia', 422);
        }

        $data = $validator->validated();
        $data['cover_media_id'] = ($data['cover_media_id'] ?? '') === '' ? null : (int) $data['cover_media_id'];
        $data['scheduled_at'] = ($data['scheduled_at'] ?? '') === '' ? null : $data['scheduled_at'];
        $data['published_at'] = $data['status'] === 'published' ? date('Y-m-d H:i:s') : null;
        $data['author_id'] = $this->auth->id();
        $data['created_by'] = $id === null ? $this->auth->id() : ($data['created_by'] ?? null);
        $data['updated_by'] = $this->auth->id();
        if ($id === null) {
            $this->news->createAdmin($data);
        } else {
            unset($data['created_by']);
            $this->news->updateAdmin($id, $data);
        }

        return $this->redirectWith('/admin/noticias', sprintf(
            $id === null ? 'La noticia «%s» se creó.' : 'Los cambios de la noticia «%s» se guardaron.',
            (string) $data['title']
        ) . ' ' . match ((string) $data['status']) {
            'published' => 'Ya se ve en el blog público.',
            'scheduled' => 'Queda programada: se publicará en la fecha indicada.',
            'hidden' => 'Queda oculta: no se lista en el blog.',
            default => 'Sigue como borrador: todavía no se ve en el blog.',
        });
    }

    /** @param array<string, mixed> $values @param array<string, array<int, string>> $errors */
    private function form(array $values, array $errors, string $message, string $heading, int $status = 200): Response
    {
        return $this->admin('admin.news-form', ['title' => $heading . ' | Panel', 'heading' => $heading, 'values' => $values, 'errors' => $errors, 'message' => $message], $status);
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}