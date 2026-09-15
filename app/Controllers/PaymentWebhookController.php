<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Services\PaymentService;
use GB\Support\Request;
use GB\Support\Response;

/**
 * Notificaciones de la pasarela.
 *
 * Esta dirección es pública por necesidad: la llama el proveedor, no una
 * persona. Por eso no lleva token de sesión y toda su confianza se apoya en la
 * verificación de la firma. Responde siempre con un reconocimiento para que el
 * proveedor no reintente indefinidamente.
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(\GB\Support\View $view, private PaymentService $payments)
    {
        parent::__construct($view);
    }

    public function handle(Request $request): Response
    {
        $provider = (string) $request->routeParam('provider');

        $raw = $request->rawBody();
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            return $this->respondJson(['ok' => false, 'message' => 'Cuerpo no reconocido.'], 400);
        }

        $headers = [];
        foreach (['X-Signature', 'X-Event-Checksum', 'X-Signature-Sha256', 'X-Webhook-Signature'] as $name) {
            $value = $request->header($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        $outcome = $this->payments->confirmFromWebhook($provider, $payload, $headers, $request->ip());

        // Una firma no verificada se rechaza de forma explícita: el proveedor
        // debe poder distinguir "recibido" de "aceptado".
        if ($outcome['result'] === 'invalid_signature' || $outcome['result'] === 'provider_mismatch') {
            return $this->respondJson(['ok' => false, 'message' => 'Notificación no verificada.'], 401);
        }

        // Los reenvíos y las órdenes ya pagadas se reconocen sin volver a actuar.
        return $this->respondJson([
            'ok' => true,
            'result' => $outcome['result'],
            'reference' => $outcome['reference'],
        ]);
    }
}
