<?php

declare(strict_types=1);

/**
 * Alias de middleware.
 *
 * Las rutas de config/routes.php usan estos nombres cortos:
 *   ['auth', 'role:SuperAdmin', 'csrf']
 */

return [
    'aliases' => [
        'headers'  => \GB\Middleware\SecurityHeadersMiddleware::class,
        'https'    => \GB\Middleware\ForceHttpsMiddleware::class,
        'auth'     => \GB\Middleware\AuthMiddleware::class,
        'guest'    => \GB\Middleware\GuestMiddleware::class,
        'role'     => \GB\Middleware\RoleMiddleware::class,
        'csrf'     => \GB\Middleware\CsrfMiddleware::class,
        'throttle' => \GB\Middleware\ThrottleMiddleware::class,
        'password.change' => \GB\Middleware\MustChangePasswordMiddleware::class,
    ],

    /**
     * Middleware que se aplican a toda petición, en orden.
     * `https` no hace nada mientras APP_ENV no sea "production".
     *
     * `password.change` va en la lista global porque es una condición que debe
     * cumplirse en cualquier pantalla: así no depende de que cada ruta se
     * acuerde de incluirla.
     */
    'global' => [
        'headers',
        'https',
        'password.change',
    ],

    /**
     * Cuántos intentos se permiten por acción antes de bloquear temporalmente.
     * `default` se aplica a cualquier acción que no aparezca en la lista.
     */
    'limits' => [
        'default'        => ['attempts' => 20, 'minutes' => 15],
        'login'          => ['attempts' => 5,  'minutes' => 15],
        'register'       => ['attempts' => 5,  'minutes' => 60],
        'password_reset' => ['attempts' => 3,  'minutes' => 60],
        'contact'        => ['attempts' => 5,  'minutes' => 10],
        'newsletter'     => ['attempts' => 5,  'minutes' => 10],
        'checkout'       => ['attempts' => 10, 'minutes' => 15],
    ],

    /**
     * Direcciones internas que usan los middleware para redirigir.
     */
    'paths' => [
        'login'   => 'ingresar',
        'admin'   => 'admin',
        'student' => 'mi-cuenta',
    ],
];
