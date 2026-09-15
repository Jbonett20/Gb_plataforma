<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\SettingRepository;
use GB\Services\ViewCache;
use GB\Support\Audit;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;
use Throwable;

/**
 * Ajustes del sitio editables desde el panel.
 *
 * La pantalla no está escrita a mano: se genera a partir de
 * `config/admin_labels.php`, igual que los formularios de los bloques. Así, un
 * ajuste nuevo se declara en ese archivo y aparece aquí con su etiqueta y su
 * ayuda, sin tocar el controlador ni la vista.
 *
 * Se editan sólo los ajustes declarados: lo que no está en la lista no se puede
 * tocar, ni por descuido ni enviando campos de más desde el navegador.
 */
final class SettingController extends Controller
{
    public function __construct(
        View $view,
        private SettingRepository $settings,
        private ViewCache $cache,
        private Audit $audit,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $screens = $this->screens();
        $key = (string) $request->routeParam('screen', '');

        if ($key === '' || !isset($screens[$key])) {
            $key = (string) array_key_first($screens);
        }

        $screen = $screens[$key] ?? null;

        if ($screen === null) {
            return $this->redirectWith('/admin', 'Todavía no hay ajustes editables.', 'warning');
        }

        return $this->respond('admin.settings', $this->pageData([
            'title' => (string) $screen['label'] . ' | Panel',
            'screen' => $screen,
            'screenKey' => $key,
            'screens' => $screens,
            'values' => $this->values($screen),
            'errors' => [],
            'message' => '',
        ]), 'layouts.admin');
    }

    public function save(Request $request): Response
    {
        $screens = $this->screens();
        $key = (string) $request->routeParam('screen', '');
        $screen = $screens[$key] ?? null;

        if ($screen === null) {
            return $this->redirectWith('/admin', 'Ese apartado de ajustes no existe.', 'warning');
        }

        $input = $request->all();
        $errors = [];
        $saved = 0;

        foreach ($screen['fields'] as $settingKey => $field) {
            $value = $this->normalise($field, $input[$settingKey] ?? null);
            $error = $this->validate($field, $settingKey, $value);

            if ($error !== '') {
                $errors[$settingKey][] = $error;

                continue;
            }

            // Guardar sólo lo que cambia evita dejar rastro de ediciones vacías
            // en la auditoría y no toca la fecha de lo que sigue igual.
            if ($this->settings->get($settingKey, $this->defaultFor($field)) === $value) {
                continue;
            }

            $this->settings->set($settingKey, $value, $this->auth->id());
            $saved++;
            $this->record($settingKey, (string) $field['label'], $value);
        }

        if ($errors !== []) {
            return $this->respond('admin.settings', $this->pageData([
                'title' => (string) $screen['label'] . ' | Panel',
                'screen' => $screen,
                'screenKey' => $key,
                'screens' => $screens,
                'values' => array_merge($this->values($screen), $input),
                'errors' => $errors,
                'message' => 'No se guardó nada: revisa lo que está marcado.',
            ]), 'layouts.admin', 422);
        }

        // El encabezado y el pie se arman con estos valores: lo guardado en la
        // caché de páginas públicas deja de servir.
        $this->cache->flush();

        return $this->redirectWith(
            (string) $screen['path'],
            $saved === 0
                ? 'No había cambios que guardar.'
                : ($saved === 1 ? 'Ajuste guardado.' : $saved . ' ajustes guardados.'),
            $saved === 0 ? 'info' : 'success'
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function screens(): array
    {
        $screens = (array) config('admin_labels.settings_screens', []);

        return array_filter($screens, static fn (mixed $screen): bool => is_array($screen) && isset($screen['fields']));
    }

    /**
     * @param array<string, mixed> $screen
     * @return array<string, string>
     */
    private function values(array $screen): array
    {
        $values = [];

        foreach ($screen['fields'] as $key => $field) {
            $default = $this->defaultFor($field);
            $values[$key] = $this->settings->get($key, $default);
        }

        return $values;
    }

    /** @param array<string, mixed> $field */
    private function defaultFor(array $field): string
    {
        return (string) ($field['default'] ?? '');
    }

    /** @param array<string, mixed> $field */
    private function normalise(array $field, mixed $raw): string
    {
        $value = is_scalar($raw) ? trim((string) $raw) : '';

        if ((string) ($field['type'] ?? 'text') === 'boolean') {
            return in_array($raw, ['1', 'on', 'true', 'yes', 'si', 'sí', true], true) ? '1' : '0';
        }

        return $value;
    }

    /** @param array<string, mixed> $field */
    private function validate(array $field, string $key, string $value): string
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'boolean') {
            return '';
        }

        $validator = new Validator([$key => $value], [$key => (string) $field['label']]);
        $rules = [];

        if (isset($field['max'])) {
            $rules[] = 'max:' . (int) $field['max'];
        }

        if ($type === 'url') {
            $rules[] = 'url';
        }

        if ($type === 'email') {
            $rules[] = 'email';
        }

        if ($rules === []) {
            return '';
        }

        $validator->validate([$key => implode('|', $rules)]);

        return (string) ($validator->firstError() ?? '');
    }

    private function record(string $key, string $label, string $value): void
    {
        try {
            $this->audit->log(
                action: 'setting_changed',
                entityType: 'setting',
                entityId: null,
                summary: sprintf('Ajuste «%s» (%s) cambiado a «%s»', $label, $key, $value)
            );
        } catch (Throwable) {
            // La auditoría es informativa: el ajuste ya está guardado.
        }
    }
}
