<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

final class SubscriberRepository extends Model
{
    protected string $table = 'subscribers';

    public function emailExists(string $email): bool
    {
        return $this->exists(
            'SELECT 1 FROM subscribers WHERE email = :email LIMIT 1',
            ['email' => mb_strtolower(trim($email))]
        );
    }

    /** Suscriptores que siguen activos. */
    public function countActive(): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM subscribers WHERE status = 'active'"
        );
    }

    /** @param array<string, mixed> $data */
    public function createFromWeb(array $data): int
    {
        return $this->insert($data + [
            'status' => 'active',
            'source' => 'web',
            'confirmed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} */
    public function adminPage(int $page = 1, int $perPage = 25): array
    {
        return $this->paginate(
            'SELECT id, name, last_name, phone, email, status, source, confirmed_at, created_at
             FROM subscribers ORDER BY created_at DESC, id DESC',
            [], $perPage, $page
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allForExport(): array
    {
        return $this->select(
            'SELECT name, last_name, phone, email, status, source, confirmed_at, created_at
             FROM subscribers ORDER BY created_at DESC, id DESC'
        );
    }
}