<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Crea una contraseña nueva</title>
</head>

<body style="margin:0; padding:0; background-color:#f7f9fd; font-family:Arial, Helvetica, sans-serif; color:#444444;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f9fd; padding:32px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="max-width:560px; background-color:#ffffff; border-radius:12px; padding:32px;">

          <tr>
            <td>
              <h1 style="margin:0 0 4px; font-size:20px; color:#0b2341;">Crea una contraseña nueva</h1>
              <p style="margin:0 0 20px; font-size:14px; color:#6b7280;"><?= e((string) ($siteName ?? '')) ?></p>
            </td>
          </tr>

          <tr>
            <td>
              <p style="margin:0 0 14px; font-size:15px; line-height:1.55;">
                Hola<?= !empty($name) ? ' ' . e((string) $name) : '' ?>:
              </p>
              <p style="margin:0 0 14px; font-size:15px; line-height:1.55;">
                Recibimos una solicitud para restablecer la contraseña de tu cuenta.
                Pulsa el botón para crear una nueva.
              </p>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:12px 0 22px;">
              <a href="<?= e((string) ($link ?? '')) ?>"
                 style="display:inline-block; background-color:#602350; color:#ffffff; text-decoration:none;
                        padding:13px 26px; border-radius:8px; font-size:15px;">
                Crear mi contraseña
              </a>
            </td>
          </tr>

          <tr>
            <td>
              <p style="margin:0 0 14px; font-size:14px; line-height:1.55; color:#444444;">
                El enlace sirve durante <?= e((string) ($minutes ?? 60)) ?> minutos y sólo se puede usar una vez.
                Si no fuiste tú quien lo pidió, ignora este mensaje: tu contraseña actual sigue siendo válida.
              </p>
              <p style="margin:0; font-size:13px; line-height:1.55; color:#6b7280; word-break:break-all;">
                Si el botón no funciona, copia esta dirección en tu navegador:<br>
                <a href="<?= e((string) ($link ?? '')) ?>" style="color:#602350;"><?= e((string) ($link ?? '')) ?></a>
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding-top:24px; border-top:1px solid #e6e9ef;">
              <p style="margin:0; font-size:13px; color:#6b7280;">
                Por seguridad, nunca te pediremos tu contraseña por correo ni por teléfono.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>

</html>
