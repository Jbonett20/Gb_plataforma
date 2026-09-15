<?php

declare(strict_types=1);

namespace GB\Services;

use RuntimeException;

/** Guarda archivos de recursos fuera del directorio público. */
final class ProtectedFileStorage
{
    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls',
    ];

    public function __construct(private string $root)
    {
    }

    /** @param array<string, mixed> $file @return array{path: string, name: string, mime: string, size: int} */
    public function store(array $file, string $directory = 'templates'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo cargar el archivo.');
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new RuntimeException('El archivo recibido no es una carga válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('El formato del archivo no está permitido. Usa PDF, Word, Excel o ZIP.');
        }

        $relativeDirectory = trim($directory, '/');
        $targetDirectory = rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativeDirectory;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento protegido.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $relativePath = $relativeDirectory . '/' . $name;
        $target = $targetDirectory . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($temporary, $target)) {
            throw new RuntimeException('No se pudo guardar el archivo protegido.');
        }

        return [
            'path' => $relativePath,
            'name' => mb_substr((string) ($file['name'] ?? $name), 0, 255),
            'mime' => $mime,
            'size' => (int) ($file['size'] ?? filesize($target) ?: 0),
        ];
    }
}