<?php

declare(strict_types=1);

/**
 * Sección de equipo: fotografía, nombre, cargo y redes.
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'team';
$visibleItems = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive'));
$instagram = (string) (config('app.company.instagram') ?? '');
?>
<section id="<?= e($sectionId) ?>" class="team section py-5">

  <?php if (!empty($block['eyebrow'])): ?>
    <div class="container section-title" data-aos="fade-up">
      <h2 class="text-align text-uppercase"><?= e($block['eyebrow']) ?></h2>
    </div>
  <?php endif; ?>

  <div class="container">

    <?php if (!empty($block['heading'])): ?>
      <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
          <h3 class="text-center px-3"><?= e($block['heading']) ?></h3>
        </div>
      </div>
    <?php endif; ?>

    <div class="row justify-content-center gy-4">
      <?php foreach ($visibleItems as $index => $item): ?>
        <?php
        $photo = media_url(isset($item['media_id']) ? (int) $item['media_id'] : null, 600);
        $instagramUrl = is_array($item['data'] ?? null) ? (string) ($item['data']['instagram'] ?? '') : (string) ($item['data'] ?? '');
        ?>
        <div class="col-xl-4 col-lg-5 col-md-6" data-aos="fade-up" data-aos-delay="<?= e(300 + 100 * $index) ?>">
          <div class="member text-center">
            <?php if ($photo !== null): ?>
              <div class="ratio-media ratio-team-photo mx-auto" style="max-width: 380px;">
                <img src="<?= e($photo) ?>" alt="<?= e(media_alt(isset($item['media_id']) ? (int) $item['media_id'] : null, (string) ($item['title'] ?? ''))) ?>">
              </div>
            <?php endif; ?>
            <div class="member-info mt-3">
              <div class="member-info-content text-white">
                <h4><?= e($item['title'] ?? '') ?></h4>
                <?php if (!empty($item['subtitle'])): ?>
                  <span><?= e($item['subtitle']) ?></span>
                <?php endif; ?>
              </div>
              <div class="social mt-2">
                <?php if ($instagramUrl !== ''): ?>
                  <a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                <?php elseif ($instagram !== ''): ?>
                  <a href="<?= e($instagram) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($item['link_url'])): ?>
                  <a href="<?= e($item['link_url']) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

</section>
