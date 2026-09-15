<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

/**
 * Módulos de contenido: Cursos, Plantillas, Videos y Noticias.
 *
 * Un módulo agrupa un tipo de contenido y permite mostrarlo u ocultarlo entero
 * de una sola vez. La navegación pública y las secciones de la portada se
 * generan a partir de su estado, de modo que no queden enlaces rotos ni huecos
 * en la maquetación.
 */
final class ModuleRepository extends Model
{
    use Trashable;

    protected string $table = 'modules';

    private const COLUMNS = 'id, key_name, label, menu_label, description, status, sort_order,
        show_in_menu, show_on_home, deleted_at, purge_after, created_at, updated_at';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(bool $includeTrashed = false): array
    {
        $condition = $includeTrashed ? '1 = 1' : 'deleted_at IS NULL';

        return $this->select(
            'SELECT ' . self::COLUMNS . ' FROM modules WHERE ' . $condition . ' ORDER BY sort_order, id'
        );
    }

    /**
     * Módulos que el sitio público debe mostrar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visible(): array
    {
        return $this->select(
            'SELECT ' . self::COLUMNS . " FROM modules
             WHERE deleted_at IS NULL AND status = 'active' ORDER BY sort_order, id"
        );
    }

    /**
     * Enlaces para el menú del sitio público.
     *
     * @return array<int, array{key: string, label: string, href: string}>
     */
    public function menuItems(): array
    {
        $items = [];

        foreach ($this->visible() as $module) {
            if ((int) $module['show_in_menu'] !== 1) {
                continue;
            }

            $items[] = [
                'key' => (string) $module['key_name'],
                'label' => (string) ($module['menu_label'] ?: $module['label']),
                'href' => url($module['key_name']),
            ];
        }

        return $items;
    }

    /**
     * Claves de los módulos que deben aparecer en la portada.
     *
     * @return array<int, string>
     */
    public function homeSectionKeys(): array
    {
        $keys = [];

        foreach ($this->visible() as $module) {
            if ((int) $module['show_on_home'] === 1) {
                $keys[] = (string) $module['key_name'];
            }
        }

        return $keys;
    }

    /**
     * Claves de todos los módulos visibles, para decidir si una dirección
     * pública tiene sentido.
     *
     * @return array<int, string>
     */
    public function visibleKeys(): array
    {
        return array_map(
            static fn (array $module): string => (string) $module['key_name'],
            $this->visible()
        );
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->selectOne('SELECT ' . self::COLUMNS . ' FROM modules WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByKey(string $key): ?array
    {
        return $this->selectOne('SELECT ' . self::COLUMNS . ' FROM modules WHERE key_name = :key', ['key' => $key]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($data);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateModule(int $id, array $values): int
    {
        $values['updated_at'] = date('Y-m-d H:i:s');

        return $this->update($id, $values);
    }

    public function setStatus(int $id, string $status): int
    {
        return $this->run(
            'UPDATE modules SET status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'status' => $status]
        );
    }

    public function isPubliclyVisible(string $key): bool
    {
        return $this->exists(
            'SELECT 1 FROM modules WHERE key_name = :key AND deleted_at IS NULL AND status IN (\'active\', \'hidden\')',
            ['key' => $key]
        );
    }
}
