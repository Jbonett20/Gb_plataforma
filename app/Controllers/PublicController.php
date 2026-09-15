<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Services\PageComposer;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Páginas del sitio público.
 *
 * Todo el contenido proviene de los bloques administrables: no hay ni un texto
 * escrito en el código. Los bloques se leen de la última versión publicada, de
 * modo que guardar un borrador no cambia lo que ve el visitante.
 */
final class PublicController extends Controller
{
    public function __construct(View $view, private PageComposer $composer)
    {
        parent::__construct($view);
    }

    public function home(Request $request): Response
    {
        $page = $this->composer->home();

        return $this->respond('public.home', $this->pageData([
            'title' => 'González & Ballesteros - Consultores y Asociados | Derecho Laboral, Civil y Seguridad Social',
            'metaDescription' => 'Somos una firma de abogados especializada en derecho laboral, civil y comercial. '
                . 'Más de 20 años de experiencia legal estratégica a su servicio.',
            'sectionsHtml' => $page['sections'],
            'footerHtml' => $page['footer'],
            'hasContent' => $page['hasContent'],
            'navLinks' => $page['nav'],
        ]));
    }
}
