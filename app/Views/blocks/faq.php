<?php

declare(strict_types=1);

/**
 * Preguntas frecuentes (acordeón).
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'faq';
$blockId = (int) ($block['id'] ?? 0);
$visibleItems = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive'));
$accordionId = 'faqAccordion' . $blockId;
?>
<section id="<?= e($sectionId) ?>" class="faq section bg-light py-5">
  <div class="container">

    <div class="container section-title" data-aos="fade-up">
      <?php if (!empty($block['heading'])): ?>
        <h2 style="letter-spacing:0 !important;"><?= e($block['heading']) ?></h2><br>
      <?php endif; ?>
      <?php if (!empty($block['eyebrow']) || !empty($block['intro'])): ?>
        <div>
          <span><?= e($block['eyebrow'] ?: '') ?></span>
          <?php if (!empty($block['intro'])): ?>
            <span class="description-title"><?= e($block['intro']) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($visibleItems !== []): ?>
      <div class="accordion" id="<?= e($accordionId) ?>">

        <?php foreach ($visibleItems as $index => $item): ?>
          <?php
          $headingId = 'faqHeading' . $blockId . '-' . $index;
          $collapseId = 'faqCollapse' . $blockId . '-' . $index;
          ?>
          <div class="accordion-item">
            <h3 class="accordion-header" id="<?= e($headingId) ?>">
              <button class="accordion-button<?= $index === 0 ? '' : ' collapsed' ?>" type="button"
                      data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>"
                      aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="<?= e($collapseId) ?>">
                <?= e($item['title'] ?? '') ?>
              </button>
            </h3>
            <div id="<?= e($collapseId) ?>" class="accordion-collapse collapse<?= $index === 0 ? ' show' : '' ?>"
                 data-bs-parent="#<?= e($accordionId) ?>">
              <div class="accordion-body">
                <?= format_text($item['body'] ?? '') ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    <?php endif; ?>

  </div>
</section>
