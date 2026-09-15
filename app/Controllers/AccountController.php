<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Services\AccountService;
use GB\Services\EnrollmentService;
use GB\Services\PageComposer;
use GB\Models\PaymentRepository;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Área personal del estudiante.
 *
 * Muestra los cursos en los que está inscrita la persona, su avance lección por
 * lección, los materiales de cada curso y sus datos. Todo sale de la sesión: no
 * hay ninguna pantalla que reciba un identificador de estudiante por la
 * dirección, para que nadie pueda ver el área de otra persona cambiando un
 * número.
 */
final class AccountController extends Controller
{
    public function __construct(
        View $view,
        private EnrollmentService $enrollments,
        private AccountService $accounts,
        private Auth $auth,
        private PageComposer $composer,
        private PaymentRepository $payments,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user() ?? [];

        return $this->accountView('account.dashboard', [
            'title' => 'Mi cuenta | ' . (string) config('app.name'),
            'courses' => $this->enrollments->myCourses((int) $user['id']),
            'purchases' => array_map(static function (array $purchase): array {
                $statusLabels = [
                    'pending' => 'Pendiente', 'paid' => 'Pagada', 'failed' => 'Fallida',
                    'refunded' => 'Reembolsada', 'cancelled' => 'Cancelada',
                ];
                $purchase['amount_label'] = number_format((float) $purchase['amount'], 2, ',', '.')
                    . ' ' . (string) $purchase['currency'];
                $purchase['status_label'] = $statusLabels[$purchase['status']] ?? (string) $purchase['status'];
                return $purchase;
            }, $this->payments->forUser((int) $user['id'])),
            'scripts' => $this->scripts(),
        ]);
    }

    public function profile(Request $request): Response
    {
        $user = $this->auth->user() ?? [];

        return $this->accountView('account.profile', [
            'title' => 'Mis datos | ' . (string) config('app.name'),
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => [
                'name' => (string) ($user['name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
            ],
            'errors' => [],
            'message' => '',
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $result = $this->accounts->updateProfile($userId, $request->all());

        return $this->accountView('account.profile', [
            'title' => 'Mis datos | ' . (string) config('app.name'),
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => $result->values(),
            'errors' => $result->errors(),
            'message' => $result->succeeded() ? $result->message() : ($result->firstError() ?? ''),
            'saved' => $result->succeeded(),
        ], $result->succeeded() ? 200 : 422);
    }

    public function changePassword(Request $request): Response
    {
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $result = $this->accounts->changePassword($userId, $request->all());

        return $this->accountView('account.profile', [
            'title' => 'Mis datos | ' . (string) config('app.name'),
            'requirements' => $this->accounts->policy()->requirements(),
            'values' => [],
            'errors' => $result->errors(),
            'message' => $result->succeeded() ? $result->message() : ($result->firstError() ?? ''),
            'passwordChanged' => $result->succeeded(),
        ], $result->succeeded() ? 200 : 422);
    }

    /**
     * Guion del marcado de lecciones. La dirección base se imprime desde el
     * servidor para que el guion funcione igual en la raíz del dominio que en
     * una subcarpeta, sin tener que adivinar la ruta.
     */
    private function scripts(): string
    {
        return '<script>window.GB_ACCOUNT_BASE = ' . json_encode(base_url(), JSON_UNESCAPED_SLASHES) . ';</script>'
            . '<script src="' . e(asset('js/account.js')) . '" defer></script>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function accountView(string $view, array $data, int $status = 200): Response
    {
        $response = $this->respond(
            $view,
            $this->pageData($this->composer->chrome() + $data + [
                'bodyClass' => 'account-page',
                'student' => $this->auth->user() ?? [],
            ]),
            'layouts.public',
            $status
        );

        // El área privada no se guarda en la caché del navegador: al cerrar
        // sesión, el botón "atrás" no puede devolver a esta pantalla.
        return $response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }
}
