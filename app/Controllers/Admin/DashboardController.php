<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\AppointmentRepository;
use GB\Models\CourseRepository;
use GB\Models\PaymentRepository;
use GB\Models\SubscriberRepository;
use GB\Models\UserRepository;
use GB\Services\OnboardingService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Pantalla de entrada del panel.
 *
 * Responde a la primera pregunta de quien administra: "¿cómo va todo?". Muestra
 * cifras del período en curso y no datos acumulados de por vida, que no dicen
 * nada útil para decidir.
 */
final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        private UserRepository $users,
        private CourseRepository $courses,
        private PaymentRepository $payments,
        private SubscriberRepository $subscribers,
        private AppointmentRepository $appointments,
        private OnboardingService $onboarding,
        private Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-01 00:00:00', strtotime('+1 month'));
        $sales = $this->payments->paidSummary($monthStart, $monthEnd);

        return $this->respond('admin.dashboard', $this->pageData([
            'title' => 'Resumen | Panel',
            'students' => $this->users->countByRole('Student'),
            'publishedCourses' => $this->courses->countPublished(),
            'sales' => $sales,
            'subscribers' => $this->subscribers->countActive(),
            'pendingAppointments' => $this->appointments->countNew(),
            'tour' => $this->onboarding->pending($this->auth->id()),
        ]), 'layouts.admin');
    }
}
