<?php

declare(strict_types=1);

/**
 * Formulario de una sección, generado a partir de `config/admin_labels.php`.
 *
 * Los campos no se escriben aquí: se declaran en la configuración y esta vista
 * los dibuja según su tipo. Añadir un campo es añadir una línea allí.
 *
 * @var array<string, mixed> $block
 * @var array<string, mixed> $definition
 * @var array<int, array<string, mixed>> $fields
 * @var array<int, array<string, mixed>> $settingsFields
 * @var array<int, array<string, mixed>> $itemFields
 * @var array<int, array<string, mixed>> $items
 * @var array<string, mixed> $errors
 * @var string|null $message
 */

$block = is_array($block ?? null) ? $block : [];
$definition = is_array($definition ?? null) ? $definition : [];
$fields = is_array($fields ?? null) ? $fields : [];
$settingsFields = is_array($settingsFields ?? null) ? $settingsFields : [];
$itemFields = is_array($itemFields ?? null) ? $itemFields : [];
$items = is_array($items ?? null) ? $items : [];
$errors = is_array($errors ?? null) ? $errors : [];
$message = (string) ($message ?? '');

$blockId = (int) ($block['id'] ?? 0);
$itemSingular = (string) ($definition['item_singular'] ?? 'Elemento');

$errorFor = static function (array $field) use ($errors): string {
    return (string) ($errors['block'][$field['name']] ?? '');
};

/** Dibuja un campo según su tipo, sin que la vista tenga que saber más. */
$renderField = static function (array $field, string $namePattern, mixed $value, string $error = ''): void {
    $id = preg_replace('/[^a-z0-9_-]+/i', '-', $namePattern) ?? 'campo';
    $label = (string) $field['label'];
    $type = (string) $field['type'];
    ?>
    <div class="col-md-6">
      <?php if ($type === 'media'): ?>
        <?php
        $name = $namePattern;
        $profile = (string) $field['profile'];
        $value = (int) ($value ?? 0);
        $label = $label;
        require GB_APP_PATH . '/Views/partials/image-field.php';
        ?>
      <?php else: ?>
        <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea' || $type === 'rich'): ?>
          <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($namePattern) ?>" rows="4"
                    <?= $field['required'] ? 'required' : '' ?>><?= e((string) ($value ?? '')) ?></textarea>
        <?php elseif ($type === 'number'): ?>
          <input class="form-control" id="<?= e($id) ?>" name="<?= e($namePattern) ?>" type="number"
                 value="<?= e((string) ($value ?? '')) ?>">
        <?php else: ?>
          <input class="form-control" id="<?= e($id) ?>" name="<?= e($namePattern) ?>" type="text"
                 value="<?= e((string) ($value ?? '')) ?>" <?= $field['required'] ? 'required' : '' ?>>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="text-danger small mt-1"><?= e($error) ?></div>
      <?php elseif (!empty($field['help'])): ?>
        <div class="form-text"><?= e((string) $field['help']) ?></div>
      <?php endif; ?>
    </div>
    <?php
};
?>
<div class="mb-3">
  <a href="<?= e(url('admin/bloques')) ?>">&larr; Secciones del sitio</a>
  <h1 class="h3 mt-2 mb-0"><?= e((string) ($definition['label'] ?? $block['label'])) ?></h1>
  <p class="text-muted mb-0"><?= e((string) ($definition['help'] ?? '')) ?></p>
</div>

<?php if ($message !== ''): ?>
  <div class="alert alert-danger"><?= e($message) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/bloques/' . $blockId)) ?>" class="admin-card card p-4 mb-3">
  <?= csrf_field() ?>

  <?php if ($fields !== [] || $settingsFields !== []): ?>
    <h2 class="h6 mb-3">Contenido de la sección</h2>
    <div class="row g-3">
      <?php foreach ($fields as $field): ?>
        <?php $renderField($field, (string) $field['name'], $field['value'], $errorFor($field)); ?>
      <?php endforeach; ?>
      <?php foreach ($settingsFields as $field): ?>
        <?php
        $name = 'settings[' . $field['name'] . ']';
        $renderField($field, $name, $field['value']);
        ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($itemFields !== []): ?>
    <h2 class="h6 mt-4 mb-1"><?= e($definition['item_singular'] ?? 'Elementos') ?></h2>
    <p class="text-muted small">Se muestran en el orden de esta lista. Para quitar uno, marca «Quitar».</p>

    <?php $rows = $items === [] ? [['id' => 0, 'values' => []]] : $items; ?>
    <?php foreach ($rows as $index => $item): ?>
      <div class="border rounded p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong class="small"><?= e($itemSingular) ?> <?= (int) $index + 1 ?></strong>
          <?php if ((int) ($item['id'] ?? 0) > 0): ?>
            <label class="small text-muted mb-0">
              <input type="checkbox" name="items[<?= (int) $index ?>][_remove]" value="1"> Quitar
            </label>
          <?php endif; ?>
        </div>
        <?php if ((int) ($item['id'] ?? 0) > 0): ?>
          <input type="hidden" name="items[<?= (int) $index ?>][id]" value="<?= (int) $item['id'] ?>">
        <?php endif; ?>
        <div class="row g-3">
          <?php foreach ($itemFields as $field): ?>
            <?php
            $key = (string) $field['name'];
            $value = $item['values'][$key] ?? null;
            $error = (string) ($errors['items'][(string) $index] ?? '');
            $renderField($field, 'items[' . (int) $index . '][' . $key . ']', $value, $error);
            ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p class="text-muted small mb-0">
      Para añadir otro, guarda primero y vuelve a entrar: la lista crece con un elemento nuevo.
    </p>
  <?php endif; ?>

  <div class="mt-4 d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit">Guardar borrador</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/bloques/' . $blockId . '/previa')) ?>">Ver como borrador</a>
  </div>
</form>

<div class="admin-card card p-4">
  <h2 class="h6">Publicar</h2>
  <p class="text-muted small">
    Publicar copia lo que hay ahora en el sitio público y guarda una versión en el historial,
    para poder deshacer.
  </p>
  <div class="d-flex flex-wrap gap-2">
    <form method="post" action="<?= e(url('admin/bloques/' . $blockId . '/publicar')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-success" type="submit"
              onclick="return confirm('Los visitantes verán estos cambios de inmediato. ¿Publicar?');">
        Publicar cambios
      </button>
    </form>
    <form method="post" action="<?= e(url('admin/bloques/' . $blockId . '/deshacer')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger" type="submit"
              onclick="return confirm('Se volverá a la versión publicada anterior. ¿Continuar?');">
        Deshacer último cambio
      </button>
    </form>
  </div>
</div>
