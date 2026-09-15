<?php

declare(strict_types=1);

/**
 * Pie de página.
 *
 * Los datos de contacto se toman de los Ajustes del sitio para no tenerlos
 * repetidos en dos lugares. Los enlaces se agrupan por el campo "Columna".
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 */

$visibleItems = array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive');

$columns = [];

foreach ($visibleItems as $item) {
    $column = trim((string) ($item['subtitle'] ?? '')) ?: 'Enlaces';
    $columns[$column][] = $item;
}

$year = date('Y');
?>
<footer id="footer" class="footer dark-background">

  <div class="container footer-top">
    <div class="row gy-4">

      <div class="col-lg-4 col-md-6 footer-about">
        <a href="<?= e(url('/')) ?>" class="logo d-flex align-items-center">
          <span class="sitename">G&amp;B</span>
        </a>
        <div class="footer-contact pt-3">
          <p><?= e(setting('site_address')) ?></p>
          <p><?= e(setting('site_coverage')) ?></p>
          <p class="mt-3"><strong>Teléfono:</strong> <span><?= e(setting('site_phone')) ?></span></p>
          <p><strong>Correo electrónico:</strong> <span><?= e(setting('site_email')) ?></span></p>
        </div>

        <div class="social-links d-flex mt-4">
          <?php if (setting('instagram') !== ''): ?>
            <a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <?php endif; ?>
          <?php if (setting('linkedin') !== ''): ?>
            <a href="<?= e(setting('linkedin')) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
          <?php endif; ?>
        </div>
      </div>

      <?php foreach ($columns as $columnName => $links): ?>
        <div class="col-lg-2 col-md-3 footer-links">
          <h4><?= e($columnName) ?></h4>
          <ul>
            <?php foreach ($links as $link): ?>
              <li><a href="<?= e($link['link_url'] ?: '#') ?>"><?= e($link['title'] ?? '') ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>

      <div class="col-lg-4 col-md-12 footer-newsletter">
        <p>¿Quieres recibir actualizaciones legales y noticias relevantes?</p>
        <form method="post" action="<?= e(url('suscripcion')) ?>" data-newsletter-form>
          <?= csrf_field_cached() ?>
          <div class="input-group mb-2">
            <input class="form-control" type="text" name="name" placeholder="Nombre" required>
            <input class="form-control" type="email" name="email" placeholder="Correo electrónico" required>
          </div>
          <div class="input-group mb-2">
            <input class="form-control" type="text" name="last_name" placeholder="Apellidos">
            <input class="form-control" type="tel" name="phone" placeholder="Teléfono">
          </div>
          <button type="submit" class="btn bg-brand text-white">Suscribirse</button>
          <span class="small ms-2" data-newsletter-status role="status" aria-live="polite"></span>
        </form>

        <p class="small mt-3 mb-0">
          <?php if (app(\GB\Support\Auth::class)->check()): ?>
            <a href="<?= e(url('mi-cuenta')) ?>" class="text-white">Ir a mi cuenta</a>
          <?php else: ?>
            ¿Quieres tomar un curso?
            <a href="<?= e(url('registro')) ?>" class="text-white">Crea tu cuenta</a>
            o
            <a href="<?= e(url('ingresar')) ?>" class="text-white">entra</a>
            si ya la tienes.
          <?php endif; ?>
        </p>
      </div>

    </div>
  </div>

  <div class="container text-center mt-4">
    <small class="text-white-50">&copy; <?= e($year) ?> <?= e(setting('site_name')) ?> - Todos los derechos reservados.</small>
  </div>

</footer>
