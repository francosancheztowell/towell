/**
 * Programa Urd-Eng › Reservar y programar (19-05 p1.1).
 * Vista: resources/views/modulos/programa_urd_eng/reservar-programar.blade.php (datos en
 * data-pagina de #pu-pagina). Este archivo solo cablea; la lógica pura vive en logica.ts.
 *
 *  estado.ts          configuración, estado y avisos
 *  tabla-telares.ts   filas de telares, chips, íconos de orden
 *  tabla-inventario.ts filas de inventario y marca de piezas elegidas
 *  seleccion.ts       telar / selección múltiple / piezas / lote
 *  filtros.ts         menú de encabezado, modal de filtro, chips, orden
 *  edicion.ts         cuenta y calibre en la celda, tipo de atado, menú "⋮" de fila
 *  reservas.ts        programar, reservar, liberar
 */
import { accionesTactiles } from '../../../utils/acciones-tactiles.ts';
import { delegate, onReady } from '../../../utils/dom.ts';
import { loader } from '../../../componentes/loader.ts';
import { $, cargarConfig, disable, state } from './estado.ts';
import { accionMenuFila, cambiarTipoAtado, cerrarMenuFila, editarCelda } from './edicion.ts';
import { chipEstado, chipSalon, enlazarFiltros, restablecerFiltros } from './filtros.ts';
import { cargarInventario, liberarTelar, programar, reservar } from './reservas.ts';
import { actualizarBotones, alternarMultiple, alternarTelar, elegirFilaInventario, seleccionarLote } from './seleccion.ts';
import { actualizarBotonFiltro, pintarInventario, repintarInventario } from './tabla-inventario.ts';
import { pintarTelares } from './tabla-telares.ts';
import { clonar } from './logica.ts';

/** Botones de la barra y del encabezado de inventario (data-accion en el Blade). */
const acciones: Record<string, () => void> = {
    programar,
    reservar: () => void reservar(),
    liberar: () => void liberarTelar(),
    'seleccionar-lote': seleccionarLote,
    'recargar-telares': restablecerFiltros,
    'alternar-filtro-inventario': () => {
        if (!state.selectedTelar) return;
        state.mostrarTodoInventario = !state.mostrarTodoInventario;
        repintarInventario();
        actualizarBotonFiltro();
    },
};

function enlazarTelares(tbody: HTMLElement): void {
    tbody.classList.add('towell-acciones-zona');

    // Cuenta y calibre: clic derecho o mantener presionado sobre la celda la edita.
    accionesTactiles(tbody, '.editable-cell', (td) => {
        cerrarMenuFila();
        editarCelda(td as HTMLTableCellElement);
    });

    delegate<HTMLSelectElement>(tbody, 'change', '.tipo-atado-select', (_e, sel) => void cambiarTipoAtado(sel));
    delegate<HTMLInputElement>(tbody, 'change', '.telar-checkbox', (e, cb) => {
        e.stopPropagation();
        const row = cb.closest<HTMLTableRowElement>('.selectable-row');
        if (cb.disabled) cb.checked = false;
        else if (row) alternarMultiple(row, cb);
    });

    tbody.addEventListener('click', (e) => {
        const target = e.target as HTMLElement | null;
        if (!target) return;

        // Barra de Karl Mayer: expandir / contraer sus julios (en el dataset: seleccionar reescribe className).
        const toggle = target.closest<HTMLElement>('.pu-toggle');
        if (toggle) {
            e.preventDefault();
            e.stopPropagation();
            const tr = toggle.closest('tr');
            if (tr) {
                tr.dataset.expandido = tr.dataset.expandido === '1' ? '' : '1';
                toggle.setAttribute('aria-expanded', String(tr.dataset.expandido === '1'));
            }
            return;
        }
        if (target.closest('button,a,input,select')) return;

        const row = target.closest<HTMLTableRowElement>('.selectable-row');
        if (!row) return;
        e.preventDefault();
        alternarTelar(row);
    });

    tbody.addEventListener('keydown', (e) => teclaSelecciona(e, '.selectable-row', alternarTelar));
}

function enlazarInventario(tbody: HTMLElement): void {
    tbody.addEventListener('click', (e) => {
        const target = e.target as HTMLElement | null;
        if (!target || target.closest('button,a')) return;
        const row = target.closest<HTMLTableRowElement>('.selectable-row-inventario');
        if (!row) return;
        e.preventDefault();
        elegirFilaInventario(row);
    });
    tbody.addEventListener('keydown', (e) => teclaSelecciona(e, '.selectable-row-inventario', elegirFilaInventario));
}

/** Enter o Espacio sobre la fila enfocada equivale al clic (la pantalla se opera sin ratón). */
function teclaSelecciona(e: KeyboardEvent, selector: string, accion: (row: HTMLTableRowElement) => void): void {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const target = e.target as HTMLElement | null;
    // Un checkbox, el select de atado o un input de edición se quedan con su propia tecla.
    if (!target || target.closest('input,select,button,a')) return;
    const row = target.closest<HTMLTableRowElement>(selector);
    if (!row) return;
    e.preventDefault();
    accion(row);
}

async function cargarInventarioInicial(): Promise<void> {
    loader.show();
    try {
        const rows = await cargarInventario();
        state.inventarioDataOriginal = clonar(rows);
        pintarInventario(rows);
        actualizarBotones();
        actualizarBotonFiltro();
    } catch {
        // Sin inventario la pantalla sigue sirviendo para programar: tabla vacía y sin Reservar.
        pintarInventario([]);
        disable($('#btnReservar'));
    } finally {
        loader.hide();
    }
}

onReady(() => {
    const raiz = document.getElementById('pu-pagina');
    if (!cargarConfig(raiz)) return;

    enlazarFiltros();
    pintarTelares(state.telaresData);

    const tbodyTelares = $('#telaresTable tbody');
    if (tbodyTelares) enlazarTelares(tbodyTelares);
    const tbodyInventario = $('#inventarioTable tbody');
    if (tbodyInventario) enlazarInventario(tbodyInventario);

    // Botones del navbar (fuera de #pu-pagina) y del contenido: un delegate por documento.
    delegate(document, 'click', '[data-accion]', (_e, btn) => {
        if ((btn as HTMLButtonElement).disabled) return;
        acciones[btn.dataset.accion ?? '']?.();
    });
    delegate(document, 'click', '[data-accion-chip]', (_e, btn) => {
        const valor = btn.dataset.valor ?? '';
        if (btn.dataset.accionChip === 'salon') chipSalon(valor);
        else chipEstado(valor);
    });

    const menuFila = $('#puMenuFila');
    if (menuFila) delegate(menuFila, 'click', '[data-action]', (_e, btn) => accionMenuFila(btn.dataset.action ?? ''));
    document.addEventListener('click', (e) => {
        if (!(e.target as Element | null)?.closest?.('#puMenuFila')) cerrarMenuFila();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarMenuFila();
    });

    void cargarInventarioInicial();
});
