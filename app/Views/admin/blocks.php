<?php

declare(strict_types=1);

/**
 * Listado de secciones del sitio público.
 *
 * @var array<int, array<string, mixed>> $blocks
 * @var int $pending
 */

$blocks = is_array($blocks ?? null) ? $blocks : [];
$pending = (int) ($pending ?? 0);
?>
<h1 class="h3 mb-1">Secciones del sitio</h1>
<p class="text-muted">
  Aquí se edita lo que se ve en la web. Guardar deja el cambio preparado; publicar lo hace visible.
</p>

<?php if ($pending > 0): ?>
  <div class="alert alert-warning">
    <?= $pending ?> sección(es) nunca se han publicado, así que todavía no aparecen en el sitio.
  </div>
<?php endif; ?>

<div class="admin-card card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Sección</th><th>Qué es</th><th>Contenido</th><th>Estado</th><th class="text-end">Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($blocks as $block): ?>
          <tr>
            <td><strong><?= e((string) $block['friendly_label']) ?></strong></td>
            <td class="text-muted small"><?= e((string) $block['friendly_help']) ?></td>
            <td class="small"><?= (int) $block['item_count'] ?> elemento(s)</td>
            <td>
              <?php if ($block['is_published']): ?>
                <span class="badge text-bg-success">Publicada</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">Sin publicar</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($block['is_known']): ?>
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(url('admin/bloques/' . (int) $block['id'])) ?>">Editar</a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= e(url('admin/bloques/' . (int) $block['id'] . '/previa')) ?>">Vista previa</a>
              <?php else: ?>
                <span class="text-muted small">Sin campos declarados</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($blocks === []): ?>
          <tr><td colspan="5" class="text-center py-4">No hay secciones definidas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
