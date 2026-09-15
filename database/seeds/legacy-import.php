<?php

declare(strict_types=1);

/**
 * Importación de los datos del sitio anterior.
 *
 * Trae dos conjuntos de datos:
 *   - los suscriptores del boletín (tabla `gb_suscriptores`);
 *   - las solicitudes de cita recibidas por el formulario de contacto
 *     (tabla `contactos`, que el panel actual llama "citas").
 *
 * Es reejecutable: cada fila conserva su identificador original en `legacy_id`,
 * así que volver a ejecutarlo no duplica nada. Los suscriptores se deduplican
 * además por correo.
 *
 * Configura el acceso a la base anterior con las variables LEGACY_DB_* del
 * archivo .env. Si no están puestas, el guion no hace nada y lo explica.
 *
 * Uso:
 *   php database/seeds/legacy-import.php
 *   php database/seeds/legacy-import.php --dry-run    (sólo informa)
 */

use GB\Application;
use GB\Support\Env;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

$container = Application::boot();

/** @var PDO $target */
$target = $container->get(PDO::class);

$dryRun = in_array('--dry-run', $argv, true);

echo "Importación de datos del sitio anterior\n";
echo str_repeat('=', 78) . "\n";

$legacyDatabase = (string) Env::get('LEGACY_DB_DATABASE', '');

if ($legacyDatabase === '') {
    echo "La base de datos del sitio anterior no está configurada.\n\n";
    echo "Para importarla, añade estas líneas al archivo .env con los datos de la base\n";
    echo "de producción y vuelve a ejecutar este comando:\n\n";
    echo "  LEGACY_DB_HOST=localhost\n";
    echo "  LEGACY_DB_DATABASE=nombre_de_la_base_anterior\n";
    echo "  LEGACY_DB_USERNAME=usuario\n";
    echo "  LEGACY_DB_PASSWORD=contraseña\n\n";
    echo "Mientras tanto, el sitio nuevo funciona con normalidad: sólo faltarán esos\n";
    echo "suscriptores y esas citas.\n";

    exit(0);
}

try {
    $legacy = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) Env::get('LEGACY_DB_HOST', 'localhost'),
            (int) Env::get('LEGACY_DB_PORT', '3306'),
            $legacyDatabase
        ),
        (string) Env::get('LEGACY_DB_USERNAME', ''),
        (string) Env::get('LEGACY_DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo conectar a la base anterior: " . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * Busca la primera tabla de la lista que exista en la base anterior: los
 * nombres cambiaron entre versiones del sitio.
 *
 * @param array<int, string> $candidates
 */
function findTable(PDO $connection, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $statement->execute([$candidate]);

        if ((int) $statement->fetchColumn() > 0) {
            return $candidate;
        }
    }

    return null;
}

/**
 * @return array<int, string>
 */
function columnsOf(PDO $connection, string $table): array
{
    $statement = $connection->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);

    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Devuelve el valor de la primera columna que exista de la lista.
 *
 * @param array<string, mixed> $row
 * @param array<int, string> $names
 */
function pick(array $row, array $names): ?string
{
    foreach ($names as $name) {
        if (isset($row[$name]) && trim((string) $row[$name]) !== '') {
            return trim((string) $row[$name]);
        }
    }

    return null;
}

$subscribers = 0;
$subscribersSkipped = 0;
$appointments = 0;
$appointmentsSkipped = 0;

// -----------------------------------------------------------------------------
// Suscriptores
// -----------------------------------------------------------------------------
$subscriberTable = findTable($legacy, ['gb_suscriptores', 'suscriptores', 'subscribers', 'newsletter_subscribers']);

if ($subscriberTable === null) {
    echo "Suscriptores: no se encontró la tabla en la base anterior.\n";
} else {
    $rows = $legacy->query('SELECT * FROM `' . $subscriberTable . '`')->fetchAll() ?: [];
    $insert = $target->prepare(
        'INSERT INTO subscribers (name, last_name, phone, email, status, source, legacy_id, created_at, updated_at)
         VALUES (:name, :last_name, :phone, :email, :status, :source, :legacy_id, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE name = VALUES(name), last_name = VALUES(last_name), phone = VALUES(phone)'
    );
    $exists = $target->prepare('SELECT id FROM subscribers WHERE email = ?');

    foreach ($rows as $row) {
        $email = strtolower((string) pick($row, ['email', 'correo', 'correo_electronico']) ?? '');
        $name = pick($row, ['nombre', 'name', 'nombres']) ?? '';

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $subscribersSkipped++;

            continue;
        }

        $exists->execute([$email]);

        if ($exists->fetchColumn() !== false) {
            $subscribersSkipped++;

            continue;
        }

        if (!$dryRun) {
            $insert->execute([
                'name' => $name === '' ? 'Suscriptor' : $name,
                'last_name' => pick($row, ['apellidos', 'last_name']),
                'phone' => pick($row, ['telefono', 'teléfono', 'phone', 'celular']),
                'email' => $email,
                'status' => 'active',
                'source' => 'migracion',
                'legacy_id' => pick($row, ['id', 'id_suscriptor']),
                'created_at' => pick($row, ['fecha_creacion', 'created_at', 'fecha']) ?? date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $subscribers++;
    }

    printf(
        "Suscriptores: %d importados, %d omitidos (ya existían o sin correo válido).\n",
        $subscribers,
        $subscribersSkipped
    );
}

// -----------------------------------------------------------------------------
// Solicitudes de cita
// -----------------------------------------------------------------------------
$appointmentTable = findTable($legacy, ['contactos', 'gb_citas', 'citas', 'appointments']);

if ($appointmentTable === null) {
    echo "Solicitudes de cita: no se encontró la tabla en la base anterior.\n";
} else {
    $rows = $legacy->query('SELECT * FROM `' . $appointmentTable . '`')->fetchAll() ?: [];
    $insert = $target->prepare(
        'INSERT INTO appointments (name, last_name, email, phone, subject, message, status, source, legacy_id, created_at, updated_at)
         VALUES (:name, :last_name, :email, :phone, :subject, :message, :status, :source, :legacy_id, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE message = VALUES(message)'
    );

    foreach ($rows as $row) {
        $legacyId = pick($row, ['id', 'id_cita']);

        if ($legacyId === null) {
            $appointmentsSkipped++;

            continue;
        }

        // El panel anterior usaba `estado = 2` para las citas ya atendidas.
        $legacyStatus = pick($row, ['estado', 'status']);
        $status = match (true) {
            $legacyStatus === null => 'new',
            $legacyStatus === '2' || strtolower($legacyStatus) === 'atendido' => 'attended',
            $legacyStatus === '1' || strtolower($legacyStatus) === 'contactado' => 'contacted',
            default => 'new',
        };

        if (!$dryRun) {
            $insert->execute([
                'name' => pick($row, ['nombre', 'name']) ?? 'Sin nombre',
                'last_name' => pick($row, ['apellidos', 'last_name']),
                'email' => pick($row, ['email', 'correo']),
                'phone' => pick($row, ['telefono', 'teléfono', 'phone', 'celular']),
                'subject' => pick($row, ['asunto', 'subject']),
                'message' => pick($row, ['mensaje', 'message']),
                'status' => $status,
                'source' => 'migracion',
                'legacy_id' => $legacyId,
                'created_at' => pick($row, ['fecha_creacion', 'created_at', 'fecha']) ?? date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $appointments++;
    }

    printf(
        "Solicitudes de cita: %d importadas, %d omitidas.\n",
        $appointments,
        $appointmentsSkipped
    );
}

echo str_repeat('=', 78) . "\n";

if ($dryRun) {
    echo "Modo --dry-run: no se guardó nada. Vuelve a ejecutarlo sin esa opción.\n";
} else {
    echo "Importación terminada. Volver a ejecutarla no duplicará registros.\n";
}

exit(0);
