<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\BlockItemRepository;
use GB\Models\BlockRepository;
use GB\Support\Audit;
use RuntimeException;
use Throwable;

/**
 * Ciclo de vida del contenido de un bloque.
 *
 * Guardar NO publica. El panel guarda el borrador y el visitante sigue viendo
 * la última versión publicada hasta que alguien pulsa "Publicar". Cada
 * publicación deja una copia completa en el historial, lo que hace posible la
 * acción "Deshacer" y volver a cualquier versión anterior.
 */
final class BlockPublisher
{
    public function __construct(
        private BlockRepository $blocks,
        private BlockItemRepository $items,
        private Audit $audit,
        private ViewCache $cache,
    ) {
    }

    /**
     * Guarda el borrador de un bloque: textos y lista de elementos.
     *
     * @param array<string, mixed> $blockValues
     * @param array<int, array<string, mixed>> $itemPayloads
     */
    public function saveDraft(int $blockId, array $blockValues, array $itemPayloads): void
    {
        if ($this->blocks->findById($blockId) === null) {
            throw new RuntimeException(sprintf('No existe el bloque %d.', $blockId));
        }

        $allowed = ['eyebrow', 'heading', 'intro', 'media_id', 'cta_label', 'cta_url', 'settings', 'label'];
        $values = array_intersect_key($blockValues, array_flip($allowed));

        $this->blocks->updateContent($blockId, $values);

        if ($itemPayloads !== []) {
            $this->items->replaceAll($blockId, $itemPayloads);
        }
    }

    /**
     * Publica el contenido actual del bloque. Devuelve el número de versión.
     */
    public function publish(int $blockId, ?int $userId = null, ?string $note = null): int
    {
        $block = $this->blocks->findById($blockId);

        if ($block === null) {
            throw new RuntimeException(sprintf('No existe el bloque %d.', $blockId));
        }

        $snapshot = $this->snapshotOf($blockId);
        $versionNumber = $this->blocks->nextVersionNumber($blockId);
        $versionId = $this->blocks->addVersion($blockId, $versionNumber, $snapshot, $note, $userId);

        $this->blocks->markPublished($blockId, $versionId, $userId);
        $this->cache->flush();

        $this->record(
            'publish',
            $blockId,
            sprintf('Publicó "Sección de %s" (versión %d)', $block['label'], $versionNumber),
            ['version' => $versionNumber]
        );

        return $versionNumber;
    }

    /**
     * Devuelve el bloque al contenido de la versión publicada anteriormente.
     */
    public function undo(int $blockId, ?int $userId = null): bool
    {
        $block = $this->blocks->findById($blockId);

        if ($block === null || $block['published_version_id'] === null) {
            return false;
        }

        $currentNumber = (int) ($block['published_version_number'] ?? 0);

        if ($currentNumber <= 1) {
            return false;
        }

        $previous = $this->blocks->previousVersion($blockId, $currentNumber);

        if ($previous === null) {
            return false;
        }

        $this->applySnapshot($blockId, $this->decodeSnapshot($previous['snapshot']));
        $this->publish($blockId, $userId, sprintf('Deshacer: se volvió a la versión %d', $previous['version_number']));

        return true;
    }

    /**
     * Restaura una versión concreta del historial.
     */
    public function restoreVersion(int $blockId, int $versionId, ?int $userId = null): bool
    {
        $version = $this->blocks->versionById($versionId);

        if ($version === null || (int) $version['block_id'] !== $blockId) {
            return false;
        }

        $this->applySnapshot($blockId, $this->decodeSnapshot($version['snapshot']));
        $this->publish($blockId, $userId, sprintf('Restauración de la versión %d', $version['version_number']));

        return true;
    }

    /**
     * ¿Hay cambios guardados que todavía no se han publicado?
     */
    public function hasUnpublishedChanges(int $blockId): bool
    {
        $block = $this->blocks->findById($blockId);

        if ($block === null) {
            return false;
        }

        if ($block['published_version_id'] === null) {
            return true;
        }

        $version = $this->blocks->versionById((int) $block['published_version_id']);

        if ($version === null) {
            return true;
        }

        return $this->fingerprint($this->snapshotOf($blockId))
            !== $this->fingerprint($this->decodeSnapshot($version['snapshot']));
    }

    /**
     * Copia del contenido publicable de un bloque.
     *
     * @return array<string, mixed>
     */
    public function snapshotOf(int $blockId): array
    {
        $block = $this->blocks->findById($blockId);

        if ($block === null) {
            throw new RuntimeException(sprintf('No existe el bloque %d.', $blockId));
        }

        return [
            'block' => [
                'eyebrow' => $block['eyebrow'],
                'heading' => $block['heading'],
                'intro' => $block['intro'],
                'media_id' => $block['media_id'] === null ? null : (int) $block['media_id'],
                'cta_label' => $block['cta_label'],
                'cta_url' => $block['cta_url'],
                'settings' => $block['settings'],
            ],
            'items' => $this->items->snapshotForBlock($blockId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function versions(int $blockId, int $limit = 20): array
    {
        return $this->blocks->versions($blockId, $limit);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function applySnapshot(int $blockId, array $snapshot): void
    {
        $content = is_array($snapshot['block'] ?? null) ? $snapshot['block'] : [];
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];

        $this->blocks->updateContent($blockId, [
            'eyebrow' => $content['eyebrow'] ?? null,
            'heading' => $content['heading'] ?? null,
            'intro' => $content['intro'] ?? null,
            'media_id' => $content['media_id'] ?? null,
            'cta_label' => $content['cta_label'] ?? null,
            'cta_url' => $content['cta_url'] ?? null,
            'settings' => $content['settings'] ?? null,
        ]);

        $this->items->replaceAll($blockId, $items);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSnapshot(mixed $snapshot): array
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (!is_string($snapshot) || $snapshot === '') {
            return [];
        }

        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Huella del contenido, para saber si el borrador difiere de lo publicado.
     */
    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function record(string $action, int $blockId, string $summary, array $changes = []): void
    {
        try {
            $this->audit->log(
                action: $action,
                entityType: 'block',
                entityId: $blockId,
                summary: $summary,
                changes: $changes
            );
        } catch (Throwable) {
            // El registro es informativo: no debe impedir publicar.
        }
    }
}
