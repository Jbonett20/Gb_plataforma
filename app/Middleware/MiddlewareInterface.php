<?php

declare(strict_types=1);

namespace GB\Middleware;

use Closure;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Contrato de los middleware encadenados por el enrutador.
 *
 * `$args` recibe lo que se declare después de dos puntos en la ruta, por
 * ejemplo `role:SuperAdmin` llega como ['SuperAdmin'].
 */
interface MiddlewareInterface
{
    /** @param array<int, string> $args */
    public function handle(Request $request, Closure $next, array $args = []): Response;
}
