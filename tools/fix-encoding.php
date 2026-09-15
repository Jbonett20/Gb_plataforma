<?php

declare(strict_types=1);

/**
 * Repara los acentos mal codificados de un archivo de configuración.
 *
 * Síntoma: en el navegador se lee "GonzÃ¡lez" en lugar de "González".
 *
 * Causa: el texto se guardó como UTF-8 y luego se volvió a codificar tomando
 * cada byte como un carácter independiente. Se deshace el paso de más.
 *
 * Uso:  php tools/fix-encoding.php .env [--write]
 */

$path = $argv[1] ?? '';
if ($path === '') {
    fwrite(STDERR, "Uso: php tools/fix-encoding.php <archivo> [--write]\n");
    exit(1);
}

$write = in_array('--write', $argv, true);

if (!is_file($path)) {
    fwrite(STDERR, sprintf("No existe el archivo %s\n", $path));
    exit(1);
}

$original = (string) file_get_contents($path);

// Sólo se toca si de verdad hay señales de doble codificación.
if (!preg_match('/Ã|â€|Â/', $original)) {
    echo "El archivo no parece tener acentos mal codificados. No se cambia nada.\n";
    exit(0);
}

$fixed = mb_convert_encoding($original, 'Windows-1252', 'UTF-8');

if (!is_string($fixed) || $fixed === '') {
    fwrite(STDERR, "No se pudo convertir el contenido.\n");
    exit(1);
}

// Comprobación: el resultado debe ser UTF-8 válido y conservar el número de líneas.
if (!mb_check_encoding($fixed, 'UTF-8')) {
    fwrite(STDERR, "El resultado no es texto UTF-8 válido. No se cambia nada.\n");
    exit(1);
}

if (substr_count($original, "\n") !== substr_count($fixed, "\n")) {
    fwrite(STDERR, "El número de líneas cambiaría. No se cambia nada.\n");
    exit(1);
}

echo "Antes  →  Después\n";
echo str_repeat('-', 70) . "\n";

$before = explode("\n", $original);
$after = explode("\n", $fixed);

foreach ($before as $index => $line) {
    if ($line !== ($after[$index] ?? '')) {
        printf("%s\n%s\n\n", $line, $after[$index]);
    }
}

if (!$write) {
    echo "Simulación: no se escribió nada. Añade --write para aplicar el cambio.\n";
    exit(0);
}

$backup = $path . '.antes-de-codificar';

if (!copy($path, $backup)) {
    fwrite(STDERR, "No se pudo crear el respaldo. No se cambia nada.\n");
    exit(1);
}

if (file_put_contents($path, $fixed) === false) {
    fwrite(STDERR, "No se pudo escribir el archivo. El respaldo está en {$backup}\n");
    exit(1);
}

printf("Corregido. Respaldo en %s\n", $backup);
