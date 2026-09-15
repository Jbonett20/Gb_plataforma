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
       no una sección del contenido, así que se ve en todas las páginas. */
    .header-account__link {
      color: var(--heading-color);
      font-weight: 600;
      font-size: .95rem;
      text-decoration: none;
      white-space: nowrap;
    }
    .header-account__link:hover { color: var(--accent-color); }
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
