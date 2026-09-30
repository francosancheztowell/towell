/**
 * Liberar Órdenes — columnas: fijar, ocultar, filtros (texto y tipo Excel) y el menú
 * contextual del encabezado (clic derecho o mantener presionado en tablet).
 *
 * Los modales con casillas y listas siguen en SweetAlert2 (mismo diseño); sus eventos se
 * enlazan en didOpen, sin onclick en el HTML. Todo dato que entra al HTML va escapado.
 */
import { accionesTactiles } from '../../../utils/acciones-tactiles.ts';
import { escapeHtml } from '../../../utils/format.ts';
import { notify } from '../../../utils/notifications.ts';
import { filaPasaFiltros, valoresUnicos, type ColumnaLiberar, type FiltroTexto, type FiltrosColumna } from './logica.ts';

let columnas: ColumnaLiberar[] = [];
let pinnedColumns: number[] = [2, 6, 9, 12];
let hiddenColumns: number[] = [];
/** Filtros del modal "Filtros" (la celda contiene el texto). */
let filtrosTexto: FiltroTexto[] = [];
/** Filtros tipo Excel por columna: valores permitidos. */
let filtrosColumna: FiltrosColumna = {};

const esEditable = (c: ColumnaLiberar) => c.field !== 'select' && c.field !== 'prioridad';
const etiquetaDe = (field: string) => columnas.find((c) => c.field === field)?.label || field;

/** Valor de celda de una fila para una columna (select, input, total calculado o texto). */
export function getCellValueForColumn(row: Element, columnField: string): string {
    const cell = row.querySelector(`td[data-column="${CSS.escape(columnField)}"]`);
    if (!cell) return '';
    const select = cell.querySelector('select');
    if (select) return (select.value || '').trim();
    const input = cell.querySelector('input');
    if (input) return (input.value != null ? String(input.value) : '').trim();
    if (columnField === 'TotalPzas') {
        const span = cell.querySelector('span[data-calculated-value]');
        if (span) return (span.getAttribute('data-calculated-value') || '').trim();
    }
    return (cell.textContent || '').trim();
}

/** Aplica los filtros sin avisar (display:none en las filas que no pasan). */
export function applyFiltersSilent(): void {
    document.querySelectorAll<HTMLElement>('.row-data').forEach((row) => {
        const pasa = filaPasaFiltros((col) => (row.querySelector(`td[data-column="${CSS.escape(col)}"]`) ? getCellValueForColumn(row, col) : null), filtrosTexto, filtrosColumna);
        row.style.display = pasa ? '' : 'none';
    });
}

/* ── Íconos del encabezado (fijado / filtrado) ──────────────────────────── */

function botonBadge(clase: string, color: string, titulo: string, icono: string, dato: Record<string, string>): HTMLButtonElement {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = `${clase} inline-flex items-center justify-center w-5 h-5 rounded ${color} text-white text-xs`;
    b.title = titulo;
    b.setAttribute('aria-label', titulo);
    Object.assign(b.dataset, dato);
    const i = document.createElement('i');
    i.className = icono;
    i.setAttribute('aria-hidden', 'true');
    b.appendChild(i);
    return b;
}

export function updateLiberarHeaderBadges(): void {
    document.querySelectorAll<HTMLElement>('.liberar-header-badges').forEach((span) => {
        const index = parseInt(span.dataset.index ?? '', 10);
        const field = span.dataset.field;
        if (Number.isNaN(index) || !field) return;
        const partes: HTMLElement[] = [];
        if (pinnedColumns.includes(index)) {
            partes.push(botonBadge('liberar-header-badge-pin', 'bg-amber-400/90 hover:bg-amber-500', 'Quitar fijado', 'fas fa-thumbtack', { index: String(index) }));
        }
        if (filtrosColumna[field] != null) {
            partes.push(botonBadge('liberar-header-badge-filter', 'bg-blue-400/90 hover:bg-blue-500', 'Quitar filtro', 'fas fa-filter', { field }));
        }
        span.replaceChildren(...partes);
    });
}

/* ── Fijar y ocultar ──────────────────────────────────────────────────── */

export function updatePinnedColumnsPositions(): void {
    // Limpiar estilos de todas las columnas fijadas
    document.querySelectorAll<HTMLElement>('.pinned-column').forEach((el) => {
        el.style.left = '';
        el.classList.remove('pinned-column');
        if (el.tagName === 'TH') {
            el.style.backgroundColor = '#3b82f6';
            el.style.color = '#fff';
            el.style.zIndex = '10';
            el.style.top = '0';
        } else {
            el.style.backgroundColor = '';
        }
    });

    // thead fijo (igual que req-programa-tejido); las celdas fijadas del tbody van debajo.
    const thead = document.querySelector<HTMLElement>('#mainTable thead');
    if (thead) {
        thead.style.position = 'sticky';
        thead.style.top = '0';
        thead.style.zIndex = '10';
    }
    const theadHeight = thead ? thead.offsetHeight : 0;

    let left = 0;
    pinnedColumns.forEach((idx) => {
        if (hiddenColumns.includes(idx)) return;
        const th = document.querySelector<HTMLElement>(`th.column-${idx}`);
        if (!th || th.style.display === 'none') return;

        const width = th.offsetWidth || th.getBoundingClientRect().width;
        document.querySelectorAll<HTMLElement>(`.column-${idx}`).forEach((el) => {
            el.classList.add('pinned-column');
            el.style.position = 'sticky';
            el.style.left = left + 'px';
            if (el.tagName === 'TH') {
                el.style.backgroundColor = '#f59e0b';
                el.style.color = '#fff';
                el.style.zIndex = '20';
                el.style.top = '0';
            } else {
                el.style.backgroundColor = '#fffbeb';
                el.style.zIndex = '10';
                el.style.top = theadHeight + 'px';
            }
        });
        left += width;
    });
}

function pinColumn(index: number): void {
    if (pinnedColumns.includes(index)) return;
    pinnedColumns.push(index);
    pinnedColumns.sort((a, b) => a - b);
    updatePinnedColumnsPositions();
    updateLiberarHeaderBadges();
}

function unpinColumn(index: number): void {
    pinnedColumns = pinnedColumns.filter((i) => i !== index);
    updatePinnedColumnsPositions();
    updateLiberarHeaderBadges();
}

function hideColumn(index: number): void {
    document.querySelectorAll<HTMLElement>(`.column-${index}`).forEach((el) => { el.style.display = 'none'; });
    if (!hiddenColumns.includes(index)) hiddenColumns.push(index);
    // Si estaba fijada, las demás se recorren.
    if (pinnedColumns.includes(index)) updatePinnedColumnsPositions();
}

function showColumn(index: number): void {
    document.querySelectorAll<HTMLElement>(`.column-${index}`).forEach((el) => { el.style.display = ''; });
    hiddenColumns = hiddenColumns.filter((i) => i !== index);
    updatePinnedColumnsPositions();
}

/** Modal con una casilla por columna (fijar u ocultar); cada cambio se aplica al momento. */
function modalCasillas(titulo: string, texto: string, marcadas: number[], color: 'yellow' | 'red', confirmColor: string, alCambiar: (idx: number, marcada: boolean) => void): void {
    const opciones = columnas.map((col, index) => (!esEditable(col) ? '' : `
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <label for="col-opcion-${index}" class="text-sm font-medium text-gray-700">${escapeHtml(col.label)}</label>
                <input type="checkbox" id="col-opcion-${index}" ${marcadas.includes(index) ? 'checked' : ''}
                    class="w-4 h-4 text-${color}-600 bg-gray-100 border-gray-300 rounded focus:ring-${color}-500"
                    data-column-index="${index}">
            </div>
        `)).filter((html) => html !== '').join('');

    void Swal.fire({
        title: titulo,
        html: `
            <div class="text-left">
                <p class="text-sm text-gray-600 mb-4">${texto}</p>
                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                    ${opciones}
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6b7280',
        width: '500px',
        didOpen: (popup) => {
            popup.addEventListener('change', (e) => {
                const cb = e.target as HTMLInputElement;
                const idx = parseInt(cb.dataset.columnIndex ?? '', 10);
                if (!Number.isNaN(idx)) alCambiar(idx, cb.checked);
            });
        },
    });
}

export function openPinColumnsModal(): void {
    modalCasillas('Fijar Columnas', 'Selecciona las columnas que deseas fijar a la izquierda de la tabla:', pinnedColumns, 'yellow', '#f59e0b',
        (idx, marcada) => (marcada ? pinColumn(idx) : unpinColumn(idx)));
}

export function openHideColumnsModal(): void {
    modalCasillas('Ocultar Columnas', 'Selecciona las columnas que deseas ocultar:', hiddenColumns, 'red', '#ef4444',
        (idx, marcada) => (marcada ? hideColumn(idx) : showColumn(idx)));
}

/* ── Filtros de texto ─────────────────────────────────────────────────── */

function reabrirFiltros(): void {
    applyFiltersSilent();
    Swal.close();
    setTimeout(() => openFiltersModal(), 100);
}

function addCustomFilter(): void {
    const colSelect = document.getElementById('filtro-columna') as HTMLSelectElement | null;
    const valEl = document.getElementById('filtro-valor') as HTMLInputElement | null;
    const column = colSelect?.value;
    const value = valEl?.value?.trim();

    if (!column || !value) {
        notify.warning('Campos incompletos: selecciona una columna e ingresa un valor.');
        return;
    }
    if (filtrosTexto.some((f) => f.column === column && f.value === value)) {
        notify.info('Filtro duplicado: este filtro ya está activo.');
        return;
    }
    filtrosTexto.push({ column, value });
    reabrirFiltros();
}

/** Abre el modal de filtros. Opcional: campo de columna a preseleccionar. */
export function openFiltersModal(preSelectColumnField?: string): void {
    const activos = filtrosTexto.length > 0 ? `
        <div class="space-y-2 pt-3 border-t border-gray-100">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase text-gray-400">Filtros activos</span>
                <span class="inline-flex items-center justify-center rounded-full bg-blue-100 px-1.5 text-xs font-bold text-blue-600">
                    ${filtrosTexto.length}
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                ${filtrosTexto.map((f, i) => `
                        <div class="inline-flex items-center gap-1.5 pl-2 pr-1 py-0.5 bg-blue-50 rounded-full text-xs text-blue-800">
                            <span class="font-medium">${escapeHtml(etiquetaDe(f.column))}:</span>
                            <span class="text-blue-600">${escapeHtml(f.value)}</span>
                            <button type="button" data-accion-filtro="quitar" data-indice="${i}" aria-label="Quitar filtro"
                                    class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-100 text-blue-500 transition-colors">
                                <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    `).join('')}
            </div>
        </div>
    ` : '';

    const html = `
        <div class="w-full max-h-[80vh] overflow-hidden flex flex-col">
            <section class="flex-1 overflow-y-auto bg-white px-5 py-4 space-y-4">
                ${activos}

                <div class="space-y-2 ${filtrosTexto.length > 0 ? 'pt-3 border-t border-gray-100' : ''}">
                    <span class="text-xs font-semibold uppercase text-gray-400">Buscar en columna</span>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select id="filtro-columna" aria-label="Columna"
                                class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                       focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                            <option value="">Selecciona columna...</option>
                            ${columnas.filter(esEditable).map((c) => `<option value="${escapeHtml(c.field)}">${escapeHtml(c.label)}</option>`).join('')}
                        </select>
                        <div id="filtro-valor-container" class="flex-[2]">
                            <input type="text" id="filtro-valor" placeholder="Valor a buscar..." aria-label="Valor a buscar"
                                   class="w-full rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700
                                          focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                        </div>
                        <button type="button" data-accion-filtro="agregar" aria-label="Agregar filtro"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </section>

            <footer class="flex items-center justify-between px-5 py-3 bg-gray-50 border-t border-gray-200">
                <button type="button" data-accion-filtro="limpiar"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Limpiar Todo
                </button>
                <button type="button" data-accion-filtro="cerrar"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cerrar
                </button>
            </footer>
        </div>
    `;

    void Swal.fire({
        title: 'Filtros',
        html,
        width: '580px',
        padding: 0,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'rounded-xl overflow-hidden p-0 shadow-xl', htmlContainer: 'p-0 m-0' },
        backdrop: 'rgba(0,0,0,0.4)',
        didOpen: (popup) => {
            popup.addEventListener('click', (e) => {
                const boton = (e.target as Element).closest<HTMLElement>('[data-accion-filtro]');
                switch (boton?.dataset.accionFiltro) {
                    case 'agregar': addCustomFilter(); break;
                    case 'quitar':
                        filtrosTexto.splice(Number(boton.dataset.indice), 1);
                        reabrirFiltros();
                        break;
                    case 'limpiar':
                        filtrosTexto = [];
                        filtrosColumna = {};
                        updateLiberarHeaderBadges();
                        reabrirFiltros();
                        break;
                    case 'cerrar': Swal.close(); break;
                }
            });
            popup.querySelector('#filtro-valor')?.addEventListener('keypress', (e) => {
                if ((e as KeyboardEvent).key === 'Enter') addCustomFilter();
            });
            setTimeout(() => {
                const colSelect = document.getElementById('filtro-columna') as HTMLSelectElement | null;
                if (preSelectColumnField && colSelect) {
                    colSelect.value = preSelectColumnField;
                    document.getElementById('filtro-valor')?.focus();
                } else {
                    colSelect?.focus();
                }
            }, 50);
        },
    });
}

/* ── Filtro tipo Excel ────────────────────────────────────────────────── */

/** Modal con los valores únicos de la columna y una casilla por valor para elegir qué mostrar. */
function openFilterExcelModal(columnField: string, columnLabel: string): void {
    const uniqueValues = valoresUnicos(Array.from(document.querySelectorAll('.row-data'), (row) => getCellValueForColumn(row, columnField)));
    const actual = filtrosColumna[columnField];
    const selectedSet = actual ? new Set(actual) : null;

    const checkboxesHtml = uniqueValues.map(([val, count], i) => {
        const checked = selectedSet === null ? true : selectedSet.has(val);
        const safe = escapeHtml(val);
        return `
            <label class="flex items-center gap-2 py-1.5 px-2 hover:bg-gray-50 rounded cursor-pointer">
                <input type="checkbox" class="excel-filter-cb w-4 h-4 text-blue-600 rounded border-gray-300" data-value="${safe}" ${checked ? 'checked' : ''} id="excel-filter-${i}">
                <span class="text-sm text-gray-700 truncate flex-1" title="${safe}">${safe}</span>
                <span class="text-xs text-gray-400">(${count})</span>
            </label>`;
    }).join('');

    const html = `
        <div class="w-full max-h-[70vh] flex flex-col">
            <p class="text-sm text-gray-600 mb-2">Mostrar filas donde <strong>${escapeHtml(columnLabel)}</strong> sea uno de:</p>
            <div class="flex gap-2 mb-2">
                <button type="button" id="excel-filter-select-all" class="px-3 py-1.5 text-xs font-medium bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Seleccionar todo</button>
                <button type="button" id="excel-filter-deselect-all" class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Quitar selección</button>
            </div>
            <div class="border border-gray-200 rounded-lg overflow-y-auto flex-1 min-h-0" style="max-height: 320px;">
                ${checkboxesHtml || '<p class="p-3 text-sm text-gray-500">No hay valores en esta columna.</p>'}
            </div>
            <footer class="flex justify-between gap-2 mt-3 pt-3 border-t border-gray-200">
                <button type="button" id="excel-filter-clear" class="px-3 py-2 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Limpiar filtro de columna</button>
                <button type="button" id="excel-filter-apply" class="px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">Aplicar</button>
            </footer>
        </div>`;

    void Swal.fire({
        title: 'Filtrar: ' + (columnLabel || columnField),
        html,
        width: '420px',
        padding: '1rem',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'rounded-xl', htmlContainer: 'p-0 text-left' },
        didOpen: (popup) => {
            const casillas = () => popup.querySelectorAll<HTMLInputElement>('.excel-filter-cb');
            popup.querySelector('#excel-filter-select-all')?.addEventListener('click', () => casillas().forEach((cb) => { cb.checked = true; }));
            popup.querySelector('#excel-filter-deselect-all')?.addEventListener('click', () => casillas().forEach((cb) => { cb.checked = false; }));
            popup.querySelector('#excel-filter-apply')?.addEventListener('click', () => {
                const selected = Array.from(casillas()).filter((cb) => cb.checked).map((cb) => cb.dataset.value ?? '');
                if (selected.length === uniqueValues.length) delete filtrosColumna[columnField];
                else filtrosColumna[columnField] = selected;
                applyFiltersSilent();
                updateLiberarHeaderBadges();
                Swal.close();
                notify.success('Filtro aplicado');
            });
            popup.querySelector('#excel-filter-clear')?.addEventListener('click', () => {
                delete filtrosColumna[columnField];
                applyFiltersSilent();
                updateLiberarHeaderBadges();
                Swal.close();
                notify.info('Filtro de columna quitado');
            });
        },
    });
}

/* ── Menú contextual del encabezado ───────────────────────────────────── */

function initContextMenuHeader(): void {
    const menu = document.getElementById('liberar-context-menu-header');
    const thead = document.querySelector<HTMLElement>('#mainTable thead');
    if (!menu) return;

    let menuColumnIndex: number | null = null;
    let menuColumnField: string | null = null;

    const hide = () => {
        menu.classList.add('hidden');
        menuColumnIndex = null;
        menuColumnField = null;
    };
    const show = (x: number, y: number, columnIndex: number, columnField: string) => {
        menuColumnIndex = columnIndex;
        menuColumnField = columnField;
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.classList.remove('hidden');
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (x - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (y - rect.height) + 'px';
    };

    document.addEventListener('click', (e) => {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target as Node)) hide();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.classList.contains('hidden')) hide();
    });

    if (thead) {
        // Clic derecho o mantener presionado en tablet (UX-06).
        thead.classList.add('towell-acciones-zona');
        accionesTactiles(thead, 'th', (th, pos) => {
            let columnIndex = parseInt(th.dataset.index ?? '', 10);
            if (Number.isNaN(columnIndex)) {
                const m = th.className.match(/column-(\d+)/);
                if (m) columnIndex = parseInt(m[1] ?? '', 10);
            }
            const columnField = th.dataset.field || th.getAttribute('data-field');
            if (Number.isNaN(columnIndex) || columnField == null) return;
            show(pos.x, pos.y, columnIndex, columnField);
        });
    }

    document.getElementById('liberar-context-filtrar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        const field = menuColumnField;
        hide();
        if (idx != null && idx >= 0 && field) openFilterExcelModal(field, etiquetaDe(field));
    });
    document.getElementById('liberar-context-fijar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        hide();
        if (idx == null || idx < 0) return;
        if (pinnedColumns.includes(idx)) unpinColumn(idx);
        else pinColumn(idx);
        notify.info('Columna fijada/desfijada');
    });
    document.getElementById('liberar-context-ocultar')?.addEventListener('click', () => {
        const idx = menuColumnIndex;
        hide();
        if (idx == null || idx < 0) return;
        hideColumn(idx);
        notify.info('Columna oculta');
    });
}

/* ── Arranque ─────────────────────────────────────────────────────────── */

export function iniciarColumnas(cols: ColumnaLiberar[]): void {
    columnas = cols;
    initContextMenuHeader();

    // Íconos del encabezado: clic quita el fijado o el filtro de esa columna.
    updateLiberarHeaderBadges();
    document.querySelector('#mainTable thead')?.addEventListener('click', (e) => {
        const pinBtn = (e.target as Element).closest<HTMLElement>('.liberar-header-badge-pin');
        const filterBtn = (e.target as Element).closest<HTMLElement>('.liberar-header-badge-filter');
        if (pinBtn) {
            e.preventDefault();
            e.stopPropagation();
            const idx = parseInt(pinBtn.dataset.index ?? '', 10);
            if (!Number.isNaN(idx)) {
                unpinColumn(idx);
                updateLiberarHeaderBadges();
                updatePinnedColumnsPositions();
            }
        }
        if (filterBtn) {
            e.preventDefault();
            e.stopPropagation();
            const field = filterBtn.dataset.field;
            if (field) {
                delete filtrosColumna[field];
                applyFiltersSilent();
                updateLiberarHeaderBadges();
            }
        }
    });

    // Posiciones de las columnas fijadas cuando la tabla ya se pintó, y al cambiar el tamaño.
    setTimeout(updatePinnedColumnsPositions, 100);
    window.addEventListener('resize', updatePinnedColumnsPositions);
}
