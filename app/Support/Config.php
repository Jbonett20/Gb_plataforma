<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Acceso a los archivos de `config/`.
 *
 * Cada archivo devuelve un arreglo y se consulta con notación de punto:
 * `Config::get('app.name')` lee la clave `name` de `config/app.php`.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    public static function load(string $configPath): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        foreach (glob(rtrim($configPath, '/\\') . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $value = require $file;

            self::$items[$key] = is_array($value) ? $value : [];
        }
    }

    /**
     * Registra un valor calculado en tiempo de ejecución.
     *
     * Acepta tanto una clave suelta (`Config::set('base_path', …)`) como una ruta
     * con puntos (`Config::set('app.auth.payments_enabled', true)`), que es la
     * forma en la que después se consulta. Las rutas con puntos se escriben en su
     * sitio; si no se hiciera así, el valor quedaría guardado bajo un nombre con
     * puntos que `get()` nunca encuentra y el cambio se perdería en silencio.
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);

        if (count($segments) === 1) {
            self::$items[$key] = $value;

            return;
        }

        $target = &self::$items;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $target[$segment] = $value;

                break;
            }

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    /** @return array<int|string, mixed> */
    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key, $default);

        return is_array($value) ? $value : $default;
    }
}
