/**
 * Filtros y orden de las dos tablas: menú de encabezado (clic derecho o mantener presionado →
 * Filtrar columna / Quitar filtro / Quitar filtros de tabla), modal del valor, chips de salón y
 * estado, y orden por columna (Shift/Ctrl/Cmd acumula).
 */
import { accionesTactiles } from '../../../utils/acciones-tactiles.ts';
import type { PosicionAcciones } from '../../../utils/acciones-tactiles.ts';
import { delegate } from '../../../utils/dom.ts';
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { $, avisar, state } from './estado.ts';
import { alternarEstado, alternarSalon, s, siguienteOrden } from './logica.ts';
import { repintarInventario } from './tabla-inventario.ts';
import { repintarTelares } from './tabla-telares.ts';
import type { TableKey } from './types.ts';

const MODAL = 'puModalFiltro';
const COLUMNAS_SI_NO = ['reservado', 'programado'];

let destino: { table: TableKey; column: string } | null = null;

const repintar = (table: TableKey): void => (table === 'telares' ? repintarTelares() : repintarInventario());

/* ---------- Filtros por columna ---------- */

export function aplicarFiltroColumna(table: TableKey, column: string, value: string): void {
    const next = s(value).trim();
    const map = state.filters[table];
    if (next) map[column] = next;
    else delete map[column];
    repintar(table);
}

export function quitarFiltroColumna(table: TableKey, column: string): void {
    delete state.filters[table][column];
    repintar(table);
}

export function quitarFiltrosTabla(table: TableKey): void {
    state.filters[table] = {};
    repintar(table);
}

/** "Recargar" del estado vacío: quita todos los filtros y repinta. */
export function restablecerFiltros(): void {
    state.filters = { telares: {}, inventario: {} };
    if (state.telaresDataOriginal.length) repintarTelares();
    if (state.inventarioDataOriginal.length) repintarInventario();
    avisar({ tipo: 'success', titulo: 'Filtros restablecidos' });
}

/* ---------- Chips ---------- */

export function chipSalon(v: string): void {
    alternarSalon(state.filters.telares, v);
    repintarTelares();
}

export function chipEstado(v: string): void {
    alternarEstado(state.filters.telares, v);
    repintarTelares();
}

/* ---------- Menú de encabezado ---------- */

const menu = (): HTMLElement | null => $('#tableContextMenu');

export const cerrarMenuColumna = (): void => menu()?.classList.add('hidden');

function abrirMenuColumna(table: TableKey, th: HTMLElement, pos: PosicionAcciones): void {
    const m = menu();
    const column = th.dataset.column;
    if (!m || !column) return;
    destino = { table, column };
    m.classList.remove('hidden');
    // position: fixed → coordenadas de viewport (las que da accionesTactiles).
    m.style.left = `${pos.x}px`;
    m.style.top = `${pos.y}px`;
    m.querySelector<HTMLButtonElement>('button')?.focus();
}

function abrirModalFiltro(table: TableKey, column: string): void {
    const actual = state.filters[table][column] ?? '';
    const siNo = COLUMNAS_SI_NO.includes(column);
    const texto = $<HTMLInputElement>('#puFiltroValor');
    const opcion = $<HTMLSelectElement>('#puFiltroSiNo');
    if (!texto || !opcion) return;

    texto.closest<HTMLElement>('[data-pu-campo]')?.classList.toggle('hidden', siNo);
    opcion.closest<HTMLElement>('[data-pu-campo]')?.classList.toggle('hidden', !siNo);
    texto.value = siNo ? '' : actual;
    opcion.value = siNo ? actual || '1' : '1';
    const etiqueta = $(`#telaresTable th[data-column="${column}"] span, #inventarioTable th[data-column="${column}"] span`);
    const nombre = $('#puFiltroColumna');
    if (nombre) nombre.textContent = etiqueta?.textContent?.trim() || column;

    abrir(MODAL);
    (siNo ? opcion : texto).focus();
    if (!siNo) texto.select();
}

function aplicarDesdeModal(): void {
    if (!destino) return;
    const siNo = COLUMNAS_SI_NO.includes(destino.column);
    const valor = siNo ? ($<HTMLSelectElement>('#puFiltroSiNo')?.value ?? '') : ($<HTMLInputElement>('#puFiltroValor')?.value ?? '');
    cerrarPorId(MODAL);
    aplicarFiltroColumna(destino.table, destino.column, valor);
}

const accionesMenu: Record<string, (t: TableKey, c: string) => void> = {
    'filter-column': abrirModalFiltro,
    'clear-column-filter': quitarFiltroColumna,
    'clear-table-filters': quitarFiltrosTabla,
};

/* ---------- Orden ---------- */

function alternarOrden(table: TableKey, col: string, additive: boolean): void {
    state.sort[table] = siguienteOrden(state.sort[table], col, additive);
    repintar(table);
}

/* ---------- Cableado ---------- */

export function enlazarFiltros(): void {
    const encabezados: Array<[TableKey, string, string]> = [
        ['telares', '#telaresTable thead', '.sortable'],
        ['inventario', '#inventarioTable thead', '.sortable-inventario'],
    ];

    for (const [table, sel, celda] of encabezados) {
        const thead = $(sel);
        if (!thead) continue;
        thead.classList.add('towell-acciones-zona');
        // Clic derecho o mantener presionado sobre el encabezado: menú de filtros.
        accionesTactiles(thead, celda, (th, pos) => abrirMenuColumna(table, th, pos));
        delegate<HTMLElement, MouseEvent>(thead, 'click', celda, (e, th) => {
            const col = th.dataset.column;
            if (col) alternarOrden(table, col, e.shiftKey || e.ctrlKey || e.metaKey);
        });
    }

    const m = menu();
    if (m) {
        delegate(m, 'click', '[data-action]', (_e, btn) => {
            const accion = accionesMenu[btn.dataset.action ?? ''];
            cerrarMenuColumna();
            if (accion && destino) accion(destino.table, destino.column);
        });
    }
    document.addEventListener('click', (e) => {
        if (!(e.target as Element | null)?.closest?.('#tableContextMenu')) cerrarMenuColumna();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarMenuColumna();
    });

    $('#puFormFiltro')?.addEventListener('submit', (e) => {
        e.preventDefault();
        aplicarDesdeModal();
    });
}
