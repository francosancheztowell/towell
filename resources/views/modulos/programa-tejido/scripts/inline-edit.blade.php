// =========================
// Inline Edit - Estado
// =========================
// inlineEditMode y catalogosCache ya están declarados en state.blade.php
// inlineFieldPayloadMap también está en state.blade.php y usa nombres de BD
// uiInlineEditableFields ahora usa directamente los nombres de BD que coinciden con data-column

// Helpers de formateo mejorados
function parseSqlDateTimeLocal(raw) {
  if (!raw) return null;
  const s = String(raw).trim().replace('T', ' ').replace('Z', '');
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
  if (m) {
    const Y = +m[1], Mo = +m[2] - 1, D = +m[3];
    const hh = +(m[4] || 0), mm = +(m[5] || 0), ss = +(m[6] || 0);
    const d = new Date(Y, Mo, D, hh, mm, ss, 0);
    return isNaN(d.getTime()) ? null : d;
  }
  const d = new Date(raw);
  return isNaN(d.getTime()) ? null : d;
}

function formatDateOnlyDisplay(raw) {
  const d = parseSqlDateTimeLocal(raw);
  if (!d) return '';
  return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTimeDisplay(raw) {
  const d = parseSqlDateTimeLocal(raw);
  if (!d) return '';
  const date = d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  return `${date}<br>${hh}:${mm}`;
}

function formatNumber2(raw) {
  if (raw == null || raw === '') return '';
  const n = Number(raw);
  if (!isFinite(n)) return String(raw);
  return n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Mantener formatDateDisplay para compatibilidad con código existente
function formatDateDisplay(isoOrDate) {
  return formatDateOnlyDisplay(isoOrDate);
}

function toInputDate(val) {
  // acepta dd/mm/yyyy, yyyy-mm-dd, dd-mm-yyyy, etc.
  if (!val) return '';
  const s = String(val).trim();

  // si ya viene YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;

  // dd/mm/yyyy o dd-mm-yyyy
  const m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m) {
    const dd = String(m[1]).padStart(2, '0');
    const mm = String(m[2]).padStart(2, '0');
    let yy = String(m[3]);
    if (yy.length === 2) yy = '20' + yy;
    return `${yy}-${mm}-${dd}`;
  }

  // intenta parsear fecha
  const d = new Date(s);
  if (!isNaN(d.getTime())) {
    const yy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yy}-${mm}-${dd}`;
  }

  return '';
}

/**
 * Config de ediciones (usando nombres de campos de BD que coinciden con data-column):
 * - type: text|number|date|select
 * - catalog: hilos|calendarios|aplicaciones (si type=select)
 */
const uiInlineEditableFields = {
  // Usar los nombres de campos de BD que coinciden con data-column
  FibraRizo: {
    type: 'select',
    catalog: 'hilos',
    displayFormatter: (_val, inputEl) => inputEl?.selectedOptions?.[0]?.textContent || ''
  },
  CalendarioId: {
    type: 'select',
    catalog: 'calendarios',
    displayFormatter: (_val, inputEl) => inputEl?.selectedOptions?.[0]?.textContent || ''
  },
  TotalPedido: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  FlogsId: {
    type: 'text',
    maxLength: 20
  },
  NombreProyecto: {
    type: 'text',
    maxLength: 150
  },
  AplicacionId: {
    type: 'select',
    catalog: 'aplicaciones',
    toPayload: (v) => (v === '' || v === 'NA') ? null : v,
    displayFormatter: (_val, inputEl) => inputEl?.selectedOptions?.[0]?.textContent || ''
  },
  EntregaProduc: {
    type: 'date',
    inputFormatter: (v) => toInputDate(v),
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  TamanoClave: {
    type: 'text',
    maxLength: 40
  },
  Rasurado: {
    type: 'text',
    maxLength: 2
  },
  ProgramarProd: {
    type: 'date',
    inputFormatter: (v) => toInputDate(v),
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  NoTiras: {
    type: 'number',
    min: 0,
    step: 1,
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  Peine: {
    type: 'number',
    min: 0,
    step: 1,
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  LargoCrudo: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  Luchaje: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  PesoCrudo: {
    type: 'number',
    min: 0,
    step: 0.01,
    displayFormatter: (v) => (v == null || v === '') ? '' : Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  },
  EntregaPT: {
    type: 'date',
    inputFormatter: (v) => toInputDate(v),
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  EntregaCte: {
    type: 'datetime-local',
    inputFormatter: (v) => {
      if (!v) return '';
      const d = new Date(v);
      if (isNaN(d.getTime())) return '';
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      const hours = String(d.getHours()).padStart(2, '0');
      const minutes = String(d.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day}T${hours}:${minutes}`;
    },
    toPayload: (v) => v === '' ? null : v,
    displayFormatter: (v) => v ? formatDateDisplay(v) : ''
  },
  PTvsCte: {
    type: 'number',
    step: 1,
    displayFormatter: (v) => (v == null || v === '') ? '' : String(v)
  },
  // NOTA: Las siguientes columnas NO son editables (calculadas automáticamente):
  // - DiasEficiencia (Dias Ef)
  // - ProdKgDia
  // - StdDia
  // - ProdKgDia2
  // - StdToaHra
  // - HorasProd
  // - StdHrsEfect
  // - FechaInicio (Inicio)
  // - FechaFinal (Fin)
};

// ==========================
// Inline Edit - Lógica
// ==========================
(function() {
  'use strict';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const tbodyEl = () => $('#mainTable tbody');

  function getCellValue(cell) {
    // data-value tiene prioridad (valor real)
    const v = cell?.dataset?.value;
    if (v !== undefined && v !== null) return String(v).trim();
    return (cell?.textContent || '').trim();
  }

  function setCellValue(cell, display, rawValue) {
    cell.innerHTML = (display ?? '');
    if (rawValue === null || rawValue === undefined) {
      delete cell.dataset.value;
    } else {
      cell.dataset.value = String(rawValue);
    }
    cell.dataset.originalValue = cell.innerHTML;
  }

  // Cargar catálogo
  async function loadCatalog(catalogName) {
    if (catalogosCache[catalogName]) return catalogosCache[catalogName];

    let url = '';
    if (catalogName === 'hilos') url = '/programa-tejido/hilos-options';
    else if (catalogName === 'aplicaciones') url = '/programa-tejido/aplicacion-id-options';
    else if (catalogName === 'calendarios') url = '/programa-tejido/calendario-id-options';
    else return [];

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        credentials: 'same-origin'
      });

      if (!res.ok) return [];

      const data = await res.json();

      let normalized = [];
      if (Array.isArray(data)) {
        normalized = data.map(x => ({ value: x, label: x }));
      } else if (data?.data && Array.isArray(data.data)) {
        if (catalogName === 'hilos') {
          normalized = data.data.map(item => ({
            value: item.Hilo || item.value || item.id || item,
            label: item.Hilo
              ? (item.Hilo + (item.Fibra ? ' - ' + item.Fibra : ''))
              : (item.label || item.name || item.value || item.id || String(item))
          }));
        } else {
          normalized = data.data.map(item => ({
            value: item.value || item.id || item,
            label: item.label || item.name || item.text || item.value || item.id || String(item)
          }));
        }
      } else if (data?.success && Array.isArray(data.data)) {
        normalized = data.data.map(item => ({
          value: item.value || item.id || item,
          label: item.label || item.name || item.text || item.value || item.id || String(item)
        }));
      }

      catalogosCache[catalogName] = normalized;
      return normalized;
    } catch (e) {
      console.error('loadCatalog error', catalogName, e);
      return [];
    }
  }

  // ====== Editar SOLO la celda clickeada ======
  window.enableInlineEditForCell = async function enableInlineEditForCell(cell) {
    if (!cell) {
      return;
    }

    const row = cell.closest('.selectable-row');
    if (!row) {
      return;
    }

    const rowId = row.getAttribute('data-id');
    if (!rowId) {
      return;
    }

    // Asegurar que solo esta fila específica tenga el amarillo (limpiar otras filas)
    const allRows = window.allRows?.length ? window.allRows : Array.from(document.querySelectorAll('.selectable-row'));
    allRows.forEach(r => {
      if (r !== row && r.classList.contains('bg-yellow-100') && !r.querySelector('.inline-edit-input')) {
        // Solo quitar amarillo de filas que no están en edición
        r.classList.remove('bg-yellow-100');
      }
    });

    const columnName = cell.getAttribute('data-column');
    if (!columnName) {
      return;
    }

    if (!uiInlineEditableFields[columnName]) {
      return;
    }

    // si ya está editando esa celda
    if (cell.querySelector('.inline-edit-input')) {
      return;
    }


    const cfg = uiInlineEditableFields[columnName];
    const currentValue = getCellValue(cell);
    const originalDisplay = cell.dataset.originalValue ?? cell.innerHTML;
    const originalDataValue = cell.dataset.value;

    // Guardar valores originales para poder restaurarlos
    cell.dataset.originalValue = originalDisplay;
    if (originalDataValue !== undefined) {
      cell.dataset.originalDataValue = originalDataValue;
    }

    // NO agregar amarillo al inicio, solo cuando hay cambios

    const wrap = document.createElement('div');
    wrap.className = 'inline-edit-input-container';
    wrap.style.width = '100%';

    let input;

    if (cfg.type === 'select' && cfg.catalog) {
      input = document.createElement('select');
      // Agregar clase especial para TotalPedido si es select (aunque no debería serlo)
      const baseClass = columnName === 'TotalPedido'
        ? 'inline-edit-input inline-edit-input-wide w-full'
        : 'inline-edit-input w-full';
      input.className = baseClass;
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      // opción vacía
      const empty = document.createElement('option');
      empty.value = '';
      empty.textContent = '-- Seleccionar --';
      input.appendChild(empty);

      // opcional: NA para aplicaciones
      if (columnName === 'AplicacionId') {
        const na = document.createElement('option');
        na.value = 'NA';
        na.textContent = 'NA';
        input.appendChild(na);
      }

      const catalog = await loadCatalog(cfg.catalog);
      catalog.forEach(item => {
        const opt = document.createElement('option');
        opt.value = String(item.value ?? '');
        opt.textContent = String(item.label ?? item.value ?? '');
        input.appendChild(opt);
      });

      // seleccionar por value (data-value) o por texto
      const byValue = Array.from(input.options).find(o => o.value == currentValue);
      if (byValue) byValue.selected = true;
      else {
        const byText = Array.from(input.options).find(o => o.textContent.trim() === currentValue);
        if (byText) byText.selected = true;
      }

    } else if (cfg.type === 'date' || cfg.type === 'datetime-local') {
      input = document.createElement('input');
      input.type = cfg.type;
      // Agregar clase especial para TotalPedido si es fecha (aunque no debería serlo)
      const baseClass = columnName === 'TotalPedido'
        ? 'inline-edit-input inline-edit-input-wide w-full'
        : 'inline-edit-input w-full';
      input.className = baseClass;
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      // Para datetime-local, usar el data-value original si existe para preservar segundos
      let dateValue = currentValue;
      if (cfg.type === 'datetime-local' && originalDataValue) {
        // Usar el valor original del data-value que tiene la fecha completa con segundos
        dateValue = originalDataValue;
      }

      input.value = cfg.inputFormatter ? cfg.inputFormatter(dateValue) : dateValue;

    } else {
      input = document.createElement('input');
      input.type = cfg.type || 'text';
      // Agregar clase especial para TotalPedido para hacerlo más ancho
      const baseClass = columnName === 'TotalPedido'
        ? 'inline-edit-input inline-edit-input-wide w-full'
        : 'inline-edit-input w-full';
      input.className = baseClass;
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      if (cfg.maxLength) input.maxLength = cfg.maxLength;
      if (cfg.step) input.step = cfg.step;
      if (cfg.min !== undefined) input.min = cfg.min;

      if (cfg.type === 'number') {
        // Mantener el valor original sin redondear para preservar decimales
        // No usar parseFloat que puede perder precisión, usar el valor tal cual
        const numStr = String(currentValue).replace(/[^\d.-]/g, '');
        if (numStr === '' || numStr === '-') {
          input.value = '';
        } else {
          // Preservar todos los decimales del valor original
          input.value = numStr;
        }
      } else {
        input.value = currentValue;
      }
    }

    function cancel() {
      // Limpiar timeout del amarillo si existe
      if (yellowTimeout) {
        clearTimeout(yellowTimeout);
        yellowTimeout = null;
      }

      cell.innerHTML = originalDisplay;
      // Solo quitar el amarillo si no hay otras celdas en edición
      const otherInputs = row.querySelectorAll('.inline-edit-input');
      if (otherInputs.length === 0 || (otherInputs.length === 1 && otherInputs[0] === input)) {
        row.classList.remove('inline-edit-row');
        row.classList.remove('bg-yellow-100');
      }
    }

    // Variable para el timeout del amarillo (ya no se usa, el amarillo permanece hasta guardar)
    let yellowTimeout = null;

    // Mostrar amarillo cuando hay cambios y mantenerlo hasta que se guarde completamente
    input.addEventListener('input', () => {
      const newVal = (input.value ?? '').trim();
      // Para datetime-local, usar el valor original del data-value si existe
      let compareValue = currentValue;
      if (cfg.type === 'datetime-local' && originalDataValue) {
        compareValue = originalDataValue;
      }
      const oldVal = (cfg.type === 'date' || cfg.type === 'datetime-local')
        ? (cfg.inputFormatter ? cfg.inputFormatter(compareValue) : compareValue)
        : String(compareValue ?? '');

      // Limpiar timeout anterior si existe (ya no se usa, pero por si acaso)
      if (yellowTimeout) {
        clearTimeout(yellowTimeout);
        yellowTimeout = null;
      }

      // Si hay cambios, mostrar amarillo y mantenerlo hasta guardar
      if (newVal !== String(oldVal ?? '')) {
        row.classList.add('inline-edit-row');
        row.classList.add('bg-yellow-100');
        // NO quitar el amarillo automáticamente, permanecerá hasta que se guarde
      } else {
        // Si no hay cambios, quitar amarillo inmediatamente
        row.classList.remove('bg-yellow-100');
      }
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        saveInlineField(input, row, cell);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        cancel();
      }
    });

    input.addEventListener('blur', () => {
      setTimeout(() => {
        const newVal = (input.value ?? '').trim();
        // Para datetime-local, usar el valor original del data-value si existe
        let compareValue = currentValue;
        if (cfg.type === 'datetime-local' && originalDataValue) {
          compareValue = originalDataValue;
        }
        const oldVal = (cfg.type === 'date' || cfg.type === 'datetime-local')
          ? (cfg.inputFormatter ? cfg.inputFormatter(compareValue) : compareValue)
          : String(compareValue ?? '');

        if (newVal !== String(oldVal ?? '')) {
          saveInlineField(input, row, cell);
        } else {
          cancel();
        }
      }, 150);
    });

    wrap.appendChild(input);
    cell.innerHTML = '';
    cell.appendChild(wrap);

    // No hacer focus automático para que no se active el amarillo
    // input.focus();
    // input.select?.();
  }

  // actualizar más celdas si backend recalcula cosas (fechas, saldo, etc.)
  function applyRowUpdatesFromBackend(row, data) {
    if (!row || !data) return;

    const num2Cols = new Set([
      'DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','HorasProd','StdHrsEfect','DiasJornada','PesoGRM2',
      'TotalPedido','LargoCrudo','Luchaje','PesoCrudo'
    ]);

    const dateTimeCols = new Set(['FechaInicio','FechaFinal','EntregaCte']);
    const dateOnlyCols = new Set(['EntregaProduc','EntregaPT','ProgramarProd']);

    Object.entries(data).forEach(([backendKey, raw]) => {
      if (raw === undefined) return;

      const td = row.querySelector(`td[data-column="${backendKey}"]`);
      if (!td) return;

      let display = '';
      const cfg = uiInlineEditableFields[backendKey];

      if (dateTimeCols.has(backendKey)) {
        display = raw ? formatDateTimeDisplay(raw) : '';
      } else if (dateOnlyCols.has(backendKey) || backendKey.startsWith('Entrega')) {
        display = raw ? formatDateOnlyDisplay(raw) : '';
      } else if (num2Cols.has(backendKey)) {
        display = formatNumber2(raw);
      } else if (cfg && cfg.displayFormatter) {
        display = cfg.displayFormatter(raw);
      } else {
        display = (raw == null || raw === '') ? '' : String(raw);
      }

      setCellValue(td, display, raw);
    });
  }

  // Guardar campo individual
  async function saveInlineField(input, row, cell) {
    const columnName = input.dataset.field;
    const rowId = input.dataset.rowId;
    const cfg = uiInlineEditableFields[columnName];

    // Usar directamente el nombre de campo BD (que coincide con data-column) para el payload
    const payloadField = inlineFieldPayloadMap[columnName] || columnName;

    if (!cfg || !payloadField || !rowId) return;

    let value = (input.value ?? '').trim();

    if (cfg.toPayload) value = cfg.toPayload(value);
    else if (cfg.type === 'number') value = (value === '' ? null : Number(value));
    else if (value === '') value = null;

    row.classList.add('inline-saving');

    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch(`/planeacion/programa-tejido/${rowId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        credentials: 'same-origin',
        body: JSON.stringify({ [payloadField]: value })
      });

      const result = await res.json().catch(() => ({}));
      if (!res.ok || result?.success === false) {
        throw new Error(result?.message || 'Error al guardar');
      }

      // display - formatear bien al instante (sin recargar)
      let displayValue = '';
      if (cfg?.displayFormatter) {
        displayValue = cfg.displayFormatter(value, input);
      } else if (cfg?.type === 'number') {
        displayValue = formatNumber2(value);
      } else {
        displayValue = (value == null) ? '' : String(value);
      }

      setCellValue(cell, displayValue, value);

      // si backend manda resumen, actualiza otras celdas (fechas/saldo/etc)
      if (result?.data) applyRowUpdatesFromBackend(row, result.data);

      // Quitar amarillo después de guardar exitosamente
      row.classList.remove('bg-yellow-100');

      if (typeof window.showToast === 'function') {
        window.showToast('Campo actualizado', 'success');
      } else if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1200, showConfirmButton: false, toast: true, position: 'top-end' });
      }
    } catch (e) {
      console.error('saveInlineField error', e);

      // restaurar
      const original = cell.dataset.originalValue ?? '';
      cell.innerHTML = original;

      // Mantener amarillo si hay error (para indicar que hay cambios pendientes)
      // No quitar el amarillo aquí, se mantendrá hasta que se guarde exitosamente

      if (typeof window.showToast === 'function') {
        window.showToast(`Error: ${e.message}`, 'error');
      } else if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: e.message, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
      }
    } finally {
      row.classList.remove('inline-saving');
      // No remover inline-edit-row ni bg-yellow-100 aquí
      // El amarillo permanecerá hasta que se guarde exitosamente o se cancele
    }
  }

  // Cerrar edición inline de una fila (restaurar valores originales)
  window.closeInlineEditForRow = function(row) {
    if (!row) return;

    const cells = row.querySelectorAll('td[data-column]');
    cells.forEach(cell => {
      const inputContainer = cell.querySelector('.inline-edit-input-container');
      if (inputContainer) {
        // Siempre restaurar el valor original guardado
        const originalValue = cell.dataset.originalValue;
        if (originalValue !== undefined && originalValue !== null) {
          cell.innerHTML = originalValue;
          // Restaurar también el data-value si existe
          const originalDataValue = cell.dataset.originalDataValue;
          if (originalDataValue !== undefined) {
            cell.dataset.value = originalDataValue;
          }
        } else {
          // Si no hay valor original, obtener el valor del input y formatearlo
          const input = cell.querySelector('.inline-edit-input');
          if (input) {
            const currentValue = input.value;
            const col = cell.getAttribute('data-column');
            const cfg = uiInlineEditableFields[col];
            if (cfg && cfg.displayFormatter) {
              cell.innerHTML = cfg.displayFormatter(currentValue, input);
            } else {
              cell.innerHTML = currentValue || '';
            }
          }
        }
      }
    });

    // Remover clases de edición
    row.classList.remove('inline-edit-row');
    row.classList.remove('bg-yellow-100');
  };

  // Activar edición en todas las celdas editables de una fila
  window.enableInlineEditForAllCellsInRow = async function(row) {
    if (!row) return;

    // NO agregar amarillo al inicio, solo cuando hay cambios

    const cells = row.querySelectorAll('td[data-column]');
    const editableCells = Array.from(cells).filter(cell => {
      const col = cell.getAttribute('data-column');
      return col && uiInlineEditableFields[col] && !cell.querySelector('.inline-edit-input');
    });

    // Activar edición en todas las celdas editables simultáneamente
    const promises = editableCells.map(cell => {
      if (window.enableInlineEditForCell) {
        return window.enableInlineEditForCell(cell);
      }
      return Promise.resolve();
    });

    await Promise.all(promises);
  };

  // Aplicar modo inline
  window.applyInlineModeToRows = function() {
    if (!inlineEditMode) return;

    const rows = window.allRows?.length ? window.allRows : $$('.selectable-row');
    rows.forEach(r => r.classList.add('inline-edit-ready'));

    const tb = tbodyEl();
    if (!tb) return;

    // Remover listener anterior si existe
    if (tb.dataset.inlineBound && tb._inlineEditHandler) {
      tb.removeEventListener('click', tb._inlineEditHandler);
    }

    // Crear nuevo handler
    tb._inlineEditHandler = function inlineEditClickHandler(e) {
      if (!inlineEditMode) return;

      // Buscar la celda clickeada
      const cell = e.target.closest('td[data-column]');
      if (!cell) return;

      const col = cell.getAttribute('data-column');
      if (!col) return;

      // Verificar si el campo es editable
      if (!uiInlineEditableFields[col]) {
        return; // No es editable, dejar que el evento continúe
      }

      // Evitar que se active si se hace click en un input existente
      if (cell.querySelector('.inline-edit-input')) return;

      // Detener propagación SOLO si es una celda editable
      e.stopPropagation();

      if (window.enableInlineEditForCell) {
        window.enableInlineEditForCell(cell);
      }
    };

    tb.addEventListener('click', tb._inlineEditHandler, true); // Usar capture phase para tener prioridad
    tb.dataset.inlineBound = '1';
  };

  window.toggleInlineEditMode = function() {
    inlineEditMode = !inlineEditMode;
    window.inlineEditMode = inlineEditMode; // Sincronizar con window

    const tb = tbodyEl();
    if (inlineEditMode) {
      tb?.classList.add('inline-edit-mode');
      // Forzar re-aplicación del modo inline
      if (tb?.dataset.inlineBound) {
        delete tb.dataset.inlineBound;
      }
      window.applyInlineModeToRows();
      if (typeof window.showToast === 'function') window.showToast('Edición inline activada: clic en una celda editable', 'info');
    } else {
      tb?.classList.remove('inline-edit-mode');
      // Remover listener
      if (tb?._inlineEditHandler) {
        tb.removeEventListener('click', tb._inlineEditHandler, true);
        delete tb._inlineEditHandler;
        delete tb.dataset.inlineBound;
      }
      // cerrar inputs abiertos y limpiar ediciones de todas las filas
      const rowsWithInputs = $$('.selectable-row').filter(row => row.querySelector('.inline-edit-input'));
      rowsWithInputs.forEach(row => {
        if (typeof window.closeInlineEditForRow === 'function') {
          window.closeInlineEditForRow(row);
        }
      });
      if (typeof window.showToast === 'function') window.showToast('Edición inline desactivada', 'info');
    }
  };

})();
