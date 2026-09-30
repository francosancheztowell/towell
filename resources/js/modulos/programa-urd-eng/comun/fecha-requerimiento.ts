/**
 * Fecha y hora de requerimiento del material (Programa Urd-Eng): lógica pura, sin DOM.
 * La piden Creación de órdenes y Karl Mayer antes de crear la orden (19-05).
 * Hora LOCAL del equipo (datetime-local no lleva zona); toISOString() daría UTC.
 */

const dos = (n: number): string => String(n).padStart(2, '0');

/** Date → 'YYYY-MM-DDTHH:mm' en hora local (valor de un input datetime-local). */
export function aLocalISO(d: Date): string {
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}T${dos(d.getHours())}:${dos(d.getMinutes())}`;
}

/** Mínimo = ahora (sin segundos); sugerida = ahora + 1 h. */
export function rangoFechaRequerimiento(ahora: Date = new Date()): { min: string; sugerida: string } {
    const base = new Date(ahora.getTime());
    base.setSeconds(0, 0);
    return { min: aLocalISO(base), sugerida: aLocalISO(new Date(base.getTime() + 60 * 60 * 1000)) };
}

/** Mensaje de error o null si la fecha es válida (mismos textos que el modal anterior). */
export function validarFechaRequerimiento(valor: string, min: string): string | null {
    if (!valor) return 'Por favor selecciona una fecha y hora de requerimiento.';
    if (valor < min) return 'La fecha de requerimiento no puede ser anterior a la fecha y hora actual.';
    return null;
}
