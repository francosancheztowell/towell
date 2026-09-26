/**
 * Lógica pura del catálogo Actividades BPM (Urdido y Engomado). Sin DOM: tests/Js/urdeng-bpm-actividades.test.mjs.
 */

/** Clic en una fila: la misma fila deselecciona; otra, la selecciona. */
export function alternarSeleccion(actual: string | null, clic: string | null | undefined): string | null {
    if (!clic) return null;
    return actual === clic ? null : clic;
}

export interface ValoresActividad {
    Orden: string;
    Actividad: string;
    Maquina?: string;
}

/** Valores del modal Editar desde los data-* de la fila; Máquina solo en la variante que la usa (Urdido). */
export function valoresEdicion(
    fila: Readonly<Record<string, string | undefined>>,
    conMaquina: boolean,
): ValoresActividad {
    const valores: ValoresActividad = { Orden: fila.orden ?? '', Actividad: fila.actividad ?? '' };
    if (conMaquina) valores.Maquina = fila.maquina ?? '';
    return valores;
}
