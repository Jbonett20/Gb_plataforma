<?php

declare(strict_types=1);

namespace GB\Support;

use RuntimeException;

/**
 * Renderizado de vistas PHP.
 *
 * Una vista es un archivo dentro de `app/Views`. Puede renderizarse sola o
 * envuelta en un layout (`public` o `admin`).
 */
final class View
{
    private ?string $shared = null;

    public function __construct(private string $viewsPath)
    {
    }

    public function exists(string $view): bool
    {
        return is_file($this->resolve($view));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->capture($this->resolve($view), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->capture($this->resolve($layout), $data + ['content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('No se encuentra la vista "%s".', $file));
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }

    private function resolve(string $view): string
    {
        return rtrim($this->viewsPath, '/\\') . '/' . str_replace('.', '/', $view) . '.php';
    }
}
