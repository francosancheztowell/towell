/**
 * Reglas del cierre de paro sin DOM (tests en tests/Js/mantenimiento-finalizar-paro.test.mjs).
 */

export const MAX_CALIDAD = 5;

/** Calificación guardada acotada a 0..5 (0 = sin calificar). */
export function acotarCalidad(valor: unknown): number {
    const n = Number.parseInt(String(valor ?? ''), 10);
    return Number.isFinite(n) ? Math.min(Math.max(n, 0), MAX_CALIDAD) : 0;
}

export function calidadValida(n: number): boolean {
    return Number.isInteger(n) && n >= 1 && n <= MAX_CALIDAD;
}

export interface DatosCierre {
    atendio: string;
    turno: string;
    calidad: number;
    obsCierre: string;
}

/** Primer problema del formulario, o null si se puede enviar. */
export function validarCierre(d: DatosCierre): { campo: 'atendio' | 'calidad'; mensaje: string } | null {
    if (!d.atendio.trim()) return { campo: 'atendio', mensaje: 'Debe seleccionar quién atendió el paro.' };
    if (!calidadValida(d.calidad)) {
        return { campo: 'calidad', mensaje: `Debe seleccionar una calificación entre 1 y ${MAX_CALIDAD}.` };
    }
    return null;
}

/** Payload del PUT: solo los campos de cierre (los informativos no viajan). */
export function payloadCierre(d: DatosCierre): { atendio: string; turno: string | null; calidad: number; obs_cierre: string | null } {
    return {
        atendio: d.atendio.trim(),
        turno: d.turno || null,
        calidad: d.calidad,
        obs_cierre: d.obsCierre || null,
    };
}
