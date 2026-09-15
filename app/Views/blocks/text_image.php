<?php

declare(strict_types=1);

/**
 * Sección de título, texto y recuadros (por ejemplo la visión).
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'section';
$visibleItems = array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive');
?>
<section id="<?= e($sectionId) ?>" class="about section">

  <?php if (!empty($block['eyebrow'])): ?>
    <div class="container section-title" data-aos="fade-up">
      <h2><?= e($block['eyebrow']) ?></h2>
    </div>
  <?php endif; ?>

  <div class="container">
    <div class="row gy-4">

      <?php if (!empty($block['heading'])): ?>
        <h2 class="mb-4 section-title"><?= e($block['heading']) ?></h2>
      <?php endif; ?>

      <?php foreach (array_values($visibleItems) as $index => $item): ?>
        <div class="col-lg-6 content" data-aos="fade-up" data-aos-delay="<?= e(100 * ($index + 1)) ?>">
          <h4>
            <?php if (!empty($item['icon'])): ?>
              <i class="<?= e($item['icon']) ?> me-2 text-brand"></i>
            <?php endif; ?>
            <?= e($item['title'] ?? '') ?>
          </h4>
          <?php if (!empty($item['body'])): ?>
            <p><?= e($item['body']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

    </div>
  </div>

</section>
