<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\BlockRepository;
use GB\Models\ModuleRepository;

/**
 * Construye el menú del sitio público.
 *
 * Los enlaces se generan a partir de lo que está visible: si una sección se
 * oculta o un módulo se desactiva, su enlace desaparece del menú al instante y
 * no queda ningún enlace roto.
 */
final class NavigationBuilder
{
    public function __construct(
        private BlockRepository $blocks,
        private ModuleRepository $modules,
        private BlockRenderer $renderer,
    ) {
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    public function publicMenu(): array
    {
        $menu = $this->renderer->menuFrom($this->blocks->publishedBlocks());

        foreach ($this->modules->menuItems() as $module) {
            $menu[] = ['label' => $module['label'], 'href' => $module['href']];
        }

        return $menu;
    }
}
