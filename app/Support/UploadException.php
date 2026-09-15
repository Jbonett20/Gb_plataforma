<?php

declare(strict_types=1);

namespace GB\Support;

use RuntimeException;

/**
 * Problema al subir o procesar un archivo.
 *
 * El mensaje se muestra tal cual a quien administra el sitio, así que está
 * redactado en español y dice qué hacer, nunca qué falló por dentro.
 */
final class UploadException extends RuntimeException
{
    /** @param array<string, string> $context datos para el panel, nunca técnicos */
    public function __construct(string $message, private array $context = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return $this->context;
    }

    public static function notAnImage(): self
    {
        return new self('El archivo que intentas subir no es una imagen. Se aceptan JPG, PNG, WEBP, GIF o SVG.');
    }

    public static function tooLarge(float $maxMb, float $sizeMb): self
    {
        return new self(sprintf(
            'La imagen pesa %.1f MB y el máximo para este lugar es %.1f MB. Prueba con una versión más liviana.',
            $sizeMb,
            $maxMb
        ));
    }

    public static function tooSmall(int $minWidth, int $minHeight, int $width, int $height): self
    {
        return new self(sprintf(
            'La imagen mide %d × %d píxeles y se necesitan al menos %d × %d para que se vea nítida. '
            . 'Puedes subir otra o continuar de todos modos.',
            $width,
            $height,
            $minWidth,
            $minHeight
        ), ['code' => 'too_small']);
    }

    public static function uploadFailed(string $reason): self
    {
        return new self('No se pudo recibir el archivo: ' . $reason . ' Vuelve a intentarlo.');
    }

    public static function unsafeSvg(): self
    {
        return new self('El archivo SVG contiene código que no está permitido por seguridad. '
            . 'Exporta el logo como PNG si el problema continúa.');
    }

    public static function cannotProcess(string $detail): self
    {
        return new self('No pudimos procesar esta imagen. Prueba a guardarla de nuevo como JPG o PNG. Detalle: ' . $detail);
    }

    public static function unknownProfile(string $key): self
    {
        return new self(sprintf('El lugar de destino "%s" no está definido en la configuración de imágenes.', $key));
    }
}
