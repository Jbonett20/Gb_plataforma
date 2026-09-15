<?php

declare(strict_types=1);

/**
 * Pie de página del sitio público.
 *
 * Los datos de contacto provienen de los Ajustes del sitio (tabla settings).
 * Este parcial es el respaldo cuando todavía no se ha publicado el bloque
 * `footer`, para que la página nunca quede sin datos de contacto.
 *
 * @var string $siteName
 */

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

      <div class="col-lg-2 col-md-3 footer-links">
        <h4>Enlaces Útiles</h4>
        <ul>
          <li><a href="<?= e(url('/')) ?>">Inicio</a></li>
        </ul>
      </div>

      <div class="col-lg-2 col-md-3 footer-links">
        <h4>Especialidades</h4>
        <ul>
          <li><a href="#">Derecho Laboral</a></li>
          <li><a href="#">Derecho Civil</a></li>
          <li><a href="#">Seguridad Social</a></li>
          <li><a href="#">Consultoría Legal</a></li>
          <li><a href="#">Litigios Estratégicos</a></li>
        </ul>
      </div>

      <div class="col-lg-4 col-md-12 footer-newsletter">
        <p>¿Quieres recibir actualizaciones legales y noticias relevantes?</p>
        <form method="post" action="<?= e(url('suscripcion')) ?>" data-newsletter-form>
          <?= csrf_field() ?>
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
      </div>

    </div>
  </div>

  <div class="container text-center mt-4">
    <small class="text-white-50">&copy; <?= e($year) ?> <?= e(setting('site_name')) ?> - Todos los derechos reservados.</small>
  </div>

</footer>
