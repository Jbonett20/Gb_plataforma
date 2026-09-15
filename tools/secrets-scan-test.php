<?php

declare(strict_types=1);

/**
 * Comprobación de credenciales y rutas absolutas (tarea 12.6).
 *
 * Revisa que en el código no haya contraseñas, llaves ni rutas absolutas
 * escritas a mano, que el archivo `.env` no sea alcanzable desde la web y que
 * esté excluido del control de versiones. Son fallos que no se notan hasta que
 * el proyecto se publica en un repositorio, y entonces ya es tarde.
 *
 * Uso:  php tools/secrets-scan-test.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este guion sólo puede ejecutarse desde la línea de comandos.');
}

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

/** Rutas que contienen código que se despliega. */
$codeRoots = ['app', 'config', 'public', 'database'];

$files = [];

foreach ($codeRoots as $root) {
    $directory = GB_BASE_PATH . '/' . $root;

    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (in_array($file->getExtension(), ['php', 'js', 'css', 'htaccess'], true)) {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

echo 'Credenciales y rutas absolutas (tarea 12.6)' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;
echo 'Archivos revisados: ' . count($files) . PHP_EOL . PHP_EOL;

// --------------------------------------------------------------- Secretos
// Se busca una asignación con valor literal en un nombre que promete un secreto.
$secretPattern = '/(api[_-]?key|secret|private[_-]?key|client[_-]?secret|password|passwd|token)\s*=\s*([\'"])(?![a-z_]+\2)(.{12,})\2/i';

$secretFindings = [];
$absoluteFindings = [];

foreach ($files as $file) {
    $contents = (string) file_get_contents($file);
    $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
    $relative = str_replace(GB_BASE_PATH . DIRECTORY_SEPARATOR, '', $file);

    foreach ($lines as $number => $line) {
        // Los valores que salen de la configuración no son secretos escritos.
        $isConfigRead = str_contains($line, 'env(') || str_contains($line, 'config(');

        if (preg_match($secretPattern, $line, $matches) === 1 && !$isConfigRead) {
            $value = (string) ($matches[3] ?? '');

            // Los nombres de campo de un formulario y los textos de ayuda no son
            // secretos: se descartan los valores que son sólo texto con espacios.
            $looksLikeSecret = preg_match('/^[A-Za-z0-9+\/=_\-\.]{16,}$/', $value) === 1
                || preg_match('/^(cambia|tu-|pon-|escribe)/iu', $value) === 1;

            if ($looksLikeSecret) {
                $secretFindings[] = $relative . ':' . ($number + 1) . ' → ' . $matches[1];
            }
        }

        if (preg_match('/[A-Za-z]:[\\\\\/](xampp|wamp|laragon|Users)[\\\\\/]/', $line) === 1
            || str_contains($line, '/xampp/htdocs/')) {
            $absoluteFindings[] = $relative . ':' . ($number + 1);
        }
    }
}

$check('ningún archivo de código lleva llaves ni contraseñas escritas', $secretFindings === [],
    implode(PHP_EOL . '           ', array_slice($secretFindings, 0, 8)));

$check('ningún archivo de código lleva rutas absolutas de este equipo', $absoluteFindings === [],
    implode(PHP_EOL . '           ', array_slice($absoluteFindings, 0, 8)));

// ------------------------------------------------------- Archivo de entorno
$envPath = GB_BASE_PATH . '/.env';
$examplePath = GB_BASE_PATH . '/.env.example';

$check('el archivo .env existe', is_file($envPath));
$check('el archivo .env queda fuera del directorio público',
    !is_file(GB_PUBLIC_PATH . '/.env'));
$check('se puede empezar de cero con .env.example', is_file($examplePath));

if (is_file($examplePath)) {
    $example = (string) file_get_contents($examplePath);
    $exampleLines = preg_split('/\r\n|\r|\n/', $example) ?: [];
    $withValues = [];

    foreach ($exampleLines as $line) {
        if (preg_match('/^(DB_PASSWORD|MAIL_PASSWORD|APP_KEY|[A-Z_]*(KEY|SECRET|TOKEN|PASSWORD))=(\S+)/', (string) $line, $m) === 1) {
            $value = (string) $m[2];

            // Un ejemplo sólo puede traer vacíos o textos que invitan a cambiarlo.
            if ($value !== '' && preg_match('/^(cambia|tu-|pon-|escribe|genera)/iu', $value) !== 1) {
                $withValues[] = (string) $m[1];
            }
        }
    }

    $check('el archivo de ejemplo sólo trae valores de relleno', $withValues === [],
        implode(', ', $withValues));
}

// ------------------------------------------------- Control de versiones
$ignored = [];

foreach ([GB_BASE_PATH . '/.gitignore', dirname(GB_BASE_PATH) . '/.gitignore'] as $ignoreFile) {
    if (is_file($ignoreFile)) {
        $ignored[] = (string) file_get_contents($ignoreFile);
    }
}

$ignoredText = implode(PHP_EOL, $ignored);

$check('hay un archivo de exclusión para el control de versiones', $ignored !== []);

if ($ignored !== []) {
    foreach (['.env', 'storage/', 'public/uploads/'] as $must) {
        $check('queda excluido del repositorio: ' . $must,
            preg_match('/^' . preg_quote($must, '/') . '/m', $ignoredText) === 1
            || str_contains($ignoredText, $must));
    }
}

// ------------------------------------- Nada sensible en el directorio público
$publicFiles = glob(GB_PUBLIC_PATH . '/*') ?: [];
$suspicious = [];

foreach ($publicFiles as $path) {
    $name = basename($path);

    if (in_array($name, ['.env', 'composer.json', 'composer.lock', 'phpunit.xml'], true)) {
        $suspicious[] = $name;
    }
}

$check('el directorio público sólo contiene lo que debe', $suspicious === [],
    implode(', ', $suspicious));

$check('el material protegido vive fuera del directorio público',
    is_dir(GB_STORAGE_PATH . '/protected') && !is_dir(GB_PUBLIC_PATH . '/protected'));

echo str_repeat('-', 70) . PHP_EOL;
echo $failures === 0
    ? "Resultado: todas las comprobaciones pasaron.\n"
    : "Resultado: {$failures} comprobación(es) fallaron.\n";

exit($failures === 0 ? 0 : 1);
