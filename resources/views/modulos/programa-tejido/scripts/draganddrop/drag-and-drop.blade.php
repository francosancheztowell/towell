<script>
    /* ============================================================
     * DRAG & DROP - RÁPIDO + ESTABLE (120-200 filas)
     * FIX CRÍTICO:
     *   - Drop es async, dragend limpia globals => bug.
     *   - Solución: snapshot "ctx" en drop y NO usar globals después de await.
     * Extras:
     *   - restoreOriginalOrder usa Set (O(n))
     *   - TelarScan usa indexMap (idx O(1))
     * ============================================================ */

    let DD_LOG_ENABLED = false;
    window.setDDLog = (v) => { DD_LOG_ENABLED = !!v; console.log('[DragDrop] logs=', DD_LOG_ENABLED); };

    function ddLog(step, payload) {
      if (!DD_LOG_ENABLED) return;
      payload !== undefined ? console.log('[DragDrop]', step, payload) : console.log('[DragDrop]', step);
    }

    window.dragDropMode = window.dragDropMode ?? false;
    window.allRows = window.allRows ?? [];

    let rowCache = new Map();
    function clearRowCache() { rowCache = new Map(); }

    function tbodyEl() { return document.querySelector('#mainTable tbody'); }

    function getRowMeta(row) {
      if (!row) return null;
      if (!rowCache.has(row)) {
        const telarCell = row.querySelector('[data-column="NoTelarId"]');
        const salonCell = row.querySelector('[data-column="SalonTejidoId"]');
        const cambioHiloCell = row.querySelector('[data-column="CambioHilo"]');
        const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
        const checkbox = enProcesoCell ? enProcesoCell.querySelector('input[type="checkbox"]') : null;

        rowCache.set(row, {
          id: row.getAttribute('data-id') || '',
          telar: telarCell ? telarCell.textContent.trim() : null,
          salon: salonCell ? salonCell.textContent.trim() : null,
          cambioHilo: cambioHiloCell ? cambioHiloCell.textContent.trim() : null,
          enProceso: !!(checkbox && checkbox.checked),
        });
      }
      return rowCache.get(row);
    }

    function getRowTelar(row){ const m = getRowMeta(row); return m ? m.telar : null; }
    function getRowSalon(row){ const m = getRowMeta(row); return m ? m.salon : null; }
    function getRowCambioHilo(row){ const m = getRowMeta(row); return m ? m.cambioHilo : null; }
    function isRowEnProceso(row){ const m = getRowMeta(row); return !!(m && m.enProceso); }

    function normalizeTelarValue(value) {
      if (value === undefined || value === null) return '';
      const str = String(value).trim();
      if (!str) return '';
      const n = Number(str);
      if (!Number.isNaN(n)) return n.toString();
      return str.toUpperCase();
    }
    function isSameTelar(a,b){ return normalizeTelarValue(a) === normalizeTelarValue(b); }

    // -------------------------
    // Estado D&D
    // -------------------------
    let draggedRow = null;
    let draggedRowTelar = null;
    let draggedRowSalon = null;
    let draggedRowCambioHilo = null;

    let originalOrderIds = [];
    let draggedRowOriginalTelarIndex = null;
    let dragBlockedReason = null;
    let dragDropPerformed = false;

    let ddLastOver = { row:null, rawTelar:null, decisionTelar:null, salon:null, isBefore:false, y:null };
    let ddLastSig = null;

    // scan cache por telar destino (para EnProceso)
    let ddTelarScan = { telarNorm:null, rows:null, indexMap:null, lastEnProceso:-1 };
    function resetTelarScan(){ ddTelarScan = { telarNorm:null, rows:null, indexMap:null, lastEnProceso:-1 }; }

    function buildTelarScanFromDOM(decisionTelar) {
      const tb = tbodyEl();
      if (!tb) return { rows: [], indexMap: new Map(), lastEnProceso: -1 };

      const norm = normalizeTelarValue(decisionTelar);
      if (ddTelarScan.telarNorm === norm && ddTelarScan.rows && ddTelarScan.indexMap) return ddTelarScan;

      const rows = [];
      const indexMap = new Map();
      let lastEnProceso = -1;

      for (const r of tb.children) {
        if (!r.classList || !r.classList.contains('selectable-row')) continue;
        if (r === draggedRow) continue;
        if (!isSameTelar(getRowTelar(r), decisionTelar)) continue;

        if (isRowEnProceso(r)) lastEnProceso = rows.length;
        indexMap.set(r, rows.length);
        rows.push(r);
      }

      ddTelarScan = { telarNorm: norm, rows, indexMap, lastEnProceso };
      return ddTelarScan;
    }

    // -------------------------
    // Restaurar orden original
    // -------------------------
    function restoreOriginalOrder() {
      try {
        if (!originalOrderIds.length) return;
        const tb = tbodyEl();
        if (!tb) return;

        const idSet = new Set(originalOrderIds);

        const rowMap = new Map();
        for (const r of tb.querySelectorAll('.selectable-row')) {
          rowMap.set(r.getAttribute('data-id') || '', r);
        }

        const frag = document.createDocumentFragment();
        for (const id of originalOrderIds) {
          const r = rowMap.get(id);
          if (r) frag.appendChild(r);
        }
        rowMap.forEach((r, id) => { if (!idSet.has(id)) frag.appendChild(r); });

        tb.innerHTML = '';
        tb.appendChild(frag);

        window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));
        clearRowCache();
        ddLog('restoreOriginalOrder', { total: window.allRows.length });
      } finally {
        originalOrderIds = [];
      }
    }

    // -------------------------
    // Inserción visual (O(1))
    // -------------------------
    function placeDraggedRowRelative(targetRow, isBefore) {
      const tb = tbodyEl();
      if (!tb || !draggedRow || !targetRow || targetRow === draggedRow) return;

      if (isBefore) {
        if (draggedRow.nextElementSibling === targetRow) return;
        tb.insertBefore(draggedRow, targetRow);
      } else {
        const next = targetRow.nextElementSibling;
        if (next === draggedRow) return;
        tb.insertBefore(draggedRow, next);
      }
    }

    // FIN del telar actual usando scan cache (sin loop reverse)
    function placeDraggedRowEndOfTelar(telarValue) {
      const tb = tbodyEl();
      if (!tb || !draggedRow) return;

      const scan = buildTelarScanFromDOM(telarValue);
      const last = scan.rows && scan.rows.length ? scan.rows[scan.rows.length - 1] : null;

      if (!last) return; // no hay otro registro del telar
      tb.insertBefore(draggedRow, last.nextElementSibling);
    }

    // -------------------------
    // Scheduler rAF
    // -------------------------
    let rafPending = false;
    let rafMode = null;
    let rafTargetRow = null;
    let rafIsBefore = false;
    let rafTelar = null;

    function runPlacementNow() {
      if (!draggedRow) return;
      if (rafMode === 'relative') placeDraggedRowRelative(rafTargetRow, rafIsBefore);
      else if (rafMode === 'endTelar') placeDraggedRowEndOfTelar(rafTelar);
      else if (rafMode === 'append') {
        const tb = tbodyEl();
        if (tb) tb.appendChild(draggedRow);
      }
    }
    function flushPlacement() {
      if (!rafPending) return;
      rafPending = false;
      runPlacementNow();
    }
    function schedulePlaceRelative(targetRow, isBefore) {
      rafMode = 'relative';
      rafTargetRow = targetRow;
      rafIsBefore = isBefore;
      if (rafPending) return;
      rafPending = true;
      requestAnimationFrame(() => { rafPending = false; runPlacementNow(); });
    }
    function schedulePlaceEndTelar(telarValue) {
      rafMode = 'endTelar';
      rafTelar = telarValue;
      if (rafPending) return;
      rafPending = true;
      requestAnimationFrame(() => { rafPending = false; runPlacementNow(); });
    }
    function scheduleAppendEnd() {
      rafMode = 'append';
      if (rafPending) return;
      rafPending = true;
      requestAnimationFrame(() => { rafPending = false; runPlacementNow(); });
    }

    // -------------------------
    // Toggle modo D&D
    // -------------------------
    function toggleDragDropMode() {
      window.dragDropMode = !window.dragDropMode;

      const btn = document.getElementById('btnDragDrop');
      const tb = tbodyEl();
      if (!btn || !tb) return;

      window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      clearRowCache();

      if (window.dragDropMode) {
        if (typeof deselectRow === 'function') deselectRow();

        btn.classList.remove('bg-black','hover:bg-gray-800','focus:ring-gray-500');
        btn.classList.add('bg-gray-400','hover:bg-gray-500','ring-2','ring-gray-300');
        btn.title = 'Desactivar arrastrar filas';

        window.allRows.forEach((row) => {
          const meta = getRowMeta(row);
          const enProceso = !!meta.enProceso;

          row.draggable = !enProceso;
          row.onclick = null;

          row.removeEventListener('dragstart', handleDragStart);
          row.removeEventListener('dragend', handleDragEnd);

          if (!enProceso) {
            row.classList.add('cursor-move');
            row.classList.remove('cursor-not-allowed');
            row.style.opacity = '';
            row.addEventListener('dragstart', handleDragStart);
            row.addEventListener('dragend', handleDragEnd);
          } else {
            row.classList.add('cursor-not-allowed');
            row.classList.remove('cursor-move');
            row.style.opacity = '0.6';
          }
        });

        tb.removeEventListener('dragover', handleDragOver);
        tb.removeEventListener('drop', handleDrop);
        tb.addEventListener('dragover', handleDragOver);
        tb.addEventListener('drop', handleDrop);

        if (typeof showToast === 'function') showToast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
        return;
      }

      // OFF
      btn.classList.remove('bg-gray-400','hover:bg-gray-500','ring-2','ring-gray-300');
      btn.classList.add('bg-black','hover:bg-gray-800','focus:ring-gray-500');
      btn.title = 'Activar/Desactivar arrastrar filas';

      window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      clearRowCache();

      window.allRows.forEach((row, i) => {
        row.draggable = false;
        row.classList.remove('cursor-move','cursor-not-allowed');
        row.style.opacity = '';
        row.removeEventListener('dragstart', handleDragStart);
        row.removeEventListener('dragend', handleDragEnd);
        row.onclick = () => (typeof selectRow === 'function' ? selectRow(row, i) : null);
      });

      tb.removeEventListener('dragover', handleDragOver);
      tb.removeEventListener('drop', handleDrop);

      if (typeof showToast === 'function') showToast('Modo arrastrar desactivado', 'info');
    }
    window.toggleDragDropMode = toggleDragDropMode;

    // -------------------------
    // DragStart
    // -------------------------
    function handleDragStart(e) {
      if (isRowEnProceso(this)) {
        e.preventDefault();
        if (typeof showToast === 'function') showToast('No se puede mover un registro en proceso', 'error');
        return false;
      }

      draggedRow = this;
      draggedRowTelar = getRowTelar(this);
      draggedRowSalon = getRowSalon(this);
      draggedRowCambioHilo = getRowCambioHilo(this);

      dragBlockedReason = null;
      dragDropPerformed = false;
      ddLastSig = null;
      resetTelarScan();

      const tb = tbodyEl();
      if (tb) {
        const snapshot = Array.from(tb.querySelectorAll('.selectable-row'));
        originalOrderIds = snapshot.map(r => r.getAttribute('data-id') || '');

        let idx = -1, count = 0;
        for (const r of snapshot) {
          if (!isSameTelar(getRowTelar(r), draggedRowTelar)) continue;
          if (r === draggedRow) { idx = count; break; }
          count++;
        }
        draggedRowOriginalTelarIndex = idx;
      }

      ddLastOver = { row:null, rawTelar:null, decisionTelar:null, salon:null, isBefore:false, y:null };

      if (typeof deselectRow === 'function') deselectRow();

      this.classList.add('dragging');
      this.style.opacity = '0.4';

      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', getRowMeta(draggedRow).id);

      ddLog('dragStart', {
        id: getRowMeta(draggedRow).id,
        telar: draggedRowTelar,
        salon: draggedRowSalon,
        originalIndexTelar: draggedRowOriginalTelarIndex
      });
    }

    // -------------------------
    // DragOver (delegado en TBODY)
    // -------------------------
    function handleDragOver(e) {
      e.preventDefault();
      e.stopPropagation();

      dragBlockedReason = null;
      if (!draggedRow) return false;

      const targetRow = e.target?.closest?.('.selectable-row') || null;
      if (targetRow === draggedRow) return false;

      if (!targetRow) {
        ddLastOver = { row:null, rawTelar:null, decisionTelar: draggedRowTelar, salon: draggedRowSalon, isBefore:false, y:e.clientY };
        scheduleAppendEnd();
        return false;
      }

      const rect = targetRow.getBoundingClientRect();
      const isBefore = e.clientY < (rect.top + rect.height / 2);

      const rawTargetTelar = getRowTelar(targetRow);
      const rawTargetSalon = getRowSalon(targetRow);

      const borderSnapToEnd = (!isSameTelar(rawTargetTelar, draggedRowTelar) && isBefore && !e.altKey);

      let decisionTelar = rawTargetTelar;
      if (borderSnapToEnd) decisionTelar = draggedRowTelar;

      const sig = `${getRowMeta(targetRow).id}|${isBefore ? 1 : 0}|${normalizeTelarValue(decisionTelar)}|${e.altKey ? 1 : 0}|${borderSnapToEnd ? 1 : 0}`;
      if (sig === ddLastSig) return false;
      ddLastSig = sig;

      if (ddLastOver.row && ddLastOver.row !== targetRow) {
        ddLastOver.row.classList.remove('drag-over','drag-over-warning','drop-not-allowed');
      }

      ddLastOver = { row: targetRow, rawTelar: rawTargetTelar, decisionTelar, salon: rawTargetSalon, isBefore, y: e.clientY };

      // validación EnProceso solo si cambia de telar
      if (!isSameTelar(decisionTelar, draggedRowTelar)) {
        const scan = buildTelarScanFromDOM(decisionTelar);
        const idx = scan.indexMap?.get(targetRow);
        const posObjetivo = (typeof idx === 'number') ? (isBefore ? idx : idx + 1) : (scan.rows?.length || 0);

        if (scan.lastEnProceso !== -1 && posObjetivo <= scan.lastEnProceso) {
          dragBlockedReason = 'No se puede colocar antes de un registro en proceso en el telar destino.';
          e.dataTransfer.dropEffect = 'none';
          targetRow.classList.add('drop-not-allowed');
          targetRow.classList.remove('drag-over','drag-over-warning');
          return false;
        }
      }

      if (isSameTelar(decisionTelar, draggedRowTelar)) {
        e.dataTransfer.dropEffect = 'move';
        targetRow.classList.add('drag-over');
        targetRow.classList.remove('drag-over-warning','drop-not-allowed');
      } else {
        e.dataTransfer.dropEffect = 'copy';
        targetRow.classList.add('drag-over-warning');
        targetRow.classList.remove('drag-over','drop-not-allowed');
      }

      if (!targetRow.classList.contains('drop-not-allowed')) {
        if (borderSnapToEnd) schedulePlaceEndTelar(draggedRowTelar);
        else schedulePlaceRelative(targetRow, isBefore);
      }

      return false;
    }

    // -------------------------
    // Posición destino por DOM (DROP)
    // -------------------------
    function calcularPosicionObjetivoDOM(targetTelar, targetRowElement, isBefore, draggedEl) {
      const tb = tbodyEl();
      if (!tb) return 0;

      let count = 0;

      if (!targetRowElement) {
        for (const r of tb.children) {
          if (!r.classList || !r.classList.contains('selectable-row')) continue;
          if (r === draggedEl) continue;
          if (isSameTelar(getRowTelar(r), targetTelar)) count++;
        }
        return count;
      }

      for (const r of tb.children) {
        if (!r.classList || !r.classList.contains('selectable-row')) continue;
        if (r === draggedEl) continue;

        if (isSameTelar(getRowTelar(r), targetTelar)) {
          if (r === targetRowElement) return isBefore ? count : count + 1;
          count++;
        }
      }
      return count;
    }

    // -------------------------
    // Drop (delegado en TBODY)
    // -------------------------
    async function handleDrop(e) {
      e.stopPropagation();
      e.preventDefault();

      flushPlacement();
      dragDropPerformed = true;

      if (!draggedRow) {
        if (typeof showToast === 'function') showToast('Error: No se encontró el registro arrastrado', 'error');
        restoreOriginalOrder();
        return false;
      }

      // SNAPSHOT CRÍTICO (para que dragend no te rompa)
      const ctx = {
        draggedRowEl: draggedRow,
        draggedId: (e.dataTransfer?.getData?.('text/plain')) || getRowMeta(draggedRow).id,
        draggedTelar: draggedRowTelar,
        draggedSalon: draggedRowSalon,
        originalIndexTelar: draggedRowOriginalTelarIndex
      };

      if (!ctx.draggedId) {
        if (typeof showToast === 'function') showToast('Error: No se pudo obtener el ID del registro', 'error');
        restoreOriginalOrder();
        return false;
      }

      if (dragBlockedReason) {
        if (typeof showToast === 'function') showToast(dragBlockedReason, 'error');
        restoreOriginalOrder();
        return false;
      }

      // EXTRAER TODO ANTES DE AWAIT
      const targetTelar = ddLastOver.decisionTelar || ctx.draggedTelar;
      const targetSalon = ddLastOver.salon || ctx.draggedSalon;
      const overRow = ddLastOver.row;
      const isBefore = !!ddLastOver.isBefore;

      const esMismoTelar = isSameTelar(ctx.draggedTelar, targetTelar);
      if (esMismoTelar) {
        await procesarMovimientoMismoTelar(ctx);
        return false;
      }

      let targetPosition = calcularPosicionObjetivoDOM(targetTelar, overRow, isBefore, ctx.draggedRowEl);

      // Ajuste EnProceso destino
      const scan = buildTelarScanFromDOM(targetTelar);
      const minAllowed = (scan.lastEnProceso !== -1) ? (scan.lastEnProceso + 1) : 0;
      targetPosition = Math.max(minAllowed, targetPosition);
      targetPosition = Math.max(0, Math.min(targetPosition, (scan.rows?.length || 0)));

      await procesarMovimientoOtroTelar(ctx, targetSalon, targetTelar, targetPosition);
      return false;
    }

    // -------------------------
    // MISMO TELAR (usa ctx)
    // -------------------------
    async function procesarMovimientoMismoTelar(ctx) {
      const tb = tbodyEl();
      if (!tb) { restoreOriginalOrder(); return; }

      const rowsSameTelar = [];
      for (const r of tb.children) {
        if (!r.classList || !r.classList.contains('selectable-row')) continue;
        if (isSameTelar(getRowTelar(r), ctx.draggedTelar)) rowsSameTelar.push(r);
      }

      if (rowsSameTelar.length < 2) {
        if (typeof showToast === 'function') showToast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
        restoreOriginalOrder();
        return;
      }

      const nuevaPosicion = rowsSameTelar.indexOf(ctx.draggedRowEl);
      const posicionOriginal = (typeof ctx.originalIndexTelar === 'number' && ctx.originalIndexTelar >= 0) ? ctx.originalIndexTelar : null;

      if (posicionOriginal !== null && posicionOriginal === nuevaPosicion) {
        if (typeof showToast === 'function') showToast('El registro ya está en esa posición', 'info');
        restoreOriginalOrder();
        return;
      }

      if (typeof showLoading === 'function') showLoading();
      try {
        const response = await fetch(`/planeacion/programa-tejido/${ctx.draggedId}/prioridad/mover`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ new_position: nuevaPosicion })
        });

        const data = await response.json();
        if (typeof hideLoading === 'function') hideLoading();

        if (data.success) {
          originalOrderIds = [];
          if (typeof updateTableAfterDragDrop === 'function') updateTableAfterDragDrop(data.detalles, ctx.draggedId);
          if (typeof showToast === 'function') showToast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');
          window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));
          clearRowCache();
        } else {
          if (typeof showToast === 'function') showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
          restoreOriginalOrder();
        }
      } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        if (typeof showToast === 'function') showToast('Ocurrió un error al procesar la solicitud', 'error');
        restoreOriginalOrder();
      }
    }

    // -------------------------
    // OTRO TELAR (usa ctx)
    // -------------------------
    async function procesarMovimientoOtroTelar(ctx, nuevoSalon, nuevoTelar, targetPosition) {
      if (typeof showLoading === 'function') showLoading();

      try {
        const verifRes = await fetch(`/planeacion/programa-tejido/${ctx.draggedId}/verificar-cambio-telar`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ nuevo_salon: nuevoSalon, nuevo_telar: nuevoTelar })
        });

        if (!verifRes.ok) {
          if (typeof hideLoading === 'function') hideLoading();
          if (typeof showToast === 'function') showToast('No se pudo validar el cambio de telar', 'error');
          restoreOriginalOrder();
          return;
        }

        const verificacion = await verifRes.json();
        if (typeof hideLoading === 'function') hideLoading();

        if (!verificacion.puede_mover) {
          await Swal.fire({
            icon: 'error',
            title: 'No se puede cambiar de telar',
            html: `<div class="text-left"><p class="mb-2">${verificacion.mensaje || 'Bloqueado'}</p></div>`,
            confirmButtonColor: '#dc2626'
          });
          restoreOriginalOrder();
          return;
        }

        const cambiosHTML =
          (verificacion.cambios && Array.isArray(verificacion.cambios) && verificacion.cambios.length)
            ? `
              <div class="mt-3 max-h-80 overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-100 sticky top-0">
                    <tr>
                      <th class="px-3 py-2 text-left font-semibold text-gray-700">Campo</th>
                      <th class="px-3 py-2 text-left font-semibold text-gray-700">Actual</th>
                      <th class="px-3 py-2 text-left font-semibold text-gray-700">Nuevo</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    ${verificacion.cambios.map(c => `
                      <tr>
                        <td class="px-3 py-2 font-medium text-gray-900">${c.campo || ''}</td>
                        <td class="px-3 py-2 text-gray-600">${c.actual ?? ''}</td>
                        <td class="px-3 py-2 text-blue-700 font-semibold">${c.nuevo ?? ''}</td>
                      </tr>
                    `).join('')}
                  </tbody>
                </table>
              </div>
            `
            : `<div class="mt-3 text-sm text-gray-600">Se moverá el registro al telar <b>${nuevoTelar}</b> (Salón <b>${nuevoSalon}</b>).</div>`;

        const res = await Swal.fire({
          icon: 'warning',
          title: 'Cambio de Telar/Salón',
          html: `
            <div class="text-left">
              <div class="text-sm text-gray-700">
                <div><b>Origen:</b> Telar ${ctx.draggedTelar} (Salón ${ctx.draggedSalon})</div>
                <div><b>Destino:</b> Telar ${nuevoTelar} (Salón ${nuevoSalon})</div>
                <div class="mt-2"><b>Posición destino:</b> ${targetPosition}</div>
              </div>
              ${cambiosHTML}
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Sí, cambiar de telar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#3b82f6',
          cancelButtonColor: '#6b7280',
          allowOutsideClick: false
        });

        if (!res.isConfirmed) {
          if (typeof showToast === 'function') showToast('Operación cancelada', 'info');
          restoreOriginalOrder();
          return;
        }

        if (typeof showLoading === 'function') showLoading();
        const cambioRes = await fetch(`/planeacion/programa-tejido/${ctx.draggedId}/cambiar-telar`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ nuevo_salon: nuevoSalon, nuevo_telar: nuevoTelar, target_position: targetPosition })
        });

        const cambio = await cambioRes.json();
        if (typeof hideLoading === 'function') hideLoading();

        if (!cambio.success) {
          if (typeof showToast === 'function') showToast(cambio.message || 'No se pudo cambiar de telar', 'error');
          restoreOriginalOrder();
          return;
        }

        sessionStorage.setItem('priorityChangeMessage', cambio.message || 'Telar actualizado correctamente');
        sessionStorage.setItem('priorityChangeType', 'success');
        if (cambio.registro_id) {
          sessionStorage.setItem('scrollToRegistroId', cambio.registro_id);
          sessionStorage.setItem('selectRegistroId', cambio.registro_id);
        }
        window.location.href = '/planeacion/programa-tejido';

      } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        if (typeof showToast === 'function') showToast('Error al procesar el cambio de telar', 'error');
        restoreOriginalOrder();
      }
    }

    // -------------------------
    // DragEnd
    // -------------------------
    function handleDragEnd() {
      this.classList.remove('dragging');
      this.style.opacity = '';

      if (ddLastOver.row) ddLastOver.row.classList.remove('drag-over','drag-over-warning','drop-not-allowed');

      // limpiar estado (ya es seguro porque drop usa ctx snapshot)
      draggedRow = null;
      draggedRowTelar = null;
      draggedRowSalon = null;
      draggedRowCambioHilo = null;

      draggedRowOriginalTelarIndex = null;
      ddLastOver = { row:null, rawTelar:null, decisionTelar:null, salon:null, isBefore:false, y:null };
      ddLastSig = null;
      resetTelarScan();

      rafPending = false;
      rafMode = null;
      rafTargetRow = null;
      rafTelar = null;

      if (!dragDropPerformed && dragBlockedReason) {
        if (typeof showToast === 'function') showToast(dragBlockedReason, 'error');
        restoreOriginalOrder();
      }

      dragBlockedReason = null;
      dragDropPerformed = false;

      ddLog('dragEnd');
    }
    </script>
