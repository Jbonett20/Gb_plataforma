<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\BlockRepository;
use GB\Models\ModuleRepository;
use GB\Support\Auth;
use GB\Support\Csrf;

/**
 * Arma las páginas del sitio público a partir de los bloques y los módulos.
 *
 * Se apoya en la caché de vistas: si hay una copia vigente y quien visita no
 * tiene sesión iniciada, se sirve tal cual. Las páginas personalizadas nunca se
 * guardan en caché.
 */
final class PageComposer
{
    public function __construct(
        private BlockRepository $blocks,
        private ModuleRepository $modules,
        private BlockRenderer $renderer,
        private NavigationBuilder $navigation,
        private ViewCache $cache,
        private Auth $auth,
        private Csrf $csrf,
    ) {
    }

    /**
     * Portada del sitio.
     *
     * El bloque del pie de página no se devuelve dentro de `sections`: se dibuja
     * en el pie del layout, fuera del contenido principal.
     *
     * @return array{
     *     sections: string,
     *     footer: string,
     *     nav: array<int, array{label: string, href: string}>,
     *     hasContent: bool
     * }
     */
    public function home(): array
    {
        $cacheable = $this->cacheable();
        $cacheKey = 'public.home';

        if ($cacheable) {
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                $decoded = json_decode($cached, true);

                if (is_array($decoded) && isset($decoded['sections'], $decoded['nav'], $decoded['hasContent'])) {
                    return $this->withFreshToken($decoded);
                }
            }
        }

        $blocks = $this->blocks->publishedBlocks();

        $footer = '';
        $sections = [];

        foreach ($blocks as $block) {
            if ((string) ($block['key_name'] ?? '') === 'footer') {
                $footer = $this->renderer->render($block);

                continue;
            }

            $sections[] = $block;
        }

        $data = [
            'sections' => $this->renderer->renderAll($sections),
            'footer' => $footer,
            'nav' => $this->navigation->publicMenu(),
            'hasContent' => $sections !== [],
        ];

        if ($cacheable) {
            $this->cache->put($cacheKey, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        return $this->withFreshToken($data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedBlocks(): array
    {
        return $this->blocks->publishedBlocks();
    }

    /**
     * Cabecera y pie comunes a cualquier página que no sea la portada.
     *
     * Las páginas de cuenta, de cursos y de noticias comparten el mismo menú y el
     * mismo pie que el inicio, para que el sitio se vea como uno solo.
     *
     * @return array{navLinks: array<int, array{label: string, href: string}>, footerHtml: string}
     */
    public function chrome(): array
    {
        $footer = '';

        foreach ($this->blocks->publishedBlocks() as $block) {
            if ((string) ($block['key_name'] ?? '') === 'footer') {
                $footer = $this->renderer->render($block);

                break;
            }
        }

        return [
            'navLinks' => $this->navigation->publicMenu(),
            'footerHtml' => $this->withFreshToken($footer),
        ];
    }

    /**
     * Módulos visibles, para las secciones de cursos, plantillas, videos y noticias.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleModules(): array
    {
        return $this->modules->visible();
    }

    /**
     * Cambia la marca del token por el token de la sesión que está mirando la
     * página.
     *
     * El HTML de los bloques (incluido el formulario de contacto y el del pie)
     * se guarda en la caché pública. El token de seguridad es de cada sesión, así
     * que dentro de la página guardada sólo puede haber una marca: si se guardara
     * un token, el siguiente visitante recibiría uno ajeno y su envío se
     * rechazaría por seguridad, que es exactamente lo que ocurría antes.
     */
    private function withFreshToken(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(Csrf::PLACEHOLDER, $this->csrf->token(), $value);
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->withFreshToken($item);
        }

        return $value;
    }

    /**
     * Una página pública sólo se cachea si quien la pide no tiene sesión: el
     * contenido personalizado nunca debe servirse a otra persona.
     */
    private function cacheable(): bool
    {
        return $this->cache->enabled() && !$this->auth->check();
    }
}
