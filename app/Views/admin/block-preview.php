<?php

declare(strict_types=1);

/**
 * Vista previa del borrador de una sección.
 *
 * Se dibuja con la maquetación real del sitio para que lo que se ve aquí sea lo
 * que quedará publicado. Está dentro del panel, con aviso de que no es el sitio
 * en vivo, para que nadie confunda el borrador con lo publicado.
 *
 * @var string $blockLabel
 * @var string $previewHtml
 * @var bool $isPublished
 */

$blockLabel = (string) ($blockLabel ?? '');
$previewHtml = (string) ($previewHtml ?? '');
$isPublished = (bool) ($isPublished ?? false);
?>
<div class="mb-3">
  <a href="<?= e(url('admin/bloques')) ?>">&larr; Secciones del sitio</a>
  <h1 class="h3 mt-2 mb-1">Vista previa: <?= e($blockLabel) ?></h1>
  <p class="text-muted mb-0">
    Así se vería el borrador guardado.
    <?= $isPublished
        ? 'Es un borrador: si no lo publicas, los visitantes siguen viendo la versión publicada.'
        : 'Esta sección todavía no se ha publicado, así que no aparece en el sitio.' ?>
  </p>
</div>

<div class="admin-card card p-0 overflow-hidden">
  <?= $previewHtml ?>
</div>
