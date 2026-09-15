<?php
declare(strict_types=1);
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$errorFor = static fn (string $field): string => (string) ($errors[$field][0] ?? '');
$fieldValue = static fn (string $field, mixed $default = ''): mixed => $values[$field] ?? $default;
$id = (int) ($values['id'] ?? 0);
?>
<div class="mb-4"><a href="<?= e(url('admin/plantillas')) ?>">&larr; Plantillas</a><h1 class="h3 mt-2"><?= e($heading) ?></h1></div>
<?php if ($message !== ''): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
<form method="post" action="<?= e(url($id > 0 ? 'admin/plantillas/' . $id : 'admin/plantillas')) ?>" enctype="multipart/form-data" class="admin-card card p-4"><div class="row g-3"><?= csrf_field() ?><?php if ($id > 0): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
  <div class="col-12"><?php $name = 'preview_media_id'; $profile = 'template_thumb'; $value = (int) ($values['preview_media_id'] ?? 0); $label = 'Imagen de vista previa'; require GB_APP_PATH . '/Views/partials/image-field.php'; ?></div>
  <?php foreach ([['title','Título'],['slug','Dirección de la plantilla'],['category','Categoría'],['summary','Resumen']] as $field): ?><div class="col-md-6"><label class="form-label" for="<?= e($field[0]) ?>"><?= e($field[1]) ?></label><input class="form-control" id="<?= e($field[0]) ?>" name="<?= e($field[0]) ?>" value="<?= e((string) $fieldValue($field[0])) ?>"><?php if ($errorFor($field[0]) !== ''): ?><div class="text-danger small mt-1"><?= e($errorFor($field[0])) ?></div><?php endif; ?></div><?php endforeach; ?>
  <div class="col-12"><label class="form-label" for="description">Descripción</label><textarea class="form-control" id="description" name="description" rows="6"><?= e((string) $fieldValue('description')) ?></textarea></div>
  <div class="col-md-6"><label class="form-label" for="file">Archivo descargable</label><input class="form-control" id="file" name="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip" <?= $id > 0 ? '' : 'required' ?>><div class="form-text">PDF, Word, Excel o ZIP. Se guarda fuera del directorio público.</div><?php if ($id > 0): ?><div class="small text-muted mt-1">Actual: <?= e((string) $fieldValue('original_file_name')) ?></div><?php endif; ?><?php if ($errorFor('file') !== ''): ?><div class="text-danger small mt-1"><?= e($errorFor('file')) ?></div><?php endif; ?></div>
  <div class="col-md-3"><label class="form-label" for="status">Estado</label><select class="form-select" id="status" name="status"><option value="active" <?= $fieldValue('status','active') === 'active' ? 'selected' : '' ?>>Visible</option><option value="inactive" <?= $fieldValue('status') === 'inactive' ? 'selected' : '' ?>>Oculta</option></select></div>
  <div class="col-md-3"><label class="form-label" for="sort_order">Orden</label><input class="form-control" id="sort_order" name="sort_order" type="number" value="<?= e((string) $fieldValue('sort_order', 0)) ?>"></div>
  <div class="col-12 form-check"><input class="form-check-input" id="requires_registration" name="requires_registration" value="1" type="checkbox" <?= !empty($values['requires_registration']) ? 'checked' : '' ?>><label class="form-check-label" for="requires_registration">Pedir registro antes de descargar</label></div>
</div><div class="mt-4"><button class="btn btn-primary" type="submit">Guardar plantilla</button></div></form>