<?php

declare(strict_types=1);

/**
 * Visibilidad de los módulos del sitio.
 *
 * @var array<int, array<string, mixed>> $modules
 * @var array<string, array{label: string, help: string}> $states
 * @var string $message
 */

$modules = is_array($modules ?? null) ? $modules : [];
$states = is_array($states ?? null) ? $states : [];
$message = (string) ($message ?? '');

$stateKeys = array_values(array_filter(array_keys($states), static fn (string $key): bool => $key !== 'deleted'));
$stateKeys[] = 'deleted';
?>
<h1 class="h3 mb-1">Módulos del sitio</h1>
<p class="text-muted">Decide qué secciones se muestran en la web. Nada se borra: solo cambia de estado.</p>

<?php if ($message !== ''): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<div class="admin-card card mb-3">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Sección</th><th>Qué es</th><th>Estado actual</th><th class="text-end">Cambiar</th></tr>
      </thead>
      <tbody>
        <?php foreach ($modules as $module): ?>
          <tr>
            <td><strong><?= e((string) $module['label']) ?></strong></td>
            <td class="text-muted small"><?= e((string) ($module['description'] ?? '')) ?></td>
            <td>
              <?= e((string) ($states[$module['status']]['label'] ?? $module['status'])) ?>
              <?php if (($module['deleted_at'] ?? null) !== null): ?>
                <br><small class="text-muted">En la papelera desde <?= e((string) $module['deleted_at']) ?></small>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <form method="post" action="<?= e(url('admin/modulos/' . (int) $module['id'])) ?>" class="d-flex gap-2 justify-content-end">
                <?= csrf_field() ?>
                <label class="visually-hidden" for="estado-<?= (int) $module['id'] ?>">Estado de <?= e((string) $module['label']) ?></label>
                <select class="form-select form-select-sm w-auto" id="estado-<?= (int) $module['id'] ?>" name="status"
                        onchange="this.form.dataset.estado = this.value;">
                  <?php foreach ($stateKeys as $key): ?>
                    <?php if (!isset($states[$key])) { continue; } ?>
                    <option value="<?= e($key) ?>" <?= $module['status'] === $key ? 'selected' : '' ?>>
                      <?= e((string) $states[$key]['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary"
                        onclick="return confirmEstado(this.form);">Aplicar</button>
              </form>

              <?php if (($module['deleted_at'] ?? null) !== null): ?>
                <a class="btn btn-sm btn-outline-danger mt-2"
                   href="<?= e(url('admin/modulos/' . (int) $module['id'] . '/borrar')) ?>">
                  Borrar definitivamente
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($modules === []): ?>
          <tr><td colspan="4" class="text-center py-4">No hay módulos definidos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-card card p-3">
  <h2 class="h6">Qué significa cada estado</h2>
  <dl class="row mb-0 small">
    <?php foreach ($stateKeys as $key): ?>
      <?php if (!isset($states[$key])) { continue; } ?>
      <dt class="col-sm-4"><?= e((string) $states[$key]['label']) ?></dt>
      <dd class="col-sm-8 text-muted"><?= e((string) $states[$key]['help']) ?></dd>
    <?php endforeach; ?>
  </dl>
</div>

<script>
  /*
   * Antes de aplicar un cambio de estado se explica su efecto y se pide
   * confirmación. El aviso depende del estado elegido: no es lo mismo ocultar
   * una sección que mandarla a la papelera.
   */
  var AVISOS = {
    active: 'La sección volverá a verse en el sitio web con todo su contenido. ¿Continuar?',
    inactive: 'La sección dejará de verse en el sitio web. Su contenido se conserva aquí y podrás volver a mostrarlo cuando quieras. ¿Continuar?',
    hidden: 'La sección dejará de aparecer en el menú y en las listas, pero seguirá accesible por enlace directo. ¿Continuar?',
    deleted: 'La sección pasará a la papelera: no se verá en el sitio y su contenido se conservará durante 30 días. Para borrarlo de verdad hay que confirmarlo después. ¿Continuar?'
  };

  function confirmEstado(form) {
    var estado = form.querySelector('select[name="status"]').value;
    return window.confirm(AVISOS[estado] || 'Se cambiará el estado de esta sección. ¿Continuar?');
  }
</script>
