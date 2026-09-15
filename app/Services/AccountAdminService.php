<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\UserRepository;
use GB\Support\Audit;
use GB\Support\FormResult;
use GB\Support\PasswordPolicy;
use GB\Support\Validator;
use Throwable;

/**
 * Cuentas de acceso y sus roles.
 *
 * La regla que da sentido a este servicio: el panel nunca puede quedarse sin
 * administradores. Si la última cuenta capaz de entrar al panel se desactiva o
 * se le quita el rol, nadie podría volver a arreglarlo desde la aplicación.
 *
 * Además, nadie cambia su propio rol ni se desactiva a sí mismo: es la forma más
 * común de perder el acceso por un descuido, y no hay ninguna tarea legítima que
 * lo necesite.
 */
final class AccountAdminService
{
    public function __construct(
        private UserRepository $users,
        private Audit $audit,
        private PasswordPolicy $policy,
    ) {
    }

    /** @return array<int, array{id: int, key_name: string, label: string}> */
    public function roles(): array
    {
        return $this->users->roles();
    }

    public function policy(): PasswordPolicy
    {
        return $this->policy;
    }

    /**
     * Crea una cuenta desde el panel, con el rol que se indique.
     *
     * Es la vía para dar acceso a otra persona sin que tenga que registrarse por
     * su cuenta: quien administra crea la cuenta, el sistema genera una
     * contraseña temporal, se la entrega a la persona y el sistema le obliga a
     * cambiarla en el primer ingreso.
     *
     * La contraseña temporal se devuelve una sola vez, en el resultado, para
     * mostrarla en pantalla. No se guarda en ningún sitio en claro ni se puede
     * volver a consultar después: si se pierde, se crea otra cuenta o se cambia
     * la contraseña desde la pantalla de la propia persona.
     *
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $actorId): FormResult
    {
        $validator = new Validator($input, [
            'name' => 'Nombre',
            'last_name' => 'Apellidos',
            'email' => 'Correo electrónico',
            'role' => 'Rol',
        ]);

        $validator->validate([
            'name' => 'required|min:2|max:120',
            'last_name' => 'max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:SuperAdmin,Student',
        ]);

        if ($validator->fails()) {
            return FormResult::failed($validator->errors(), $input, 'Revisa los datos: ' . ($validator->firstError() ?? ''));
        }

        $data = $validator->validated();
        $email = mb_strtolower(trim((string) $data['email']));
        $roleKey = (string) $data['role'];

        if ($this->users->emailExists($email)) {
            return FormResult::failed(
                ['email' => ['Ya existe una cuenta con ese correo.']],
                $input,
                'Ya existe una cuenta con ese correo. Búscala en la lista para cambiarle el rol.'
            );
        }

        $roleId = $this->users->roleId($roleKey);

        if ($roleId === null) {
            return FormResult::failed(['role' => ['Ese rol no existe.']], $input, 'Ese rol no existe.');
        }

        $temporary = $this->temporaryPassword();

        $userId = $this->users->create([
            'role_id' => $roleId,
            'name' => (string) $data['name'],
            'last_name' => ($data['last_name'] ?? '') === '' ? null : (string) $data['last_name'],
            'email' => $email,
            'password_hash' => $this->policy->hash($temporary),
            'status' => 'active',
            // La primera contraseña es temporal: el sistema la hace cambiar al entrar.
            'must_change_password' => 1,
            'accepted_terms_at' => date('Y-m-d H:i:s'),
        ]);

        $this->record('account_created', $userId, sprintf(
            'Cuenta creada con el rol "%s" para %s',
            $roleKey,
            $email
        ));

        return FormResult::ok(
            ['email' => $email, 'user_id' => $userId],
            sprintf('Cuenta creada para %s.', $email),
            ['password' => $temporary]
        );
    }

    /**
     * Contraseña temporal que se entrega al crear una cuenta.
     *
     * Se compone a mano para que cumpla los requisitos siempre —lleva letras y
     * números— y para poder dictarla por teléfono sin confundirse: sin letras
     * parecidas entre sí, sin caracteres que se confundan al leerlos.
     */
    private function temporaryPassword(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyz';
        $palabra = '';

        for ($i = 0; $i < 6; $i++) {
            $palabra .= $letras[random_int(0, strlen($letras) - 1)];
        }

        $numero = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $password = 'Gb-' . $palabra . $numero;

        // Si por lo que sea no cumpliera la política, se corrige aquí en lugar de
        // crear una cuenta con una contraseña que después no valdría.
        return $this->policy->passes($password) ? $password : 'Gb-temporal' . $numero . '1';
    }

    public function changeRole(int $targetId, string $roleKey, int $actorId): FormResult
    {
        $target = $this->users->findById($targetId);

        if ($target === null) {
            return FormResult::failed([], message: 'Esa cuenta no existe.');
        }

        if ($targetId === $actorId) {
            return FormResult::failed([], message: 'No puedes cambiar tu propio rol. Pídeselo a otra persona con acceso al panel.');
        }

        $roleId = $this->users->roleId($roleKey);

        if ($roleId === null) {
            return FormResult::failed([], message: 'Ese rol no existe.');
        }

        $wasAdmin = (string) $target['role_key'] === 'SuperAdmin';
        $isActive = (string) $target['status'] === 'active';

        if ($wasAdmin && $roleKey !== 'SuperAdmin' && $isActive && $this->users->countActiveAdmins() <= 1) {
            return FormResult::failed([], message:
                'Esta es la última cuenta de administración activa. Si le quitas el rol, nadie podría '
                . 'volver a entrar al panel. Crea otra cuenta de administración antes de hacerlo.');
        }

        $this->users->setRole($targetId, $roleId);
        $this->record('role_changed', $targetId, sprintf(
            'Rol cambiado a "%s" para %s',
            $roleKey,
            (string) $target['email']
        ));

        return FormResult::ok([], 'Rol actualizado.');
    }

    public function changeStatus(int $targetId, string $status, int $actorId): FormResult
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return FormResult::failed([], message: 'Ese estado no es válido.');
        }

        $target = $this->users->findById($targetId);

        if ($target === null) {
            return FormResult::failed([], message: 'Esa cuenta no existe.');
        }

        if ($targetId === $actorId) {
            return FormResult::failed([], message: 'No puedes desactivar tu propia cuenta.');
        }

        $isAdmin = (string) $target['role_key'] === 'SuperAdmin';
        $wouldLoseAdmin = $isAdmin && $status === 'inactive';

        if ($wouldLoseAdmin && $this->users->countActiveAdmins() <= 1) {
            return FormResult::failed([], message:
                'Esta es la última cuenta de administración activa. Si la desactivas, nadie podría '
                . 'volver a entrar al panel.');
        }

        $this->users->setStatus($targetId, $status);
        $this->record('status_changed', $targetId, sprintf(
            'Cuenta de %s %s',
            (string) $target['email'],
            $status === 'active' ? 'activada' : 'desactivada'
        ));

        return FormResult::ok([], $status === 'active' ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }

    private function record(string $action, int $userId, string $summary): void
    {
        try {
            $this->audit->log(action: $action, entityType: 'user', entityId: $userId, summary: $summary);
        } catch (Throwable) {
            // La auditoría es informativa: el cambio ya está hecho.
        }
    }
}
