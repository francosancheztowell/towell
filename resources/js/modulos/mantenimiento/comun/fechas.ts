/**
 * Fechas de Mantenimiento sin DOM (tests en tests/Js/mantenimiento-*.test.mjs).
 */

/**
 * dd/mm/aaaa a partir de la fecha que manda el API (`2026-09-29T00:00:00.000000Z`
 * por el cast `date`). Se toma el prefijo tal cual: pasarla por `new Date()` la
 * leía como medianoche UTC y en México (UTC-6) se pintaba el día anterior.
 */
export function fechaCorta(valor: unknown): string {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(valor ?? ''));
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

/** aaaa-mm-dd y hh:mm del reloj local (para inputs date/time). */
export function relojLocal(ahora: Date = new Date()): { fecha: string; hora: string } {
    const dos = (n: number): string => String(n).padStart(2, '0');
    return {
        fecha: `${ahora.getFullYear()}-${dos(ahora.getMonth() + 1)}-${dos(ahora.getDate())}`,
        hora: `${dos(ahora.getHours())}:${dos(ahora.getMinutes())}`,
    };
}

export const MENSAJE_FALTAN_FECHAS = 'Seleccione fecha inicial y final.';
export const MENSAJE_ORDEN_FECHAS = 'La fecha inicial no puede ser mayor que la final.';

/** null si el rango sirve; si no, el mensaje para el usuario. */
export function validarRango(inicio: string, fin: string): string | null {
    const fi = inicio.trim();
    const ff = fin.trim();
    if (!fi || !ff) return MENSAJE_FALTAN_FECHAS;
    if (fi > ff) return MENSAJE_ORDEN_FECHAS;
    return null;
}

/** URL del reporte con el rango en la query. */
export function urlConRango(base: string, inicio: string, fin: string): string {
    const params = new URLSearchParams({ fecha_ini: inicio.trim(), fecha_fin: fin.trim() });
    return base + (base.includes('?') ? '&' : '?') + params.toString();
}
