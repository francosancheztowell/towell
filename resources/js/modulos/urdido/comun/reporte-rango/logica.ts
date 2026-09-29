/**
 * Lógica pura del modal "Consultar en rango" de los reportes de Urdido/Engomado (19-01).
 * Sin DOM: la prueban tests/Js/urdeng-reporte-rango.test.mjs.
 */

export interface ValoresRango {
    fechaIni: string;
    fechaFin: string;
    /** undefined cuando el reporte no tiene el filtro (Control Merma). */
    soloFinalizados?: boolean | undefined;
}

export type ResultadoRango =
    | { ok: true }
    | { ok: false; campo: 'fecha_ini' | 'fecha_fin'; mensaje: string };

export const MENSAJE_FALTAN = 'Seleccione fecha inicial y final';
export const MENSAJE_ORDEN = 'La fecha inicial no puede ser mayor que la final';

/** Faltan fechas → el modal se abre solo (mismo criterio que el @if del Blade). */
export function faltanFechas(fechaIni: string | null | undefined, fechaFin: string | null | undefined): boolean {
    return !fechaIni || !fechaFin;
}

/**
 * Mismas reglas que el preConfirm del Swal: ambas fechas y en orden.
 * Los <input type="date"> entregan YYYY-MM-DD, que se compara como texto.
 */
export function validarRango(fechaIni: string, fechaFin: string): ResultadoRango {
    const fi = fechaIni.trim();
    const ff = fechaFin.trim();
    if (!fi) return { ok: false, campo: 'fecha_ini', mensaje: MENSAJE_FALTAN };
    if (!ff) return { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_FALTAN };
    if (fi > ff) return { ok: false, campo: 'fecha_fin', mensaje: MENSAJE_ORDEN };
    return { ok: true };
}

/**
 * URL de la consulta. solo_finalizados va como '1'/'0' explícito: el controller
 * toma '1' por defecto, así que desmarcado tiene que mandar '0'.
 */
export function urlConsulta(ruta: string, v: ValoresRango): string {
    const params = new URLSearchParams({ fecha_ini: v.fechaIni.trim(), fecha_fin: v.fechaFin.trim() });
    if (v.soloFinalizados !== undefined) params.set('solo_finalizados', v.soloFinalizados ? '1' : '0');
    return `${ruta}${ruta.includes('?') ? '&' : '?'}${params.toString()}`;
}
