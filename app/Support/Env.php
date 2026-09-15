<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Lectura de la configuración del entorno (.env).
 *
 * Usa vlucas/phpdotenv cuando Composer está instalado y, si no lo está, un
 * analizador propio equivalente para los casos habituales. Esto permite
 * arrancar el proyecto en un hosting compartido sin ejecutar `composer install`.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    private static bool $loaded = false;

    private static ?string $loadedFrom = null;

    public static function load(string $file): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        self::$loadedFrom = $file;

        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable(dirname($file), basename($file))->safeLoad();

            return;
        }

        self::parseFile($file);
    }

    /**
     * Indica desde qué archivo se cargó la configuración, para mensajes de ayuda.
     */
    public static function loadedFrom(): ?string
    {
        return self::$loadedFrom;
    }

    public static function has(string $key): bool
    {
        return self::lookup($key) !== null;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::lookup($key);

        if ($value === null) {
            return $default;
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::lookup($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::lookup($key);

        if ($value === null || trim($value) === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Devuelve el valor de una variable obligatoria o detiene la ejecución con
     * un mensaje que explica exactamente qué falta y dónde ponerlo.
     */
    public static function required(string $key): string
    {
        $value = self::lookup($key);

        if ($value === null || trim($value) === '') {
            throw new ConfigurationException(sprintf(
                'Falta la variable de configuración "%s". Ábrela en el archivo .env y asígnale un valor. '
                . 'Si el archivo .env no existe, créalo copiando .env.example.',
                $key
            ));
        }

        return $value;
    }

    private static function lookup(string $key): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        foreach ([$_ENV, $_SERVER] as $source) {
            if (isset($source[$key]) && is_string($source[$key])) {
                self::$values[$key] = $source[$key];

                return $source[$key];
            }
        }

        $fromGetenv = getenv($key);

        if (is_string($fromGetenv) && $fromGetenv !== '') {
            self::$values[$key] = $fromGetenv;

            return $fromGetenv;
        }

        return null;
    }

    /**
     * Analizador de respaldo para archivos .env sin Composer.
     */
    private static function parseFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }

            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $value = trim($value);

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                } else {
                    // Comentario al final de la línea sin comillas.
                    $hash = strpos($value, ' #');

                    if ($hash !== false) {
                        $value = rtrim(substr($value, 0, $hash));
                    }
                }
            }

            self::$values[$key] = $value;
            $_ENV[$key] = $value;
        }
    }
}
