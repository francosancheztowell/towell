<script>
    /* ============================================================
     *  DRAG & DROP (ÚNICO) - CORREGIDO
     *  FIX BORDE DE TELAR:
     *   - Si estás sobre OTRO telar pero en la MITAD SUPERIOR => se interpreta como “final del telar actual”
     *   - Cambio real de telar: suelta en mitad inferior o mantén ALT
     *  Además:
     *   - getDragAfterElement usa DOM (no allRows) para evitar “saltos” al telar de abajo.
     *   - Cache seguro (WeakMap) sin .clear() (se reinicia reasignando).
     * ============================================================ */

    /** Log */
    const DD_LOG_ENABLED = true;
    function ddLog(step, payload) {
      if (!DD_LOG_ENABLED) return;
      if (payload !== undefined) console.log('[DragDrop]', step, payload);
      else console.log('[DragDrop]', step);
    }

    /** Estado Drag&Drop */
    let draggedRow = null;
    let draggedRowTelar = null;
    let draggedRowSalon = null;
    let draggedRowCambioHilo = null;

    let dragStartPosition = null;
    let originalOrderIds = [];
    let draggedRowOriginalTelarIndex = null;
    let dragBlockedReason = null;
    let dragDropPerformed = false;
    let lastDragOverTime = 0;

    /** Último target real del dragOver (clave del FIX) */
    let ddLastOver = {
      row: null,
      rawTelar: null,
      decisionTelar: null,
      salon: null,
      isBefore: false,
      y: null
    };

    /** Cache seguro */
    if (!window.rowCache || !(window.rowCache instanceof WeakMap)) window.rowCache = new WeakMap();
    try { if (typeof rowCache !== 'undefined') rowCache = window.rowCache; } catch (e) {}

    function clearRowCache() {
      window.rowCache = new WeakMap();
      try { if (typeof rowCache !== 'undefined') rowCache = window.rowCache; } catch (e) {}
    }

    function tbodyEl() {
      return document.querySelector('#mainTable tbody');
    }

    /** Helpers telar/salón/cambioHilo con cache */
    function getRowTelar(row) {
      if (!row) return null;
      const rc = window.rowCache;

      if (!rc.has(row)) {
        const telarCell = row.querySelector('[data-column="NoTelarId"]');
        const salonCell = row.querySelector('[data-column="SalonTejidoId"]');
        const cambioHiloCell = row.querySelector('[data-column="CambioHilo"]');
        rc.set(row, {
          telar: telarCell ? telarCell.textContent.trim() : null,
          salon: salonCell ? salonCell.textContent.trim() : null,
          cambioHilo: cambioHiloCell ? cambioHiloCell.textContent.trim() : null
        });
      }
      return rc.get(row).telar;
    }
    function getRowSalon(row) {
      if (!row) return null;
      const rc = window.rowCache;
      if (!rc.has(row)) getRowTelar(row);
      return rc.get(row).salon;
    }
    function getRowCambioHilo(row) {
      if (!row) return null;
      const rc = window.rowCache;
      if (!rc.has(row)) getRowTelar(row);
      return rc.get(row).cambioHilo;
    }

    /** Normalizar telar para comparar */
    function normalizeTelarValue(value) {
      if (value === undefined || value === null) return '';
      const str = String(value).trim();
      if (!str) return '';
      const numericValue = Number(str);
      if (!Number.isNaN(numericValue)) return numericValue.toString();
      return str.toUpperCase();
    }
    function isSameTelar(a, b) {
      return normalizeTelarValue(a) === normalizeTelarValue(b);
    }

    /** EnProceso */
    function isRowEnProceso(row) {
      const enProcesoCell = row?.querySelector?.('[data-column="EnProceso"]');
      if (!enProcesoCell) return false;
      const checkbox = enProcesoCell.querySelector('input[type="checkbox"]');
      return !!(checkbox && checkbox.checked);
    }

    /** Restaurar orden original (si cancelas o hay error) */
    function restoreOriginalOrder() {
      try {
        if (!originalOrderIds || originalOrderIds.length === 0) return;
        const tb = tbodyEl();
        if (!tb) return;

        const rowMap = new Map();
        tb.querySelectorAll('.selectable-row').forEach(row => {
          rowMap.set(row.getAttribute('data-id') || '', row);
        });

        const fragment = document.createDocumentFragment();
        originalOrderIds.forEach(id => {
          const row = rowMap.get(id);
          if (row) fragment.appendChild(row);
        });

        // filas nuevas al final
        rowMap.forEach((row, id) => {
          if (!originalOrderIds.includes(id)) fragment.appendChild(row);
        });

        tb.innerHTML = '';
        tb.appendChild(fragment);

        // refrescar allRows (IMPORTANTE)
        if (typeof allRows !== 'undefined') allRows = Array.from(tb.querySelectorAll('.selectable-row'));
        window.allRows = Array.isArray(window.allRows) ? allRows : Array.from(tb.querySelectorAll('.selectable-row'));

        clearRowCache();
        ddLog('restoreOriginalOrder', { total: (typeof allRows !== 'undefined' ? allRows.length : tb.querySelectorAll('.selectable-row').length) });
      } finally {
        originalOrderIds = [];
      }
    }

    /** afterElement: SIEMPRE desde DOM (no desde allRows) */
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

    /** Toggle global (lo usas desde otros módulos) */
    window.toggleDragDropMode = function toggleDragDropMode() {
      dragDropMode = !dragDropMode;

      const btn = document.getElementById('btnDragDrop');
      const tb = tbodyEl();
      if (!btn || !tb) return;

      if (dragDropMode) {
        if (typeof deselectRow === 'function') deselectRow();

        btn.classList.remove('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
        btn.classList.add('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
        btn.title = 'Desactivar arrastrar filas';

        // snapshot filas
        allRows = Array.from(tb.querySelectorAll('.selectable-row'));
        window.allRows = allRows;
        clearRowCache();

        allRows.forEach(row => {
          const enProceso = isRowEnProceso(row);
          row.draggable = !enProceso;
          row.onclick = null;

          if (!enProceso) {
            row.classList.add('cursor-move');
            row.removeEventListener('dragstart', handleDragStart);
            row.removeEventListener('dragover', handleDragOver);
            row.removeEventListener('drop', handleDrop);
            row.removeEventListener('dragend', handleDragEnd);

            row.addEventListener('dragstart', handleDragStart);
            row.addEventListener('dragover', handleDragOver);
            row.addEventListener('drop', handleDrop);
            row.addEventListener('dragend', handleDragEnd);
          } else {
            row.classList.add('cursor-not-allowed');
            row.style.opacity = '0.6';
          }
        });

        // permitir drop en huecos del tbody
        tb.addEventListener('dragover', handleDragOver);
        tb.addEventListener('drop', handleDrop);

        showToast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
        return;
      }

      // OFF
      btn.classList.remove('bg-gray-400', 'hover:bg-gray-500', 'ring-2', 'ring-gray-300');
      btn.classList.add('bg-black', 'hover:bg-gray-800', 'focus:ring-gray-500');
      btn.title = 'Activar/Desactivar arrastrar filas';

      allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      window.allRows = allRows;
      clearRowCache();

      allRows.forEach((row, i) => {
        row.draggable = false;
        row.classList.remove('cursor-move', 'cursor-not-allowed');
        row.style.opacity = '';

        row.removeEventListener('dragstart', handleDragStart);
        row.removeEventListener('dragover', handleDragOver);
        row.removeEventListener('drop', handleDrop);
        row.removeEventListener('dragend', handleDragEnd);

        row.onclick = () => selectRow(row, i);
      });

      tb.removeEventListener('dragover', handleDragOver);
      tb.removeEventListener('drop', handleDrop);

      showToast('Modo arrastrar desactivado', 'info');
    };

    /** DragStart */
    function handleDragStart(e) {
      if (isRowEnProceso(this)) {
        e.preventDefault();
        showToast('No se puede mover un registro en proceso', 'error');
        return false;
      }

      draggedRow = this;
      draggedRowTelar = getRowTelar(this);
      draggedRowSalon = getRowSalon(this);
      draggedRowCambioHilo = getRowCambioHilo(this);

      dragStartPosition = this.rowIndex;
      draggedRowOriginalTelarIndex = null;
      dragBlockedReason = null;
      dragDropPerformed = false;
      lastDragOverTime = 0;

      // snapshot orden DOM para restaurar
      const tb = tbodyEl();
      if (tb) {
        const snapshotRows = Array.from(tb.querySelectorAll('.selectable-row'));
        originalOrderIds = snapshotRows.map(r => r.getAttribute('data-id') || '');
        const sameTelar = snapshotRows.filter(r => isSameTelar(getRowTelar(r), draggedRowTelar));
        draggedRowOriginalTelarIndex = sameTelar.indexOf(draggedRow);
      }

      ddLastOver = { row: null, rawTelar: null, decisionTelar: null, salon: null, isBefore: false, y: null };

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

    /** DragOver (FIX borde) */
    function handleDragOver(e) {
      e.preventDefault();
      e.stopPropagation();

      dragBlockedReason = null;

      let targetRow = this;

      // Si viene del TBODY, buscar fila más cercana al mouse
      if (this.tagName === 'TBODY') {
        const rows = Array.from(this.querySelectorAll('.selectable-row'));
        let closestRow = null;
        let closestDistance = Infinity;

        for (const row of rows) {
          if (row === draggedRow) continue;
          const rect = row.getBoundingClientRect();
          const dist = Math.abs(e.clientY - (rect.top + rect.height / 2));
          if (dist < closestDistance) {
            closestDistance = dist;
            closestRow = row;
          }
        }
        if (!closestRow) return false;
        targetRow = closestRow;
      }

      if (!draggedRow || targetRow === draggedRow) return false;

      // throttle
      const now = performance.now();
      if (now - lastDragOverTime < 16) return false;
      lastDragOverTime = now;

      // limpiar clases del target previo
      if (ddLastOver.row && ddLastOver.row !== targetRow) {
        ddLastOver.row.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed');
      }

      const rawTargetTelar = getRowTelar(targetRow);
      const rawTargetSalon = getRowSalon(targetRow);

      const rect = targetRow.getBoundingClientRect();
      const isBefore = e.clientY < (rect.top + rect.height / 2);

      // FIX:
      // Si estás en mitad superior de OTRO telar => decisión = telar de origen (final del telar actual)
      let decisionTelar = rawTargetTelar;
      if (!isSameTelar(rawTargetTelar, draggedRowTelar) && isBefore && !e.altKey) {
        decisionTelar = draggedRowTelar;
      }

      ddLastOver = {
        row: targetRow,
        rawTelar: rawTargetTelar,
        decisionTelar,
        salon: rawTargetSalon,
        isBefore,
        y: e.clientY
      };

      ddLog('dragOver', {
        targetId: targetRow.getAttribute('data-id'),
        rawTargetTelar,
        decisionTelar,
        draggedTelar: draggedRowTelar,
        isBefore,
        altKey: !!e.altKey
      });

      // Validación SOLO si realmente cambia de telar (decisionTelar != origen)
      if (!isSameTelar(decisionTelar, draggedRowTelar)) {
        const tb = tbodyEl();
        if (tb) {
          const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
          const targetRowsInDOM = allRowsInDOM.filter(r => r !== draggedRow && isSameTelar(getRowTelar(r), decisionTelar));

          const idxTargetInTelar = targetRowsInDOM.indexOf(targetRow);
          let posObjetivo = idxTargetInTelar !== -1 ? (isBefore ? idxTargetInTelar : idxTargetInTelar + 1) : targetRowsInDOM.length;

          let ultimoEnProcesoIndex = -1;
          for (let i = 0; i < targetRowsInDOM.length; i++) {
            if (isRowEnProceso(targetRowsInDOM[i])) ultimoEnProcesoIndex = i;
          }

          if (ultimoEnProcesoIndex !== -1 && posObjetivo <= ultimoEnProcesoIndex) {
            dragBlockedReason = 'No se puede colocar antes de un registro en proceso en el telar destino.';
            e.dataTransfer.dropEffect = 'none';
            targetRow.classList.add('drop-not-allowed');
            targetRow.classList.remove('drag-over', 'drag-over-warning');
            return false;
          }
        }
      }

      // Feedback visual según decisionTelar
      if (isSameTelar(decisionTelar, draggedRowTelar)) {
        e.dataTransfer.dropEffect = 'move';
        targetRow.classList.add('drag-over');
        targetRow.classList.remove('drag-over-warning', 'drop-not-allowed');
      } else {
        e.dataTransfer.dropEffect = 'copy';
        targetRow.classList.add('drag-over-warning');
        targetRow.classList.remove('drag-over', 'drop-not-allowed');
      }

      // Reordenamiento visual DOM
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

    /** Posición objetivo (para cambio de telar) */
    function calcularPosicionObjetivo(targetTelar, targetRowElement = null) {
      const tb = tbodyEl();
      if (!tb) return 0;

      const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
      const targetRows = allRowsInDOM.filter(r => r !== draggedRow && isSameTelar(getRowTelar(r), targetTelar));

      if (!targetRowElement) return targetRows.length;

      const targetIndex = allRowsInDOM.indexOf(targetRowElement);
      if (targetIndex === -1) return targetRows.length;

      let count = 0;
      for (let i = 0; i < targetIndex; i++) {
        const r = allRowsInDOM[i];
        if (r !== draggedRow && isSameTelar(getRowTelar(r), targetTelar)) count++;
      }

      return ddLastOver?.isBefore === true ? count : count + 1;
    }

    /** Drop */
    async function handleDrop(e) {
      e.stopPropagation();
      e.preventDefault();

      dragDropPerformed = true;

      if (!draggedRow) {
        showToast('Error: No se encontró el registro arrastrado', 'error');
        restoreOriginalOrder();
        return false;
      }

      const registroId = (e.dataTransfer?.getData?.('text/plain')) || draggedRow.getAttribute('data-id');
      if (!registroId) {
        showToast('Error: No se pudo obtener el ID del registro', 'error');
        restoreOriginalOrder();
        return false;
      }

      if (dragBlockedReason) {
        showToast(dragBlockedReason, 'error');
        restoreOriginalOrder();
        return false;
      }

      const targetTelar = ddLastOver.decisionTelar || draggedRowTelar;
      const targetSalon = ddLastOver.salon || draggedRowSalon;

      ddLog('DROP', {
        registroId,
        draggedRowTelar,
        rawOverTelar: ddLastOver.rawTelar,
        decisionTelar: ddLastOver.decisionTelar,
        isBefore: ddLastOver.isBefore
      });

      const esMismoTelar = isSameTelar(draggedRowTelar, targetTelar);

      if (esMismoTelar) {
        await procesarMovimientoMismoTelar(registroId);
        return false;
      }

      // Cambio real de telar
      let targetPosition = calcularPosicionObjetivo(targetTelar, ddLastOver.row);

      // Ajuste por EnProceso en telar destino
      const tb = tbodyEl();
      const allRowsInDOM = tb ? Array.from(tb.querySelectorAll('.selectable-row')) : [];
      const targetRowsDOM = allRowsInDOM.filter(r => r !== draggedRow && isSameTelar(getRowTelar(r), targetTelar));

      let minAllowed = 0;
      for (let i = 0; i < targetRowsDOM.length; i++) {
        if (isRowEnProceso(targetRowsDOM[i])) minAllowed = i + 1;
      }
      if (targetPosition < minAllowed) targetPosition = minAllowed;
      targetPosition = Math.max(0, Math.min(targetPosition, targetRowsDOM.length));

      await procesarMovimientoOtroTelar(registroId, targetSalon, targetTelar, targetPosition);
      return false;
    }

    /** MISMO TELAR */
    async function procesarMovimientoMismoTelar(registroId) {
      const tb = tbodyEl();
      if (!tb) {
        restoreOriginalOrder();
        return;
      }

      const allRowsInDOM = Array.from(tb.querySelectorAll('.selectable-row'));
      const rowsSameTelar = allRowsInDOM.filter(r => isSameTelar(getRowTelar(r), draggedRowTelar));

      if (rowsSameTelar.length < 2) {
        showToast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
        restoreOriginalOrder();
        return;
      }

      const nuevaPosicion = rowsSameTelar.indexOf(draggedRow);
      const posicionOriginal =
        (typeof draggedRowOriginalTelarIndex === 'number' && draggedRowOriginalTelarIndex >= 0)
          ? draggedRowOriginalTelarIndex
          : null;

      ddLog('MismoTelar posiciones', {
        registroId,
        telar: draggedRowTelar,
        posicionOriginal,
        nuevaPosicion,
        total: rowsSameTelar.length
      });

      if (posicionOriginal !== null && posicionOriginal === nuevaPosicion) {
        showToast('El registro ya está en esa posición', 'info');
        restoreOriginalOrder();
        return;
      }

      showLoading();
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
        hideLoading();

        ddLog('Backend mismo telar', data);

        if (data.success) {
          originalOrderIds = [];
          if (typeof updateTableAfterDragDrop === 'function') {
            updateTableAfterDragDrop(data.detalles, registroId);
          }
          showToast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

          // refrescar allRows
          allRows = Array.from(tb.querySelectorAll('.selectable-row'));
          window.allRows = allRows;
          clearRowCache();
        } else {
          showToast(data.message || 'No se pudo actualizar la prioridad', 'error');
          restoreOriginalOrder();
        }
      } catch (err) {
        hideLoading();
        showToast('Ocurrió un error al procesar la solicitud', 'error');
        restoreOriginalOrder();
      }
    }

    /** OTRO TELAR (tus endpoints) */
    async function procesarMovimientoOtroTelar(registroId, nuevoSalon, nuevoTelar, targetPosition) {
      showLoading();

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
          hideLoading();
          showToast('No se pudo validar el cambio de telar', 'error');
          restoreOriginalOrder();
          return;
        }

        const verificacion = await verifRes.json();
        hideLoading();

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

        // 2) confirmar (ahora sí muestra detalles)
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
          showToast('Operación cancelada', 'info');
          restoreOriginalOrder();
          return;
        }

        // 3) aplicar
        showLoading();
        const cambioRes = await fetch(`/planeacion/programa-tejido/${registroId}/cambiar-telar`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ nuevo_salon: nuevoSalon, nuevo_telar: nuevoTelar, target_position: targetPosition })
        });

        const cambio = await cambioRes.json();
        hideLoading();

        ddLog('Cambio telar backend', cambio);

        if (!cambio.success) {
          showToast(cambio.message || 'No se pudo cambiar de telar', 'error');
          restoreOriginalOrder();
          return;
        }

        // tu flujo actual: recargar
        sessionStorage.setItem('priorityChangeMessage', cambio.message || 'Telar actualizado correctamente');
        sessionStorage.setItem('priorityChangeType', 'success');
        if (cambio.registro_id) {
          sessionStorage.setItem('scrollToRegistroId', cambio.registro_id);
          sessionStorage.setItem('selectRegistroId', cambio.registro_id);
        }
        window.location.href = '/planeacion/programa-tejido';

      } catch (err) {
        hideLoading();
        showToast('Error al procesar el cambio de telar', 'error');
        restoreOriginalOrder();
      }
    }

    /** DragEnd */
    function handleDragEnd() {
      this.classList.remove('dragging');
      this.style.opacity = '';

      const tb = tbodyEl();
      if (tb) {
        tb.querySelectorAll('.selectable-row').forEach(row => {
          row.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed');
        });
      }

      // reset
      draggedRow = null;
      draggedRowTelar = null;
      draggedRowSalon = null;
      draggedRowCambioHilo = null;

      dragStartPosition = null;
      lastDragOverTime = 0;
      draggedRowOriginalTelarIndex = null;

      ddLastOver = { row: null, rawTelar: null, decisionTelar: null, salon: null, isBefore: false, y: null };

      // si no hubo drop real y estaba bloqueado, restaurar
      if (!dragDropPerformed && dragBlockedReason) {
        showToast(dragBlockedReason, 'error');
        restoreOriginalOrder();
      }
      dragBlockedReason = null;
      dragDropPerformed = false;

      ddLog('dragEnd');
    }
    </script>
