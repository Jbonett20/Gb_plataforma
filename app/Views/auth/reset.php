<?php

declare(strict_types=1);

/**
 * Formulario de contraseña nueva, abierto desde el enlace del correo.
 *
 * @var string $token
 * @var array<int, array{key: string, text: string}> $requirements
 * @var array<string, mixed> $values
 * @var array<string, array<int, string>> $errors
 * @var string $message
 */

$token = (string) ($token ?? '');
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
          <h2 class="account-card__title">Crea una contraseña nueva</h2>
          <p class="account-card__lead">Al guardarla entrarás directamente a tu área personal.</p>

          <?php if ($message !== ''): ?>
            <div class="account-alert account-alert--error" role="alert"><?= e($message) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('restablecer-contrasena/' . $token)) ?>" novalidate>
            <?= csrf_field() ?>

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
              <label for="password_confirmation">Repite la contraseña</label>
              <input type="password" id="password_confirmation" name="password_confirmation"
                     autocomplete="new-password" required
                     <?= $errorFor('password_confirmation') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('password_confirmation') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('password_confirmation')) ?></div>
              <?php endif; ?>
            </div>

            <p class="account-card__lead" id="requisitos-contrasena" style="margin-bottom:.25rem;">
              Tu contraseña necesita:
            </p>
            <ul class="account-requirements">
              <?php foreach ($requirements as $requirement): ?>
                <li><i class="bi bi-check-circle"></i><span><?= e($requirement['text']) ?></span></li>
              <?php endforeach; ?>
            </ul>

            <button type="submit" class="account-button">Guardar y entrar</button>
          </form>

          <div class="account-links">
            <a href="<?= e(url('recuperar-contrasena')) ?>">Pedir otro enlace</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
