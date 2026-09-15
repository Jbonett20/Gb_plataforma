<?php

declare(strict_types=1);

/**
 * Campo de imagen con ajuste de encuadre.
 *
 * Muestra la imagen ya recortada a la proporción de su lugar en la página, de
 * modo que quien administra ve exactamente lo que verá el visitante. Arrastrando
 * sobre la vista previa se elige qué parte se conserva.
 *
 * La vista previa usa el mismo criterio que el recorte del servidor
 * (`object-fit: cover` con punto focal), así que lo que se ve es lo que queda.
 *
 * @var string $name nombre del campo que guarda el identificador de la imagen
 * @var string $profile destino definido en config/images.php
 * @var int|null $value identificador actual
 * @var string|null $label
 * @var float $focalX
 * @var float $focalY
 */

$profiles = (array) config('images.profiles', []);
$definition = $profiles[$profile] ?? [];
$ratio = $definition['ratio'] ?? null;
$ratioValue = is_array($ratio) ? ($ratio[0] . '/' . $ratio[1]) : '';
$currentUrl = media_url($value);
$focalX = $focalX ?? 0.5;
$focalY = $focalY ?? 0.5;
?>
<div class="image-field" data-image-field
     data-profile="<?= e($profile) ?>"
     data-ratio="<?= e($ratioValue) ?>"
     data-upload-url="<?= e(url('admin/imagenes')) ?>">

  <?php if (!empty($label)): ?>
    <label class="form-label"><?= e($label) ?></label>
  <?php endif; ?>

  <div class="image-field__preview<?= $ratioValue === '' ? ' image-field__preview--free' : '' ?>"
       data-image-field-box>
    <img src="<?= e($currentUrl ?? '') ?>" alt="" data-image-field-image
         style="object-position: <?= e(round($focalX * 100)) ?>% <?= e(round($focalY * 100)) ?>%"
         <?= $currentUrl === null ? 'hidden' : '' ?>>
    <p class="image-field__empty" data-image-field-empty <?= $currentUrl === null ? '' : 'hidden' ?>>
      Sin imagen
    </p>
  </div>

  <div class="image-field__controls">
    <label class="btn btn-sm btn-outline-secondary mb-0">
      <i class="bi bi-upload me-1"></i>Elegir imagen
      <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
             data-image-field-input hidden>
    </label>

    <button type="button" class="btn btn-sm btn-outline-secondary" data-image-field-clear
            <?= $currentUrl === null ? 'hidden' : '' ?>>
      <i class="bi bi-x-lg me-1"></i>Quitar
    </button>

    <span class="image-field__status small" data-image-field-status role="status" aria-live="polite"></span>
  </div>

  <p class="form-text mb-0" data-image-field-hint>
    <?php if ($ratioValue !== ''): ?>
      Arrastra sobre la imagen para elegir qué parte se conserva. Se recorta sola a la proporción de este lugar.
    <?php else: ?>
      Se conserva la proporción original de la imagen.
    <?php endif; ?>
  </p>

  <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>" data-image-field-value>
  <input type="hidden" name="<?= e($name) ?>_focal_x" value="<?= e($focalX) ?>" data-image-field-focal-x>
  <input type="hidden" name="<?= e($name) ?>_focal_y" value="<?= e($focalY) ?>" data-image-field-focal-y>
</div>
