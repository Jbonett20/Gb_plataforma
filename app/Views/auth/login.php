<?php

declare(strict_types=1);

/**
 * Formulario de ingreso.
 *
 * @var array<string, mixed> $values
 * @var array<string, array<int, string>> $errors
 * @var string $message
 */

$values = is_array($values ?? null) ? $values : [];
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
          <h2 class="account-card__title">Iniciar sesión</h2>
          <p class="account-card__lead">Entra para continuar con tus cursos y tus descargas.</p>

          <?php if ($message !== ''): ?>
            <div class="account-alert account-alert--error" role="alert"><?= e($message) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('ingresar')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="account-field">
              <label for="email">Correo electrónico</label>
              <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>"
                     autocomplete="email" required
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

            <button type="submit" class="account-button">Entrar</button>
          </form>

          <div class="account-links">
            <a href="<?= e(url('recuperar-contrasena')) ?>">Olvidé mi contraseña</a>
            <br>
            ¿Todavía no tienes cuenta? <a href="<?= e(url('registro')) ?>">Créala aquí</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
