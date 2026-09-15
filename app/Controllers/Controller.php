<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Support\Flash;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Base de todos los controladores.
 *
 * Aporta el acceso a las vistas y un método `respond()` coherente: los
 * controladores siempre devuelven un objeto Response, nunca imprimen nada.
 */
abstract class Controller
{
    public function __construct(protected View $view)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function respond(string $view, array $data = [], string $layout = 'layouts.public', int $status = 200): Response
    {
        return Response::html($this->view->render($view, $data, $layout), $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function respondJson(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }

    /**
     * Redirige dejando un aviso para la pantalla siguiente (tarea 11.6).
     *
     * Toda operación que cambia algo avisa de lo que acaba de pasar: sin esa
     * confirmación, quien administra no sabe si el cambio llegó a guardarse.
     */
    protected function redirectWith(string $path, string $message, string $type = 'success'): Response
    {
        app(Flash::class)->put($type, $message);

        return $this->redirect($path);
    }

    /**
     * Datos comunes a todas las vistas públicas: nombre del sitio, contacto y
     * datos para buscadores. Se ampliará con los bloques del CMS en la tarea 4.4.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function pageData(array $data = []): array
    {
        return $data + [
            'siteName' => (string) config('app.name'),
            'company' => (array) config('app.company', []),
            'title' => null,
            'metaDescription' => null,
        ];
    }
}
