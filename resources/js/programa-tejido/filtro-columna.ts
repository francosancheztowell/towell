/**
 * Filtro por columna al estilo AX / Power BI: panel pegado al encabezado (popover nativo),
 * aplica al instante y cada valor muestra cuántos registros quedan con los DEMÁS filtros.
 * Lógica pura arriba; el pintor abajo. El estado de filtros sigue en index.ts.
 */
import { escapeHtml } from '../utils/format.ts';

export interface FilaColumna { valor: string; etiqueta: string; pasaOtros: boolean }
export interface ValorColumna { valor: string; etiqueta: string; total: number; disponibles: number }
export type TipoColumna = 'texto' | 'numero' | 'fecha';
export interface Rango { min: string; max: string }

const comparar = new Intl.Collator('es-MX', { numeric: true, sensitivity: 'base' }).compare;
const RE_FECHA = /^\d{4}-\d{2}-\d{2}/;

/** Agrupa por valor; los que siguen disponibles con los otros filtros van primero (Power BI los atenúa al final). */
export function valoresDeColumna(filas: FilaColumna[]): ValorColumna[] {
    const porValor = new Map<string, ValorColumna>();
    for (const f of filas) {
        const valor = f.valor.trim();
        if (!valor) continue;
        const v = porValor.get(valor) ?? { valor, etiqueta: f.etiqueta.trim() || valor, total: 0, disponibles: 0 };
        v.total++;
        if (f.pasaOtros) v.disponibles++;
        porValor.set(valor, v);
    }
    return [...porValor.values()].sort((a, b) =>
        Number(b.disponibles > 0) - Number(a.disponibles > 0) || comparar(a.etiqueta, b.etiqueta));
}

/** Número o fecha cuando TODOS los valores lo son; entonces el panel ofrece rango. */
export function tipoDeColumna(valores: string[]): TipoColumna {
    const llenos = valores.filter((v) => v.trim() !== '');
    if (!llenos.length) return 'texto';
    if (llenos.every((v) => RE_FECHA.test(v))) return 'fecha';
    if (llenos.every((v) => !Number.isNaN(Number(v.replace(/,/g, ''))))) return 'numero';
    return 'texto';
}

/** 'min..max' del filtro between; null si ningún lado tiene valor. */
export function rangoATexto(r: Rango): string | null {
    const min = r.min.trim();
    const max = r.max.trim();
    return min || max ? `${min}..${max}` : null;
}

export function textoARango(texto: string | undefined): Rango {
    const [min = '', max = ''] = (texto ?? '').split('..');
    return { min, max };
}

export interface OpcionesFiltroColumna {
    ancla: HTMLElement;
    titulo: string;
    tipo: TipoColumna;
    valores: ValorColumna[];
    seleccion: Set<string>;
    rango: Rango;
    /** Llamado en cada cambio: valores marcados o rango (uno excluye al otro). */
    alCambiar: (valores: string[], rango: string | null) => void;
}

let panel: HTMLElement | null = null;

function obtenerPanel(): HTMLElement {
    if (panel) return panel;
    panel = document.createElement('div');
    panel.id = 'ptFiltroColumna';
    panel.setAttribute('popover', 'auto');
    panel.setAttribute('role', 'dialog');
    panel.className = 'pt-filtro-columna inset-auto m-0 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white p-0 text-slate-800 shadow-[0_12px_32px_-8px_rgb(15_23_42/0.35)]';
    document.body.appendChild(panel);
    return panel;
}

/** Debajo del encabezado, sin salirse de la pantalla (tablets de 1006 px y 427 de alto). */
function posicionar(el: HTMLElement, ancla: HTMLElement): void {
    const r = ancla.getBoundingClientRect();
    const ancho = el.offsetWidth || 288;
    const left = Math.min(Math.max(8, r.left), window.innerWidth - ancho - 8);
    const top = Math.min(r.bottom + 4, window.innerHeight - 180);
    el.style.position = 'fixed';
    el.style.left = `${left}px`;
    el.style.top = `${Math.max(8, top)}px`;
    el.style.maxHeight = `${Math.max(180, window.innerHeight - top - 12)}px`;
}

function htmlLista(o: OpcionesFiltroColumna): string {
    return o.valores.map((v) => `
        <label class="flex min-h-touch cursor-pointer items-center gap-3 px-3 text-sm hover:bg-slate-50 ${v.disponibles ? '' : 'text-slate-400'}" data-valor="${escapeHtml(v.valor)}">
            <input type="checkbox" class="size-4 shrink-0 accent-blue-600" value="${escapeHtml(v.valor)}" ${o.seleccion.has(v.valor) ? 'checked' : ''}>
            <span class="min-w-0 flex-1 truncate">${escapeHtml(v.etiqueta)}</span>
            <span class="shrink-0 tabular-nums text-caption ${v.disponibles ? 'text-slate-500' : 'text-slate-400'}" title="${v.disponibles} con los demás filtros de ${v.total}">${v.disponibles}</span>
        </label>`).join('');
}

export function abrirFiltroColumna(o: OpcionesFiltroColumna): HTMLElement {
    const el = obtenerPanel();
    const tipoInput = o.tipo === 'fecha' ? 'date' : 'number';
    const rangoHtml = o.tipo === 'texto' ? '' : `
        <div class="grid grid-cols-2 gap-2 border-b border-slate-100 px-3 py-2">
            <label class="text-caption text-slate-500">Desde
                <input data-rango="min" type="${tipoInput}" value="${escapeHtml(o.rango.min.slice(0, o.tipo === 'fecha' ? 10 : undefined))}" class="mt-1 block min-h-touch w-full rounded-lg border border-slate-300 px-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </label>
            <label class="text-caption text-slate-500">Hasta
                <input data-rango="max" type="${tipoInput}" value="${escapeHtml(o.rango.max.slice(0, o.tipo === 'fecha' ? 10 : undefined))}" class="mt-1 block min-h-touch w-full rounded-lg border border-slate-300 px-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </label>
        </div>`;
    const buscar = o.valores.length > 8 ? `
        <div class="border-b border-slate-100 px-3 py-2">
            <input data-buscar type="search" placeholder="Buscar valor…" aria-label="Buscar valor" class="block min-h-touch w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>` : '';

    el.innerHTML = `
        <div class="flex max-h-[inherit] flex-col">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 py-1 pl-3 pr-1">
                <p class="truncate text-sm font-semibold">${escapeHtml(o.titulo)}</p>
                <button type="button" data-limpiar class="min-h-touch rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50">Limpiar</button>
            </div>
            ${rangoHtml}${buscar}
            <div data-lista class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-1">${htmlLista(o)}</div>
        </div>`;

    const marcados = (): string[] =>
        Array.from(el.querySelectorAll<HTMLInputElement>('[data-lista] input:checked')).map((i) => i.value);
    const leerRango = (): string | null => rangoATexto({
        min: el.querySelector<HTMLInputElement>('[data-rango="min"]')?.value ?? '',
        max: el.querySelector<HTMLInputElement>('[data-rango="max"]')?.value ?? '',
    });

    el.oninput = (e) => {
        const t = e.target as HTMLInputElement;
        if (t.matches('[data-buscar]')) {
            const q = t.value.trim().toLowerCase();
            el.querySelectorAll<HTMLElement>('[data-lista] label').forEach((l) => {
                l.hidden = !!q && !(l.textContent ?? '').toLowerCase().includes(q);
            });
            return;
        }
        if (t.matches('[data-rango]')) {
            el.querySelectorAll<HTMLInputElement>('[data-lista] input').forEach((i) => (i.checked = false));
            o.alCambiar([], leerRango());
            return;
        }
        if (t.type === 'checkbox') {
            el.querySelectorAll<HTMLInputElement>('[data-rango]').forEach((i) => (i.value = ''));
            o.alCambiar(marcados(), null);
        }
    };
    el.onclick = (e) => {
        if (!(e.target as HTMLElement).closest('[data-limpiar]')) return;
        el.querySelectorAll<HTMLInputElement>('input').forEach((i) => (i.type === 'checkbox' ? (i.checked = false) : (i.value = '')));
        o.alCambiar([], null);
    };

    if (!el.matches(':popover-open')) el.showPopover();
    posicionar(el, o.ancla);
    el.querySelector<HTMLElement>('[data-buscar], [data-rango="min"], [data-lista] input')?.focus({ preventScroll: true });
    return el;
}
