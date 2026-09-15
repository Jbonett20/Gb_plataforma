<?php

declare(strict_types=1);

/**
 * Lista simple de enlaces.
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'links';
$visibleItems = array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive');

if ($visibleItems === []) {
    return;
}
?>
<section id="<?= e($sectionId) ?>" class="section py-4">
  <div class="container">
    <?php if (!empty($block['heading'])): ?>
      <h2 class="h5 mb-3"><?= e($block['heading']) ?></h2>
    <?php endif; ?>

    <ul class="list-unstyled mb-0">
      <?php foreach ($visibleItems as $item): ?>
        <li class="mb-2">
          <a href="<?= e($item['link_url'] ?: '#') ?>"><?= e($item['title'] ?? '') ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
