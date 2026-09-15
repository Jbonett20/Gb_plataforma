<?php

declare(strict_types=1);

/**
 * Verificación del esquema.
 *
 * Comprueba que la base de datos quedó exactamente como espera la aplicación:
 * todas las tablas, sus llaves foráneas y los índices de rendimiento.
 *
 * Uso:  php database/verify.php
 *
 * Devuelve código de salida 1 si algo falta, para poder encadenarlo en un
 * despliegue.
 */

use GB\Application;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

/**
 * Tablas que deben existir tras aplicar todas las migraciones.
 *
 * @var array<int, string>
 */
$expectedTables = [
    'roles', 'users', 'password_resets',
    'media',
    'modules', 'blocks', 'block_items', 'block_versions', 'settings',
    'courses', 'course_modules', 'lessons', 'enrollments', 'lesson_progress',
    'templates', 'template_downloads', 'videos', 'video_views',
    'payments', 'webhook_events',
    'news', 'subscribers', 'appointments', 'content_access_log', 'audit_logs', 'throttle',
];

/**
 * Llaves foráneas que garantizan la integridad entre tablas.
 * Nombre de la restricción => tabla donde vive.
 *
 * @var array<string, string>
 */
$expectedForeignKeys = [
    'fk_users_role' => 'users',
    'fk_users_avatar' => 'users',
    'fk_password_resets_user' => 'password_resets',
    'fk_media_uploaded_by' => 'media',
    'fk_blocks_module' => 'blocks',
    'fk_blocks_published_by' => 'blocks',
    'fk_block_items_block' => 'block_items',
    'fk_block_items_media' => 'block_items',
    'fk_block_versions_block' => 'block_versions',
    'fk_block_versions_user' => 'block_versions',
    'fk_settings_updated_by' => 'settings',
    'fk_courses_cover' => 'courses',
    'fk_courses_created_by' => 'courses',
    'fk_course_modules_course' => 'course_modules',
    'fk_lessons_module' => 'lessons',
    'fk_lessons_media' => 'lessons',
    'fk_enrollments_user' => 'enrollments',
    'fk_enrollments_course' => 'enrollments',
    'fk_enrollments_payment' => 'enrollments',
    'fk_lesson_progress_enrollment' => 'lesson_progress',
    'fk_lesson_progress_lesson' => 'lesson_progress',
    'fk_templates_preview' => 'templates',
    'fk_template_downloads_template' => 'template_downloads',
    'fk_videos_cover' => 'videos',
    'fk_videos_course' => 'videos',
    'fk_video_views_video' => 'video_views',
    'fk_payments_user' => 'payments',
    'fk_payments_course' => 'payments',
    'fk_news_cover' => 'news',
    'fk_appointments_assigned_to' => 'appointments',
    'fk_content_access_user' => 'content_access_log',
    'fk_content_access_course' => 'content_access_log',
    'fk_content_access_lesson' => 'content_access_log',
    'fk_content_access_video' => 'content_access_log',
    'fk_content_access_media' => 'content_access_log',
    'fk_audit_logs_user' => 'audit_logs',
];

/**
 * Índices que sostienen las consultas más frecuentes del sitio y del panel.
 * Nombre del índice => tabla.
 *
 * @var array<string, string>
 */
$expectedIndexes = [
    'uq_users_email' => 'users',
    'uq_media_identity' => 'media',
    'uq_blocks_key_name' => 'blocks',
    'idx_block_items_block' => 'block_items',
    'uq_block_versions' => 'block_versions',
    'uq_settings_key_name' => 'settings',
    'uq_courses_slug' => 'courses',
    'idx_courses_status' => 'courses',
    'uq_enrollments_user_course' => 'enrollments',
    'uq_lesson_progress' => 'lesson_progress',
    'uq_templates_slug' => 'templates',
    'uq_videos_slug' => 'videos',
    'uq_payments_reference' => 'payments',
    'uq_payments_gateway_external' => 'payments',
    'uq_webhook_events' => 'webhook_events',
    'uq_news_slug' => 'news',
    'idx_news_scheduled' => 'news',
    'uq_subscribers_email' => 'subscribers',
    'uq_appointments_legacy' => 'appointments',
    'idx_content_access_user' => 'content_access_log',
    'idx_audit_logs_entity' => 'audit_logs',
    'uq_throttle_action_identifier' => 'throttle',
];

$container = Application::boot();

try {
    $pdo = $container->get(PDO::class);
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo conectar a la base de datos.\n" . $exception->getMessage() . "\n");
    exit(1);
}

$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$problems = 0;

echo "Verificación del esquema — base de datos: {$database}\n";
echo str_repeat('=', 78) . "\n";

// --- Tablas -----------------------------------------------------------------
$foundTables = $pdo->query(
    "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

$missingTables = array_values(array_diff($expectedTables, $foundTables));
$unexpectedTables = array_values(array_diff($foundTables, array_merge($expectedTables, ['migrations'])));

printf("Tablas esperadas: %d   encontradas: %d\n", count($expectedTables), count($foundTables));

if ($missingTables !== []) {
    $problems += count($missingTables);
    echo "  FALTAN: " . implode(', ', $missingTables) . "\n";
    echo "  Solución: ejecuta  php database/migrate.php\n";
} else {
    echo "  [ok] Están todas las tablas.\n";
}

if ($unexpectedTables !== []) {
    echo "  Aviso: hay tablas que el esquema no declara: " . implode(', ', $unexpectedTables) . "\n";
}

// --- Llaves foráneas --------------------------------------------------------
$foundForeignKeys = $pdo->query(
    "SELECT constraint_name FROM information_schema.table_constraints
     WHERE table_schema = DATABASE() AND constraint_type = 'FOREIGN KEY'"
)->fetchAll(PDO::FETCH_COLUMN);

$missingForeignKeys = array_keys(array_diff_key($expectedForeignKeys, array_flip($foundForeignKeys)));

printf("\nLlaves foráneas esperadas: %d   encontradas: %d\n", count($expectedForeignKeys), count($foundForeignKeys));

if ($missingForeignKeys !== []) {
    $problems += count($missingForeignKeys);

    foreach ($missingForeignKeys as $constraint) {
        echo sprintf("  FALTA: %s (tabla %s)\n", $constraint, $expectedForeignKeys[$constraint]);
    }
} else {
    echo "  [ok] Todas las relaciones entre tablas existen.\n";
}

// --- Índices -----------------------------------------------------------------
$foundIndexes = [];

foreach ($pdo->query(
    "SELECT index_name, table_name FROM information_schema.statistics WHERE table_schema = DATABASE()"
)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $foundIndexes[(string) $row['index_name']] = (string) $row['table_name'];
}

$missingIndexes = array_keys(array_diff_key($expectedIndexes, $foundIndexes));

printf("\nÍndices esperados: %d   encontrados: %d\n", count($expectedIndexes), count($foundIndexes));

if ($missingIndexes !== []) {
    $problems += count($missingIndexes);

    foreach ($missingIndexes as $index) {
        echo sprintf("  FALTA: %s (tabla %s)\n", $index, $expectedIndexes[$index]);
    }
} else {
    echo "  [ok] Los índices de rendimiento están creados.\n";
}

// --- Datos mínimos -----------------------------------------------------------
$roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();

echo "\nRoles del sistema: {$roleCount}\n";

if ($roleCount < 2) {
    $problems++;
    echo "  FALTAN: se esperaban los roles SuperAdmin y Student.\n";
} else {
    echo "  [ok] SuperAdmin y Student están definidos.\n";
}

// --- Resultado ---------------------------------------------------------------
echo str_repeat('=', 78) . "\n";

if ($problems === 0) {
    echo "Resultado: el esquema está completo y correcto.\n";
    echo "Siguiente paso:  php database/seed.php\n";

    exit(0);
}

echo "Resultado: se encontraron {$problems} problema(s). Revisa los mensajes anteriores.\n";

exit(1);
