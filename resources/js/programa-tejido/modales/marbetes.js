import { PT_BOOT } from '../boot.ts';

(function () {
  var CAMPOS = ['pesoRollo', 'repeticiones', 'mtsRollo', 'pzasRollo', 'noMarbete', 'totalRollos', 'totalPzas'];
  // Columna de la grilla donde se refleja cada campo al guardar.
  var COLUMNAS = {
    pesoRollo: 'PesoRollo', repeticiones: 'Repeticiones', mtsRollo: 'MtsRollo', pzasRollo: 'PzasRollo',
    noMarbete: 'NoMarbete', totalRollos: 'TotalRollos', totalPzas: 'TotalPzas'
  };
  var URL_MARBETES = PT_BOOT.routes.marbetes;
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

    http.post(PT_BOOT.routes.marbetesGuardar, payload)
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
