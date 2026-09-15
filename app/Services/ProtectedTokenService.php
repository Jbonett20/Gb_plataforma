<?php

declare(strict_types=1);

namespace GB\Services;

use RuntimeException;

/** Tokens cortos para solicitar contenido protegido sin revelar su ruta real. */
final class ProtectedTokenService
{
    public function issue(int $userId, string $resourceType, int $resourceId, ?string $ip = null, ?int $ttl = null): string
    {
        $key = $this->key();
        $payload = [
            'user_id' => $userId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'expires_at' => time() + ($ttl ?? (int) config('app.protected_link_ttl', 600)),
            'ip_hash' => $ip === null ? null : hash_hmac('sha256', $ip, $key),
            'nonce' => bin2hex(random_bytes(8)),
        ];
        $encoded = $this->encode($payload);

        return $encoded . '.' . $this->signature($encoded, $key);
    }

    /** @return array<string, mixed>|null */
    public function validate(string $token, int $userId, string $resourceType, int $resourceId, ?string $ip = null): ?array
    {
        $key = $this->key();
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;
        if (!hash_equals($this->signature($encoded, $key), $signature)) {
            return null;
        }

        $payload = json_decode($this->decode($encoded), true);
        if (!is_array($payload) || (int) ($payload['user_id'] ?? 0) !== $userId
            || (string) ($payload['resource_type'] ?? '') !== $resourceType
            || (int) ($payload['resource_id'] ?? 0) !== $resourceId
            || (int) ($payload['expires_at'] ?? 0) < time()) {
            return null;
        }

        if ($ip !== null && !hash_equals(
            (string) ($payload['ip_hash'] ?? ''),
            hash_hmac('sha256', $ip, $key)
        )) {
            return null;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo crear el token protegido.');
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decode(string $encoded): string
    {
        $padding = strlen($encoded) % 4;
        $decoded = base64_decode(strtr($encoded . ($padding ? str_repeat('=', 4 - $padding) : ''), '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private function signature(string $encoded, string $key): string
    {
        return hash_hmac('sha256', $encoded, $key);
    }

    private function key(): string
    {
        $key = (string) config('app.key', '');
        if ($key === '') {
            throw new RuntimeException('APP_KEY no está configurada.');
        }

        return $key;
    }
}