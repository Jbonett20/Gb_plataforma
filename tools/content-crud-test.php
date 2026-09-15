<?php

declare(strict_types=1);

/**
 * Comprobación de las listas y la edición de contenido del panel (tarea 11.9).
 *
 * Recorre el camino completo de cada tipo de contenido tal como lo hace una
 * persona desde el panel: crear, ver en la lista, abrir para editar, cambiar,
 * publicar cuando corresponde y retirar. Incluye la regla que impide publicar un
 * curso sin lecciones, que es la que más se olvida.
 *
 * Crea datos de prueba y los borra al terminar. Uso:
 *   php tools/content-crud-test.php
 */

use GB\Application;
use GB\Models\CourseRepository;
use GB\Models\TemplateRepository;
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
$users = $container->get(UserRepository::class);
$courses = $container->get(CourseRepository::class);
$templates = $container->get(TemplateRepository::class);
$media = $container->get(\GB\Models\MediaRepository::class);
$pipeline = $container->get(\GB\Services\ImagePipeline::class);
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

$email = 'zz-contenido-prueba@example.invalid';
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$adminId = $users->create([
    'role_id' => $users->roleId('SuperAdmin'), 'name' => 'Prueba', 'last_name' => 'Contenido',
    'email' => $email, 'password_hash' => password_hash('Temporal-2026!', PASSWORD_DEFAULT),
    'status' => 'active', 'accepted_terms_at' => date('Y-m-d H:i:s'),
]);
$users->completeOnboarding($adminId);
$auth->attempt($email, 'Temporal-2026!', '127.0.0.1', ['SuperAdmin']);

$mediaBefore = (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
// Se anota hasta dónde llegaba la auditoría: la limpieza sólo puede borrar los
// registros creados por esta prueba, nunca el historial que ya existía.
$auditBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
$mediaIds = [];
$source = sys_get_temp_dir() . '/zz-contenido-imagen.png';
$image = imagecreatetruecolor(1600, 900);
imagefilledrectangle($image, 0, 0, 1600, 900, imagecolorallocate($image, 30, 90, 40));
imagepng($image, $source);
imagedestroy($image);

$route = static function (string $method, string $uri, array $post = [], array $files = []) use ($router, $csrf): \GB\Support\Response {
    $server = [
        'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1',
    ];

    if ($method !== 'GET') {
        $post['_token'] = $csrf->token();
    }

    return $router->dispatch(new Request([], $post, $server, $files, []));
};

$cover = ['file' => [
    'name' => 'portada.png', 'type' => 'image/png', 'tmp_name' => $source,
    'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($source),
]];

echo 'Listas y edición de contenido del panel (tarea 11.9)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

try {
    // ---------------------------------------------------------------- Cursos
    echo 'Cursos' . PHP_EOL;
    $uploaded = json_decode($route('POST', '/admin/imagenes', ['profile' => 'course_cover'], $cover)->content(), true);
    $coverId = (int) ($uploaded['media']['id'] ?? 0);
    $mediaIds[] = $coverId;

    $response = $route('POST', '/admin/cursos', [
        'title' => 'Curso de prueba completo', 'slug' => 'zz-curso-prueba-completo',
        'summary' => 'Resumen de prueba.', 'description' => 'Descripción de prueba del curso.',
        'access_type' => 'free', 'price' => '0', 'currency' => 'EUR', 'level' => 'Básico',
        'category' => 'Derecho civil', 'duration_minutes' => '120', 'status' => 'draft',
        'cover_media_id' => (string) $coverId,
    ]);
    $check('se crea un curso desde el panel', $response->status() === 302);
    $check('la lista muestra el curso creado', str_contains($route('GET', '/admin/cursos')->content(), 'Curso de prueba completo'));

    $courseId = (int) $pdo->query('SELECT id FROM courses WHERE slug = "zz-curso-prueba-completo"')->fetchColumn();
    $check('el curso quedó guardado con su portada',
        $courseId > 0 && (int) $pdo->query('SELECT cover_media_id FROM courses WHERE id = ' . $courseId)->fetchColumn() === $coverId);

    $form = $route('GET', '/admin/cursos/' . $courseId . '/editar')->content();
    $check('el formulario de edición se abre con los datos',
        str_contains($form, 'Curso de prueba completo') && str_contains($form, 'Descripción de prueba del curso.'));

    // Publicar sin lecciones debe impedirse y explicar por qué
    $response = $route('PUT', '/admin/cursos/' . $courseId, [
        'title' => 'Curso de prueba completo', 'slug' => 'zz-curso-prueba-completo',
        'summary' => 'Resumen de prueba.', 'description' => 'Descripción de prueba del curso.',
        'access_type' => 'free', 'price' => '0', 'currency' => 'EUR', 'level' => 'Básico',
        'category' => 'Derecho civil', 'duration_minutes' => '120', 'status' => 'published',
        'cover_media_id' => (string) $coverId,
    ]);
    $body = $response->content();
    $check('publicar sin lecciones se rechaza', $response->status() === 422);
    $check('y se explica que falta una lección', str_contains($body, 'al menos una lección'));
    $check('el curso no quedó publicado',
        (string) $pdo->query('SELECT status FROM courses WHERE id = ' . $courseId)->fetchColumn() !== 'published');

    // ------------------------------------------------- Temario: módulos y lecciones
    echo 'Módulos y lecciones' . PHP_EOL;
    $route('POST', '/admin/cursos/' . $courseId . '/modulos', ['title' => 'Módulo de prueba', 'summary' => 'Resumen', 'sort_order' => '1']);
    $moduleId = (int) $pdo->query('SELECT id FROM course_modules WHERE course_id = ' . $courseId)->fetchColumn();
    $check('se añade un módulo', $moduleId > 0);
    $check('el temario muestra el módulo', str_contains($route('GET', '/admin/cursos/' . $courseId . '/temario')->content(), 'Módulo de prueba'));

    $route('POST', '/admin/cursos/' . $courseId . '/modulos/' . $moduleId . '/lecciones', [
        'title' => 'Lección de prueba', 'summary' => 'Resumen', 'content_type' => 'video',
        'duration_minutes' => '15', 'sort_order' => '1', 'is_preview' => '1',
    ]);
    $lessonId = (int) $pdo->query('SELECT id FROM lessons WHERE course_module_id = ' . $moduleId)->fetchColumn();
    $check('se añade una lección', $lessonId > 0);
    $check('la lección se marca como anticipo',
        (int) $pdo->query('SELECT is_preview FROM lessons WHERE id = ' . $lessonId)->fetchColumn() === 1);

    $route('PUT', '/admin/cursos/' . $courseId . '/modulos/' . $moduleId, ['title' => 'Módulo editado', 'summary' => 'Resumen nuevo', 'sort_order' => '2']);
    $check('el módulo se edita',
        (string) $pdo->query('SELECT title FROM course_modules WHERE id = ' . $moduleId)->fetchColumn() === 'Módulo editado');

    $route('PUT', '/admin/cursos/' . $courseId . '/modulos/' . $moduleId . '/lecciones/' . $lessonId, [
        'title' => 'Lección editada', 'summary' => 'Resumen', 'content_type' => 'document', 'duration_minutes' => '20', 'sort_order' => '1',
    ]);
    $check('la lección se edita',
        (string) $pdo->query('SELECT title FROM lessons WHERE id = ' . $lessonId)->fetchColumn() === 'Lección editada');

    // Ahora sí se puede publicar
    $response = $route('PUT', '/admin/cursos/' . $courseId, [
        'title' => 'Curso de prueba completo', 'slug' => 'zz-curso-prueba-completo',
        'summary' => 'Resumen de prueba.', 'description' => 'Descripción de prueba del curso.',
        'access_type' => 'free', 'price' => '0', 'currency' => 'EUR', 'level' => 'Básico',
        'category' => 'Derecho civil', 'duration_minutes' => '120', 'status' => 'published',
        'cover_media_id' => (string) $coverId,
    ]);
    $check('con la lección añadida el curso se publica', $response->status() === 302
        && (string) $pdo->query('SELECT status FROM courses WHERE id = ' . $courseId)->fetchColumn() === 'published');
    $check('se confirma que ya aparece en el catálogo',
        str_contains($route('GET', '/admin/cursos')->content(), 'catálogo público'));
    $check('el curso aparece en el catálogo público',
        str_contains($route('GET', '/cursos')->content(), 'Curso de prueba completo'));

    // ------------------------------------------------------------- Plantillas
    // El archivo se registra directamente: la subida real exige una petición
    // HTTP con archivo cargado, que no existe en la línea de comandos.
    echo 'Plantillas' . PHP_EOL;
    $templateId = $templates->createAdmin([
        'title' => 'Plantilla de prueba', 'slug' => 'zz-plantilla-prueba', 'summary' => 'Resumen',
        'description' => 'Descripción de la plantilla.', 'category' => 'Contratos', 'status' => 'active',
        'sort_order' => 0, 'requires_registration' => 0,
        'file_path' => 'templates/zz-plantilla-prueba.pdf', 'original_file_name' => 'plantilla.pdf',
        'file_mime' => 'application/pdf', 'file_size_bytes' => 1024,
        'created_by' => $adminId, 'updated_by' => $adminId,
    ]);
    $check('la plantilla queda registrada', $templateId > 0);
    $check('la lista de plantillas la muestra', str_contains($route('GET', '/admin/plantillas')->content(), 'Plantilla de prueba'));
    $check('su formulario de edición se abre', str_contains($route('GET', '/admin/plantillas/' . $templateId . '/editar')->content(), 'Plantilla de prueba'));
    $route('DELETE', '/admin/plantillas/' . $templateId);
    // No se busca el título en el HTML: el aviso de confirmación lo repite. Se
    // comprueba que salió de la lista y que quedó en la papelera.
    $check('la plantilla sale de la lista',
        $templates->adminList() === []
        || !in_array($templateId, array_column($templates->adminList(), 'id'), true));
    $check('y se conserva en la papelera el tiempo previsto',
        (int) $pdo->query('SELECT COUNT(*) FROM templates WHERE id = ' . $templateId . ' AND deleted_at IS NOT NULL')->fetchColumn() === 1);

    // ----------------------------------------------------------------- Videos
    echo 'Videos' . PHP_EOL;
    $route('POST', '/admin/videos', [
        'title' => 'Video de prueba', 'slug' => 'zz-video-prueba', 'summary' => 'Resumen',
        'description' => 'Descripción del video.', 'category' => 'Clases', 'source' => 'youtube',
        'external_id' => 'dQw4w9WgXcQ', 'duration_seconds' => '300', 'access_type' => 'free',
        'status' => 'active', 'sort_order' => '0', 'cover_media_id' => (string) $coverId,
    ]);
    $videoId = (int) $pdo->query('SELECT id FROM videos WHERE slug = "zz-video-prueba"')->fetchColumn();
    $check('el video queda registrado', $videoId > 0);
    $check('la lista de videos lo muestra', str_contains($route('GET', '/admin/videos')->content(), 'Video de prueba'));
    $check('su formulario de edición se abre', str_contains($route('GET', '/admin/videos/' . $videoId . '/editar')->content(), 'Video de prueba'));

    $route('PUT', '/admin/videos/' . $videoId, [
        'title' => 'Video editado', 'slug' => 'zz-video-prueba', 'summary' => 'Resumen',
        'description' => 'Descripción del video.', 'category' => 'Clases', 'source' => 'vimeo',
        'external_id' => '123456789', 'duration_seconds' => '420', 'access_type' => 'paid',
        'status' => 'inactive', 'sort_order' => '0', 'cover_media_id' => (string) $coverId,
    ]);
    $video = $pdo->query('SELECT title, source, access_type, status FROM videos WHERE id = ' . $videoId)->fetch(PDO::FETCH_ASSOC);
    $check('el video se edita', $video['title'] === 'Video editado' && $video['source'] === 'vimeo'
        && $video['access_type'] === 'paid' && $video['status'] === 'inactive');

    // --------------------------------------------------------------- Noticias
    echo 'Noticias' . PHP_EOL;
    $route('POST', '/admin/noticias', [
        'title' => 'Noticia de prueba completa', 'slug' => 'zz-noticia-prueba-completa',
        'summary' => 'Resumen de la noticia.', 'body' => 'Contenido de la noticia de prueba.',
        'category' => 'Sentencias', 'tags' => 'prueba, civil', 'author_name' => 'Prueba',
        'status' => 'published', 'cover_media_id' => (string) $coverId,
    ]);
    $newsId = (int) $pdo->query('SELECT id FROM news WHERE slug = "zz-noticia-prueba-completa"')->fetchColumn();
    $check('la noticia queda registrada', $newsId > 0);
    $check('la lista de noticias la muestra', str_contains($route('GET', '/admin/noticias')->content(), 'Noticia de prueba completa'));
    $check('su formulario de edición se abre', str_contains($route('GET', '/admin/noticias/' . $newsId . '/editar')->content(), 'Noticia de prueba completa'));
    $check('la noticia publicada aparece en el blog', str_contains($route('GET', '/noticias')->content(), 'Noticia de prueba completa'));

    $route('PUT', '/admin/noticias/' . $newsId, [
        'title' => 'Noticia editada', 'slug' => 'zz-noticia-prueba-completa', 'summary' => 'Resumen nuevo',
        'body' => 'Contenido nuevo de la noticia.', 'category' => 'Sentencias', 'status' => 'hidden',
        'cover_media_id' => (string) $coverId,
    ]);
    $check('la noticia se edita',
        (string) $pdo->query('SELECT title FROM news WHERE id = ' . $newsId)->fetchColumn() === 'Noticia editada');

    // --------------------------------------------------- Retirada de contenido
    echo 'Retirada de contenido' . PHP_EOL;
    $route('DELETE', '/admin/cursos/' . $courseId . '/modulos/' . $moduleId . '/lecciones/' . $lessonId);
    $check('la lección sale del temario',
        (int) $pdo->query('SELECT COUNT(*) FROM lessons WHERE id = ' . $lessonId . ' AND deleted_at IS NULL')->fetchColumn() === 0);
    $route('DELETE', '/admin/cursos/' . $courseId . '/modulos/' . $moduleId);
    $check('el módulo sale del temario',
        (int) $pdo->query('SELECT COUNT(*) FROM course_modules WHERE id = ' . $moduleId . ' AND deleted_at IS NULL')->fetchColumn() === 0);
    $route('DELETE', '/admin/cursos/' . $courseId);
    $check('el curso sale del catálogo público', !str_contains($route('GET', '/cursos')->content(), 'Curso de prueba completo'));
    $route('DELETE', '/admin/videos/' . $videoId);
    // Igual que con las plantillas: el aviso de confirmación repite el título,
    // así que la comprobación va contra la lista real, no contra el HTML.
    $check('el video sale de su lista',
        (int) $pdo->query('SELECT COUNT(*) FROM videos WHERE id = ' . $videoId . ' AND deleted_at IS NULL')->fetchColumn() === 0);
    $route('DELETE', '/admin/noticias/' . $newsId);
    $check('la noticia sale del blog',
        (int) $pdo->query('SELECT COUNT(*) FROM news WHERE id = ' . $newsId . ' AND deleted_at IS NULL')->fetchColumn() === 0
        && !str_contains($route('GET', '/noticias')->content(), 'Noticia editada'));
} finally {
    // Limpieza completa: filas, archivos y registros de auditoría de la prueba
    $pdo->exec('DELETE FROM lessons');
    $pdo->exec('DELETE FROM course_modules');
    $pdo->exec('DELETE FROM courses');
    $pdo->exec('DELETE FROM templates');
    $pdo->exec('DELETE FROM videos');
    $pdo->exec('DELETE FROM news');
    // Sólo los registros de auditoría que ha creado esta prueba.
    $pdo->exec('DELETE FROM audit_logs WHERE id > ' . $auditBefore);

    foreach ($mediaIds as $id) {
        if ($pdo->query('SELECT id FROM media WHERE id = ' . $id)->fetchColumn() !== false) {
            $pipeline->delete($id);
        }
    }

    $pdo->exec('DELETE FROM users WHERE id = ' . $adminId);
    $pdo->exec('DELETE FROM throttle WHERE identifier = "0.0.0.0"');

    $session = GB_STORAGE_PATH . '/sessions/active/user-' . $adminId . '.json';

    if (is_file($session)) {
        unlink($session);
    }

    if (is_file($source)) {
        unlink($source);
    }

    $protected = GB_STORAGE_PATH . '/protected/templates/zz-plantilla-prueba.pdf';

    if (is_file($protected)) {
        unlink($protected);
    }
}

$after = (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
$leftovers = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM course_modules')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn()
    + (int) $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();

echo str_repeat('-', 70) . PHP_EOL;
echo 'Contenido restante: ' . $leftovers . ' — imágenes: ' . $mediaBefore . ' → ' . $after . PHP_EOL;
echo $failures === 0 && $leftovers === 0 && $after === $mediaBefore
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 && $leftovers === 0 && $after === $mediaBefore ? 0 : 1);
