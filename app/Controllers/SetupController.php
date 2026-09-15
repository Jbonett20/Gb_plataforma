<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Support\HealthCheck;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Asistente de instalación.
 *
 * Sólo responde cuando APP_DEBUG está activo. En producción devuelve 404 para
 * no exponer el estado del servidor a un visitante.
 */
final class SetupController extends Controller
{
    public function __construct(View $view, private HealthCheck $health)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        if (!(bool) config('app.debug', false)) {
            return $this->respond('errors.404', $this->pageData([
                'title' => 'Página no encontrada',
            ]), 'layouts.public', 404);
        }

        $checks = $this->health->run();
        $hasErrors = false;

        foreach ($checks as $check) {
            if ($check['status'] === HealthCheck::ERROR) {
                $hasErrors = true;
                break;
            }
        }

        return $this->respond('setup.index', $this->pageData([
            'title' => 'Instalación | ' . (string) config('app.name'),
            'checks' => $checks,
            'hasErrors' => $hasErrors,
            'phpVersion' => PHP_VERSION,
            'basePath' => GB_BASE_PATH,
            'storagePath' => GB_STORAGE_PATH,
            'storageWritable' => is_writable(GB_STORAGE_PATH),
            'composerInstalled' => is_file(GB_BASE_PATH . '/vendor/autoload.php'),
        ]));
    }
}
