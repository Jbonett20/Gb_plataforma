<?php

declare(strict_types=1);

namespace GB\Services\Payments;

interface PaymentGateway
{
    public function key(): string;

    public function configured(): bool;

    /** @param array<string, mixed> $payment @return array{redirect_url: string, external_id: string|null} */
    public function createCheckout(array $payment): array;

    /**
     * Comprueba la autenticidad de una notificación de la pasarela.
     *
     * Devuelve el evento normalizado cuando la firma es válida, o null cuando no
     * puede verificarse. Ante la duda se devuelve null: una notificación sin
     * verificar jamás debe conceder acceso.
     *
     * @param array<string, mixed> $payload cuerpo recibido, ya decodificado
     * @param array<string, string> $headers cabeceras de la petición
     * @return array{external_id: string, reference: string, status: string, type: string}|null
     */
    public function verifyWebhook(array $payload, array $headers): ?array;
}