/* =============================================================================
   account.js — Marcado de lecciones completadas
   -----------------------------------------------------------------------------
   Al pulsar el círculo de una lección se avisa al servidor y se actualiza la
   barra de avance sin recargar la página.

   El porcentaje que devuelve el servidor es el que manda: se guarda aquí lo que
   responde el servidor y no lo que el navegador calcula por su cuenta, para que
   la pantalla y lo guardado no puedan discrepar.
   ============================================================================= */

(function () {
  'use strict';

  var csrfInput = document.querySelector('input[name="_token"]');

  if (!csrfInput) {
    return;
  }

  function updateProgress(courseId, percent) {
    var bar = document.querySelector('[data-progress-bar="' + courseId + '"]');
    var label = document.querySelector('[data-progress-label="' + courseId + '"]');
    var wrap = bar ? bar.closest('[role="progressbar"]') : null;

    if (bar) {
      bar.style.width = percent + '%';
    }

    if (wrap) {
      wrap.setAttribute('aria-valuenow', String(percent));
    }

    if (label) {
      label.textContent = percent + '%';
    }
  }

  function toggle(item) {
    var lessonId = item.getAttribute('data-toggle-lesson');
    var courseId = item.getAttribute('data-course');
    var wasDone = item.getAttribute('data-completed') === '1';
    var nextState = !wasDone;
    var icon = item.querySelector('i');
    var row = item.closest('.lesson-list__item');
    var url = window.GB_ACCOUNT_BASE + '/cursos/' + encodeURIComponent(courseId)
      + '/lecciones/' + encodeURIComponent(lessonId) + '/completar';

    item.disabled = true;

    var body = new URLSearchParams();
    body.append('_token', csrfInput.value);
    body.append('completed', nextState ? '1' : '0');

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (response) {
        return response.json().catch(function () {
          return { ok: false };
        });
      })
      .then(function (data) {
        if (!data.ok) {
          // Si algo falla se deja la pantalla como estaba: es preferible a
          // mostrar un avance que no se guardó.
          return;
        }

        item.setAttribute('data-completed', nextState ? '1' : '0');
        item.setAttribute('aria-pressed', nextState ? 'true' : 'false');

        if (row) {
          row.classList.toggle('is-done', nextState);
        }

        if (icon) {
          icon.className = 'bi ' + (nextState ? 'bi-check-circle-fill' : 'bi-circle');
        }

        if (typeof data.percent === 'number') {
          updateProgress(courseId, data.percent);
        }
      })
      .catch(function () {
        // Sin conexión: no se cambia nada en pantalla.
      })
      .finally(function () {
        item.disabled = false;
      });
  }

  document.querySelectorAll('[data-toggle-lesson]').forEach(function (item) {
    item.addEventListener('click', function (event) {
      event.preventDefault();
      toggle(item);
    });
  });
})();
