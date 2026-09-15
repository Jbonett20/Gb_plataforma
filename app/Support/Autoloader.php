<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Autoloader PSR-4 mínimo para el namespace GB\.
 *
 * Se registra desde app/bootstrap.php. Si Composer está instalado, su propio
 * autoloader también está registrado y ambos conviven sin conflicto.
 */
final class Autoloader
{
    private const PREFIX = 'GB\\';

    public static function register(string $appPath): void
    {
        spl_autoload_register(static function (string $class) use ($appPath): void {
            if (!str_starts_with($class, self::PREFIX)) {
                return;
            }

            $relative = substr($class, strlen(self::PREFIX));
            $file = $appPath . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
