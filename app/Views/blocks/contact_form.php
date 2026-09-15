<?php

declare(strict_types=1);

/**
 * Contacto: datos de la firma y formulario para agendar una cita.
 *
 * El formulario se envía sin recargar la página. Si el navegador no ejecuta
 * JavaScript, sigue funcionando como un envío normal.
 *
 * @var array<string, mixed> $block
 * @var array<int, array<string, mixed>> $items
 * @var string $anchor
 */

$sectionId = ltrim($anchor, '#') ?: 'contact';
$blockId = (int) ($block['id'] ?? 0);
$visibleItems = array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'active') !== 'inactive');
$formId = 'form_contact_' . $blockId;
$statusId = 'form_contact_status_' . $blockId;
?>
<section id="<?= e($sectionId) ?>" class="contact section light-background">

  <div class="container section-title" data-aos="fade-up">
    <?php if (!empty($block['heading'])): ?>
      <h2><?= e($block['heading']) ?></h2>
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

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="row gy-4">

      <div class="col-lg-6">
        <div class="row gy-4">
          <?php foreach (array_values($visibleItems) as $index => $item): ?>
            <?php $isWide = $index === 0 || count($visibleItems) === 1; ?>
            <div class="<?= $isWide ? 'col-lg-12' : 'col-md-6' ?>">
              <div class="info-item d-flex flex-column justify-content-center align-items-center"
                   data-aos="fade-up" data-aos-delay="<?= e(200 + 100 * $index) ?>">
                <?php if (!empty($item['icon'])): ?>
                  <i class="<?= e($item['icon']) ?> bg-brand text-white"></i>
                <?php endif; ?>
                <h3><?= e($item['title'] ?? '') ?></h3>
                <p><?= nl2br(e($item['body'] ?? '')) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-lg-6">
        <form id="<?= e($formId) ?>" method="post" action="<?= e(url('contacto')) ?>" class="php-email-form"
              data-aos="fade-up" data-aos-delay="500">
          <?= csrf_field_cached() ?>
          <div class="row gy-4">

            <div class="col-md-6">
              <label for="<?= e($formId) ?>_name" class="visually-hidden">Nombre</label>
              <input type="text" id="<?= e($formId) ?>_name" name="name" class="form-control"
                     placeholder="Nombre" required maxlength="150" autocomplete="name">
            </div>

            <div class="col-md-6">
              <label for="<?= e($formId) ?>_email" class="visually-hidden">Correo electrónico</label>
              <input type="email" id="<?= e($formId) ?>_email" name="email" class="form-control"
                     placeholder="Correo electrónico" required maxlength="190" autocomplete="email">
            </div>

            <div class="col-md-6">
              <label for="<?= e($formId) ?>_phone" class="visually-hidden">Teléfono</label>
              <input type="tel" id="<?= e($formId) ?>_phone" name="phone" class="form-control"
                     placeholder="Teléfono (opcional)" maxlength="40" autocomplete="tel">
            </div>

            <div class="col-md-6">
              <label for="<?= e($formId) ?>_subject" class="visually-hidden">Asunto</label>
              <input type="text" id="<?= e($formId) ?>_subject" name="subject" class="form-control"
                     placeholder="Asunto" required maxlength="200">
            </div>

            <div class="col-md-12">
              <label for="<?= e($formId) ?>_message" class="visually-hidden">Mensaje</label>
              <textarea id="<?= e($formId) ?>_message" name="message" class="form-control" rows="5"
                        placeholder="Cuéntanos brevemente qué necesitas" required minlength="10" maxlength="2000"></textarea>
            </div>

            <div class="col-md-12 text-center">
              <div id="<?= e($statusId) ?>" class="mb-3" role="status" aria-live="polite"></div>
              <button type="submit">Enviar mensaje</button>
            </div>

          </div>
        </form>
      </div>

    </div>
  </div>

</section>

<script>
  (function () {
    var form = document.getElementById(<?= json_encode($formId) ?>);
    var status = document.getElementById(<?= json_encode($statusId) ?>);
    if (!form || !status) return;

    form.addEventListener('submit', function (event) {
      if (!window.fetch) return;           // sin fetch, el envío normal sigue funcionando
      event.preventDefault();

      var button = form.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
      status.className = 'mb-3 text-muted';
      status.textContent = 'Enviando…';

      window.fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form)
      }).then(function (response) {
        return response.json().catch(function () { return {}; });
      }).then(function (data) {
        var ok = data && data.error !== true;
        status.className = 'mb-3 ' + (ok ? 'text-success' : 'text-danger');
        status.textContent = (data && data.message) || (ok ? 'Recibimos tu mensaje. Te contactaremos pronto.' : 'No pudimos enviar el mensaje.');
        if (ok) form.reset();
      }).catch(function () {
        status.className = 'mb-3 text-danger';
        status.textContent = 'No pudimos enviar el mensaje. Revisa tu conexión e inténtalo otra vez.';
      }).then(function () {
        if (button) button.disabled = false;
      });
    });
  })();
</script>
