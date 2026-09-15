<?php
declare(strict_types=1);
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$errorFor = static fn (string $field): string => (string) ($errors[$field][0] ?? '');
$value = static fn (string $field, mixed $default = ''): mixed => $values[$field] ?? $default;
$id = (int) ($values['id'] ?? 0);
?>
<div class="mb-4"><a href="<?= e(url('admin/cursos')) ?>">&larr; Cursos</a><h1 class="h3 mt-2"><?= e($heading) ?></h1></div>
<?php if ($message !== ''): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
<form method="post" action="<?= e(url($id > 0 ? 'admin/cursos/' . $id : 'admin/cursos')) ?>" class="admin-card card p-4">
  <?= csrf_field() ?><?php if ($id > 0): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
  <div class="row g-3">
    <div class="col-12">
      <?php
      $name = 'cover_media_id';
      $profile = 'course_cover';
      $fieldValue = $value;
      $value = (int) ($values['cover_media_id'] ?? 0);
      $label = 'Portada del curso';
      require GB_APP_PATH . '/Views/partials/image-field.php';
      $value = $fieldValue;
      ?>
    </div>
    <?php foreach ([['title','Título'],['slug','Dirección del curso'],['subtitle','Subtítulo'],['summary','Resumen'],['category','Categoría'],['instructor_name','Docente'],['currency','Moneda'],['level','Nivel'],['duration_minutes','Duración en minutos'],['price','Precio']] as $field): ?>
      <div class="col-md-<?= in_array($field[0], ['summary'], true) ? '12' : '6' ?>"><label class="form-label" for="<?= e($field[0]) ?>"><?= e($field[1]) ?></label><input class="form-control" id="<?= e($field[0]) ?>" name="<?= e($field[0]) ?>" value="<?= e((string) $value($field[0])) ?>" <?= $errorFor($field[0]) !== '' ? 'aria-invalid="true"' : '' ?>><?php if ($errorFor($field[0]) !== ''): ?><div class="text-danger small mt-1"><?= e($errorFor($field[0])) ?></div><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="col-md-6"><label class="form-label" for="access_type">Tipo de acceso</label><select class="form-select" id="access_type" name="access_type"><option value="free" <?= $value('access_type','free') === 'free' ? 'selected' : '' ?>>Gratuito</option><option value="paid" <?= $value('access_type') === 'paid' ? 'selected' : '' ?>>De pago</option></select></div>
    <div class="col-md-6"><label class="form-label" for="status">Estado</label><select class="form-select" id="status" name="status"><option value="draft" <?= $value('status','draft') === 'draft' ? 'selected' : '' ?>>Borrador</option><option value="published" <?= $value('status') === 'published' ? 'selected' : '' ?>>Publicado</option><option value="hidden" <?= $value('status') === 'hidden' ? 'selected' : '' ?>>Oculto</option></select><div class="form-text">Para publicar debe tener portada, descripción y al menos una lección activa.</div></div>
    <div class="col-12"><label class="form-label" for="description">Descripción completa</label><textarea class="form-control" id="description" name="description" rows="7"><?= e((string) $value('description')) ?></textarea></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary" type="submit">Guardar curso</button></div>
</form>
<?php if ($id > 0): ?>
  <div class="mt-3"><a class="btn btn-outline-secondary" href="<?= e(url('admin/cursos/' . $id . '/temario')) ?>">Editar módulos y lecciones</a></div>
<?php endif; ?>