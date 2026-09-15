<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Middleware\ThrottleMiddleware;
use GB\Services\AccountService;
use GB\Services\PageComposer;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Throttle;
use GB\Support\View;

/**
 * Registro, inicio y cierre de sesión.
 *
 * Además de lo visible, aquí se cumplen dos cosas que el requisito exige:
 *
 *   - al iniciar sesión se renueva el identificador de sesión (`Auth::login`),
 *     de modo que un identificador obtenido antes no sirva después;
 *   - al cerrar sesión se borra el contenido y se renueva el identificador, y
 *     las páginas privadas se envían sin caché, así que el botón "atrás" del
 *     navegador no puede mostrar el área privada.
 */
final class AuthController extends Controller
{
    public function __construct(
        View $view,
        private AccountService $accounts,
        private Auth $auth,
        private Throttle $throttle,
        private PageComposer $composer,
    ) {
        parent::__construct($view);
    }

    public function showLogin(Request $request): Response
    {
        // La sesión del panel no cierra esta puerta: quien administra puede
        // querer entrar con una cuenta de estudiante desde el mismo navegador.
        // La vista le avisa de que tiene la sesión abierta y le ofrece cerrarla.
        if ($this->auth->check() && !$this->auth->isAdmin()) {
            return $this->redirect('/mi-cuenta');
        }

        return $this->respond('auth.login', $this->pageData($this->composer->chrome() + [
            'title' => 'Iniciar sesión | ' . (string) config('app.name'),
            'metaDescription' => 'Entra a tu área personal para continuar tus cursos.',
            'bodyClass' => 'account-page',
            'values' => [],
            'errors' => [],
            'message' => '',
        ]));
    }

    public function login(Request $request): Response
    {
        $identifier = trim((string) $request->string('email'));
        $password = $request->string('password');
        $emailRequired = (bool) config('app.auth.email_login_required', false);

        $errors = [];

        if ($identifier === '') {
            $errors['email'][] = $emailRequired
                ? 'Escribe el correo electrónico con el que te registraste.'
                : 'Escribe tu correo, teléfono o nombre de acceso.';
        } elseif ($emailRequired && filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Escribe el correo electrónico con el que te registraste.';
        }

        if ($password === '') {
            $errors['password'][] = 'Escribe tu contraseña.';
        }

        if ($errors === []) {
            // Aviso previo al bloqueo: si ya está bloqueada, se dice cuánto falta.
            $remaining = $this->accounts->lockoutRemaining($identifier);

            if ($remaining > 0) {
                return $this->loginFailed($request, [
                    'email' => [sprintf(
                        'Por varios intentos fallidos, esta cuenta está bloqueada. Vuelve a intentarlo en %d %s.',
                        $remaining,
                        $remaining === 1 ? 'minuto' : 'minutos'
                    )],
                ], $identifier);
            }

            if ($this->auth->attempt($identifier, $password, $request->ip())) {
                // El intento salió bien: se borra el contador para no arrastrar
                // los fallos anteriores hasta un bloqueo que ya no aplica.
                $this->throttle->clear('login', ThrottleMiddleware::identifierFor($request, 'email'));

                // Esta puerta es la del área de estudiantes, pero quien entra
                // manda: si las credenciales son de una cuenta de administración,
                // no se le puede dejar en «no tienes permiso» —su área no es
                // ésta—, así que se le deja donde administra. Cuando la cuenta
                // es de estudiante, entra a su área como siempre.
                $destino = $this->auth->isAdmin() ? '/admin' : '/mi-cuenta';

                return $this->redirect($this->auth->consumeIntendedUrl() ?? $destino);
            }

            $message = (string) $this->auth->failureMessage();
            $left = $this->accounts->attemptsLeft($identifier);

            if ($this->auth->failureReason() === Auth::FAILURE_INVALID && $left === 1) {
                $message .= ' Te queda un intento antes de que la cuenta se bloquee temporalmente.';
            }

            $errors['email'][] = $message;
        }

        return $this->loginFailed($request, $errors, $identifier);
    }

    public function showRegister(Request $request): Response
    {
        // Igual que en el ingreso: la sesión del panel no impide ver esta puerta.
        if ($this->auth->check() && !$this->auth->isAdmin()) {
            return $this->redirect('/mi-cuenta');
        }

        return $this->respond('auth.register', $this->pageData($this->composer->chrome() + [
            'title' => 'Crear cuenta | ' . (string) config('app.name'),
            'metaDescription' => 'Crea tu cuenta para acceder a los cursos y a los recursos gratuitos.',
            'bodyClass' => 'account-page',
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => [],
            'errors' => [],
            'message' => '',
        ]));
    }

    public function register(Request $request): Response
    {
        $result = $this->accounts->register($request->all());

        if ($result->succeeded()) {
            return $this->redirect('/mi-cuenta');
        }

        return $this->respond('auth.register', $this->pageData($this->composer->chrome() + [
            'title' => 'Crear cuenta | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => $result->values(),
            'errors' => $result->errors(),
            'message' => $result->firstError() ?? '',
        ]), 'layouts.public', 422);
    }

    /**
     * Cierre de sesión. Se responde con un redireccionamiento y con las cabeceras
     * que impiden que el navegador recupere la pantalla privada desde su caché.
     */
    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return $this->redirect('/')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private function loginFailed(Request $request, array $errors, string $email): Response
    {
        return $this->respond('auth.login', $this->pageData($this->composer->chrome() + [
            'title' => 'Iniciar sesión | ' . (string) config('app.name'),
            'bodyClass' => 'account-page',
            'values' => ['email' => $email],
            'errors' => $errors,
            'message' => '',
        ]), 'layouts.public', 422);
    }
}
