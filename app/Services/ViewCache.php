<?php

declare(strict_types=1);

namespace GB\Services;

/**
 * Caché de páginas públicas.
 *
 * Guarda el HTML ya generado en `storage/cache` para no repetir consultas en
 * cada visita. Se vacía por completo cada vez que alguien publica un cambio, de
 * modo que nunca se sirve contenido viejo.
 *
 * Sólo se cachean páginas de visitantes sin sesión: el contenido personalizado
 * (área de estudiante, panel) nunca se guarda aquí.
 */
final class ViewCache
{
    public function __construct(
        private string $path,
        private int $ttl = 600,
        private bool $enabled = true,
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $file = $this->file($key);

        if (!is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $separator = strpos($contents, "\n");

        if ($separator === false) {
            return null;
        }

        $expires = (int) substr($contents, 0, $separator);

        if ($expires < time()) {
            @unlink($file);

            return null;
        }

        return substr($contents, $separator + 1);
    }

    public function put(string $key, string $html): void
    {
        if (!$this->enabled || $html === '') {
            return;
        }

        $this->ensureDirectory();

        // Se escribe de forma atómica para que dos visitantes simultáneos no
        // lean un archivo a medio escribir.
        $file = $this->file($key);
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporary, (time() + $this->ttl) . "\n" . $html) === false) {
            return;
        }

        @rename($temporary, $file);
    }

    public function forget(string $key): void
    {
        $file = $this->file($key);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Vacía toda la caché pública. Devuelve cuántos archivos borró.
     */
    public function flush(): int
    {
        if (!is_dir($this->path)) {
            return 0;
        }

        $removed = 0;

        foreach (glob(rtrim($this->path, '/\\') . '/*.html') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Elimina los archivos vencidos que nadie volvió a pedir.
     */
    public function prune(): int
    {
        if (!is_dir($this->path)) {
            return 0;
        }

        $removed = 0;

        foreach (glob(rtrim($this->path, '/\\') . '/*.html') ?: [] as $file) {
            $contents = file_get_contents($file, length: 20);

            if ($contents === false) {
                continue;
            }

            $separator = strpos($contents, "\n");
            $expires = $separator === false ? 0 : (int) substr($contents, 0, $separator);

            if ($expires < time() && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function file(string $key): string
    {
        return rtrim($this->path, '/\\') . '/' . hash('sha256', $key) . '.html';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }
    }
}
