<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\PaymentRepository;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

/**
 * Ventas del panel.
 *
 * Muestra el movimiento de un período acotado y no de toda la vida del sitio:
 * para decidir hace falta saber qué pasó este mes, no el acumulado histórico.
 * Los filtros son de fecha y de estado, que es lo que se pregunta de verdad
 * ("¿qué quedó pendiente?", "¿cuánto se reembolsó?").
 */
final class SalesController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'failed', 'refunded', 'cancelled'];

    public function __construct(View $view, private PaymentRepository $payments)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        // Por defecto, el mes en curso.
        $defaultFrom = date('Y-m-01');
        $defaultTo = date('Y-m-01', strtotime('first day of next month'));

        $from = $this->normalizeDate($request->string('from')) ?? $defaultFrom;
        $to = $this->normalizeDate($request->string('to')) ?? $defaultTo;

        // Si las fechas llegan invertidas, se ordenan en lugar de devolver un
        // error: quien consulta quería ver ese tramo, no pelearse con el filtro.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $status = $request->string('status');
        $status = in_array($status, self::STATUSES, true) ? $status : '';

        $fromStamp = $from . ' 00:00:00';
        $toStamp = $to . ' 00:00:00';

        return $this->respond('admin.sales', $this->pageData([
            'title' => 'Ventas | Panel',
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'statusLabels' => [
                'pending' => 'Pendiente', 'paid' => 'Pagada', 'failed' => 'Fallida',
                'refunded' => 'Reembolsada', 'cancelled' => 'Cancelada',
            ],
            'totals' => $this->payments->statusTotals($fromStamp, $toStamp),
            'sales' => $this->payments->salesPage(
                $fromStamp,
                $toStamp,
                $status,
                max(1, $request->int('page', 1)),
                (int) config('app.pagination.admin_per_page', 25)
            ),
        ]), 'layouts.admin');
    }

    /**
     * Acepta sólo fechas completas y reales; cualquier otra cosa se ignora y se
     * usa el valor por defecto.
     */
    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
