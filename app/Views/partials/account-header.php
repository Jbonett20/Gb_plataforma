<?php

declare(strict_types=1);

/**
 * Cabecera del área personal: saludo y navegación entre sus pantallas.
 *
 * @var array<string, mixed> $student
 * @var string $currentSection
 */

$student = is_array($student ?? null) ? $student : [];
$currentSection = (string) ($currentSection ?? 'cursos');

$fullName = trim(((string) ($student['name'] ?? '')) . ' ' . ((string) ($student['last_name'] ?? '')));
?>

<div class="account-header">
  <div class="container">
    <div class="account-header__row">
      <h2>
        Hola, <?= e($fullName !== '' ? $fullName : 'estudiante') ?>
      </h2>

      <nav class="account-nav" aria-label="Secciones de mi cuenta">
        <a href="<?= e(url('mi-cuenta')) ?>" class="<?= $currentSection === 'cursos' ? 'is-active' : '' ?>">
          Mis cursos
        </a>
        <a href="<?= e(url('mi-cuenta/perfil')) ?>" class="<?= $currentSection === 'perfil' ? 'is-active' : '' ?>">
          Mis datos
        </a>
        <form method="post" action="<?= e(url('salir')) ?>" class="d-inline">
          <?= csrf_field() ?>
          <button type="submit" class="account-button account-button--ghost">Cerrar sesión</button>
        </form>
      </nav>
    </div>
  </div>
</div>
