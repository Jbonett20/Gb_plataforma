/**
 * Suscripción a novedades.
 *
 * El formulario del pie se envía sin recargar la página y el resultado se
 * muestra justo al lado, para que quien se suscribe sepa si quedó registrado
 * sin perder de vista lo que estaba leyendo.
 *
 * Si el navegador no puede usar esta vía, el formulario sigue funcionando de
 * forma normal: se envía y el servidor responde con la misma información.
 */
document.querySelectorAll('[data-newsletter-form]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var status = form.querySelector('[data-newsletter-status]');
    var button = form.querySelector('button[type="submit"]');

    if (button) {
      button.disabled = true;
    }

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' }
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (!status) {
          return;
        }

        status.textContent = result.data.message || 'No se pudo completar la suscripción.';
        status.className = 'small ms-2 ' + (result.ok ? 'text-success' : 'text-warning');

        if (result.ok) {
          form.reset();
        }
      })
      .catch(function () {
        if (status) {
          status.textContent = 'No se pudo completar la suscripción. Inténtalo de nuevo.';
          status.className = 'small ms-2 text-warning';
        }
      })
      .finally(function () {
        if (button) {
          button.disabled = false;
        }
      });
  });
});
