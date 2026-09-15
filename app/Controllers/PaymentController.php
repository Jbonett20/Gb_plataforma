<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\PaymentRepository;
use GB\Services\PaymentService;
use GB\Services\Payments\PaymentGatewayManager;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class PaymentController extends Controller
{
    public function __construct(View $view, private PaymentService $payments, private PaymentRepository $orders, private Auth $auth, private PaymentGatewayManager $gateways)
    {
        parent::__construct($view);
    }

    public function start(Request $request): Response
    {
        $result = $this->payments->start(
            (int) ($this->auth->user()['id'] ?? 0),
            (string) $request->routeParam('slug'),
            $request
        );

        if (!$result->succeeded()) {
            return $this->respond('public.payment-unavailable', $this->pageData([
                'title' => 'Pago no disponible | ' . (string) config('app.name'),
                'message' => $result->message(), 'bodyClass' => 'page-payment',
            ]), 'layouts.public', 503);
        }

        return $this->redirect((string) ($result->values()['redirect_url'] ?? '/mi-cuenta'));
    }

    public function result(Request $request): Response
    {
        $reference = trim($request->string('reference'));
        $payment = $reference === '' ? null : $this->orders->findByReference($reference);

        return $this->respond('public.payment-result', $this->pageData([
            'title' => 'Resultado del pago | ' . (string) config('app.name'),
            'payment' => $payment, 'bodyClass' => 'page-payment-result',
        ]));
    }

    public function adminStatus(Request $request): Response
    {
        $gateway = null;
        $configured = false;
        $gatewayError = '';

        try {
            $gateway = $this->gateways->active();
            $configured = $gateway->configured();
        } catch (\RuntimeException $exception) {
            $gatewayError = $exception->getMessage();
        }

        return $this->respond('admin.payment-status', $this->pageData([
            'title' => 'Pagos | Panel', 'enabled' => (bool) config('app.auth.payments_enabled', false),
            'gateway' => $gateway?->key(), 'configured' => $configured, 'gatewayError' => $gatewayError,
        ]), 'layouts.admin');
    }
}