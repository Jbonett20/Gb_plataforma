<?php

declare(strict_types=1);

/**
 * Área personal: los cursos en los que está inscrita la persona.
 *
 * @var array<int, array<string, mixed>> $courses
 * @var array<int, array<string, mixed>> $purchases
 * @var array<string, mixed> $student
 */

$courses = is_array($courses ?? null) ? $courses : [];
$purchases = is_array($purchases ?? null) ? $purchases : [];
$student = is_array($student ?? null) ? $student : [];

$firstName = (string) ($student['name'] ?? '');

$currentSection = 'cursos';
require GB_APP_PATH . '/Views/partials/account-header.php';
?>

<section class="account-section">
  <div class="container">

    <h3 class="account-card__title" style="font-size:1.25rem;">Mis cursos</h3>
    <p class="account-card__lead">
      <?php if ($courses === []): ?>
        Todavía no tienes cursos. Cuando te inscribas a uno, aparecerá aquí con tu avance.
      <?php else: ?>
        Marca cada lección al terminarla y verás tu avance actualizarse.
      <?php endif; ?>
    </p>

    <?php if ($courses === []): ?>
      <div class="empty-state">
        <i class="bi bi-journal-bookmark"></i>
        <p class="mb-2">Aún no hay cursos en tu área personal.</p>
        <a class="account-button account-button--ghost" href="<?= e(url('/')) ?>#services">
          Ver los servicios de la firma
        </a>
      </div>
    <?php else: ?>
      <div class="row gy-4">
        <?php foreach ($courses as $course): ?>
          <?php
          $completed = is_array($course['completed_lesson_ids'] ?? null) ? $course['completed_lesson_ids'] : [];
          $total = (int) ($course['lesson_total'] ?? 0);
          $percent = (int) ($course['progress_percent'] ?? 0);
          ?>
          <div class="col-lg-6">
            <div class="course-card">
              <div class="course-card__body">
                <h4 class="course-card__title"><?= e((string) ($course['title'] ?? '')) ?></h4>

                <?php if (!empty($course['summary'])): ?>
                  <p class="course-card__summary"><?= e((string) $course['summary']) ?></p>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-1">
                  <small class="text-muted">Avance del curso</small>
                  <strong data-progress-label="<?= e((string) $course['course_id']) ?>"><?= e((string) $percent) ?>%</strong>
                </div>
                <div class="progress mb-3" role="progressbar"
                     aria-label="Avance en <?= e((string) ($course['title'] ?? '')) ?>"
                     aria-valuenow="<?= e((string) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
                  <div class="progress-bar" data-progress-bar="<?= e((string) $course['course_id']) ?>"
                       style="width: <?= e((string) $percent) ?>%"></div>
                </div>

                <?php if ($total === 0): ?>
                  <p class="account-field__help mb-0">
                    Este curso todavía no tiene lecciones publicadas.
                  </p>
                <?php else: ?>
                  <?php foreach ((array) ($course['syllabus'] ?? []) as $module): ?>
                    <?php if ((array) ($module['lessons'] ?? []) === []) { continue; } ?>
                    <p class="lesson-list__module"><?= e((string) $module['title']) ?></p>
                    <ul class="lesson-list">
                      <?php foreach ((array) $module['lessons'] as $lesson): ?>
                        <?php $isDone = in_array((int) $lesson['id'], array_map('intval', $completed), true); ?>
                        <li class="lesson-list__item <?= $isDone ? 'is-done' : '' ?>"
                            data-lesson="<?= e((string) $lesson['id']) ?>">
                          <button type="button" class="lesson-list__toggle"
                                  data-toggle-lesson="<?= e((string) $lesson['id']) ?>"
                                  data-course="<?= e((string) $course['course_id']) ?>"
                                  data-completed="<?= $isDone ? '1' : '0' ?>"
                                  aria-pressed="<?= $isDone ? 'true' : 'false' ?>"
                                  title="<?= $isDone ? 'Marcar como pendiente' : 'Marcar como completada' ?>">
                            <i class="bi <?= $isDone ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i>
                          </button>
                          <span class="lesson-list__title"><?= e((string) $lesson['title']) ?></span>
                          <?php if (!empty($lesson['duration_minutes'])): ?>
                            <small class="text-muted"><?= e((string) $lesson['duration_minutes']) ?> min</small>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($purchases !== []): ?>
      <h3 class="account-card__title mt-5" style="font-size:1.25rem;">Mis compras</h3>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr><th>Curso</th><th>Fecha</th><th>Monto</th><th>Estado</th></tr>
          </thead>
          <tbody>
            <?php foreach ($purchases as $purchase): ?>
              <tr>
                <td><?= e((string) ($purchase['course_title'] ?? '')) ?></td>
                <td><?= e((string) ($purchase['created_at'] ?? '')) ?></td>
                <td><?= e((string) ($purchase['amount_label'] ?? '')) ?></td>
                <td><?= e((string) ($purchase['status_label'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</section>
