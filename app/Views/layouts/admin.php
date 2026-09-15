<?php

declare(strict_types=1);

/**
 * Layout base del panel de administración.
 *
 * Usa los mismos tokens de marca que el sitio público. El menú se construye a
 * partir de `config/admin_labels.php` (tarea 11.2) y cada pantalla muestra su
 * ayuda en lenguaje no técnico (tarea 11.4).
 *
 * @var string $content
 * @var string $siteName
 * @var string|null $title
 */

use GB\Support\AdminMenu;
use GB\Support\Request;

$siteName = $siteName ?? (string) config('app.name');
$title = $title ?? 'Panel de administración';

$adminMenu = app(AdminMenu::class);
$currentPath = app(Request::class)->path() === '/' ? '' : ltrim(app(Request::class)->path(), '/');
$screenHelp = $adminMenu->helpFor($currentPath);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title) ?></title>

  <link href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>" rel="stylesheet">
  <link href="<?= e(asset('css/theme.css')) ?>" rel="stylesheet">

  <style>
    /* Panel: superficie clara y acentos de marca. */
    body { background-color: var(--light-background-color); color: var(--default-color); }
    .admin-topbar { background-color: var(--accent-color); color: var(--contrast-color); }
    .admin-topbar a { color: var(--contrast-color); text-decoration: none; }
    .admin-card { background-color: var(--surface-color); border: 0; box-shadow: 0 .125rem .5rem rgba(11,35,65,.08); }
    .admin-nav a { display: block; padding: .5rem .75rem; border-radius: .375rem; color: var(--default-color); text-decoration: none; }
    .admin-nav a:hover, .admin-nav a.active { background-color: var(--accent-soft); color: var(--accent-color); }
    .admin-nav__group { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; padding: .75rem .75rem .25rem; }
  </style>
</head>

<body>

  <header class="admin-topbar py-3 mb-4">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="<?= e(url('/')) ?>" class="fw-bold">
        <i class="bi bi-shield-lock me-2"></i>Panel de administración
      </a>
      <span class="small opacity-75"><?= e($siteName) ?></span>
    </div>
  </header>

  <div class="container pb-5">
    <div class="row g-4">
      <aside class="col-lg-3">
        <nav class="admin-card card p-2 admin-nav" aria-label="Secciones del panel">
          <?php if ($currentPath === 'admin'): ?>
            <a href="<?= e(url('admin')) ?>" class="active">Resumen</a>
          <?php endif; ?>
          <?php foreach ($adminMenu->groups() as $group): ?>
            <p class="admin-nav__group mb-1"><?= e($group['label']) ?></p>
            <?php foreach ($group['items'] as $item): ?>
              <a href="<?= e(url($item['path'])) ?>"
                 class="<?= $adminMenu->isActive($item['path'], $currentPath) ? 'active' : '' ?>">
                <i class="bi <?= e($item['icon']) ?> me-2"></i><?= e($item['label']) ?>
              </a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </nav>
      </aside>

      <div class="col-lg-9">
        <?php if ($screenHelp !== null): ?>
          <div class="alert alert-light border" role="note">
            <i class="bi bi-info-circle me-1"></i><?= e($screenHelp) ?>
          </div>
        <?php endif; ?>
        <?php $flashMessages = app(\GB\Support\Flash::class)->pull(); ?>
        <?php foreach ($flashMessages as $flashMessage): ?>
          <div class="alert alert-<?= e((string) $flashMessage['type']) ?>" role="status">
            <?= e((string) $flashMessage['message']) ?>
          </div>
        <?php endforeach; ?>
        <?= $content ?>
      </div>
    </div>
  </div>

  <script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(asset('js/image-field.js')) ?>"></script>
</body>

</html>
