<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Services\OnboardingService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Cierre del recorrido guiado del primer ingreso (tarea 11.5).
 *
 * Recorrer las pantallas no cambia nada por sí solo: lo que se guarda es que la
 * persona ya vio la guía, para no volver a mostrarla cada vez que entre.
 */
final class OnboardingController extends Controller
{
    public function __construct(
        View $view,
        private OnboardingService $onboarding,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function finish(Request $request): Response
    {
        $this->onboarding->complete($this->auth->id());

        return $this->redirectWith(
            '/admin',
            'Recorrido terminado. Ya no volverá a aparecer; puedes volver a consultarlo desde el menú cuando quieras.'
        );
    }
}
