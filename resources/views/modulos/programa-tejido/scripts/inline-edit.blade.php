// ==========================
// Inline Edit - Estado / Config
// ==========================
// inlineEditMode y catalogosCache ya están declarados en state.blade.php
// inlineFieldPayloadMap también está en state.blade.php y usa nombres de BD
// uiInlineEditableFields ahora usa directamente los nombres de BD que coinciden con data-column

function formatDateDisplay(isoOrDate) {
  if (!isoOrDate) return '';
  try {
    const d = new Date(String(isoOrDate).includes('T') ? isoOrDate : (String(isoOrDate).trim() + 'T00:00:00'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } catch { return ''; }
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
  }
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
  async function enableInlineEditForCell(cell) {
    if (!cell) {
      console.log('enableInlineEditForCell: cell es null');
      return;
    }

    const row = cell.closest('.selectable-row');
    const rowId = row?.getAttribute('data-id');
    if (!rowId) {
      console.log('enableInlineEditForCell: no se encontró rowId');
      return;
    }

    const columnName = cell.getAttribute('data-column');
    if (!columnName) {
      console.log('enableInlineEditForCell: no se encontró columnName');
      return;
    }

    if (!uiInlineEditableFields[columnName]) {
      console.log('enableInlineEditForCell: campo no editable:', columnName);
      return;
    }

    // si ya está editando esa celda
    if (cell.querySelector('.inline-edit-input')) {
      console.log('enableInlineEditForCell: ya hay un input en esta celda');
      return;
    }

    console.log('enableInlineEditForCell: activando edición para', columnName, 'en fila', rowId);

    const cfg = uiInlineEditableFields[columnName];
    const currentValue = getCellValue(cell);
    const originalDisplay = cell.dataset.originalValue ?? cell.innerHTML;
    cell.dataset.originalValue = originalDisplay;

    row.classList.add('inline-edit-row');

    const wrap = document.createElement('div');
    wrap.className = 'inline-edit-input-container';
    wrap.style.width = '100%';

    let input;

    if (cfg.type === 'select' && cfg.catalog) {
      input = document.createElement('select');
      input.className = 'inline-edit-input w-full';
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

    } else if (cfg.type === 'date') {
      input = document.createElement('input');
      input.type = 'date';
      input.className = 'inline-edit-input w-full';
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;
      input.value = cfg.inputFormatter ? cfg.inputFormatter(currentValue) : currentValue;

    } else {
      input = document.createElement('input');
      input.type = cfg.type || 'text';
      input.className = 'inline-edit-input w-full';
      input.dataset.field = columnName;
      input.dataset.rowId = rowId;

      if (cfg.maxLength) input.maxLength = cfg.maxLength;
      if (cfg.step) input.step = cfg.step;
      if (cfg.min !== undefined) input.min = cfg.min;

      if (cfg.type === 'number') {
        const num = parseFloat(String(currentValue).replace(/[^\d.-]/g, ''));
        input.value = isNaN(num) ? '' : String(Math.round(num));
      } else {
        input.value = currentValue;
      }
    }

    function cancel() {
      cell.innerHTML = originalDisplay;
      row.classList.remove('inline-edit-row');
    }

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
        const oldVal = (cfg.type === 'date')
          ? (cfg.inputFormatter ? cfg.inputFormatter(currentValue) : currentValue)
          : String(currentValue ?? '');

        if (newVal !== String(oldVal ?? '')) saveInlineField(input, row, cell);
        else cancel();
      }, 150);
    });

    wrap.appendChild(input);
    cell.innerHTML = '';
    cell.appendChild(wrap);

    input.focus();
    input.select?.();
  }

  // actualizar más celdas si backend recalcula cosas (fechas, saldo, etc.)
  function applyRowUpdatesFromBackend(row, data) {
    if (!row || !data) return;

    // Los nombres de backend coinciden directamente con data-column
    Object.entries(data).forEach(([backendKey, raw]) => {
      if (raw === undefined) return;
      const td = row.querySelector(`td[data-column="${backendKey}"]`);
      if (!td) return;

      let display = (raw ?? '') + '';
      const cfg = uiInlineEditableFields[backendKey];

      // Formatear según el tipo de campo
      if (cfg && cfg.displayFormatter) {
        display = cfg.displayFormatter(raw);
      } else if (backendKey === 'TotalPedido' || backendKey === 'SaldoPedido') {
        display = (raw == null) ? '' : Number(raw).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } else if (backendKey.includes('Fecha') || backendKey.includes('Entrega')) {
        const d = raw ? String(raw).replace(' ', 'T') : '';
        display = d ? formatDateDisplay(d) : '';
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

      // display
      let displayValue = '';
      if (cfg.displayFormatter) displayValue = cfg.displayFormatter(value, input);
      else displayValue = (value == null) ? '' : String(value);

      setCellValue(cell, displayValue, value);

      // si backend manda resumen, actualiza otras celdas (fechas/saldo/etc)
      if (result?.data) applyRowUpdatesFromBackend(row, result.data);

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

      if (typeof window.showToast === 'function') {
        window.showToast(`Error: ${e.message}`, 'error');
      } else if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: e.message, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
      }
    } finally {
      row.classList.remove('inline-saving');
      row.classList.remove('inline-edit-row');
    }
  }

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

      console.log('Activando edición para:', col, 'en modo inline:', inlineEditMode);
      enableInlineEditForCell(cell);
    };

    tb.addEventListener('click', tb._inlineEditHandler, true); // Usar capture phase para tener prioridad
    tb.dataset.inlineBound = '1';
  };

  window.toggleInlineEditMode = function() {
    inlineEditMode = !inlineEditMode;

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
      // cerrar inputs abiertos
      $$('.inline-edit-input-container').forEach(c => {
        const td = c.closest('td');
        if (td) td.innerHTML = td.dataset.originalValue || td.innerHTML;
      });
      if (typeof window.showToast === 'function') window.showToast('Edición inline desactivada', 'info');
    }
  };

})();
