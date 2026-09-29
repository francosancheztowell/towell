/**
 * Captura de Fórmula: filtro tipo Excel por columna y orden por Fecha.
 * Se abre con clic derecho en el encabezado o, en tablet, con pulsación larga (UX-06).
 * Antes era un Swal con html + preConfirm; ahora el modal x-ui.modal-base #modalFiltroColumna.
 */
import { delegate } from '../../../utils/dom.ts';
import { el, icono } from '../../urdido/comun/pagina.ts';
import { abrirModalBase, cerrarModalBase, porId } from './estado.ts';
import { VACIO, filaPasaFiltros, filtroDeSeleccion, ordenPorFecha, valoresConConteo } from './logica.ts';

const MODAL = 'modalFiltroColumna';
const PULSACION_LARGA_MS = 550;

const filtros = new Map<number, Set<string>>();
const panel = {
    columna: -1,
    valores: [] as [string, number][],
    marcados: new Set<string>(),
};
let ordenFechaAsc: boolean | null = null;

function filas(): HTMLTableRowElement[] {
    return [...document.querySelectorAll<HTMLTableRowElement>('#formulaTableBody tr[data-folio]')];
}

function encabezados(): HTMLTableCellElement[] {
    return [...document.querySelectorAll<HTMLTableCellElement>('#formulaTable thead tr:last-child th')];
}

function textosDeFila(fila: HTMLTableRowElement): string[] {
    return [...fila.cells].map((c) => (c.textContent ?? '').trim());
}

function nodo<T extends HTMLElement = HTMLElement>(clave: string): T {
    return porId(MODAL).querySelector<T>(`[data-filtro="${clave}"]`)!;
}

function busqueda(): string {
    return nodo<HTMLInputElement>('buscar').value.trim().toLowerCase();
}

function visibles(): string[] {
    const s = busqueda();
    const todos = panel.valores.map(([v]) => v);
    return s ? todos.filter((v) => v.toLowerCase().includes(s)) : todos;
}

function actualizarConteo(): void {
    const marcados = nodo('valores').querySelectorAll('input[type="checkbox"]:checked').length;
    nodo('conteo').textContent = `${marcados} de ${panel.valores.length}`;
}

function pintarValores(): void {
    const s = busqueda();
    const lista = s ? panel.valores.filter(([v]) => v.toLowerCase().includes(s)) : panel.valores;
    const contenedor = nodo('valores');
    if (lista.length === 0) {
        contenedor.replaceChildren(el('div', { clase: 'px-3 py-6 text-center text-sm text-gray-400 italic', texto: 'Sin resultados' }));
    } else {
        contenedor.replaceChildren(
            ...lista.map(([valor, cuenta]) => {
                const casilla = el('input', {
                    clase: 'w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 shrink-0',
                    attrs: { type: 'checkbox', value: valor },
                });
                casilla.checked = panel.marcados.has(valor);
                return el(
                    'label',
                    { clase: 'flex items-center gap-2.5 min-h-touch py-2 px-3 hover:bg-blue-50 cursor-pointer rounded text-sm border-b border-gray-100 last:border-b-0' },
                    casilla,
                    el('span', { clase: 'truncate flex-1' + (valor === VACIO ? ' italic text-gray-400' : ''), texto: valor, attrs: { title: valor } }),
                    el('span', { clase: 'text-caption text-gray-400 tabular-nums shrink-0', texto: cuenta }),
                );
            }),
        );
    }
    actualizarConteo();
}

function aplicarFiltros(): void {
    for (const fila of filas()) {
        fila.style.display = filtros.size === 0 || filaPasaFiltros(textosDeFila(fila), filtros) ? '' : 'none';
    }
    for (const th of encabezados()) {
        const activo = filtros.has(Number(th.dataset.colIndex));
        let punto = th.querySelector('.ctx-filter-dot');
        if (activo && !punto) {
            punto = el('span', { clase: 'ctx-filter-dot ml-1 inline-block w-2 h-2 rounded-full bg-yellow-300 align-middle', attrs: { 'aria-label': 'Filtro activo' } });
            th.append(punto);
        } else if (!activo) {
            punto?.remove();
        }
    }
}

function abrirFiltro(th: HTMLTableCellElement): void {
    if (!porId(MODAL).classList.contains('hidden')) return; // ya abierto (p. ej. contextmenu tras pulsación larga)
    const columna = Number(th.dataset.colIndex);
    const nombre = (th.childNodes[0]?.textContent || th.textContent || '').replace(/[▲▼]/g, '').trim();
    panel.columna = columna;
    panel.valores = valoresConConteo(
        filas()
            .map((f) => f.cells[columna])
            .filter((c): c is HTMLTableCellElement => !!c)
            .map((c) => c.textContent ?? ''),
    );
    panel.marcados = new Set(filtros.get(columna) ?? panel.valores.map(([v]) => v));

    const titulo = document.getElementById(`${MODAL}-titulo`);
    titulo?.replaceChildren(icono('fa-solid fa-filter text-blue-500 mr-2'), nombre);
    nodo<HTMLInputElement>('buscar').value = '';
    nodo('quitar-todos').classList.toggle('hidden', filtros.size === 0);
    pintarValores();
    abrirModalBase(MODAL);
}

const acciones: Record<string, () => void> = {
    todos: () => {
        visibles().forEach((v) => panel.marcados.add(v));
        pintarValores();
    },
    ninguno: () => {
        visibles().forEach((v) => panel.marcados.delete(v));
        pintarValores();
    },
    aplicar: () => {
        // Como antes: cuentan las casillas marcadas que se ven (con la búsqueda aplicada).
        const marcados = [...nodo('valores').querySelectorAll<HTMLInputElement>('input[type="checkbox"]:checked')].map((c) => c.value);
        const filtro = filtroDeSeleccion(marcados, panel.valores.length);
        if (filtro) filtros.set(panel.columna, filtro);
        else filtros.delete(panel.columna);
        aplicarFiltros();
        cerrarModalBase(MODAL);
    },
    limpiar: () => {
        filtros.delete(panel.columna);
        aplicarFiltros();
        cerrarModalBase(MODAL);
    },
    'quitar-todos': () => {
        filtros.clear();
        aplicarFiltros();
        cerrarModalBase(MODAL);
    },
};

function ordenarPorFecha(): void {
    ordenFechaAsc = ordenFechaAsc === null ? false : !ordenFechaAsc;
    const tbody = porId('formulaTableBody');
    const lista = filas();
    for (const i of ordenPorFecha(lista.map((f) => f.dataset.fecha ?? ''), ordenFechaAsc)) {
        const fila = lista[i];
        if (fila) tbody.append(fila);
    }
    porId('th-fecha').setAttribute('aria-sort', ordenFechaAsc ? 'ascending' : 'descending');
}

/** Pulsación larga táctil en un encabezado = clic derecho (tablets de planta, UX-06). */
function pulsacionLarga(thead: HTMLElement, alPulsar: (th: HTMLTableCellElement) => void): void {
    let temporizador: number | undefined;
    let inicio: { x: number; y: number } | null = null;
    let suprimirClic = false;
    const cancelar = (): void => {
        window.clearTimeout(temporizador);
        inicio = null;
    };

    thead.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'touch') return;
        const th = (e.target as Element | null)?.closest<HTMLTableCellElement>('th');
        if (!th) return;
        inicio = { x: e.clientX, y: e.clientY };
        temporizador = window.setTimeout(() => {
            suprimirClic = true;
            alPulsar(th);
        }, PULSACION_LARGA_MS);
    });
    thead.addEventListener('pointermove', (e) => {
        if (inicio && Math.hypot(e.clientX - inicio.x, e.clientY - inicio.y) > 10) cancelar();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((tipo) => thead.addEventListener(tipo, cancelar));
    // El clic sintético que sigue a la pulsación larga no debe ordenar por Fecha ni caer en el
    // fondo del modal recién abierto (lo cerraría): se cancela en touchend.
    thead.addEventListener(
        'touchend',
        (e) => {
            if (!suprimirClic) return;
            suprimirClic = false;
            e.preventDefault();
        },
        { passive: false },
    );
}

export function iniciarFiltros(): void {
    const thead = document.querySelector<HTMLElement>('#formulaTable thead');
    if (!thead) return;
    encabezados().forEach((th, i) => {
        th.dataset.colIndex = String(i);
        th.style.cursor = 'context-menu';
    });

    delegate<HTMLTableCellElement, MouseEvent>(thead, 'contextmenu', 'th', (e, th) => {
        e.preventDefault();
        e.stopPropagation();
        abrirFiltro(th);
    });
    pulsacionLarga(thead, abrirFiltro);

    const thFecha = porId('th-fecha');
    thFecha.setAttribute('aria-sort', 'none');
    thFecha.addEventListener('click', ordenarPorFecha);

    const modal = porId(MODAL);
    nodo<HTMLInputElement>('buscar').addEventListener('input', pintarValores);
    delegate<HTMLInputElement>(nodo('valores'), 'change', 'input[type="checkbox"]', (_e, casilla) => {
        if (casilla.checked) panel.marcados.add(casilla.value);
        else panel.marcados.delete(casilla.value);
        actualizarConteo();
    });
    delegate(modal, 'click', '[data-filtro-accion]', (_e, boton) => acciones[boton.dataset.filtroAccion ?? '']?.());
}
