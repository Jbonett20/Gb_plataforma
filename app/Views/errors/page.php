<?php

declare(strict_types=1);

/**
 * Plantilla común de las páginas de error.
 *
 * Los textos vienen de `GB\Support\ErrorResponder::copyFor()`, de modo que el
 * mensaje que ve el visitante y el que recibe la interfaz por JSON nunca se
 * contradicen.
 *
 * @var array{status: int, title: string, message: string} $error
 * @var string $siteName
 */

$error = $error ?? \GB\Support\ErrorResponder::copyFor(500);
?>
<section class="section bg-light-section">
  <div class="container text-center py-5">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <p class="text-brand fw-bold mb-2"><?= e($siteName) ?></p>
        <h1 class="mb-3"><?= e($error['title']) ?></h1>
        <p class="mb-4"><?= e($error['message']) ?></p>

        <a href="<?= e(url('/')) ?>" class="btn bg-brand text-white me-2">Volver al inicio</a>
        <a href="<?= e(url('/')) ?>#contact" class="btn btn-outline-secondary">Contacto</a>
      </div>
    </div>
  </div>
</section>
