<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Páginas de error para el visitante.
 *
 * Todo mensaje está redactado en español y explica qué pasó y qué hacer, sin
 * códigos ni términos técnicos. Las respuestas de error para peticiones que
 * esperan JSON devuelven un objeto con un mensaje equivalente.
 */
final class ErrorResponder
{
    public function __construct(private View $view)
    {
    }

    /**
     * Texto por defecto de cada situación.
     *
     * @return array{status: int, title: string, message: string}
     */
    public static function copyFor(int $status): array
    {
        return match ($status) {
            403 => [
                'status' => 403,
                'title' => 'No tienes permiso para ver esto',
                'message' => 'Esta sección es sólo para administradores de la plataforma. '
                    . 'Si crees que es un error, escríbenos y lo revisamos.',
            ],
            404 => [
                'status' => 404,
                'title' => 'Contenido no disponible',
                'message' => 'La página que buscas no existe o dejó de estar publicada. '
                    . 'Puedes volver al inicio o escribirnos si necesitas ayuda para encontrar algo.',
            ],
            405 => [
                'status' => 405,
                'title' => 'Acción no permitida',
                'message' => 'Esta dirección no acepta esa forma de envío. Vuelve atrás e inténtalo de nuevo '
                    . 'desde la página correspondiente.',
            ],
            419 => [
                'status' => 419,
                'title' => 'La página expiró',
                'message' => 'Por seguridad, los formularios dejan de funcionar después de un rato. '
                    . 'Vuelve atrás, recarga la página y envía la información otra vez.',
            ],
            429 => [
                'status' => 429,
                'title' => 'Demasiados intentos',
                'message' => 'Por seguridad hemos pausado esta acción durante unos minutos.',
            ],
            default => [
                'status' => 500,
                'title' => 'Estamos solucionando un inconveniente',
                'message' => 'No pudimos mostrar esta página en este momento. Ya registramos el problema. '
                    . 'Vuelve a intentarlo en unos minutos.',
            ],
        };
    }

    /**
     * Respuesta HTML amable.
     *
     * @param array<string, mixed> $data datos adicionales para la vista
     * @param string|null $message texto concreto de esta situación, si lo hay
     */
    public function render(int $status, array $data = [], ?string $message = null): Response
    {
        $copy = self::copyFor($status);

        // El texto concreto manda sobre el genérico. Sin esto, avisos útiles como
        // «Espera 12 minutos» se quedaban en el camino y el visitante sólo veía
        // un «durante unos minutos» que no le dice cuándo puede volver.
        if ($message !== null && trim($message) !== '') {
            $copy['message'] = $message;
        }

        return Response::html(
            $this->view->render('errors.page', $data + [
                'siteName' => (string) config('app.name'),
                'company' => (array) config('app.company', []),
                'title' => $copy['title'],
                'metaDescription' => '',
                'error' => $copy,
            ], 'layouts.public'),
            $status
        );
    }

    /**
     * Respuesta JSON con el mismo significado, para peticiones de la interfaz.
     */
    public function json(int $status, ?string $message = null): Response
    {
        $copy = self::copyFor($status);

        return Response::json([
            'error' => true,
            'status' => $status,
            'title' => $copy['title'],
            'message' => $message ?? $copy['message'],
        ], $status);
    }

    /**
     * Elige entre HTML y JSON según lo que espere quien hace la petición.
     *
     * @param array<string, mixed> $data
     */
    public function respond(Request $request, int $status, ?string $message = null, array $data = []): Response
    {
        return $request->wantsJson()
            ? $this->json($status, $message)
            : $this->render($status, $data, $message);
    }
}
