<?php

declare(strict_types=1);

namespace GB\Services\Payments;

use RuntimeException;

final class PaymentGatewayManager
{
    /** @param array<string, PaymentGateway> $gateways */
    public function __construct(private array $gateways, private string $selected)
    {
    }

    public function active(): PaymentGateway
    {
        if (!isset($this->gateways[$this->selected])) {
            throw new RuntimeException('La pasarela configurada no está disponible.');
        }

        return $this->gateways[$this->selected];
    }

    public function assertReady(): void
    {
        if (!$this->active()->configured()) {
            throw new RuntimeException('No hay una pasarela de pagos configurada y activa.');
        }
    }
}