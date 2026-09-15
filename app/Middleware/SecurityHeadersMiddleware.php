<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Cabeceras de seguridad.
 *
 * Impide que el navegador interprete como código un archivo con otro tipo, que
 * el sitio se muestre dentro de una página ajena y que se filtre la dirección
 * de origen al salir hacia otros dominios.
 *
 * La política de contenido (CSP) es configurable: mientras los bloques del CMS
 * puedan incluir estilos o scripts en línea, se permite `unsafe-inline`; cuando
 * dejen de hacerlo, basta con quitar esos permisos en config/app.php.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @param array<string, mixed> $config config('app.security') */
    public function __construct(private array $config = [])
    {
    }

    public function handle(Request $request, Closure $next, array $args = []): Response
    {
        $response = $next($request);

        $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => (string) ($this->config['x_frame_options'] ?? 'SAMEORIGIN'),
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);

        $csp = trim((string) ($this->config['csp'] ?? ''));

        if ($csp !== '') {
            $response->withHeader('Content-Security-Policy', $csp);
        }

        // Sólo se anuncia HSTS cuando el sitio ya opera sobre conexión cifrada.
        if ($request->isSecure() && ($this->config['hsts'] ?? true)) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
