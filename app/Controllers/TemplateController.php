<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\TemplateRepository;
use GB\Services\PageComposer;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;
use RuntimeException;

final class TemplateController extends Controller
{
    public function __construct(View $view, private TemplateRepository $templates, private PageComposer $composer, private Auth $auth)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->respond('public.templates', $this->pageData($this->composer->chrome() + [
            'title' => 'Plantillas | ' . (string) config('app.name'),
            'templates' => $this->templates->published(), 'bodyClass' => 'page-templates',
        ]));
    }

    public function show(Request $request): Response
    {
        $template = $this->templates->findPublishedBySlug((string) $request->routeParam('slug'));
        if ($template === null) {
            return $this->redirect('/plantillas');
        }

        return $this->respond('public.template', $this->pageData($this->composer->chrome() + [
            'title' => (string) $template['title'] . ' | ' . (string) config('app.name'),
            'template' => $template, 'bodyClass' => 'page-template',
        ]));
    }

    public function download(Request $request): Response
    {
        $template = $this->templates->findPublishedBySlug((string) $request->routeParam('slug'));
        if ($template === null) {
            return Response::html('La plantilla no está disponible.', 404);
        }

        $user = $this->auth->user();
        $policy = (string) config('app.template_download_policy', 'libre');
        if ((int) $template['requires_registration'] === 1 || ($policy === 'obligatorio' && $user === null)) {
            if ($user === null) {
                $this->auth->setIntendedUrl($request->fullPath());
                return $this->redirect('/ingresar');
            }
        }

        $root = realpath(GB_STORAGE_PATH . '/protected');
        $file = realpath(GB_STORAGE_PATH . '/protected/' . ltrim((string) $template['file_path'], '/\\'));
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return Response::html('El archivo no está disponible.', 404);
        }

        $this->templates->recordDownload(
            (int) $template['id'], $user === null ? null : (int) $user['id'],
            (string) ($user['name'] ?? ''), (string) ($user['email'] ?? ''), $request->ip(), $request->userAgent()
        );

        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException('No se pudo leer el archivo protegido.');
        }

        return new Response($content, 200, [
            'Content-Type' => (string) ($template['file_mime'] ?: 'application/octet-stream'),
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="' . addcslashes((string) $template['original_file_name'], '"\\') . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}