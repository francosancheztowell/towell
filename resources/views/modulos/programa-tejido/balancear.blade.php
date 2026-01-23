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
      function formatearFecha(fecha) {
        if (!fecha) return '-';
        try {
          const d = new Date(fecha);
          if (d.getFullYear() <= 1970 || isNaN(d.getTime())) return '-';
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
        // sql: "YYYY-MM-DD HH:mm:ss" -> ISO "YYYY-MM-DDTHH:mm:ss"
        if (!sql) return 0;
        const iso = String(sql).trim().replace(' ', 'T');
        const d = new Date(iso);
        return isNaN(d.getTime()) ? 0 : d.getTime();
      }

      function sortRegistrosPorFechaTelar(registros) {
        return [...registros].sort((a, b) => {
          const aMs = parseSQLDateToMs(a?.FechaInicio);
          const bMs = parseSQLDateToMs(b?.FechaInicio);
          if (aMs !== bMs) return aMs - bMs;

          const aTelarNum = Number(a?.NoTelarId);
          const bTelarNum = Number(b?.NoTelarId);
          const aTelarNumOk = Number.isFinite(aTelarNum);
          const bTelarNumOk = Number.isFinite(bTelarNum);

          if (aTelarNumOk && bTelarNumOk && aTelarNum !== bTelarNum) {
            return aTelarNum - bTelarNum;
          }

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
        const d = new Date(str);
        return isNaN(d.getTime()) ? null : d;
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

      function mapWithScaledTimeline(reg, _lineasIgnorado, inputsMap) {
        const datos = inputsMap[String(reg.Id)] || {};

        const pedidoNuevo = datos.pedido ?? Number(reg.TotalPedido || 0);
        const produccion = Number(reg.Produccion || 0);
        const saldoNuevo = Math.max(0, pedidoNuevo - produccion);
        if (saldoNuevo <= 0) return { map: {}, min: null, max: null };

        const fechaInicioMs =
          datos.fechaInicioMs ||
          (reg.FechaInicio ? new Date(String(reg.FechaInicio).replace(' ', 'T')).getTime() : 0);

        const fechaFinalCalcMs = datos.fechaFinalCalcMs || 0;

        // si ya hay preview exacto del backend, úsalo
        const fechaFinDestinoMs = fechaFinalCalcMs ||
          (reg.FechaFinal ? new Date(String(reg.FechaFinal).replace(' ', 'T')).getTime() : 0);

        if (!fechaInicioMs || !fechaFinDestinoMs || fechaFinDestinoMs <= fechaInicioMs) {
          const inicioDate = fechaInicioMs ? new Date(fechaInicioMs) : null;
          const key = inicioDate ? getDateKeyLocal(inicioDate) : 'N/A';
          const minNormalized = inicioDate ? normalizeToLocalMidnight(inicioDate).getTime() : null;
          const maxNormalized = fechaFinDestinoMs ? normalizeToLocalMidnight(new Date(fechaFinDestinoMs)).getTime() : null;
          return { map: { [key]: saldoNuevo }, min: minNormalized, max: maxNormalized };
        }

        const inicio = new Date(fechaInicioMs);
        const fin = new Date(fechaFinDestinoMs);

        const totalSegundos = Math.abs(fin.getTime() - inicio.getTime()) / 1000;
        const totalHoras = totalSegundos / 3600.0;
        if (totalHoras <= 0) {
          const key = getDateKeyLocal(inicio);
          const minNormalized = normalizeToLocalMidnight(inicio).getTime();
          const maxNormalized = normalizeToLocalMidnight(fin).getTime();
          return { map: { [key]: saldoNuevo }, min: minNormalized, max: maxNormalized };
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
        return { map, min: minNormalized, max: maxNormalized };
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
          const neededHeight = 42 + rows.length * 40 + 32;
          const maxHeight = Math.round(window.innerHeight * 0.7);
          wrapper.style.height = `${Math.min(neededHeight, maxHeight)}px`;
        }

        const template = `200px repeat(${dates.length}, 70px)`;
        let html = `<div class="gantt-grid" style="grid-template-columns:${template}">`;

        html += `<div class="gantt-cell gantt-header gantt-label"></div>`;
        dates.forEach(d => html += `<div class="gantt-cell gantt-header">${formatShort(d)}</div>`);

        rows.forEach((row, idx) => {
          html += `<div class="gantt-cell gantt-label">${row.label}</div>`;
          dates.forEach(d => {
            const key = getDateKeyLocal(d);
            const qty = row.map[key] || 0;
            const hasQty = qty && qty !== 0;
            const cls = hasQty ? (idx % 2 === 0 ? 'gantt-bar' : 'gantt-bar-alt') : '';
            const displayQty = hasQty ? qty.toLocaleString('es-MX', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) : '';
            html += `<div class="gantt-cell ${cls}">${displayQty}</div>`;
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
            let localMin = parseDateISO(String(reg.FechaInicio || '').replace(' ', 'T'));
            let localMax = parseDateISO(String(reg.FechaFinal || '').replace(' ', 'T'));

            if (Array.isArray(lineas) && lineas.length) {
              lineas.forEach(l => {
                const d = parseDateISO(String(l.Fecha || '').replace(' ', 'T'));
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
            rows.push({ label, map });
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
            map
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
                const fechaFormateada = formatearFecha(item.fecha_inicio.replace(' ', 'T'));
                inicioCell.textContent = fechaFormateada;
                inicioCell.innerText = fechaFormateada; // Forzar actualización
              }

              // Actualizar fecha final - asegurar que se actualice correctamente
              if (finalCell) {
                if (item.fecha_final) {
                  const fechaFormateada = formatearFecha(item.fecha_final.replace(' ', 'T'));
                  finalCell.textContent = fechaFormateada;
                  finalCell.innerText = fechaFormateada; // Forzar actualización
                  // También actualizar el atributo data si existe
                  if (finalCell.dataset) {
                    finalCell.dataset.fechaFinal = item.fecha_final;
                  }
                } else {
                  // Si no hay fecha final, limpiar la celda
                  finalCell.textContent = '-';
                  finalCell.innerText = '-';
                }
              } else {
                console.warn('No se encontró celda fecha-final-display para id:', id);
              }
            });

            // Actualizar gantt después de actualizar las fechas
            updateGanttPreview();
          });
        } catch (e) {
          if (e?.name === 'AbortError') return;
          console.error('previewFechasExactas error', e);
        }
      }

      function schedulePreview(ordCompartida) {
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(() => previewFechasExactas(ordCompartida), 250);
      }

      // ==========================
      // Totales / saldos (SIN calcular fecha aquí)
      // ==========================
      window.calcularTotalesYFechas = function (changedInput = null, ordCompartida = null) {
        if (adjustingPedidos) return;
        lastEditedInput = changedInput || lastEditedInput;

        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        let totalPedido = 0;
        let totalSaldo = 0;

        inputs.forEach(input => {
          if (input.value && input.value.includes('.')) input.value = Math.round(Number(input.value) || 0);

          const produccion = Number(input.dataset.produccion || 0) || 0;
          let pedido = Math.round(Number(input.value) || 0);

          // Validar que el pedido nunca sea menor que la producción
          if (produccion > 0 && pedido < produccion) {
            pedido = produccion;
            input.value = pedido;
          }

          const row = input.closest('tr');
          const saldoCell = row.querySelector('.saldo-display');

          const saldo = Math.max(0, pedido - produccion);

          totalPedido += pedido;
          totalSaldo += saldo;

          saldoCell.textContent = saldo.toLocaleString('es-MX');
          saldoCell.className =
            'px-3 py-2 text-sm text-right saldo-display ' +
            (saldo > 0 ? 'text-green-600 font-medium' : 'text-gray-500');
        });

        // Ajuste automático para mantener suma de pedidos
        if (!adjustingFromTotal) {
          const totalDisponibleEl = document.getElementById('total-disponible');
          const totalDisponible = totalDisponibleEl
            ? parseNumber(totalDisponibleEl.textContent)
            : (totalDisponibleBalanceo ?? inputs.reduce((s, i) => s + (Number(i.dataset.original) || 0), 0));

          if (totalDisponible > 0) {
            const diff = totalDisponible - totalPedido;

            // Cuando hay exactamente 2 registros: ajustar el otro registro
            if (inputs.length === 2 && Math.abs(diff) > 0.0001) {
              const targets = inputs.filter(inp => inp !== lastEditedInput);
              const target = targets[0] || inputs[0];
              const valActual = Number(target.value) || 0;
              const produccion = Number(target.dataset.produccion || 0) || 0;

              adjustingPedidos = true;
              target.value = Math.round(Math.max(produccion, valActual + diff));
              adjustingPedidos = false;

              return window.calcularTotalesYFechas(target, ordCompartida);
            }

            // Cuando hay 3 o más registros: ajustar el primero (por orden actual).
            // Si se modifica el primero, entonces ajustar el último.
            if (inputs.length >= 3 && Math.abs(diff) > 0.0001 && lastEditedInput) {
              const primeroInput = inputs[0];
              const ultimoInput = inputs[inputs.length - 1];
              const target = (lastEditedInput === primeroInput) ? ultimoInput : primeroInput;
              const valActualTarget = Number(target.value) || 0;
              const produccion = Number(target.dataset.produccion || 0) || 0;

              adjustingPedidos = true;
              target.value = Math.round(Math.max(produccion, valActualTarget + diff));
              adjustingPedidos = false;

              return window.calcularTotalesYFechas(target, ordCompartida);
            }
          }
        }

        const totalPedidoInput = document.getElementById('total-pedido-input');
        const totalSaldoEl = document.getElementById('total-saldo');

        if (totalPedidoInput && !adjustingPedidos && !adjustingFromTotal && document.activeElement !== totalPedidoInput) {
          totalPedidoInput.value = totalPedido;
        }
        if (totalSaldoEl) totalSaldoEl.textContent = totalSaldo.toLocaleString('es-MX');

        const totalDisponibleEl = document.getElementById('total-disponible');
        if (totalDisponibleEl) {
          totalDisponibleEl.textContent = totalPedido;
          if (totalDisponibleBalanceo !== null) totalDisponibleBalanceo = totalPedido;
        }

        if (ordCompartida != null) schedulePreview(ordCompartida);
      };

      window.actualizarPedidosDesdeTotal = function (totalInput, ordCompartida) {
        if (adjustingPedidos || adjustingFromTotal) return;

        if (totalInput.value && totalInput.value.includes('.')) totalInput.value = Math.round(Number(totalInput.value) || 0);

        const nuevoTotal = Math.round(Number(totalInput.value) || 0);
        const inputs = Array.from(document.querySelectorAll('.pedido-input'));
        if (inputs.length === 0) return;

        let totalActual = 0;
        inputs.forEach(input => totalActual += Number(input.value) || 0);

        const diferencia = nuevoTotal - totalActual;
        if (Math.abs(diferencia) < 0.0001) return;

        adjustingFromTotal = true;

        if (inputs.length === 1) {
          const input = inputs[0];
          const produccion = Number(input.dataset.produccion || 0) || 0;
          const nuevoValor = Math.round(Math.max(produccion, (Number(input.value) || 0) + diferencia));
          input.value = nuevoValor;
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
              input.value = nuevoValor;
            } else {
              const nuevoValor = Math.round(Math.max(produccion, valorActual + delta));
              input.value = nuevoValor;
              diferenciaRestante -= delta;
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
        if (inputs.length < 2) {
                    return; // Silenciosamente, no hacer nada si hay menos de 2 registros
        }

        // Obtener el input de fecha objetivo del modal
        const fechaInput = document.getElementById('fecha-fin-objetivo-balanceo');
        if (!fechaInput) {
                    return; // Silenciosamente, no hacer nada si no existe el input
        }

        const fechaFinObjetivo = fechaInput.value;
        if (!fechaFinObjetivo) {
                    fechaInput.focus();
          return;
        }

        try {
                    // Llamar al endpoint de balanceo automático
          const response = await fetch('/planeacion/programa-tejido/balancear-automatico', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json'
            },
            body: JSON.stringify({
              ord_compartida: ordCompartida,
              fecha_fin_objetivo: fechaFinObjetivo
            })
          });

          const data = await response.json();

          if (!data.success) {
                        return;
          }

                    // Aplicar los cambios calculados a los inputs (silenciosamente, como si fuera cambio manual)
          if (data.cambios && Array.isArray(data.cambios)) {
            // Aplicar cambios a los inputs
            data.cambios.forEach(cambio => {
              const input = getInputById(cambio.id);
              if (input) {
                const produccion = Number(input.dataset.produccion || 0) || 0;
                const nuevoValor = Math.round(Math.max(produccion, cambio.total_pedido));
                const valorAnterior = Number(input.value) || 0;

                // Solo actualizar si hay cambio
                if (Math.abs(nuevoValor - valorAnterior) > 0.01) {
                                    input.value = nuevoValor;

                  // NO actualizar dataset.original aquí - debe mantenerse el valor original
                  // para que al guardar se detecten los cambios del balanceo
                  // input.dataset.original solo se actualiza al cargar o después de guardar

                  // Disparar evento input para que se actualicen totales y fechas
                  // Esto funciona igual que cuando el usuario cambia el input manualmente
                  input.dispatchEvent(new Event('input', { bubbles: true }));
                }
              }
            });

            // Recalcular totales y fechas inmediatamente (esto actualizará el gantt automáticamente)
            // El schedulePreview dentro de calcularTotalesYFechas actualizará las fechas finales
            window.calcularTotalesYFechas(null, ordCompartida);

            // Forzar actualización inmediata del preview (sin esperar el debounce)
            setTimeout(() => {
                            previewFechasExactas(ordCompartida);
            }, 100);
          }
        } catch (error) {
          console.error('Error al balancear:', error);
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

        const filasHTML = registros.map(reg => {
          const fechaInicio = reg.FechaInicio ? new Date(String(reg.FechaInicio).replace(' ', 'T')).getTime() : 0;
          const fechaFinal = reg.FechaFinal ? new Date(String(reg.FechaFinal).replace(' ', 'T')).getTime() : 0;
          const duracionOriginalMs = (fechaInicio && fechaFinal) ? (fechaFinal - fechaInicio) : 0;

          // Usar el valor actual de la tabla si existe, sino el del backend
          const pedidoActual = valoresActuales[reg.Id] ?? Number(reg.TotalPedido || 0);
          const pedidoOriginal = Number(reg.TotalPedido || 0); // Mantener el original para data-original
          const produccion = Number(reg.Produccion || 0);
          const saldoActual = Math.max(0, pedidoActual - produccion);
          const stdDia = Number(reg.StdDia || 0);
          const minPedido = produccion > 0 ? produccion : 0;

          return `
            <tr class="hover:bg-gray-50 border-b border-gray-200" data-registro-id="${reg.Id}">
              <td class="px-3 py-2 text-xs sm:text-sm font-medium text-gray-900">${reg.NoTelarId || '-'}</td>
              <td class="px-3 py-2 text-xs sm:text-sm text-gray-600">${reg.NombreProducto || '-'}</td>
              <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">${Math.round(stdDia).toLocaleString('es-MX')}</td>
              <td class="px-3 py-2 text-right">
                <input
                  type="number"
                  class="pedido-input w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  data-id="${reg.Id}"
                  data-original="${pedidoOriginal}"
                  data-fecha-inicio="${fechaInicio}"
                  data-duracion-original="${duracionOriginalMs}"
                  data-std-dia="${stdDia}"
                  data-produccion="${produccion}"
                  value="${pedidoActual}"
                  min="${minPedido}"
                  step="1"
                  oninput="calcularTotalesYFechas(this, ${ordCompartida})"
                >
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-right text-gray-600">${produccion.toLocaleString('es-MX')}</td>
              <td class="px-3 py-2 text-xs sm:text-sm text-right saldo-display ${saldoActual > 0 ? 'text-green-600 font-medium' : 'text-gray-500'}"
                  data-produccion="${produccion}"
                  data-saldo-original="${saldoActual}">
                ${saldoActual.toLocaleString('es-MX')}
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-inicio-display">
                ${formatearFecha(String(reg.FechaInicio || '').replace(' ', 'T'))}
              </td>
              <td class="px-3 py-2 text-xs sm:text-sm text-center text-gray-600 fecha-final-display">
                ${formatearFecha(String(reg.FechaFinal || '').replace(' ', 'T'))}
              </td>
            </tr>
          `;
        }).join('');

        const htmlContent = `
          <div class="space-y-4 text-left">
            <style>
              #gantt-ord-container { max-height: 75vh; }
              .gantt-grid { display: grid; grid-auto-rows: minmax(40px, auto); width: max-content; }
              .gantt-cell { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 11px; line-height: 1.25; text-align: center; min-width: 80px; box-sizing: border-box; }
              .gantt-header { background: #f9fafb; font-weight: 600; color: #374151; position: sticky; top: 0; z-index: 10; }
              .gantt-label { font-weight: 600; background: #f3f4f6; text-align: left; position: sticky; left: 0; z-index: 20; white-space: nowrap; }
              .gantt-bar { background: #fef2f2; color: #b91c1c; font-weight: 600; }
              .gantt-bar-alt { background: #ecfdf3; color: #166534; font-weight: 600; }
            </style>

            <div class="flex flex-col sm:flex-row sm:justify-end gap-2 items-end">
              <div class="flex flex-col sm:flex-row gap-2 items-center w-full sm:w-auto">
                <label class="text-xs text-gray-700 whitespace-nowrap">Fecha Objetivo:</label>
                <input
                  type="date"
                  id="fecha-fin-objetivo-balanceo"
                  class="px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                <button type="button"
                  onclick="aplicarBalanceoAutomatico(${ordCompartida})"
                  class="inline-flex items-center justify-center gap-1 rounded-md bg-green-500 px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-green-600">
                  <i class="fa-solid fa-scale-balanced text-xs"></i>
                  <span>Balancear</span>
                </button>
              </div>


            </div>

            <div class="flex flex-col lg:flex-row gap-4 items-stretch">
              <div class="w-full lg:w-5/12 min-w-0 flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="overflow-x-auto">
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
                            class="w-20 sm:w-24 px-2 py-1 text-xs sm:text-sm text-right font-bold text-gray-900 border border-gray-300 rounded"
                            value="${totalPedido}"
                            oninput="actualizarPedidosDesdeTotal(this, ${ordCompartida})">
                        </td>
                        <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-gray-900">${totalProduccion.toLocaleString('es-MX')}</td>
                        <td class="px-3 py-2 text-xs sm:text-sm text-right font-bold text-green-600" id="total-saldo">${totalSaldo.toLocaleString('es-MX')}</td>
                        <td colspan="2"></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <div id="total-disponible" class="hidden">${totalPedido}</div>
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
          width: '100%',
          showCloseButton: true,
          showConfirmButton: true,
          confirmButtonText: '<i class="fa-solid fa-save mr-2"></i> Guardar',
          confirmButtonColor: '#3b82f6',
          showCancelButton: true,
          cancelButtonText: 'Cancelar',
          cancelButtonColor: '#6b7280',
          preConfirm: () => guardarCambiosPedido(ordCompartida),
          didOpen: () => {
            renderGanttOrd(registros);
            //  preview exacto inmediato (para asegurar dataset y preview correcto)
            previewFechasExactas(ordCompartida);

            // Establecer fecha sugerida en el input de fecha objetivo
            const fechaInput = document.getElementById('fecha-fin-objetivo-balanceo');
            if (fechaInput && registros.length > 0) {
              // Obtener la fecha final más lejana de los registros
              let fechaMaxima = null;
              registros.forEach(reg => {
                if (reg.FechaFinal) {
                  try {
                    const fecha = new Date(String(reg.FechaFinal).replace(' ', 'T'));
                    if (!isNaN(fecha.getTime()) && fecha.getFullYear() > 1970) {
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
                const fechaFormateada = fechaMaxima.toISOString().split('T')[0];
                fechaInput.value = fechaFormateada;
                // No establecer min para permitir seleccionar cualquier fecha
              }
            }
          }
        });
      };

    })();
    </script>
