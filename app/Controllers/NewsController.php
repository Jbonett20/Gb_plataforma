<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\NewsRepository;
use GB\Services\PageComposer;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class NewsController extends Controller
{
    public function __construct(View $view, private NewsRepository $news, private PageComposer $composer)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $page = max(1, $request->int('page', 1));
        $listing = $this->news->publishedPage($page, (int) config('app.pagination.per_page', 12));
        return $this->respond('public.news', $this->pageData($this->composer->chrome() + [
            'title' => 'Noticias | ' . (string) config('app.name'), 'news' => $listing['items'],
            'pagination' => $listing, 'bodyClass' => 'page-news',
        ]));
    }

    public function show(Request $request): Response
    {
        $article = $this->news->findPublishedBySlug((string) $request->routeParam('slug'));
        if ($article === null) {
            return $this->redirect('/noticias');
        }

        $title = (string) ($article['meta_title'] ?: $article['title']);
        $description = (string) ($article['meta_description'] ?: $article['summary'] ?: '');
        $image = media_url((int) ($article['cover_media_id'] ?? 0), 1200);
        $head = '<meta property="og:title" content="' . e($title) . '">'
            . '<meta property="og:description" content="' . e($description) . '">'
            . ($image === null ? '' : '<meta property="og:image" content="' . e($image) . '">');

        return $this->respond('public.news-detail', $this->pageData($this->composer->chrome() + [
            'title' => $title . ' | ' . (string) config('app.name'), 'metaDescription' => $description,
            'head' => $head, 'article' => $article, 'bodyClass' => 'page-news-detail',
        ]));
    }
}