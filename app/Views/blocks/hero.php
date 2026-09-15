<?php

declare(strict_types=1);

/**
 * Carrusel de inicio.
 *
 * El marcado es el mismo del sitio actual (`modulos/hero.php`): `main.css`
 * posiciona y recorta la imagen con sus propias reglas, y `main.js` genera los
 * indicadores a partir de los elementos `.carousel-item`.
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var array<string, mixed> $settings
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'hero';
$interval = (int) ($settings['interval_ms'] ?? 7500);
$visibleItems = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive'));
?>
<?php if ($visibleItems !== []): ?>
  <section id="<?= e($sectionId) ?>" class="hero section dark-background">

    <div id="hero-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="<?= e($interval) ?>">

      <?php foreach ($visibleItems as $index => $item): ?>
        <?php $imageUrl = media_url(isset($item['media_id']) ? (int) $item['media_id'] : null, 1920); ?>
        <div class="carousel-item<?= $index === 0 ? ' active' : '' ?>">
          <?php if ($imageUrl !== null): ?>
            <img src="<?= e($imageUrl) ?>" alt="<?= e(media_alt(isset($item['media_id']) ? (int) $item['media_id'] : null, (string) ($item['title'] ?? ''))) ?>">
          <?php endif; ?>
          <div class="carousel-container">
            <h2><?= e($item['title'] ?? '') ?></h2>
            <?php if (!empty($item['body'])): ?>
              <p><?= e($item['body']) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['link_label'])): ?>
              <a href="<?= e($item['link_url'] ?? '#') ?>" class="btn-get-started"><?= e($item['link_label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (count($visibleItems) > 1): ?>
        <a class="carousel-control-prev" href="#hero-carousel" role="button" data-bs-slide="prev">
          <span class="carousel-control-prev-icon bi bi-chevron-left" aria-hidden="true"></span>
        </a>
        <a class="carousel-control-next" href="#hero-carousel" role="button" data-bs-slide="next">
          <span class="carousel-control-next-icon bi bi-chevron-right" aria-hidden="true"></span>
        </a>

        <ol class="carousel-indicators"></ol>
      <?php endif; ?>

    </div>

  </section>
<?php endif; ?>
