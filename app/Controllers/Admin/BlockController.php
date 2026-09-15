<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\BlockItemRepository;
use GB\Models\BlockRepository;
use GB\Services\BlockPublisher;
use GB\Services\BlockRenderer;
use GB\Support\AdminBlockForm;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;
use RuntimeException;
use Throwable;

/**
 * Secciones del sitio público.
 *
 * Guardar no publica: el borrador queda guardado y el visitante sigue viendo lo
 * último publicado hasta que alguien pulse "Publicar". La vista previa muestra
 * el borrador sin publicarlo.
 */
final class BlockController extends Controller
{
    public function __construct(
        View $view,
        private BlockRepository $blocks,
        private BlockItemRepository $items,
        private BlockPublisher $publisher,
        private BlockRenderer $renderer,
        private AdminBlockForm $form,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $registry = (array) config('admin_labels.blocks', []);
        $rows = [];

        foreach ($this->blocks->all() as $block) {
            $key = (string) $block['key_name'];

            $rows[] = $block + [
                'friendly_label' => (string) ($registry[$key]['label'] ?? $block['label']),
                'friendly_help' => (string) ($registry[$key]['help'] ?? ''),
                'is_published' => $block['published_version_id'] !== null,
                'item_count' => $this->items->countForBlock((int) $block['id']),
                'is_known' => isset($registry[$key]),
            ];
        }

        return $this->admin('admin.blocks', [
            'title' => 'Secciones del sitio | Panel',
            'blocks' => $rows,
            'pending' => $this->blocks->countUnpublished(),
        ]);
    }

    public function edit(Request $request): Response
    {
        $block = $this->blocks->findById((int) $request->routeParam('id'));

        if ($block === null) {
            return $this->redirect('/admin/bloques');
        }

        return $this->formView($block, [], null, 200, null);
    }

    public function save(Request $request): Response
    {
        $block = $this->blocks->findById((int) $request->routeParam('id'));

        if ($block === null) {
            return $this->redirect('/admin/bloques');
        }

        $definition = $this->definitionFor($block);
        $input = $request->all();
        $errors = $this->form->validate($input, $definition);

        if ($errors !== []) {
            return $this->formView($block, $errors, 'Revisa lo que falta antes de guardar.', 422, $input);
        }

        try {
            $this->publisher->saveDraft(
                (int) $block['id'],
                $this->form->blockValues($input, $definition),
                $this->form->itemPayloads($input, $definition)
            );
        } catch (RuntimeException $exception) {
            return $this->formView($block, [], $exception->getMessage(), 422, $input);
        }

        // El panel muestra el borrador guardado; el sitio público sigue igual.
        return $this->redirectWith(
            '/admin/bloques/' . (int) $block['id'],
            'Borrador guardado. Los visitantes siguen viendo la versión publicada hasta que pulses «Publicar».',
            'info'
        );
    }

    public function publish(Request $request): Response
    {
        $block = $this->blocks->findById((int) $request->routeParam('id'));

        if ($block === null) {
            return $this->redirect('/admin/bloques');
        }

        try {
            $version = $this->publisher->publish((int) $block['id'], $this->auth->id());
        } catch (Throwable) {
            return $this->redirectWith(
                '/admin/bloques',
                'No se pudo publicar la sección. El sitio sigue mostrando la versión anterior.',
                'danger'
            );
        }

        return $this->redirectWith(
            '/admin/bloques/' . (int) $block['id'],
            'Sección publicada (versión ' . $version . '). Los visitantes ya ven estos cambios.'
        );
    }

    public function undo(Request $request): Response
    {
        $block = $this->blocks->findById((int) $request->routeParam('id'));

        if ($block === null) {
            return $this->redirect('/admin/bloques');
        }

        $done = $this->publisher->undo((int) $block['id'], $this->auth->id());

        return $done
            ? $this->redirectWith(
                '/admin/bloques/' . (int) $block['id'],
                'Se volvió a la versión publicada anterior. Los visitantes ven esa versión.',
                'warning'
            )
            : $this->redirectWith(
                '/admin/bloques/' . (int) $block['id'],
                'No había nada que deshacer: no hay cambios publicados que revertir.',
                'info'
            );
    }

    /**
     * Vista previa del borrador, sin publicarlo.
     *
     * Se renderiza aquí mismo, fuera del compositor de páginas públicas, así que
     * el borrador no pasa por la caché de vistas y no puede servirse a un
     * visitante.
     */
    public function preview(Request $request): Response
    {
        $block = $this->blocks->findById((int) $request->routeParam('id'));

        if ($block === null) {
            return $this->redirect('/admin/bloques');
        }

        $definition = $this->definitionFor($block);

        $block['items'] = $this->items->forBlock((int) $block['id'], true);
        $block['settings'] = is_array($block['settings'] ?? null) ? $block['settings'] : [];

        $html = $this->renderer->render($block);

        return $this->respond('admin.block-preview', $this->pageData([
            'title' => 'Vista previa | Panel',
            'blockLabel' => (string) ($definition['label'] ?? $block['label']),
            'previewHtml' => $html,
            'isPublished' => $block['published_version_id'] !== null,
        ]), 'layouts.admin');
    }

    /** @param array<string, array<int, string>> $errors @param array<string, mixed>|null $input */
    private function formView(array $block, array $errors, ?string $message, int $status = 200, ?array $input = null): Response
    {
        $definition = $this->definitionFor($block);
        $current = $input === null ? $block : array_merge($block, [
            'eyebrow' => $input['eyebrow'] ?? $block['eyebrow'],
            'heading' => $input['heading'] ?? $block['heading'],
            'intro' => $input['intro'] ?? $block['intro'],
            'media_id' => $input['media_id'] ?? $block['media_id'],
            'cta_label' => $input['cta_label'] ?? $block['cta_label'],
            'cta_url' => $input['cta_url'] ?? $block['cta_url'],
        ]);

        $items = $input !== null && isset($input['items']) && is_array($input['items'])
            ? array_values(array_filter($input['items'], static fn ($row): bool => is_array($row)))
            : $this->form->items($this->items->forBlock((int) $block['id'], true), $definition);

        return $this->admin('admin.block-form', [
            'title' => (string) ($definition['label'] ?? $block['label']) . ' | Panel',
            'block' => $block,
            'definition' => $definition,
            'fields' => $this->form->blockFields($definition, $current),
            'settingsFields' => $this->form->settingsFields($definition, (array) ($current['settings'] ?? [])),
            'itemFields' => $this->form->itemFields($definition),
            'items' => $items,
            'errors' => $errors,
            'message' => $message,
        ], $status);
    }

    /** @return array<string, mixed> */
    private function definitionFor(array $block): array
    {
        $registry = (array) config('admin_labels.blocks', []);
        $key = (string) $block['key_name'];

        if (!isset($registry[$key])) {
            // Un bloque sin declaración se sigue mostrando, pero sin formulario
            // inventado: es mejor no ofrecer campos que no se sabe interpretar.
            throw new RuntimeException('Esa sección no está declarada en las etiquetas del panel.');
        }

        return (array) $registry[$key];
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}
