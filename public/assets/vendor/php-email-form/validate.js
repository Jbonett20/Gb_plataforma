(function () {
  "use strict";

  let forms = document.querySelectorAll('.php-email-form');

  forms.forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      const action = form.getAttribute('action');
      const recaptcha = form.getAttribute('data-recaptcha-site-key');

      if (!action) {
        displayError(form, 'La propiedad action del formulario no está definida.');
        return;
      }

      form.querySelector('.loading').classList.add('d-block');
      form.querySelector('.error-message').classList.remove('d-block');
      form.querySelector('.sent-message').classList.remove('d-block');

      let formData = new FormData(form);

      if (recaptcha) {
        if (typeof grecaptcha !== "undefined") {
          grecaptcha.ready(function () {
            try {
              grecaptcha.execute(recaptcha, { action: 'php_email_form_submit' })
                .then(token => {
                  formData.set('recaptcha-response', token);
                  php_email_form_submit(form, action, formData);
                });
            } catch (error) {
              displayError(form, error);
            }
          });
        } else {
          displayError(form, '¡La API de reCAPTCHA no está cargada!');
        }
      } else {
        php_email_form_submit(form, action, formData);
      }
    });
  });

  function php_email_form_submit(form, action, formData) {
    fetch(action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => response.text())
      .then(data => {
        form.querySelector('.loading').classList.remove('d-block');

        let json;
        try {
          json = JSON.parse(data);
        } catch (e) {
          throw new Error('Respuesta inválida del servidor: ' + data);
        }

        if (json.success === true || json.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: '¡Mensaje enviado!',
            text: json.message || 'Tu mensaje fue enviado correctamente.',
            confirmButtonColor: '#602350',
            iconColor: '#602350'
          });
          form.reset();
        } else {
          throw new Error(json.message || 'Hubo un error al enviar el mensaje');
        }
      })
      .catch((error) => {
        displayError(form, error);
      });
  }

  function displayError(form, error) {
    form.querySelector('.loading').classList.remove('d-block');
    Swal.fire({
      icon: 'error',
      title: 'Oops...',
      text: error.toString(),
      confirmButtonColor: '#d33'
    });
  }

})();
