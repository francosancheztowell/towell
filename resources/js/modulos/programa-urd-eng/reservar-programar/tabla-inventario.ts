/**
 * Tabla "Inventario disponible": filas, marca de piezas elegidas, contador de julios de la
 * barra y botón Quitar/Aplicar filtro.
 */
import { el, icono } from '../../urdido/comun/pagina.ts';
import { $, $$, state } from './estado.ts';
import { clonar, filtrarInventarioPorTelar, filtrarLocal, filtrosActivos, fmt, ordenarFilas, s } from './logica.ts';
import type { RawRow } from './types.ts';

const CELDA = 'px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center';
const CLASE_LIBRE = 'hover:bg-orange-50 selectable-row-inventario cursor-pointer';
const CLASE_OCUPADA = 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75';

export const filasInventario = (): HTMLTableRowElement[] => $$<HTMLTableRowElement>('#inventarioTable .selectable-row-inventario');

function fila(r: RawRow): HTMLTableRowElement {
    const noTelarAsignado = s(r.NoTelarId);
    const hasTelar = noTelarAsignado !== '';
    const tr = el('tr', { clase: hasTelar ? CLASE_OCUPADA : CLASE_LIBRE });

    tr.dataset.disabled = String(hasTelar);
    if (!hasTelar) {
        tr.tabIndex = 0;
        tr.setAttribute('aria-selected', 'false');
    }
    Object.assign(tr.dataset, {
        tipo: s(r.Tipo),
        itemId: s(r.ItemId),
        configId: s(r.ConfigId),
        inventSizeId: s(r.InventSizeId),
        inventColorId: s(r.InventColorId),
        inventLocationId: s(r.InventLocationId),
        inventBatchId: s(r.InventBatchId),
        wmsLocationId: s(r.WMSLocationId),
        inventSerialId: s(r.InventSerialId),
        noTelarId: noTelarAsignado,
        metros: s(r.Metros),
        numJulio: s(r.InventSerialId),
    });

    const td = (texto: string | Node, clase = ''): HTMLTableCellElement =>
        el('td', { clase: clase ? `${CELDA} ${clase}` : CELDA }, texto);

    tr.append(
        td(s(r.ItemId)),
        td(el('span', { clase: `px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.Tipo)}`, texto: s(r.Tipo) })),
        td(s(r.ConfigId)),
        td(s(r.InventSizeId)),
        td(s(r.InventColorId)),
        td(s(r.InventBatchId)),
        td(s(r.WMSLocationId)),
        td(s(r.InventSerialId)),
        td(fmt.date(r.ProdDate)),
        td(fmt.num(r.Metros, 0)),
        td(fmt.num(r.InventQty, 0)),
        td(noTelarAsignado, 'font-medium'),
    );
    return tr;
}

function filaVacia(): HTMLTableRowElement {
    return el(
        'tr',
        {},
        el(
            'td',
            { clase: 'px-4 py-8 text-center text-sm text-gray-500', attrs: { colspan: '12' } },
            icono('fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2'),
            ' No hay datos de inventario disponible por el momento',
        ),
    );
}

export function pintarInventario(rows: RawRow[]): void {
    const tbody = $<HTMLTableSectionElement>('#inventarioTable tbody');
    if (!tbody) return;

    if (!state.inventarioDataOriginal.length && rows.length) state.inventarioDataOriginal = clonar(rows);

    let data = rows;
    if (state.mostrarTodoInventario) {
        data = state.inventarioDataOriginal.length ? state.inventarioDataOriginal : data;
    } else if (state.selectedTelar) {
        data = filtrarInventarioPorTelar(data, state.selectedTelar);
    }

    const activos = filtrosActivos(state.filters.inventario);
    if (activos.length) data = filtrarLocal(data, activos);
    data = ordenarFilas(data, state.sort.inventario);
    state.inventarioData = data;

    if (!data.length) {
        tbody.replaceChildren(filaVacia());
        return;
    }

    const frag = document.createDocumentFragment();
    data.forEach((r) => frag.append(fila(r)));
    tbody.replaceChildren(frag);

    actualizarBotonFiltro();
    // El tbody se reconstruye entero: hay que volver a pintar las piezas elegidas.
    repintarSeleccionInventario();
}

/** Repinta con los datos originales (tras cambiar filtros, orden o telar). */
export const repintarInventario = (): void => pintarInventario(state.inventarioDataOriginal);

/** Recarga los datos de la tabla con lo que devolvió el servidor. */
export function reemplazarInventario(rows: RawRow[]): void {
    if (!rows.length) return;
    state.inventarioDataOriginal = clonar(rows);
    pintarInventario(rows);
    actualizarBotonFiltro();
}

export function limpiarVisualInventario(row: HTMLTableRowElement): void {
    row.querySelector('.pu-slot')?.remove();
    row.setAttribute('aria-selected', 'false');
    row.style.removeProperty('background-color');
    row.style.removeProperty('color');
    row.querySelectorAll('td').forEach((c) => {
        c.classList.remove('text-white');
        c.style.removeProperty('color');
    });
    row.className = row.dataset.disabled === 'true' ? CLASE_OCUPADA : CLASE_LIBRE;
}

/** Marca en la tabla las piezas de `state.selectedInventarios` (con su número si son varias). */
export function repintarSeleccionInventario(): void {
    const rows = filasInventario();
    rows.forEach(limpiarVisualInventario);

    const sel = state.selectedInventarios;
    sel.forEach((item, i) => {
        const row = rows.find((r) => r.dataset.inventSerialId === item.inventSerialId);
        if (!row) return;

        row.setAttribute('aria-selected', 'true');
        // green-700: 5.6:1 con texto blanco.
        row.classList.add('is-selected', 'bg-green-700', 'text-white');
        row.style.setProperty('background-color', '#047857', 'important');
        row.style.setProperty('color', '#fff', 'important');
        row.querySelectorAll('td').forEach((c) => {
            c.classList.add('text-white');
            c.style.setProperty('color', '#fff', 'important');
        });

        if (sel.length > 1) {
            row.querySelector('td')?.prepend(
                el('span', {
                    clase: 'pu-slot mr-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-black/25 text-caption font-bold align-middle',
                    texto: i + 1,
                }),
            );
        }
    });

    actualizarContadorJulios();
}

/** Contador de julios de la barra: los ya reservados y los que se van a reservar. */
export function actualizarContadorJulios(): void {
    const box = $('#puJuliosContador');
    const tel = state.selectedTelar;
    const max = tel?.max_julios ?? 1;
    const esBarra = !!tel && max > 1;

    $('#btnSeleccionarLote')?.classList.toggle('hidden', !esBarra);

    if (!box) return;
    if (!esBarra || !tel) {
        box.classList.add('hidden');
        return;
    }
    const pendientes = state.selectedInventarios.length;
    box.classList.remove('hidden');
    box.textContent = `Julios ${tel.julios.length}/${max}${pendientes ? ` (+${pendientes} por reservar)` : ''}`;
}

/** Botón "Quitar Filtro" / "Aplicar Filtro": solo con un telar seleccionado. */
export function actualizarBotonFiltro(): void {
    const btn = $('#btnQuitarFiltroInventario');
    if (!btn) return;

    if (!state.selectedTelar) {
        btn.classList.add('hidden');
        return;
    }
    btn.classList.remove('hidden');
    const i = btn.querySelector('i');
    const texto = btn.querySelector('span');
    const todo = state.mostrarTodoInventario;
    if (i) i.className = todo ? 'fa-solid fa-filter' : 'fa-solid fa-filter-circle-xmark';
    if (texto) texto.textContent = todo ? 'Aplicar Filtro' : 'Quitar Filtro';
    btn.title = todo ? 'Aplicar filtro y mostrar solo registros del telar seleccionado' : 'Quitar filtro y mostrar todos los registros';
}
