<?php

declare(strict_types=1);

/**
 * Sección de clientes.
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'testimonials';
$visibleItems = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive'));
?>
<section class="testimonials section bg-white py-5" id="<?= e($sectionId) ?>">

  <?php if (!empty($block['heading'])): ?>
    <div class="container section-title text-align" data-aos="fade-up">
      <h2 style="letter-spacing:0 !important;"><?= e($block['heading']) ?></h2>
    </div>
  <?php endif; ?>

  <div class="container">
    <div class="row gy-4 justify-content-center">

      <?php foreach ($visibleItems as $index => $item): ?>
        <?php
        $stars = (int) (is_array($item['data'] ?? null) ? ($item['data']['stars'] ?? 5) : 5);
        $stars = max(1, min(5, $stars));
        ?>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= e(100 * ($index + 1)) ?>">
          <div class="testimonial-item p-3 shadow-sm rounded-4" style="border: 2px solid var(--accent-color);">
            <div class="stars mb-2 text-warning fs-5" aria-label="<?= e($stars) ?> de 5"><?= str_repeat('★', $stars) ?></div>
            <h3 class="text-dark mb-1 h5"><?= e($item['title'] ?? '') ?></h3>
            <?php if (!empty($item['subtitle'])): ?>
              <small class="text-secondary"><?= e($item['subtitle']) ?></small>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </div>

</section>
