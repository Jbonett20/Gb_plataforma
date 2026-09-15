<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\CourseRepository;
use GB\Models\PaymentRepository;
use GB\Models\UserRepository;
use GB\Services\EnrollmentService;
use GB\Services\PaymentService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Estudiantes vistos desde el panel.
 *
 * Muestra a cada persona con sus cursos, sus compras y sus accesos, y permite
 * conceder o revocar acceso a mano para los casos que la pasarela no cubre.
 * La concesión manual nunca se disfraza de compra: la auditoría distingue una
 * de otra.
 */
final class StudentController extends Controller
{
    public function __construct(
        View $view,
        private UserRepository $users,
        private CourseRepository $courses,
        private PaymentRepository $paymentOrders,
        private PaymentService $payments,
        private EnrollmentService $enrollments,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.students', [
            'title' => 'Estudiantes | Panel',
            'search' => $request->string('q'),
            'students' => $this->users->studentsPage(
                $request->string('q'),
                max(1, $request->int('page', 1)),
                (int) config('app.pagination.admin_per_page', 25)
            ),
            'message' => '',
        ]);
    }

    public function show(Request $request): Response
    {
        $student = $this->users->findStudent((int) $request->routeParam('id'));

        if ($student === null) {
            return $this->redirect('/admin/estudiantes');
        }

        $studentId = (int) $student['id'];

        return $this->admin('admin.student', [
            'title' => (string) $student['name'] . ' | Panel',
            'student' => $student,
            'enrollments' => $this->enrollments->historyFor($studentId),
            'purchases' => $this->paymentOrders->forUser($studentId),
            'courses' => $this->courses->adminList(),
            'message' => '',
        ]);
    }

    public function grant(Request $request): Response
    {
        $studentId = (int) $request->routeParam('id');
        $result = $this->enrollments->grantManually(
            $studentId,
            $request->int('course_id'),
            $this->auth->id()
        );

        return $this->redirectWith(
            '/admin/estudiantes/' . $studentId,
            $result->message(),
            $result->succeeded() ? 'success' : 'warning'
        );
    }

    public function revoke(Request $request): Response
    {
        $studentId = (int) $request->routeParam('id');
        $result = $this->enrollments->revokeManually(
            (int) $request->routeParam('enrollment'),
            $studentId,
            $this->auth->id(),
            $request->string('reason')
        );

        return $this->redirectWith(
            '/admin/estudiantes/' . $studentId,
            $result->message(),
            $result->succeeded() ? 'warning' : 'danger'
        );
    }

    /**
     * Reembolsa una compra del estudiante.
     *
     * Se comprueba que la compra sea suya: sin esto, cambiando un número en la
     * dirección se podría reembolsar la compra de otra persona.
     */
    public function refund(Request $request): Response
    {
        $studentId = (int) $request->routeParam('id');
        $paymentId = (int) $request->routeParam('payment');

        if (!$this->ownsPayment($paymentId, $studentId)) {
            return $this->redirectWith('/admin/estudiantes/' . $studentId, 'Esa compra no pertenece a este estudiante.', 'danger');
        }

        $result = $this->payments->refund($paymentId, $this->auth->id(), $request->string('reason'));

        return $this->redirectWith(
            '/admin/estudiantes/' . $studentId,
            $result->message(),
            $result->succeeded() ? 'warning' : 'danger'
        );
    }

    /**
     * Marca a mano una compra como pagada cuando la pasarela no confirmó.
     */
    public function reconcile(Request $request): Response
    {
        $studentId = (int) $request->routeParam('id');
        $paymentId = (int) $request->routeParam('payment');

        if (!$this->ownsPayment($paymentId, $studentId)) {
            return $this->redirectWith('/admin/estudiantes/' . $studentId, 'Esa compra no pertenece a este estudiante.', 'danger');
        }

        $result = $this->payments->reconcile($paymentId, $this->auth->id(), $request->string('note'));

        return $this->redirectWith(
            '/admin/estudiantes/' . $studentId,
            $result->message(),
            $result->succeeded() ? 'success' : 'danger'
        );
    }

    /**
     * La compra indicada pertenece a ese estudiante.
     */
    private function ownsPayment(int $paymentId, int $studentId): bool
    {
        $payment = $this->paymentOrders->findDetail($paymentId);

        return $payment !== null && (int) $payment['user_id'] === $studentId;
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}
