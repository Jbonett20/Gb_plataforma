<?php

declare(strict_types=1);

/**
 * Franja de invitación a la acción, con imagen de fondo y botón.
 *
 * @var array<string, mixed> $block
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'call-to-action';
$background = media_url(isset($block['media_id']) ? (int) $block['media_id'] : null, 1920);
?>
<section id="<?= e($sectionId) ?>" class="call-to-action section dark-background">

  <?php if ($background !== null): ?>
    <img src="<?= e($background) ?>" alt="<?= e(media_alt(isset($block['media_id']) ? (int) $block['media_id'] : null, '')) ?>">
  <?php endif; ?>

  <div class="container">
    <div class="row justify-content-center" data-aos="zoom-in" data-aos-delay="100">
      <div class="col-xl-10">
        <div class="text-center text-white">
          <?php if (!empty($block['heading'])): ?>
            <h3><?= e($block['heading']) ?></h3>
          <?php endif; ?>
          <?php if (!empty($block['intro'])): ?>
            <p><?= e($block['intro']) ?></p>
          <?php endif; ?>
          <?php if (!empty($block['cta_label'])): ?>
            <a class="cta-btn" href="<?= e($block['cta_url'] ?: '#') ?>"><?= e($block['cta_label']) ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</section>
