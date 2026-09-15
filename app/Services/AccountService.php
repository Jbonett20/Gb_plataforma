<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\PasswordResetRepository;
use GB\Models\UserRepository;
use GB\Support\Audit;
use GB\Support\Auth;
use GB\Support\FormResult;
use GB\Support\Mailer;
use GB\Support\PasswordPolicy;
use GB\Support\Validator;
use Throwable;

/**
 * Altas, ingreso, contraseñas y perfil.
 *
 * Los controladores sólo traducen peticiones a llamadas de esta clase; así las
 * mismas reglas valen para el sitio, para el panel y para los guiones de
 * comprobación, sin duplicar la lógica en tres sitios.
 *
 * Dos compromisos que se cumplen aquí y no en la vista:
 *   - la contraseña nunca sale de esta clase (ni en claro, ni en los datos que
 *     se devuelven para repintar el formulario, ni en los registros);
 *   - cuando un correo ya está registrado o no existe al pedir un
 *     restablecimiento, la persona recibe siempre la misma respuesta, para que
 *     nadie pueda averiguar qué correos tienen cuenta.
 */
final class AccountService
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRepository $resets,
        private Auth $auth,
        private Audit $audit,
        private Mailer $mailer,
        private PasswordPolicy $policy,
        private array $security = [],
    ) {
    }

    public function policy(): PasswordPolicy
    {
        return $this->policy;
    }

    /**
     * Si el envío de correo ya está activado. Las pantallas lo usan para no
     * prometer un mensaje que todavía no puede salir.
     */
    public function mailConfigured(): bool
    {
        return $this->mailer->configured();
    }

    /**
     * Crea una cuenta de estudiante y deja la sesión iniciada.
     *
     * @param array<string, mixed> $input
     */
    public function register(array $input): FormResult
    {
        $validator = new Validator($input, [
            'name' => 'Nombre',
            'last_name' => 'Apellidos',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'password' => 'Contraseña',
            'terms' => 'Aceptación de las condiciones',
        ]);

        $validator->validate([
            'name' => 'required|min:2|max:120',
            'last_name' => 'max:120',
            'email' => 'required|email|max:190',
            'phone' => 'max:40',
            'password' => 'required|max:200',
            'terms' => 'required',
        ]);

        if ($validator->fails()) {
            return FormResult::failed($validator->errors(), $input);
        }

        $email = mb_strtolower(trim((string) $validator->validated()['email']));
        $password = (string) $input['password'];
        $errors = [];

        // El correo duplicado se avisa de forma explícita: aquí sí conviene
        // decir que ya existe, porque la persona puede recuperar esa cuenta.
        if ($this->users->emailExists($email)) {
            $errors['email'][] = 'Ya existe una cuenta con este correo electrónico. '
                . 'Puedes iniciar sesión o recuperar tu contraseña.';
        }

        if (!$this->policy->passes($password, $email)) {
            $errors['password'][] = $this->policy->message($password, $email);
        }

        if ($errors !== []) {
            return FormResult::failed($errors, $input);
        }

        $roleId = $this->users->roleId('Student');

        if ($roleId === null) {
            return FormResult::failed(
                ['email' => ['El sistema todavía no está preparado para crear cuentas. Avisa al administrador.']],
                $input
            );
        }

        $now = date('Y-m-d H:i:s');

        $userId = $this->users->create([
            'role_id' => $roleId,
            'name' => (string) $validator->validated()['name'],
            'last_name' => $validator->validated()['last_name'] ?? null,
            'email' => $email,
            'password_hash' => $this->policy->hash($password),
            'phone' => $validator->validated()['phone'] ?? null,
            'status' => 'active',
            'accepted_terms_at' => $now,
        ]);

        $account = $this->users->findById($userId);

        if ($account !== null) {
            $this->auth->login($account);
        }

        $this->record('student_registered', sprintf('Nueva cuenta de estudiante: %s', $email), $userId);

        return FormResult::ok(
            ['email' => $email],
            'Tu cuenta quedó creada. Te damos la bienvenida.'
        );
    }

    /**
     * Cuántos minutos debe esperar una cuenta bloqueada por intentos fallidos.
     * Devuelve 0 cuando no está bloqueada.
     *
     * El bloqueo es de la CUENTA, no de la dirección de red: así un ataque
     * contra una cuenta concreta se frena sin castigar a quien comparte salida
     * a internet desde el mismo sitio.
     */
    public function lockoutRemaining(string $identifier): int
    {
        $account = $this->users->findByLoginIdentifier($identifier);

        if ($account === null) {
            return 0;
        }

        $until = $this->users->lockedUntil((int) $account['id']);

        if ($until === null) {
            return 0;
        }

        return max(1, (int) ceil((strtotime($until) - time()) / 60));
    }

    /**
     * Intentos fallidos que le quedan a una cuenta antes de bloquearse. Sirve
     * para avisar a tiempo en lugar de sorprender con el bloqueo.
     */
    public function attemptsLeft(string $identifier): int
    {
        $account = $this->users->findByLoginIdentifier($identifier);
        $allowed = (int) ($this->security['login_max_attempts'] ?? 5);

        if ($account === null) {
            return $allowed;
        }

        return max(0, $allowed - (int) ($account['failed_login_attempts'] ?? 0));
    }

    /**
     * Pide el enlace de restablecimiento.
     *
     * La respuesta es la misma exista o no la cuenta: así nadie puede usar este
     * formulario para averiguar qué correos están registrados.
     *
     * @param array<string, mixed> $input
     */
    public function requestPasswordReset(array $input): FormResult
    {
        // El texto es el mismo exista o no la cuenta. Cuando el envío de correo
        // todavía no está activado se dice lo que realmente ocurre en lugar de
        // prometer un mensaje que no va a llegar.
        $generic = $this->mailer->configured()
            ? 'Si ese correo tiene una cuenta, te enviamos un enlace para crear una contraseña nueva. '
                . 'Revisa también la carpeta de correo no deseado.'
            : 'Si ese correo tiene una cuenta, preparamos el enlace para crear una contraseña nueva. '
                . 'El envío de correo del sitio todavía no está activo, así que escríbenos y te lo hacemos llegar.';

        $validator = new Validator($input, ['email' => 'Correo electrónico']);
        $validator->validate(['email' => 'required|email|max:190']);

        if ($validator->fails()) {
            return FormResult::failed($validator->errors(), $input);
        }

        $email = mb_strtolower(trim((string) $validator->validated()['email']));
        $account = $this->users->findByEmail($email);

        if ($account === null || $account['status'] !== 'active') {
            // Se registra el intento sin destinatario y se responde lo mismo.
            $this->record('password_reset_requested', sprintf('Solicitud para un correo sin cuenta: %s', $email), null);

            return FormResult::ok(['email' => $email], $generic);
        }

        if ($this->resets->recentCount($email, 15) >= 3) {
            return FormResult::ok(
                ['email' => $email],
                'Ya se pidieron varios enlaces para este correo hace poco. Espera unos minutos antes de pedir otro.'
            );
        }

        $userId = (int) $account['id'];
        $issued = $this->resets->issue($userId, $email);
        $link = url('restablecer-contrasena/' . $issued['token']);

        $sent = $this->mailer->sendTemplate(
            $email,
            'Crea una contraseña nueva',
            'password_reset',
            [
                'name' => (string) $account['name'],
                'link' => $link,
                'minutes' => 60,
            ]
        );

        $this->record('password_reset_requested', $this->mailSummary($email, $sent, $userId), $userId);

        if (!$sent) {
            $this->resets->invalidateForUser($userId);

            return FormResult::failed(
                ['email' => ['No pudimos preparar el mensaje en este momento. Inténtalo más tarde '
                    . 'o escríbenos para restablecer tu contraseña.']],
                $input
            );
        }

        return FormResult::ok(['email' => $email], $generic);
    }

    /**
     * Redacta el rastro que queda en la auditoría. Distingue "se envió" de
     * "quedó guardado porque el correo aún no está activado": así el
     * administrador sabe dónde buscar el enlace.
     */
    private function mailSummary(string $email, bool $sent, int $userId): string
    {
        if (!$sent) {
            return sprintf('No se pudo preparar el mensaje para %s: %s', $email, (string) $this->mailer->lastError());
        }

        if ($this->mailer->configured()) {
            return sprintf('Enlace de restablecimiento enviado a %s', $email);
        }

        return sprintf(
            'Enlace de restablecimiento para %s guardado en storage/logs/mail (el correo saliente todavía no está activado). %s',
            $email,
            (string) $this->mailer->lastError()
        );
    }

    /**
     * Comprueba un enlace antes de mostrar el formulario de contraseña nueva.
     *
     * @return array<string, mixed>|null
     */
    public function findResetLink(string $token): ?array
    {
        return $this->resets->findUsable($token);
    }

    /**
     * Guarda la contraseña nueva usando el enlace recibido.
     *
     * @param array<string, mixed> $input
     */
    public function resetPassword(string $token, array $input): FormResult
    {
        $link = $this->resets->findUsable($token);

        if ($link === null) {
            return FormResult::failed([],
                message: 'Este enlace ya venció o se usó antes. Solicita uno nuevo para continuar.'
            );
        }

        $input['password_confirmation'] = $input['password_confirmation'] ?? '';
        $password = (string) ($input['password'] ?? '');

        $validator = new Validator($input, [
            'password' => 'Contraseña nueva',
            'password_confirmation' => 'Repetición de la contraseña',
        ]);
        $validator->validate(['password' => 'required|max:200']);

        $errors = $validator->errors();

        if (($input['password_confirmation'] ?? '') !== $password) {
            $errors['password_confirmation'][] = 'Las dos contraseñas no coinciden. Escríbelas iguales.';
        }

        $userId = (int) $link['user_id'];
        $account = $this->users->findById($userId);

        if ($account !== null && !$this->policy->passes($password, (string) $account['email'])) {
            $errors['password'][] = $this->policy->message($password, (string) $account['email']);
        }

        if ($errors !== []) {
            return FormResult::failed($errors, $input);
        }

        // El enlace se gasta antes de cambiar nada: si dos peticiones llegan a
        // la vez, sólo una consigue el cambio.
        if (!$this->resets->consume((int) $link['id'])) {
            return FormResult::failed([],
                message: 'Este enlace ya se había usado. Solicita uno nuevo para continuar.'
            );
        }

        $this->users->updatePassword($userId, $this->policy->hash($password));

        $account = $this->users->findById($userId);

        if ($account !== null) {
            $this->auth->login($account);
        }

        $this->record('password_reset_completed', 'Contraseña restablecida con un enlace temporal', $userId);

        return FormResult::ok([], 'Tu contraseña quedó actualizada y ya tienes la sesión iniciada.');
    }

    /**
     * Cambio de contraseña desde el área personal. Exige la contraseña actual:
     * así, quien encuentre una sesión abierta no puede apropiarse de la cuenta.
     *
     * @param array<string, mixed> $input
     */
    public function changePassword(int $userId, array $input): FormResult
    {
        $account = $this->users->findForAuthentication((string) ($this->users->findById($userId)['email'] ?? ''));

        if ($account === null) {
            return FormResult::failed([], message: 'No encontramos tu cuenta. Vuelve a iniciar sesión.');
        }

        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['password'] ?? '');

        $errors = [];

        if ($current === '') {
            $errors['current_password'][] = 'Escribe tu contraseña actual.';
        } elseif (!password_verify($current, (string) $account['password_hash'])) {
            $errors['current_password'][] = 'La contraseña actual no es correcta.';
        }

        if (!$this->policy->passes($new, (string) $account['email'])) {
            $errors['password'][] = $this->policy->message($new, (string) $account['email']);
        }

        if (($input['password_confirmation'] ?? '') !== $new) {
            $errors['password_confirmation'][] = 'Las dos contraseñas no coinciden. Escríbelas iguales.';
        }

        if ($errors !== []) {
            return FormResult::failed($errors, $input);
        }

        $this->users->updatePassword($userId, $this->policy->hash($new));
        $this->resets->invalidateForUser($userId);
        $this->record('password_changed', 'Contraseña cambiada desde el área personal', $userId);

        return FormResult::ok([], 'Tu contraseña quedó actualizada.');
    }

    /**
     * Datos del perfil. No incluye el correo si se pidió que no se pueda
     * cambiar: ese dato identifica la cuenta.
     *
     * @param array<string, mixed> $input
     */
    public function updateProfile(int $userId, array $input): FormResult
    {
        $validator = new Validator($input, [
            'name' => 'Nombre',
            'last_name' => 'Apellidos',
            'phone' => 'Teléfono',
        ]);

        $validator->validate([
            'name' => 'required|min:2|max:120',
            'last_name' => 'max:120',
            'phone' => 'max:40',
        ]);

        if ($validator->fails()) {
            return FormResult::failed($validator->errors(), $input);
        }

        $data = $validator->validated();

        $this->users->updateProfile($userId, [
            'name' => (string) $data['name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        $this->record('profile_updated', 'Datos personales actualizados', $userId);
        $this->auth->refresh();

        return FormResult::ok($data, 'Guardamos tus datos.');
    }

    /**
     * Anota en la auditoría sin que un fallo al anotar rompa la operación: quien
     * actuó y desde dónde los toma el registro de la propia sesión.
     */
    private function record(string $action, string $summary, ?int $userId): void
    {
        try {
            $this->audit->log(
                action: $action,
                entityType: 'user',
                entityId: $userId,
                summary: $summary
            );
        } catch (Throwable) {
            // La auditoría es informativa: la operación ya está hecha.
        }
    }
}
