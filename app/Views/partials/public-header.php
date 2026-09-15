<?php

declare(strict_types=1);

/**
 * Encabezado y navegación del sitio público.
 *
 * IMPORTANTE: `assets/js/main.js` del sitio actual exige que existan `#header`
 * y `.mobile-nav-toggle`; si faltan, el script falla y ninguna animación se
 * inicializa. Por eso este parcial siempre los imprime.
 *
 * Los enlaces llegarán del CMS en la tarea 4.8; por ahora vienen del controlador.
 *
 * @var string $siteName
 * @var array<int, array{label: string, href: string}> $navLinks
 */

$navLinks = $navLinks ?? [];

// Los enlaces del menú apuntan a secciones de la portada ("#services"). En
// cualquier otra página esos destinos no existen, así que se antepone la raíz
// del sitio: desde /ingresar, "Servicios" lleva a la portada y baja a la
// sección, en lugar de no hacer nada.
$currentPath = trim((string) app(\GB\Support\Request::class)->path(), '/');
$anchorPrefix = $currentPath === '' ? '' : url('/') . '/';

$navHref = static function (string $href) use ($anchorPrefix): string {
    return str_starts_with($href, '#') ? $anchorPrefix . $href : $href;
};

// El acceso de estudiantes es una función de la plataforma, no una sección del
// contenido, así que se dibuja aquí y no en los bloques administrables: tiene
// que estar siempre y en todas las páginas.
$isLoggedIn = app(\GB\Support\Auth::class)->check();
?>
<header id="header" class="header d-flex align-items-center sticky-top">
  <div class="container-fluid container-xl position-relative d-flex align-items-center">

    <a href="<?= e(url('/')) ?>" class="logo d-flex align-items-center me-auto">
      <img src="<?= e(asset('img/Logo1.png')) ?>" alt="<?= e($siteName) ?>" class="img-fluid img_logo" style="max-width: 180px;">
    </a>

    <nav id="navmenu" class="navmenu">
      <ul>
        <?php foreach ($navLinks as $index => $link): ?>
          <li>
            <a href="<?= e($navHref((string) $link['href'])) ?>"<?= $index === 0 && $currentPath === '' ? ' class="active"' : '' ?>><?= e($link['label']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
    </nav>

    <div class="header-account d-flex align-items-center gap-3">
      <?php if ($isLoggedIn): ?>
        <a class="header-account__link" href="<?= e(url('mi-cuenta')) ?>">
          <i class="bi bi-person-check me-1"></i>Mi cuenta
        </a>
      <?php else: ?>
        <a class="header-account__link" href="<?= e(url('ingresar')) ?>">
          <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
        </a>
        <a class="header-account__link" href="<?= e(url('registro')) ?>">Crear cuenta</a>
      <?php endif; ?>
    </div>

    <a class="btn-getstarted" href="<?= e($navHref('#contact')) ?>">EMPEZAR</a>
  </div>
</header>