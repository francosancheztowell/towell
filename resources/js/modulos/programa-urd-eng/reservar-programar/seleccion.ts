/**
 * Selección: un telar (clic en la fila), varios telares (checkbox, para programar juntos) y las
 * piezas de inventario para la reserva (una en rizo/pie, hasta llenar la barra en Karl Mayer).
 */
import { $, $$, avisar, disable, state } from './estado.ts';
import {
    agregarAMultiple,
    candidatasLote,
    elegirPieza,
    estadoBotones,
    loteDeSeleccion,
    motivoRechazoMultiple,
    quitarDeMultiple,
    s,
    telarDesdeDataset,
} from './logica.ts';
import {
    actualizarBotonFiltro,
    actualizarContadorJulios,
    filasInventario,
    limpiarVisualInventario,
    repintarInventario,
    repintarSeleccionInventario,
} from './tabla-inventario.ts';
import type { SelectedInventario } from './types.ts';

const filaSeleccionada = (): HTMLTableRowElement | null => $<HTMLTableRowElement>('#telaresTable .selectable-row.is-selected');

export function limpiarVisualTelar(row: HTMLTableRowElement | null): void {
    if (!row) return;
    row.style.removeProperty('background-color');
    row.style.removeProperty('color');
    row.style.removeProperty('border-left');
    row.setAttribute('aria-selected', 'false');
    row.querySelectorAll('td').forEach((c) => {
        c.classList.remove('text-white');
        c.style.removeProperty('color');
    });
    const base = row.dataset.baseBg ?? (row.className.includes('bg-gray-50') ? 'bg-gray-50' : 'bg-white');
    row.className = `selectable-row hover:bg-blue-50 cursor-pointer ${row.dataset.hasBoth === 'true' ? 'bg-blue-100 border-l-4 border-blue-400' : base}`;
}

export function actualizarBotones(): void {
    const e = estadoBotones(state.selectedTelar, state.selectedInventarios, state.selectedTelares);
    disable($('#btnProgramar'), e.programar);
    disable($('#btnReservar'), e.reservar);
    disable($('#btnLiberarTelar'), e.liberar);
}

/** Quita toda la selección (telar, piezas y selección múltiple). */
export function limpiarSeleccion(repintar = true): void {
    limpiarVisualTelar(filaSeleccionada());
    filasInventario()
        .filter((r) => r.classList.contains('is-selected'))
        .forEach(limpiarVisualInventario);
    state.selectedTelar = null;
    state.selectedInventarios = [];
    state.selectedTelares = [];
    state.mostrarTodoInventario = false;
    actualizarContadorJulios();

    disable($('#btnProgramar'));
    disable($('#btnReservar'));
    disable($('#btnLiberarTelar'));
    actualizarBotonFiltro();

    if (repintar && state.inventarioDataOriginal.length) repintarInventario();
}

/** Checkbox de selección múltiple. */
export function alternarMultiple(row: HTMLTableRowElement, cb: HTMLInputElement): void {
    // Antes viajaba siempre 'Normal' en la selección múltiple aunque la fila dijera 'Especial'.
    const item = telarDesdeDataset(row.dataset, row.dataset.tipoAtado || 'Normal');

    if (cb.checked) {
        const aviso = motivoRechazoMultiple(item, state.selectedTelares);
        if (aviso) {
            avisar(aviso);
            cb.checked = false;
            return;
        }
        state.selectedTelares = agregarAMultiple(state.selectedTelares, item);
        row.classList.add('bg-yellow-50');
        row.style.setProperty('border-left', '3px solid #eab308', 'important');
    } else {
        state.selectedTelares = quitarDeMultiple(state.selectedTelares, item);
        row.classList.remove('bg-yellow-50');
        row.style.removeProperty('border-left');
    }
    actualizarBotones();
}

/** Clic en una fila de telar: la selecciona (o la suelta si ya lo estaba). */
export function alternarTelar(row: HTMLTableRowElement): void {
    if (row.classList.contains('is-selected')) limpiarSeleccion();
    else seleccionarTelar(row);
}

export function seleccionarTelar(row: HTMLTableRowElement): void {
    const prev = filaSeleccionada();
    if (prev && prev !== row) limpiarVisualTelar(prev);

    row.className = `selectable-row is-selected cursor-pointer ${row.dataset.hasBoth === 'true' ? 'border-l-4 border-blue-300' : ''}`;
    row.setAttribute('aria-selected', 'true');
    // blue-600: con blanco encima da 5.1:1.
    row.classList.add('bg-blue-600', 'text-white');
    row.style.setProperty('background-color', '#2563eb', 'important');
    row.style.setProperty('color', '#fff', 'important');
    row.querySelectorAll('td').forEach((c) => {
        c.classList.add('text-white');
        c.style.setProperty('color', '#fff', 'important');
    });

    const tipoAtado = row.querySelector<HTMLSelectElement>('.tipo-atado-select')?.value ?? (row.dataset.tipoAtado || 'Normal');
    const tel = telarDesdeDataset(row.dataset, tipoAtado);
    state.selectedTelar = tel;

    // Cambiar de telar descarta las piezas elegidas para el anterior.
    state.selectedInventarios = [];
    actualizarContadorJulios();
    actualizarBotones();
    actualizarBotonFiltro();

    if (!state.inventarioDataOriginal.length) return;
    state.mostrarTodoInventario = false;
    repintarInventario();

    // Solo rizo/pie preselecciona su pieza. En una barra estorbaría al elegir los julios que faltan.
    if (tel.no_julio && (tel.max_julios || 1) <= 1) {
        const match = filasInventario().find((r) => r.dataset.inventSerialId === tel.no_julio);
        if (match) elegirFilaInventario(match);
    }
}

/** Pieza de inventario a partir del dataset de su fila. */
function piezaDeFila(row: HTMLTableRowElement): SelectedInventario {
    const d = row.dataset;
    return {
        itemId: s(d.itemId),
        configId: s(d.configId),
        inventSizeId: s(d.inventSizeId),
        inventColorId: s(d.inventColorId),
        inventLocationId: s(d.inventLocationId),
        inventBatchId: s(d.inventBatchId),
        wmsLocationId: s(d.wmsLocationId),
        inventSerialId: s(d.inventSerialId),
        metros: parseFloat(s(d.metros, '0')) || 0,
        numJulio: s(d.numJulio),
        tipo: s(d.tipo),
        data: state.inventarioData.find((i) => s(i.ItemId) === s(d.itemId) && s(i.InventSerialId) === s(d.inventSerialId)),
    };
}

/** Clic en una fila de inventario. */
export function elegirFilaInventario(row: HTMLTableRowElement): void {
    if (row.dataset.disabled === 'true') {
        avisar({ tipo: 'info', titulo: 'Pieza ya reservada' });
        return;
    }
    const r = elegirPieza(state.selectedInventarios, piezaDeFila(row), state.selectedTelar);
    if (!r.ok) {
        avisar(r.aviso);
        return;
    }
    state.selectedInventarios = r.seleccion;
    repintarSeleccionInventario();
    actualizarBotones();
}

/**
 * Marca de golpe los julios libres del mismo lote, hasta llenar la barra.
 * El lote sale de la primera pieza elegida y, si no hay, del No. Orden del telar.
 */
export function seleccionarLote(): void {
    const tel = state.selectedTelar;
    if (!tel || (tel.max_julios || 1) <= 1) return;

    const lote = loteDeSeleccion(tel, state.selectedInventarios);
    if (!lote) {
        avisar({ tipo: 'info', titulo: 'Sin lote', texto: 'Selecciona primero un julio para saber de qué lote' });
        return;
    }
    const huecos = (tel.max_julios || 1) - tel.julios.length;
    if (huecos <= 0) {
        avisar({ tipo: 'info', titulo: 'Barra llena', texto: `La barra ${tel.tipo} ya tiene sus ${tel.max_julios} julios` });
        return;
    }

    const filas = filasInventario().map((row) => ({
        row,
        disabled: row.dataset.disabled === 'true',
        inventBatchId: s(row.dataset.inventBatchId),
        tipo: s(row.dataset.tipo),
    }));
    const candidatas = candidatasLote(filas, lote, tel.tipo, huecos);
    if (!candidatas.length) {
        avisar({ tipo: 'info', titulo: 'Sin julios', texto: `No hay julios libres del lote ${lote}` });
        return;
    }

    state.selectedInventarios = candidatas.map((c) => piezaDeFila(c.row));
    repintarSeleccionInventario();
    actualizarBotones();
    avisar({ tipo: 'success', titulo: `${candidatas.length} julio(s) del lote ${lote}` });
}

/** Busca la fila de un telar (por id o telar + tipo) y la vuelve a seleccionar tras repintar. */
export function reseleccionarTelar(ref: { id: string | null; no_telar: string | null }, tipoUpper: string): void {
    const found = $$<HTMLTableRowElement>('#telaresTable .selectable-row').find((r) =>
        ref.id ? r.dataset.id === ref.id : r.dataset.telar === ref.no_telar && s(r.dataset.tipo).toUpperCase().trim() === tipoUpper,
    );
    if (found) seleccionarTelar(found);
}
