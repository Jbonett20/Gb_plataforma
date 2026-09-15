/**
 * Campo de imagen con ajuste de encuadre.
 *
 * Se apoya en las mismas reglas que usa el servidor para recortar:
 *   - la vista previa muestra la imagen con `object-fit: cover` dentro de la
 *     proporción del destino, que es exactamente lo que verá el visitante;
 *   - el punto focal (0 a 1) mueve qué parte se conserva, y es lo que se envía
 *     al servidor para hacer el recorte real.
 *
 * Uso: cualquier contenedor con el atributo `data-image-field`.
 */
(function () {
  'use strict';

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');

  function clamp(value) {
    return Math.max(0, Math.min(1, value));
  }

  function init(field) {
    var box = field.querySelector('[data-image-field-box]');
    var image = field.querySelector('[data-image-field-image]');
    var empty = field.querySelector('[data-image-field-empty]');
    var input = field.querySelector('[data-image-field-input]');
    var clear = field.querySelector('[data-image-field-clear]');
    var status = field.querySelector('[data-image-field-status]');
    var valueInput = field.querySelector('[data-image-field-value]');
    var focalXInput = field.querySelector('[data-image-field-focal-x]');
    var focalYInput = field.querySelector('[data-image-field-focal-y]');
    var ratio = field.getAttribute('data-ratio') || '';
    var uploadUrl = field.getAttribute('data-upload-url') || '';
    var profile = field.getAttribute('data-profile') || '';
    var uploading = false;

    if (!box || !image || !input) {
      return;
    }

    if (ratio) {
      var parts = ratio.split('/');
      if (parts.length === 2) {
        box.style.aspectRatio = parts[0].trim() + ' / ' + parts[1].trim();
      }
    }

    function setStatus(message, kind) {
      if (!status) return;
      status.textContent = message || '';
      status.className = 'image-field__status small ' + (kind ? 'text-' + kind : 'text-muted');
    }

    function applyFocal(x, y) {
      x = clamp(x);
      y = clamp(y);
      image.style.objectPosition = (Math.round(x * 100)) + '% ' + (Math.round(y * 100)) + '%';
      if (focalXInput) focalXInput.value = x.toFixed(4);
      if (focalYInput) focalYInput.value = y.toFixed(4);
    }

    function showImage(url) {
      image.src = url;
      image.hidden = false;
      if (empty) empty.hidden = true;
      if (clear) clear.hidden = false;
    }

    // --- Elegir qué parte se conserva, arrastrando sobre la vista previa ---
    var drag = null;

    box.addEventListener('pointerdown', function (event) {
      if (image.hidden || uploading) return;
      drag = {
        x: event.clientX,
        y: event.clientY,
        focalX: parseFloat(focalXInput ? focalXInput.value : '0.5') || 0.5,
        focalY: parseFloat(focalYInput ? focalYInput.value : '0.5') || 0.5,
        width: box.clientWidth || 1,
        height: box.clientHeight || 1
      };
      box.setPointerCapture(event.pointerId);
      box.classList.add('is-dragging');
      event.preventDefault();
    });

    box.addEventListener('pointermove', function (event) {
      if (!drag) return;
      // Arrastrar hacia la derecha descubre la parte izquierda de la imagen.
      applyFocal(
        drag.focalX - ((event.clientX - drag.x) / drag.width),
        drag.focalY - ((event.clientY - drag.y) / drag.height)
      );
    });

    function endDrag(event) {
      if (!drag) return;
      drag = null;
      box.classList.remove('is-dragging');
      if (event && box.hasPointerCapture && box.hasPointerCapture(event.pointerId)) {
        box.releasePointerCapture(event.pointerId);
      }
    }

    box.addEventListener('pointerup', endDrag);
    box.addEventListener('pointercancel', endDrag);

    // --- Subir la imagen ---
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;

      if (!/^image\//.test(file.type)) {
        setStatus('El archivo elegido no es una imagen.', 'danger');
        input.value = '';
        return;
      }

      // Vista previa inmediata, antes de que termine la subida.
      showImage(URL.createObjectURL(file));
      setStatus('Ajusta el encuadre y espera…', 'muted');

      if (!uploadUrl || !window.fetch) {
        setStatus('El navegador no puede subir la imagen. Guarda el formulario para enviarla.', 'warning');
        return;
      }

      var data = new FormData();
      data.append('file', file);
      data.append('profile', profile);
      data.append('focal_x', focalXInput ? focalXInput.value : '0.5');
      data.append('focal_y', focalYInput ? focalYInput.value : '0.5');

      var headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');

      uploading = true;
      box.classList.add('is-uploading');

      window.fetch(uploadUrl, { method: 'POST', headers: headers, body: data })
        .then(function (response) {
          return response.json().catch(function () { return {}; });
        })
        .then(function (result) {
          if (result && result.error) {
            setStatus(result.message || 'No se pudo guardar la imagen.', 'danger');
            if (result.code === 'too_small') {
              setStatus(result.message + ' Puedes guardar el formulario para continuar de todos modos.', 'warning');
            }
            return;
          }

          if (result && result.media) {
            if (valueInput) valueInput.value = result.media.id;
            showImage(result.media.url);
            setStatus('Imagen guardada y ajustada.', 'success');
          }
        })
        .catch(function () {
          setStatus('No se pudo subir la imagen. Revisa tu conexión e inténtalo otra vez.', 'danger');
        })
        .then(function () {
          uploading = false;
          box.classList.remove('is-uploading');
          input.value = '';
        });
    });

    // --- Quitar la imagen ---
    if (clear) {
      clear.addEventListener('click', function () {
        if (valueInput) valueInput.value = '';
        image.src = '';
        image.hidden = true;
        if (empty) empty.hidden = false;
        clear.hidden = true;
        setStatus('Se quitará al guardar el formulario.', 'muted');
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-image-field]').forEach(init);
  });
})();
