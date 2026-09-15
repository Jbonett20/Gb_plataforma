<?php

declare(strict_types=1);

/**
 * Ficha pública del curso.
 *
 * En el modo transitorio, cualquier curso publicado puede verse y, si es
 * gratuito, inscribirse sin pasar por la pasarela. Los cursos de pago se
 * muestran con el aviso apropiado, pero no se conceden automáticamente.
 *
 * @var array<string, mixed> $course
 * @var array<int, array<string, mixed>> $syllabus
 * @var bool $isEnrolled
 * @var bool $previewOnly
 */

$course = is_array($course ?? null) ? $course : [];
$syllabus = is_array($syllabus ?? null) ? $syllabus : [];
$title = (string) ($course['title'] ?? 'Curso');
$summary = (string) ($course['summary'] ?? '');
$description = trim((string) ($course['description'] ?? ''));
$cover = media_url((int) ($course['cover_media_id'] ?? 0), 1200);
$accessType = (string) ($course['access_type'] ?? 'free');
$canEnroll = $accessType === 'free';
$badge = $accessType === 'paid' ? 'Curso de pago' : 'Curso gratuito';
$slug = (string) ($course['slug'] ?? '');

$authUser = auth()->user();
$userLoggedIn = $authUser !== null;
?>

<section class="section bg-light-section">
  <div class="container py-5">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7">
        <p class="eyebrow text-brand mb-2"><?= e($badge) ?></p>
        <h1 class="mb-3"><?= e($title) ?></h1>

        <?php if ($summary !== ''): ?>
          <p class="lead text-muted"><?= e($summary) ?></p>
        <?php endif; ?>

        <?php if ($userLoggedIn && $isEnrolled): ?>
          <div class="alert alert-success mb-3">
            Ya tienes acceso a este curso en tu área personal.
          </div>
        <?php elseif ($userLoggedIn && $canEnroll): ?>
          <form method="post" action="<?= e(url('cursos/' . $slug . '/inscribirse')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn bg-brand text-white">Inscribirme ahora</button>
          </form>
        <?php elseif ($userLoggedIn && !$canEnroll): ?>
          <div class="alert alert-warning mb-3">
            Este curso requiere confirmación de pago antes de habilitar acceso.
          </div>
          <a href="<?= e(url('cursos/' . $slug . '/comprar')) ?>" class="btn bg-brand text-white">Comprar cuando esté disponible</a>
        <?php else: ?>
          <div class="alert alert-info mb-3">
            Inicia sesión para acceder a este curso o para reservarlo en tu área personal.
          </div>
          <a href="<?= e(url('ingresar')) ?>" class="btn bg-brand text-white">Entrar a mi cuenta</a>
        <?php endif; ?>
      </div>

      <div class="col-lg-5">
        <?php if ($cover !== null): ?>
          <img src="<?= e($cover) ?>" alt="<?= e($title) ?>" class="img-fluid rounded shadow-sm" style="width:100%; max-height:480px; object-fit:cover;">
        <?php else: ?>
          <div class="bg-white border rounded p-5 text-center text-muted">Sin imagen destacada</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($description !== ''): ?>
      <div class="row mt-5">
        <div class="col-lg-8">
          <h2 class="h4 mb-3">Descripción</h2>
          <div class="content-copy"><?= $description ?></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="row mt-5">
      <div class="col-lg-8">
        <h2 class="h4 mb-3">Temario</h2>

        <?php if ($syllabus === []): ?>
          <p class="text-muted">Este curso todavía no tiene lecciones publicadas.</p>
        <?php else: ?>
          <?php foreach ($syllabus as $module): ?>
            <?php $lessons = is_array($module['lessons'] ?? null) ? $module['lessons'] : []; ?>
            <?php if ($lessons === []) continue; ?>
            <div class="mb-4">
              <h3 class="h5 mb-3"><?= e((string) ($module['title'] ?? '')) ?></h3>
              <ul class="list-group">
                <?php foreach ($lessons as $lesson): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                      <?= e((string) ($lesson['title'] ?? '')) ?>
                      <?php if (!empty($lesson['is_preview'])): ?>
                        <span class="badge bg-light text-dark ms-2">Vista previa</span>
                      <?php endif; ?>
                    </span>
                    <?php if (!empty($lesson['duration_minutes'])): ?>
                      <small class="text-muted"><?= e((string) $lesson['duration_minutes']) ?> min</small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
