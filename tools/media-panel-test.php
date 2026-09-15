<?php

declare(strict_types=1);

/**
 * Comprobación de la carga de imágenes desde el panel (tarea 11.8).
 *
 * `images-test.php` comprueba el procesamiento de la imagen. Este comprueba la
 * puerta por la que entra desde el panel: que sin sesión de administración no
 * se pueda subir nada, que la respuesta traiga la vista previa y las medidas
 * reales, que un archivo que no es imagen se rechace con un mensaje entendible
 * y que un destino inexistente no rompa nada.
 *
 * Crea una imagen de prueba y la borra al terminar. Uso:
 *   php tools/media-panel-test.php
 */

use GB\Application;
use GB\Models\MediaRepository;
use GB\Models\UserRepository;
use GB\Support\Auth;
use GB\Support\Csrf;
use GB\Support\Request;
use GB\Support\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();
$media = $container->get(MediaRepository::class);
$users = $container->get(UserRepository::class);
$pdo = $container->get(PDO::class);
$auth = $container->get(Auth::class);
$router = $container->get(Router::class);
$csrf = $container->get(Csrf::class);
(require GB_BASE_PATH . '/config/routes.php')($router);

$failures = 0;

$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? '  [ok]    ' : '  [FALLA] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$email = 'zz-imagen-prueba@example.invalid';
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$adminId = $users->create([
    'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Prueba', 'last_name' => 'Imágenes',
    'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
    'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
]);
$users->completeOnboarding($adminId);

$mediaBefore = (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
$createdIds = [];
$files = [];

$request = static function (string $method, string $uri, array $post, array $files, ?string $token) use ($router): \GB\Support\Response {
    $server = [
        'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1',
    ];

    $body = $post;

    if ($token !== null) {
        $body['_token'] = $token;
    }

    return $router->dispatch(new Request([], $body, $server, $files, []));
};

// Una imagen de 1600x900 para que quepa en el destino de portada
$source = sys_get_temp_dir() . '/zz-panel-image.png';
$text = sys_get_temp_dir() . '/zz-panel-image.txt';

$image = imagecreatetruecolor(1600, 900);
imagefilledrectangle($image, 0, 0, 1600, 900, imagecolorallocate($image, 20, 60, 120));
imagefilledrectangle($image, 0, 0, 400, 900, imagecolorallocate($image, 230, 120, 40));
imagepng($image, $source);
imagedestroy($image);

$upload = static fn (): array => [
    'file' => [
        'name' => 'portada.png',
        'type' => 'image/png',
        'tmp_name' => $source,
        'error' => UPLOAD_ERR_OK,
        'size' => (int) filesize($source),
    ],
];

echo 'Carga de imágenes desde el panel (tarea 11.8)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // 1. Sin sesión no se sube nada
    $response = $request('POST', '/admin/imagenes', ['profile' => 'course_cover'], $upload(), $csrf->token());
    $check('sin sesión de administración la carga se rechaza', $response->status() !== 200);
    $check('el contador de imágenes no cambió',
        (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn() === $mediaBefore);

    // 2. Con sesión, pero sin token de seguridad
    $auth->attempt($email, 'Temporal-2026!', '127.0.0.1', ['SuperAdmin']);
    $response = $request('POST', '/admin/imagenes', ['profile' => 'course_cover'], $upload(), null);
    $check('sin token de seguridad la carga se rechaza', $response->status() !== 200);

    // 3. Carga correcta
    $token = $csrf->token();
    $response = $request('POST', '/admin/imagenes', ['profile' => 'course_cover'], $upload(), $token);
    $payload = json_decode($response->content(), true);

    $check('la carga responde correctamente', $response->status() === 200 && ($payload['error'] ?? true) === false);
    $check('el mensaje explica que se ajustó al tamaño del lugar',
        str_contains((string) ($payload['message'] ?? ''), 'ajust'));
    $check('devuelve la dirección de la vista previa', ($payload['media']['url'] ?? '') !== '');
    $check('devuelve las medidas reales del recorte',
        ($payload['media']['width'] ?? 0) > 0 && ($payload['media']['height'] ?? 0) > 0);
    $check('el recorte respeta la proporción del destino (16:9)',
        abs(($payload['media']['width'] / max(1, $payload['media']['height'])) - (16 / 9)) < 0.02);

    $mediaId = (int) ($payload['media']['id'] ?? 0);
    $createdIds[] = $mediaId;

    $previewPath = (string) (parse_url((string) $payload['media']['url'], PHP_URL_PATH) ?? '');
    $relative = substr($previewPath, (int) strpos($previewPath, '/uploads/'));
    $preview = GB_PUBLIC_PATH . $relative;
    $check('la vista previa existe como archivo', is_file($preview));
    $check('la vista previa es una imagen', is_file($preview) && getimagesize($preview) !== false);

    $original = $pdo->query('SELECT disk, path, is_protected FROM media WHERE id = ' . $mediaId)->fetch(PDO::FETCH_ASSOC);
    $check('el original queda fuera del alcance web',
        is_array($original) && (string) $original['disk'] !== 'public');

    // 4. Un archivo que no es imagen
    file_put_contents($text, 'esto no es una imagen');
    $response = $request('POST', '/admin/imagenes', ['profile' => 'course_cover'], ['file' => [
        'name' => 'trampa.png', 'type' => 'image/png', 'tmp_name' => $text,
        'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($text),
    ]], $token);
    $payload = json_decode($response->content(), true);
    $check('un archivo que no es imagen se rechaza', $response->status() === 422);
    $check('con un mensaje entendible', str_contains(mb_strtolower((string) ($payload['message'] ?? '')), 'imagen'));

    // 5. Un destino inexistente
    $response = $request('POST', '/admin/imagenes', ['profile' => 'perfil-inexistente'], $upload(), $token);
    $check('un destino desconocido se rechaza sin romper el panel', $response->status() === 422);

    // 6. Reemplazo conservando el encuadre
    $upload2 = $upload();
    $upload2['file']['name'] = 'portada2.png';
    $response = $request('POST', '/admin/imagenes/' . $mediaId . '/reemplazar', ['profile' => 'course_cover'], $upload2, $token);
    $payload = json_decode($response->content(), true);
    $check('el reemplazo responde correctamente', $response->status() === 200 && ($payload['error'] ?? true) === false);
    $check('avisa de que conserva el encuadre', str_contains((string) ($payload['message'] ?? ''), 'encuadre'));
    $newId = (int) ($payload['media']['id'] ?? 0);
    $createdIds[] = $newId;

    // 7. Borrado del recurso
    $response = $request('DELETE', '/admin/imagenes/' . $newId, [], [], $token);
    $check('la imagen se puede eliminar', $response->status() === 200);
    $check('borrar dos veces avisa de que ya no existe',
        $request('DELETE', '/admin/imagenes/' . $newId, [], [], $token)->status() === 404);
} finally {
    // Limpieza: se borran los recursos creados y sus archivos
    foreach ($createdIds as $id) {
        $row = $pdo->query('SELECT id FROM media WHERE id = ' . $id)->fetchColumn();

        if ($row !== false) {
            $container->get(\GB\Services\ImagePipeline::class)->delete($id);
        }
    }

    $pdo->exec('DELETE FROM audit_logs WHERE entity_type = "user" AND entity_id = ' . $adminId);
    $pdo->exec('DELETE FROM users WHERE id = ' . $adminId);
    $pdo->exec('DELETE FROM throttle WHERE identifier = "0.0.0.0"');

    $session = GB_STORAGE_PATH . '/sessions/active/user-' . $adminId . '.json';

    if (is_file($session)) {
        unlink($session);
    }

    if (is_file($source)) {
        unlink($source);
    }

    if (is_file($text)) {
        unlink($text);
    }
}

$after = (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();

echo str_repeat('-', 70) . PHP_EOL;
echo 'Imágenes antes: ' . $mediaBefore . ' — después de la limpieza: ' . $after . PHP_EOL;
echo $failures === 0 && $after === $mediaBefore
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $after === $mediaBefore ? 0 : 1);
