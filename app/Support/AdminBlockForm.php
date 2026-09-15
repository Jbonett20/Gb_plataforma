<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Traduce las declaraciones de `config/admin_labels.php` a formularios.
 *
 * Es la frontera entre "cómo se llama el campo para quien administra" y "cómo se
 * llama la columna en la base de datos". El panel nunca escribe nombres de
 * columnas: escribe lo que declara este archivo, y aquí se mapea.
 *
 * Convención de nombres: el campo declarado `media` corresponde a la columna
 * `media_id`; el resto de campos simples se llaman igual que su columna. Lo que
 * no tiene columna propia se guarda en el JSON `data` del elemento.
 */
final class AdminBlockForm
{
    /** Columnas de `blocks` que se pueden editar desde el panel. */
    private const BLOCK_COLUMNS = ['eyebrow', 'heading', 'intro', 'media_id', 'cta_label', 'cta_url'];

    /** Columnas de `block_items` que se pueden editar desde el panel. */
    private const ITEM_COLUMNS = ['title', 'subtitle', 'body', 'link_url', 'link_label', 'icon', 'media_id'];

    /**
     * Campos propios del bloque (los que no son elementos repetibles).
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $block
     * @return array<int, array<string, mixed>>
     */
    public function blockFields(array $definition, array $block): array
    {
        $fields = [];

        foreach ($definition as $key => $declaration) {
            if (!is_array($declaration) || !isset($declaration['type'])) {
                continue;
            }

            $column = $key === 'media' ? 'media_id' : $key;

            if (!in_array($column, self::BLOCK_COLUMNS, true)) {
                continue;
            }

            $fields[] = $this->field($column, $declaration, $block[$column] ?? null);
        }

        return $fields;
    }

    /**
     * Campos que viven en el JSON `settings` del bloque.
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    public function settingsFields(array $definition, array $settings): array
    {
        $fields = [];
        $declared = $definition['settings'] ?? null;

        if (!is_array($declared)) {
            return $fields;
        }

        foreach ($declared as $key => $declaration) {
            if (!is_array($declaration)) {
                continue;
            }

            $value = $settings[$key] ?? ($declaration['default'] ?? null);

            $fields[] = $this->field((string) $key, $declaration, $value, 'settings');
        }

        return $fields;
    }

    /**
     * Campos de un elemento repetible.
     *
     * @param array<string, mixed> $definition
     * @return array<int, array<string, mixed>>
     */
    public function itemFields(array $definition): array
    {
        $fields = [];
        $declared = $definition['item_fields'] ?? null;

        if (!is_array($declared)) {
            return $fields;
        }

        foreach ($declared as $key => $declaration) {
            if (!is_array($declaration)) {
                continue;
            }

            $column = $key === 'media' ? 'media_id' : $key;
            $fields[] = $this->field($column, $declaration, null);
        }

        return $fields;
    }

    /**
     * Elementos actuales del bloque, listos para pintar.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $definition
     * @return array<int, array<string, mixed>>
     */
    public function items(array $items, array $definition): array
    {
        $result = [];

        foreach ($items as $item) {
            $values = ['id' => (int) ($item['id'] ?? 0)];

            foreach ($this->itemFields($definition) as $field) {
                $values[$field['name']] = $item[$field['name']] ?? null;
            }

            // Los campos sin columna propia viven en `data`.
            foreach ((array) ($definition['item_fields'] ?? []) as $key => $declaration) {
                $column = $key === 'media' ? 'media_id' : $key;

                if (in_array($column, self::ITEM_COLUMNS, true)) {
                    continue;
                }

                $values[$column] = $item['data'][$key] ?? null;
            }

            $result[] = ['id' => (int) ($item['id'] ?? 0), 'values' => $values];
        }

        return $result;
    }

    /**
     * Valores del bloque a partir de lo enviado, ya filtrados a lo permitido.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function blockValues(array $input, array $definition): array
    {
        $values = [];

        foreach ($this->blockFields($definition, []) as $field) {
            $name = (string) $field['name'];
            $raw = $input[$name] ?? null;

            $values[$name] = $field['type'] === 'media'
                ? (is_numeric($raw) && (int) $raw > 0 ? (int) $raw : null)
                : $this->textOrNull($raw);
        }

        $settings = [];
        foreach ($this->settingsFields($definition, []) as $field) {
            $name = (string) $field['name'];
            $raw = $input['settings'][$name] ?? null;
            $settings[$name] = $field['type'] === 'number' ? (int) $raw : $this->textOrNull($raw);
        }

        if ($settings !== []) {
            $values['settings'] = $settings;
        }

        return $values;
    }

    /**
     * Elementos enviados, en orden y sin los marcados para quitar.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $definition
     * @return array<int, array<string, mixed>>
     */
    public function itemPayloads(array $input, array $definition): array
    {
        $rows = $input['items'] ?? null;

        if (!is_array($rows)) {
            return [];
        }

        $payloads = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['_remove'])) {
                continue;
            }

            $payload = [];
            $data = [];

            foreach ((array) ($definition['item_fields'] ?? []) as $key => $declaration) {
                $column = $key === 'media' ? 'media_id' : $key;
                $raw = $row[$column] ?? null;

                if (in_array($column, self::ITEM_COLUMNS, true)) {
                    $payload[$column] = $column === 'media_id'
                        ? (is_numeric($raw) && (int) $raw > 0 ? (int) $raw : null)
                        : $this->textOrNull($raw);
                } else {
                    $data[$key] = $this->textOrNull($raw);
                }
            }

            if ($payload === array_filter($payload, static fn ($value): bool => $value !== null)) {
                // Todos los campos útiles están vacíos: no se guarda un elemento
                // en blanco, que sólo ensuciaría la sección.
                continue;
            }

            if (isset($row['id']) && is_numeric($row['id']) && (int) $row['id'] > 0) {
                $payload['id'] = (int) $row['id'];
            }

            $payload['data'] = array_filter($data, static fn ($value): bool => $value !== null);

            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * Errores en lenguaje claro, con el nombre que ve la persona.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $definition
     * @return array<string, array<int, string>>
     */
    public function validate(array $input, array $definition): array
    {
        $errors = [];
        $singular = (string) ($definition['item_singular'] ?? 'Elemento');

        foreach ($this->blockFields($definition, []) as $field) {
            if (empty($field['required'])) {
                continue;
            }

            if ($this->textOrNull($input[$field['name']] ?? null) === null) {
                $errors['block'][$field['name']] = 'Completa "' . $field['label'] . '".';
            }
        }

        $rows = $input['items'] ?? [];

        if (is_array($rows)) {
            foreach ($rows as $index => $row) {
                if (!is_array($row) || !empty($row['_remove'])) {
                    continue;
                }

                foreach ($this->itemFields($definition) as $field) {
                    if (empty($field['required'])) {
                        continue;
                    }

                    if ($this->textOrNull($row[$field['name']] ?? null) === null) {
                        $errors['items'][(string) $index] = $singular . ' ' . ((int) $index + 1)
                            . ': completa "' . $field['label'] . '".';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $declaration
     * @return array<string, mixed>
     */
    private function field(string $name, array $declaration, mixed $value, string $group = 'block'): array
    {
        return [
            'name' => $name,
            'group' => $group,
            'label' => (string) ($declaration['label'] ?? $name),
            'type' => (string) ($declaration['type'] ?? 'text'),
            'help' => (string) ($declaration['help'] ?? ''),
            'profile' => (string) ($declaration['profile'] ?? ''),
            'required' => !empty($declaration['required']),
            'value' => $value,
        ];
    }

    private function textOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
