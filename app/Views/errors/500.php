<?php

declare(strict_types=1);

/**
 * Inconveniente del servidor.
 *
 * Delegado: el detalle técnico queda en storage/logs/php-errors.log y el
 * visitante ve un mensaje amable y en español.
 */

$error = \GB\Support\ErrorResponder::copyFor(500);

require GB_APP_PATH . '/Views/errors/page.php';
