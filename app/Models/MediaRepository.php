<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Recursos visuales: imágenes, banners, miniaturas y documentos.
 *
 * `public_path` indica la ruta que ve el navegador. Para las imágenes heredadas
 * del sitio actual apunta a `assets/img/...`; para las que se suben desde el
 * panel, el pipeline de imágenes deja ahí el derivado recortado al perfil.
 */
final class MediaRepository extends Model
{
    protected string $table = 'media';

    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        return $this->cache[$id] = $this->selectOne(
            'SELECT id, disk, path, public_path, file_name, mime_type, extension, size_bytes,
                    width, height, profile, focal_x, focal_y, alt_text, title, variants,
                    is_protected, is_legacy, uploaded_by, created_at
             FROM media WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        $result = [];

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $media = $this->findById($id);

            if ($media !== null) {
                $result[$id] = $media;
            }
        }

        return $result;
    }

    /**
     * Busca si ese mismo archivo ya está guardado para el mismo destino y con
     * el mismo encuadre. En ese caso no hace falta volver a procesarlo.
     *
     * @return array<string, mixed>|null
     */
    public function findDuplicate(string $checksum, string $profile, float $focalX, float $focalY): ?array
    {
        return $this->selectOne(
            'SELECT id, disk, path, public_path, width, height, profile, focal_x, focal_y, alt_text, variants
             FROM media
             WHERE checksum = :checksum AND profile = :profile
               AND focal_x = :focal_x AND focal_y = :focal_y
             LIMIT 1',
            [
                'checksum' => $checksum,
                'profile' => $profile,
                'focal_x' => round($focalX, 4),
                'focal_y' => round($focalY, 4),
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function register(array $data): int
    {
        if (isset($data['variants']) && is_array($data['variants'])) {
            $data['variants'] = json_encode($data['variants'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($data);
    }

    /**
     * Marca el recurso como heredado del sitio anterior. Sirve para distinguir
     * las imágenes originales de las que se suban desde el panel.
     */
    public function markAsLegacy(int $id): void
    {
        unset($this->cache[$id]);

        $this->run('UPDATE media SET is_legacy = 1 WHERE id = :id', ['id' => $id]);
    }

    /**
     * Rutas de todos los archivos que la base de datos reconoce como propios.
     * Lo usa la limpieza de archivos huérfanos.
     *
     * @return array<int, array{public_path: string|null, path: string|null, variants: string|null}>
     */
    public function allReferences(): array
    {
        return $this->select('SELECT public_path, path, variants FROM media', []);
    }

    /**
     * Borra el registro del recurso. Los archivos los elimina el pipeline.
     */
    public function remove(int $id): int
    {
        // Sin esto, una consulta posterior devolvería la fila ya borrada.
        unset($this->cache[$id]);

        return $this->deleteById($id);
    }

    /**
     * Dirección pública del recurso, eligiendo la variante más adecuada.
     *
     * @param int|null $targetWidth ancho aproximado que se mostrará en pantalla
     */
    public function publicUrl(?int $id, ?int $targetWidth = null): ?string
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $media = $this->findById($id);

        if ($media === null) {
            return null;
        }

        $variant = $this->bestVariant($this->variants($media), $targetWidth);

        if ($variant !== null) {
            return url($variant);
        }

        $publicPath = (string) ($media['public_path'] ?? '');

        return $publicPath === '' ? null : url($publicPath);
    }

    public function altText(?int $id, string $fallback = ''): string
    {
        if ($id === null || $id <= 0) {
            return $fallback;
        }

        $media = $this->findById($id);
        $alt = $media === null ? '' : trim((string) ($media['alt_text'] ?? ''));

        return $alt === '' ? $fallback : $alt;
    }

    /**
     * @return array<int, array{width: int, path: string, format?: string}>
     */
    public function variants(array $media): array
    {
        $raw = $media['variants'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($raw)) {
            return [];
        }

        $variants = [];

        foreach ($raw as $variant) {
            if (is_array($variant) && isset($variant['path'])) {
                $variants[] = [
                    'width' => (int) ($variant['width'] ?? 0),
                    'path' => (string) $variant['path'],
                    'format' => (string) ($variant['format'] ?? ''),
                ];
            }
        }

        return $variants;
    }

    /**
     * Elige la variante más liviana que se vea bien en el ancho pedido. A igual
     * medida, prefiere el formato moderno.
     *
     * @param array<int, array{width: int, path: string, format?: string}> $variants
     */
    private function bestVariant(array $variants, ?int $targetWidth): ?string
    {
        if ($variants === []) {
            return null;
        }

        $candidates = $variants;

        if ($targetWidth !== null && $targetWidth > 0) {
            $suitable = array_values(array_filter(
                $variants,
                static fn (array $variant): bool => $variant['width'] >= $targetWidth
            ));

            // Ninguna llega al ancho pedido: se usa la mayor disponible.
            $candidates = $suitable !== [] ? $suitable : [$variants[array_key_last($variants)]];
        }

        $best = null;

        foreach ($candidates as $variant) {
            if ($best === null
                || $variant['width'] < $best['width']
                || ($variant['width'] === $best['width']
                    && self::formatRank((string) ($variant['format'] ?? '')) < self::formatRank((string) ($best['format'] ?? '')))) {
                $best = $variant;
            }
        }

        return is_array($best) ? $best['path'] : null;
    }

    private static function formatRank(string $format): int
    {
        return match ($format) {
            'webp' => 0,
            'jpeg', 'jpg' => 1,
            'png' => 2,
            default => 3,
        };
    }
}
