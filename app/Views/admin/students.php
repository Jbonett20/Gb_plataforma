<?php

declare(strict_types=1);

/**
 * Listado de estudiantes del panel.
 *
 * @var array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} $students
 * @var string $search
 */

$students = is_array($students ?? null) ? $students : [];
$items = is_array($students['items'] ?? null) ? $students['items'] : [];
$page = (int) ($students['page'] ?? 1);
$pages = (int) ($students['pages'] ?? 1);
$total = (int) ($students['total'] ?? 0);
$search = (string) ($search ?? '');
?>
<h1 class="h3 mb-1">Estudiantes</h1>
<p class="text-muted">Personas con cuenta en la plataforma y los cursos que tienen vigentes.</p>

<form method="get" action="<?= e(url('admin/estudiantes')) ?>" class="row g-2 align-items-end mb-3">
  <div class="col-md-8">
    <label class="form-label" for="student-search">Buscar</label>
    <input class="form-control" id="student-search" name="q" type="search" value="<?= e($search) ?>"
           placeholder="Nombre, correo o teléfono">
  </div>
  <div class="col-md-4 d-grid">
    <button class="btn btn-primary" type="submit">Buscar</button>
  </div>
</form>

<div class="admin-card card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Estudiante</th><th>Contacto</th><th>Cursos vigentes</th><th>Alta</th><th class="text-end">Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $student): ?>
          <tr>
            <td><strong><?= e(trim((string) $student['name'] . ' ' . (string) ($student['last_name'] ?? ''))) ?></strong></td>
            <td class="small"><?= e((string) $student['email']) ?><br><span class="text-muted"><?= e((string) ($student['phone'] ?? '')) ?></span></td>
            <td><?= (int) ($student['active_courses'] ?? 0) ?></td>
            <td class="small text-muted"><?= e((string) $student['created_at']) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/estudiantes/' . (int) $student['id'])) ?>">Ver ficha</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
          <tr><td colspan="5" class="text-center py-4">
            <?= $search === '' ? 'Todavía no hay estudiantes registrados.' : 'Ningún estudiante coincide con esa búsqueda.' ?>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-2"><?= $total ?> estudiante(s) en total.</p>

<?php if ($pages > 1): ?>
  <nav aria-label="Páginas de estudiantes">
    <ul class="pagination justify-content-center">
      <?php for ($number = 1; $number <= $pages; $number++): ?>
        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= e(url('admin/estudiantes?page=' . $number . ($search === '' ? '' : '&q=' . rawurlencode($search)))) ?>"><?= $number ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>
