<?php

declare(strict_types=1);

/**
 * Petición de enlace para restablecer la contraseña.
 *
 * @var array<string, mixed> $values
 * @var array<string, array<int, string>> $errors
 * @var string $message
 * @var bool $mailActive si el envío de correo ya está activado
 */

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$message = (string) ($message ?? '');

$errorFor = static function (string $field) use ($errors): string {
    return $errors[$field][0] ?? '';
};

// El aviso de "revisa tu correo" es informativo; el fallo de envío es un error.
$isNotice = $errors === [] && $message !== '';

// Mientras no haya servidor de correo configurado, conviene decirlo aquí: el
// enlace queda guardado en el sitio, pero no sale ningún mensaje.
$mailActive = (bool) ($mailActive ?? true);
?>

<section class="account-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-5 col-md-8">

        <div class="account-card">
          <h2 class="account-card__title">Recuperar contraseña</h2>
          <p class="account-card__lead">
            <?php if ($mailActive): ?>
              Escribe el correo de tu cuenta y te enviamos un enlace para crear una contraseña nueva.
            <?php else: ?>
              Escribe el correo de tu cuenta y preparamos el enlace para crear una contraseña nueva.
              El envío de correo del sitio todavía no está activo: si no lo recibes, escríbenos
              y te lo hacemos llegar.
            <?php endif; ?>
          </p>

          <?php if ($isNotice): ?>
            <div class="account-alert account-alert--ok" role="status"><?= e($message) ?></div>
          <?php elseif ($message !== ''): ?>
            <div class="account-alert account-alert--error" role="alert"><?= e($message) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('recuperar-contrasena')) ?>" novalidate>
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

            <button type="submit" class="account-button">Enviarme el enlace</button>
          </form>

          <div class="account-links">
            <a href="<?= e(url('ingresar')) ?>">Volver al inicio de sesión</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
