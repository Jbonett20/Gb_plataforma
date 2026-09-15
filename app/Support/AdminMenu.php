<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Menú del panel de administración.
 *
 * Agrupa las pantallas por lo que la persona quiere hacer y no por el nombre de
 * la tabla que hay detrás. Los grupos se declaran en `config/admin_labels.php`,
 * que es el único archivo donde se decide cómo se llaman las cosas.
 *
 * Sólo se enlazan pantallas que existen: si una sección todavía no está
 * construida, no aparece, para no dejar enlaces que lleven a un error.
 */
final class AdminMenu
{
    /**
     * @param array<string, string> $groups clave => etiqueta visible
     * @param array<int, array{group: string, label: string, path: string, help?: string, icon?: string}> $sections
     */
    public function __construct(
        private array $groups,
        private array $sections,
    ) {
    }

    /**
     * Grupos con sus pantallas ya resueltas. Los grupos vacíos se omiten.
     *
     * @return array<int, array{key: string, label: string, items: array<int, array{label: string, path: string, icon: string}>}>
     */
    public function groups(): array
    {
        $byGroup = [];

        foreach ($this->sections as $section) {
            $byGroup[$section['group']][] = $section;
        }

        $result = [];

        foreach ($this->groups as $key => $label) {
            if (!isset($byGroup[$key])) {
                continue;
            }

            $items = [];

            foreach ($byGroup[$key] as $section) {
                $items[] = [
                    'label' => $section['label'],
                    'path' => $section['path'],
                    'icon' => $section['icon'] ?? 'bi-circle',
                ];
            }

            $result[] = ['key' => $key, 'label' => $label, 'items' => $items];
        }

        return $result;
    }

    /**
     * Ayuda contextual de una pantalla: qué controla y qué se ve afectado en el
     * sitio público.
     */
    public function helpFor(string $path): ?string
    {
        foreach ($this->sections as $section) {
            if ($section['path'] === $path) {
                return $section['help'] ?? null;
            }

            // Las pantallas de detalle (editar, temario) heredan la ayuda de su
            // listado, para no repetir el mismo texto en cada dirección.
            if (str_starts_with($path, $section['path'] . '/')) {
                return $section['help'] ?? null;
            }
        }

        return null;
    }

    /**
     * Marca si una entrada corresponde a la dirección actual.
     */
    public function isActive(string $sectionPath, string $currentPath): bool
    {
        return $currentPath === $sectionPath || str_starts_with($currentPath, $sectionPath . '/');
    }

    /**
     * Recorrido guiado del primer ingreso (tarea 11.5).
     *
     * Sólo salen los pasos cuya pantalla existe de verdad: prometer una pantalla
     * que todavía no está construida sería peor que no mostrar el recorrido.
     *
     * @return array{title: string, intro: string, steps: array<int, array{title: string, text: string, url: string, icon: string}>}
     */
    public function tour(array $definition): array
    {
        // El Resumen no está en el menú lateral (se enlaza aparte), así que hay
        // que admitirlo como destino válido además de las pantallas declaradas.
        $available = array_merge(['admin'], array_column($this->sections, 'path'));
        $steps = [];

        foreach ($definition['steps'] ?? [] as $step) {
            $path = trim((string) ($step['path'] ?? ''), '/');

            if ($path === '' || !in_array($path, $available, true)) {
                continue;
            }

            $steps[] = [
                'title' => (string) ($step['title'] ?? ''),
                'text' => (string) ($step['text'] ?? ''),
                'url' => url($path),
                'icon' => (string) ($step['icon'] ?? 'bi-circle'),
            ];
        }

        return [
            'title' => (string) ($definition['title'] ?? ''),
            'intro' => (string) ($definition['intro'] ?? ''),
            'steps' => $steps,
        ];
    }
}
