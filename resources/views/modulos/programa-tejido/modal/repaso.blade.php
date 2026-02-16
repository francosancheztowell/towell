{{-- Modal Crear Repaso --}}
<div id="modalRepaso" class="hidden fixed left-0 right-0 bottom-0 z-50 overflow-y-auto" aria-labelledby="modalRepasoTitle" aria-modal="true" role="dialog" style="top: var(--pt-navbar-height, 64px); background-color: rgba(0, 0, 0, 0.4);">
  <div class="min-h-full flex items-center justify-center p-4">
  <div class="relative bg-white rounded-lg shadow-xl border border-gray-200 w-full max-w-lg overflow-hidden">
    {{-- Header con título y botón cerrar --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
      <h3 id="modalRepasoTitle" class="text-base font-semibold text-gray-800 uppercase tracking-wide">Crear Repaso</h3>
      <button type="button" onclick="cerrarModalRepaso()" class="p-1 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-gray-300" aria-label="Cerrar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    {{-- Contenido: tabla de campos --}}
    <div class="p-4">
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
        <button type="button" id="btnCrearRepaso" onclick="crearRepasoEnviar()" class="px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 font-medium transition-colors">
          Crear
        </button>
      </div>
    </div>
  </div>
  </div>
</div>

<script>
  (function() {
    var repasoRowId = null;
    var apiBase = '/programa-tejido';

    function getCsrf() {
      var m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') || '' : '';
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

      // Limpiar y resetear
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
      .then(function(r) { return r.json().catch(function() { return {}; }); })
      .then(function(res) {
        if (res.ok) {
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
                  if (typeof toast === 'function') toast(res.message || 'Repaso creado', 'success');
                })
                .catch(function() {
                  if (typeof toast === 'function') toast('Repaso creado. Si no aparece, recargue la página.', 'info');
                });
            }, 200);
          } else {
            if (typeof toast === 'function') toast(res.message || 'Repaso creado', 'success');
          }
        } else {
          if (typeof toast === 'function') toast(res.message || 'Error al crear repaso', 'error');
        }
      })
      .catch(function() {
        if (typeof toast === 'function') toast('Error al crear repaso', 'error');
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
