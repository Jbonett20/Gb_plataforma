<?php

declare(strict_types=1);

/**
 * Funciones auxiliares disponibles en todo el proyecto.
 *
 * Se cargan desde app/bootstrap.php antes que cualquier otra cosa.
 */

use GB\Application;
use GB\Models\MediaRepository;
use GB\Support\Auth;
use GB\Support\Config;
use GB\Support\Csrf;
use GB\Support\Env;

if (!function_exists('env')) {
    /**
     * Lee una variable del archivo .env.
     */
    function env(string $key, ?string $default = null): ?string
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Lee un valor de configuración con notación de punto: config('app.name').
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /**
     * Escapa texto para mostrarlo en HTML sin riesgo de inyección.
     *
     * Toda salida de datos escritos por usuarios DEBE pasar por esta función.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Dirección base del sitio, sin barra final.
     *
     * Se toma de APP_URL; si no está definida, se deduce de la petición para
     * que funcione en cualquier subcarpeta sin reconfigurar nada.
     */
    function base_url(): string
    {
        $configured = rtrim((string) Env::get('APP_URL', ''), '/');

        if ($configured !== '') {
            return $configured;
        }

        static $derived = null;

        if ($derived !== null) {
            return $derived;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $directory = rtrim(dirname($script), '/');

        return $derived = ($secure ? 'https' : 'http') . '://' . $host . $directory;
    }
}

if (!function_exists('url')) {
    /**
     * Construye una dirección absoluta a partir de una ruta interna.
     */
    function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? base_url() . '/' : base_url() . '/' . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * Dirección de un recurso estático de public/assets.
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('storage_path')) {
    /**
     * Ruta absoluta dentro de storage/.
     */
    function storage_path(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return defined('GB_STORAGE_PATH')
            ? GB_STORAGE_PATH . ($path === '' ? '' : '/' . $path)
            : $path;
    }
}

if (!function_exists('public_path')) {
    /**
     * Ruta absoluta dentro de public/.
     */
    function public_path(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return defined('GB_PUBLIC_PATH')
            ? GB_PUBLIC_PATH . ($path === '' ? '' : '/' . $path)
            : $path;
    }
}

if (!function_exists('app')) {
    /**
     * Acceso a los servicios de la aplicación.
     *
     *   app()                        -> el contenedor
     *   app(Auth::class)             -> el servicio de autenticación
     */
    function app(?string $id = null): mixed
    {
        $container = Application::container();

        return $id === null ? $container : $container->get($id);
    }
}

if (!function_exists('auth')) {
    /**
     * Autenticación: auth()->check(), auth()->user(), auth()->isAdmin().
     */
    function auth(): Auth
    {
        return app(Auth::class);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Token de seguridad de la sesión, para incluirlo en formularios y en
     * peticiones de la interfaz.
     */
    function csrf_token(): string
    {
        return app(Csrf::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Campo oculto con el token. Todo formulario que modifique datos debe
     * incluirlo: `<?= csrf_field() ?>`.
     */
    function csrf_field(): string
    {
        return app(Csrf::class)->field();
    }
}

if (!function_exists('csrf_field_cached')) {
    /**
     * Campo oculto con el token, para formularios que van dentro de un bloque
     * cacheado (el pie de página y el formulario de contacto de la portada).
     *
     * Escribe una marca en lugar del token: el HTML del bloque se guarda en la
     * caché de páginas públicas y allí no puede quedar el token de una sesión
     * concreta. La marca se sustituye por el token de quien visita justo antes
     * de enviar la página, en el compositor de páginas.
     */
    function csrf_field_cached(): string
    {
        return '<input type="hidden" name="' . e(app(Csrf::class)->fieldName())
            . '" value="' . e(Csrf::PLACEHOLDER) . '">';
    }
}

if (!function_exists('media_url')) {
    /**
     * Dirección pública de una imagen subida desde el panel.
     *
     * `$width` es el ancho aproximado con el que se mostrará, para elegir la
     * variante más liviana que se vea bien.
     */
    function media_url(?int $mediaId, ?int $width = null): ?string
    {
        if ($mediaId === null || $mediaId <= 0) {
            return null;
        }

        return app(MediaRepository::class)->publicUrl($mediaId, $width);
    }
}

if (!function_exists('media_alt')) {
    /**
     * Texto alternativo de una imagen: lo leen los lectores de pantalla y los
     * buscadores, y es lo que se muestra si la imagen no carga.
     */
    function media_alt(?int $mediaId, string $fallback = ''): string
    {
        if ($mediaId === null || $mediaId <= 0) {
            return $fallback;
        }

        return app(MediaRepository::class)->altText($mediaId, $fallback);
    }
}

if (!function_exists('format_text')) {
    /**
     * Convierte el texto que escribe el SuperAdmin en párrafos y listas, sin
     * permitir código.
     *
     * Convención, explicada en la ayuda del panel:
     *   - una línea que empieza por "- " se convierte en un punto de lista;
     *   - una línea en blanco separa párrafos;
     *   - el resto son párrafos normales.
     *
     * Todo el contenido se escapa primero, así que nada de lo que se escriba
     * puede llegar al navegador como código.
     */
    function format_text(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\R/', $text) ?: [];
        $html = '';
        $inList = false;
        $paragraph = [];

        $flushParagraph = static function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html .= '<p>' . implode('<br>', array_map('e', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);
            $isListItem = str_starts_with(ltrim($line), '- ');

            if ($isListItem) {
                $flushParagraph();

                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }

                $html .= '<li>' . e(ltrim(substr(ltrim($line), 2))) . '</li>';

                continue;
            }

            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }

            if (trim($line) === '') {
                $flushParagraph();

                continue;
            }

            $paragraph[] = $line;
        }

        if ($inList) {
            $html .= '</ul>';
        }

        $flushParagraph();

        return $html;
    }
}

if (!function_exists('setting')) {
    /**
     * Ajuste del sitio editable desde el panel. Si todavía no existe, se usa el
     * valor de configuración como respaldo.
     */
    function setting(string $key, string $default = ''): string
    {
        return app(\GB\Models\SettingRepository::class)->get($key, $default);
    }
}
