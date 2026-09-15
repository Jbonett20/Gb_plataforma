<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\ContentAccessRepository;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class ContentAccessController extends Controller
{
    public function __construct(View $view, private ContentAccessRepository $accessLog)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->respond('admin.content-access', $this->pageData([
            'title' => 'Accesos a contenido | Panel', 'accesses' => $this->accessLog->recent(),
        ]), 'layouts.admin');
    }
}