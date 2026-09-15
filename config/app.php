<?php

declare(strict_types=1);

/**
 * Configuración general de la aplicación.
 *
 * Los valores se leen del archivo .env. Los que no dependen del entorno
 * (comportamientos, listas) se declaran directamente aquí.
 */

use GB\Support\Env;

return [
    'name' => (string) Env::get('APP_NAME', 'González & Ballesteros'),
    'env' => (string) Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone' => (string) Env::get('APP_TIMEZONE', 'America/Bogota'),
    'key' => (string) Env::get('APP_KEY', ''),

    // Datos de contacto de la firma. El panel puede sobrescribirlos desde la
    // configuración del sitio (tabla settings); esto es sólo el valor inicial.
    'company' => [
        'legal_name' => 'González & Ballesteros - Consultores y Asociados',
        'email' => 'gerencia@gonzalezballesteros.com',
        'phone' => '3188517817 - 3017543649',
        'whatsapp' => '573188517817',
        'address' => 'Bogotá y Barranquilla',
        'coverage' => 'Atención virtual o presencial en toda Colombia',
        'instagram' => 'https://www.instagram.com/gonzalezyballesteros_abogados/',
        'linkedin' => 'https://www.linkedin.com/company/gonzalez-ballesteros',
    ],

    'locales' => [
        'default' => 'es',
        'supported' => ['es'],
    ],

    'session' => [
        'name' => (string) Env::get('SESSION_NAME', 'gb_session'),
        'student_lifetime_minutes' => Env::int('SESSION_LIFETIME_STUDENT', 120),
        'admin_lifetime_minutes' => Env::int('SESSION_LIFETIME_ADMIN', 20),
        'max_concurrent' => Env::int('MAX_CONCURRENT_SESSIONS', 2),
    ],

    'auth' => [
        'email_login_required' => Env::bool('EMAIL_LOGIN_REQUIRED', false),
        'legacy_login_enabled' => Env::bool('LEGACY_LOGIN_ENABLED', true),
        'payments_enabled' => Env::bool('PAYMENTS_ENABLED', false),
    ],

    'security' => [
        'login_max_attempts' => Env::int('LOGIN_MAX_ATTEMPTS', 5),
        'login_lockout_minutes' => Env::int('LOGIN_LOCKOUT_MINUTES', 15),
        'password_min_length' => 10,

        // Cabeceras de seguridad.
        'x_frame_options' => 'SAMEORIGIN',
        'hsts' => true,

        // Política de contenido. Mientras los bloques del CMS puedan incluir
        // estilos o scripts en línea se permiten con 'unsafe-inline'; cuando
        // dejen de hacerlo, quita esos permisos para endurecer la política.
        // Se autoriza el CDN de la librería de avisos que usan los formularios.
        'csp' => "default-src 'self'; "
            . "img-src 'self' data: https:; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
            . "font-src 'self' https://fonts.gstatic.com data:; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "connect-src 'self'; "
            . "media-src 'self'; "
            . "frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "object-src 'none'",
    ],

    'uploads' => [
        'max_image_mb' => Env::int('UPLOAD_MAX_IMAGE_MB', 8),
        'max_document_mb' => Env::int('UPLOAD_MAX_DOCUMENT_MB', 64),
        'max_video_mb' => Env::int('UPLOAD_MAX_VIDEO_MB', 512),
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
    ],

    // Vigencia de los enlaces firmados que entregan material de pago.
    'protected_link_ttl' => Env::int('PROTECTED_LINK_TTL', 600),

    // Días que se conserva el contenido eliminado antes del borrado definitivo.
    'trash_retention_days' => Env::int('TRASH_RETENTION_DAYS', 30),

    // Descarga de plantillas gratuitas: libre | opcional | obligatorio
    'template_download_policy' => (string) Env::get('TEMPLATE_DOWNLOAD_POLICY', 'libre'),

    'pagination' => [
        'per_page' => 12,
        'admin_per_page' => 25,
    ],

    // Caché del HTML de las páginas públicas. Se vacía sola cada vez que alguien
    // publica un cambio desde el panel. Las páginas con sesión iniciada nunca se
    // guardan aquí.
    'cache_enabled' => Env::bool('VIEW_CACHE', true),
    'cache_ttl' => Env::int('VIEW_CACHE_TTL', 600),

    'mail' => [
        'host' => (string) Env::get('MAIL_HOST', ''),
        'port' => Env::int('MAIL_PORT', 587),
        'username' => (string) Env::get('MAIL_USERNAME', ''),
        'password' => (string) Env::get('MAIL_PASSWORD', ''),
        'encryption' => (string) Env::get('MAIL_ENCRYPTION', 'tls'),
        'from_address' => (string) Env::get('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
        'from_name' => (string) Env::get('MAIL_FROM_NAME', 'González & Ballesteros'),
    ],

    'payments' => [
        'gateway' => (string) Env::get('PAYMENT_GATEWAY', 'manual'),
        'currency' => (string) Env::get('PAYMENT_CURRENCY', 'COP'),
        'currency_symbol' => (string) Env::get('PAYMENT_CURRENCY_SYMBOL', '$'),
    ],
];
