<?php

declare(strict_types=1);

/**
 * Catálogo público de cursos.
 *
 * Inicio de la capa de contenido público: lista los cursos publicados del CMS.
 * Aún no cargamos autenticación por correo ni pago: el acceso sigue siendo
 * transitorio por inscripción gratuita y por la preparación del sistema.
 *
 * @var array<int, array<string, mixed>> $courses
 */

$courses = is_array($courses ?? null) ? $courses : [];
$search = (string) ($search ?? '');
$access = (string) ($access ?? '');
$pagination = is_array($pagination ?? null) ? $pagination : [];
$page = (int) ($pagination['page'] ?? 1);
$pages = (int) ($pagination['pages'] ?? 1);
$total = (int) ($pagination['total'] ?? count($courses));
$catalogUrl = static function (int $targetPage) use ($search, $access): string {
  $query = array_filter([
    'q' => $search,
    'access' => $access,
    'page' => $targetPage > 1 ? $targetPage : null,
  ], static fn (mixed $value): bool => $value !== null && $value !== '');

  return url('cursos') . ($query === [] ? '' : '?' . http_build_query($query));
};
?>

<section class="section bg-light-section">
  <div class="container py-5">
    <div class="row justify-content-center mb-4">
      <div class="col-lg-8 text-center">
        <p class="eyebrow text-brand mb-2">Cursos</p>
        <h1 class="h2 mb-3">Aprende con contenido práctico y actualizado</h1>
        <p class="text-muted mb-0">
          Accede a cursos gratuitos y mantén el catálogo preparado para la activación del email y las compras.
        </p>
      </div>
    </div>

    <form method="get" action="<?= e(url('cursos')) ?>" class="row g-2 align-items-end mb-4">
      <div class="col-md-6">
        <label for="course-search" class="form-label">Buscar cursos</label>
        <input type="search" id="course-search" name="q" value="<?= e($search) ?>"
               class="form-control" placeholder="Título, tema o categoría">
      </div>
      <div class="col-md-3">
        <label for="course-access" class="form-label">Tipo de acceso</label>
        <select id="course-access" name="access" class="form-select">
          <option value="">Todos</option>
          <option value="free" <?= $access === 'free' ? 'selected' : '' ?>>Gratuitos</option>
          <option value="paid" <?= $access === 'paid' ? 'selected' : '' ?>>De pago</option>
        </select>
      </div>
      <div class="col-md-3 d-grid">
        <button type="submit" class="btn bg-brand text-white">Filtrar cursos</button>
      </div>
    </form>

    <?php if ($total === 0): ?>
      <div class="text-center py-5">
        <p class="mb-3">No encontramos cursos con esos filtros.</p>
        <a href="<?= e(url('cursos')) ?>" class="btn bg-brand text-white">Ver todos los cursos</a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($courses as $course): ?>
          <?php
          $title = (string) ($course['title'] ?? '');
          $summary = (string) ($course['summary'] ?? '');
          $subtitle = (string) ($course['subtitle'] ?? '');
          $slug = (string) ($course['slug'] ?? '');
          $cover = media_url((int) ($course['cover_media_id'] ?? 0), 900);
          $isEnrolled = (bool) ($course['is_enrolled'] ?? false);
          $accessLabel = (string) ($course['access_label'] ?? 'Curso');
          ?>
          <div class="col-lg-4 col-md-6">
            <article class="card h-100 border-0 shadow-sm">
              <?php if ($cover !== null): ?>
                <img src="<?= e($cover) ?>" class="card-img-top" alt="<?= e($title) ?>" style="height:220px; object-fit:cover;">
              <?php else: ?>
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:220px;">
                  <span class="text-muted">Sin imagen</span>
                </div>
              <?php endif; ?>

              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="badge text-bg-light text-muted"><?= e($accessLabel) ?></span>
                  <?php if ($isEnrolled): ?>
                    <span class="badge text-bg-success">Inscrito</span>
                  <?php endif; ?>
                </div>

                <?php if ($subtitle !== ''): ?>
                  <p class="small text-uppercase text-muted mb-2"><?= e($subtitle) ?></p>
                <?php endif; ?>

                <h2 class="h4 mb-2"><?= e($title) ?></h2>
                <?php if ($summary !== ''): ?>
                  <p class="text-muted flex-grow-1"><?= e($summary) ?></p>
                <?php endif; ?>

                <div class="mt-3 d-grid gap-2">
                  <a href="<?= e(url('cursos/' . $slug)) ?>" class="btn bg-brand text-white">Ver curso</a>
                </div>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="mt-5" aria-label="Páginas de cursos">
          <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
              <li class="page-item"><a class="page-link" href="<?= e($catalogUrl($page - 1)) ?>">Anterior</a></li>
            <?php endif; ?>
            <?php for ($number = 1; $number <= $pages; $number++): ?>
              <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($catalogUrl($number)) ?>" aria-label="Página <?= $number ?>"><?= $number ?></a>
              </li>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
              <li class="page-item"><a class="page-link" href="<?= e($catalogUrl($page + 1)) ?>">Siguiente</a></li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
