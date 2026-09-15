<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\MediaRepository;
use GB\Support\UploadException;
use GdImage;

/**
 * Procesamiento de las imágenes que se suben desde el panel.
 *
 * Es el único punto por el que entra una imagen al sistema. Ningún formulario
 * escribe archivos por su cuenta.
 *
 * Qué hace, en orden:
 *   1. comprueba el tipo REAL del archivo (no la extensión), el peso y las
 *      dimensiones mínimas;
 *   2. corrige la orientación que traen algunas fotos de móvil;
 *   3. recorta a la proporción del destino, desplazando el encuadre según el
 *      punto focal que eligió quien administra;
 *   4. genera varias medidas en formato moderno, con respaldo compatible;
 *   5. guarda el original fuera del alcance web y registra todo en `media`.
 *
 * Se apoya en GD, la extensión de imágenes que ya trae PHP, para no añadir
 * dependencias que compliquen el despliegue en hosting compartido.
 */
final class ImagePipeline
{
    /**
     * @param array<string, mixed> $config config('images')
     */
    public function __construct(
        private MediaRepository $media,
        private array $config,
        private string $originalsPath,
        private string $publicUploadsPath,
    ) {
    }

    /** @return array<string, array<string, mixed>> */
    public function profiles(): array
    {
        return is_array($this->config['profiles'] ?? null) ? $this->config['profiles'] : [];
    }

    /** @return array<string, mixed> */
    public function profile(string $key): array
    {
        $profiles = $this->profiles();

        if (!isset($profiles[$key])) {
            throw UploadException::unknownProfile($key);
        }

        return $profiles[$key];
    }

    /**
     * Texto de ayuda del destino, para mostrarlo junto al campo de carga.
     */
    public function helpFor(string $key): string
    {
        $profile = $this->profile($key);

        $help = (string) ($profile['help'] ?? '');
        $minWidth = (int) ($profile['min_width'] ?? 0);
        $minHeight = (int) ($profile['min_height'] ?? 0);

        if ($minWidth > 0 && $minHeight > 0) {
            $help .= sprintf(' Mínimo recomendado: %d × %d píxeles, hasta %s MB.', $minWidth, $minHeight, $profile['max_mb'] ?? '—');
        }

        return trim($help);
    }

    /**
     * Comprueba el archivo sin guardarlo. Devuelve sus datos reales.
     *
     * @param array<string, mixed> $file entrada de $_FILES
     * @return array{mime: string, extension: string, width: int, height: int, size_bytes: int}
     */
    public function validate(array $file, string $profileKey, bool $allowSmall = false): array
    {
        $profile = $this->profile($profileKey);

        $this->assertUploadSucceeded($file);

        $path = (string) ($file['tmp_name'] ?? '');

        if ($path === '' || !is_file($path)) {
            throw UploadException::uploadFailed('no se encontró el archivo temporal.');
        }

        $sizeBytes = (int) ($file['size'] ?? 0);
        $maxMb = (float) ($profile['max_mb'] ?? 8);
        $sizeMb = $sizeBytes / 1048576;

        if ($sizeMb > $maxMb) {
            throw UploadException::tooLarge($maxMb, $sizeMb);
        }

        $mime = $this->detectMime($path);

        if (!$this->isAllowed($mime)) {
            throw UploadException::notAnImage();
        }

        $allowed = (array) ($this->config['allowed_mime'] ?? []);
        $extension = (string) ($allowed[$mime] ?? 'bin');

        if ($mime === 'image/svg+xml') {
            $this->assertSafeSvg($path);

            $dimensions = $this->svgDimensions($path);
            $width = $dimensions['width'];
            $height = $dimensions['height'];
        } else {
            $info = @getimagesize($path);

            if ($info === false) {
                throw UploadException::notAnImage();
            }

            $width = (int) $info[0];
            $height = (int) $info[1];
        }

        $minWidth = (int) ($profile['min_width'] ?? 0);
        $minHeight = (int) ($profile['min_height'] ?? 0);

        if (!$allowSmall && ($width < $minWidth || $height < $minHeight)) {
            throw UploadException::tooSmall($minWidth, $minHeight, $width, $height);
        }

        return [
            'mime' => $mime,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Procesa y guarda la imagen. Devuelve el identificador del recurso.
     *
     * @param array<string, mixed> $file entrada de $_FILES
     */
    public function store(
        array $file,
        string $profileKey,
        ?int $uploadedBy = null,
        string $altText = '',
        float $focalX = 0.5,
        float $focalY = 0.5,
        bool $allowSmall = false,
    ): int {
        $profile = $this->profile($profileKey);
        $info = $this->validate($file, $profileKey, $allowSmall);

        $focalX = $this->clampFocal($focalX);
        $focalY = $this->clampFocal($focalY);

        $hash = bin2hex(random_bytes(8));
        $temporaryPath = (string) $file['tmp_name'];
        $checksum = hash_file('sha256', $temporaryPath) ?: null;

        // ¿Ya está guardada esta misma imagen, para este mismo destino y con el
        // mismo encuadre? Entonces se reutiliza y no se procesa de nuevo.
        if ($checksum !== null) {
            $existing = $this->media->findDuplicate($checksum, $profileKey, $focalX, $focalY);

            if ($existing !== null) {
                return (int) $existing['id'];
            }
        }

        // El archivo temporal se consume al guardar el original, así que todo lo
        // que necesite leerlo (procesar la imagen o generar variantes) ocurre
        // ANTES. Mover primero y leer después fue un fallo real: la imagen ya no
        // estaba en su sitio cuando había que recortarla.
        if ($info['mime'] === 'image/svg+xml') {
            $originalRelative = $this->storeOriginal($temporaryPath, $info['extension'], $hash);
            $publicRelative = $this->publishSvgCopy($originalRelative, $profileKey, $hash);

            return $this->media->register([
                'disk' => 'local',
                'path' => $originalRelative,
                'public_path' => $publicRelative,
                'file_name' => (string) ($file['name'] ?? 'logo.svg'),
                'mime_type' => $info['mime'],
                'extension' => 'svg',
                'size_bytes' => $info['size_bytes'],
                'width' => $info['width'],
                'height' => $info['height'],
                'profile' => $profileKey,
                'focal_x' => $focalX,
                'focal_y' => $focalY,
                'alt_text' => $altText === '' ? null : $altText,
                'checksum' => $checksum,
                'variants' => null,
                'is_protected' => 0,
                'is_legacy' => 0,
                'uploaded_by' => $uploadedBy,
            ]);
        }

        $image = $this->createImage($temporaryPath, $info['mime']);
        $image = $this->normalizeOrientation($image, $temporaryPath, $info['mime']);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $rect = self::cropRect($sourceWidth, $sourceHeight, $profile['ratio'] ?? null, $focalX, $focalY);
        $widths = self::planVariants($profile, $rect['width']);
        $formats = $this->formatsFor($info['mime']);

        $cropped = $this->crop($image, $rect);
        $variants = $this->writeVariants($cropped, $profileKey, $hash, $widths, $profile['ratio'] ?? null, $formats);

        imagedestroy($image);
        imagedestroy($cropped);

        $master = $this->masterVariant($variants);

        if ($master === null) {
            $this->discardFiles($profileKey, $variants, null);

            throw UploadException::cannotProcess('no se pudo generar ninguna medida.');
        }

        // Ya no se necesita el temporal: se guarda fuera del alcance web.
        $originalRelative = $this->storeOriginal($temporaryPath, $info['extension'], $hash);

        try {
            return $this->media->register([
                'disk' => 'local',
                'path' => $originalRelative,
                'public_path' => $master['path'],
                'file_name' => (string) ($file['name'] ?? 'imagen'),
                'mime_type' => $info['mime'],
                'extension' => $info['extension'],
                'size_bytes' => $info['size_bytes'],
                'width' => $master['width'],
                'height' => $master['height'],
                'profile' => $profileKey,
                'focal_x' => $focalX,
                'focal_y' => $focalY,
                'alt_text' => $altText === '' ? null : $altText,
                'checksum' => $checksum,
                'variants' => $variants,
                'is_protected' => 0,
                'is_legacy' => 0,
                'uploaded_by' => $uploadedBy,
            ]);
        } catch (\Throwable $exception) {
            // Si no se pudo registrar, los archivos ya escritos quedarían
            // huérfanos ocupando espacio para siempre. Se deshace el trabajo.
            $this->discardFiles($profileKey, $variants, $originalRelative);

            throw $exception;
        }
    }

    /**
     * Reemplaza la imagen conservando el destino y el encuadre. Devuelve el
     * identificador del nuevo recurso; el anterior debe eliminarse aparte para
     * no dejar sin imagen a los bloques que aún lo usan.
     *
     * @param array<string, mixed> $file
     */
    public function replaceWith(int $mediaId, array $file, ?int $uploadedBy = null, bool $allowSmall = false): int
    {
        $current = $this->media->findById($mediaId);

        if ($current === null) {
            throw UploadException::cannotProcess('la imagen anterior ya no existe.');
        }

        $profileKey = (string) ($current['profile'] ?? '');

        if ($profileKey === '') {
            throw UploadException::cannotProcess('la imagen anterior no tiene un destino definido.');
        }

        return $this->store(
            $file,
            $profileKey,
            $uploadedBy,
            (string) ($current['alt_text'] ?? ''),
            (float) ($current['focal_x'] ?? 0.5),
            (float) ($current['focal_y'] ?? 0.5),
            $allowSmall
        );
    }

    /**
     * Borra los archivos de un recurso y su registro.
     */
    public function delete(int $mediaId): bool
    {
        $media = $this->media->findById($mediaId);

        if ($media === null) {
            return false;
        }

        $original = $this->originalsPath . '/' . (string) $media['path'];

        if (is_file($original)) {
            @unlink($original);
        }

        foreach ($this->media->variants($media) as $variant) {
            $file = $this->publicUploadsPath . '/' . $this->relativeToUploads($variant['path']);

            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->media->remove($mediaId);

        return true;
    }

    /**
     * Rango de la imagen que se conserva al recortar, según la proporción de
     * destino y el punto focal.
     *
     * Es público y estático a propósito: así se puede comprobar con distintos
     * tamaños de entrada sin necesidad de subir archivos.
     *
     * @param array{0: int, 1: int}|null $ratio
     * @return array{x: int, y: int, width: int, height: int}
     */
    public static function cropRect(int $sourceWidth, int $sourceHeight, ?array $ratio, float $focalX, float $focalY): array
    {
        if ($ratio === null || $sourceWidth <= 0 || $sourceHeight <= 0) {
            return ['x' => 0, 'y' => 0, 'width' => max(1, $sourceWidth), 'height' => max(1, $sourceHeight)];
        }

        $targetRatio = $ratio[0] / $ratio[1];
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            // La imagen es más ancha de lo necesario: se recorta a los lados.
            $height = $sourceHeight;
            $width = max(1, (int) round($sourceHeight * $targetRatio));
            $x = (int) round(($sourceWidth - $width) * self::clamp($focalX));
            $y = 0;
        } else {
            // Es más alta: se recorta arriba y abajo.
            $width = $sourceWidth;
            $height = max(1, (int) round($sourceWidth / $targetRatio));
            $x = 0;
            $y = (int) round(($sourceHeight - $height) * self::clamp($focalY));
        }

        return [
            'x' => max(0, min($x, $sourceWidth - $width)),
            'y' => max(0, min($y, $sourceHeight - $height)),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Medidas que se van a generar. Nunca se agranda una imagen: si el original
     * es pequeño, se usan sólo las medidas que quepan en él.
     *
     * @param array<string, mixed> $profile
     * @return array<int, int>
     */
    public static function planVariants(array $profile, int $availableWidth): array
    {
        $sizes = array_map('intval', (array) ($profile['sizes'] ?? [$profile['width'] ?? 0]));
        $sizes = array_values(array_filter($sizes, static fn (int $size): bool => $size > 0));

        if ($sizes === []) {
            $sizes = [max(1, (int) ($profile['width'] ?? $availableWidth))];
        }

        $planned = array_values(array_filter($sizes, static fn (int $size): bool => $size <= $availableWidth));
        $natural = min($availableWidth, max($sizes));

        if (!in_array($natural, $planned, true)) {
            $planned[] = $natural;
        }

        sort($planned);

        return array_values(array_unique(array_map(static fn (int $size): int => max(1, $size), $planned)));
    }

    // -------------------------------------------------------------------------
    // Interno
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $file */
    private function assertUploadSucceeded(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw UploadException::uploadFailed(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'el archivo supera el tamaño permitido por el servidor.',
            UPLOAD_ERR_PARTIAL => 'la carga quedó incompleta.',
            UPLOAD_ERR_NO_FILE => 'no se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'el servidor no pudo guardar el archivo temporal.',
            default => 'error desconocido (' . $error . ').',
        });
    }

    private function detectMime(string $path): string
    {
        if (!function_exists('finfo_open')) {
            $info = @getimagesize($path);

            return is_array($info) ? (string) ($info['mime'] ?? '') : '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return '';
        }

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime;
    }

    private function isAllowed(string $mime): bool
    {
        $allowed = (array) ($this->config['allowed_mime'] ?? []);

        return isset($allowed[$mime]);
    }

    /**
     * Un SVG puede contener código. Se acepta sólo si es un dibujo: ni scripts,
     * ni manejadores de eventos, ni entidades externas.
     */
    private function assertSafeSvg(string $path): void
    {
        $contents = (string) file_get_contents($path, false, null, 0, 65536);
        $lower = strtolower($contents);

        $forbidden = ['<script', 'javascript:', 'onload=', 'onerror=', 'onclick=', '<foreignobject', '<!entity', '<iframe'];

        foreach ($forbidden as $needle) {
            if (str_contains($lower, $needle)) {
                throw UploadException::unsafeSvg();
            }
        }
    }

    /** @return array{width: int, height: int} */
    private function svgDimensions(string $path): array
    {
        $contents = (string) file_get_contents($path, false, null, 0, 4096);

        if (preg_match('/viewBox\s*=\s*"\s*[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)\s*"/i', $contents, $matches) === 1) {
            return ['width' => (int) round((float) $matches[1]), 'height' => (int) round((float) $matches[2])];
        }

        if (preg_match('/width\s*=\s*"([\d.]+)(px)?"[^>]*height\s*=\s*"([\d.]+)(px)?"/i', $contents, $matches) === 1) {
            return ['width' => (int) round((float) $matches[1]), 'height' => (int) round((float) $matches[3])];
        }

        return ['width' => 0, 'height' => 0];
    }

    private function createImage(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$image instanceof GdImage) {
            throw UploadException::cannotProcess('el servidor no puede procesar este formato.');
        }

        return $image;
    }

    /**
     * Algunas fotos de móvil vienen giradas: el dato correcto está en los
     * metadatos EXIF y hay que aplicarlo antes de recortar.
     */
    private function normalizeOrientation(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => null,
        };

        if ($angle === null) {
            return $image;
        }

        $rotated = @imagerotate($image, $angle, 0);

        if (!$rotated instanceof GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * @param array{x: int, y: int, width: int, height: int} $rect
     */
    private function crop(GdImage $source, array $rect): GdImage
    {
        $target = imagecreatetruecolor($rect['width'], $rect['height']);

        if (!$target instanceof GdImage) {
            throw UploadException::cannotProcess('no se pudo reservar memoria para la imagen.');
        }

        // Fondo blanco por si el original tiene transparencia parcial.
        $white = imagecolorallocate($target, 255, 255, 255);

        if ($white !== false) {
            imagefilledrectangle($target, 0, 0, $rect['width'], $rect['height'], $white);
        }

        imagecopy(
            $target,
            $source,
            0,
            0,
            $rect['x'],
            $rect['y'],
            $rect['width'],
            $rect['height']
        );

        return $target;
    }

    /**
     * @param array<int, int> $widths
     * @param array{0: int, 1: int}|null $ratio
     * @param array<int, string> $formats
     * @return array<int, array{width: int, path: string, format: string}>
     */
    private function writeVariants(
        GdImage $cropped,
        string $profileKey,
        string $hash,
        array $widths,
        ?array $ratio,
        array $formats,
    ): array {
        $sourceWidth = imagesx($cropped);
        $sourceHeight = imagesy($cropped);
        $variants = [];

        $directory = $this->publicUploadsPath . '/' . $profileKey;

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw UploadException::cannotProcess('no se pudo crear la carpeta de destino.');
        }

        foreach ($widths as $width) {
            $height = $ratio === null
                ? max(1, (int) round($sourceHeight * ($width / $sourceWidth)))
                : max(1, (int) round($width / ($ratio[0] / $ratio[1])));

            $resized = imagecreatetruecolor($width, $height);

            if (!$resized instanceof GdImage) {
                continue;
            }

            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $cropped, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            foreach ($formats as $format) {
                $fileName = sprintf('%s-%d.%s', $hash, $width, $format === 'jpeg' ? 'jpg' : $format);
                $absolute = $directory . '/' . $fileName;

                if (!$this->save($resized, $absolute, $format)) {
                    continue;
                }

                $variants[] = [
                    'width' => $width,
                    'height' => $height,
                    'path' => 'uploads/' . $profileKey . '/' . $fileName,
                    'format' => $format,
                ];
            }

            imagedestroy($resized);
        }

        return $variants;
    }

    /**
     * Formatos a generar: el moderno y uno compatible. Si el servidor no soporta
     * WebP, se genera sólo el compatible.
     *
     * @return array<int, string>
     */
    private function formatsFor(string $sourceMime): array
    {
        $fallback = in_array($sourceMime, ['image/png', 'image/gif'], true) ? 'png' : 'jpeg';
        $formats = [];

        if (function_exists('imagewebp')) {
            $formats[] = 'webp';
        }

        $formats[] = $fallback;

        return $formats;
    }

    private function save(GdImage $image, string $path, string $format): bool
    {
        $quality = (array) ($this->config['quality'] ?? []);

        return match ($format) {
            'webp' => function_exists('imagewebp') && imagewebp($image, $path, (int) ($quality['webp'] ?? 82)),
            'png' => imagepng($image, $path, (int) ($quality['png'] ?? 6)),
            default => imagejpeg($image, $path, (int) ($quality['jpeg'] ?? 85)),
        };
    }

    /**
     * @param array<int, array{width: int, path: string, format: string}> $variants
     * @return array{width: int, path: string, format: string}|null
     */
    private function masterVariant(array $variants): ?array
    {
        $master = null;

        foreach ($variants as $variant) {
            if ($master === null || $variant['width'] > $master['width']) {
                $master = $variant;
            }
        }

        return $master;
    }

    private function storeOriginal(string $temporaryPath, string $extension, string $hash): string
    {
        $relative = date('Y/m') . '/' . $hash . '.' . $extension;
        $absolute = $this->originalsPath . '/' . $relative;
        $directory = dirname($absolute);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw UploadException::cannotProcess('no se pudo crear la carpeta de originales.');
        }

        // `move_uploaded_file` sólo funciona con archivos que vienen de un
        // formulario; para el resto (por ejemplo la carga del contenido inicial,
        // que lee archivos del propio proyecto) se COPIA.
        //
        // Se copia a propósito y no se renombra: renombrar movería el archivo
        // original del sitio, dejando sin imagen a quien lo referenciaba.
        $stored = @move_uploaded_file($temporaryPath, $absolute) || @copy($temporaryPath, $absolute);

        if (!$stored) {
            throw UploadException::cannotProcess('no se pudo guardar el archivo original.');
        }

        return $relative;
    }

    /**
     * Copia el SVG ya guardado a la carpeta pública. No se recorta porque no es
     * una imagen de mapa de bits.
     */
    private function publishSvgCopy(string $originalRelative, string $profileKey, string $hash): string
    {
        $directory = $this->publicUploadsPath . '/' . $profileKey;

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $relative = 'uploads/' . $profileKey . '/' . $hash . '.svg';
        $absolute = $this->publicUploadsPath . '/' . $profileKey . '/' . $hash . '.svg';

        if (!@copy($this->originalsPath . '/' . $originalRelative, $absolute)) {
            throw UploadException::cannotProcess('no se pudo publicar el logo.');
        }

        return $relative;
    }

    /**
     * Borra archivos que quedaron sin registro en la base de datos: por ejemplo
     * si el guardado se interrumpió a medias.
     *
     * Sólo toca archivos con el nombre que genera este pipeline
     * (`<hash>-<ancho>.<ext>`), nunca nada más que haya en esas carpetas.
     *
     * @return array{files: int, bytes: int}
     */
    public function pruneOrphans(): array
    {
        $known = [];

        foreach ($this->media->allReferences() as $row) {
            if (!empty($row['public_path'])) {
                $known[(string) $row['public_path']] = true;
            }

            if (!empty($row['path'])) {
                $known['__original__' . (string) $row['path']] = true;
            }

            $decoded = is_string($row['variants'] ?? null) ? json_decode((string) $row['variants'], true) : null;

            foreach (is_array($decoded) ? $decoded : [] as $variant) {
                if (is_array($variant) && !empty($variant['path'])) {
                    $known[(string) $variant['path']] = true;
                }
            }
        }

        $removed = 0;
        $bytes = 0;

        foreach ($this->generatedFiles($this->publicUploadsPath) as $file) {
            $relative = 'uploads/' . $this->relativeToUploads($file);

            if (isset($known[$relative])) {
                continue;
            }

            $bytes += (int) filesize($file);

            if (@unlink($file)) {
                $removed++;
            }
        }

        foreach ($this->generatedFiles($this->originalsPath) as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file, strlen($this->originalsPath))), '/');

            if (isset($known['__original__' . $relative])) {
                continue;
            }

            $bytes += (int) filesize($file);

            if (@unlink($file)) {
                $removed++;
            }
        }

        return ['files' => $removed, 'bytes' => $bytes];
    }

    /**
     * Recorre las carpetas buscando únicamente archivos con el patrón que genera
     * este pipeline, en cualquier profundidad.
     *
     * @return array<int, string>
     */
    private function generatedFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if ($name === 'index.html') {
                continue;
            }

            // Variante: hash-ancho.ext  ·  Original: hash.ext
            if (preg_match('/^[0-9a-f]{16}(-\d+)?\.[a-z0-9]+$/', $name) === 1) {
                $found[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        return $found;
    }

    /**
     * @param array<int, array{width: int, height: int, path: string, format: string}> $variants
     */
    private function discardFiles(string $profileKey, array $variants, ?string $originalRelative): void
    {
        foreach ($variants as $variant) {
            $file = $this->publicUploadsPath . '/' . $this->relativeToUploads($variant['path']);

            if (is_file($file)) {
                @unlink($file);
            }
        }

        if ($originalRelative !== null) {
            $file = $this->originalsPath . '/' . $originalRelative;

            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function relativeToUploads(string $publicPath): string
    {
        $prefix = 'uploads/';

        return str_starts_with($publicPath, $prefix) ? substr($publicPath, strlen($prefix)) : $publicPath;
    }

    private function clampFocal(float $value): float
    {
        $focal = (array) ($this->config['focal'] ?? []);

        return self::clamp($value, (float) ($focal['min'] ?? 0.0), (float) ($focal['max'] ?? 1.0));
    }

    private static function clamp(float $value, float $min = 0.0, float $max = 1.0): float
    {
        return max($min, min($max, $value));
    }
}
