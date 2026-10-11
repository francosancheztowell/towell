/**
 * Barra de filtros activos de Programa Tejido: chips quitables, "N de M registros" y
 * persistencia en la URL (?filtros=) para que recargar, volver o compartir el enlace
 * conserve lo filtrado. Lógica pura + un pintor; el estado sigue viviendo en index.ts.
 */
import { escapeHtml } from '../utils/format.ts';

export interface FiltroColumna { column: string; operator: string; value: string }
export type RangoFecha = { desde: string | null; hasta: string | null };
export type CampoFecha = 'fechaInicio' | 'fechaFinal';

export interface EstadoFiltros {
    filters: FiltroColumna[];
    quick: Record<string, boolean>;
    fechas: Record<CampoFecha, RangoFecha>;
}

export interface Chip { tipo: 'columna' | 'rapido' | 'fecha'; clave: string; etiqueta: string; valor: string }

const ETIQUETA_FECHA: Record<CampoFecha, string> = { fechaInicio: 'F. inicio', fechaFinal: 'F. final' };
const PARAM = 'filtros';

const fechaCorta = (iso: string | null): string => {
    if (!iso) return '…';
    const [y, m, d] = iso.split('-');
    return d && m ? `${d}/${m}/${y?.slice(2)}` : iso;
};

/** Un chip por columna (sus valores unidos con coma), uno por filtro rápido y uno por rango. */
export function chipsDe(
    estado: EstadoFiltros,
    etiquetaColumna: (campo: string) => string,
    etiquetaRapido: (clave: string) => string,
): Chip[] {
    const porColumna = new Map<string, string[]>();
    for (const f of estado.filters) {
        const lista = porColumna.get(f.column) ?? [];
        lista.push(f.operator === 'empty' ? '(vacío)' : f.operator === 'notEmpty' ? '(con valor)' : f.value);
        porColumna.set(f.column, lista);
    }
    const chips: Chip[] = [...porColumna].map(([col, valores]) => ({
        tipo: 'columna', clave: col, etiqueta: etiquetaColumna(col), valor: valores.join(', '),
    }));
    for (const [clave, activo] of Object.entries(estado.quick)) {
        if (activo) chips.push({ tipo: 'rapido', clave, etiqueta: etiquetaRapido(clave), valor: '' });
    }
    for (const campo of Object.keys(ETIQUETA_FECHA) as CampoFecha[]) {
        const r = estado.fechas[campo];
        if (r?.desde || r?.hasta) {
            chips.push({ tipo: 'fecha', clave: campo, etiqueta: ETIQUETA_FECHA[campo], valor: `${fechaCorta(r.desde)} – ${fechaCorta(r.hasta)}` });
        }
    }
    return chips;
}

/** Texto compacto para la URL; '' cuando no hay nada filtrado. */
export function serializar(estado: EstadoFiltros): string {
    const c = estado.filters.map((f) => [f.column, f.operator, f.value]);
    const q = Object.keys(estado.quick).filter((k) => estado.quick[k]);
    const f: Record<string, [string | null, string | null]> = {};
    for (const campo of Object.keys(ETIQUETA_FECHA) as CampoFecha[]) {
        const r = estado.fechas[campo];
        if (r?.desde || r?.hasta) f[campo] = [r.desde, r.hasta];
    }
    if (!c.length && !q.length && !Object.keys(f).length) return '';
    return JSON.stringify({ c, q, f });
}

/** Inverso de serializar. Lo que no tenga la forma esperada se descarta (la URL la puede editar cualquiera). */
export function deserializar(texto: string | null, rapidosValidos: string[]): EstadoFiltros | null {
    if (!texto) return null;
    let crudo: unknown;
    try { crudo = JSON.parse(texto); } catch { return null; }
    if (!crudo || typeof crudo !== 'object') return null;
    const { c, q, f } = crudo as { c?: unknown; q?: unknown; f?: unknown };
    const esTexto = (v: unknown): v is string => typeof v === 'string';
    const fechaValida = (v: unknown): string | null => (esTexto(v) && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : null);

    const filters = (Array.isArray(c) ? c : [])
        .filter((t): t is [string, string, string] => Array.isArray(t) && t.length === 3 && t.every(esTexto))
        .map(([column, operator, value]) => ({ column, operator, value }));
    const quick: Record<string, boolean> = Object.fromEntries(rapidosValidos.map((k) => [k, Array.isArray(q) && q.includes(k)]));
    const fechas: Record<CampoFecha, RangoFecha> = { fechaInicio: { desde: null, hasta: null }, fechaFinal: { desde: null, hasta: null } };
    if (f && typeof f === 'object') {
        for (const campo of Object.keys(fechas) as CampoFecha[]) {
            const par = (f as Record<string, unknown>)[campo];
            if (Array.isArray(par)) fechas[campo] = { desde: fechaValida(par[0]), hasta: fechaValida(par[1]) };
        }
    }
    return { filters, quick, fechas };
}

/**
 * Enlaces viejos (?salon=&telar=) que mandan los respaldos de index.ts: hasta ahora nadie
 * los leía y el usuario caía en la grilla sin filtrar.
 */
export function filtrosDeParametrosViejos(params: URLSearchParams): FiltroColumna[] {
    const out: FiltroColumna[] = [];
    const salon = params.get('salon')?.trim();
    const telar = params.get('telar')?.trim();
    if (salon) out.push({ column: 'SalonTejidoId', operator: 'equals', value: salon });
    if (telar) out.push({ column: 'NoTelarId', operator: 'equals', value: telar });
    return out;
}

/** Escribe el estado en la URL sin recargar ni ensuciar el historial. */
export function guardarEnUrl(estado: EstadoFiltros, win: Pick<Window, 'location' | 'history'> = window): void {
    const url = new URL(win.location.href);
    const texto = serializar(estado);
    if (texto) url.searchParams.set(PARAM, texto); else url.searchParams.delete(PARAM);
    url.searchParams.delete('salon');
    url.searchParams.delete('telar');
    if (url.href !== win.location.href) win.history.replaceState(win.history.state, '', url);
}

export function leerDeUrl(rapidosValidos: string[], search: string = window.location.search): EstadoFiltros | null {
    const params = new URLSearchParams(search);
    const estado = deserializar(params.get(PARAM), rapidosValidos);
    const viejos = filtrosDeParametrosViejos(params);
    if (!viejos.length) return estado;
    const base = estado ?? deserializar('{}', rapidosValidos)!;
    return { ...base, filters: [...base.filters, ...viejos] };
}

/** Pinta la barra; oculta cuando no hay filtros, para no robar alto en las tablets de 427 px. */
export function pintarBarra(el: HTMLElement, chips: Chip[], visibles: number, total: number): void {
    el.hidden = chips.length === 0;
    if (el.hidden) { el.innerHTML = ''; return; }
    const chipHtml = chips.map((c) => `
        <button type="button" data-quitar-tipo="${c.tipo}" data-quitar-clave="${escapeHtml(c.clave)}"
                class="pt-chip group relative inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full bg-blue-50 pl-3 pr-2 text-caption text-blue-900 ring-1 ring-inset ring-blue-200 transition-colors hover:bg-blue-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 before:absolute before:-inset-y-1.5 before:inset-x-0 before:content-['']"
                aria-label="Quitar filtro ${escapeHtml(c.etiqueta)}${c.valor ? ' ' + escapeHtml(c.valor) : ''}">
            <span class="font-semibold">${escapeHtml(c.etiqueta)}</span>${c.valor ? `<span class="max-w-56 truncate text-blue-700">${escapeHtml(c.valor)}</span>` : ''}
            <i class="fa-solid fa-xmark text-blue-500 group-hover:text-blue-800" aria-hidden="true"></i>
        </button>`).join('');
    el.innerHTML = `
        <p class="shrink-0 text-sm text-slate-600 tabular-nums" aria-live="polite">
            <span class="font-semibold text-slate-900">${visibles}</span> de ${total} registros
        </p>
        <div class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto py-1.5">${chipHtml}</div>
        <button type="button" data-quitar-tipo="todo"
                class="min-h-touch shrink-0 rounded-lg px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-blue-600">
            Limpiar todo
        </button>`;
}
