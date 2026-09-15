<?php

declare(strict_types=1);

namespace GB\Services;

/** Registro ligero de sesiones activas por usuario para hosting compartido. */
final class SessionRegistry
{
    public function __construct(private string $directory, private int $maximum = 2)
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }
    }

    public function register(int $userId, string $sessionId, int $now): void
    {
        if ($sessionId === 'cli-session') {
            return;
        }
        $sessions = $this->read($userId);
        $sessions[$sessionId] = $now;
        arsort($sessions, SORT_NUMERIC);
        $this->write($userId, array_slice($sessions, 0, max(1, $this->maximum), true));
    }

    public function isActive(int $userId, string $sessionId): bool
    {
        return $sessionId === 'cli-session' || array_key_exists($sessionId, $this->read($userId));
    }

    public function forget(int $userId, string $sessionId): void
    {
        if ($sessionId === 'cli-session') {
            return;
        }
        $sessions = $this->read($userId);
        unset($sessions[$sessionId]);
        $this->write($userId, $sessions);
    }

    /** @return array<string, int> */
    private function read(int $userId): array
    {
        $raw = @file_get_contents($this->path($userId));
        $decoded = $raw === false ? null : json_decode($raw, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    /** @param array<string, int> $sessions */
    private function write(int $userId, array $sessions): void
    {
        @file_put_contents($this->path($userId), json_encode($sessions, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function path(int $userId): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'user-' . $userId . '.json';
    }
}