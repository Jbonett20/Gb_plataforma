<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\CourseRepository;
use GB\Models\EnrollmentRepository;
use GB\Models\PaymentEventRepository;
use GB\Models\PaymentRepository;
use GB\Services\Payments\PaymentGatewayManager;
use GB\Support\Audit;
use GB\Support\FormResult;
use GB\Support\Request;
use RuntimeException;
use Throwable;

final class PaymentService
{
    public function __construct(
        private CourseRepository $courses,
        private PaymentRepository $payments,
        private PaymentGatewayManager $gateways,
        private PaymentEventRepository $events,
        private EnrollmentRepository $enrollments,
        private Audit $audit,
    ) {
    }

    public function start(int $userId, string $slug, Request $request): FormResult
    {
        if (!(bool) config('app.auth.payments_enabled', false)) {
            return FormResult::failed([], message: 'Las compras todavía no están activas.');
        }

        $course = $this->courses->findBySlug($slug);
        if ($course === null || ($course['access_type'] ?? '') !== 'paid') {
            return FormResult::failed([], message: 'Ese curso no está disponible para compra.');
        }

        try {
            $this->gateways->assertReady();
        } catch (RuntimeException $exception) {
            return FormResult::failed([], message: $exception->getMessage());
        }

        $reference = 'GB-' . strtoupper(bin2hex(random_bytes(8)));
        $paymentId = $this->payments->createPending([
            'reference' => $reference, 'user_id' => $userId, 'course_id' => (int) $course['id'],
            'gateway' => $this->gateways->active()->key(), 'amount' => (string) $course['price'],
            'currency' => (string) ($course['currency'] ?: config('app.payments.currency', 'COP')),
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        $checkout = $this->gateways->active()->createCheckout([
            'id' => $paymentId, 'reference' => $reference, 'amount' => $course['price'],
            'currency' => $course['currency'], 'course' => $course,
        ]);

        return FormResult::ok(['payment_id' => $paymentId, 'reference' => $reference] + $checkout);
    }

    /**
     * Procesa una notificación de la pasarela.
     *
     * Tres reglas que sostienen la confianza en este flujo:
     *   1. Sin firma verificada no se hace nada: la primera comprobación es
     *      siempre `verifyWebhook`, y si falla se registra el intento y se
     *      responde como rechazado.
     *   2. Un evento repetido no vuelve a producir efectos: se reconoce por su
     *      identificador en `webhook_events`.
     *   3. El acceso se concede aquí y sólo aquí, nunca desde la página de
     *      retorno del navegador.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array{result: string, reference: string|null}
     */
    public function confirmFromWebhook(string $provider, array $payload, array $headers, string $ip = '0.0.0.0'): array
    {
        $gateway = $this->gateways->active();

        if ($gateway->key() !== $provider) {
            return ['result' => 'provider_mismatch', 'reference' => null];
        }

        $event = $gateway->verifyWebhook($payload, $headers);

        if ($event === null) {
            // Queda constancia del intento, aunque no se pueda identificar el
            // evento: es justo lo que hay que poder auditar.
            $this->events->record([
                'provider' => $provider,
                'external_event_id' => 'inválido-' . hash('sha256', json_encode($payload) ?: ''),
                'event_type' => null,
                'payment_reference' => null,
                'signature_valid' => 0,
                'processed' => 1,
                'processed_at' => date('Y-m-d H:i:s'),
                'result' => 'Firma no verificada: no se concedió acceso',
                'ip' => $ip,
            ]);

            return ['result' => 'invalid_signature', 'reference' => null];
        }

        $recorded = $this->events->record([
            'provider' => $provider,
            'external_event_id' => $event['external_id'],
            'event_type' => $event['type'],
            'payment_reference' => $event['reference'],
            'signature_valid' => 1,
            'ip' => $ip,
        ]);

        if (!$recorded['is_new']) {
            // Reenvío: se reconoce y no se repite ningún efecto.
            return ['result' => 'duplicate', 'reference' => $event['reference']];
        }

        $result = $this->applyEvent($event);

        $this->events->markProcessed($recorded['id'], $result['message']);

        return ['result' => $result['result'], 'reference' => $event['reference']];
    }

    /**
     * @param array{external_id: string, reference: string, status: string, type: string} $event
     * @return array{result: string, message: string}
     */
    private function applyEvent(array $event): array
    {
        $payment = $this->payments->findByReference($event['reference']);

        if ($payment === null) {
            return ['result' => 'reference_not_found', 'message' => 'No existe una orden con esa referencia'];
        }

        $status = strtolower($event['status']);

        if (in_array($status, ['failed', 'declined', 'cancelled'], true)) {
            $this->payments->markFailed((int) $payment['id'], $status);

            return ['result' => 'marked_failed', 'message' => 'La pasarela informó un pago no completado'];
        }

        if ($status !== 'paid' && $status !== 'approved') {
            return ['result' => 'ignored_status', 'message' => 'Estado informado sin efecto: ' . $status];
        }

        return $this->activate((int) $payment['id'], $event['external_id'], $event['reference']);
    }

    /**
     * Marca la orden como pagada y habilita el curso, en una sola unidad.
     */
    private function activate(int $paymentId, string $externalId, string $reference): array
    {
        $payment = $this->payments->findById($paymentId);

        if ($payment === null) {
            return ['result' => 'reference_not_found', 'message' => 'Orden inexistente'];
        }

        if ((string) $payment['status'] === 'paid') {
            return ['result' => 'already_paid', 'message' => 'La orden ya estaba pagada'];
        }

        $claimed = $this->payments->markPaid($paymentId, $externalId);

        if (!$claimed) {
            // Otra notificación se adelantó: no se concede un segundo acceso.
            return ['result' => 'already_paid', 'message' => 'La orden ya estaba pagada'];
        }

        $enrollmentId = $this->enrollments->grantPurchase(
            (int) $payment['user_id'],
            (int) $payment['course_id'],
            $paymentId
        );

        $this->record(
            'payment_confirmed',
            (int) $payment['course_id'],
            sprintf('Pago confirmado por la pasarela (referencia %s)', $reference)
        );

        return [
            'result' => 'access_granted',
            'message' => sprintf('Acceso concedido (inscripción %d)', $enrollmentId),
        ];
    }

    private function record(string $action, int $courseId, string $summary): void
    {
        try {
            $this->audit->log(
                action: $action,
                entityType: 'course',
                entityId: $courseId,
                summary: $summary
            );
        } catch (Throwable) {
            // La auditoría es informativa: el acceso ya está concedido.
        }
    }

    /**
     * Registra el reembolso de una compra y retira el acceso que concedió.
     *
     * El historial no se borra: la compra queda marcada como reembolsada y la
     * inscripción como revocada, con el motivo. Así siempre se puede responder
     * "qué pasó con este dinero".
     */
    public function refund(int $paymentId, ?int $adminId, string $reason): FormResult
    {
        $payment = $this->payments->findDetail($paymentId);

        if ($payment === null) {
            return FormResult::failed([], message: 'Esa compra no existe.');
        }

        if ((string) $payment['status'] !== 'paid') {
            return FormResult::failed([], message: 'Sólo se puede reembolsar una compra que esté pagada.');
        }

        $reason = trim($reason);
        $reason = $reason === '' ? 'Reembolso registrado desde el panel' : $reason;

        $done = $this->payments->transaction(function () use ($paymentId, $adminId, $reason): bool {
            $refunded = $this->payments->markRefunded($paymentId, $adminId, $reason);

            if (!$refunded) {
                return false;
            }

            $this->enrollments->revokeByPayment($paymentId, $adminId, $reason);

            return true;
        });

        if (!$done) {
            return FormResult::failed([], message: 'La compra ya había sido reembolsada.');
        }

        $this->record(
            'payment_refunded',
            (int) $payment['course_id'],
            sprintf('Reembolso de la compra %s: %s', (string) $payment['reference'], $reason)
        );

        return FormResult::ok([], 'Reembolso registrado y acceso retirado. El historial se conserva.');
    }

    /**
     * Marca a mano una compra como pagada.
     *
     * Es el respaldo operativo para cuando la confirmación de la pasarela no
     * llega: quien administra comprueba el cobro por fuera y lo deja asentado.
     * Queda en la auditoría con su autor, para que después se sepa que este
     * acceso no vino de una confirmación automática.
     */
    public function reconcile(int $paymentId, ?int $adminId, string $note): FormResult
    {
        $payment = $this->payments->findDetail($paymentId);

        if ($payment === null) {
            return FormResult::failed([], message: 'Esa compra no existe.');
        }

        if ((string) $payment['status'] === 'paid') {
            return FormResult::failed([], message: 'Esa compra ya figura como pagada.');
        }

        if ((string) $payment['status'] === 'refunded') {
            return FormResult::failed([], message: 'Esa compra fue reembolsada y no se puede conciliar.');
        }

        $note = trim($note);
        $note = $note === '' ? 'Conciliación manual' : $note;

        $claimed = $this->payments->markPaid($paymentId, 'manual-' . (string) ($adminId ?? 'sin-autor'));

        if (!$claimed) {
            return FormResult::failed([], message: 'La compra cambió de estado mientras la revisabas. Vuelve a intentarlo.');
        }

        $this->enrollments->grantPurchase(
            (int) $payment['user_id'],
            (int) $payment['course_id'],
            $paymentId
        );

        $this->record(
            'payment_reconciled',
            (int) $payment['course_id'],
            sprintf('Compra %s marcada como pagada a mano: %s', (string) $payment['reference'], $note)
        );

        return FormResult::ok([], 'Compra conciliada y acceso concedido. Queda registrado en la auditoría.');
    }
}