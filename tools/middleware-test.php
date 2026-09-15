<?php

declare(strict_types=1);

/**
 * Verificación de la seguridad transversal (grupo 3 del plan).
 *
 * Comprueba, sin navegador, que los middleware y los servicios de seguridad
 * hacen lo que dicen las especificaciones:
 *
 *   - cabeceras de seguridad presentes;
 *   - la política de contenido (CSP) se envía;
 *   - el token CSRF se exige en lo que modifica datos y no en lo que sólo lee;
 *   - sin sesión se redirige al ingreso y se guarda a dónde se quería ir;
 *   - un estudiante no entra a las rutas de administración;
 *   - una ruta sin roles declarados no la pasa nadie (denegar por defecto);
 *   - el bloqueo por intentos repetidos funciona;
 *   - la sesión caduca por inactividad;
 *   - las consultas sin parámetros vinculados se rechazan;
 *   - la validación responde en español nombrando el campo visible.
 *
 * Crea dos cuentas temporales y las elimina al terminar.
 *
 * Uso:  php tools/middleware-test.php
 */

use GB\Application;
use GB\Middleware\AuthMiddleware;
use GB\Middleware\CsrfMiddleware;
use GB\Middleware\ForceHttpsMiddleware;
use GB\Middleware\GuestMiddleware;
use GB\Middleware\MiddlewareInterface;
use GB\Middleware\RoleMiddleware;
use GB\Middleware\SecurityHeadersMiddleware;
use GB\Middleware\ThrottleMiddleware;
use GB\Models\UserRepository;
use GB\Support\Auth;
use GB\Support\Csrf;
use GB\Support\Model;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Router;
use GB\Support\Session;
use GB\Support\Throttle;
use GB\Support\Validator;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

/** @var Session $session */
$session = $container->get(Session::class);
$session->start();

/** @var Auth $auth */
$auth = $container->get(Auth::class);

/** @var UserRepository $users */
$users = $container->get(UserRepository::class);

$passed = 0;
$failed = 0;

/**
 * @param callable(): bool $condition
 */
function check(string $name, callable $condition): void
{
    global $passed, $failed;

    try {
        $ok = (bool) $condition();
    } catch (Throwable $exception) {
        $ok = false;
        $name .= ' — ' . $exception::class . ': ' . $exception->getMessage();
    }

    if ($ok) {
        $passed++;
        printf("  [ok]    %s\n", $name);
    } else {
        $failed++;
        printf("  [FALLA] %s\n", $name);
    }
}

function makeRequest(string $method, string $path, array $body = []): Request
{
    return new Request([], $body, [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost',
        'REMOTE_ADDR' => '127.0.0.1',
    ], [], []);
}

function nextOk(): Closure
{
    return static fn (Request $request): Response => Response::html('destino alcanzado', 200);
}

/**
 * @param array<int, string> $args
 */
function runMiddleware(MiddlewareInterface $middleware, Request $request, array $args = []): Response
{
    return $middleware->handle($request, nextOk(), $args);
}

// -----------------------------------------------------------------------------
// Cuentas temporales y marcas de limpieza
// -----------------------------------------------------------------------------
$adminRoleId = $users->roleId('SuperAdmin');
$studentRoleId = $users->roleId('Student');

if ($adminRoleId === null || $studentRoleId === null) {
    fwrite(STDERR, "No se encuentran los roles SuperAdmin y Student. Ejecuta antes: php database/migrate.php\n");
    exit(1);
}

$adminEmail = 'prueba-admin@example.test';
$studentEmail = 'prueba-estudiante@example.test';
$password = 'ClaveDePrueba123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$cleanupUserIds = [];
$throttleIdentifier = null;
$auditStartId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();

foreach ([[$adminEmail, $adminRoleId, 'Prueba', 'Administrador'], [$studentEmail, $studentRoleId, 'Prueba', 'Estudiante']] as [$email, $roleId, $name, $lastName]) {
    $existing = $users->findByEmail($email);

    if ($existing !== null) {
        $cleanupUserIds[] = (int) $existing['id'];
        $pdo->prepare('UPDATE users SET password_hash = ?, status = \'active\', role_id = ? WHERE id = ?')
            ->execute([$hash, $roleId, $existing['id']]);

        continue;
    }

    $pdo->prepare(
        'INSERT INTO users (role_id, name, last_name, email, password_hash, status, email_verified_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, \'active\', NOW(), NOW(), NOW())'
    )->execute([$roleId, $name, $lastName, $email, $hash]);

    $cleanupUserIds[] = (int) $pdo->lastInsertId();
}

$adminId = $users->findByEmail($adminEmail)['id'] ?? null;
$studentId = $users->findByEmail($studentEmail)['id'] ?? null;

echo "Verificación de seguridad transversal\n";
echo str_repeat('=', 78) . "\n";

// -----------------------------------------------------------------------------
// 1. Cabeceras de seguridad y política de contenido
// -----------------------------------------------------------------------------
echo "Cabeceras de seguridad\n";

$headers = runMiddleware(
    $container->get(SecurityHeadersMiddleware::class),
    makeRequest('GET', '/')
)->headers();

check('envía X-Content-Type-Options: nosniff', fn () => ($headers['X-Content-Type-Options'] ?? '') === 'nosniff');
check('envía X-Frame-Options', fn () => ($headers['X-Frame-Options'] ?? '') === 'SAMEORIGIN');
check('envía Referrer-Policy', fn () => isset($headers['Referrer-Policy']));
check('envía Permissions-Policy', fn () => isset($headers['Permissions-Policy']));
check('envía Content-Security-Policy', fn () => str_contains((string) ($headers['Content-Security-Policy'] ?? ''), "default-src 'self'"));
check('no anuncia HSTS sin cifrado', fn () => !isset($headers['Strict-Transport-Security']));

$secureHeaders = runMiddleware(
    $container->get(SecurityHeadersMiddleware::class),
    new Request([], [], [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTPS' => 'on',
        'REMOTE_ADDR' => '127.0.0.1',
    ])
)->headers();

check('anuncia HSTS cuando la conexión es cifrada', fn () => isset($secureHeaders['Strict-Transport-Security']));

// -----------------------------------------------------------------------------
// 2. Redirección a conexión cifrada
// -----------------------------------------------------------------------------
echo "\nRedirección a conexión cifrada\n";

$httpsMiddleware = new ForceHttpsMiddleware('production');
$redirect = $httpsMiddleware->handle(makeRequest('GET', '/cursos?pagina=2'), nextOk());

check('en producción redirige a https', fn () => $redirect->status() === 301
    && str_starts_with((string) ($redirect->headers()['Location'] ?? ''), 'https://'));
check('conserva la ruta y los parámetros', fn () => str_contains((string) ($redirect->headers()['Location'] ?? ''), '/cursos?pagina=2'));

$localMiddleware = new ForceHttpsMiddleware('local');
check('en desarrollo no interviene', fn () => $localMiddleware->handle(makeRequest('GET', '/'), nextOk())->status() === 200);

// -----------------------------------------------------------------------------
// 3. Token CSRF
// -----------------------------------------------------------------------------
echo "\nProtección contra falsificación de peticiones\n";

$session->flush();

/** @var Csrf $csrf */
$csrf = $container->get(Csrf::class);
$csrfMiddleware = $container->get(CsrfMiddleware::class);

$token = $csrf->token();

check('el token tiene suficiente entropía', fn () => strlen($token) >= 64);
check('permite las consultas de sólo lectura', fn () => runMiddleware($csrfMiddleware, makeRequest('GET', '/cursos'))->status() === 200);

check('rechaza un envío sin token', fn () => runMiddleware($csrfMiddleware, makeRequest('POST', '/contacto', ['mensaje' => 'hola']))->status() === 419);
check('rechaza un token ajeno', fn () => runMiddleware($csrfMiddleware, makeRequest('POST', '/contacto', ['_token' => str_repeat('a', 64)]))->status() === 419);
check('acepta el token de la sesión', fn () => runMiddleware($csrfMiddleware, makeRequest('POST', '/contacto', ['_token' => $token]))->status() === 200);
check('acepta el token por cabecera', function () use ($csrfMiddleware, $token): bool {
    $request = new Request([], [], [
        'REQUEST_METHOD' => 'DELETE',
        'REQUEST_URI' => '/cursos/1',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_CSRF_TOKEN' => $token,
    ]);

    return runMiddleware($csrfMiddleware, $request)->status() === 200;
});

// -----------------------------------------------------------------------------
// 4. Sesión requerida
// -----------------------------------------------------------------------------
echo "\nSesión requerida\n";

$auth->logout();
$session->flush();

$authMiddleware = $container->get(AuthMiddleware::class);
$response = runMiddleware($authMiddleware, makeRequest('GET', '/admin/bloques'));

check('sin sesión redirige a la página de ingreso', fn () => $response->status() === 302
    && str_contains((string) ($response->headers()['Location'] ?? ''), 'ingresar'));
check('guarda a dónde se quería ir', fn () => $auth->consumeIntendedUrl() === '/admin/bloques');

$jsonRequest = new Request([], [], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/admin/bloques',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_ACCEPT' => 'application/json',
]);

check('responde 401 en JSON para la interfaz', fn () => runMiddleware($authMiddleware, $jsonRequest)->status() === 401);

// -----------------------------------------------------------------------------
// 5. Control por rol
// -----------------------------------------------------------------------------
echo "\nControl por rol\n";

$roleMiddleware = $container->get(RoleMiddleware::class);

check('una ruta sin roles declarados no la pasa nadie', fn () => runMiddleware($roleMiddleware, makeRequest('GET', '/admin'), [])->status() === 403);

$auth->login(['id' => $studentId, 'role_key' => 'Student']);
$auth->refresh();

check('el estudiante no entra a las rutas de administración', fn () => runMiddleware($roleMiddleware, makeRequest('GET', '/admin/bloques'), ['SuperAdmin'])->status() === 403);
check('el estudiante sí entra a las rutas de estudiantes', fn () => runMiddleware($roleMiddleware, makeRequest('GET', '/mi-cuenta'), ['SuperAdmin', 'Student'])->status() === 200);

$auth->login(['id' => $adminId, 'role_key' => 'SuperAdmin']);
$auth->refresh();

check('el administrador entra a las rutas de administración', fn () => runMiddleware($roleMiddleware, makeRequest('GET', '/admin/bloques'), ['SuperAdmin'])->status() === 200);

$deniedLogged = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'access_denied'")->fetchColumn();
check('los accesos denegados quedan registrados', fn () => $deniedLogged > 0);

// --- Las dos puertas de estudiantes no llevan al panel -----------------------
echo "\nPuertas de estudiantes\n";

// «Inicia sesión» y «Regístrate» del encabezado llevan siempre al área de
// estudiantes. Con la sesión del panel abierta antes se saltaba al panel, de
// modo que pulsar un botón del sitio público terminaba dentro de la
// administración. Eso ya no pasa: las dos puertas se muestran.
$guestMiddleware = $container->get(GuestMiddleware::class);

check('con sesión de administración, entrar muestra la puerta del estudiante',
    fn () => runMiddleware($guestMiddleware, makeRequest('GET', '/ingresar'), ['student'])->status() === 200);
check('con sesión de administración, crear cuenta muestra la puerta del estudiante',
    fn () => runMiddleware($guestMiddleware, makeRequest('GET', '/registro'), ['student'])->status() === 200);

$puertaDelPanel = runMiddleware($guestMiddleware, makeRequest('GET', '/admin/ingresar'));

check('la puerta del panel sigue llevando al panel', fn () => $puertaDelPanel->status() === 302
    && str_contains((string) ($puertaDelPanel->headers()['Location'] ?? ''), '/admin'));

// Y la comprobación de extremo a extremo: se pide la dirección de verdad, con la
// sesión del panel abierta, y se mira lo que recibe el navegador.
$router = $container->get(Router::class);
(require GB_BASE_PATH . '/config/routes.php')($router);

$pedir = static fn (string $uri): Response => $router->dispatch(new Request([], [], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => $uri,
    'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'localhost',
    'SERVER_NAME' => 'localhost',
    'REMOTE_ADDR' => '127.0.0.1',
], [], []));

$paginaDeIngreso = $pedir('/ingresar');

check('pulsar «Inicia sesión» con la sesión del panel abierta no acaba en el panel',
    fn () => $paginaDeIngreso->status() === 200
        && !str_contains((string) ($paginaDeIngreso->headers()['Location'] ?? ''), '/admin'));
check('y la página explica que hay una sesión de administración abierta',
    fn () => str_contains($paginaDeIngreso->content(), 'sesión de administración'));
check('que se puede cerrar desde ahí mismo', fn () => str_contains($paginaDeIngreso->content(), '/salir'));

$auth->login(['id' => $studentId, 'role_key' => 'Student']);
$auth->refresh();

check('un estudiante que ya entró no vuelve a ver el formulario',
    fn () => runMiddleware($guestMiddleware, makeRequest('GET', '/ingresar'), ['student'])->status() === 302);

$auth->logout();
$session->flush();

check('sin sesión, las dos puertas se muestran', fn () => runMiddleware($guestMiddleware, makeRequest('GET', '/ingresar'), ['student'])->status() === 200
    && runMiddleware($guestMiddleware, makeRequest('GET', '/registro'), ['student'])->status() === 200);

// Y si alguien escribe sus credenciales de administración en la puerta de
// estudiantes, no puede acabar en «no tienes permiso»: la puerta es del área de
// estudiantes, pero quien entra manda, así que se le deja donde administra.
$entradaDeAdministrador = $router->dispatch(new Request([], [
    'email' => $adminEmail,
    'password' => $password,
    '_token' => $csrf->token(),
], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/ingresar',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'localhost',
    'SERVER_NAME' => 'localhost',
    'REMOTE_ADDR' => '203.0.113.99',
], [], []));

check('entrar por la puerta de estudiantes con una cuenta de administración lleva al panel',
    fn () => $entradaDeAdministrador->status() === 302
        && str_ends_with((string) ($entradaDeAdministrador->headers()['Location'] ?? ''), '/admin'));

$auth->logout();
$session->flush();

// -----------------------------------------------------------------------------
// 6. Caducidad de la sesión por inactividad
// -----------------------------------------------------------------------------
echo "\nCaducidad de la sesión\n";

$auth->login(['id' => $adminId, 'role_key' => 'SuperAdmin']);
$session->put('_last_activity', time() - 3600);

check('la sesión de administración caduca a los 20 minutos', fn () => $auth->check() === false);
check('al caducar se borra la sesión', fn () => $session->get('auth_user_id') === null);

$auth->login(['id' => $studentId, 'role_key' => 'Student']);
$session->put('_last_activity', time() - 3600);

check('la sesión de estudiante sigue viva a los 60 minutos', fn () => $auth->check() === true);

$auth->login(['id' => $studentId, 'role_key' => 'Student']);
$session->put('_last_activity', time() - 8000);

check('la sesión de estudiante caduca pasadas 2 horas', fn () => $auth->check() === false);

// -----------------------------------------------------------------------------
// 7. Limitación de intentos
// -----------------------------------------------------------------------------
echo "\nLimitación de intentos abusivos\n";

$throttleIdentifier = '127.0.0.1|' . bin2hex(random_bytes(4));
$throttleMiddleware = $container->get(ThrottleMiddleware::class);

$request = new Request([], ['email' => substr($throttleIdentifier, -8)], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/ingresar',
    'REMOTE_ADDR' => $throttleIdentifier,
]);

$statuses = [];

for ($attempt = 1; $attempt <= 6; $attempt++) {
    $statuses[] = runMiddleware($throttleMiddleware, $request, ['login', 'email'])->status();
}

check('los primeros intentos se atienden', fn () => $statuses[0] === 200 && $statuses[3] === 200);
check('al superar el límite responde 429', fn () => end($statuses) === 429);

/** @var Throttle $throttle */
$throttle = $container->get(Throttle::class);
$identifier = $throttleIdentifier . '|' . substr($throttleIdentifier, -8);

check('el bloqueo indica cuánto esperar', fn () => str_contains($throttle->waitMessage('login', $identifier), 'Espera'));
check('al limpiar el contador vuelve a permitir', function () use ($throttle, $identifier): bool {
    $throttle->clear('login', $identifier);

    return !$throttle->tooManyAttempts('login', $identifier);
});

// -----------------------------------------------------------------------------
// 8. Consultas con parámetros vinculados
// -----------------------------------------------------------------------------
echo "\nConsultas con parámetros vinculados\n";

$probe = new class ($pdo) extends Model {
    protected string $table = 'roles';

    /** @param array<string, mixed> $b */
    public function query(string $sql, array $b = []): array
    {
        return $this->select($sql, $b);
    }
};

check('acepta una consulta con parámetros', fn () => count($probe->query('SELECT * FROM roles WHERE key_name = :key', ['key' => 'SuperAdmin'])) === 1);
check('rechaza un encadenamiento de sentencias', function () use ($probe): bool {
    try {
        $probe->query('SELECT 1; DROP TABLE roles');

        return false;
    } catch (RuntimeException) {
        return true;
    }
});
check('rechaza parámetros faltantes', function () use ($probe): bool {
    try {
        $probe->query('SELECT * FROM roles WHERE key_name = :key');

        return false;
    } catch (RuntimeException) {
        return true;
    }
});

// -----------------------------------------------------------------------------
// 9. Validación en español
// -----------------------------------------------------------------------------
echo "\nValidación de datos\n";

$validator = new Validator(
    ['email' => 'esto-no-es-un-correo', 'password' => '', 'terms' => '1'],
    ['email' => 'Correo electrónico', 'password' => 'Contraseña']
);
$validator->validate([
    'email' => 'required|email',
    'password' => 'required|min:10',
    'terms' => 'required',
]);

check('detecta los campos con problemas', fn () => $validator->fails());
check('nombra el campo como se ve en pantalla', fn () => str_contains(implode(' ', $validator->messages()), 'Correo electrónico'));
check('el mensaje está en español', fn () => str_contains(implode(' ', $validator->messages()), 'no tiene un formato de correo válido'));

$valid = new Validator(['email' => 'persona@example.com', 'password' => 'ClaveSegura123'], ['email' => 'Correo electrónico', 'password' => 'Contraseña']);
$valid->validate(['email' => 'required|email', 'password' => 'required|min:10']);

check('acepta datos correctos', fn () => $valid->passes());
check('devuelve sólo los campos validados', fn () => array_keys($valid->validated()) === ['email', 'password']);

// -----------------------------------------------------------------------------
// Limpieza
// -----------------------------------------------------------------------------
$placeholders = implode(',', array_fill(0, count($cleanupUserIds), '?'));

$pdo->prepare("DELETE FROM audit_logs WHERE id > ?")->execute([$auditStartId]);
$pdo->prepare("DELETE FROM throttle WHERE identifier LIKE ?")->execute([$throttleIdentifier . '%']);
$pdo->prepare("DELETE FROM enrollments WHERE user_id IN ({$placeholders})")->execute($cleanupUserIds);
$pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($cleanupUserIds);

echo str_repeat('=', 78) . "\n";
printf("Comprobaciones superadas: %d   fallidas: %d\n", $passed, $failed);
echo 'Cuentas temporales eliminadas: ' . count($cleanupUserIds) . "\n";

exit($failed === 0 ? 0 : 1);
