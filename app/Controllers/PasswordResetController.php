<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Services\AccountService;
use GB\Services\PageComposer;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Recuperación de contraseña.
 *
 * Dos reglas que no se ven pero importan:
 *
 *   - Al pedir el enlace, la respuesta es idéntica exista o no la cuenta. Si el
 *     mensaje cambiara, cualquiera podría averiguar qué correos están
 *     registrados probando uno a uno desde este formulario.
 *   - El enlace sirve una sola vez y caduca. El token se guarda encriptado, así
 *     que ni leyendo la base de datos se puede usar.
 */
final class PasswordResetController extends Controller
{
    public function __construct(
        View $view,
        private AccountService $accounts,
        private PageComposer $composer,
    ) {
        parent::__construct($view);
    }

    public function showRequest(Request $request): Response
    {
        return $this->respond('auth.forgot', $this->pageData($this->composer->chrome() + [
            'title' => 'Recuperar contraseña | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
            'values' => [],
            'errors' => [],
            'message' => '',
            'mailActive' => $this->accounts->mailConfigured(),
        ]));
    }

    public function sendLink(Request $request): Response
    {
        $result = $this->accounts->requestPasswordReset($request->all());

        $data = $this->composer->chrome() + [
            'title' => 'Recuperar contraseña | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
            'values' => $result->values(),
            'errors' => $result->errors(),
            'message' => $result->message(),
            'mailActive' => $this->accounts->mailConfigured(),
        ];

        return $this->respond('auth.forgot', $this->pageData($data), 'layouts.public', $result->succeeded() ? 200 : 422);
    }

    /**
     * Formulario de contraseña nueva. Si el enlace no sirve, se explica y se
     * ofrece pedir otro: nunca se muestra un error técnico.
     */
    public function showReset(Request $request): Response
    {
        $token = (string) $request->routeParam('token');
        $link = $this->accounts->findResetLink($token);

        if ($link === null) {
            return $this->expired();
        }

        return $this->respond('auth.reset', $this->pageData($this->composer->chrome() + [
            'title' => 'Crear contraseña nueva | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
            'token' => $token,
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => [],
            'errors' => [],
            'message' => '',
        ]));
    }

    public function reset(Request $request): Response
    {
        $token = (string) $request->routeParam('token');
        $result = $this->accounts->resetPassword($token, $request->all());

        if ($result->succeeded()) {
            return $this->redirect('/mi-cuenta');
        }

        // Si el enlace dejó de servir entre abrir el formulario y enviarlo, se
        // responde con la pantalla que invita a pedir uno nuevo.
        if ($this->accounts->findResetLink($token) === null) {
            return $this->expired();
        }

        return $this->respond('auth.reset', $this->pageData($this->composer->chrome() + [
            'title' => 'Crear contraseña nueva | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
            'token' => $token,
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => $result->values(),
            'errors' => $result->errors(),
            'message' => $result->firstError() ?? $result->message(),
        ]), 'layouts.public', 422);
    }

    private function expired(): Response
    {
        return $this->respond('auth.reset-expired', $this->pageData($this->composer->chrome() + [
            'title' => 'Enlace vencido | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
        ]), 'layouts.public', 410);
    }
}
