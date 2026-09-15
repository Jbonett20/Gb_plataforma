<?php

declare(strict_types=1);

/**
 * Portada del sitio.
 *
 * Las secciones llegan ya convertidas en HTML desde los bloques publicados en
 * el panel (`GB\Services\BlockRenderer`). Esta vista no contiene ningún texto
 * del sitio: sólo decide qué mostrar cuando todavía no hay nada publicado.
 *
 * @var string $sectionsHtml
 * @var bool $hasContent
 */

$sectionsHtml = $sectionsHtml ?? '';
$hasContent = $hasContent ?? false;
$debug = (bool) config('app.debug', false);
?>

<?= $sectionsHtml ?>

<?php if (!$hasContent && $debug): ?>
  <section class="section bg-light-section">
    <div class="container" data-aos="fade-up">
      <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
          <h2 class="mb-3">Todavía no hay secciones publicadas</h2>
          <p class="mb-4">
            El sitio ya está listo, pero ninguna sección se ha publicado todavía. Carga el contenido
            inicial y publícalo desde el panel de administración.
          </p>
          <a href="<?= e(url('instalacion')) ?>" class="btn bg-brand text-white">
            Ver estado de la instalación
          </a>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>
