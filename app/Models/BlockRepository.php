<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

/**
 * Bloques de contenido del sitio público.
 *
 * Regla importante, y poco evidente a primera vista:
 *
 *   - El ESTADO (visible / oculto) se lee en vivo de la tabla `blocks`: cuando
 *     el SuperAdmin oculta una sección, desaparece del sitio al instante.
 *   - El CONTENIDO (textos, imágenes, orden) se lee de la última versión
 *     PUBLICADA guardada en `block_versions`. Así, guardar un borrador no
 *     cambia nada de lo que ve el visitante.
 *
 * Por eso `publishedBlocks()` une `blocks` con su versión publicada en lugar de
 * leer la tabla directamente.
 */
final class BlockRepository extends Model
{
    use Trashable;

    protected string $table = 'blocks';

    private const COLUMNS = 'b.id, b.module_id, b.key_name, b.label, b.help_text, b.eyebrow, b.heading,
        b.intro, b.media_id, b.cta_label, b.cta_url, b.type, b.template, b.sort_order, b.status,
        b.settings, b.published_version_id, b.published_at, b.published_by, b.deleted_at,
        b.purge_after, b.created_at, b.updated_at';

    /**
     * Bloques para el panel: incluye los que aún no se han publicado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(bool $includeTrashed = false): array
    {
        $condition = $includeTrashed ? '1 = 1' : 'b.deleted_at IS NULL';

        $rows = $this->select(
            'SELECT ' . self::COLUMNS . ', bv.version_number AS published_version_number
             FROM blocks b
             LEFT JOIN block_versions bv ON bv.id = b.published_version_id
             WHERE ' . $condition . '
             ORDER BY b.sort_order, b.id'
        );

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    /**
     * Bloques que el sitio público debe mostrar, con su contenido publicado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function publishedBlocks(): array
    {
        $rows = $this->select(
            'SELECT ' . self::COLUMNS . ', bv.snapshot, bv.version_number AS published_version_number
             FROM blocks b
             INNER JOIN block_versions bv ON bv.id = b.published_version_id
             WHERE b.deleted_at IS NULL AND b.status IN (\'active\', \'hidden\')
             ORDER BY b.sort_order, b.id'
        );

        return array_map(fn (array $row): array => $this->hydrateWithSnapshot($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $row = $this->selectOne(
            'SELECT ' . self::COLUMNS . ', bv.version_number AS published_version_number
             FROM blocks b
             LEFT JOIN block_versions bv ON bv.id = b.published_version_id
             WHERE b.id = :id',
            ['id' => $id]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<string, mixed>|null */
    public function findByKey(string $key): ?array
    {
        $row = $this->selectOne(
            'SELECT ' . self::COLUMNS . ', bv.version_number AS published_version_number
             FROM blocks b
             LEFT JOIN block_versions bv ON bv.id = b.published_version_id
             WHERE b.key_name = :key',
            ['key' => $key]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data['settings'] = $this->encodeJson($data['settings'] ?? null);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($data);
    }

    /**
     * Guarda el contenido editable de un bloque (borrador: no publica).
     *
     * @param array<string, mixed> $values
     */
    public function updateContent(int $id, array $values): int
    {
        if (array_key_exists('settings', $values)) {
            $values['settings'] = $this->encodeJson($values['settings']);
        }

        $values['updated_at'] = date('Y-m-d H:i:s');

        return $this->update($id, $values);
    }

    public function setStatus(int $id, string $status): int
    {
        return $this->run(
            'UPDATE blocks SET status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'status' => $status]
        );
    }

    /**
     * Marca una versión como la publicada.
     */
    public function markPublished(int $id, int $versionId, ?int $userId): int
    {
        return $this->run(
            'UPDATE blocks
             SET published_version_id = :version, published_at = NOW(), published_by = :user, updated_at = NOW()
             WHERE id = :id',
            ['id' => $id, 'version' => $versionId, 'user' => $userId]
        );
    }

    public function nextVersionNumber(int $blockId): int
    {
        return 1 + (int) $this->scalar(
            'SELECT COALESCE(MAX(version_number), 0) FROM block_versions WHERE block_id = :id',
            ['id' => $blockId]
        );
    }

    /**
     * Última versión publicada anterior a la actual, para la acción "Deshacer".
     *
     * @return array<string, mixed>|null
     */
    public function previousVersion(int $blockId, int $currentVersionNumber): ?array
    {
        return $this->selectOne(
            'SELECT id, version_number, snapshot, published_at, published_by
             FROM block_versions
             WHERE block_id = :id AND version_number < :version
             ORDER BY version_number DESC LIMIT 1',
            ['id' => $blockId, 'version' => $currentVersionNumber]
        );
    }

    /** @return array<string, mixed>|null */
    public function versionById(int $versionId): ?array
    {
        return $this->selectOne(
            'SELECT id, block_id, version_number, snapshot, published_at, published_by
             FROM block_versions WHERE id = :id',
            ['id' => $versionId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function versions(int $blockId, int $limit = 20): array
    {
        return $this->select(
            'SELECT id, version_number, note, published_by, published_at
             FROM block_versions WHERE block_id = :id
             ORDER BY version_number DESC LIMIT ' . max(1, $limit),
            ['id' => $blockId]
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function addVersion(int $blockId, int $versionNumber, array $snapshot, ?string $note, ?int $userId): int
    {
        // Consulta explícita: el historial vive en `block_versions`, no en la
        // tabla principal de este repositorio.
        $this->run(
            'INSERT INTO block_versions (block_id, version_number, snapshot, note, published_by, published_at)
             VALUES (:block, :version, :snapshot, :note, :user, :published_at)',
            [
                'block' => $blockId,
                'version' => $versionNumber,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'note' => $note,
                'user' => $userId,
                'published_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * Cuántos bloques no tienen ninguna versión publicada.
     */
    public function countUnpublished(): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM blocks WHERE published_version_id IS NULL AND deleted_at IS NULL'
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['settings'] = $this->decodeJson($row['settings'] ?? null);
        $row['is_published'] = $row['published_version_id'] !== null;

        return $row;
    }

    /**
     * Combina el bloque con el contenido de su versión publicada.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateWithSnapshot(array $row): array
    {
        $decoded = is_string($row['snapshot'] ?? null) ? json_decode((string) $row['snapshot'], true) : null;
        $snapshot = is_array($decoded) ? $decoded : [];

        $content = is_array($snapshot['block'] ?? null) ? $snapshot['block'] : [];
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];

        unset($row['snapshot']);

        $row = array_merge($row, $content);
        $row['settings'] = is_array($content['settings'] ?? null) ? $content['settings'] : [];
        $row['items'] = array_map(
            fn (array $item): array => is_array($item) ? $item : [],
            $items
        );
        $row['is_published'] = true;

        return $row;
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
