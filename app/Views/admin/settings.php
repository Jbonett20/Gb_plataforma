<?php

declare(strict_types=1);

/**
 * Ajustes del sitio.
 *
 * El formulario se dibuja a partir de lo declarado en `config/admin_labels.php`:
 * añadir un ajuste allí lo hace aparecer aquí, con su etiqueta y su ayuda, sin
 * tocar esta vista.
 *
 * @var array<string, mixed> $screen
 * @var string $screenKey
 * @var array<string, array<string, mixed>> $screens
 * @var array<string, string> $values
 * @var array<string, array<int, string>> $errors
 * @var string $message
 */

$screen = is_array($screen ?? null) ? $screen : ['label' => '', 'help' => '', 'path' => '', 'fields' => []];
$screenKey = (string) ($screenKey ?? '');
$screens = is_array($screens ?? null) ? $screens : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$message = (string) ($message ?? '');
$fields = is_array($screen['fields'] ?? null) ? $screen['fields'] : [];

$errorFor = static fn (string $key): string => (string) ($errors[$key][0] ?? '');
?>
<h1 class="h3 mb-1"><?= e((string) $screen['label']) ?></h1>
<p class="text-muted">Cambia aquí lo que se ve en el sitio sin tocar el diseño.</p>

<?php if ($message !== ''): ?>
  <div class="alert alert-danger"><?= e($message) ?></div>
<?php endif; ?>

<?php if (count($screens) > 1): ?>
  <nav class="mb-3" aria-label="Apartados de ajustes">
    <ul class="nav nav-pills gap-2">
      <?php foreach ($screens as $key => $other): ?>
        <li class="nav-item">
          <a class="nav-link <?= $key === $screenKey ? 'active' : '' ?>"
             href="<?= e(url((string) $other['path'])) ?>"><?= e((string) $other['label']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
<?php endif; ?>

<form method="post" action="<?= e(url((string) $screen['path'])) ?>" class="admin-card card p-4">
  <?= csrf_field() ?>

  <div class="row g-3">
    <?php foreach ($fields as $key => $field): ?>
      <?php
      $type = (string) ($field['type'] ?? 'text');
      $id = 'ajuste-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $key);
      $value = (string) ($values[$key] ?? '');
      ?>
      <div class="col-12">
        <?php if ($type === 'boolean'): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="<?= e((string) $key) ?>"
                   value="1" <?= $value === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($id) ?>"><?= e((string) $field['label']) ?></label>
          </div>
        <?php else: ?>
          <label class="form-label" for="<?= e($id) ?>"><?= e((string) $field['label']) ?></label>
          <?php if ($type === 'textarea'): ?>
            <textarea class="form-control" id="<?= e($id) ?>" name="<?= e((string) $key) ?>" rows="3"><?= e($value) ?></textarea>
          <?php else: ?>
            <input class="form-control" id="<?= e($id) ?>" name="<?= e((string) $key) ?>"
                   type="<?= $type === 'url' ? 'url' : ($type === 'email' ? 'email' : 'text') ?>"
                   value="<?= e($value) ?>"
                   <?= isset($field['max']) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?>>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($errorFor((string) $key) !== ''): ?>
          <div class="text-danger small mt-1"><?= e($errorFor((string) $key)) ?></div>
        <?php elseif (!empty($field['help'])): ?>
          <div class="form-text"><?= e((string) $field['help']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4">
    <button class="btn btn-primary" type="submit">Guardar cambios</button>
  </div>
</form>

<p class="text-muted small mt-3 mb-0"><?= e((string) ($screen['help'] ?? '')) ?></p>
