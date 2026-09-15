<?php

declare(strict_types=1);

namespace GB\Services;

use PDO;
use RuntimeException;

/**
 * Contenido que arrastra cada módulo del sitio (tarea 11.6).
 *
 * Sirve para dos cosas: decir con números qué se perdería al borrar un módulo
 * de forma definitiva, y negarse a borrarlo cuando lo que hay detrás es
 * historial que no se debe destruir (compras, accesos concedidos, descargas).
 *
 * La regla es deliberada: el contenido se puede borrar, el historial no. Un
 * registro de compra o de acceso es la prueba de lo que ocurrió, y borrarlo
 * para "limpiar" dejaría a la empresa sin forma de demostrar nada.
 */
final class ModuleAccessService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Qué se pierde si se borra definitivamente este módulo.
     *
     * @return array{
     *     title: string,
     *     summary: string,
     *     items: array<int, array{label: string, count: int}>,
     *     history: array<int, array{label: string, count: int}>,
     *     blocked: bool,
     *     blockedReason: string
     * }
     */
    public function impact(string $moduleKey): array
    {
        $content = $this->contentCounts($moduleKey);
        $history = $this->historyCounts($moduleKey);

        $total = 0;

        foreach ($content as $item) {
            $total += $item['count'];
        }

        $historyTotal = 0;

        foreach ($history as $item) {
            $historyTotal += $item['count'];
        }

        $summary = $total === 0
            ? 'Ahora mismo este módulo no tiene contenido guardado: solo se borrará la sección del menú.'
            : sprintf(
                'Se borrarán %d registros de este módulo (%s). No hay forma de recuperarlos.',
                $total,
                implode(', ', array_map(
                    static fn (array $item): string => $item['count'] . ' ' . $item['label'],
                    $content
                ))
            );

        if ($historyTotal > 0) {
            $summary .= ' Además hay ' . $historyTotal . ' registros ligados que impiden el borrado.';
        }

        return [
            'title' => $this->contentLabel($moduleKey),
            'summary' => $summary,
            'items' => $content,
            'history' => $history,
            'blocked' => $historyTotal > 0,
            'blockedReason' => $historyTotal > 0
                ? 'No se puede borrar definitivamente: hay ' . $historyTotal . ' registros ligados a este contenido, entre historial de compras o accesos y contenido que depende de él. Borrarlos en cascada destruiría pruebas de lo que ocurrió. Puedes dejar el módulo oculto, que consigue lo mismo sin perder nada.'
                : '',
        ];
    }

    /**
     * Borra el contenido del módulo. Se niega si hay historial protegido.
     */
    public function purge(string $moduleKey): void
    {
        $impact = $this->impact($moduleKey);

        if ($impact['blocked']) {
            throw new RuntimeException($impact['blockedReason']);
        }

        $this->pdo->beginTransaction();

        try {
            // El orden importa: primero lo que depende de otras tablas.
            foreach ($this->deletionOrder($moduleKey) as $statement) {
                $this->pdo->exec($statement);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function contentCounts(string $moduleKey): array
    {
        return array_values(array_filter(array_map(
            fn (array $item): array => [
                'label' => $item['label'],
                'count' => $this->count($item['count_sql']),
            ],
            $this->contentTables($moduleKey)
        ), static fn (array $item): bool => $item['count'] > 0));
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function historyCounts(string $moduleKey): array
    {
        return array_values(array_filter(array_map(
            fn (array $item): array => [
                'label' => $item['label'],
                'count' => $this->count($item['count_sql']),
            ],
            $this->historyTables($moduleKey)
        ), static fn (array $item): bool => $item['count'] > 0));
    }

    /**
     * @return array<int, array{label: string, count_sql: string}>
     */
    private function contentTables(string $moduleKey): array
    {
        return match ($moduleKey) {
            'news' => [
                ['label' => 'noticias', 'count_sql' => 'SELECT COUNT(*) FROM news'],
            ],
            'videos' => [
                ['label' => 'videos', 'count_sql' => 'SELECT COUNT(*) FROM videos'],
            ],
            'templates' => [
                ['label' => 'plantillas', 'count_sql' => 'SELECT COUNT(*) FROM templates'],
            ],
            'courses' => [
                ['label' => 'lecciones', 'count_sql' => 'SELECT COUNT(*) FROM lessons'],
                ['label' => 'módulos de curso', 'count_sql' => 'SELECT COUNT(*) FROM course_modules'],
                ['label' => 'cursos', 'count_sql' => 'SELECT COUNT(*) FROM courses'],
            ],
            default => [],
        };
    }

    /**
     * Historial que nunca se borra: compras, accesos y descargas.
     *
     * @return array<int, array{label: string, count_sql: string}>
     */
    private function historyTables(string $moduleKey): array
    {
        return match ($moduleKey) {
            'courses' => [
                ['label' => 'inscripciones o accesos', 'count_sql' => 'SELECT COUNT(*) FROM enrollments'],
                ['label' => 'compras', 'count_sql' => 'SELECT COUNT(*) FROM payments'],
                ['label' => 'accesos registrados', 'count_sql' => 'SELECT COUNT(*) FROM content_access_log'],
                ['label' => 'progreso de lecciones', 'count_sql' => 'SELECT COUNT(*) FROM lesson_progress'],
                ['label' => 'videos que pertenecen a un curso', 'count_sql' => 'SELECT COUNT(*) FROM videos WHERE course_id IS NOT NULL'],
            ],
            'videos' => [
                ['label' => 'accesos registrados', 'count_sql' => 'SELECT COUNT(*) FROM content_access_log WHERE video_id IS NOT NULL'],
                ['label' => 'reproducciones', 'count_sql' => 'SELECT COUNT(*) FROM video_views'],
            ],
            'templates' => [
                ['label' => 'descargas', 'count_sql' => 'SELECT COUNT(*) FROM template_downloads'],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function deletionOrder(string $moduleKey): array
    {
        return match ($moduleKey) {
            'news' => ['DELETE FROM news'],
            'videos' => ['DELETE FROM videos'],
            'templates' => ['DELETE FROM templates'],
            'courses' => [
                'DELETE FROM lessons',
                'DELETE FROM course_modules',
                'DELETE FROM courses',
            ],
            default => [],
        };
    }

    private function contentLabel(string $moduleKey): string
    {
        return match ($moduleKey) {
            'news' => 'Noticias',
            'videos' => 'Videos',
            'templates' => 'Plantillas',
            'courses' => 'Cursos',
            default => $moduleKey,
        };
    }

    private function count(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }
}
