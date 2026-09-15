<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

final class NewsRepository extends Model
{
    use Trashable;

    protected string $table = 'news';

    private const PUBLIC_COLUMNS = 'n.id, n.slug, n.title, n.summary, n.body, n.cover_media_id,
        n.category, n.tags, n.author_name, n.published_at, n.meta_title, n.meta_description';

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} */
    public function publishedPage(int $page = 1, int $perPage = 10): array
    {
        $sql = 'SELECT ' . self::PUBLIC_COLUMNS . ' FROM news n
                WHERE n.deleted_at IS NULL
                  AND (n.status = :published OR (n.status = :scheduled AND n.scheduled_at <= NOW()))
                ORDER BY COALESCE(n.published_at, n.scheduled_at) DESC, n.id DESC';

        return $this->paginate($sql, ['published' => 'published', 'scheduled' => 'scheduled'], $perPage, $page);
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->selectOne(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM news n
             WHERE n.slug = :slug AND n.deleted_at IS NULL
               AND (n.status = :published OR (n.status = :scheduled AND n.scheduled_at <= NOW()))',
            ['slug' => $slug, 'published' => 'published', 'scheduled' => 'scheduled']
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function adminList(): array
    {
        return $this->select(
            'SELECT id, slug, title, category, author_name, status, scheduled_at,
                    published_at, views_count, updated_at FROM news
             WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findForAdmin(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, slug, title, summary, body, cover_media_id, category, tags,
                    author_id, author_name, status, scheduled_at, published_at,
                    meta_title, meta_description, is_featured FROM news
             WHERE id = :id AND deleted_at IS NULL', ['id' => $id]
        );
    }

    /** @param array<string, mixed> $data */
    public function createAdmin(array $data): int
    {
        return $this->insert($data + ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** @param array<string, mixed> $data */
    public function updateAdmin(int $id, array $data): void
    {
        $sets = [];
        $bindings = ['id' => $id];
        foreach ($data as $field => $value) {
            $sets[] = $field . ' = :' . $field;
            $bindings[$field] = $value;
        }
        if ($sets !== []) {
            $this->run('UPDATE news SET ' . implode(', ', $sets) . ' WHERE id = :id', $bindings);
        }
    }
}