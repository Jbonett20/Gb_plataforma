<?php

declare(strict_types=1);

/**
 * Contenido no disponible.
 *
 * Delegado: los textos y el marcado viven en un solo lugar
 * (`ErrorResponder::copyFor()` y `errors/page.php`). Este archivo se conserva
 * para que `render('errors.404')` siga funcionando desde cualquier controlador.
 */

$error = \GB\Support\ErrorResponder::copyFor(404);

require GB_APP_PATH . '/Views/errors/page.php';
