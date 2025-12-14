<script>
    /* ============================================================
     * DRAG & DROP (ÚNICO) - ESTABLE
     * - Sin duplicados
     * - getDragAfterElement desde DOM (NO allRows)
     * - FIX BORDE TELAR:
     *     si estás en mitad superior de OTRO telar => se interpreta como “final del telar actual”
     *     para cambio real: soltar en mitad inferior o mantener ALT
     * - Drop usa el telar decidido en dragOver (ddLastOver)
     * ============================================================ */

    const DD_LOG_ENABLED = true;
    function ddLog(step, payload) {
      if (!DD_LOG_ENABLED) return;
      payload !== undefined ? console.log('[DragDrop]', step, payload) : console.log('[DragDrop]', step);
    }

    // --- Asegurar globals esperados ---
    window.dragDropMode = window.dragDropMode ?? false;
    window.allRows = window.allRows ?? [];

    // Cache: usa Map normal para compatibilidad
    let rowCache = new Map();
    function clearRowCache() { rowCache = new Map(); }

    // Helpers DOM
    function tbodyEl() { return document.querySelector('#mainTable tbody'); }
    function $$(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

    // Helpers Telar/Salon/CambioHilo
    function getRowTelar(row) {
      if (!row) return null;
      if (!rowCache.has(row)) {
        const telarCell = row.querySelector('[data-column="NoTelarId"]');
        const salonCell = row.querySelector('[data-column="SalonTejidoId"]');
        const cambioHiloCell = row.querySelector('[data-column="CambioHilo"]');
        rowCache.set(row, {
          telar: telarCell ? telarCell.textContent.trim() : null,
          salon: salonCell ? salonCell.textContent.trim() : null,
          cambioHilo: cambioHiloCell ? cambioHiloCell.textContent.trim() : null
        });
      }
      return rowCache.get(row).telar;
    }
    function getRowSalon(row) { if (!row) return null; if (!rowCache.has(row)) getRowTelar(row); return rowCache.get(row).salon; }
    function getRowCambioHilo(row){ if (!row) return null; if (!rowCache.has(row)) getRowTelar(row); return rowCache.get(row).cambioHilo; }

    // Comparación telar robusta
    function normalizeTelarValue(value) {
      if (value === undefined || value === null) return '';
      const str = String(value).trim();
      if (!str) return '';
      const n = Number(str);
      if (!Number.isNaN(n)) return n.toString();
      return str.toUpperCase();
    }
    function isSameTelar(a,b){ return normalizeTelarValue(a) === normalizeTelarValue(b); }

    // EnProceso
    function isRowEnProceso(row) {
      const enProcesoCell = row?.querySelector?.('[data-column="EnProceso"]');
      if (!enProcesoCell) return false;
      const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
      return !!(checkbox && checkbox.checked);
    }

    // Estado D&D
    let draggedRow = null;
    let draggedRowTelar = null;
    let draggedRowSalon = null;
    let draggedRowCambioHilo = null;

    let originalOrderIds = [];
    let draggedRowOriginalTelarIndex = null;
    let dragBlockedReason = null;
    let dragDropPerformed = false;
    let lastDragOverTime = 0;

    // “Último over” (clave para no brincar de telar)
    let ddLastOver = { row:null, rawTelar:null, decisionTelar:null, salon:null, isBefore:false, y:null };

    // Restaurar orden original
    function restoreOriginalOrder() {
      try {
        if (!originalOrderIds.length) return;
        const tb = tbodyEl();
        if (!tb) return;

        const rowMap = new Map();
        $$('.selectable-row', tb).forEach(r => rowMap.set(r.getAttribute('data-id')||'', r));

        const frag = document.createDocumentFragment();
        originalOrderIds.forEach(id => { const r = rowMap.get(id); if (r) frag.appendChild(r); });
        rowMap.forEach((r,id)=>{ if (!originalOrderIds.includes(id)) frag.appendChild(r); });

        tb.innerHTML = '';
        tb.appendChild(frag);

        window.allRows = $$('.selectable-row', tb);
        clearRowCache();
        ddLog('restoreOriginalOrder', { total: window.allRows.length });
      } finally {
        originalOrderIds = [];
      }
    }

    // afterElement SIEMPRE del DOM actual
    function getDragAfterElement(container, y) {
      const draggable = Array.from(container.querySelectorAll('.selectable-row:not(.dragging)'));
      if (!draggable.length) return null;

      let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
      for (const child of draggable) {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) closest = { offset, element: child };
      }
      return closest.element;
    }

    // Toggle (export global)
    function toggleDragDropMode() {
      window.dragDropMode = !window.dragDropMode;

      const btn = document.getElementById('btnDragDrop');
      const tb = tbodyEl();
      if (!btn || !tb) return;

      // snapshot de filas (siempre)
      window.allRows = $$('.selectable-row', tb);
      clearRowCache();

      if (window.dragDropMode) {
        if (typeof deselectRow === 'function') deselectRow();

        btn.classList.remove('bg-black','hover:bg-gray-800','focus:ring-gray-500');
        btn.classList.add('bg-gray-400','hover:bg-gray-500','ring-2','ring-gray-300');
        btn.title = 'Desactivar arrastrar filas';

        window.allRows.forEach(row => {
          const enProceso = isRowEnProceso(row);
          row.draggable = !enProceso;
          row.onclick = null;

          // important: evitar duplicados
          row.removeEventListener('dragstart', handleDragStart);
          row.removeEventListener('dragover', handleDragOver);
          row.removeEventListener('drop', handleDrop);
          row.removeEventListener('dragend', handleDragEnd);

          if (!enProceso) {
            row.classList.add('cursor-move');
            row.classList.remove('cursor-not-allowed');
            row.style.opacity = '';

            row.addEventListener('dragstart', handleDragStart);
            row.addEventListener('dragover', handleDragOver);
            row.addEventListener('drop', handleDrop);
            row.addEventListener('dragend', handleDragEnd);
          } else {
            row.classList.add('cursor-not-allowed');
            row.classList.remove('cursor-move');
            row.style.opacity = '0.6';
          }
        });

        // drop en huecos
        tb.addEventListener('dragover', handleDragOver);
        tb.addEventListener('drop', handleDrop);

        if (typeof showToast === 'function') showToast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
        return;
      }

      // OFF
      btn.classList.remove('bg-gray-400','hover:bg-gray-500','ring-2','ring-gray-300');
      btn.classList.add('bg-black','hover:bg-gray-800','focus:ring-gray-500');
      btn.title = 'Activar/Desactivar arrastrar filas';

      window.allRows = $$('.selectable-row', tb);
      clearRowCache();

      window.allRows.forEach((row, i) => {
        row.draggable = false;
        row.classList.remove('cursor-move','cursor-not-allowed');
        row.style.opacity = '';

        row.removeEventListener('dragstart', handleDragStart);
        row.removeEventListener('dragover', handleDragOver);
        row.removeEventListener('drop', handleDrop);
        row.removeEventListener('dragend', handleDragEnd);

        row.onclick = () => (typeof selectRow === 'function' ? selectRow(row, i) : null);
      });

      tb.removeEventListener('dragover', handleDragOver);
      tb.removeEventListener('drop', handleDrop);

      if (typeof showToast === 'function') showToast('Modo arrastrar desactivado', 'info');
    }
    window.toggleDragDropMode = toggleDragDropMode;

    // DragStart
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
      lastDragOverTime = 0;

      const tb = tbodyEl();
      if (tb) {
        const snapshot = $$('.selectable-row', tb);
        originalOrderIds = snapshot.map(r => r.getAttribute('data-id') || '');
        const sameTelar = snapshot.filter(r => isSameTelar(getRowTelar(r), draggedRowTelar));
        draggedRowOriginalTelarIndex = sameTelar.indexOf(draggedRow);
      }

      ddLastOver = { row:null, rawTelar:null, decisionTelar:null, salon:null, isBefore:false, y:null };

      if (typeof deselectRow === 'function') deselectRow();

      this.classList.add('dragging');
      this.style.opacity = '0.4';

      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', draggedRow.getAttribute('data-id') || '');

      ddLog('dragStart', {
        id: draggedRow.getAttribute('data-id'),
        telar: draggedRowTelar,
        salon: draggedRowSalon,
        originalIndexTelar: draggedRowOriginalTelarIndex
      });
    }

    // DragOver (FIX borde telar)
    function handleDragOver(e) {
      e.preventDefault();
      e.stopPropagation();

      dragBlockedReason = null;

      let targetRow = this;

      // si es TBODY, encontrar fila más cercana al mouse
      if (this.tagName === 'TBODY') {
        const rows = $$('.selectable-row', this);
        let closest = null, best = Infinity;
        for (const r of rows) {
          if (r === draggedRow) continue;
          const rect = r.getBoundingClientRect();
          const dist = Math.abs(e.clientY - (rect.top + rect.height/2));
          if (dist < best) { best = dist; closest = r; }
        }
        if (!closest) return false;
        targetRow = closest;
      }

      if (!draggedRow || targetRow === draggedRow) return false;

      // throttle
      const now = performance.now();
      if (now - lastDragOverTime < 16) return false;
      lastDragOverTime = now;

      // limpiar clases de target previo
      if (ddLastOver.row && ddLastOver.row !== targetRow) {
        ddLastOver.row.classList.remove('drag-over','drag-over-warning','drop-not-allowed');
      }

      const rawTargetTelar = getRowTelar(targetRow);
      const rawTargetSalon = getRowSalon(targetRow);

      const rect = targetRow.getBoundingClientRect();
      const isBefore = e.clientY < (rect.top + rect.height/2);

      // FIX: si estás en mitad superior de OTRO telar y NO presionas ALT -> se interpreta como final del telar actual
      let decisionTelar = rawTargetTelar;
      if (!isSameTelar(rawTargetTelar, draggedRowTelar) && isBefore && !e.altKey) {
        decisionTelar = draggedRowTelar;
      }

      ddLastOver = { row: targetRow, rawTelar: rawTargetTelar, decisionTelar, salon: rawTargetSalon, isBefore, y: e.clientY };

      ddLog('dragOver', {
        targetId: targetRow.getAttribute('data-id'),
        rawTargetTelar,
        decisionTelar,
        draggedTelar: draggedRowTelar,
        isBefore,
        altKey: !!e.altKey
      });

      // validación EnProceso SOLO si realmente cambia de telar
      if (!isSameTelar(decisionTelar, draggedRowTelar)) {
        const tb = tbodyEl();
        if (tb) {
          const allDOM = $$('.selectable-row', tb);
          const targetTelarRows = allDOM.filter(r => r !== draggedRow && isSameTelar(getRowTelar(r), decisionTelar));

          const idx = targetTelarRows.indexOf(targetRow);
          const posObjetivo = idx !== -1 ? (isBefore ? idx : idx + 1) : targetTelarRows.length;

          let ultimoEnProcesoIndex = -1;
          for (let i=0;i<targetTelarRows.length;i++) if (isRowEnProceso(targetTelarRows[i])) ultimoEnProcesoIndex = i;

          if (ultimoEnProcesoIndex !== -1 && posObjetivo <= ultimoEnProcesoIndex) {
            dragBlockedReason = 'No se puede colocar antes de un registro en proceso en el telar destino.';
            e.dataTransfer.dropEffect = 'none';
            targetRow.classList.add('drop-not-allowed');
            targetRow.classList.remove('drag-over','drag-over-warning');
            return false;
          }
        }
      }

      // feedback visual según decisionTelar
      if (isSameTelar(decisionTelar, draggedRowTelar)) {
        e.dataTransfer.dropEffect = 'move';
        targetRow.classList.add('drag-over');
        targetRow.classList.remove('drag-over-warning','drop-not-allowed');
      } else {
        e.dataTransfer.dropEffect = 'copy';
        targetRow.classList.add('drag-over-warning');
        targetRow.classList.remove('drag-over','drop-not-allowed');
      }

      // reordenamiento visual DOM
      if (!targetRow.classList.contains('drop-not-allowed')) {
        const tb = tbodyEl();
        if (tb) {
          const after = getDragAfterElement(tb, e.clientY);
          if (after == null) tb.appendChild(draggedRow);
          else tb.insertBefore(draggedRow, after);
        }
      }

      return false;
    }

    // Posición destino (cambio telar)
    function calcularPosicionObjetivo(targetTelar, targetRowElement) {
      const tb = tbodyEl();
      if (!tb) return 0;

      const allDOM = $$('.selectable-row', tb);
      const targetRows = allDOM.filter(r => r !== draggedRow && isSameTelar(getRowTelar(r), targetTelar));

      if (!targetRowElement) return targetRows.length;

      const targetIndex = allDOM.indexOf(targetRowElement);
      if (targetIndex === -1) return targetRows.length;

      let count = 0;
      for (let i=0;i<targetIndex;i++) {
        const r = allDOM[i];
        if (r !== draggedRow && isSameTelar(getRowTelar(r), targetTelar)) count++;
      }
      return ddLastOver.isBefore ? count : count + 1;
    }

    // Drop
    async function handleDrop(e) {
      e.stopPropagation();
      e.preventDefault();

      dragDropPerformed = true;

      if (!draggedRow) {
        if (typeof showToast === 'function') showToast('Error: No se encontró el registro arrastrado', 'error');
        restoreOriginalOrder();
        return false;
      }

      const registroId = (e.dataTransfer?.getData?.('text/plain')) || draggedRow.getAttribute('data-id');
      if (!registroId) {
        if (typeof showToast === 'function') showToast('Error: No se pudo obtener el ID del registro', 'error');
        restoreOriginalOrder();
        return false;
      }

      if (dragBlockedReason) {
        if (typeof showToast === 'function') showToast(dragBlockedReason, 'error');
        restoreOriginalOrder();
        return false;
      }

      const targetTelar = ddLastOver.decisionTelar || draggedRowTelar;
      const targetSalon = ddLastOver.salon || draggedRowSalon;

      ddLog('DROP', { registroId, draggedRowTelar, rawOverTelar: ddLastOver.rawTelar, decisionTelar: ddLastOver.decisionTelar, isBefore: ddLastOver.isBefore });

      const esMismoTelar = isSameTelar(draggedRowTelar, targetTelar);
      if (esMismoTelar) {
        await procesarMovimientoMismoTelar(registroId);
        return false;
      }

      let targetPosition = calcularPosicionObjetivo(targetTelar, ddLastOver.row);

      // Ajuste EnProceso destino
      const tb = tbodyEl();
      const allDOM = tb ? $$('.selectable-row', tb) : [];
      const targetRowsDOM = allDOM.filter(r => r !== draggedRow && isSameTelar(getRowTelar(r), targetTelar));

      let minAllowed = 0;
      for (let i=0;i<targetRowsDOM.length;i++) if (isRowEnProceso(targetRowsDOM[i])) minAllowed = i + 1;

      targetPosition = Math.max(minAllowed, targetPosition);
      targetPosition = Math.max(0, Math.min(targetPosition, targetRowsDOM.length));

      await procesarMovimientoOtroTelar(registroId, targetSalon, targetTelar, targetPosition);
      return false;
    }

    // MISMO TELAR
    async function procesarMovimientoMismoTelar(registroId) {
      const tb = tbodyEl();
      if (!tb) { restoreOriginalOrder(); return; }

      const allDOM = $$('.selectable-row', tb);
      const rowsSameTelar = allDOM.filter(r => isSameTelar(getRowTelar(r), draggedRowTelar));

      if (rowsSameTelar.length < 2) {
        if (typeof showToast === 'function') showToast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
        restoreOriginalOrder();
        return;
      }

      const nuevaPosicion = rowsSameTelar.indexOf(draggedRow);
      const posicionOriginal = (typeof draggedRowOriginalTelarIndex === 'number' && draggedRowOriginalTelarIndex >= 0) ? draggedRowOriginalTelarIndex : null;

      ddLog('MismoTelar posiciones', { registroId, telar: draggedRowTelar, posicionOriginal, nuevaPosicion, total: rowsSameTelar.length });

      if (posicionOriginal !== null && posicionOriginal === nuevaPosicion) {
        if (typeof showToast === 'function') showToast('El registro ya está en esa posición', 'info');
        restoreOriginalOrder();
        return;
      }

      if (typeof showLoading === 'function') showLoading();
      try {
        const response = await fetch(`/planeacion/programa-tejido/${registroId}/prioridad/mover`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ new_position: nuevaPosicion })
        });

        const data = await response.json();
        if (typeof hideLoading === 'function') hideLoading();

        ddLog('Backend mismo telar', data);

        if (data.success) {
          originalOrderIds = []; // confirmar
          if (typeof updateTableAfterDragDrop === 'function') updateTableAfterDragDrop(data.detalles, registroId);
          if (typeof showToast === 'function') showToast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

          // refrescar allRows/cache
          window.allRows = $$('.selectable-row', tb);
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

    // OTRO TELAR (tus endpoints)
    async function procesarMovimientoOtroTelar(registroId, nuevoSalon, nuevoTelar, targetPosition) {
      if (typeof showLoading === 'function') showLoading();

      try {
        // 1) verificar
        const verifRes = await fetch(`/planeacion/programa-tejido/${registroId}/verificar-cambio-telar`, {
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

        ddLog('Verificación cambio telar', verificacion);

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

        // 2) confirmar
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
                <div><b>Origen:</b> Telar ${draggedRowTelar} (Salón ${draggedRowSalon})</div>
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

        // 3) aplicar
        if (typeof showLoading === 'function') showLoading();
        const cambioRes = await fetch(`/planeacion/programa-tejido/${registroId}/cambiar-telar`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ nuevo_salon: nuevoSalon, nuevo_telar: nuevoTelar, target_position: targetPosition })
        });

        const cambio = await cambioRes.json();
        if (typeof hideLoading === 'function') hideLoading();

        ddLog('Cambio telar backend', cambio);

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

    // DragEnd
    function handleDragEnd() {
      this.classList.remove('dragging');
      this.style.opacity = '';

      const tb = tbodyEl();
      if (tb) $$('.selectable-row', tb).forEach(r => r.classList.remove('drag-over','drag-over-warning','drop-not-allowed'));

      draggedRow = null;
      draggedRowTelar = null;
      draggedRowSalon = null;
      draggedRowCambioHilo = null;

      lastDragOverTime = 0;
      draggedRowOriginalTelarIndex = null;
      ddLastOver = { row:null, rawTelar:null, decisionTelar:null, salon:null, isBefore:false, y:null };

      if (!dragDropPerformed && dragBlockedReason) {
        if (typeof showToast === 'function') showToast(dragBlockedReason, 'error');
        restoreOriginalOrder();
      }

      dragBlockedReason = null;
      dragDropPerformed = false;

      ddLog('dragEnd');
    }
    </script>
