<?php

declare(strict_types=1);

/**
 * Cuadrícula de tarjetas. Cubre dos usos del sitio actual:
 *
 *   - servicios: tarjeta con ícono (sin imagen);
 *   - casos destacados: tarjeta con imagen encima y la franja de marca.
 *
 * El SuperAdmin elige cuál con la opción "Mostrar la imagen encima del texto".
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var array<string, mixed> $settings
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'section';
$columns = max(1, min(4, (int) ($settings['columns'] ?? 4)));
$mediaOnTop = (bool) ($settings['media_on_top'] ?? false);
$visibleItems = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive'));

$columnClass = match ($columns) {
    1 => 'col-lg-12',
    2 => 'col-lg-6',
    3 => 'col-lg-4',
    default => 'col-lg-3',
};
?>
<section id="<?= e($sectionId) ?>" class="<?= $mediaOnTop ? 'py-5' : 'features section py-5' ?>">

  <div class="container">
    <?php if (!empty($block['eyebrow']) || !empty($block['heading']) || !empty($block['intro'])): ?>
      <div class="container section-title" data-aos="fade-up">
        <?php if (!empty($block['eyebrow'])): ?>
          <h2><?= e($block['eyebrow']) ?></h2><br>
        <?php endif; ?>
        <?php if (!empty($block['heading'])): ?>
          <div><span><?= e($block['heading']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($block['intro'])): ?>
          <p class="text-muted"><?= e($block['intro']) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="row gy-4">
      <?php foreach ($visibleItems as $index => $item): ?>
        <?php $imageUrl = media_url(isset($item['media_id']) ? (int) $item['media_id'] : null, 800); ?>
        <div class="<?= e($columnClass) ?> col-md-6" data-aos="fade-up" data-aos-delay="<?= e(100 * ($index + 1)) ?>">

          <?php if ($mediaOnTop): ?>
            <div class="card shadow-sm border-0 h-100">
              <?php if ($imageUrl !== null): ?>
                <div class="position-relative ratio-media ratio-news-cover">
                  <img src="<?= e($imageUrl) ?>" alt="<?= e(media_alt(isset($item['media_id']) ? (int) $item['media_id'] : null, (string) ($item['title'] ?? ''))) ?>">
                  <svg class="position-absolute bottom-0 start-0 w-100" height="40px" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0,0 C150,150 350,0 500,150 L500,00 L0,0 Z" style="stroke: none; fill: var(--accent-color);"></path>
                  </svg>
                </div>
              <?php endif; ?>
              <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                  <?php if (!empty($item['icon'])): ?>
                    <i class="<?= e($item['icon']) ?> text-brand me-2 fs-4"></i>
                  <?php endif; ?>
                  <h3 class="card-title mb-0 text-dark h5"><?= e($item['title'] ?? '') ?></h3>
                </div>
                <?php if (!empty($item['body'])): ?>
                  <p class="card-text" style="text-align: justify;"><?= e($item['body']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="card h-100 shadow-sm border-0">
              <div class="card-body">
                <h3 class="card-title text-dark fw-bold h5">
                  <?php if (!empty($item['icon'])): ?>
                    <i class="<?= e($item['icon']) ?> text-brand me-2"></i>
                  <?php endif; ?>
                  <?= e($item['title'] ?? '') ?>
                </h3>
                <?php if (!empty($item['body'])): ?>
                  <p class="card-text" style="text-align: justify;"><?= e($item['body']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>
  </div>

</section>
