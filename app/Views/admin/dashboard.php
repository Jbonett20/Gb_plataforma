<?php

declare(strict_types=1);

/**
 * Resumen del panel.
 *
 * @var int $students
 * @var int $publishedCourses
 * @var array{count: int, total: float, currency: string} $sales
 * @var int $subscribers
 * @var int $pendingAppointments
 * @var array{title: string, intro: string, steps: array<int, array{title: string, text: string, url: string, icon: string}>}|null $tour
 */

$students = (int) ($students ?? 0);
$publishedCourses = (int) ($publishedCourses ?? 0);
$sales = is_array($sales ?? null) ? $sales : ['count' => 0, 'total' => 0.0, 'currency' => ''];
$subscribers = (int) ($subscribers ?? 0);
$pendingAppointments = (int) ($pendingAppointments ?? 0);

$salesLabel = number_format((float) $sales['total'], 2, ',', '.')
    . ($sales['currency'] === '' ? '' : ' ' . $sales['currency']);

$cards = [
    ['label' => 'Estudiantes registrados', 'value' => (string) $students, 'icon' => 'bi-people'],
    ['label' => 'Cursos publicados', 'value' => (string) $publishedCourses, 'icon' => 'bi-journal-bookmark'],
    ['label' => 'Ventas de este mes', 'value' => $salesLabel . ' (' . (int) $sales['count'] . ')', 'icon' => 'bi-cash-coin'],
    ['label' => 'Suscriptores activos', 'value' => (string) $subscribers, 'icon' => 'bi-envelope-at'],
    ['label' => 'Citas sin atender', 'value' => (string) $pendingAppointments, 'icon' => 'bi-calendar-check'],
];
?>
<h1 class="h3 mb-4">Resumen</h1>

<?php require GB_APP_PATH . '/Views/admin/partials/tour.php'; ?>

<div class="row g-3">
  <?php foreach ($cards as $card): ?>
    <div class="col-md-6 col-xl-4">
      <div class="admin-card card p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <span class="fs-3 text-muted"><i class="bi <?= e($card['icon']) ?>"></i></span>
          <div>
            <p class="text-muted mb-1 small"><?= e($card['label']) ?></p>
            <p class="fs-5 mb-0 fw-semibold"><?= e($card['value']) ?></p>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<p class="text-muted small mt-4 mb-0">
  Las ventas corresponden a pagos confirmados del mes en curso. Mientras la pasarela de pagos
  esté desactivada, este valor permanece en cero porque el sitio no cobra.
</p>
