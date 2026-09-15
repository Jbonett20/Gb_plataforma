<?php

declare(strict_types=1);

/**
 * Layout base del sitio público.
 *
 * Reutiliza la línea gráfica del sitio actual: mismas tipografías, mismas
 * librerías y el CSS del tema. No se altera ningún color ni medida.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $metaDescription
 * @var string $siteName
 * @var array<string, mixed> $company
 * @var array<int, array{label: string, href: string}> $navLinks
 * @var string|null $footerHtml
 */

$siteName = $siteName ?? (string) config('app.name');
$company = $company ?? (array) config('app.company', []);
$title = $title ?? $siteName;
$metaDescription = $metaDescription ?? '';
$navLinks = is_array($navLinks ?? null) && $navLinks !== [] ? $navLinks : [['label' => 'Inicio', 'href' => '#hero']];
$footerHtml = $footerHtml ?? '';
$bodyClass = $bodyClass ?? 'index-page';
$whatsapp = setting('whatsapp');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= e($title) ?></title>
  <?php if ($metaDescription !== ''): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
  <?php endif; ?>

  <!-- Favicons (los mismos del sitio actual) -->
  <link href="<?= e(asset('img/favicon.png')) ?>" rel="icon">
  <link href="<?= e(asset('img/apple-touch-icon.png')) ?>" rel="apple-touch-icon">

  <!-- Tipografías -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Librerías del sitio actual -->
  <link href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>" rel="stylesheet">
  <link href="<?= e(asset('vendor/aos/aos.css')) ?>" rel="stylesheet">
  <link href="<?= e(asset('vendor/swiper/swiper-bundle.min.css')) ?>" rel="stylesheet">

  <!-- Estilos del sitio actual y tokens de marca -->
  <link href="<?= e(asset('css/main.css')) ?>" rel="stylesheet">
  <link href="<?= e(asset('css/theme.css')) ?>" rel="stylesheet">
  <!-- Acceso y área personal (reglas acotadas a .account-page) -->
  <link href="<?= e(asset('css/account.css')) ?>" rel="stylesheet">

  <style>
    /* Acceso de estudiantes en el encabezado. Es una función de la plataforma,
       no una sección del contenido, así que se ve en todas las páginas.
       Los dos botones comparten clase: uno no puede verse más importante que
       el otro, porque son las dos mitades de lo mismo (entrar o darse de alta). */
    /* Separación: los dos botones no pueden tocarse entre sí (son dos cosas
       distintas) ni pegarse al menú, que es lo que pasaba antes. */
    .header-account {
      gap: 16px;
      margin-left: 30px;
    }

    /* --- Quién eres, cuando hay sesión abierta ---------------------------- */
    .header-account__menu { position: relative; }

    .header-account__user {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      list-style: none;
      cursor: pointer;
      color: var(--heading-color);
      font-weight: 600;
      font-size: .9rem;
      line-height: 1;
      padding: 9px 12px;
      border: 1px solid var(--accent-line);
      border-radius: 4px;
      white-space: nowrap;
      transition: .3s;
    }
    .header-account__user::-webkit-details-marker { display: none; }
    .header-account__user:hover {
      color: var(--accent-color);
      border-color: var(--accent-color);
      background: var(--accent-soft);
    }
    .header-account__caret { font-size: .75rem; transition: transform .2s; }
    .header-account__menu[open] .header-account__caret { transform: rotate(180deg); }

    .header-account__dropdown {
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      min-width: 250px;
      background: var(--surface-color);
      border: 1px solid var(--accent-line);
      border-radius: 8px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, .12);
      padding: .75rem;
      z-index: 1200;
    }
    .header-account__hi {
      margin: 0 0 .5rem;
      padding: 0 .35rem;
      font-size: .82rem;
      color: var(--default-color);
      white-space: normal;
    }
    .header-account__hi strong { color: var(--heading-color); }
    .header-account__dropdown a,
    .header-account__dropdown button {
      display: block;
      width: 100%;
      text-align: left;
      background: none;
      border: 0;
      padding: .5rem .35rem;
      border-radius: 4px;
      color: var(--heading-color);
      font-weight: 600;
      font-size: .88rem;
      text-decoration: none;
      cursor: pointer;
    }
    .header-account__dropdown a:hover,
    .header-account__dropdown button:hover {
      background: var(--accent-soft);
      color: var(--accent-color);
    }

    .header-account__button {
      display: inline-flex;
      align-items: center;
      color: var(--contrast-color);
      background: var(--accent-color);
      font-weight: 600;
      font-size: .9rem;
      line-height: 1;
      padding: 10px 18px;
      border-radius: 4px;
      text-decoration: none;
      white-space: nowrap;
      transition: .3s;
    }
    .header-account__button:hover,
    .header-account__button:focus {
      color: var(--contrast-color);
      background: color-mix(in srgb, var(--accent-color), transparent 15%);
    }
    @media (max-width: 1200px) {
      /* El menú se pliega en el alternador y main.css fija el orden: logo,
         botones, alternador. Sin decir aquí el orden de los botones, se irían
         al principio de la fila —antes del logo— porque el orden por defecto
         (0) es menor que el del logo (1). Y aquí la separación va a la derecha,
         entre los botones y el alternador del menú. */
      .header-account { order: 2; gap: 12px; margin: 0 15px 0 0; }
      .header-account__button { padding: 9px 15px; }
      .header-account__user { padding: 8px 11px; }
    }
    @media (max-width: 400px) {
      .header-account { gap: 10px; margin-right: 10px; }
      .header-account__button { padding: 8px 12px; font-size: .85rem; }
      .header-account__user { padding: 7px 10px; font-size: .85rem; }
      .header-account__dropdown { min-width: 220px; }
    }
  </style>

  <?= $head ?? '' ?>
</head>

<body class="<?= e($bodyClass) ?>">

  <?php require GB_APP_PATH . '/Views/partials/public-header.php'; ?>

  <main class="main">
    <?= $content ?>
  </main>

  <?php
  // El pie de página también es un bloque administrable. Si todavía no se ha
  // publicado, se usa la versión básica como respaldo para que la página nunca
  // quede sin datos de contacto.
  ?>
  <?php if (trim($footerHtml) !== ''): ?>
    <?= $footerHtml ?>
  <?php else: ?>
    <?php require GB_APP_PATH . '/Views/partials/public-footer.php'; ?>
  <?php endif; ?>

  <?php if ($whatsapp !== ''): ?>
    <div class="wsp">
      <a href="https://wa.me/<?= e($whatsapp) ?>" class="wsp__button" target="_blank" rel="noopener">
        <i class="bi bi-whatsapp"></i>
      </a>
    </div>
  <?php endif; ?>

  <?php
  // Estos dos elementos son obligatorios: `assets/js/main.js` del sitio actual
  // registra listeners sobre `.scroll-top` sin comprobar si existe, y si falta
  // se detiene toda la inicialización (animaciones, carrusel, menú móvil).
  ?>
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>

  <div id="preloader">
    <div></div>
    <div></div>
    <div></div>
    <div></div>
  </div>

  <!-- Librerías del sitio actual -->
  <script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(asset('vendor/aos/aos.js')) ?>"></script>
  <script src="<?= e(asset('vendor/purecounter/purecounter_vanilla.js')) ?>"></script>
  <script src="<?= e(asset('vendor/swiper/swiper-bundle.min.js')) ?>"></script>
  <script src="<?= e(asset('vendor/glightbox/js/glightbox.min.js')) ?>"></script>
  <script src="<?= e(asset('vendor/imagesloaded/imagesloaded.pkgd.min.js')) ?>"></script>
  <script src="<?= e(asset('vendor/isotope-layout/isotope.pkgd.min.js')) ?>"></script>

  <script src="<?= e(asset('js/main.js')) ?>"></script>
  <script src="<?= e(asset('js/newsletter.js')) ?>"></script>

  <?= $scripts ?? '' ?>
</body>

</html>
