/**
 * Alineación (Planeación). Antes: planeacion/alineacion/_script.blade.php (<script> inline
 * con los datos incrustados). Los datos y rutas llegan en data-pagina de #alineacion.
 *
 * Tabla con fijar columnas, filtro por valores de columna (menú del encabezado: clic derecho
 * o mantener presionado en tablet), selección de renglón y refresco cada 5 minutos.
 */
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { accionesTactiles } from '../../../utils/acciones-tactiles.ts';
import { onReady } from '../../../utils/dom.ts';
import { escapeHtml } from '../../../utils/format.ts';
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import {
    claseCelda,
    claseFila,
    filtrarFilas,
    valorCelda,
    valoresColumna,
    type ConfigAlineacion,
    type FilaAlineacion,
    type FiltroAlineacion,
} from './logica.ts';

const REFRESCO_MS = 5 * 60 * 1000;

let cfg: ConfigAlineacion;
const estado = {
    data: [] as FilaAlineacion[],
    filtered: [] as FilaAlineacion[],
    pinnedColumns: [] as number[],
    filters: [] as FiltroAlineacion[],
    selectedRowIndex: null as number | null,
};

const columnElements = (idx: number) => Array.from(document.querySelectorAll<HTMLElement>('#mainTable .column-' + idx));
const etiquetaColumna = (idx: number) => {
    const field = cfg.columnas[idx];
    return (field && cfg.columnLabels[field]) || field || 'Columna ' + idx;
};

function updatePinnedPositions(): void {
    if (!document.getElementById('mainTable')) return;
    let left = 0;
    estado.pinnedColumns.forEach((idx) => {
        const els = columnElements(idx);
        const th = els.find((el) => el.tagName === 'TH');
        if (!th) return;
        const w = th.offsetWidth || 80;
        els.forEach((el) => {
            el.classList.add('alineacion-pinned');
            el.style.left = left + 'px';
            el.style.position = 'sticky';
            if (el.tagName === 'TH') el.style.top = '0';
        });
        left += w;
    });
    document.querySelectorAll<HTMLElement>('#mainTable th[data-index], #mainTable td[data-index]').forEach((el) => {
        const idx = parseInt(el.getAttribute('data-index') ?? '', 10);
        if (Number.isNaN(idx) || !estado.pinnedColumns.includes(idx)) {
            el.classList.remove('alineacion-pinned');
            el.style.left = '';
            el.style.position = '';
            el.style.top = '';
        }
    });
}

function botonIcono(accion: string, titulo: string, icono: string, dato: Record<string, string>): HTMLButtonElement {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'alineacion-header-icon';
    b.title = titulo;
    b.setAttribute('aria-label', titulo);
    b.dataset.action = accion;
    Object.assign(b.dataset, dato);
    const i = document.createElement('i');
    i.className = icono;
    i.setAttribute('aria-hidden', 'true');
    b.appendChild(i);
    return b;
}

function updateColumnHeaderIcons(): void {
    cfg.columnas.forEach((field, idx) => {
        const th = columnElements(idx).find((el) => el.tagName === 'TH');
        const container = th?.querySelector('.alineacion-header-icons');
        if (!container) return;
        const iconos: HTMLElement[] = [];
        if (estado.filters.some((f) => f.column === field)) iconos.push(botonIcono('clear-filter', 'Quitar filtro', 'fas fa-filter', { column: field }));
        if (estado.pinnedColumns.includes(idx)) iconos.push(botonIcono('unpin', 'Desfijar', 'fas fa-thumbtack', { index: String(idx) }));
        container.replaceChildren(...iconos);
    });
}

function celda(row: FilaAlineacion, col: string, colIdx: number, seleccionadaSinParo: boolean): HTMLTableCellElement {
    const raw = valorCelda(col, row[col] ?? '');
    const td = document.createElement('td');
    td.className = claseCelda(col, raw, seleccionadaSinParo) + colIdx;
    td.dataset.column = col;
    td.dataset.index = String(colIdx);
    td.dataset.value = raw;
    if (col === 'NoTelarId' && row._tieneParoActivo) {
        const i = document.createElement('i');
        i.className = 'fas fa-exclamation-triangle text-yellow-500 mr-1';
        i.title = 'Paro activo en mantenimiento';
        td.appendChild(i);
    }
    td.append(raw);
    return td;
}

function renderTable(): void {
    estado.filtered = filtrarFilas(estado.data, estado.filters);
    const tbody = document.getElementById('alineacion-body');
    if (!tbody) return;

    if (!estado.filtered.length) {
        const td = document.createElement('td');
        td.colSpan = cfg.columnas.length;
        td.className = 'py-16 text-center text-gray-500';
        td.textContent = 'No hay datos para mostrar';
        const tr = document.createElement('tr');
        tr.appendChild(td);
        tbody.replaceChildren(tr);
        return;
    }

    tbody.replaceChildren(...estado.filtered.map((row, index) => {
        const seleccionada = estado.selectedRowIndex === index;
        const paro = !!row._tieneParoActivo;
        const tr = document.createElement('tr');
        tr.className = claseFila(seleccionada, paro, index % 2 === 0);
        tr.dataset.rowIndex = String(index);
        tr.append(...cfg.columnas.map((col, colIdx) => celda(row, col, colIdx, seleccionada && !paro)));
        return tr;
    }));

    updatePinnedPositions();
    updateColumnHeaderIcons();
}

/** Refresca los datos desde la API (sustituto de sockets). */
let refreshEnCurso = false;
async function refreshData(): Promise<void> {
    if (refreshEnCurso) return;
    refreshEnCurso = true;
    try {
        const json = await http.get<{ s?: boolean; items?: FilaAlineacion[]; message?: string }>(cfg.apiUrl);
        if (json?.s && Array.isArray(json.items)) {
            estado.data = json.items;
            estado.selectedRowIndex = null;
            renderTable();
        } else {
            notify.error(json?.message || 'No se pudieron actualizar los datos de alineación.');
        }
    } catch (e) {
        console.warn('Alineación: error al refrescar datos', e);
        // Con respuesta del servidor (4xx/5xx con JSON) se muestra su mensaje, como antes con fetch.
        const mensaje = e instanceof HttpError ? (e.data as { message?: string } | null)?.message : undefined;
        notify.error(e instanceof HttpError && e.status > 0
            ? mensaje || 'No se pudieron actualizar los datos de alineación.'
            : 'No se pudo conectar para actualizar los datos de alineación.');
    } finally {
        refreshEnCurso = false;
    }
}

/* ── Filtro por valores de la columna ─────────────────────────────────── */

function openFilterModal(columnField: string): void {
    const columnLabel = cfg.columnLabels[columnField] || columnField;
    const valores = valoresColumna(estado.filtered, columnField);
    if (valores.length === 0) {
        void notify.alert('No hay valores para filtrar en esta columna.', 'Sin valores', 'info');
        return;
    }
    const actuales = estado.filters.filter((f) => f.column === columnField).map((f) => f.value);

    const opciones = valores.map(([value, count]) => `<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"><div class="flex items-center gap-2">`
        + `<input type="checkbox" class="alineacion-filter-cb w-4 h-4 text-blue-600" value="${escapeHtml(value)}"${actuales.includes(value) ? ' checked' : ''}>`
        + `<span class="text-sm text-gray-700">${escapeHtml(value)}</span></div><span class="text-xs text-gray-500">(${count})</span></label>`).join('');

    const html = `<div class="text-left"><p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>${escapeHtml(columnLabel)}</strong></p>`
        + '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">'
        + '<div class="mb-2 pb-2 border-b border-gray-200"><input type="text" id="alineacionFilterSearch" placeholder="Buscar..." aria-label="Buscar valor" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></div>'
        + `<div id="alineacionFilterCheckboxes" class="space-y-1">${opciones}</div></div></div>`;

    // Formulario con casillas y buscador: se queda en SweetAlert2 (mismo diseño).
    void Swal.fire({
        title: 'Filtrar columna',
        html,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        width: '500px',
        didOpen: (popup) => {
            const search = popup.querySelector<HTMLInputElement>('#alineacionFilterSearch');
            const container = popup.querySelector('#alineacionFilterCheckboxes');
            search?.addEventListener('input', () => {
                const term = search.value.toLowerCase();
                container?.querySelectorAll<HTMLElement>('label').forEach((lab) => {
                    lab.style.display = (lab.textContent || '').toLowerCase().includes(term) ? '' : 'none';
                });
            });
        },
        preConfirm: () => Array.from(document.querySelectorAll<HTMLInputElement>('.alineacion-filter-cb:checked'), (cb) => cb.value),
    }).then((result) => {
        if (!result.isConfirmed) return;
        estado.filters = estado.filters.filter((f) => f.column !== columnField)
            .concat(((result.value as string[] | undefined) ?? []).map((value) => ({ column: columnField, value })));
        renderTable();
    });
}

function openPanelFijar(): void {
    const fijadas = new Set(estado.pinnedColumns);
    const filas = cfg.columnas.map((_, idx) => '<label class="flex items-center gap-2 py-1.5 px-2 hover:bg-gray-50 rounded cursor-pointer alineacion-fijar-row">'
        + `<input type="checkbox" class="alineacion-fijar-cb w-4 h-4 text-amber-600 rounded border-gray-300" data-index="${idx}"${fijadas.has(idx) ? ' checked' : ''}>`
        + `<span class="text-sm text-gray-800">${escapeHtml(etiquetaColumna(idx))}</span></label>`).join('');

    void Swal.fire({
        title: 'Fijar columnas',
        html: '<div class="text-left"><p class="text-sm text-gray-600 mb-3">Marca las columnas que quieres <strong>fijar</strong> (quedan a la izquierda al hacer scroll):</p>'
            + `<div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">${filas}</div></div>`,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        width: '380px',
        preConfirm: () => Array.from(document.querySelectorAll<HTMLInputElement>('.alineacion-fijar-cb:checked'), (cb) => parseInt(cb.dataset.index ?? '', 10))
            .filter((i) => !Number.isNaN(i)),
    }).then((result) => {
        if (!result.isConfirmed) return;
        estado.pinnedColumns = ((result.value as number[] | undefined) ?? []).slice().sort((a, b) => a - b);
        updatePinnedPositions();
        updateColumnHeaderIcons();
    });
}

function alternarFijada(idx: number): void {
    const i = estado.pinnedColumns.indexOf(idx);
    if (i >= 0) estado.pinnedColumns.splice(i, 1);
    else {
        estado.pinnedColumns.push(idx);
        estado.pinnedColumns.sort((a, b) => a - b);
    }
    updatePinnedPositions();
    updateColumnHeaderIcons();
}

/* ── Menú del encabezado ──────────────────────────────────────────────── */

function enlazarMenuEncabezado(): void {
    const menu = document.getElementById('alineacionContextMenuHeader');
    const thead = document.querySelector<HTMLElement>('#mainTable thead');
    if (!menu || !thead) return;
    let menuColumnIndex: number | null = null;
    let menuColumnField: string | null = null;

    const hide = () => {
        menu.classList.add('hidden');
        menu.style.display = 'none';
        menuColumnIndex = null;
        menuColumnField = null;
    };
    const show = (x: number, y: number, columnIndex: number, columnField: string) => {
        menuColumnIndex = columnIndex;
        menuColumnField = columnField;
        const label = document.getElementById('alineacionCtxFijarLabel');
        if (label) label.textContent = estado.pinnedColumns.includes(columnIndex) ? 'Desfijar' : 'Fijar';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.style.display = 'block';
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (x - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (y - rect.height) + 'px';
        menu.classList.remove('hidden');
    };

    // Clic derecho o mantener presionado en tablet (UX-18 2.1).
    thead.classList.add('towell-acciones-zona');
    accionesTactiles(thead, 'th', (th, pos) => {
        const columnIndex = parseInt(th.getAttribute('data-index') ?? '', 10);
        const columnField = th.getAttribute('data-column');
        if (Number.isNaN(columnIndex) || !columnField) return;
        show(pos.x, pos.y, columnIndex, columnField);
    });

    document.addEventListener('click', (e) => {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target as Node)) hide();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });

    document.getElementById('alineacionCtxFiltrar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        const field = menuColumnField;
        hide();
        if (idx != null && field) openFilterModal(field);
    });
    document.getElementById('alineacionCtxFijar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        hide();
        if (idx != null) alternarFijada(idx);
    });
}

onReady(() => {
    const raiz = document.getElementById('alineacion');
    const datos = leerDatos<ConfigAlineacion>(raiz);
    if (!raiz || !datos) return;
    cfg = datos;
    estado.data = datos.items ?? [];

    renderTable();

    document.getElementById('alineacionNavFijar')?.addEventListener('click', openPanelFijar);

    // Clic en un renglón: lo marca (otro clic lo desmarca).
    document.getElementById('alineacion-body')?.addEventListener('click', (e) => {
        const tr = (e.target as Element).closest<HTMLElement>('tr.alineacion-selectable-row');
        const idx = parseInt(tr?.dataset.rowIndex ?? '', 10);
        if (Number.isNaN(idx)) return;
        estado.selectedRowIndex = estado.selectedRowIndex === idx ? null : idx;
        renderTable();
    });

    // Íconos del encabezado: quitar filtro o desfijar.
    document.getElementById('mainTable')?.addEventListener('click', (e) => {
        const btn = (e.target as Element).closest<HTMLElement>('.alineacion-header-icon');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (btn.dataset.action === 'clear-filter' && btn.dataset.column) {
            estado.filters = estado.filters.filter((f) => f.column !== btn.dataset.column);
            renderTable();
        } else if (btn.dataset.action === 'unpin') {
            const idx = parseInt(btn.dataset.index ?? '', 10);
            if (estado.pinnedColumns.includes(idx)) alternarFijada(idx);
        }
    });

    enlazarMenuEncabezado();
    setInterval(() => { void refreshData(); }, REFRESCO_MS);
});
