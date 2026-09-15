<?php

declare(strict_types=1);

/**
 * Ficha de un estudiante: cursos, compras y acceso manual.
 *
 * @var array<string, mixed> $student
 * @var array<int, array<string, mixed>> $enrollments
 * @var array<int, array<string, mixed>> $purchases
 * @var array<int, array<string, mixed>> $courses
 */

$student = is_array($student ?? null) ? $student : [];
$enrollments = is_array($enrollments ?? null) ? $enrollments : [];
$purchases = is_array($purchases ?? null) ? $purchases : [];
$courses = is_array($courses ?? null) ? $courses : [];
$studentId = (int) ($student['id'] ?? 0);

$statusLabels = [
    'active' => 'Vigente',
    'revoked' => 'Revocado',
    'expired' => 'Vencido',
];

$sourceLabels = [
    'free' => 'Curso gratuito',
    'purchase' => 'Compra',
    'granted' => 'Concedido a mano',
];

$paymentLabels = [
    'pending' => 'Pendiente',
    'paid' => 'Pagada',
    'failed' => 'Fallida',
    'refunded' => 'Reembolsada',
    'cancelled' => 'Cancelada',
];

$available = array_values(array_filter(
    $courses,
    static fn (array $course): bool => ($course['status'] ?? '') === 'published'
));
?>
<div class="mb-3">
  <a href="<?= e(url('admin/estudiantes')) ?>">&larr; Estudiantes</a>
  <h1 class="h3 mt-2 mb-0"><?= e(trim((string) $student['name'] . ' ' . (string) ($student['last_name'] ?? ''))) ?></h1>
  <p class="text-muted mb-0">
    <?= e((string) $student['email']) ?>
    <?php if (!empty($student['phone'])): ?> · <?= e((string) $student['phone']) ?><?php endif; ?>
    · Alta: <?= e((string) $student['created_at']) ?>
  </p>
</div>

<?php if (($message ?? '') !== ''): ?>
  <div class="alert alert-warning"><?= e((string) $message) ?></div>
<?php endif; ?>

<h2 class="h5 mt-4">Cursos del estudiante</h2>
<div class="admin-card card mb-4">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Curso</th><th>Origen</th><th>Estado</th><th>Avance</th><th class="text-end">Acceso</th></tr>
      </thead>
      <tbody>
        <?php foreach ($enrollments as $enrollment): ?>
          <tr>
            <td><strong><?= e((string) $enrollment['course_title']) ?></strong>
              <?php if (($enrollment['status'] ?? '') === 'revoked' && !empty($enrollment['revoke_reason'])): ?>
                <br><small class="text-muted">Motivo: <?= e((string) $enrollment['revoke_reason']) ?></small>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($sourceLabels[$enrollment['source']] ?? (string) $enrollment['source']) ?></td>
            <td><?= e($statusLabels[$enrollment['status']] ?? (string) $enrollment['status']) ?></td>
            <td><?= (int) $enrollment['progress_percent'] ?>%</td>
            <td class="text-end">
              <?php if (($enrollment['status'] ?? '') === 'active'): ?>
                <form method="post"
                      action="<?= e(url('admin/estudiantes/' . $studentId . '/accesos/' . (int) $enrollment['id'] . '/revocar')) ?>"
                      class="d-flex gap-2 justify-content-end">
                  <?= csrf_field() ?>
                  <label class="visually-hidden" for="motivo-<?= (int) $enrollment['id'] ?>">Motivo de la revocación</label>
                  <input class="form-control form-control-sm w-auto" id="motivo-<?= (int) $enrollment['id'] ?>"
                         name="reason" placeholder="Motivo" maxlength="255">
                  <button class="btn btn-sm btn-outline-danger" type="submit"
                          onclick="return confirm('Se retirará el acceso a este curso. El historial se conserva. ¿Continuar?');">
                    Revocar acceso
                  </button>
                </form>
              <?php else: ?>
                <span class="text-muted small">Sin acceso vigente</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($enrollments === []): ?>
          <tr><td colspan="5" class="text-center py-4">Este estudiante todavía no tiene cursos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($available !== []): ?>
  <div class="admin-card card p-3 mb-4">
    <h2 class="h6">Conceder acceso a un curso</h2>
    <p class="text-muted small">
      Úsalo para ventas acordadas por fuera de la plataforma o accesos de cortesía.
      Queda registrado como concesión manual con tu nombre y la fecha.
    </p>
    <form method="post" action="<?= e(url('admin/estudiantes/' . $studentId . '/accesos')) ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <div class="col-md-8">
        <label class="form-label" for="course_id">Curso publicado</label>
        <select class="form-select" id="course_id" name="course_id" required>
          <option value="">Elige un curso…</option>
          <?php foreach ($available as $course): ?>
            <option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-primary" type="submit">Conceder acceso</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<h2 class="h5">Compras</h2>
<div class="admin-card card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Curso</th><th>Fecha</th><th>Monto</th><th>Estado</th><th>Referencia</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($purchases as $purchase): ?>
          <tr>
            <td><?= e((string) ($purchase['course_title'] ?? '')) ?></td>
            <td class="small text-muted"><?= e((string) $purchase['created_at']) ?></td>
            <td><?= e(number_format((float) $purchase['amount'], 2, ',', '.') . ' ' . (string) $purchase['currency']) ?></td>
            <td><?= e($paymentLabels[$purchase['status']] ?? (string) $purchase['status']) ?></td>
            <td class="small"><?= e((string) $purchase['reference']) ?></td>
            <td class="text-end">
              <?php $paymentId = (int) $purchase['id']; ?>
              <?php if (($purchase['status'] ?? '') === 'paid'): ?>
                <form method="post"
                      action="<?= e(url('admin/estudiantes/' . $studentId . '/compras/' . $paymentId . '/reembolsar')) ?>"
                      class="d-flex gap-2 justify-content-end">
                  <?= csrf_field() ?>
                  <label class="visually-hidden" for="motivo-reembolso-<?= $paymentId ?>">Motivo del reembolso</label>
                  <input class="form-control form-control-sm w-auto" id="motivo-reembolso-<?= $paymentId ?>"
                         name="reason" placeholder="Motivo" maxlength="255">
                  <button class="btn btn-sm btn-outline-danger" type="submit"
                          onclick="return confirm('Se registrará el reembolso y se retirará el acceso al curso. El historial se conserva. ¿Continuar?');">
                    Reembolsar
                  </button>
                </form>
              <?php elseif (($purchase['status'] ?? '') === 'pending'): ?>
                <form method="post"
                      action="<?= e(url('admin/estudiantes/' . $studentId . '/compras/' . $paymentId . '/conciliar')) ?>"
                      class="d-flex gap-2 justify-content-end">
                  <?= csrf_field() ?>
                  <label class="visually-hidden" for="nota-conciliar-<?= $paymentId ?>">Nota de conciliación</label>
                  <input class="form-control form-control-sm w-auto" id="nota-conciliar-<?= $paymentId ?>"
                         name="note" placeholder="Comprobación del cobro" maxlength="255">
                  <button class="btn btn-sm btn-outline-primary" type="submit"
                          onclick="return confirm('Se marcará la compra como pagada y se concederá el acceso. Úsalo solo si verificaste el cobro por fuera. ¿Continuar?');">
                    Marcar como pagada
                  </button>
                </form>
              <?php else: ?>
                <span class="text-muted small">Sin acciones</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($purchases === []): ?>
          <tr><td colspan="6" class="text-center py-4">Sin compras registradas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-2">
  Reembolsar retira el acceso y conserva el historial. Marcar como pagada es el respaldo para cuando
  la pasarela no confirma: queda registrado en la auditoría que ese acceso se concedió a mano.
</p>
