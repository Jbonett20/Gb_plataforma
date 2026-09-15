<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Support\View;
use RuntimeException;

/**
 * Convierte los bloques guardados en HTML.
 *
 * Cada tipo de bloque tiene su propia plantilla en `app/Views/blocks`. El
 * SuperAdmin nunca escribe HTML: sólo rellena campos, y esta clase decide qué
 * maquetación corresponde. Así el diseño no se puede romper desde el panel.
 */
final class BlockRenderer
{
    /**
     * @param array<string, array<string, mixed>> $registry config('admin_labels.blocks')
     */
    public function __construct(
        private View $view,
        private array $registry = [],
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    public function renderAll(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $html .= $this->render($block);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $block
     */
    public function render(array $block): string
    {
        return $this->view->render($this->templateFor($block), [
            'block' => $block,
            'items' => is_array($block['items'] ?? null) ? $block['items'] : [],
            'settings' => is_array($block['settings'] ?? null) ? $block['settings'] : [],
            'anchor' => $this->anchorFor($block),
        ]);
    }

    /**
     * Plantilla que dibuja el bloque.
     */
    public function templateFor(array $block): string
    {
        $type = (string) ($block['type'] ?? '');
        $override = (string) ($block['template'] ?? '');

        $candidates = [];

        if ($override !== '') {
            $candidates[] = 'blocks.' . $override;
        }

        if ($type !== '') {
            $candidates[] = 'blocks.' . $type;
        }

        $candidates[] = 'blocks.unknown';

        foreach ($candidates as $candidate) {
            if ($this->view->exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf(
            'No hay ninguna plantilla para el bloque "%s" (tipo "%s").',
            (string) ($block['key_name'] ?? '?'),
            $type
        ));
    }

    /**
     * Ancla del bloque dentro de la página, usada por el menú.
     */
    public function anchorFor(array $block): string
    {
        $key = (string) ($block['key_name'] ?? '');
        $configured = $this->registry[$key]['anchor'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $key === '' ? '' : '#' . $key;
    }

    /**
     * Bloques que deben aparecer en el menú del sitio.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array{label: string, href: string}>
     */
    public function menuFrom(array $blocks): array
    {
        $items = [];

        foreach ($blocks as $block) {
            $key = (string) ($block['key_name'] ?? '');
            $definition = $this->registry[$key] ?? [];

            if (($definition['in_menu'] ?? false) !== true) {
                continue;
            }

            $items[] = [
                'label' => (string) ($definition['menu_label'] ?? $block['label'] ?? $key),
                'href' => $this->anchorFor($block),
            ];
        }

        return $items;
    }
}
