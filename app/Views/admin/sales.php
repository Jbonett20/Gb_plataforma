<?php

declare(strict_types=1);

/**
 * Ventas del panel: movimiento del período con filtros.
 *
 * @var string $from
 * @var string $to
 * @var string $status
 * @var array<string, string> $statusLabels
 * @var array<string, array{count: int, total: float}> $totals
 * @var array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} $sales
 */

$from = (string) ($from ?? '');
$to = (string) ($to ?? '');
$status = (string) ($status ?? '');
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
$totals = is_array($totals ?? null) ? $totals : [];
$sales = is_array($sales ?? null) ? $sales : [];
$items = is_array($sales['items'] ?? null) ? $sales['items'] : [];
$page = (int) ($sales['page'] ?? 1);
$pages = (int) ($sales['pages'] ?? 1);

$money = static fn (float $amount, string $currency = 'COP'): string => number_format($amount, 2, ',', '.') . ' ' . $currency;

$resumen = [];
foreach ($statusLabels as $key => $label) {
    $resumen[$key] = [
        'label' => $label,
        'count' => (int) ($totals[$key]['count'] ?? 0),
        'total' => (float) ($totals[$key]['total'] ?? 0),
    ];
}

$query = static function (array $extra) use ($from, $to, $status): string {
    $params = array_filter([
        'from' => $from,
        'to' => $to,
        'status' => $status,
    ], static fn (string $value): bool => $value !== '');

    return url('admin/ventas') . '?' . http_build_query($params + $extra);
};
?>
<h1 class="h3 mb-1">Ventas</h1>
<p class="text-muted">Movimiento del período elegido. Todo lo que aparece aquí salió de una orden registrada.</p>

<form method="get" action="<?= e(url('admin/ventas')) ?>" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label" for="from">Desde</label>
    <input class="form-control" id="from" name="from" type="date" value="<?= e($from) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label" for="to">Hasta</label>
    <input class="form-control" id="to" name="to" type="date" value="<?= e($to) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label" for="status">Estado</label>
    <select class="form-select" id="status" name="status">
      <option value="">Todos</option>
      <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-grid">
    <button class="btn btn-primary" type="submit">Aplicar filtros</button>
  </div>
</form>

<div class="row g-3 mb-4">
  <?php foreach ($resumen as $key => $row): ?>
    <?php if ($row['count'] === 0) { continue; } ?>
    <div class="col-md-6 col-xl-3">
      <div class="admin-card card p-3 h-100">
        <p class="text-muted small mb-1"><?= e($row['label']) ?></p>
        <p class="fs-5 fw-semibold mb-0"><?= e($money($row['total'])) ?></p>
        <p class="text-muted small mb-0"><?= (int) $row['count'] ?> orden(es)</p>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Fecha</th><th>Estudiante</th><th>Curso</th><th>Monto</th><th>Estado</th><th>Referencia</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $sale): ?>
          <tr>
            <td class="small text-muted"><?= e((string) $sale['created_at']) ?></td>
            <td>
              <?= e(trim((string) $sale['student_name'] . ' ' . (string) ($sale['student_last_name'] ?? ''))) ?>
              <br><small class="text-muted"><?= e((string) $sale['student_email']) ?></small>
            </td>
            <td><?= e((string) $sale['course_title']) ?></td>
            <td><?= e($money((float) $sale['amount'], (string) $sale['currency'])) ?></td>
            <td><?= e($statusLabels[$sale['status']] ?? (string) $sale['status']) ?></td>
            <td class="small"><?= e((string) $sale['reference']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
          <tr><td colspan="6" class="text-center py-4">No hay órdenes en ese período con esos filtros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-2">
  <?= (int) ($sales['total'] ?? 0) ?> orden(es) en el período mostrado.
  El acceso a un curso se concede cuando la compra queda pagada, no cuando se crea la orden.
</p>

<?php if ($pages > 1): ?>
  <nav aria-label="Páginas de ventas">
    <ul class="pagination justify-content-center">
      <?php for ($number = 1; $number <= $pages; $number++): ?>
        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= e($query(['page' => $number])) ?>"><?= $number ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>
