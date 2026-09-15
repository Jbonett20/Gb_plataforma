<?php

declare(strict_types=1);

/**
 * Cambio de la contraseña inicial del panel.
 *
 * Aparece una sola vez por cuenta, antes de dejar usar el panel: la contraseña
 * con la que se creó la cuenta la conoce quien la creó.
 *
 * @var array<int, array{key: string, text: string}> $requirements
 * @var array<string, array<int, string>> $errors
 * @var string $message
 */

$requirements = is_array($requirements ?? null) ? $requirements : [];
$errors = is_array($errors ?? null) ? $errors : [];
$message = (string) ($message ?? '');

$errorFor = static function (string $field) use ($errors): string {
    return $errors[$field][0] ?? '';
};
?>

<section class="account-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-5 col-md-8">

        <div class="account-card">
          <h1 class="account-card__title" style="font-size:1.35rem;">
            <i class="bi bi-key me-2"></i>Cambia tu contraseña
          </h1>
          <p class="account-card__lead">
            Esta contraseña es temporal. Cámbiala para poder entrar al panel.
          </p>

          <?php if ($message !== '' && $errors === []): ?>
            <div class="account-alert account-alert--error" role="alert"><?= e($message) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('admin/clave')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="account-field">
              <label for="current_password">Contraseña actual</label>
              <input type="password" id="current_password" name="current_password"
                     autocomplete="current-password" required
                     <?= $errorFor('current_password') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('current_password') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('current_password')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="password">Contraseña nueva</label>
              <input type="password" id="password" name="password" autocomplete="new-password" required
                     aria-describedby="requisitos-contrasena"
                     <?= $errorFor('password') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('password') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('password')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="password_confirmation">Repite la contraseña nueva</label>
              <input type="password" id="password_confirmation" name="password_confirmation"
                     autocomplete="new-password" required
                     <?= $errorFor('password_confirmation') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('password_confirmation') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('password_confirmation')) ?></div>
              <?php endif; ?>
            </div>

            <p class="account-card__lead" id="requisitos-contrasena" style="margin-bottom:.25rem;">
              La contraseña nueva necesita:
            </p>
            <ul class="account-requirements">
              <?php foreach ($requirements as $requirement): ?>
                <li><i class="bi bi-check-circle"></i><span><?= e($requirement['text']) ?></span></li>
              <?php endforeach; ?>
            </ul>

            <button type="submit" class="account-button">Guardar y entrar al panel</button>
          </form>

          <div class="account-links">
            <form method="post" action="<?= e(url('salir')) ?>" class="d-inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-link p-0">Cerrar sesión</button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
