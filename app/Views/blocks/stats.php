<?php

declare(strict_types=1);

/**
 * Franja de cifras animadas.
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'stats';
$visibleItems = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive'));

if ($visibleItems === []) {
    return;
}

$columns = max(1, min(4, (int) ($block['settings']['columns'] ?? min(4, count($visibleItems)))));
$columnClass = match ($columns) {
    1 => 'col-lg-12',
    2 => 'col-lg-6',
    3 => 'col-lg-4',
    default => 'col-lg-3',
};
?>
<section id="<?= e($sectionId) ?>" class="stats section">

  <div class="container" data-aos="fade-up" data-aos-delay="0">
    <div class="row gy-4">

      <?php foreach ($visibleItems as $item): ?>
        <?php $value = (int) (is_array($item['data'] ?? null) ? ($item['data']['value'] ?? 0) : 0); ?>
        <div class="<?= e($columnClass) ?> col-md-6 d-flex flex-column align-items-center">
          <?php if (!empty($item['icon'])): ?>
            <i class="<?= e($item['icon']) ?>"></i>
          <?php endif; ?>
          <div class="stats-item">
            <span data-purecounter-start="0" data-purecounter-end="<?= e($value) ?>"
                  data-purecounter-duration="1" class="purecounter"></span>
            <p><?= e($item['title'] ?? '') ?></p>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </div>

</section>
