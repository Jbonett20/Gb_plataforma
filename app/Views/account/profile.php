<?php

declare(strict_types=1);

/**
 * Datos personales y cambio de contraseña.
 *
 * El correo electrónico se muestra pero no se puede editar: identifica la
 * cuenta. Cambiarlo requiere comprobar que la dirección nueva es de la persona,
 * y eso se hace desde el panel de administración.
 *
 * @var array<int, array{key: string, text: string}> $requirements
 * @var array<string, mixed> $values
 * @var array<string, array<int, string>> $errors
 * @var string $message
 * @var array<string, mixed> $student
 */

$requirements = is_array($requirements ?? null) ? $requirements : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$message = (string) ($message ?? '');
$student = is_array($student ?? null) ? $student : [];

$errorFor = static function (string $field) use ($errors): string {
    return $errors[$field][0] ?? '';
};

$value = static function (string $field, string $fallback) use ($values): string {
    return (string) ($values[$field] ?? $fallback);
};

$currentSection = 'perfil';
require GB_APP_PATH . '/Views/partials/account-header.php';
?>

<section class="account-section">
  <div class="container">
    <div class="row gy-4">

      <div class="col-lg-6">
        <div class="account-card">
          <h3 class="account-card__title" style="font-size:1.15rem;">Mis datos</h3>
          <p class="account-card__lead">Así te llamamos y así te contactamos.</p>

          <?php if ($message !== '' && ($errors === [])): ?>
            <div class="account-alert account-alert--ok" role="status"><?= e($message) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('mi-cuenta/perfil')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="account-field">
              <label for="name">Nombre</label>
              <input type="text" id="name" name="name"
                     value="<?= e($value('name', (string) ($student['name'] ?? ''))) ?>" required
                     <?= $errorFor('name') !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($errorFor('name') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('name')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="last_name">Apellidos</label>
              <input type="text" id="last_name" name="last_name"
                     value="<?= e($value('last_name', (string) ($student['last_name'] ?? ''))) ?>">
              <?php if ($errorFor('last_name') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('last_name')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="phone">Teléfono</label>
              <input type="tel" id="phone" name="phone"
                     value="<?= e($value('phone', (string) ($student['phone'] ?? ''))) ?>">
              <?php if ($errorFor('phone') !== ''): ?>
                <div class="account-field__error"><?= e($errorFor('phone')) ?></div>
              <?php endif; ?>
            </div>

            <div class="account-field">
              <label for="email_readonly">Correo electrónico</label>
              <input type="email" id="email_readonly" value="<?= e((string) ($student['email'] ?? '')) ?>" readonly>
              <div class="account-field__help">
                Es el dato con el que entras. Si necesitas cambiarlo, escríbenos.
              </div>
            </div>

            <button type="submit" class="account-button">Guardar mis datos</button>
          </form>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="account-card">
          <h3 class="account-card__title" style="font-size:1.15rem;">Cambiar contraseña</h3>
          <p class="account-card__lead">
            Te pedimos la contraseña actual para asegurarnos de que eres tú.
          </p>

          <form method="post" action="<?= e(url('mi-cuenta/contrasena')) ?>" novalidate>
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

            <button type="submit" class="account-button">Cambiar mi contraseña</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>
