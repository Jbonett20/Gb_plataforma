<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Models\Concerns\Trashable;
use GB\Support\Model;

final class TemplateRepository extends Model
{
    use Trashable;

    protected string $table = 'templates';

    /** @return array<int, array<string, mixed>> */
    public function adminList(): array
    {
        return $this->select(
            'SELECT id, slug, title, category, original_file_name, file_mime,
                    file_size_bytes, requires_registration, download_count, status, updated_at
             FROM templates WHERE deleted_at IS NULL ORDER BY sort_order, title'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function published(): array
    {
        return $this->select(
            'SELECT id, slug, title, summary, description, category, preview_media_id,
                    original_file_name, file_mime, file_size_bytes, requires_registration,
                    download_count
             FROM templates
             WHERE deleted_at IS NULL AND status = :status
             ORDER BY sort_order, title',
            ['status' => 'active']
        );
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->selectOne(
            'SELECT id, slug, title, summary, description, category, preview_media_id,
                    file_path, original_file_name, file_mime, file_size_bytes,
                    requires_registration, download_count
             FROM templates
             WHERE slug = :slug AND deleted_at IS NULL AND status = :status',
            ['slug' => $slug, 'status' => 'active']
        );
    }

    public function recordDownload(int $templateId, ?int $userId, string $name, string $email, string $ip, string $userAgent): void
    {
        $this->run(
            'INSERT INTO template_downloads (template_id, user_id, name, email, ip, user_agent, created_at)
             VALUES (:template, :user, :name, :email, :ip, :agent, NOW())',
            [
                'template' => $templateId, 'user' => $userId, 'name' => $name === '' ? null : mb_substr($name, 0, 150),
                'email' => $email === '' ? null : mb_substr($email, 0, 190), 'ip' => mb_substr($ip, 0, 45),
                'agent' => mb_substr($userAgent, 0, 255),
            ]
        );
        $this->run('UPDATE templates SET download_count = download_count + 1 WHERE id = :id', ['id' => $templateId]);
    }

    /** @return array<string, mixed>|null */
    public function findForAdmin(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, slug, title, summary, description, category, preview_media_id,
                    file_path, original_file_name, file_mime, file_size_bytes,
                    requires_registration, status, sort_order
             FROM templates WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
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
            $this->run('UPDATE templates SET ' . implode(', ', $sets) . ' WHERE id = :id', $bindings);
        }
    }
}