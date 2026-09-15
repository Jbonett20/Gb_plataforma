<section class="account-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-5 col-md-8">

        <div class="account-card">
          <h2 class="account-card__title">Ese enlace ya no sirve</h2>
          <p class="account-card__lead">
            Los enlaces para crear una contraseña valen una sola vez y duran una hora.
            Este ya se usó o se pasó de tiempo.
          </p>

          <div class="account-alert account-alert--info" role="status">
            Pide uno nuevo y lo recibirás en tu correo en unos segundos.
          </div>

          <a class="account-button" href="<?= e(url('recuperar-contrasena')) ?>">Pedir un enlace nuevo</a>

          <div class="account-links">
            <a href="<?= e(url('ingresar')) ?>">Volver al inicio de sesión</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
