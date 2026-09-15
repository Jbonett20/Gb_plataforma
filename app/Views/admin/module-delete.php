<?php

declare(strict_types=1);

/**
 * Advertencia previa al borrado definitivo de un módulo (tarea 11.6).
 *
 * Es el segundo paso: el módulo ya está en la papelera y aquí se explica, con
 * números, qué se pierde. Ni el botón ni la casilla son decorativos: el servidor
 * exige la confirmación emitida para este módulo y la casilla marcada.
 *
 * @var array<string, mixed> $module
 * @var array{title: string, summary: string, items: array<int, array{label: string, count: int}>, history: array<int, array{label: string, count: int}>, blocked: bool, blockedReason: string} $impact
 * @var string $token
 */

$module = is_array($module ?? null) ? $module : [];
$impact = is_array($impact ?? null) ? $impact : ['blocked' => true, 'blockedReason' => '', 'items' => [], 'history' => [], 'summary' => '', 'title' => ''];
$token = (string) ($token ?? '');
$blocked = (bool) $impact['blocked'];
?>
<div class="mb-3">
  <a href="<?= e(url('admin/modulos')) ?>">&larr; Módulos del sitio</a>
  <h1 class="h3 mt-2 mb-0">Borrar definitivamente «<?= e((string) $module['label']) ?>»</h1>
</div>

<?php if ($blocked): ?>
  <div class="alert alert-danger">
    <strong>No se puede borrar este módulo.</strong>
    <?= e((string) $impact['blockedReason']) ?>
  </div>

  <div class="admin-card card p-4 mb-3">
    <h2 class="h6">Qué hay ligado a este módulo</h2>
    <ul class="mb-0">
      <?php foreach ($impact['history'] as $item): ?>
        <li><?= (int) $item['count'] ?> <?= e((string) $item['label']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <a class="btn btn-primary" href="<?= e(url('admin/modulos')) ?>">Volver sin borrar nada</a>
<?php else: ?>
  <div class="alert alert-warning">
    <strong>Esta acción es irreversible.</strong> Una vez borrado, no hay forma de recuperarlo:
    ni desde el panel ni pidiendo una copia de seguridad, porque los archivos dejan de existir.
  </div>

  <div class="admin-card card p-4 mb-3">
    <h2 class="h6">Qué se va a borrar</h2>
    <p class="text-muted"><?= e((string) $impact['summary']) ?></p>

    <?php if ($impact['items'] !== []): ?>
      <table class="table table-sm mb-0">
        <thead><tr><th>Contenido</th><th class="text-end">Cantidad</th></tr></thead>
        <tbody>
          <?php foreach ($impact['items'] as $item): ?>
            <tr>
              <td><?= e((string) $item['label']) ?></td>
              <td class="text-end"><?= (int) $item['count'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <p class="text-muted small mb-0 mt-3">
      El módulo dejará de aparecer en el panel y en el sitio. Si solo querías que no se viera,
      lo que buscas es marcar «<?= e((string) (config('admin_labels.statuses.inactive.label') ?? 'Oculto al público')) ?>»
      y no hace falta borrar nada.
    </p>
  </div>

  <form method="post" action="<?= e(url('admin/modulos/' . (int) $module['id'] . '/borrar')) ?>"
        class="admin-card card p-4">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="acknowledge" name="acknowledge" value="1" required>
      <label class="form-check-label" for="acknowledge">
        Entiendo que se va a borrar «<?= e((string) $module['label']) ?>» y todo su contenido, y que
        no se puede deshacer.
      </label>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-danger" type="submit"
              onclick="return confirm('Última comprobación: se borrará para siempre. ¿Continuar?');">
        Borrar definitivamente
      </button>
      <a class="btn btn-outline-secondary" href="<?= e(url('admin/modulos')) ?>">Cancelar</a>
    </div>
  </form>
<?php endif; ?>
