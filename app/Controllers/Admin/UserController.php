<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\UserRepository;
use GB\Services\AccountAdminService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Cuentas de acceso y roles.
 *
 * Muestra quién puede entrar al panel y quién puede entrar al área de
 * estudiantes. Los cambios de rol y de estado pasan por el servicio, que es
 * quien impide quedarse sin administradores.
 */
final class UserController extends Controller
{
    public function __construct(
        View $view,
        private UserRepository $users,
        private AccountAdminService $accounts,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $role = $request->string('role');
        $role = in_array($role, ['SuperAdmin', 'Student'], true) ? $role : '';

        return $this->respond('admin.users', $this->pageData([
            'title' => 'Usuarios y roles | Panel',
            'search' => $request->string('q'),
            'role' => $role,
            'roles' => $this->accounts->roles(),
            'requirements' => $this->accounts->policy()->requirements(),
            'accounts' => $this->users->accountsPage(
                $role,
                $request->string('q'),
                max(1, $request->int('page', 1)),
                (int) config('app.pagination.admin_per_page', 25)
            ),
            'activeAdmins' => $this->users->countActiveAdmins(),
            'currentId' => $this->auth->id(),
            'aviso' => $request->string('aviso'),
            'message' => $request->string('mensaje'),
        ]), 'layouts.admin');
    }

    /**
     * Crea una cuenta desde el panel con el rol indicado.
     *
     * La contraseña la genera el sistema y se muestra una sola vez, aquí. Quien
     * crea la cuenta se la entrega a la persona, y el sistema le obliga a
     * cambiarla en el primer ingreso: así nadie más conoce su contraseña.
     */
    public function create(Request $request): Response
    {
        $result = $this->accounts->create($request->all(), (int) ($this->auth->id() ?? 0));

        if (!$result->succeeded()) {
            return $this->redirectWith('/admin/usuarios', $result->message(), 'danger');
        }

        return $this->redirectWith('/admin/usuarios', sprintf(
            'Cuenta creada para %s. Contraseña temporal: %s — al entrar, el sistema le pedirá cambiarla.',
            (string) ($result->values()['email'] ?? ''),
            (string) ($result->secret('password') ?? '')
        ));
    }

    public function changeRole(Request $request): Response
    {
        $result = $this->accounts->changeRole(
            (int) $request->routeParam('id'),
            $request->string('role'),
            (int) ($this->auth->id() ?? 0)
        );

        return $this->redirectWith(
            '/admin/usuarios',
            $result->message(),
            $result->succeeded() ? 'success' : 'warning'
        );
    }

    public function changeStatus(Request $request): Response
    {
        $result = $this->accounts->changeStatus(
            (int) $request->routeParam('id'),
            $request->string('status'),
            (int) ($this->auth->id() ?? 0)
        );

        return $this->redirectWith(
            '/admin/usuarios',
            $result->message(),
            $result->succeeded() ? 'success' : 'warning'
        );
    }
}
