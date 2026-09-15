<?php
declare(strict_types=1);
$courses = is_array($courses ?? null) ? $courses : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h1 class="h3 mb-1">Cursos</h1><p class="text-muted mb-0">Administra la información visible del catálogo.</p></div>
  <a class="btn btn-primary" href="<?= e(url('admin/cursos/nuevo')) ?>"><i class="bi bi-plus-lg me-1"></i>Nuevo curso</a>
</div>
<div class="admin-card card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Curso</th><th>Acceso</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($courses as $course): ?>
    <tr><td><strong><?= e((string) $course['title']) ?></strong><br><small class="text-muted">/<?= e((string) $course['slug']) ?></small></td>
      <td><?= ($course['access_type'] ?? '') === 'paid' ? 'De pago' : 'Gratuito' ?></td>
      <td><span class="badge text-bg-light"><?= e((string) $course['status']) ?></span></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/cursos/' . (int) $course['id'] . '/editar')) ?>">Editar</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/cursos/' . (int) $course['id'] . '/temario')) ?>">Temario</a>
        <form method="post" action="<?= e(url('admin/cursos/' . (int) $course['id'])) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="_method" value="DELETE"><button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('El curso saldrá del catálogo público y su temario dejará de estar accesible. No se borra: podrás recuperarlo durante 30 días. ¿Continuar?');">Enviar a papelera</button></form>
      </td></tr>
  <?php endforeach; ?>
  <?php if ($courses === []): ?><tr><td colspan="4" class="text-center py-4">Todavía no hay cursos.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>