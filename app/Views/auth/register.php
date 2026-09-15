<?php

declare(strict_types=1);

/**
 * Alta de cuenta.
 *
 * Los requisitos de contraseña se imprimen desde PasswordPolicy: lo que se
 * explica aquí y lo que se comprueba al guardar son la misma lista.
 *
 * @var array<int, array{key: string, text: string}> $requirements
 * @var array<string, mixed> $values
 * @var array<string, array<int, string>> $errors
 * @var string $message
 */

$requirements = is_array($requirements ?? null) ? $requirements : [];
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
      <div class="col-lg-6 col-md-9">

        <div class="account-card">
          <h2 class="account-card__title">Crear cuenta</h2>
          <p class="account-card__lead">
            Con tu cuenta accedes a los cursos gratuitos y a las plantillas descargables.
          </p>

          <?php if ($message !== '' && $errors === []): ?>
            <div class="account-alert account-alert--error" role="alert"><?= e($message) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('registro')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row">
              <div class="col-md-6">
                <div class="account-field">
                  <label for="name">Nombre</label>
                  <input type="text" id="name" name="name" value="<?= e((string) ($values['name'] ?? '')) ?>"
                         autocomplete="given-name" required
                         <?= $errorFor('name') !== '' ? 'aria-invalid="true"' : '' ?>>
                  <?php if ($errorFor('name') !== ''): ?>
                    <div class="account-field__error"><?= e($errorFor('name')) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-md-6">
                <div class="account-field">
                  <label for="last_name">Apellidos</label>
                  <input type="text" id="last_name" name="last_name"
                         value="<?= e((string) ($values['last_name'] ?? '')) ?>" autocomplete="family-name">
                  <?php if ($errorFor('last_name') !== ''): ?>
                    <div class="account-field__error"><?= e($errorFor('last_name')) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="account-field">
              <label for="email">Correo electrónico</label>
              <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>"
                     autocomplete="email" required
                     <?= $errorFor('email') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('email') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('email')) ?></div>
              <?php else: ?>
                <div class="account-field__help">A esta dirección te enviaremos los avisos de tus cursos.</div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="phone">Teléfono (opcional)</label>
              <input type="tel" id="phone" name="phone" value="<?= e((string) ($values['phone'] ?? '')) ?>"
                     autocomplete="tel">
              <?php if ($errorFor('phone') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('phone')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="password">Contraseña</label>
              <input type="password" id="password" name="password" autocomplete="new-password" required
                     aria-describedby="requisitos-contrasena"
                     <?= $errorFor('password') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('password') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('password')) ?></div>
              <?php endif; ?>
            </div>

            <p class="account-card__lead" id="requisitos-contrasena" style="margin-bottom:.25rem;">
              Tu contraseña necesita:
            </p>
            <ul class="account-requirements">
              <?php foreach ($requirements as $requirement): ?>
                <li><i class="bi bi-check-circle"></i><span><?= e($requirement['text']) ?></span></li>
              <?php endforeach; ?>
              <li><i class="bi bi-shield-lock"></i><span>Se guarda encriptada: nadie puede leerla, ni siquiera nosotros.</span></li>
            </ul>

            <div class="account-field">
              <label style="display:flex; gap:.5rem; align-items:flex-start; font-weight:400;">
                <input type="checkbox" name="terms" value="1" style="width:auto;"
                       <?= !empty($values['terms']) ? 'checked' : '' ?>
                       <?= $errorFor('terms') !== '' ? 'aria-invalid="true"' : '' ?>>
                <span>Acepto que mis datos se usen para gestionar mi cuenta y el acceso a los cursos.</span>
              </label>
              <?php if ($errorFor('terms') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('terms')) ?></div>
              <?php endif; ?>
            </div>

            <button type="submit" class="account-button">Crear mi cuenta</button>
          </form>

          <div class="account-links">
            ¿Ya tienes cuenta? <a href="<?= e(url('ingresar')) ?>">Inicia sesión</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
