{{-- Modal Editar Marbetes --}}
<x-ui.modal-base id="modalMarbetes" title="Editar Marbetes" size="md" onclose="cerrarModalMarbetes()">
  <p id="marbetes-info" class="text-xs text-gray-600 mb-3"></p>

  <div class="grid grid-cols-2 gap-3">
    @foreach ([
      'pesoRollo' => 'Peso rollo (kg)',
      'repeticiones' => 'Repeticiones',
      'mtsRollo' => 'Mts x rollo',
      'pzasRollo' => 'Pzas x rollo',
      'noMarbete' => 'No. marbetes',
      'totalRollos' => 'Total rollos',
      'totalPzas' => 'Total pzas',
    ] as $campo => $label)
      <label class="text-sm text-gray-700">
        <span class="block mb-1">{{ $label }}</span>
        <input type="number" step="any" min="0" id="marbetes-{{ $campo }}" data-campo="{{ $campo }}"
               class="marbetes-input w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
      </label>
    @endforeach
  </div>

  <div class="flex justify-center mt-4">
    <button type="button" id="btnGuardarMarbetes" onclick="guardarMarbetesEnviar()" class="modal-btn-primary">
      Guardar
    </button>
  </div>
</x-ui.modal-base>

<script>
  (function () {
    var CAMPOS = ['pesoRollo', 'repeticiones', 'mtsRollo', 'pzasRollo', 'noMarbete', 'totalRollos', 'totalPzas'];
    // Columna de la grilla donde se refleja cada campo al guardar.
    var COLUMNAS = {
      pesoRollo: 'PesoRollo', repeticiones: 'Repeticiones', mtsRollo: 'MtsRollo', pzasRollo: 'PzasRollo',
      noMarbete: 'NoMarbete', totalRollos: 'TotalRollos', totalPzas: 'TotalPzas'
    };
    var URL_MARBETES = @json(route('programa-tejido.marbetes'));
    var registroId = null;

    function inp(campo) { return document.getElementById('marbetes-' + campo); }

    function pintar(valores) {
      CAMPOS.forEach(function (c) {
        var el = inp(c);
        if (el) el.value = (valores && valores[c] !== null && valores[c] !== undefined) ? valores[c] : '';
      });
    }

    window.abrirModalMarbetes = function (row) {
      registroId = row ? row.getAttribute('data-id') : null;
      if (!registroId) { notify.error('No hay registro seleccionado'); return; }

      var modal = document.getElementById('modalMarbetes');
      if (!modal) return;
      pintar(null);
      document.getElementById('marbetes-info').textContent = 'Cargando…';
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';

      http.get(URL_MARBETES, { params: { id: registroId } })
        .then(function (res) {
          pintar(res.valores);
          var r = res.registro || {};
          document.getElementById('marbetes-info').textContent =
            'Telar ' + (r.telar || '-') + ' · ' + (r.producto || '') + ' · Tamaño ' + (r.tamano || '-') +
            ' · Tiras ' + (r.noTiras !== null && r.noTiras !== undefined ? r.noTiras : '-') +
            (res.esFel ? ' · FEL (marbetes ×2, mts/pzas ÷2)' : '');
        })
        .catch(function (err) {
          notify.error(err.data?.message || 'No se pudieron cargar los marbetes');
          window.cerrarModalMarbetes();
        });
    };

    window.cerrarModalMarbetes = function () {
      var modal = document.getElementById('modalMarbetes');
      if (modal) { modal.classList.add('hidden'); document.body.style.overflow = ''; }
      registroId = null;
    };

    // Cadena de cálculo (misma que liberar órdenes, regla FEL incluida): al cambiar un campo se
    // envían él y los de arriba, y el servidor recalcula todo lo que va debajo.
    // MtsRollo y No. marbetes no arrastran nada, así que son captura libre.
    var CADENA = ['pesoRollo', 'repeticiones', 'pzasRollo', 'totalRollos'];

    CADENA.forEach(function (campo, i) {
      inp(campo)?.addEventListener('change', function () {
        if (!registroId) return;
        var params = { id: registroId };
        CADENA.slice(0, i + 1).forEach(function (c) {
          var v = parseFloat(inp(c)?.value);
          if (v > 0) params[c] = v;
        });
        http.get(URL_MARBETES, { params: params })
          .then(function (res) { pintar(res.valores); })
          .catch(function () { notify.error('No se pudo recalcular'); });
      });
    });

    window.guardarMarbetesEnviar = function () {
      if (!registroId) return;
      var btn = document.getElementById('btnGuardarMarbetes');
      var payload = { id: registroId };
      CAMPOS.forEach(function (c) {
        var v = inp(c)?.value;
        payload[c] = (v === '' || v === undefined) ? null : parseFloat(v);
      });

      btn.disabled = true;
      btn.textContent = 'Guardando…';

      http.post(@json(route('programa-tejido.marbetes.guardar')), payload)
        .then(function (res) {
          notify.success(res.message || 'Marbetes actualizados');
          var row = document.querySelector('.selectable-row[data-id="' + registroId + '"]');
          if (row) {
            CAMPOS.forEach(function (c) {
              var td = row.querySelector('td[data-column="' + COLUMNAS[c] + '"]');
              if (!td) return;
              var v = payload[c];
              td.setAttribute('data-value', v === null ? '' : v);
              td.textContent = v === null ? '' : v;
            });
          }
          window.cerrarModalMarbetes();
        })
        .catch(function (err) { notify.error(err.data?.message || 'Error al guardar marbetes'); })
        .finally(function () { btn.disabled = false; btn.textContent = 'Guardar'; });
    };

    document.addEventListener('keydown', function (e) {
      var m = document.getElementById('modalMarbetes');
      if (e.key === 'Escape' && m && !m.classList.contains('hidden')) window.cerrarModalMarbetes();
    });
  })();
</script>
