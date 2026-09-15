<?php

declare(strict_types=1);

/**
 * Crea la cuenta inicial de superadministración.
 *
 * La contraseña nunca se escribe en el código ni en el historial de la consola:
 * se recibe por la variable de entorno GB_ADMIN_PASSWORD o se pide de forma
 * oculta. La cuenta queda con cambio de contraseña obligatorio en el primer
 * ingreso.
 *
 * Uso:
 *   php tools/create-admin.php --status
 *   php tools/create-admin.php --email=gerencia@ejemplo.com --name="Ana"
 *
 * En PowerShell, para no dejar la contraseña escrita en el historial:
 *   $env:GB_ADMIN_PASSWORD = Read-Host -AsSecureString | ConvertFrom-SecureString -AsPlainText
 *   php tools/create-admin.php --email=...
 */

use GB\Application;
use GB\Models\UserRepository;
use GB\Support\PasswordPolicy;

require dirname(__DIR__) . '/app/bootstrap.php';

$options = [];

foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/i', $argument, $matches) === 1) {
        $options[strtolower($matches[1])] = $matches[2] ?? '1';
    }
}

$container = Application::boot();
$users = $container->get(UserRepository::class);
$policy = $container->get(PasswordPolicy::class);

$existingAdmins = $users->countByRole('SuperAdmin');

if (isset($options['status'])) {
    echo $existingAdmins > 0
        ? "Hay {$existingAdmins} cuenta(s) de superadministración activas.\n"
        : "Todavía no existe ninguna cuenta de superadministración.\n";

    exit($existingAdmins > 0 ? 0 : 1);
}

if ($existingAdmins > 0 && !isset($options['force'])) {
    fwrite(STDERR, "Ya existe una cuenta de superadministración. Usa --force si necesitas crear otra.\n");
    exit(1);
}

$email = mb_strtolower(trim((string) ($options['email'] ?? '')));
$name = trim((string) ($options['name'] ?? ''));

if ($email === '' || $name === '') {
    fwrite(STDERR, "Faltan datos. Indica --email y --name.\n");
    exit(1);
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "El correo indicado no tiene un formato válido.\n");
    exit(1);
}

if ($users->emailExists($email)) {
    fwrite(STDERR, "Ya existe una cuenta con ese correo.\n");
    exit(1);
}

$password = (string) getenv('GB_ADMIN_PASSWORD');

if ($password === '') {
    fwrite(STDOUT, 'Contraseña para ' . $email . ': ');
    $password = readPasswordFromConsole();

    if ($password === '') {
        fwrite(STDERR, "\nNo se recibió ninguna contraseña. No se creó nada.\n");
        exit(1);
    }

    fwrite(STDOUT, "\n");
}

if (!$policy->passes($password, $email)) {
    fwrite(STDERR, $policy->message($password, $email) . "\n");
    exit(1);
}

$roleId = $users->roleId('SuperAdmin');

if ($roleId === null) {
    fwrite(STDERR, "No existe el rol SuperAdmin. Ejecuta primero las migraciones y las cargas iniciales.\n");
    exit(1);
}

$hash = $policy->hash($password);

$userId = $users->create([
    'role_id' => $roleId,
    'name' => $name,
    'email' => $email,
    'password_hash' => $hash,
    'status' => 'active',
    'accepted_terms_at' => date('Y-m-d H:i:s'),
]);

// La primera contraseña es temporal: el sistema exige cambiarla al entrar.
$users->setInitialPassword($userId, $hash);

echo "Cuenta de superadministración creada (id {$userId}).\n";
echo "Al iniciar sesión, el sistema pedirá cambiar la contraseña.\n";

/**
 * Lee la contraseña sin mostrarla en pantalla cuando el sistema lo permite.
 */
function readPasswordFromConsole(): string
{
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
        @shell_exec('stty -echo 2>/dev/null');
        $password = (string) fgets(STDIN);
        @shell_exec('stty echo 2>/dev/null');

        return trim($password);
    }

    // En Windows se avisa: la entrada queda visible. Para evitarlo, usa la
    // variable de entorno GB_ADMIN_PASSWORD.
    fwrite(STDOUT, '(la escritura será visible; puedes cancelar y usar GB_ADMIN_PASSWORD) ');

    return trim((string) fgets(STDIN));
}
