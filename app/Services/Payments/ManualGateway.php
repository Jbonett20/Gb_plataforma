<?php

declare(strict_types=1);

namespace GB\Services\Payments;

use RuntimeException;

/** Proveedor explícito para el modo transitorio: nunca simula un cobro. */
final class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function configured(): bool
    {
        return false;
    }

    public function createCheckout(array $payment): array
    {
        throw new RuntimeException('La pasarela de pagos todavía no está activa.');
    }

    /**
     * El modo manual no recibe notificaciones de ninguna pasarela. Devolver null
     * es deliberado: mientras no haya un proveedor real configurado, ninguna
     * notificación puede conceder acceso.
     */
    public function verifyWebhook(array $payload, array $headers): ?array
    {
        return null;
    }
}