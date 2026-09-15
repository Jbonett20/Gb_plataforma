<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Ajustes del sitio editables desde el panel.
 *
 * Se leen una sola vez por petición y quedan en memoria: son pocos valores y se
 * consultan muchas veces (el pie de página, el botón de WhatsApp, la moneda).
 *
 * Si un ajuste todavía no existe en la base de datos, se usa el valor de
 * `config/app.php` como respaldo para que el sitio nunca quede a medias.
 */
final class SettingRepository extends Model
{
    protected string $table = 'settings';

    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function get(string $key, string $default = ''): string
    {
        $all = $this->all();

        if (array_key_exists($key, $all) && $all[$key] !== '') {
            return $all[$key];
        }

        return $this->fallback($key, $default);
    }

    public function set(string $key, string $value, ?int $updatedBy = null): void
    {
        $this->run(
            'INSERT INTO settings (key_name, value, updated_by, created_at, updated_at)
             VALUES (:key, :value, :user, NOW(), NOW())
             ON DUPLICATE KEY UPDATE value = :value2, updated_by = :user2, updated_at = NOW()',
            [
                'key' => $key,
                'value' => $value,
                'user' => $updatedBy,
                'value2' => $value,
                'user2' => $updatedBy,
            ]
        );

        $this->cache = null;
    }

    /**
     * Todos los ajustes, indexados por su clave.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        foreach ($this->select('SELECT key_name, value FROM settings') as $row) {
            $this->cache[(string) $row['key_name']] = (string) ($row['value'] ?? '');
        }

        return $this->cache;
    }

    public function forget(): void
    {
        $this->cache = null;
    }

    /**
     * Valores de partida, tomados de la configuración del proyecto.
     */
    private function fallback(string $key, string $default): string
    {
        $company = (array) config('app.company', []);

        $fromConfig = match ($key) {
            'site_name' => (string) config('app.name', ''),
            'site_email' => (string) ($company['email'] ?? ''),
            'site_phone' => (string) ($company['phone'] ?? ''),
            'site_address' => (string) ($company['address'] ?? ''),
            'site_coverage' => (string) ($company['coverage'] ?? ''),
            'whatsapp' => (string) ($company['whatsapp'] ?? ''),
            'instagram' => (string) ($company['instagram'] ?? ''),
            'linkedin' => (string) ($company['linkedin'] ?? ''),
            'currency' => (string) config('app.payments.currency', 'COP'),
            'currency_symbol' => (string) config('app.payments.currency_symbol', '$'),
            default => $default,
        };

        return $fromConfig !== '' ? $fromConfig : $default;
    }
}
