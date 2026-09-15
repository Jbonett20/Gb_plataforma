<?php

declare(strict_types=1);

/**
 * Arranque de la aplicación.
 *
 * Define rutas base, registra el autoloader, carga el archivo .env y la
 * configuración, y ajusta el reporte de errores según APP_DEBUG.
 */

if (defined('GB_BASE_PATH')) {
    return;
}

define('GB_START', microtime(true));
define('GB_BASE_PATH', dirname(__DIR__));
define('GB_APP_PATH', GB_BASE_PATH . '/app');
define('GB_PUBLIC_PATH', GB_BASE_PATH . '/public');
define('GB_STORAGE_PATH', GB_BASE_PATH . '/storage');

require GB_APP_PATH . '/Support/Autoloader.php';

GB\Support\Autoloader::register(GB_APP_PATH);

// Composer es opcional: el proyecto arranca sin `composer install`.
if (is_file(GB_BASE_PATH . '/vendor/autoload.php')) {
    require GB_BASE_PATH . '/vendor/autoload.php';
}

require GB_APP_PATH . '/Support/helpers.php';

GB\Support\Env::load(GB_BASE_PATH . '/.env');
GB\Support\Config::load(GB_BASE_PATH . '/config');

date_default_timezone_set((string) GB\Support\Env::get('APP_TIMEZONE', 'America/Bogota'));

$debug = GB\Support\Env::bool('APP_DEBUG', false);

// El visitante nunca debe ver un error técnico: en producción se registra en
// storage/logs y se muestra una página amigable.
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

if (is_dir(GB_STORAGE_PATH . '/logs') || @mkdir(GB_STORAGE_PATH . '/logs', 0775, true)) {
    ini_set('error_log', GB_STORAGE_PATH . '/logs/php-errors.log');
}

// No revelar la versión de PHP en las respuestas.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
}
