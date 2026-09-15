<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Middleware\ThrottleMiddleware;
use GB\Services\AccountService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Throttle;
use GB\Support\View;

/**
 * Acceso al panel de administración.
 *
 * Es una puerta distinta de la del área de estudiantes, aunque valide contra la
 * misma tabla de cuentas: aquí sólo entra el rol de superadministración. Un
 * estudiante con credenciales correctas recibe un rechazo explícito y no se le
 * abre sesión, de modo que la contraseña del área personal nunca sirve para
 * llegar al panel.
 */
final class AuthController extends Controller
{
    public function __construct(
        View $view,
        private AccountService $accounts,
        private Auth $auth,
        private Throttle $throttle,
    ) {
        parent::__construct($view);
    }

    public function showLogin(Request $request): Response
    {
        if ($this->auth->check()) {
            return $this->redirect($this->auth->isAdmin() ? '/admin' : '/mi-cuenta');
        }

        return $this->respond('admin.login', $this->pageData([
            'title' => 'Acceso al panel | ' . (string) config('app.name'),
            'values' => [],
            'errors' => [],
        ]));
    }

    public function login(Request $request): Response
    {
        $identifier = trim($request->string('email'));
        $password = $request->string('password');
        $errors = [];

        if ($identifier === '') {
            $errors['email'][] = 'Escribe el correo de la cuenta de administración.';
        }

        if ($password === '') {
            $errors['password'][] = 'Escribe tu contraseña.';
        }

        if ($errors === []) {
            $remaining = $this->accounts->lockoutRemaining($identifier);

            if ($remaining > 0) {
                return $this->failed($identifier, [
                    'email' => [sprintf(
                        'Por varios intentos fallidos, esta cuenta está bloqueada. Vuelve a intentarlo en %d %s.',
                        $remaining,
                        $remaining === 1 ? 'minuto' : 'minutos'
                    )],
                ]);
            }

            // El rol exigido es la única diferencia con el acceso público.
            if ($this->auth->attempt($identifier, $password, $request->ip(), ['SuperAdmin'])) {
                $this->throttle->clear('login', ThrottleMiddleware::identifierFor($request, 'email'));

                return $this->redirect('/admin');
            }

            $message = (string) $this->auth->failureMessage();

            if ($this->auth->failureReason() === Auth::FAILURE_INVALID) {
                $left = $this->accounts->attemptsLeft($identifier);

                if ($left === 1) {
                    $message .= ' Te queda un intento antes de que la cuenta se bloquee temporalmente.';
                }
            }

            $errors['email'][] = $message;
        }

        return $this->failed($identifier, $errors);
    }

    /** @param array<string, array<int, string>> $errors */
    private function failed(string $identifier, array $errors): Response
    {
        return $this->respond('admin.login', $this->pageData([
            'title' => 'Acceso al panel | ' . (string) config('app.name'),
            'values' => ['email' => $identifier],
            'errors' => $errors,
        ]), 'layouts.public', 422);
    }

    /**
     * Cambio de la contraseña inicial.
     *
     * Quien crea una cuenta conoce su contraseña, así que hasta que la persona
     * no la cambie el panel permanece cerrado para ella. Se exige la contraseña
     * actual para que una sesión olvidada abierta no permita apropiarse de la
     * cuenta.
     */
    public function showPassword(Request $request): Response
    {
        return $this->respond('admin.password', $this->pageData([
            'title' => 'Cambiar contraseña | ' . (string) config('app.name'),
            'requirements' => $this->accounts->policy()->requirements(),
            'errors' => [],
            'message' => '',
        ]), 'layouts.public');
    }

    public function updatePassword(Request $request): Response
    {
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $result = $this->accounts->changePassword($userId, $request->all());

        if ($result->succeeded()) {
            $this->auth->clearMustChangePassword();

            return $this->redirectWith('/admin', 'Tu contraseña quedó cambiada. Desde ahora entrarás con la nueva.');
        }

        return $this->respond('admin.password', $this->pageData([
            'title' => 'Cambiar contraseña | ' . (string) config('app.name'),
            'requirements' => $this->accounts->policy()->requirements(),
            'errors' => $result->errors(),
            'message' => $result->firstError() ?? '',
        ]), 'layouts.public', 422);
    }
}
