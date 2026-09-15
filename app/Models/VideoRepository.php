<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

final class VideoRepository extends Model
{
    use Trashable;

    protected string $table = 'videos';

    /** @return array<int, array<string, mixed>> */
    public function adminList(): array
    {
        return $this->select(
            'SELECT id, slug, title, category, source, external_id, duration_seconds,
                    access_type, view_count, status, sort_order, updated_at
             FROM videos WHERE deleted_at IS NULL ORDER BY sort_order, title'
        );
    }

    /** @return array<string, mixed>|null */
    public function findForAdmin(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, slug, title, summary, description, category, cover_media_id,
                    source, external_id, duration_seconds, access_type, course_id,
                    status, sort_order
             FROM videos WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function published(): array
    {
        return $this->select(
            'SELECT id, slug, title, summary, description, category, cover_media_id,
                    source, external_id, duration_seconds, access_type, view_count
             FROM videos WHERE deleted_at IS NULL AND status = :status
             ORDER BY sort_order, title',
            ['status' => 'active']
        );
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->selectOne(
            'SELECT id, slug, title, summary, description, category, cover_media_id,
                    source, external_id, duration_seconds, access_type, view_count
             FROM videos WHERE slug = :slug AND deleted_at IS NULL AND status = :status',
            ['slug' => $slug, 'status' => 'active']
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
            $this->run('UPDATE videos SET ' . implode(', ', $sets) . ' WHERE id = :id', $bindings);
        }
    }

    public function recordView(int $videoId, ?int $userId, string $ip): void
    {
        $this->run(
            'INSERT INTO video_views (video_id, user_id, ip, seconds_watched, created_at)
             VALUES (:video, :user, :ip, 0, NOW())',
            ['video' => $videoId, 'user' => $userId, 'ip' => mb_substr($ip, 0, 45)]
        );
        $this->run('UPDATE videos SET view_count = view_count + 1 WHERE id = :id', ['id' => $videoId]);
    }
}