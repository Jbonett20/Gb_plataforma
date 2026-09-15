<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Enlaces de restablecimiento de contraseña.
 *
 * Se guarda el HASH del token, nunca el token en claro: si alguien leyera la
 * tabla no podría usarlo para entrar. El token viaja una sola vez, dentro del
 * enlace que recibe la persona por correo.
 */
final class PasswordResetRepository extends Model
{
    protected string $table = 'password_resets';

    /**
     * Crea un enlace nuevo y anula los anteriores de esa cuenta.
     *
     * Anular los anteriores es lo que hace que "pedir otro correo" invalide el
     * enlace previo: si no, quedaría más de un enlace válido dando vueltas.
     *
     * @return array{token: string, expires_at: string}
     */
    public function issue(int $userId, string $email, int $minutes = 60, string $ip = ''): array
    {
        $token = bin2hex(random_bytes(32));

        $this->run('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL', ['id' => $userId]);

        $this->insert([
            'user_id' => $userId,
            'email' => mb_strtolower($email),
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
            'ip' => $ip === '' ? null : $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
        ];
    }

    /**
     * Enlace vigente para un token recibido, o null si no sirve.
     *
     * @return array<string, mixed>|null
     */
    public function findUsable(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return $this->selectOne(
            'SELECT id, user_id, email, expires_at
             FROM password_resets
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC
             LIMIT 1',
            ['hash' => hash('sha256', $token)]
        );
    }

    /**
     * Marca el enlace como usado. Devuelve false si ya lo estaba, para que dos
     * peticiones simultáneas no puedan gastarlo dos veces.
     */
    public function consume(int $id): bool
    {
        return $this->run(
            'UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL',
            ['id' => $id]
        ) === 1;
    }

    public function invalidateForUser(int $userId): void
    {
        $this->run('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL', ['id' => $userId]);
    }

    /**
     * Cuántos enlaces ha pedido esta cuenta en los últimos minutos. Sirve para
     * no inundar el correo de una persona.
     */
    public function recentCount(string $email, int $minutes = 15): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM password_resets
             WHERE email = :email AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)',
            ['email' => mb_strtolower($email), 'minutes' => $minutes]
        );
    }

    public function prune(int $days = 7): int
    {
        return $this->run(
            'DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
            ['days' => $days]
        );
    }
}
