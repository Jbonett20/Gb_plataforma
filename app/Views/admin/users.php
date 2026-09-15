<?php

declare(strict_types=1);

/**
 * Cuentas de acceso y roles.
 *
 * @var array<int, array{id: int, key_name: string, label: string}> $roles
 * @var array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} $accounts
 * @var int $activeAdmins
 * @var int|null $currentId
 * @var string $role
 * @var string $search
 * @var array<int, array{key: string, text: string}> $requirements
 */

$roles = is_array($roles ?? null) ? $roles : [];
$accounts = is_array($accounts ?? null) ? $accounts : [];
$items = is_array($accounts['items'] ?? null) ? $accounts['items'] : [];
$activeAdmins = (int) ($activeAdmins ?? 0);
$currentId = $currentId ?? null;
$role = (string) ($role ?? '');
$search = (string) ($search ?? '');
$requirements = is_array($requirements ?? null) ? $requirements : [];
$page = (int) ($accounts['page'] ?? 1);
$pages = (int) ($accounts['pages'] ?? 1);
?>
<h1 class="h3 mb-1">Usuarios y roles</h1>
<p class="text-muted">Quién puede entrar al panel y quién al área de estudiantes.</p>

<div class="admin-card card p-4 mb-4">
  <h2 class="h6 mb-1">Crear una cuenta</h2>
  <p class="text-muted small">
    Úsalo para dar acceso a otra persona sin que tenga que registrarse. El sistema genera
    una contraseña temporal, te la muestra una sola vez y le obliga a cambiarla al entrar.
  </p>

  <form method="post" action="<?= e(url('admin/usuarios')) ?>" class="row g-3 align-items-end">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <label class="form-label" for="nuevo-nombre">Nombre</label>
      <input class="form-control" id="nuevo-nombre" name="name" required maxlength="120">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="nuevo-apellidos">Apellidos</label>
      <input class="form-control" id="nuevo-apellidos" name="last_name" maxlength="120">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="nuevo-correo">Correo electrónico</label>
      <input class="form-control" id="nuevo-correo" name="email" type="email" required maxlength="190">
    </div>
    <div class="col-md-2">
      <label class="form-label" for="nuevo-rol">Rol</label>
      <select class="form-select" id="nuevo-rol" name="role">
        <?php foreach ($roles as $option): ?>
          <option value="<?= e($option['key_name']) ?>"><?= e($option['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1 d-grid">
      <button class="btn btn-primary" type="submit">Crear</button>
    </div>
  </form>

  <?php if (($requirements ?? []) !== []): ?>
    <p class="text-muted small mb-0 mt-3">
      La contraseña temporal cumple estos requisitos:
      <?php foreach ($requirements as $index => $requirement): ?><?= $index > 0 ? ' · ' : '' ?><?= e((string) $requirement['text']) ?><?php endforeach; ?>
    </p>
  <?php endif; ?>
</div>

<?php if ($activeAdmins <= 1): ?>
  <div class="alert alert-warning">
    Solo hay <strong><?= $activeAdmins ?></strong> cuenta de administración activa: esta es la
    última cuenta con acceso al panel. El sistema no permitirá desactivarla ni quitarle el rol,
    porque entonces nadie podría volver a entrar. Crea otra cuenta de administración antes de cambiarla.
  </div>
<?php endif; ?>

<form method="get" action="<?= e(url('admin/usuarios')) ?>" class="row g-2 align-items-end mb-3">
  <div class="col-md-5">
    <label class="form-label" for="user-search">Buscar</label>
    <input class="form-control" id="user-search" name="q" type="search" value="<?= e($search) ?>"
           placeholder="Nombre o correo">
  </div>
  <div class="col-md-4">
    <label class="form-label" for="user-role">Rol</label>
    <select class="form-select" id="user-role" name="role">
      <option value="">Todos</option>
      <?php foreach ($roles as $option): ?>
        <option value="<?= e($option['key_name']) ?>" <?= $role === $option['key_name'] ? 'selected' : '' ?>>
          <?= e($option['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-grid">
    <button class="btn btn-primary" type="submit">Filtrar</button>
  </div>
</form>

<div class="admin-card card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr><th>Cuenta</th><th>Rol</th><th>Estado</th><th>Último ingreso</th><th class="text-end">Cambiar</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $account): ?>
          <?php $accountId = (int) $account['id']; $isSelf = $currentId !== null && $accountId === (int) $currentId; ?>
          <tr>
            <td>
              <strong><?= e(trim((string) $account['name'] . ' ' . (string) ($account['last_name'] ?? ''))) ?></strong>
              <?php if ($isSelf): ?><span class="badge text-bg-light">Tu cuenta</span><?php endif; ?>
              <br><small class="text-muted"><?= e((string) $account['email']) ?></small>
            </td>
            <td>
              <form method="post" action="<?= e(url('admin/usuarios/' . $accountId . '/rol')) ?>"
                    class="d-flex gap-2">
                <?= csrf_field() ?>
                <label class="visually-hidden" for="rol-<?= $accountId ?>">Rol de <?= e((string) $account['email']) ?></label>
                <select class="form-select form-select-sm w-auto" id="rol-<?= $accountId ?>" name="role"
                        <?= $isSelf ? 'disabled' : '' ?>>
                  <?php foreach ($roles as $option): ?>
                    <option value="<?= e($option['key_name']) ?>"
                        <?= $account['role_key'] === $option['key_name'] ? 'selected' : '' ?>>
                      <?= e($option['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit" <?= $isSelf ? 'disabled' : '' ?>
                        onclick="return confirm('Se cambiará el rol de esta cuenta. ¿Continuar?');">Guardar</button>
              </form>
            </td>
            <td><?= $account['status'] === 'active' ? 'Activa' : 'Desactivada' ?></td>
            <td class="small text-muted"><?= e((string) ($account['last_login_at'] ?? 'Nunca')) ?></td>
            <td class="text-end">
              <?php if ($isSelf): ?>
                <span class="text-muted small">Gestionada por otra persona</span>
              <?php else: ?>
                <form method="post" action="<?= e(url('admin/usuarios/' . $accountId . '/estado')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="status" value="<?= $account['status'] === 'active' ? 'inactive' : 'active' ?>">
                  <button class="btn btn-sm btn-outline-<?= $account['status'] === 'active' ? 'danger' : 'success' ?>"
                          type="submit"
                          onclick="return confirm('<?= $account['status'] === 'active'
                              ? 'Se desactivará esta cuenta y no podrá entrar. ¿Continuar?'
                              : 'Se activará esta cuenta. ¿Continuar?' ?>');">
                    <?= $account['status'] === 'active' ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
          <tr><td colspan="5" class="text-center py-4">No hay cuentas con esos filtros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted small mt-2">
  <?= (int) ($accounts['total'] ?? 0) ?> cuenta(s). Desactivar una cuenta no borra nada: se puede volver a activar.
</p>

<?php if ($pages > 1): ?>
  <nav aria-label="Páginas de usuarios">
    <ul class="pagination justify-content-center">
      <?php for ($number = 1; $number <= $pages; $number++): ?>
        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= e(url('admin/usuarios?page=' . $number
              . ($role === '' ? '' : '&role=' . rawurlencode($role))
              . ($search === '' ? '' : '&q=' . rawurlencode($search)))) ?>"><?= $number ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>
