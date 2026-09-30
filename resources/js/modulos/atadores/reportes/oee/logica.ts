/** Exportar a OEE_ATADORES.xlsx: textos y decisiones sin DOM (tests node). */
import { escapeHtml } from '../../../../utils/format.ts';

export const SONDEO_MS = 3000;
export const LIMITE_MS = 10 * 60 * 1000;

/** Cuerpo del confirm: qué semanas se escriben y cuáles se sobreescriben (escapado). */
export function htmlConfirmacion(semanasRango: readonly unknown[], semanasConDatos: readonly unknown[]): string {
    const lista = (s: readonly unknown[]): string => s.map((x) => escapeHtml(x)).join(', ');
    let html = `<p class="text-sm text-gray-600 mb-3">Se actualizarán las semanas <strong>${lista(semanasRango)}</strong> en <code>OEE_ATADORES.xlsx</code>.</p>`;
    if (semanasConDatos.length > 0) {
        html += `<div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">Las semanas <strong>${lista(semanasConDatos)}</strong> ya tienen datos y serán sobreescritas.</div>`;
    }

    return html;
}

export type EstadoOee = 'seguir' | 'listo' | 'error' | 'agotado';

export function estadoDelSondeo(estado: unknown, transcurridoMs: number): EstadoOee {
    if (estado === 'completado') return 'listo';
    if (estado === 'error') return 'error';

    return transcurridoMs > LIMITE_MS ? 'agotado' : 'seguir';
}
