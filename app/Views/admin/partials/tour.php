<?php

declare(strict_types=1);

/**
 * Recorrido guiado del primer ingreso (tarea 11.5).
 *
 * Se muestra una sola vez, en el Resumen. No bloquea nada y se puede cerrar
 * sin hacer ningún paso: es una guía, no una obligación.
 *
 * @var array{title: string, intro: string, steps: array<int, array{title: string, text: string, url: string, icon: string}>} $tour
 */

$tour = is_array($tour ?? null) ? $tour : [];
$steps = is_array($tour['steps'] ?? null) ? $tour['steps'] : [];

if ($steps !== []):
?>
<div class="admin-card card border-primary border-opacity-25 mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1"><?= e((string) ($tour['title'] ?? 'Primeros pasos')) ?></h2>
        <p class="text-muted mb-0"><?= e((string) ($tour['intro'] ?? '')) ?></p>
      </div>
      <span class="badge text-bg-primary align-self-start">Sólo se muestra esta vez</span>
    </div>

    <ol class="list-unstyled row g-3 mb-3">
      <?php foreach ($steps as $index => $step): ?>
        <li class="col-md-6">
          <a class="d-flex gap-3 text-decoration-none h-100 p-3 rounded border bg-body-tertiary"
             href="<?= e($step['url']) ?>">
            <span class="fs-4 text-primary"><i class="bi <?= e($step['icon']) ?>"></i></span>
            <span>
              <span class="d-block fw-semibold text-body">
                <?= ($index + 1) ?>. <?= e($step['title']) ?>
              </span>
              <span class="d-block small text-muted"><?= e($step['text']) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>

    <form method="post" action="<?= e(url('admin/recorrido')) ?>" class="d-flex flex-wrap align-items-center gap-2">
      <?= csrf_field() ?>
      <button class="btn btn-primary" type="submit">Terminar el recorrido</button>
      <span class="text-muted small">Siempre puedes volver a esta pantalla desde el menú.</span>
    </form>
  </div>
</div>
<?php endif; ?>
