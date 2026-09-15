<?php

declare(strict_types=1);

namespace GB\Support;

use RuntimeException;

/**
 * Se lanza cuando falta una variable obligatoria en el archivo .env.
 *
 * El mensaje está redactado para que un administrador técnico sepa qué hacer
 * sin tener que leer el código.
 */
final class ConfigurationException extends RuntimeException
{
}
