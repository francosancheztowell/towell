<script>
    (() => {
      'use strict';

      // ==========================
      // Estado global / caches
      // ==========================
      let adjustingPedidos = false;
      let adjustingFromTotal = false;
      let lastEditedInput = null;
      let totalDisponibleBalanceo = null;

      let gruposDataCache = {};       // ordCompartida => registros
      let lineasCache = {};           // programaId => líneas originales
      let currentGanttRegistros = []; // registros actuales en modal

      // preview debounce + abort
      let previewTimer = null;
      let previewAbort = null;
      let previewVersion = 0;

      // ==========================
      // Helpers generales
      // ==========================
      /**
       * Fechas SQL/Carbon `Y-m-d` o `Y-m-d H:i:s` como calendario/hora local (evita UTC de `new Date('YYYY-MM-DD')` y desfase de 1 día).
       */
      function parseFechaBackendALocal(s) {
        if (s == null || s === '') return null;
        const t = String(s).trim();
        const m = t.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{1,2}):(\d{1,2}):(\d{1,2})(?:\.\d+)?)?/);
        if (m) {
          const y = Number(m[1]);
          const mo = Number(m[2]) - 1;
          const d = Number(m[3]);
          const hh = m[4] !== undefined ? Number(m[4]) : 0;
          const mm = m[5] !== undefined ? Number(m[5]) : 0;
          const ss = m[6] !== undefined ? Number(m[6]) : 0;
          const dt = new Date(y, mo, d, hh, mm, ss);
          return isNaN(dt.getTime()) ? null : dt;
        }
        const fallback = new Date(t);
        return isNaN(fallback.getTime()) ? null : fallback;
      }

      function toDateInputValueLocal(d) {
        if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '';
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
      }

      function formatearFecha(fecha) {
        if (!fecha) return '-';
        try {
          const raw = String(fecha).trim();
          const d = /^\d{4}-\d{2}-\d{2}/.test(raw) ? parseFechaBackendALocal(raw) : new Date(fecha);
          if (!d || isNaN(d.getTime()) || d.getFullYear() <= 1970) return '-';
          return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch (e) {
          return '-';
        }
      }

      function parseNumber(val) {
        if (val === null || val === undefined) return 0;
        const n = Number(String(val).replace(/[^0-9.\-]/g, ''));
        return isNaN(n) ? 0 : n;
      }

      function parseSQLDateToMs(sql) {
        if (!sql) return 0;
        const d = parseFechaBackendALocal(String(sql).trim());
        return !d || isNaN(d.getTime()) ? 0 : d.getTime();
      }

      function sortRegistrosPorFechaTelar(registros) {
        return [...registros].sort((a, b) => {
          const aPosicion = Number(a?.Posicion) || 0;
          const bPosicion = Number(b?.Posicion) || 0;
          if (aPosicion !== bPosicion) return aPosicion - bPosicion;

          const aTelarStr = String(a?.NoTelarId ?? '');
          const bTelarStr = String(b?.NoTelarId ?? '');
          const telarCmp = aTelarStr.localeCompare(bTelarStr, 'es-MX', { numeric: true, sensitivity: 'base' });
          if (telarCmp !== 0) return telarCmp;

          const aId = Number(a?.Id) || 0;
          const bId = Number(b?.Id) || 0;
          return aId - bId;
        });
      }

      function getRowById(id) {
        // Buscar primero en el modal de SweetAlert si existe
        const swalContent = document.querySelector('.swal2-html-container');
        if (swalContent) {
          const rowInModal = swalContent.querySelector(`tr[data-registro-id="${id}"]`);
          if (rowInModal) return rowInModal;
        }
        // Fallback: buscar en todo el documento
        return document.querySelector(`tr[data-registro-id="${id}"]`);
      }

      function getInputById(id) {
        // Buscar primero en el modal de SweetAlert si existe
        const swalContent = document.querySelector('.swal2-html-container');
        if (swalContent) {
          const inputInModal = swalContent.querySelector(`.pedido-input[data-id="${id}"]`);
          if (inputInModal) return inputInModal;
        }
        // Fallback: buscar en todo el documento
        return document.querySelector(`.pedido-input[data-id="${id}"]`);
      }

      function parseDateOnlyTimeToMs(dateValue, timeValue = '00:00:00') {
        if (!dateValue) return null;

        const fecha = String(dateValue).trim().split(' ')[0];
        const hora = String(timeValue || '00:00:00').trim() || '00:00:00';
        const parsed = parseFechaBackendALocal(`${fecha} ${hora}`);
        return parsed && !Number.isNaN(parsed.getTime()) ? parsed.getTime() : null;
      }

      function comparePedidoDesc(a, b) {
        if (a.pedidoActual !== b.pedidoActual) return b.pedidoActual - a.pedidoActual;
        return a.id - b.id;
      }

      function resolveBalanceoLeader(registros = currentGanttRegistros, pedidosById = null) {
        const items = Array.from(registros || []).map(reg => {
          const input = getInputById(reg.Id);
          const pedidoActual = pedidosById && Object.prototype.hasOwnProperty.call(pedidosById, reg.Id)
            ? Math.round(Number(pedidosById[reg.Id]) || 0)
            : (input ? Math.round(Number(input.value) || 0) : Math.round(Number(reg.TotalPedido || 0)));
          const fechaInicioMs = input
            ? (Number(input.dataset.fechaInicio) || parseSQLDateToMs(reg.FechaInicio))
            : parseSQLDateToMs(reg.FechaInicio);

          return {
            id: Number(reg.Id) || 0,
            noTelarId: reg.NoTelarId || '-',
            isLeader: reg.OrdCompartidaLider === 1 || reg.OrdCompartidaLider === true || reg.OrdCompartidaLider === '1',
            fechaInicioMs: fechaInicioMs || null,
            fechaInicioKey: fechaInicioMs ? getDateKeyLocal(new Date(fechaInicioMs)) : null,
            fechaCreacionMs: parseDateOnlyTimeToMs(reg.FechaCreacion, reg.HoraCreacion),
            pedidoActual,
          };
        });

        if (!items.length) return null;

        // El balanceo solo ajusta pedido/fechas; no debe cambiar la orden lider ya definida.
        const persistedLeader = items.find(item => item.isLeader);
        if (persistedLeader) return persistedLeader;

        const todasMismaFechaInicio = new Set(items.map(item => item.fechaInicioKey ?? '__NULL__')).size <= 1;

        items.sort((a, b) => {
          if (!todasMismaFechaInicio) {
            if (a.fechaInicioMs !== null && b.fechaInicioMs !== null && a.fechaInicioMs !== b.fechaInicioMs) {
              return a.fechaInicioMs - b.fechaInicioMs;
            }

            if (a.fechaInicioMs !== null && b.fechaInicioMs === null) return -1;
            if (a.fechaInicioMs === null && b.fechaInicioMs !== null) return 1;

            return comparePedidoDesc(a, b);
          }

          if (a.fechaCreacionMs !== null && b.fechaCreacionMs !== null && a.fechaCreacionMs !== b.fechaCreacionMs) {
            return a.fechaCreacionMs - b.fechaCreacionMs;
          }

          if (a.fechaCreacionMs !== null && b.fechaCreacionMs === null) return -1;
          if (a.fechaCreacionMs === null && b.fechaCreacionMs !== null) return 1;

          return comparePedidoDesc(a, b);
        });

        return items[0] || null;
      }

      function renderBalanceoLeaderBadge(registros = currentGanttRegistros) {
        const leader = resolveBalanceoLeader(registros);
        const leaderId = leader?.id || 0;
        const badge = document.getElementById('balanceo-no-telar-principal');

        if (badge) {
          badge.textContent = leader?.noTelarId || '-';
        }

        document.querySelectorAll('tr[data-registro-id]').forEach(row => {
          const rowId = Number(row.dataset.registroId || 0);
          const isLeader = leaderId > 0 && rowId === leaderId;
          row.className = isLeader
            ? 'bg-amber-100 border-b border-amber-300'
            : 'hover:bg-gray-50 border-b border-gray-200';
        });
      }

      function getLockedTotalBalanceo(inputs = null) {
        if (typeof totalDisponibleBalanceo === 'number' && !Number.isNaN(totalDisponibleBalanceo)) {
          return totalDisponibleBalanceo;
        }

        const totalDisponibleEl = document.getElementById('total-disponible');
        if (totalDisponibleEl) {
          totalDisponibleBalanceo = Math.round(parseNumber(totalDisponibleEl.textContent));
          return totalDisponibleBalanceo;
        }

        const list = Array.from(inputs || document.querySelectorAll('.pedido-input'));
        totalDisponibleBalanceo = list.reduce((sum, input) => sum + (Number(input.dataset.original) || 0), 0);
        return totalDisponibleBalanceo;
      }

      function setLockedTotalBalanceo(total) {
        totalDisponibleBalanceo = Math.round(Number(total) || 0);

        const totalDisponibleEl = document.getElementById('total-disponible');
        if (totalDisponibleEl) {
          totalDisponibleEl.textContent = totalDisponibleBalanceo.toLocaleString('es-MX');
        }
      }

      function getCurrentInputsPayload() {
        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        return inputs.map(inp => ({
          id: Number(inp.dataset.id),
          total_pedido: Math.round(Number(inp.value) || 0),
          modo: 'total'
        }));
      }

      function hasPedidoChanges() {
        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        return inputs.some(inp => {
          const original = Number(inp.dataset.original) || 0;
          const actual = Math.round(Number(inp.value) || 0);
          return actual !== original;
        });
      }

      /** Pedido usado para sumas/saldos sin forzar el valor del input si el usuario sigue editando. */
      function pedidoEfectivoParaCalculo(input, forceNormalize) {
        const produccion = Number(input.dataset.produccion || 0) || 0;
        const raw = String(input.value ?? '').trim();
        const n = Math.round(Number(raw) || 0);
        const isFocused = !forceNormalize && document.activeElement === input;

        if (isFocused && raw === '') {
          return produccion > 0 ? produccion : 0;
        }
        if (produccion > 0) {
          return Math.max(produccion, n);
        }
        return Math.max(0, n);
      }

      function isBalanceoTotalsBalanced() {
        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        const locked = getLockedTotalBalanceo(inputs);
        if (locked <= 0 || inputs.length === 0) {
          return true;
        }
        let sum = 0;
        inputs.forEach(inp => {
          sum += pedidoEfectivoParaCalculo(inp, false);
        });
        return Math.abs(sum - locked) <= 0.0001;
      }

      const PEDIDO_INPUT_BASE_CLASS =
        'pedido-input w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right border rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500';

      const TOTAL_PEDIDO_INPUT_BASE_CLASS =
        'w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right font-bold text-gray-900 border rounded focus:outline-none';

      function updateBalanceoTotalVisualState() {
        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        const totalInput = document.getElementById('total-pedido-input');
        const msg = document.getElementById('balanceo-total-mensaje');
        const locked = getLockedTotalBalanceo(inputs);
        let sum = 0;
        inputs.forEach(inp => {
          sum += pedidoEfectivoParaCalculo(inp, false);
        });
        const mismatch = locked > 0 && inputs.length > 0 && Math.abs(sum - locked) > 0.0001;

        inputs.forEach(inp => {
          inp.className = mismatch
            ? `${PEDIDO_INPUT_BASE_CLASS} border-red-500 ring-1 ring-red-200`
            : `${PEDIDO_INPUT_BASE_CLASS} border-gray-300`;
        });

        if (totalInput) {
          totalInput.className = mismatch
            ? `${TOTAL_PEDIDO_INPUT_BASE_CLASS} border-red-500 ring-1 ring-red-200`
            : `${TOTAL_PEDIDO_INPUT_BASE_CLASS} border-gray-300`;
        }

        if (msg) {
          msg.classList.toggle('hidden', !mismatch);
          if (mismatch) {
            msg.textContent =
              'La suma de pedidos debe coincidir con el total del grupo (' +
              locked.toLocaleString('es-MX') +
              '). Al salir del campo se ajusta el último telar cuando sea posible.';
          }
        }

        syncBalanceoGuardarButtonState();
      }

      function syncBalanceoGuardarButtonState() {
        const popup = document.querySelector('.swal2-popup.balanceo-orden-modal');
        if (!popup) return;
        const btn = popup.querySelector('.swal2-confirm');
        if (!btn) return;
        const ok = isBalanceoTotalsBalanced();
        btn.disabled = !ok;
        btn.setAttribute('aria-disabled', ok ? 'false' : 'true');
        btn.title = ok ? '' : 'La suma de pedidos debe coincidir con el total del grupo antes de guardar.';
        btn.classList.toggle('opacity-50', !ok);
        btn.classList.toggle('cursor-not-allowed', !ok);
      }

      // ==========================
      // API / datos
      // ==========================
      async function fetchRegistrosOrdCompartida(ordCompartida) {
        if (gruposDataCache[ordCompartida]) return gruposDataCache[ordCompartida];

        const resp = await fetch(
          `/planeacion/programa-tejido/registros-ord-compartida/${ordCompartida}`,
          { headers: { Accept: 'application/json' } }
        );

        const data = await resp.json();
        if (data?.success && Array.isArray(data.registros)) {
          const ordenados = sortRegistrosPorFechaTelar(data.registros);
          gruposDataCache[ordCompartida] = ordenados;
          return ordenados;
        }
        throw new Error(data?.message || 'No se pudieron obtener los registros');
      }

      async function fetchLineasPrograma(programaId) {
        if (lineasCache[programaId]) return lineasCache[programaId];

        const resp = await fetch(
          `/planeacion/req-programa-tejido-line?programa_id=${programaId}&per_page=5000&sort=Fecha&dir=asc`,
          { headers: { Accept: 'application/json' } }
        );

        const json = await resp.json();
        if (!json?.success || !json.data?.data) return [];

        lineasCache[programaId] = json.data.data;
        return lineasCache[programaId];
      }

      async function prefetchLineas(registros) {
        const subset = registros.slice(0, 30);
        await Promise.all(subset.map(r => fetchLineasPrograma(r.Id).catch(() => [])));
      }

      // ==========================
      // GANTT helpers (igual que traías)
      // ==========================
      function parseDateISO(str) {
        if (!str) return null;
        const d = parseFechaBackendALocal(String(str).trim());
        return d;
      }

      function formatShort(d) {
        return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
      }

      // Normalizar fecha a medianoche local (sin zona horaria)
      function normalizeToLocalMidnight(date) {
        if (!date) return null;
        const d = date instanceof Date ? date : new Date(date);
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
      }

      // Obtener clave de fecha en formato YYYY-MM-DD usando hora local
      function getDateKeyLocal(date) {
        if (!date) return null;
        const d = date instanceof Date ? date : new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      }

      function buildDateRange(minDate, maxDate) {
        const res = [];
        // Normalizar a medianoche local para evitar problemas de zona horaria
        const min = normalizeToLocalMidnight(minDate);
        const max = normalizeToLocalMidnight(maxDate);
        if (!min || !max) return res;

        const cur = new Date(min.getTime());
        while (cur <= max) {
          res.push(new Date(cur.getTime()));
          cur.setDate(cur.getDate() + 1);
        }
        return res;
      }

      function getCurrentInputsMap() {
        const map = {};
        document.querySelectorAll('.pedido-input').forEach(inp => {
          const id = inp.dataset.id;
          if (!id) return;
          map[id] = {
            pedido: Number(inp.value) || 0,
            fechaInicioMs: Number(inp.dataset.fechaInicio) || 0,
            duracionOriginalMs: Number(inp.dataset.duracionOriginal) || 0,
            fechaFinalCalcMs: Number(inp.dataset.fechaFinalCalculada) || 0
          };
        });
        return map;
      }

      /** Capacidad teórica por día (piezas) = StdDía × (horas productivas ese día / 24). */
      function capByDayFromHorasPorDia(horasPorDia, stdDia) {
        const std = Number(stdDia) || 0;
        if (std <= 0) return null;
        const cap = {};
        Object.entries(horasPorDia).forEach(([key, horasDia]) => {
          cap[key] = Math.round(((std / 24) * horasDia + Number.EPSILON) * 1000) / 1000;
        });
        return cap;
      }

      function mapWithScaledTimeline(reg, _lineasIgnorado, inputsMap) {
        const datos = inputsMap[String(reg.Id)] || {};
        const stdDia = Number(reg.StdDia || 0) || 0;

        const pedidoNuevo = datos.pedido ?? Number(reg.TotalPedido || 0);
        const produccion = Number(reg.Produccion || 0);
        const saldoNuevo = Math.max(0, pedidoNuevo - produccion);
        if (saldoNuevo <= 0) return { map: {}, min: null, max: null, capByDay: null };

        const fechaInicioMs =
          datos.fechaInicioMs ||
          (reg.FechaInicio ? (parseFechaBackendALocal(String(reg.FechaInicio).trim())?.getTime() ?? 0) : 0);

        const fechaFinalCalcMs = datos.fechaFinalCalcMs || 0;

        // si ya hay preview exacto del backend, úsalo
        const fechaFinDestinoMs = fechaFinalCalcMs ||
          (reg.FechaFinal ? (parseFechaBackendALocal(String(reg.FechaFinal).trim())?.getTime() ?? 0) : 0);

        if (!fechaInicioMs || !fechaFinDestinoMs || fechaFinDestinoMs <= fechaInicioMs) {
          const inicioDate = fechaInicioMs ? new Date(fechaInicioMs) : null;
          const key = inicioDate ? getDateKeyLocal(inicioDate) : 'N/A';
          const minNormalized = inicioDate ? normalizeToLocalMidnight(inicioDate).getTime() : null;
          const maxNormalized = fechaFinDestinoMs ? normalizeToLocalMidnight(new Date(fechaFinDestinoMs)).getTime() : null;
          let capByDay = null;
          if (stdDia > 0 && key !== 'N/A') {
            const horasVentana =
              fechaFinDestinoMs > fechaInicioMs
                ? Math.min(24, Math.max(0, (fechaFinDestinoMs - fechaInicioMs) / 3600000))
                : 24;
            capByDay = { [key]: Math.round(((stdDia / 24) * horasVentana + Number.EPSILON) * 1000) / 1000 };
          }
          return { map: { [key]: saldoNuevo }, min: minNormalized, max: maxNormalized, capByDay };
        }

        const inicio = new Date(fechaInicioMs);
        const fin = new Date(fechaFinDestinoMs);

        const totalSegundos = Math.abs(fin.getTime() - inicio.getTime()) / 1000;
        const totalHoras = totalSegundos / 3600.0;
        if (totalHoras <= 0) {
          const key = getDateKeyLocal(inicio);
          const minNormalized = normalizeToLocalMidnight(inicio).getTime();
          const maxNormalized = normalizeToLocalMidnight(fin).getTime();
          const capByDay = stdDia > 0 ? { [key]: stdDia } : null;
          return { map: { [key]: saldoNuevo }, min: minNormalized, max: maxNormalized, capByDay };
        }

        const startDay = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate());
        const endDay = new Date(fin.getFullYear(), fin.getMonth(), fin.getDate());
        const dias = Math.round((endDay - startDay) / 86400000) + 1;

        const horasPorDia = {};
        for (let i = 0; i < dias; i++) {
          const dia = new Date(startDay.getTime() + i * 86400000);
          const esPrimerDia = i === 0;
          const esUltimoDia = dia.toDateString() === endDay.toDateString();
          let fraccion;

          if (esPrimerDia && esUltimoDia) {
            fraccion = ((fin.getTime() - inicio.getTime()) / 1000) / 86400;
          } else if (esPrimerDia) {
            const segundosDesdeMedianoche = inicio.getHours() * 3600 + inicio.getMinutes() * 60 + inicio.getSeconds();
            fraccion = (86400 - segundosDesdeMedianoche) / 86400;
          } else if (esUltimoDia) {
            const realInicio = new Date(dia.getFullYear(), dia.getMonth(), dia.getDate());
            fraccion = ((fin.getTime() - realInicio.getTime()) / 1000) / 86400;
          } else {
            fraccion = 1;
          }

          if (fraccion < 0) fraccion = Math.abs(fraccion);
          const key = getDateKeyLocal(dia);
          horasPorDia[key] = (horasPorDia[key] || 0) + (fraccion * 24.0);
        }

        const stdHrEfectivo = saldoNuevo / totalHoras;
        const map = {};
        Object.entries(horasPorDia).forEach(([key, horasDia]) => {
          const piezas = stdHrEfectivo * horasDia;
          map[key] = (map[key] || 0) + Math.round((piezas + Number.EPSILON) * 1000) / 1000;
        });

        const minNormalized = normalizeToLocalMidnight(inicio).getTime();
        const maxNormalized = normalizeToLocalMidnight(fin).getTime();
        const capByDay = capByDayFromHorasPorDia(horasPorDia, stdDia);
        return { map, min: minNormalized, max: maxNormalized, capByDay };
      }

      function renderGanttGrid(dates, rows) {
        const cont = document.getElementById('gantt-ord');
        const loader = document.getElementById('gantt-loading');
        const wrapper = document.getElementById('gantt-ord-container');
        if (!cont) return;
        if (loader) loader.classList.add('hidden');

        if (!dates.length || !rows.length) {
          cont.innerHTML = '<div class="p-3 text-sm text-gray-500">Sin datos para mostrar.</div>';
          if (wrapper) wrapper.style.height = '180px';
          return;
        }

        if (wrapper) {
          const rowPx = window.innerWidth <= 639 ? 32 : 40;
          const neededHeight = 42 + rows.length * rowPx + 32;
          const maxHeight = Math.round(window.innerHeight * (window.innerWidth <= 639 ? 0.55 : 0.7));
          wrapper.style.height = `${Math.min(neededHeight, maxHeight)}px`;
        }

        const isSmall = window.innerWidth <= 639;
        /* Columna telar compacta; sin max-width en CSS que deje hueco antes de la 1.ª fecha */
        const labelCol = isSmall ? '96px' : '156px';
        const dateCol = isSmall ? '50px' : '60px';
        const template = `${labelCol} repeat(${dates.length}, ${dateCol})`;
        let html = `<div class="gantt-grid" style="grid-template-columns:${template}">`;

        html += `<div class="gantt-cell gantt-header gantt-label gantt-corner"></div>`;
        dates.forEach(d => html += `<div class="gantt-cell gantt-header">${formatShort(d)}</div>`);

        const ganttAttrEscape = (s) =>
          String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');

        rows.forEach((row, idx) => {
          html += `<div class="gantt-cell gantt-label">${row.label}</div>`;
          dates.forEach(d => {
            const key = getDateKeyLocal(d);
            const qty = Math.round(row.map[key] || 0);
            const cap = row.capByDay && row.capByDay[key] != null ? Number(row.capByDay[key]) : null;
            const hasQty = qty > 0;
            let cls = '';
            let title = '';
            if (hasQty && cap != null && cap > 0) {
              const umbral = cap * 0.92;
              const enCap = qty >= umbral;
              cls = enCap ? 'gantt-bar-at-cap' : 'gantt-bar-space';
              const capR = Math.round(cap);
              const espacio = Math.max(0, Math.round(cap - qty));
              title = enCap
                ? `Piezas ${qty.toLocaleString('es-MX')} · ~${capR.toLocaleString('es-MX')} pzas/día prorrateado (cerca del std)`
                : `Piezas ${qty.toLocaleString('es-MX')} · Cap. prorrateada ~${capR.toLocaleString('es-MX')} · Espacio ~${espacio.toLocaleString('es-MX')} pzas`;
            } else if (hasQty) {
              cls = idx % 2 === 0 ? 'gantt-bar' : 'gantt-bar-alt';
              title = `${qty.toLocaleString('es-MX')} pzas (sin Std/Día para comparar)`;
            }
            const displayQty = hasQty ? qty.toLocaleString('es-MX') : '';
            const titleAttr = title ? ` title="${ganttAttrEscape(title)}"` : '';
            html += `<div class="gantt-cell ${cls}"${titleAttr}>${displayQty}</div>`;
          });
        });

        html += `</div>`;
        cont.innerHTML = html;
      }

      async function renderGanttOrd(registros) {
        const loader = document.getElementById('gantt-loading');
        if (loader) loader.classList.remove('hidden');

        const cont = document.getElementById('gantt-ord');
        if (cont) cont.innerHTML = '';

        try {
          const subset = registros.slice(0, 30);
          const data = await Promise.all(
            subset.map(async reg => ({ reg, lineas: await fetchLineasPrograma(reg.Id) }))
          );

          currentGanttRegistros = registros;

          let minD = null;
          let maxD = null;
          const rows = [];

          data.forEach(({ reg, lineas }) => {
            const map = {};
            let localMin = parseDateISO(String(reg.FechaInicio || '').trim());
            let localMax = parseDateISO(String(reg.FechaFinal || '').trim());

            if (Array.isArray(lineas) && lineas.length) {
              lineas.forEach(l => {
                const d = parseDateISO(String(l.Fecha || '').trim());
                if (!d) return;
                const key = getDateKeyLocal(d);
                const qty = Number(l.Cantidad || 0);
                map[key] = (map[key] || 0) + Math.round((qty + Number.EPSILON) * 1000) / 1000;

                if (!localMin || d < localMin) localMin = d;
                if (!localMax || d > localMax) localMax = d;
              });
            }

            if (!localMin || !localMax) return;

            // Normalizar fechas a medianoche local para evitar problemas de zona horaria
            const localMinNormalized = normalizeToLocalMidnight(localMin);
            const localMaxNormalized = normalizeToLocalMidnight(localMax);

            if (localMinNormalized && (!minD || localMinNormalized < minD)) minD = localMinNormalized;
            if (localMaxNormalized && (!maxD || localMaxNormalized > maxD)) maxD = localMaxNormalized;

            const label = `Telar ${reg.NoTelarId || '-'} · ${reg.NombreProducto || ''}`.trim();
            const stdDiaReg = Number(reg.StdDia || 0) || 0;
            let capByDay = null;
            if (stdDiaReg > 0 && Object.keys(map).length) {
              capByDay = {};
              Object.keys(map).forEach(k => {
                capByDay[k] = stdDiaReg;
              });
            }
            rows.push({ label, map, capByDay });
          });

          if (!minD || !maxD || rows.length === 0) {
            if (cont) cont.innerHTML = '<div class="p-3 text-sm text-gray-500">No hay líneas para mostrar.</div>';
            return;
          }

          const days = buildDateRange(minD, maxD);
          renderGanttGrid(days, rows);
        } catch (e) {
          console.error('Error al renderizar gantt', e);
          if (cont) cont.innerHTML = '<div class="p-3 text-sm text-red-600">No se pudo cargar el gantt.</div>';
        } finally {
          if (loader) loader.classList.add('hidden');
        }
      }

      function updateGanttPreview() {
        if (!currentGanttRegistros || !currentGanttRegistros.length) return;

        const inputsMap = getCurrentInputsMap();
        const rows = [];
        let minD = null;
        let maxD = null;

        currentGanttRegistros.forEach(reg => {
          const result = mapWithScaledTimeline(reg, [], inputsMap);
          const map = result.map || {};
          if (!Object.keys(map).length) return;

          // Normalizar fechas mínimas y máximas a medianoche local
          if (result.min) {
            const minNormalized = normalizeToLocalMidnight(new Date(result.min));
            if (minNormalized && (!minD || minNormalized < minD)) minD = minNormalized;
          }
          if (result.max) {
            const maxNormalized = normalizeToLocalMidnight(new Date(result.max));
            if (maxNormalized && (!maxD || maxNormalized > maxD)) maxD = maxNormalized;
          }

          rows.push({
            label: `Telar ${reg.NoTelarId || '-'} · ${reg.NombreProducto || ''}`.trim(),
            map,
            capByDay: result.capByDay ?? null
          });
        });

        if (!rows.length || !minD || !maxD) return;

        const days = buildDateRange(minD, maxD);
        renderGanttGrid(days, rows);
      }

      // ==========================
      // PREVIEW EXACTO (backend)
      // ==========================
      async function previewFechasExactas(ordCompartida) {
        if (!hasPedidoChanges()) {
          return; // No hay cambios: no recalcular ni tocar fechas
        }
        if (!isBalanceoTotalsBalanced()) {
          return;
        }
        const myVersion = ++previewVersion;

        if (previewAbort) previewAbort.abort();
        previewAbort = new AbortController();

        const cambios = getCurrentInputsPayload();
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        try {
          const res = await fetch('/planeacion/programa-tejido/preview-fechas-balanceo', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'Accept': 'application/json',
            },
            signal: previewAbort.signal,
            body: JSON.stringify({ ord_compartida: ordCompartida, cambios })
          });

          const data = await res.json();
          if (!data?.success || !Array.isArray(data.data)) return;
          if (myVersion !== previewVersion) return; // respuesta vieja

          // Pintar fechas exactas - usar requestAnimationFrame para asegurar que el DOM esté listo
          requestAnimationFrame(() => {
            data.data.forEach(item => {
              const id = Number(item.id);
              const row = getRowById(id);
              const inp = getInputById(id);

              if (!row) {
                console.warn('No se encontró fila para id:', id);
                return;
              }
              if (!inp) {
                console.warn('No se encontró input para id:', id);
                return;
              }

              const inicioMs = parseSQLDateToMs(item.fecha_inicio);
              const finMs = parseSQLDateToMs(item.fecha_final);

              // actualizar datasets para gantt preview
              if (inicioMs) inp.dataset.fechaInicio = String(inicioMs);
              if (finMs)    inp.dataset.fechaFinalCalculada = String(finMs);

              const inicioCell = row.querySelector('.fecha-inicio-display');
              const finalCell  = row.querySelector('.fecha-final-display');

              // Actualizar fecha inicio
              if (inicioCell && item.fecha_inicio) {
                const fechaFormateada = formatearFecha(item.fecha_inicio);
                inicioCell.textContent = fechaFormateada;
              }

              // Actualizar fecha final - asegurar que se actualice correctamente
              if (finalCell) {
                if (item.fecha_final) {
                  const fechaFormateada = formatearFecha(item.fecha_final);
                  finalCell.textContent = fechaFormateada;
                  // También actualizar el atributo data si existe
                  if (finalCell.dataset) {
                    finalCell.dataset.fechaFinal = item.fecha_final;
                  }
                } else {
                  // Si no hay fecha final, limpiar la celda
                  finalCell.textContent = '-';
                }
              } else {
                console.warn('No se encontró celda fecha-final-display para id:', id);
              }
            });

            // Actualizar gantt después de actualizar las fechas
            renderBalanceoLeaderBadge(currentGanttRegistros);
            updateGanttPreview();
          });
        } catch (e) {
          if (e?.name === 'AbortError') return;
          console.error('previewFechasExactas error', e);
        }
      }

      window.schedulePreview = function schedulePreview(ordCompartida) {
        if (!isBalanceoTotalsBalanced()) {
          if (previewTimer) clearTimeout(previewTimer);
          return;
        }
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(() => previewFechasExactas(ordCompartida), 250);
      }

      // ==========================
      // Totales / saldos (SIN calcular fecha aquí)
      // ==========================
      window.calcularTotalesYFechas = function (changedInput = null, ordCompartida = null, forceRebalance = false) {
        if (adjustingPedidos) return;
        lastEditedInput = changedInput || lastEditedInput;

        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        let totalPedido = 0;
        let totalSaldo = 0;

        inputs.forEach(input => {
          if (input.value && input.value.includes('.')) {
            const r = Math.round(Number(input.value) || 0);
            input.value = r || '';
          }

          const produccion = Number(input.dataset.produccion || 0) || 0;
          const isFocused = !forceRebalance && document.activeElement === input;
          let pedido = Math.round(Number(input.value) || 0);

          if (produccion > 0 && pedido < produccion) {
            if (!isFocused) {
              pedido = produccion;
              input.value = pedido || '';
            }
          }

          const row = input.closest('tr');
          const saldoCell = row.querySelector('.saldo-display');
          const pedidoCalc = pedidoEfectivoParaCalculo(input, forceRebalance);
          const saldo = Math.max(0, pedidoCalc - produccion);

          totalPedido += pedidoCalc;
          totalSaldo += saldo;

          if (saldoCell) {
            saldoCell.textContent = saldo.toLocaleString('es-MX');
            saldoCell.className =
              'px-3 py-2 text-sm text-right saldo-display ' +
              (saldo > 0 ? 'text-green-600 font-medium' : 'text-gray-500');
          }
        });

        const skipLastRowAdjust =
          !forceRebalance &&
          document.activeElement &&
          document.activeElement.matches &&
          document.activeElement.matches('.pedido-input');

        if (!adjustingFromTotal && !skipLastRowAdjust) {
          const totalDisponible = getLockedTotalBalanceo(inputs);

          if (totalDisponible > 0) {
            const diff = totalDisponible - totalPedido;

            if (inputs.length >= 2 && Math.abs(diff) > 0.0001) {
              const target = inputs[inputs.length - 1];
              const valActualTarget = Number(target.value) || 0;
              const produccionTarget = Number(target.dataset.produccion || 0) || 0;

              adjustingPedidos = true;
              const adjusted = Math.round(Math.max(produccionTarget, valActualTarget + diff));
              target.value = adjusted || '';
              adjustingPedidos = false;

              return window.calcularTotalesYFechas(target, ordCompartida, forceRebalance);
            }
          }
        }

        const totalPedidoInput = document.getElementById('total-pedido-input');
        const totalSaldoEl = document.getElementById('total-saldo');

        if (totalPedidoInput && !adjustingPedidos && !adjustingFromTotal && document.activeElement !== totalPedidoInput) {
          totalPedidoInput.value = totalPedido;
        }
        if (totalSaldoEl) totalSaldoEl.textContent = totalSaldo.toLocaleString('es-MX');
        renderBalanceoLeaderBadge(currentGanttRegistros);
        updateBalanceoTotalVisualState();

        // schedulePreview se llama desde onblur/Enter del input, no en cada keypress
      };

      window.actualizarPedidosDesdeTotal = function (totalInput, ordCompartida) {
        if (adjustingPedidos || adjustingFromTotal) return;

        if (totalInput.value && totalInput.value.includes('.')) totalInput.value = Math.round(Number(totalInput.value) || 0);

        const nuevoTotal = Math.round(Number(totalInput.value) || 0);
        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        if (inputs.length === 0) return;

        setLockedTotalBalanceo(nuevoTotal);

        let totalActual = 0;
        inputs.forEach(input => totalActual += Number(input.value) || 0);

        const diferencia = nuevoTotal - totalActual;
        if (Math.abs(diferencia) < 0.0001) return;

        adjustingFromTotal = true;

        if (inputs.length === 1) {
          const input = inputs[0];
          const produccion = Number(input.dataset.produccion || 0) || 0;
          const nuevoValor = Math.round(Math.max(produccion, (Number(input.value) || 0) + diferencia));
          input.value = nuevoValor || '';
          window.calcularTotalesYFechas(input, ordCompartida);
        } else {
          let diferenciaRestante = diferencia;

          inputs.forEach((input, index) => {
            const valorActual = Number(input.value) || 0;
            const produccion = Number(input.dataset.produccion || 0) || 0;
            const proporcion = totalActual > 0 ? (valorActual / totalActual) : (1 / inputs.length);
            const delta = diferencia * proporcion;

            if (index === inputs.length - 1) {
              const nuevoValor = Math.round(Math.max(produccion, valorActual + diferenciaRestante));
              input.value = nuevoValor || '';
            } else {
              const nuevoValor = Math.round(Math.max(produccion, valorActual + delta));
              input.value = nuevoValor || '';
              diferenciaRestante -= (nuevoValor - valorActual); // usar el cambio real aplicado, no delta teórico
            }
          });

          window.calcularTotalesYFechas(null, ordCompartida);
        }

        adjustingFromTotal = false;
      };

      // ==========================
      // Balanceo automático con fecha fin objetivo
      // ==========================
      window.aplicarBalanceoAutomatico = async function (ordCompartida) {
        const inputs = document.querySelectorAll('.pedido-input');
        if (inputs.length < 2) return;

        const fechaInput = document.getElementById('fecha-fin-objetivo-balanceo');
        if (!fechaInput) return;

        const fechaFinObjetivo = fechaInput.value;
        if (!fechaFinObjetivo) {
          fechaInput.focus();
          return;
        }
        if (fechaInput.min && fechaFinObjetivo < fechaInput.min) {
          if (typeof toastr !== 'undefined') {
            toastr.error('La fecha objetivo no puede ser anterior al inicio más tardío del grupo (' + fechaInput.min + ').');
          }
          fechaInput.focus();
          return;
        }

        const cambiosActuales = getCurrentInputsPayload();
        const totalObjetivo = getLockedTotalBalanceo(inputs);

        const btn = document.getElementById('btn-balancear-auto');
        const btnIcon  = btn?.querySelector('.btn-balancear-icon');
        const btnLabel = btn?.querySelector('.btn-balancear-label');

        const setLoading = (loading) => {
          if (!btn) return;
          btn.disabled = loading;
          if (btnIcon)  btnIcon.className  = loading ? 'fa-solid fa-spinner fa-spin text-xs btn-balancear-icon' : 'fa-solid fa-scale-balanced text-xs btn-balancear-icon';
          if (btnLabel) btnLabel.textContent = loading ? 'Calculando...' : 'Balancear';
        };

        setLoading(true);

        try {
          const response = await fetch('/planeacion/programa-tejido/balancear-automatico', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json'
            },
            body: JSON.stringify({
              ord_compartida: ordCompartida,
              fecha_fin_objetivo: fechaFinObjetivo,
              cambios: cambiosActuales,
              total_objetivo: totalObjetivo
            })
          });

          const data = await response.json();

          if (!data.success) {
            toastr.error(data.message || 'No se pudo realizar el balanceo.');
            return;
          }

          if (data.advertencia_total) {
            toastr.warning(data.advertencia_total);
          }

          if (data.cambios && Array.isArray(data.cambios)) {
            // Bloquear recálculos intermedios para que cada input no reajuste al último
            adjustingPedidos = true;

            data.cambios.forEach(cambio => {
              const input = getInputById(cambio.id);
              if (input) {
                const produccion = Number(input.dataset.produccion || 0) || 0;
                const nuevoValor = Math.round(Math.max(produccion, cambio.total_pedido));
                input.value = nuevoValor || '';
              }
            });

            // Actualizar el total disponible para que calcularTotalesYFechas
            // no intente redistribuir fuera del total original del grupo.
            setLockedTotalBalanceo(totalObjetivo);

            adjustingPedidos = false;

            window.calcularTotalesYFechas(null, ordCompartida);
            setTimeout(() => {
              previewFechasExactas(ordCompartida);
              setTimeout(() => {
                const ganttContainer = document.getElementById('gantt-ord-container');
                if (ganttContainer) ganttContainer.scrollLeft = ganttContainer.scrollWidth;
              }, 350);
            }, 100);
          }
        } catch (error) {
          console.error('Error al balancear:', error);
        } finally {
          setLoading(false);
        }
      };

      // ==========================
      // Actualizar registros en tabla principal sin recargar
      // ==========================
      async function actualizarRegistrosBalanceo(registrosIds) {
        const tb = document.querySelector('#mainTable tbody');
        if (!tb) return;

        // Obtener columnas para formatear valores
        const columns = (typeof columnsData !== 'undefined' && columnsData && columnsData.length > 0)
          ? columnsData
          : (window.columns || Array.from(document.querySelectorAll('#mainTable thead th[data-column]')).map(th => ({
            field: th.getAttribute('data-column'),
            label: th.textContent.trim(),
            dateType: null
          })));

        if (!columns || columns.length === 0) return;

        // Función para obtener el tipo de fecha de una columna
        const getDateType = (field) => {
          const col = columns.find(c => c.field === field);
          return col?.dateType || null;
        };

        // Función para formatear valor (usar la función global si existe, sino básica)
        const formatearValor = (registro, field, value) => {
          if (typeof formatearValorCelda === 'function') {
            return formatearValorCelda(registro, field, value, getDateType(field));
          }
          // Fallback básico
          if (value === null || value === undefined || value === '') return '';
          if (getDateType(field) === 'date' || getDateType(field) === 'datetime') {
            try {
              const dt = new Date(value);
              if (dt.getFullYear() <= 1970) return '';
              return getDateType(field) === 'date'
                ? dt.toLocaleDateString('es-MX')
                : dt.toLocaleString('es-MX');
            } catch (e) {
              return String(value);
            }
          }
          if (!isNaN(value) && !Number.isInteger(parseFloat(value))) {
            return parseFloat(value).toFixed(2);
          }
          return String(value);
        };

        // Actualizar cada registro
        for (const registroId of registrosIds) {
          try {
            // Obtener datos actualizados del registro
            const response = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo?t=${Date.now()}`, {
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Cache-Control': 'no-cache'
              }
            });

            if (!response.ok) continue;

            const result = await response.json();
            if (!result.success || !result.registro) continue;

            const registro = result.registro;

            // Buscar la fila existente
            const fila = tb.querySelector(`tr.selectable-row[data-id="${registroId}"]`);
            if (!fila) continue;

            // Actualizar data attribute de OrdCompartida si existe
            if (registro.OrdCompartida) {
              fila.setAttribute('data-ord-compartida', registro.OrdCompartida);
            }

            // Actualizar celdas relevantes
            columns.forEach(col => {
              const field = col.field;
              const value = registro[field] !== undefined ? registro[field] : null;
              const celda = fila.querySelector(`td[data-column="${field}"]`);

              if (celda) {
                // Actualizar data-value
                celda.setAttribute('data-value', value !== null && value !== undefined ? String(value) : '');

                // Actualizar contenido HTML formateado
                const contenidoHTML = formatearValor(registro, field, value);
                celda.innerHTML = contenidoHTML;
              }
            });

            window.PTStore?.set(String(registroId), registro);

            // Pequeño delay para no saturar
            await new Promise(resolve => setTimeout(resolve, 50));
          } catch (error) {
            console.warn(`Error al actualizar registro ${registroId}:`, error);
          }
        }
      }

      // ==========================
      // Guardar cambios
      // ==========================
      async function guardarCambiosPedido(ordCompartida) {
        window.calcularTotalesYFechas(null, ordCompartida, true);
        if (!isBalanceoTotalsBalanced()) {
          Swal.showValidationMessage(
            'La suma de pedidos no coincide con el total del grupo. Revisa el mínimo por producción en el último telar o ajusta los demás telares.'
          );
          return false;
        }

        const inputs = document.querySelectorAll('.pedido-input');
        const cambios = [];
        inputs.forEach(input => {
          const id = input.dataset.id;
          const original = Number(input.dataset.original) || 0;
          const nuevo = Math.round(Number(input.value) || 0);

          if (original !== nuevo) {
            cambios.push({
              id,
              total_pedido: nuevo,
              modo: 'total'
            });
          }
        });

        if (cambios.length === 0) {
          Swal.showValidationMessage('No hay cambios para guardar');
          return false;
        }

        try {
          const response = await fetch('/planeacion/programa-tejido/actualizar-pedidos-balanceo', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ cambios, ord_compartida: ordCompartida })
          });

          const data = await response.json();

          if (data.success) {
            try {
              lineasCache = {};
              const registrosRefrescados = await fetchRegistrosOrdCompartida(ordCompartida);
              gruposDataCache[ordCompartida] = registrosRefrescados;
              currentGanttRegistros = registrosRefrescados;
              renderGanttOrd(registrosRefrescados);
            } catch (_) {}

            Swal.fire({
              icon: 'success',
              title: 'Guardado',
              text: data.message || 'Los cambios se guardaron correctamente',
              confirmButtonColor: '#3b82f6',
              timer: 2000,
              showConfirmButton: false
            });

            // Actualizar registros en la tabla principal sin recargar
            if (data.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
              actualizarRegistrosBalanceo(data.registros_ids);
            }

            return true;
          }

          Swal.showValidationMessage(data.message || 'Error al guardar los cambios');
          return false;
        } catch (error) {
          Swal.showValidationMessage('Error de conexión al guardar los cambios');
          return false;
        }
      }

      // ==========================
      // Expuestos
      // ==========================
      window.recargarGanttOrdCompartida = async function (ordCompartida) {
        const registros = await fetchRegistrosOrdCompartida(ordCompartida);
        currentGanttRegistros = registros;
        lineasCache = {};
        await prefetchLineas(registros);
        renderGanttOrd(registros);
      };

      window.verDetallesGrupoBalanceo = async function (ordCompartida) {
        const registros = await fetchRegistrosOrdCompartida(ordCompartida);
        currentGanttRegistros = registros;

        // Obtener valores actuales desde la tabla principal (después de balanceos previos)
        const tb = document.querySelector('#mainTable tbody');
        const valoresActuales = {};
        if (tb) {
          registros.forEach(reg => {
            const row = tb.querySelector(`tr.selectable-row[data-id="${reg.Id}"]`);
            if (row) {
              const totalPedidoCell = row.querySelector(`td[data-column="TotalPedido"]`);
              if (totalPedidoCell) {
                // Intentar obtener el valor del data-value o del texto
                const dataValue = totalPedidoCell.getAttribute('data-value');
                const textValue = totalPedidoCell.textContent?.trim();
                // Limpiar el texto de formato (quitar comas, espacios, etc.)
                const valorLimpio = dataValue || textValue?.replace(/[^\d.-]/g, '');
                const valorNumerico = parseNumber(valorLimpio);
                if (valorNumerico > 0) {
                  valoresActuales[reg.Id] = valorNumerico;
                }
              }
            }
          });
        }

        const totalPedido = registros.reduce((s, r) => {
          const valorActual = valoresActuales[r.Id] ?? Number(r.TotalPedido || 0);
          return s + valorActual;
        }, 0);
        const totalProduccion = registros.reduce((s, r) => s + (Number(r.Produccion) || 0), 0);
        const totalSaldo = registros.reduce((s, r) => {
          const valorActual = valoresActuales[r.Id] ?? Number(r.TotalPedido || 0);
          const produccion = Number(r.Produccion || 0);
          return s + Math.max(0, valorActual - produccion);
        }, 0);

        totalDisponibleBalanceo = totalPedido;

        // Determinar la orden líder del grupo
        const leaderInfo = resolveBalanceoLeader(registros, valoresActuales);
        const noTelarPrincipal = leaderInfo?.noTelarId || null;

        const filasHTML = registros.map(reg => {
          const fechaInicio = reg.FechaInicio ? (parseFechaBackendALocal(String(reg.FechaInicio).trim())?.getTime() ?? 0) : 0;
          const fechaFinal = reg.FechaFinal ? (parseFechaBackendALocal(String(reg.FechaFinal).trim())?.getTime() ?? 0) : 0;
          const duracionOriginalMs = (fechaInicio && fechaFinal) ? (fechaFinal - fechaInicio) : 0;

          // Usar el valor actual de la tabla si existe, sino el del backend
          const pedidoActual = Math.round(valoresActuales[reg.Id] ?? Number(reg.TotalPedido || 0));
          const pedidoOriginal = Math.round(Number(reg.TotalPedido || 0)); // Mantener el original para data-original
          const produccion = Math.round(Number(reg.Produccion || 0));
          const saldoActual = Math.max(0, pedidoActual - produccion);
          const stdDia = Number(reg.StdDia || 0);
          const minPedido = produccion > 0 ? produccion : 0;

          return `
            <tr class="${leaderInfo?.id === Number(reg.Id) ? 'bg-amber-100 border-b border-amber-300' : 'hover:bg-gray-50 border-b border-gray-200'}" data-registro-id="${reg.Id}">
              <td class="px-3 py-2 text-xs sm:text-sm font-medium text-gray-900 whitespace-nowrap">
                ${reg.NoTelarId || '-'}
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-gray-600 max-w-[100px] sm:max-w-none truncate" title="${(reg.NombreProducto || '-').replace(/"/g, '&quot;')}">${reg.NombreProducto || '-'}</td>
              <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">${Math.round(stdDia).toLocaleString('es-MX')}</td>
              <td class="px-3 py-2 text-right">
                <input
                  type="number"
                  class="${PEDIDO_INPUT_BASE_CLASS} border-gray-300"
                  data-id="${reg.Id}"
                  data-original="${pedidoOriginal}"
                  data-fecha-inicio="${fechaInicio}"
                  data-duracion-original="${duracionOriginalMs}"
                  data-std-dia="${stdDia}"
                  data-produccion="${produccion}"
                  value="${pedidoActual || ''}"
                  min="${minPedido}"
                  step="1"
                  oninput="calcularTotalesYFechas(this, ${ordCompartida})"
                  onblur="schedulePreview(${ordCompartida})"
                  onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                >
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">${produccion.toLocaleString('es-MX')}</td>
              <td class="px-3 py-2 text-xs sm:text-sm text-right saldo-display ${saldoActual > 0 ? 'text-green-600 font-medium' : 'text-gray-500'}"
                  data-produccion="${produccion}"
                  data-saldo-original="${saldoActual}">
                ${saldoActual.toLocaleString('es-MX')}
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-inicio-display">
                ${formatearFecha(reg.FechaInicio)}
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-final-display">
                ${formatearFecha(reg.FechaFinal)}
              </td>
            </tr>
          `;
        }).join('');

        const htmlContent = `
          <div class="balanceo-modal-content space-y-3 sm:space-y-4 text-left">
            <style>
              /* Contenedor balanceo: ancho relativo al viewport (vw ≈ % del ancho pantalla); !important gana al width inline de SweetAlert2 */
              .balanceo-modal-content { max-width: 100%; }
              .swal2-popup.balanceo-orden-modal {
                width: min(98vw, 100%) !important;
                max-width: min(98vw, 100%) !important;
                padding: 0.5rem;
                box-sizing: border-box;
              }
              @media (min-width: 640px) {
                .swal2-popup.balanceo-orden-modal {
                  width: min(96vw, 100%) !important;
                  max-width: min(96vw, 100%) !important;
                  padding: 0.75rem 1rem;
                }
              }
              @media (min-width: 1024px) {
                .swal2-popup.balanceo-orden-modal {
                  width: min(94vw, 100%) !important;
                  max-width: min(94vw, 100%) !important;
                  padding: 1rem 1.25rem;
                }
              }
              .swal2-html-container.balanceo-orden-body { overflow-x: hidden; overflow-y: auto; max-height: 88vh; padding: 0.5rem; }
              @media (min-width: 640px) { .swal2-html-container.balanceo-orden-body { padding: 0.5rem 0.75rem; } }
              @media (min-width: 1024px) { .swal2-html-container.balanceo-orden-body { max-height: 90vh; padding: 0.75rem 1rem; } }
              /* Tabla: scroll horizontal, evitar que encabezados y números se apilen */
              .balanceo-tabla-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -2px; }
              .balanceo-tabla-wrap table { min-width: 620px; }
              .balanceo-tabla-wrap table th,
              .balanceo-tabla-wrap table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
              .balanceo-tabla-wrap table th:nth-child(1), .balanceo-tabla-wrap table td:nth-child(1) { min-width: 64px; max-width: 78px; }
              .balanceo-tabla-wrap table th:nth-child(2), .balanceo-tabla-wrap table td:nth-child(2) { min-width: 110px; max-width: 200px; }
              .balanceo-tabla-wrap table th:nth-child(3), .balanceo-tabla-wrap table td:nth-child(3) { min-width: 62px; }
              .balanceo-tabla-wrap table th:nth-child(4), .balanceo-tabla-wrap table td:nth-child(4) { min-width: 70px; }
              .balanceo-tabla-wrap table th:nth-child(5), .balanceo-tabla-wrap table td:nth-child(5) { min-width: 72px; }
              .balanceo-tabla-wrap table th:nth-child(6), .balanceo-tabla-wrap table td:nth-child(6) { min-width: 62px; }
              .balanceo-tabla-wrap table th:nth-child(7), .balanceo-tabla-wrap table td:nth-child(7),
              .balanceo-tabla-wrap table th:nth-child(8), .balanceo-tabla-wrap table td:nth-child(8) { min-width: 76px; }
              @media (max-width: 639px) {
                .balanceo-tabla-wrap table th, .balanceo-tabla-wrap table td { padding: 0.35rem 0.4rem; font-size: 0.7rem; }
                .balanceo-tabla-wrap .pedido-input { width: 4rem; min-width: 4rem; padding: 0.25rem 0.35rem; font-size: 0.7rem; }
                .balanceo-tabla-wrap #total-pedido-input { width: 4rem; min-width: 4rem; padding: 0.25rem 0.35rem; font-size: 0.7rem; }
              }
              /* Gantt: contenedor con scroll */
              #gantt-ord-container { max-height: 75vh; overflow: auto; -webkit-overflow-scrolling: touch; min-height: 150px; }
              .gantt-grid {
                display: grid;
                grid-auto-rows: minmax(36px, auto);
                width: max-content;
                min-width: 100%;
                column-gap: 0;
                row-gap: 0;
              }
              @media (max-width: 639px) { .gantt-grid { grid-auto-rows: minmax(32px, auto); } }
              /* min-width: 0 evita que el contenido ensanche la cuadrícula; fechas respetan el ancho fijado en template */
              .gantt-cell {
                border: 1px solid #e5e7eb;
                padding: 4px 4px;
                font-size: 10px;
                line-height: 1.2;
                text-align: center;
                min-width: 0;
                box-sizing: border-box;
              }
              @media (max-width: 639px) { .gantt-cell { padding: 3px 2px; font-size: 9px; } }
              .gantt-header { background: #f9fafb; font-weight: 600; color: #374151; position: sticky; top: 0; z-index: 10; }
              .gantt-label {
                font-weight: 600;
                background: #f3f4f6;
                text-align: left;
                position: sticky;
                left: 0;
                z-index: 21;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                width: 100%;
                max-width: none;
                min-width: 0;
                box-shadow: 1px 0 0 #e5e7eb;
              }
              @media (max-width: 639px) { .gantt-label { font-size: 9px; } }
              .gantt-header.gantt-label.gantt-corner { z-index: 30; }
              .gantt-bar { background: #f3f4f6; color: #4b5563; font-weight: 600; }
              .gantt-bar-alt { background: #e5e7eb; color: #374151; font-weight: 600; }
              /* Por Std/Día prorrateado: ámbar = aún hay espacio respecto al std; verde = cerca del tope */
              .gantt-bar-space { background: #fef3c7; color: #b45309; font-weight: 600; }
              .gantt-bar-at-cap { background: #ecfdf3; color: #166534; font-weight: 600; }
            </style>

            <div class="flex flex-col sm:flex-row sm:justify-end gap-2 items-stretch sm:items-end">
              <div class="flex items-center gap-2 rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 border border-amber-200 w-full sm:w-auto sm:mr-auto">
                <span>No telar principal es <strong id="balanceo-no-telar-principal">${noTelarPrincipal || '-'}</strong></span>
              </div>
              <div class="flex flex-col sm:flex-row gap-2 items-center w-full sm:w-auto">
                <label class="text-xs text-gray-700 whitespace-nowrap">Fecha Objetivo:</label>
                <input
                  type="date"
                  id="fecha-fin-objetivo-balanceo"
                  class="px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                <button type="button"
                  id="btn-balancear-auto"
                  onclick="aplicarBalanceoAutomatico(${ordCompartida})"
                  class="inline-flex items-center justify-center gap-1 rounded-md bg-blue-500 px-4 py-1 text-md font-medium text-white shadow-sm hover:bg-blue-600 disabled:opacity-60 disabled:cursor-not-allowed">
                  <i class="fa-solid fa-scale-balanced text-xs btn-balancear-icon"></i>
                  <span class="btn-balancear-label">Balancear</span>
                </button>
              </div>


            </div>

            <div class="flex flex-col lg:flex-row gap-4 items-stretch">
              <div class="w-full lg:w-5/12 min-w-0 flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="balanceo-tabla-wrap overflow-x-auto">
                  <table class="min-w-full">
                    <thead class="bg-blue-500 text-white">
                      <tr>
                        <th class="px-3 py-2 text-left text-[11px] sm:text-xs">Telar</th>
                        <th class="px-3 py-2 text-left text-[11px] sm:text-xs">Producto</th>
                        <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Std/Día</th>
                        <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Pedido</th>
                        <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Producción</th>
                        <th class="px-3 py-2 text-right text-[11px] sm:text-xs">Saldo</th>
                        <th class="px-3 py-2 text-center text-[11px] sm:text-xs">F.Inicio</th>
                        <th class="px-3 py-2 text-center text-[11px] sm:text-xs">F.Final</th>
                      </tr>
                    </thead>
                    <tbody>${filasHTML}</tbody>
                    <tfoot class="bg-gray-100">
                      <tr>
                        <td colspan="3" class="px-3 py-2 text-xs sm:text-sm font-semibold text-gray-700 text-right">Totales:</td>
                        <td class="px-3 py-2 text-right">
                          <input type="number"
                            id="total-pedido-input"
                            class="${TOTAL_PEDIDO_INPUT_BASE_CLASS} border-gray-300"
                            value="${Math.round(totalPedido)}"
                            oninput="actualizarPedidosDesdeTotal(this, ${ordCompartida})">
                        </td>
                        <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-gray-900">${Math.round(totalProduccion).toLocaleString('es-MX')}</td>
                        <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-green-600" id="total-saldo">${Math.round(totalSaldo).toLocaleString('es-MX')}</td>
                        <td colspan="2"></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <p id="balanceo-total-mensaje" class="hidden px-1 pt-1 text-xs text-red-600" role="status"></p>
                <div id="total-disponible" class="hidden">${Math.round(totalPedido)}</div>
              </div>

              <div class="w-full lg:flex-1 min-w-0 flex flex-col rounded-lg border border-gray-200 bg-white p-2">
                <div id="gantt-ord-container" class="relative min-h-[150px] overflow-auto">
                  <div id="gantt-loading" class="p-3 text-sm text-gray-500">Cargando líneas...</div>
                  <div id="gantt-ord"></div>
                </div>
              </div>
            </div>
          </div>
        `;

        Swal.fire({
          title: 'Balanceo de orden',
          html: htmlContent,
          customClass: { popup: 'balanceo-orden-modal', htmlContainer: 'balanceo-orden-body' },
          showCloseButton: true,
          showConfirmButton: true,
          confirmButtonText: '<i class="fa-solid fa-save mr-2"></i> Guardar',
          confirmButtonColor: '#3b82f6',
          showCancelButton: true,
          cancelButtonText: 'Cancelar',
          cancelButtonColor: '#6b7280',
          preConfirm: () => guardarCambiosPedido(ordCompartida),
          didOpen: () => {
            const swalBody = document.querySelector('.swal2-html-container');
            if (swalBody) {
              swalBody.addEventListener(
                'blur',
                e => {
                  if (!e.target?.classList?.contains('pedido-input')) return;
                  const ord = ordCompartida;
                  window.setTimeout(() => {
                    if (!document.querySelector('.swal2-popup')) return;
                    window.calcularTotalesYFechas(null, ord, true);
                    window.schedulePreview(ord);
                  }, 0);
                },
                true
              );
            }

            renderGanttOrd(registros);
            updateBalanceoTotalVisualState();
            //  preview exacto inmediato (para asegurar dataset y preview correcto)
            previewFechasExactas(ordCompartida);

            // Establecer min (inicio más tardío) y fecha sugerida en el input de fecha objetivo
            const fechaInput = document.getElementById('fecha-fin-objetivo-balanceo');
            if (fechaInput && registros.length > 0) {
              let fechaInicioMax = null;
              registros.forEach(reg => {
                if (reg.FechaInicio) {
                  try {
                    const fecha = parseFechaBackendALocal(String(reg.FechaInicio).trim());
                    if (fecha && !isNaN(fecha.getTime()) && fecha.getFullYear() > 1970) {
                      if (!fechaInicioMax || fecha > fechaInicioMax) {
                        fechaInicioMax = fecha;
                      }
                    }
                  } catch (e) {
                    console.warn('Error al parsear FechaInicio:', e);
                  }
                }
              });
              if (fechaInicioMax) {
                fechaInput.min = toDateInputValueLocal(fechaInicioMax);
              } else {
                fechaInput.removeAttribute('min');
              }

              // Obtener la fecha final más lejana de los registros (sugerencia)
              let fechaMaxima = null;
              registros.forEach(reg => {
                if (reg.FechaFinal) {
                  try {
                    const fecha = parseFechaBackendALocal(String(reg.FechaFinal).trim());
                    if (fecha && !isNaN(fecha.getTime()) && fecha.getFullYear() > 1970) {
                      if (!fechaMaxima || fecha > fechaMaxima) {
                        fechaMaxima = fecha;
                      }
                    }
                  } catch (e) {
                    console.warn('Error al parsear fecha:', e);
                  }
                }
              });

              if (fechaMaxima) {
                fechaInput.value = toDateInputValueLocal(fechaMaxima);
              } else if (fechaInput.min) {
                fechaInput.value = fechaInput.min;
              }
              if (fechaInput.min && fechaInput.value && fechaInput.value < fechaInput.min) {
                fechaInput.value = fechaInput.min;
              }
            }

            requestAnimationFrame(() => syncBalanceoGuardarButtonState());
          }
        });
      };

      // ==========================
      // Control del botón Balancear según selección
      // ==========================
      function updateBalancearButton(rowElement) {
        const btn = document.getElementById('btnBalancear');
        if (!btn) return;

        if (!rowElement) {
          // Sin selección: botón deshabilitado y gris
          btn.disabled = true;
          btn.classList.remove('bg-green-500', 'hover:bg-green-600');
          btn.classList.add('bg-gray-400', 'hover:bg-gray-500');
          btn.title = 'Balancear (selecciona un registro con orden compartida)';
          return;
        }

        const ordCompartida = rowElement.getAttribute('data-ord-compartida');
        const tieneOrdCompartida = ordCompartida &&
                                   ordCompartida.trim() !== '' &&
                                   ordCompartida !== '0' &&
                                   ordCompartida.toLowerCase() !== 'null';

        if (tieneOrdCompartida) {
          // Tiene OrdCompartida: botón habilitado y verde
          btn.disabled = false;
          btn.classList.remove('bg-gray-400', 'hover:bg-gray-500');
          btn.classList.add('bg-green-500', 'hover:bg-green-600');
          btn.title = `Balancear orden compartida: ${ordCompartida.trim()}`;
        } else {
          // No tiene OrdCompartida: botón deshabilitado y gris
          btn.disabled = true;
          btn.classList.remove('bg-green-500', 'hover:bg-green-600');
          btn.classList.add('bg-gray-400', 'hover:bg-gray-500');
          btn.title = 'Balancear (este registro no tiene orden compartida)';
        }
      }

      // Listener para cambios de selección
      document.addEventListener('pt:selection-changed', function(e) {
        const { rowElement } = e.detail || {};
        updateBalancearButton(rowElement);
      });

      // Función para abrir balanceo desde la fila seleccionada
      window.abrirBalancearDesdeSeleccion = function() {
        const rows = Array.from(document.querySelectorAll('.selectable-row'));
        const selectedRow = rows.find(r => r.classList.contains('bg-blue-700') || r.classList.contains('bg-blue-400'));

        if (!selectedRow) {
          Swal.fire({
            icon: 'warning',
            title: 'Sin selección',
            text: 'Selecciona un registro con orden compartida para balancear.'
          });
          return;
        }

        const ordCompartida = selectedRow.getAttribute('data-ord-compartida');
        if (!ordCompartida || ordCompartida.trim() === '' || ordCompartida === '0' || ordCompartida.toLowerCase() === 'null') {
          Swal.fire({
            icon: 'info',
            title: 'Sin orden compartida',
            text: 'El registro seleccionado no tiene orden compartida para balancear.'
          });
          return;
        }

        // Abrir modal de balanceo para este grupo
        if (typeof window.verDetallesGrupoBalanceo === 'function') {
          window.verDetallesGrupoBalanceo(parseInt(ordCompartida.trim()));
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'La función de balanceo no está disponible.'
          });
        }
      };

      // Inicializar estado del botón al cargar
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => updateBalancearButton(null));
      } else {
        updateBalancearButton(null);
      }

    })();
    </script>
