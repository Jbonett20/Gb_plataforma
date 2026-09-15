<?php

declare(strict_types=1);

/**
 * Comprobación de la compra de extremo a extremo y del cierre del material de
 * pago (tareas 12.7 y 12.8).
 *
 * Reúne las dos mitades de la misma pregunta. Primero intenta entrar al material
 * de pago por todos los caminos imaginables sin haber pagado —sin enlace, con
 * enlace de otra persona, con enlace caducado, con el enlace manipulado, con una
 * compra a medias— y comprueba que ninguno funciona. Después recorre la compra
 * completa con una pasarela de pruebas: alta de la orden, notificación con firma
 * falsa (rechazada), notificación legítima, y sólo entonces el acceso.
 *
 * La pasarela de pruebas vive sólo dentro de este proceso: ni el archivo `.env`
 * ni la configuración del sitio se tocan, así que en producción la plataforma
 * sigue con las compras desactivadas.
 *
 * Crea datos de prueba y los borra al terminar. Uso:
 *   php tools/paid-access-test.php
 */

use GB\Application;
use GB\Models\CourseRepository;
use GB\Models\UserRepository;
use GB\Services\Payments\PaymentGateway;
use GB\Services\Payments\PaymentGatewayManager;
use GB\Services\PaymentService;
use GB\Support\Auth;
use GB\Support\Config;
use GB\Support\Csrf;
use GB\Support\Request;
use GB\Support\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();
$users = $container->get(UserRepository::class);
$courses = $container->get(CourseRepository::class);
$pdo = $container->get(PDO::class);
$auth = $container->get(Auth::class);
$router = $container->get(Router::class);
$csrf = $container->get(Csrf::class);
$tokens = $container->get(\GB\Services\ProtectedTokenService::class);
(require GB_BASE_PATH . '/config/routes.php')($router);

$failures = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '  [ok]    ' : '  [FALLA] ') . $label . PHP_EOL;

    if ($detail !== '') {
        echo '           ' . $detail . PHP_EOL;
    }

    if (!$ok) {
        $failures++;
    }
};

/**
 * Pasarela de pruebas. Firma y verifica con la misma clave secreta, igual que
 * haría una pasarela real, de modo que se puede comprobar que una notificación
 * sin firma válida se rechaza.
 */
$secret = 'secreto-de-pruebas-' . bin2hex(random_bytes(6));

$gateway = new class ($secret) implements PaymentGateway {
    public function __construct(private string $secret)
    {
    }

    public function key(): string
    {
        return 'pruebas';
    }

    public function configured(): bool
    {
        return true;
    }

    public function createCheckout(array $payment): array
    {
        return [
            'redirect_url' => 'https://pasarela-de-pruebas.invalid/pagar/' . (string) $payment['reference'],
            'external_id' => 'ext-' . (string) $payment['reference'],
        ];
    }

    public function verifyWebhook(array $payload, array $headers): ?array
    {
        $signature = (string) ($headers['X-Signature'] ?? '');
        $body = (string) json_encode($payload);

        if ($signature === '' || !hash_equals(hash_hmac('sha256', $body, $this->secret), $signature)) {
            return null;
        }

        return [
            'external_id' => (string) ($payload['id'] ?? ''),
            'reference' => (string) ($payload['reference'] ?? ''),
            'status' => (string) ($payload['status'] ?? ''),
            'type' => (string) ($payload['type'] ?? 'payment.updated'),
        ];
    }
};

// Se sustituye la pasarela activa sólo dentro de este proceso.
$container->singleton(
    PaymentGatewayManager::class,
    static fn (): PaymentGatewayManager => new PaymentGatewayManager(['pruebas' => $gateway], 'pruebas')
);

Config::set('app.auth.payments_enabled', true);

$paymentsEnabledBefore = Config::bool('app.auth.payments_enabled', false);

$state = [
    'audit' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn(),
];
$createdFile = null;

$studentEmail = 'zz-pago-' . bin2hex(random_bytes(4)) . '@example.invalid';
$studentId = 0;
$courseId = 0;
$lessonId = 0;

$studentServer = static fn (string $method, string $uri): array => [
    'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '203.0.113.9',
];

$send = static function (string $method, string $uri, array $query = [], array $headers = []) use ($router, $studentServer): \GB\Support\Response {
    return $router->dispatch(new Request($query, [], $studentServer($method, $uri) + $headers, [], []));
};

echo 'Compra de extremo a extremo y material de pago (tareas 12.7 y 12.8)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // La plataforma, tal como está desplegada, no vende todavía
    Config::set('app.auth.payments_enabled', false);
    $service = $container->get(PaymentService::class);
    $refused = $service->start(999999, 'curso-que-no-existe', new Request([], [], $studentServer('POST', '/'), [], []));
    $check('con las compras desactivadas no se puede iniciar ninguna compra', !$refused->succeeded(),
        $refused->message());
    Config::set('app.auth.payments_enabled', $paymentsEnabledBefore);

    // ------------------------------------------------------------- Escenario
    echo 'Preparación del escenario' . PHP_EOL;

    $studentId = $users->create([
        'role_id' => $users->roleId('Student'), 'name' => 'Alumno', 'last_name' => 'De Prueba',
        'email' => $studentEmail, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
        'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
    ]);

    $courseId = $courses->createAdmin([
        'title' => 'Curso de pago de prueba', 'slug' => 'zz-curso-de-pago', 'summary' => 'Resumen',
        'description' => 'Descripción del curso de pago de prueba.', 'access_type' => 'paid',
        'price' => '150000.00', 'currency' => 'COP', 'status' => 'published',
        'published_at' => date('Y-m-d H:i:s'), 'created_by' => null, 'updated_by' => null,
    ]);

    $moduleId = $container->get(\GB\Models\CourseModuleRepository::class)
        ->createForCourse($courseId, ['title' => 'Módulo', 'summary' => null, 'sort_order' => 1, 'status' => 'active']);

    $createdFile = 'templates/zz-guia-de-prueba.txt';
    $absolute = GB_STORAGE_PATH . '/protected/' . $createdFile;

    if (!is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0750, true);
    }

    $secretContent = 'Contenido protegido de prueba. ' . bin2hex(random_bytes(8));
    file_put_contents($absolute, $secretContent);

    $lessonId = $container->get(\GB\Models\LessonRepository::class)->createForModule($moduleId, [
        'title' => 'Guía de pago', 'summary' => 'Lección de prueba', 'content_type' => 'document',
        'protected_path' => $createdFile, 'duration_minutes' => 10, 'sort_order' => 1, 'status' => 'active',
    ]);

    $check('el escenario quedó creado',
        $studentId > 0 && $courseId > 0 && $lessonId > 0 && is_file($absolute));
    $check('el material de pago vive fuera del directorio público',
        !is_file(GB_PUBLIC_PATH . '/' . ltrim($createdFile, '/')) && !is_dir(GB_PUBLIC_PATH . '/protected'));

    // ------------------------------------------- 12.8: sin pago, sin acceso
    echo 'Sin pago confirmado no se entra' . PHP_EOL;

    $auth->attempt($studentEmail, 'Temporal-2026!', '203.0.113.9', ['Student']);
    $check('el alumno tiene sesión', $auth->check());

    $validToken = $tokens->issue($studentId, 'lesson', $lessonId, '203.0.113.9');

    $sinEnlace = $send('GET', '/contenido/lecciones/' . $lessonId);
    $check('sin enlace firmado no entrega el material', $sinEnlace->status() === 404
        && !str_contains($sinEnlace->content(), 'Contenido protegido'));

    $sinCompra = $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $validToken]);
    $check('con enlace firmado pero sin compra tampoco', $sinCompra->status() === 403
        && !str_contains($sinCompra->content(), 'Contenido protegido'));
    $check('el intento queda registrado en el historial de accesos',
        (int) $pdo->query('SELECT COUNT(*) FROM content_access_log WHERE user_id = ' . $studentId . ' AND delivered = 0')->fetchColumn() > 0);

    // Un enlace emitido para otra persona no sirve
    $otroId = $users->create([
        'role_id' => $users->roleId('Student'), 'name' => 'Otro', 'last_name' => 'Alumno',
        'email' => 'zz-otro-' . bin2hex(random_bytes(4)) . '@example.invalid',
        'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT), 'status' => 'active',
        'accepted_terms_at' => date('Y-m-d H:i:s'),
    ]);
    $ajeno = $tokens->issue($otroId, 'lesson', $lessonId, '203.0.113.9');
    $check('un enlace emitido para otra cuenta se rechaza',
        $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $ajeno])->status() === 403);

    $pdo->exec('DELETE FROM users WHERE id = ' . $otroId);

    // Enlace caducado
    $caducado = $tokens->issue($studentId, 'lesson', $lessonId, '203.0.113.9', -60);
    $check('un enlace caducado se rechaza',
        $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $caducado])->status() === 403);

    // Enlace manipulado
    $manipulado = substr($validToken, 0, -4) . 'aaaa';
    $check('un enlace manipulado se rechaza',
        $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $manipulado])->status() === 403);

    // Enlace abierto desde otra conexión
    $otraIp = $tokens->issue($studentId, 'lesson', $lessonId, '198.51.100.7');
    $check('un enlace abierto desde otra conexión se rechaza',
        $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $otraIp])->status() === 403);

    // Compra a medias: la orden existe, pero nadie ha pagado
    echo 'Con la compra a medias tampoco' . PHP_EOL;
    $checkout = $container->get(PaymentService::class)->start($studentId, 'zz-curso-de-pago', new Request([], [], $studentServer('POST', '/'), [], []));
    $check('se puede iniciar la compra en modo pruebas', $checkout->succeeded(),
        $checkout->succeeded() ? '' : $checkout->message());
    $reference = (string) ($checkout->values()['reference'] ?? '');
    $check('la orden queda pendiente y con su importe',
        (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE reference = ' . $pdo->quote($reference)
            . ' AND status = "pending" AND amount = 150000.00')->fetchColumn() === 1);
    $check('y el acceso sigue cerrado con la orden pendiente',
        $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $tokens->issue($studentId, 'lesson', $lessonId, '203.0.113.9')])->status() === 403);

    $retorno = $send('GET', '/pago/resultado', ['reference' => $reference]);
    $accesosTrasRetorno = (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId)->fetchColumn();
    $check('la página de retorno no concede nada por sí sola',
        $retorno->status() < 500 && $accesosTrasRetorno === 0);

    // --------------------------------- 12.7: notificación y acceso concedido
    echo 'La notificación decide: firma falsa contra firma legítima' . PHP_EOL;

    $eventId = 'evt-' . bin2hex(random_bytes(6));
    $body = (string) json_encode(['id' => $eventId, 'reference' => $reference, 'status' => 'APPROVED', 'type' => 'payment.updated']);

    /**
     * Envía una notificación a la dirección pública del webhook, con cuerpo crudo
     * y firma en la cabecera, tal como lo haría la pasarela.
     */
    $webhook = static function (string $body, ?string $signature) use ($router): \GB\Support\Response {
        return $router->dispatch(new Request(
            [],
            [],
            [
                'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/webhooks/pruebas', 'SCRIPT_NAME' => '/index.php',
                'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '203.0.113.50',
            ] + ($signature === null ? [] : ['HTTP_X_SIGNATURE' => $signature]),
            [],
            [],
            $body
        ));
    };

    $falsa = $webhook($body, null);
    $check('una notificación sin firma se rechaza', $falsa->status() === 401);
    $check('y no concede acceso',
        (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId . ' AND status = "active"')->fetchColumn() === 0);
    $check('la orden sigue pendiente',
        (string) $pdo->query('SELECT status FROM payments WHERE reference = ' . $pdo->quote($reference))->fetchColumn() === 'pending');

    $malaFirma = $webhook($body, str_repeat('c', 64));
    $check('una firma inventada también se rechaza', $malaFirma->status() === 401);
    $check('y sigue sin conceder acceso',
        (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId . ' AND status = "active"')->fetchColumn() === 0);

    // Con la firma correcta sí se concede
    $signature = hash_hmac('sha256', $body, $secret);
    $legitima = $webhook($body, $signature);

    $check('la notificación legítima se reconoce', $legitima->status() === 200, 'estado ' . $legitima->status());
    $check('la orden queda pagada',
        (string) $pdo->query('SELECT status FROM payments WHERE reference = ' . $pdo->quote($reference))->fetchColumn() === 'paid');
    $check('se concede el acceso al curso',
        (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId . ' AND course_id = ' . $courseId . ' AND status = "active"')->fetchColumn() === 1);

    // Ahora sí entrega el material
    $entrega = $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $tokens->issue($studentId, 'lesson', $lessonId, '203.0.113.9')]);
    $check('con el pago confirmado el material se entrega', $entrega->status() === 200);
    $check('y llega el contenido correcto', str_contains($entrega->content(), $secretContent));
    $check('la respuesta no invita a guardar el archivo',
        !str_contains(strtolower((string) ($entrega->headers()['Content-Disposition'] ?? '')), 'attachment'));
    $check('el acceso concedido queda registrado',
        (int) $pdo->query('SELECT COUNT(*) FROM content_access_log WHERE user_id = ' . $studentId . ' AND delivered = 1')->fetchColumn() > 0);

    // Notificación repetida: no duplica efectos
    $repetida = $webhook($body, $signature);
    $check('una notificación repetida se reconoce sin volver a actuar', $repetida->status() < 500);
    $check('y no duplica la inscripción',
        (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId . ' AND course_id = ' . $courseId)->fetchColumn() === 1);

    // Petición por rango, como hace un reproductor
    $rango = $router->dispatch(new Request(
        ['t' => $tokens->issue($studentId, 'lesson', $lessonId, '203.0.113.9')],
        [],
        [
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/contenido/lecciones/' . $lessonId, 'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_RANGE' => 'bytes=0-9',
        ],
        [],
        []
    ));
    $check('una petición por rango devuelve la parte pedida', in_array($rango->status(), [200, 206], true));

    // ----------------------------------------------- Reembolso y cierre de nuevo
    echo 'El reembolso retira el acceso y conserva el historial' . PHP_EOL;

    $paymentId = (int) $pdo->query('SELECT id FROM payments WHERE reference = ' . $pdo->quote($reference))->fetchColumn();
    $refund = $container->get(PaymentService::class)->refund($paymentId, null, 'Prueba de reembolso');

    $check('el reembolso se registra', $refund->succeeded(), $refund->message());
    $check('la compra queda marcada como reembolsada',
        (string) $pdo->query('SELECT status FROM payments WHERE id = ' . $paymentId)->fetchColumn() === 'refunded');
    $check('el historial de la compra se conserva',
        (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE id = ' . $paymentId)->fetchColumn() === 1);
    $check('el acceso deja de estar vigente',
        (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId
            . ' AND course_id = ' . $courseId . ' AND status = "active"')->fetchColumn() === 0);
    $check('el historial de la inscripción se conserva',
        (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE user_id = ' . $studentId
            . ' AND course_id = ' . $courseId)->fetchColumn() === 1);
    $check('y el material vuelve a estar cerrado',
        $send('GET', '/contenido/lecciones/' . $lessonId, ['t' => $tokens->issue($studentId, 'lesson', $lessonId, '203.0.113.9')])->status() === 403);
} finally {
    // Limpieza en orden de dependencias: primero lo que apunta a lo demás.
    $pdo->exec('DELETE FROM webhook_events');
    $pdo->exec('DELETE FROM lesson_progress WHERE lesson_id = ' . $lessonId);
    $pdo->exec('DELETE FROM content_access_log WHERE lesson_id = ' . $lessonId);
    $pdo->exec('DELETE FROM enrollments WHERE course_id = ' . $courseId);
    $pdo->exec('DELETE FROM payments WHERE course_id = ' . $courseId);
    $pdo->exec('DELETE FROM lessons WHERE id = ' . $lessonId);
    $pdo->exec('DELETE FROM course_modules WHERE course_id = ' . $courseId);
    $pdo->exec('DELETE FROM courses WHERE id = ' . $courseId);
    $pdo->exec('DELETE FROM users WHERE id IN (' . $studentId . ', ' . ($otroId ?? 0) . ')');
    $pdo->prepare('DELETE FROM audit_logs WHERE id > ?')->execute([$state['audit']]);
    $pdo->exec('DELETE FROM throttle WHERE identifier LIKE "203.0.113.9%"');

    foreach ([$studentId, $otroId ?? 0] as $id) {
        $session = GB_STORAGE_PATH . '/sessions/active/user-' . $id . '.json';

        if ($id > 0 && is_file($session)) {
            unlink($session);
        }
    }

    if ($createdFile !== null && is_file(GB_STORAGE_PATH . '/protected/' . $createdFile)) {
        unlink(GB_STORAGE_PATH . '/protected/' . $createdFile);
    }

    Config::set('app.auth.payments_enabled', false);
}

$leftovers = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email LIKE "zz-%"')->fetchColumn();
$fileLeft = $createdFile !== null && is_file(GB_STORAGE_PATH . '/protected/' . $createdFile);

echo str_repeat('-', 70) . PHP_EOL;
echo 'Registros de prueba restantes: ' . $leftovers . ' — archivo de prueba restante: ' . ($fileLeft ? 'sí' : 'no') . PHP_EOL;
echo $failures === 0 && $leftovers === 0 && !$fileLeft
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $leftovers === 0 && !$fileLeft ? 0 : 1);
