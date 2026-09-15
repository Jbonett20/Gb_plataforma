<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Elementos repetibles de un bloque: diapositivas, servicios, integrantes del
 * equipo, clientes, casos, preguntas frecuentes y enlaces del pie de página.
 */
final class BlockItemRepository extends Model
{
    protected string $table = 'block_items';

    private const COLUMNS = 'id, block_id, media_id, title, subtitle, body, link_url, link_label,
        icon, sort_order, status, data, deleted_at, created_at, updated_at';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forBlock(int $blockId, bool $includeHidden = false): array
    {
        $condition = $includeHidden
            ? 'deleted_at IS NULL'
            : "deleted_at IS NULL AND status IN ('active', 'hidden')";

        $rows = $this->select(
            'SELECT ' . self::COLUMNS . ' FROM block_items
             WHERE block_id = :block AND ' . $condition . '
             ORDER BY sort_order, id',
            ['block' => $blockId]
        );

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    /**
     * @param array<int, int> $blockIds
     * @return array<int, array<int, array<string, mixed>>> agrupados por bloque
     */
    public function groupedForBlocks(array $blockIds): array
    {
        $grouped = [];

        foreach (array_unique(array_map('intval', $blockIds)) as $blockId) {
            $grouped[$blockId] = $this->forBlock($blockId);
        }

        return $grouped;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $row = $this->selectOne('SELECT ' . self::COLUMNS . ' FROM block_items WHERE id = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Reemplaza por completo los elementos de un bloque respetando el orden
     * recibido. Es lo que usa el panel al guardar un bloque completo.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function replaceAll(int $blockId, array $items): void
    {
        $existing = [];

        foreach ($this->select('SELECT id FROM block_items WHERE block_id = :block', ['block' => $blockId]) as $row) {
            $existing[] = (int) $row['id'];
        }

        $keptIds = [];

        foreach (array_values($items) as $position => $item) {
            $values = $this->normalize($item);
            $values['sort_order'] = $position * 10;

            $id = isset($item['id']) && (int) $item['id'] > 0 ? (int) $item['id'] : null;

            if ($id !== null && in_array($id, $existing, true)) {
                $this->update($id, $values);
                $keptIds[] = $id;

                continue;
            }

            $values['block_id'] = $blockId;
            $values['created_at'] = date('Y-m-d H:i:s');
            $values['updated_at'] = date('Y-m-d H:i:s');

            $keptIds[] = $this->insert($values);
        }

        $removed = array_diff($existing, $keptIds);

        foreach ($removed as $id) {
            $this->deleteById($id);
        }
    }

    public function setStatus(int $id, string $status): int
    {
        return $this->run(
            'UPDATE block_items SET status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'status' => $status]
        );
    }

    /**
     * Copia de los elementos de un bloque, para guardarla en una versión.
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshotForBlock(int $blockId): array
    {
        $items = [];

        foreach ($this->forBlock($blockId, true) as $item) {
            $items[] = [
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'body' => $item['body'],
                'link_url' => $item['link_url'],
                'link_label' => $item['link_label'],
                'icon' => $item['icon'],
                'media_id' => $item['media_id'] === null ? null : (int) $item['media_id'],
                'status' => $item['status'],
                'data' => $item['data'],
            ];
        }

        return $items;
    }

    public function countForBlock(int $blockId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM block_items WHERE block_id = :block AND deleted_at IS NULL',
            ['block' => $blockId]
        );
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalize(array $item): array
    {
        $data = $item['data'] ?? null;

        return [
            'media_id' => isset($item['media_id']) && (int) $item['media_id'] > 0 ? (int) $item['media_id'] : null,
            'title' => $this->stringOrNull($item['title'] ?? null),
            'subtitle' => $this->stringOrNull($item['subtitle'] ?? null),
            'body' => $this->stringOrNull($item['body'] ?? null),
            'link_url' => $this->stringOrNull($item['link_url'] ?? null),
            'link_label' => $this->stringOrNull($item['link_label'] ?? null),
            'icon' => $this->stringOrNull($item['icon'] ?? null),
            'status' => in_array($item['status'] ?? null, ['active', 'inactive', 'hidden', 'deleted'], true)
                ? (string) $item['status']
                : 'active',
            'data' => $data === null || $data === [] ? null
                : (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $data = $row['data'] ?? null;

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $row['data'] = is_array($data) ? $data : [];

        return $row;
    }
}
