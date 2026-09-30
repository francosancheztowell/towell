/**
 * Tabla "Programación de telares": filas, chips de filtro rápido e íconos de orden.
 * Sin innerHTML: cada celda se arma con nodos (cuenta y calibre se editan aquí mismo y se
 * guardan tal cual, así que nada de lo que venga del servidor se interpreta como HTML).
 */
import { botonAcciones } from '../../../utils/acciones-tactiles.ts';
import { el, icono } from '../../urdido/comun/pagina.ts';
import { $, $$, cfg, disable, state } from './estado.ts';
import { abrirMenuFila } from './edicion.ts';
import {
    ESTADOS_CHIP,
    enMultiple,
    estadoDe,
    etiquetasOrdenes,
    fechaDataset,
    filtrarLocal,
    filtrosActivos,
    fmt,
    ordenarFilas,
    rowJulios,
    rowMaxJulios,
    rowNoJulio,
    rowNoOrden,
    rowOrdenes,
    rowProgramado,
    rowReservado,
    s,
    salonCorto,
    salonesDe,
    type Estado,
} from './logica.ts';
import type { RawRow, SortRule } from './types.ts';

const CELDA = 'px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center';

const BADGE_ESTADO: Record<Estado, [string, string]> = {
    reservado: ['Reservado', 'bg-red-100 text-red-700'],
    programado: ['Programado', 'bg-amber-100 text-amber-700'],
    libre: ['Libre', 'bg-emerald-100 text-emerald-700'],
};

const td = (clase = '', ...hijos: (Node | string | null | false)[]): HTMLTableCellElement =>
    el('td', { clase: clase ? `${CELDA} ${clase}` : CELDA }, ...hijos);

/** Celda de una barra: el primer valor a la vista; los demás apilados hasta expandir la fila. */
function celdaApilada(valores: string[]): Node {
    const items = valores.map((v) => s(v).trim()).filter(Boolean);
    if (!items.length) return document.createTextNode('-');
    if (items.length === 1) return document.createTextNode(items[0] ?? '');
    return el('span', { clase: 'pu-stack' }, ...items.map((v, i) => el('span', { clase: i === 0 ? '' : 'pu-extra', texto: v })));
}

function celdaEditable(campo: 'cuenta' | 'calibre', texto: string, r: RawRow, telarNo: string, tipoUpper: string): HTMLTableCellElement {
    const celda = td('editable-cell cursor-context-menu', texto);
    celda.dataset.editableField = campo;
    celda.dataset.id = s(r.id);
    celda.dataset.telar = telarNo;
    celda.dataset.tipo = tipoUpper;
    celda.title = 'Clic derecho o mantener presionado para editar';
    return celda;
}

function celdaTipoAtado(tipoAtado: string, telarNo: string, tipoUpper: string): Node {
    if (!cfg.can.modificar) return el('span', { clase: 'text-gray-800 text-xs font-medium', texto: tipoAtado });
    const select = el('select', {
        clase: 'tipo-atado-select w-full bg-white px-2 py-1 text-xs border border-gray-300 rounded-md text-gray-900 focus:ring-2 focus:ring-blue-500',
        attrs: { 'aria-label': `Tipo de atado del telar ${telarNo}` },
    });
    select.dataset.telar = telarNo;
    select.dataset.tipo = tipoUpper;
    for (const v of ['Normal', 'Especial']) {
        const op = el('option', { texto: v, attrs: { value: v } });
        op.selected = tipoAtado === v;
        select.append(op);
    }
    return select;
}

function fila(r: RawRow, idx: number): HTMLTableRowElement {
    const metrosF = parseFloat(s(r.metros, '0')) || 0;
    const noJulio = rowNoJulio(r);
    const noOrden = rowNoOrden(r);
    const hasBoth = metrosF > 0 && noJulio !== '';
    const reservado = rowReservado(r);
    const programado = rowProgramado(r);
    const bloqueado = reservado || programado || noOrden !== '';
    const telarNo = s(r.no_telar);
    const tipoUpper = s(r.tipo).toUpperCase().trim();
    const tipoAtado = s(r.tipo_atado, 'Normal');
    const julios = rowJulios(r);
    const maxJulios = rowMaxJulios(r);
    const isInMultiple = enMultiple(state.selectedTelares, r);

    let baseBg = hasBoth ? 'bg-blue-100' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50';
    let border = hasBoth ? 'border-l-4 border-blue-400' : '';
    if (isInMultiple) {
        baseBg = 'bg-yellow-50';
        border = 'border-l-[3px] border-yellow-500';
    }

    const tr = el('tr', { clase: `selectable-row hover:bg-blue-50 cursor-pointer ${baseBg} ${border}` });
    // Seleccionar es toda la función de la pantalla: también con el teclado.
    tr.tabIndex = 0;
    tr.setAttribute('aria-selected', 'false');
    Object.assign(tr.dataset, {
        id: s(r.id),
        baseBg,
        telar: telarNo,
        tipo: tipoUpper,
        cuenta: s(r.cuenta),
        calibre: s(r.calibre),
        hilo: s(r.hilo).trim(),
        salon: s(r.salon),
        noJulio,
        julios: julios.join(','),
        ordenes: rowOrdenes(r).join(','),
        maxJulios: String(maxJulios),
        noOrden,
        metros: s(r.metros),
        hasBoth: String(hasBoth),
        isReservado: String(reservado),
        isProgramado: String(programado),
        tipoAtado,
    });
    if (r.fecha) tr.dataset.fecha = fechaDataset(r.fecha);
    if (r.turno) tr.dataset.turno = s(r.turno);

    const toggle =
        maxJulios > 1 &&
        el(
            'button',
            { clase: 'pu-toggle', attrs: { type: 'button', 'aria-expanded': 'false', title: 'Ver todos los julios', 'aria-label': `Ver los julios de la barra (${julios.length} de ${maxJulios})` } },
            el('span', { texto: `${julios.length}/${maxJulios}` }),
            icono('fa-solid fa-chevron-down'),
        );

    const [estadoTexto, estadoClase] = BADGE_ESTADO[estadoDe(r)];
    const primera = td('font-bold', telarNo);

    tr.append(
        primera,
        td('', el('span', { clase: `px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.tipo)}`, texto: s(r.tipo, '-') })),
        celdaEditable('cuenta', s(r.cuenta), r, telarNo, tipoUpper),
        celdaEditable('calibre', fmt.num(r.calibre), r, telarNo, tipoUpper),
        td('', fmt.date(r.fecha)),
        td('', s(r.turno)),
        td('', s(r.hilo)),
        td('', fmt.num(r.metros, 0)),
        td('', celdaApilada(julios), toggle),
        td('', celdaApilada(etiquetasOrdenes(r))),
        td('', el('span', { clase: `px-2 py-0.5 rounded text-xs font-semibold ${estadoClase}`, texto: estadoTexto })),
        td('', celdaTipoAtado(tipoAtado, telarNo, tipoUpper)),
        td('', el('span', { clase: `px-2 py-0.5 rounded text-xs font-medium ${fmt.salonBadge(r.salon)}`, texto: s(r.salon, 'Jacquard') })),
    );

    if (cfg.can.crear) {
        const cb = el('input', {
            clase: `telar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 ${bloqueado ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`,
            attrs: { type: 'checkbox', 'aria-label': `Seleccionar telar ${telarNo} para programar` },
        });
        cb.dataset.telar = telarNo;
        cb.dataset.tipo = tipoUpper;
        cb.disabled = bloqueado;
        cb.checked = isInMultiple;
        tr.append(td('', cb));
    }

    // Cuenta y calibre se editan con clic derecho / long-press; el "⋮" es el camino visible (UX-06).
    if (cfg.can.modificar) {
        botonAcciones(tr, (f, pos) => abrirMenuFila(f, pos), { contenedor: primera, etiqueta: `Acciones del telar ${telarNo}` });
    }

    return tr;
}

function filaVacia(texto: string, colspan: number): HTMLTableRowElement {
    return el('tr', {}, el('td', { clase: 'px-4 py-8 text-center text-sm text-gray-500', texto, attrs: { colspan: String(colspan) } }));
}

/* ---------- Íconos de orden ---------- */

export function actualizarIconosOrden(): void {
    $$('#telaresTable .sortable .sort-icon').forEach((i) => (i.className = 'fa-solid fa-sort text-gray-400 sort-icon'));
    $$('#inventarioTable .sortable-inventario .sort-icon-inventario').forEach(
        (i) => (i.className = 'fa-solid fa-sort text-gray-400 sort-icon-inventario'),
    );
    $$('.sort-priority').forEach((m) => m.remove());

    const aplicar = (selector: string, sorts: SortRule[], iconClass: string): void => {
        sorts.forEach((rule, idx) => {
            const th = $(`${selector}[data-column="${rule.column}"]`);
            if (!th) return;
            const i = th.querySelector(`.${iconClass}`);
            if (i) i.className = `fa-solid ${rule.direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down'} text-blue-600 ${iconClass}`;
            th.querySelector('button')?.append(el('span', { clase: 'sort-priority', texto: idx + 1 }));
            th.setAttribute('aria-sort', rule.direction === 'asc' ? 'ascending' : 'descending');
        });
    };

    $$('#telaresTable th[aria-sort], #inventarioTable th[aria-sort]').forEach((th) => th.removeAttribute('aria-sort'));
    aplicar('#telaresTable .sortable', state.sort.telares, 'sort-icon');
    aplicar('#inventarioTable .sortable-inventario', state.sort.inventario, 'sort-icon-inventario');
}

/* ---------- Chips de filtro rápido ---------- */

function pintarChips(): void {
    const box = $('#puChips');
    if (!box) return;
    const map = state.filters.telares;
    const activeSalon = s(map.salon);
    const activeEstado = s(map.estado);

    const chip = (texto: string, titulo: string, pulsado: boolean, dato: Record<string, string>): HTMLButtonElement => {
        const b = el('button', { clase: 'pu-chip', texto, attrs: { type: 'button', title: titulo, 'aria-pressed': String(pulsado) } });
        Object.assign(b.dataset, dato);
        return b;
    };

    box.replaceChildren(
        ...salonesDe(state.telaresDataOriginal).map((sal) =>
            chip(salonCorto(sal), `Filtrar salón ${sal}`, activeSalon === sal, { accionChip: 'salon', valor: sal }),
        ),
        el('span', { clase: 'pu-chip-sep', attrs: { 'aria-hidden': 'true' } }),
        ...ESTADOS_CHIP.map(([v, label]) =>
            chip(label, `Estado: ${label}`, v === '' ? activeEstado === '' : activeEstado === v, { accionChip: 'estado', valor: v }),
        ),
    );
}

/* ---------- Render ---------- */

export function pintarTelares(rows: RawRow[]): void {
    const tbody = $<HTMLTableSectionElement>('#telaresTable tbody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.replaceChildren(filaVacia('No hay datos disponibles', 14));
        state.selectedTelar = null;
        disable($('#btnProgramar'));
        actualizarIconosOrden();
        pintarChips();
        return;
    }

    state.telaresData = rows.slice();
    const activos = filtrosActivos(state.filters.telares);
    const data = ordenarFilas(activos.length ? filtrarLocal(rows, activos) : rows, state.sort.telares);

    const frag = document.createDocumentFragment();
    data.forEach((r, idx) => frag.append(fila(r, idx)));
    tbody.replaceChildren(frag);

    actualizarIconosOrden();
    pintarChips();
}

/** Repinta con los datos originales (tras cambiar filtros u orden). */
export const repintarTelares = (): void => pintarTelares(state.telaresDataOriginal);

export { filaVacia };
