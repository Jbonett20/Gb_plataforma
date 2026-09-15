<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\SubscriberRepository;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class SubscriberController extends Controller
{
    public function __construct(View $view, private SubscriberRepository $subscribers)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->respond('admin.subscribers', $this->pageData([
            'title' => 'Suscriptores | Panel',
            'subscribers' => $this->subscribers->adminPage(max(1, $request->int('page', 1)), (int) config('app.pagination.admin_per_page', 25)),
        ]), 'layouts.admin');
    }

    public function export(Request $request): Response
    {
        $columns = ['Nombre', 'Apellidos', 'Teléfono', 'Correo', 'Estado', 'Origen', 'Confirmado', 'Alta'];
        $lines = [self::csvLine($columns)];

        foreach ($this->subscribers->allForExport() as $subscriber) {
            $lines[] = self::csvLine([
                $subscriber['name'], $subscriber['last_name'], $subscriber['phone'], $subscriber['email'],
                $subscriber['status'], $subscriber['source'], $subscriber['confirmed_at'], $subscriber['created_at'],
            ]);
        }

        return new Response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="suscriptores-' . date('Y-m-d') . '.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param array<int, mixed> $values */
    private static function csvLine(array $values): string
    {
        return implode(',', array_map(static function (mixed $value): string {
            $text = (string) ($value ?? '');
            return '"' . str_replace('"', '""', $text) . '"';
        }, $values));
    }
}