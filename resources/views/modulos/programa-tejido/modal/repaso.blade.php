{{-- Modal Crear Repaso --}}
<x-ui.modal-base id="modalRepaso" title="Crear Repaso" size="md" onclose="cerrarModalRepaso()">
  <table class="min-w-full border border-gray-300">
    <thead>
      <tr class="bg-gray-50">
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Telar</th>
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Ancho</th>
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Hilo</th>
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Calibre</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="px-3 py-2 border-r border-gray-300">
          <select id="repaso-telar" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">Seleccione telar...</option>
          </select>
        </td>
        <td class="px-3 py-2 border-r border-gray-300">
          <input type="number" id="repaso-ancho" step="any" min="0" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="">
        </td>
        <td class="px-3 py-2 border-r border-gray-300">
          <select id="repaso-hilo" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">Seleccione hilo...</option>
          </select>
        </td>
        <td class="px-3 py-2">
          <input type="number" id="repaso-calibre" step="any" min="0" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="">
        </td>
      </tr>
    </tbody>
  </table>

  <div class="flex justify-center mt-4">
    <button type="button" id="btnCrearRepaso" onclick="crearRepasoEnviar()" class="modal-btn-primary">
      Crear
    </button>
  </div>
</x-ui.modal-base>

<script>
  (function() {
    var repasoRowId = null;
    var apiBase = '/programa-tejido';

    function getCsrf() {
      var m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') || '' : '';
    }

    /** Misma convención que el resto del módulo: showToast en layout; toastr/Swal como respaldo. */
    function notifyRepaso(msg, type) {
      type = type || 'success';
      if (typeof window.showToast === 'function') {
        window.showToast(msg, type);
        return;
      }
      if (typeof toastr !== 'undefined') {
        var fn = type === 'error' ? 'error' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success'));
        if (typeof toastr[fn] === 'function') toastr[fn](msg);
        return;
      }
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
          text: msg,
          timer: 2800,
          showConfirmButton: false
        });
      }
    }

    function cargarHilos() {
      return fetch(apiBase + '/hilos-options', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() }
      })
      .then(function(r) { return r.ok ? r.json() : []; })
      .then(function(data) {
        var arr = Array.isArray(data) ? data : (data && data.error ? [] : []);
        var sel = document.getElementById('repaso-hilo');
        if (!sel) return;
        sel.innerHTML = '<option value="">Seleccione hilo...</option>';
        arr.forEach(function(h) {
          var v = typeof h === 'object' ? (h.value || h.Hilo || h.id || '') : String(h);
          var l = typeof h === 'object' ? (h.label || h.Hilo || h.nombre || v) : v;
          if (v) sel.appendChild(new Option(l, v));
        });
      })
      .catch(function() {});
    }

    function cargarTelares() {
      return fetch(apiBase + '/telares-all', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() }
      })
      .then(function(r) { return r.ok ? r.json() : []; })
      .then(function(data) {
        var arr = Array.isArray(data) ? data : [];
        var sel = document.getElementById('repaso-telar');
        if (!sel) return;
        sel.innerHTML = '<option value="">Seleccione telar...</option>';
        arr.forEach(function(item) {
          if (item && item.value) sel.appendChild(new Option(item.label || item.value, item.value));
        });
      })
      .catch(function() {});
    }

    window.abrirModalRepaso = function(row) {
      repasoRowId = row ? row.getAttribute('data-id') : null;
      var modal = document.getElementById('modalRepaso');
      if (!modal) return;

      var telarSel = document.getElementById('repaso-telar');
      var anchoInp = document.getElementById('repaso-ancho');
      var hiloSel = document.getElementById('repaso-hilo');
      var calibreInp = document.getElementById('repaso-calibre');
      if (telarSel) telarSel.selectedIndex = 0;
      if (anchoInp) anchoInp.value = '';
      if (hiloSel) hiloSel.selectedIndex = 0;
      if (calibreInp) calibreInp.value = '';

      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';

      Promise.all([cargarHilos(), cargarTelares()]).then(function() {
        var first = document.getElementById('repaso-telar');
        if (first) setTimeout(function() { first.focus(); }, 100);
      });
    };

    window.cerrarModalRepaso = function() {
      var modal = document.getElementById('modalRepaso');
      if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
      }
      repasoRowId = null;
    };

    window.crearRepasoEnviar = function() {
      var btn = document.getElementById('btnCrearRepaso');
      var telarSel = document.getElementById('repaso-telar');
      var ancho = document.getElementById('repaso-ancho');
      var hiloSel = document.getElementById('repaso-hilo');
      var calibre = document.getElementById('repaso-calibre');

      var telarVal = telarSel ? (telarSel.options[telarSel.selectedIndex] || {}).value : '';
      var data = {
        id: repasoRowId,
        telar: (telarVal || '').trim(),
        ancho: ancho ? (ancho.value === '' ? '' : parseFloat(ancho.value)) : '',
        hilo: hiloSel ? (hiloSel.options[hiloSel.selectedIndex] || {}).value || '' : '',
        calibre: calibre ? (calibre.value === '' ? '' : parseFloat(calibre.value)) : ''
      };

      var labelOriginal = btn ? btn.textContent : '';
      if (btn) {
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('opacity-70', 'cursor-wait', 'pointer-events-none');
        btn.textContent = 'Creando…';
      }

      var base = (typeof PT_BASE_PATH !== 'undefined' ? PT_BASE_PATH : '/planeacion/programa-tejido');
      fetch(base + '/crear-repaso', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrf(),
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      })
      .then(function(r) {
        return r.json().then(function(body) {
          return { httpOk: r.ok, status: r.status, body: body };
        }).catch(function() {
          return { httpOk: r.ok, status: r.status, body: {} };
        });
      })
      .then(function(pack) {
        var res = pack.body || {};
        var success = res.ok === true && pack.httpOk;
        if (success) {
          cerrarModalRepaso();
          if (typeof agregarRegistroSinRecargar === 'function' && res.id) {
            var reg = res.registro && typeof res.registro === 'object' ? res.registro : null;
            var payload = {
              registro_id: res.id,
              message: res.message || 'Repaso creado',
              registro: reg,
              registros_datos: reg ? (function() { var o = {}; o[String(res.id)] = reg; return o; })() : null
            };
            setTimeout(function() {
              agregarRegistroSinRecargar(payload)
                .then(function() {
                  notifyRepaso(res.message || 'Repaso creado', 'success');
                })
                .catch(function() {
                  notifyRepaso('Repaso creado. Si no aparece, recargue la página.', 'info');
                });
            }, 200);
          } else {
            notifyRepaso(res.message || 'Repaso creado', 'success');
          }
          return;
        }
        var errMsg = res.message || 'Error al crear repaso';
        if (res.errors && typeof res.errors === 'object') {
          var keys = Object.keys(res.errors);
          if (keys.length) {
            var first = res.errors[keys[0]];
            if (Array.isArray(first) && first[0]) errMsg = String(first[0]);
            else if (typeof first === 'string') errMsg = first;
          }
        }
        if (!pack.httpOk && pack.status === 419) {
          errMsg = 'Sesión expirada. Recargue la página e intente de nuevo.';
        }
        notifyRepaso(errMsg, 'error');
      })
      .catch(function() {
        notifyRepaso('Error de conexión al crear repaso', 'error');
      })
      .finally(function() {
        if (btn) {
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
          btn.classList.remove('opacity-70', 'cursor-wait', 'pointer-events-none');
          btn.textContent = labelOriginal || 'Crear';
        }
      });
    };

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        var m = document.getElementById('modalRepaso');
        if (m && !m.classList.contains('hidden')) cerrarModalRepaso();
      }
    });
  })();
</script>
