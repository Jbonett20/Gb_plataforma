<?php

declare(strict_types=1);

/**
 * Asistente de instalación.
 *
 * @var array<int, array{label: string, status: string, detail: string, hint: string}> $checks
 * @var bool $hasErrors
 * @var string $phpVersion
 * @var string $basePath
 * @var string $storagePath
 * @var bool $storageWritable
 * @var bool $composerInstalled
 */

use GB\Support\HealthCheck;

$icons = [
    HealthCheck::OK => 'bi-check-circle-fill text-success',
    HealthCheck::WARNING => 'bi-exclamation-triangle-fill text-warning',
    HealthCheck::ERROR => 'bi-x-circle-fill text-danger',
];
?>
<section class="section bg-light-section">
  <div class="container py-5">

    <div class="row justify-content-center mb-4">
      <div class="col-lg-9">
        <h1 class="mb-2">Estado de la instalación</h1>
        <p class="text-muted mb-0">
          Esta página sólo es visible con el modo de desarrollo activo. Antes de publicar el sitio,
          todas las verificaciones deben aparecer en verde.
        </p>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-9">

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body">
            <?php foreach ($checks as $check): ?>
              <div class="d-flex align-items-start py-3 border-bottom">
                <i class="bi <?= e($icons[$check['status']] ?? 'bi-circle') ?> fs-4 me-3"></i>
                <div>
                  <div class="fw-bold"><?= e($check['label']) ?></div>
                  <div class="text-muted small"><?= e($check['detail']) ?></div>
                  <?php if ($check['hint'] !== ''): ?>
                    <div class="small mt-1 text-brand"><?= e($check['hint']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h2 class="h5 mb-3">Datos del entorno</h2>
            <dl class="row mb-0 small">
              <dt class="col-sm-4">Versión de PHP</dt>
              <dd class="col-sm-8"><?= e($phpVersion) ?></dd>

              <dt class="col-sm-4">Raíz del proyecto</dt>
              <dd class="col-sm-8"><code><?= e($basePath) ?></code></dd>

              <dt class="col-sm-4">Carpeta de archivos privados</dt>
              <dd class="col-sm-8">
                <code><?= e($storagePath) ?></code>
                <?= $storageWritable ? '<span class="text-success">(con permiso de escritura)</span>' : '<span class="text-danger">(sin permiso de escritura)</span>' ?>
              </dd>

              <dt class="col-sm-4">Composer</dt>
              <dd class="col-sm-8">
                <?= $composerInstalled ? 'Instalado' : 'No instalado — el proyecto funciona igual, con funciones básicas' ?>
              </dd>
            </dl>
          </div>
        </div>

      </div>
    </div>

  </div>
</section>
