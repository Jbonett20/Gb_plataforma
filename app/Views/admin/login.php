<?php

declare(strict_types=1);

/**
 * Acceso al panel de administración.
 *
 * Deliberadamente sobrio: no ofrece registro ni recuperación de contraseña,
 * porque una cuenta de administración no se crea ni se restablece desde la web.
 *
 * @var array<string, mixed> $values
 * @var array<string, array<int, string>> $errors
 */

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];

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
            <i class="bi bi-shield-lock me-2"></i>Acceso al panel
          </h1>
          <p class="account-card__lead">Sólo para cuentas de administración.</p>

          <form method="post" action="<?= e(url('admin/ingresar')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="account-field">
              <label for="email">Correo electrónico</label>
              <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>"
                     autocomplete="username" required
                     <?= $errorFor('email') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('email') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('email')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="password">Contraseña</label>
              <input type="password" id="password" name="password" autocomplete="current-password" required
                     <?= $errorFor('password') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('password') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('password')) ?></div>
              <?php endif; ?>
            </div>

            <button type="submit" class="account-button">Entrar al panel</button>
          </form>

          <div class="account-links">
            ¿Eres estudiante? <a href="<?= e(url('ingresar')) ?>">Entra por aquí</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
