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

    <?php
    // El encabezado muestra una cosa u otra según haya sesión, y las dos son
    // funciones de la plataforma —no secciones del contenido—, así que se
    // dibujan aquí y no en los bloques administrables:
    //
    //   - sin sesión: las dos puertas al área de estudiantes, iguales entre sí
    //     (entrar y crear cuenta);
    //   - con sesión: quién eres. Un icono, tu nombre y la salida, para que se
    //     vea de un vistazo que hay una sesión abierta y se pueda cerrar desde
    //     cualquier página.
    //
    // El panel de administración tiene su propia dirección y no se anuncia aquí.
    //
    // Quien manda es `check()`, que mira la sesión en cada llamada. `user()` en
    // cambio recuerda lo que leyó la primera vez, así que no se consulta si no
    // hay sesión: si no, se podría pintar el nombre de alguien que ya salió.
    $haySesion = auth()->check();
    $authUser = $haySesion ? (auth()->user() ?? []) : [];
    $esAdmin = $haySesion && auth()->isAdmin();
    $nombre = trim((string) ($authUser['name'] ?? ''));
    $nombreCompleto = trim($nombre . ' ' . (string) ($authUser['last_name'] ?? ''));
    $areaUrl = url($esAdmin ? 'admin' : 'mi-cuenta');
    $areaLabel = $esAdmin ? 'Ir al panel' : 'Mi cuenta';

    $accountButtons = setting('header_buttons_visible', '1') === '1' ? [
        ['label' => setting('header_login_label', 'Inicia sesión'), 'url' => url('ingresar')],
        ['label' => setting('header_register_label', 'Regístrate'), 'url' => url('registro')],
    ] : [];
    ?>
    <div class="header-account d-flex align-items-center">
      <?php if ($haySesion): ?>
        <details class="header-account__menu">
          <summary class="header-account__user" title="Sesión abierta como <?= e($nombreCompleto) ?>">
            <i class="bi bi-person-check-fill" aria-hidden="true"></i>
            <span class="header-account__name"><?= e($nombre !== '' ? $nombre : 'Mi sesión') ?></span>
            <i class="bi bi-chevron-down header-account__caret" aria-hidden="true"></i>
          </summary>

          <div class="header-account__dropdown">
            <p class="header-account__hi">
              Sesión abierta como <strong><?= e($nombreCompleto) ?></strong>
            </p>
            <a href="<?= e($areaUrl) ?>"><?= e($areaLabel) ?></a>
            <form method="post" action="<?= e(url('salir')) ?>">
              <?= csrf_field() ?>
              <button type="submit">Cerrar sesión</button>
            </form>
          </div>
        </details>
      <?php else: ?>
        <?php foreach ($accountButtons as $button): ?>
          <a class="header-account__button" href="<?= e($button['url']) ?>"><?= e($button['label']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</header>