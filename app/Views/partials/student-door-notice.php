<?php

declare(strict_types=1);

/**
 * Aviso para quien abre una puerta de estudiantes con la sesión del panel
 * abierta.
 *
 * «Inicia sesión» y «Regístrate» son las dos puertas del área de estudiantes.
 * Si quien las pulsa ya entró al panel en ese navegador, no se le puede enviar
 * al panel a la fuerza —sería pasar por una puerta y aparecer en otra
 * habitación—, pero tampoco es correcto dejarle un formulario como si no
 * hubiera entrado nunca: si envía el formulario, la sesión que tiene abierta se
 * sustituye.
 *
 * Así que se le dice lo que pasa y se le da la salida a mano: cerrar la sesión
 * del panel. La dirección del panel no se enlaza aquí a propósito: el sitio
 * público no anuncia la puerta de administración.
 */

if (!auth()->isAdmin()) {
    return;
}

$correo = (string) (auth()->user()['email'] ?? '');
?>
<div class="account-alert account-alert--info" role="status">
  <strong>Tienes abierta la sesión de administración</strong><?php if ($correo !== ''): ?> (<?= e($correo) ?>)<?php endif; ?>.
  Estas dos páginas son del área de estudiantes: si entras o creas una cuenta,
  esa sesión se sustituye por la nueva. Si prefieres seguir como administrador,
  cierra antes esta sesión.

  <form method="post" action="<?= e(url('salir')) ?>" class="d-inline mt-2">
    <?= csrf_field() ?>
    <button type="submit" class="account-button account-button--ghost">Cerrar la sesión del panel</button>
  </form>
</div>
