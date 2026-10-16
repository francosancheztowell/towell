/** Piezas de DOM del Kardex. Todo se arma con h(): nada de innerHTML, los datos van como texto. */
import { formatNumber } from '../../utils/format.ts';
import { colorAlmacen, type Tono } from './logica.ts';

type Hijo = Node | string | number | null | undefined | false;
type Atributos = Record<string, string | number | boolean | null | undefined>;

export function h<K extends keyof HTMLElementTagNameMap>(etiqueta: K, atributos: Atributos = {}, ...hijos: Hijo[]): HTMLElementTagNameMap[K] {
    const el = document.createElement(etiqueta);
    for (const [clave, valor] of Object.entries(atributos)) {
        if (valor === null || valor === undefined || valor === false) continue;
        el.setAttribute(clave, valor === true ? '' : String(valor));
    }
    for (const hijo of hijos) {
        if (hijo === null || hijo === undefined || hijo === false) continue;
        el.append(hijo instanceof Node ? hijo : String(hijo));
    }

    return el;
}

/** Hasta 2 decimales (sin rellenar ceros), como el libro; `decimales` los fija. */
export const kg = (v: number | null | undefined, decimales?: number): string =>
    v === null || v === undefined || Number.isNaN(v) ? '—' : formatNumber(v, decimales);
export const kg0 = (v: number | null | undefined): string => kg(v, 0);

/** Clase de color para un número: rojo si es negativo. */
export const claseNumero = (v: number): string => (v < -0.005 ? 'text-red-600' : '');

const TONOS: Record<Tono, string> = {
    ok: 'bg-green-50 text-green-700 ring-green-200',
    warn: 'bg-amber-50 text-amber-700 ring-amber-200',
    serious: 'bg-orange-50 text-orange-700 ring-orange-200',
    crit: 'bg-red-50 text-red-700 ring-red-200',
    na: 'bg-slate-100 text-slate-600 ring-slate-200',
};

export const pastilla = (tono: Tono, texto: string): HTMLSpanElement =>
    h('span', { class: `inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-caption font-semibold uppercase ring-1 ring-inset ${TONOS[tono]}` }, texto);

/** Punto de color + código del almacén. */
export function almacen(id: string, conNombre?: string): HTMLSpanElement {
    return h(
        'span',
        { class: 'inline-flex items-center gap-1.5' },
        h('span', { class: 'size-2.5 shrink-0 rounded-full', style: `background:${colorAlmacen(id)}`, 'aria-hidden': 'true' }),
        h('b', {}, id),
        conNombre ? h('span', { class: 'text-caption text-ink-muted' }, conNombre) : null,
    );
}

export interface Kpi {
    titulo: string;
    valor: string;
    unidad?: string;
    detalle?: string;
}

export function tarjetasKpi(kpis: Kpi[], compacta = false): HTMLDivElement {
    return h(
        'div',
        { class: `grid gap-3 ${compacta ? 'grid-cols-2 md:grid-cols-5' : 'sm:grid-cols-2 xl:grid-cols-4'}` },
        ...kpis.map((k) =>
            h(
                'div',
                { class: `rounded-lg border border-line bg-surface shadow-sm ${compacta ? 'p-3' : 'border-t-4 border-t-primary p-4'}` },
                h('div', { class: 'text-caption font-semibold uppercase tracking-wide text-ink-muted' }, k.titulo),
                h(
                    'div',
                    { class: `mt-1 font-bold text-blue-900 tabular-nums ${compacta ? 'text-lg' : 'text-2xl'}` },
                    k.valor,
                    k.unidad ? h('small', { class: 'ms-1 text-sm font-medium text-ink-muted' }, k.unidad) : null,
                ),
                k.detalle ? h('div', { class: 'mt-1 text-caption text-ink-muted' }, k.detalle) : null,
            ),
        ),
    );
}

export interface Columna<T> {
    titulo: string;
    /** Alineación a la izquierda (texto); por defecto los números van a la derecha. */
    texto?: boolean;
    celda(fila: T, indice: number): Hijo;
    clase?(fila: T): string;
}

export interface OpcionesTabla<T> {
    alClic?(fila: T): void;
    total?: Hijo[];
    /** Altura máxima con scroll y encabezado fijo. */
    alto?: string;
}

/** Tabla con encabezado fijo; si hay alClic, cada renglón es un botón accesible por teclado. */
export function tabla<T>(columnas: Columna<T>[], filas: T[], op: OpcionesTabla<T> = {}): HTMLDivElement {
    const alinear = (c: Columna<T>): string => (c.texto ? 'text-left' : 'text-right');
    const thead = h(
        'thead',
        { class: 'sticky top-0 z-10 bg-slate-100 text-caption uppercase tracking-wide text-slate-600' },
        h('tr', {}, ...columnas.map((c) => h('th', { scope: 'col', class: `whitespace-nowrap px-3 py-2 font-semibold ${alinear(c)}` }, c.titulo))),
    );
    const tbody = h('tbody', { class: 'divide-y divide-slate-100' });
    filas.forEach((fila, i) => {
        const tr = h(
            'tr',
            { class: `hover:bg-blue-50/60 ${op.alClic ? 'cursor-pointer focus:bg-blue-50 focus:outline-none' : ''}`, tabindex: op.alClic ? 0 : null },
            ...columnas.map((c) => h('td', { class: `whitespace-nowrap px-3 py-1.5 tabular-nums ${alinear(c)} ${c.clase?.(fila) ?? ''}` }, c.celda(fila, i))),
        );
        if (op.alClic) {
            const abrir = op.alClic;
            tr.addEventListener('click', () => abrir(fila));
            tr.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    abrir(fila);
                }
            });
        }
        tbody.append(tr);
    });
    if (op.total) {
        tbody.append(
            h(
                'tr',
                { class: 'bg-slate-50 font-bold' },
                ...op.total.map((v, i) => h('td', { class: `whitespace-nowrap px-3 py-2 tabular-nums ${columnas[i]?.texto ? 'text-left' : 'text-right'}` }, v)),
            ),
        );
    }

    return h(
        'div',
        { class: `overflow-auto rounded-lg border border-line bg-surface ${op.alto ?? ''}` },
        h('table', { class: 'min-w-full text-sm text-ink' }, thead, tbody),
    );
}

/** Celda numérica con barra de proporción detrás (la columna principal de cada tabla). */
export function celdaBarra(valor: number, maximo: number, decimales?: number): HTMLSpanElement {
    const ancho = Math.max(2, Math.round((100 * Math.abs(valor)) / Math.max(1, maximo)));
    return h(
        'span',
        { class: 'relative block min-w-28' },
        h('span', { class: 'absolute inset-y-0 right-0 rounded bg-blue-100', style: `width:${ancho}%`, 'aria-hidden': 'true' }),
        h('b', { class: `relative px-1 ${claseNumero(valor)}` }, kg(valor, decimales)),
    );
}

export function titulo(texto: string): HTMLHeadingElement {
    return h('h2', { class: 'mb-3 mt-6 text-sm font-bold uppercase tracking-wide text-blue-900' }, texto);
}

export function nota(tono: 'info' | 'aviso', ...hijos: Hijo[]): HTMLDivElement {
    const clase = tono === 'aviso' ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-primary bg-primary-soft text-slate-700';
    return h('div', { class: `mt-3 rounded-r-lg border-l-4 px-4 py-3 text-sm leading-relaxed ${clase}` }, ...hijos);
}

export function panel(...hijos: Hijo[]): HTMLElement {
    return h('section', { class: 'rounded-xl border border-line bg-surface p-4 shadow-sm md:p-5' }, ...hijos);
}

/** Encabezado de sección con el degradado del color de su almacén. */
export function encabezadoAlmacen(alm: string, ...hijos: Hijo[]): HTMLDivElement {
    const c = colorAlmacen(alm);
    return h('div', { class: 'flex items-center gap-3 px-4 py-2.5 font-bold text-white', style: `background:linear-gradient(120deg, color-mix(in srgb, ${c} 72%, black), ${c})` }, ...hijos);
}

export const chip = (texto: string): HTMLSpanElement =>
    h('span', { class: 'ms-auto rounded-full bg-white/20 px-3 py-0.5 text-caption font-semibold tabular-nums ring-1 ring-white/30' }, texto);

/* ---------- Modal de desglose (x-ui.modal-base #kardex-modal) ---------- */

export interface ContenidoModal {
    titulo: string;
    resumen: Kpi[];
    cuerpo: Node;
    volver?: () => void;
}

export function abrirModal(c: ContenidoModal): void {
    const modal = document.getElementById('kardex-modal');
    const tituloEl = document.getElementById('kardex-modal-titulo');
    const cuerpo = document.getElementById('kardex-modal-cuerpo');
    if (!modal || !tituloEl || !cuerpo) return;
    tituloEl.textContent = c.titulo;
    const volver = c.volver
        ? h('button', { type: 'button', class: 'mb-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline' }, h('i', { class: 'fas fa-arrow-left', 'aria-hidden': 'true' }), 'Volver al desglose')
        : null;
    if (volver && c.volver) volver.addEventListener('click', c.volver);
    const resumen = tarjetasKpi(c.resumen, true);
    const contenido = h('div', { class: 'mt-3' }, c.cuerpo);
    if (volver) cuerpo.replaceChildren(volver, resumen, contenido);
    else cuerpo.replaceChildren(resumen, contenido);
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
