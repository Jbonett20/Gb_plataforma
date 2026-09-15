<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\MediaRepository;
use GB\Services\ImagePipeline;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\UploadException;
use GB\Support\View;

/**
 * Carga y reemplazo de imágenes desde el panel.
 *
 * Todas las rutas que llegan aquí exigen sesión de SuperAdmin y token de
 * seguridad. La imagen nunca se guarda sin pasar por `ImagePipeline`, que es
 * quien la recorta a la proporción del destino.
 */
final class MediaController extends Controller
{
    public function __construct(
        View $view,
        private ImagePipeline $pipeline,
        private MediaRepository $media,
    ) {
        parent::__construct($view);
    }

    public function upload(Request $request): Response
    {
        $file = $request->file('file');

        if ($file === null) {
            return $this->respondJson([
                'error' => true,
                'message' => 'No se seleccionó ningún archivo.',
            ], 422);
        }

        try {
            $id = $this->pipeline->store(
                $file,
                $request->string('profile'),
                auth()->id(),
                $request->string('alt'),
                (float) $request->input('focal_x', 0.5),
                (float) $request->input('focal_y', 0.5),
                $request->bool('allow_small')
            );
        } catch (UploadException $exception) {
            return $this->respondJson([
                'error' => true,
                'message' => $exception->getMessage(),
            ] + $exception->context(), 422);
        }

        return $this->describe($id, 'La imagen se guardó y se ajustó al tamaño de su lugar en la página.');
    }

    /**
     * Reemplaza una imagen conservando el destino y el encuadre.
     */
    public function replace(Request $request): Response
    {
        $file = $request->file('file');
        $mediaId = (int) $request->routeParam('id', '0');

        if ($file === null) {
            return $this->respondJson([
                'error' => true,
                'message' => 'No se seleccionó ningún archivo.',
            ], 422);
        }

        try {
            $newId = $this->pipeline->replaceWith($mediaId, $file, auth()->id(), $request->bool('allow_small'));
        } catch (UploadException $exception) {
            return $this->respondJson([
                'error' => true,
                'message' => $exception->getMessage(),
            ] + $exception->context(), 422);
        }

        return $this->describe($newId, 'La imagen se reemplazó y mantiene el mismo encuadre.');
    }

    public function destroy(Request $request): Response
    {
        $mediaId = (int) $request->routeParam('id', '0');

        if (!$this->pipeline->delete($mediaId)) {
            return $this->respondJson([
                'error' => true,
                'message' => 'La imagen ya no existe.',
            ], 404);
        }

        return $this->respondJson([
            'error' => false,
            'message' => 'La imagen se eliminó. Los lugares donde se mostraba quedarán sin imagen.',
        ]);
    }

    private function describe(int $mediaId, string $message): Response
    {
        $media = $this->media->findById($mediaId);

        return $this->respondJson([
            'error' => false,
            'message' => $message,
            'media' => [
                'id' => $mediaId,
                'url' => media_url($mediaId),
                'width' => (int) ($media['width'] ?? 0),
                'height' => (int) ($media['height'] ?? 0),
                'alt' => (string) ($media['alt_text'] ?? ''),
            ],
        ]);
    }
}
