<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\VideoRepository;
use GB\Services\PageComposer;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class VideoController extends Controller
{
    public function __construct(View $view, private VideoRepository $videos, private PageComposer $composer)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->respond('public.videos', $this->pageData($this->composer->chrome() + [
            'title' => 'Videos | ' . (string) config('app.name'),
            'videos' => $this->videos->published(), 'bodyClass' => 'page-videos',
        ]));
    }

    public function show(Request $request): Response
    {
        $video = $this->videos->findPublishedBySlug((string) $request->routeParam('slug'));
        if ($video === null) {
            return $this->redirect('/videos');
        }

        $embed = $this->embedUrl((string) $video['source'], (string) ($video['external_id'] ?? ''));
        if ($embed === null) {
            return Response::html('Este video no está disponible.', 404);
        }

        return $this->respond('public.video', $this->pageData($this->composer->chrome() + [
            'title' => (string) $video['title'] . ' | ' . (string) config('app.name'),
            'video' => $video, 'embedUrl' => $embed, 'bodyClass' => 'page-video',
        ]));
    }

    public function recordView(Request $request): Response
    {
        $video = $this->videos->findPublishedBySlug((string) $request->routeParam('slug'));
        if ($video === null) {
            return $this->respondJson(['ok' => false, 'message' => 'El video ya no está disponible.'], 404);
        }

        $user = auth()->user();
        $this->videos->recordView((int) $video['id'], $user === null ? null : (int) $user['id'], $request->ip());

        return $this->respondJson(['ok' => true, 'message' => 'Reproducción registrada.']);
    }

    private function embedUrl(string $source, string $externalId): ?string
    {
        if ($externalId === '') {
            return null;
        }

        return match ($source) {
            'youtube' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($externalId),
            'vimeo' => 'https://player.vimeo.com/video/' . rawurlencode($externalId),
            default => null,
        };
    }
}