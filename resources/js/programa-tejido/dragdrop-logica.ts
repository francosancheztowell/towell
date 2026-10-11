/**
 * Lógica pura del arrastre de prioridad (sin DOM). La usa PT.dragdrop en index.ts y la
 * prueba tests/Js/programa-tejido-dragdrop.test.ts.
 */

/**
 * new_position que espera DragAndDropTejido::mover: índice base 0 en la lista del telar
 * ordenada SIN la fila arrastrada (el servidor la saca y la reinserta en ese índice).
 * Se cuenta por orden en el DOM, nunca por el valor de Posicion (puede tener huecos).
 *
 * `filas` es el telar destino en orden de la grilla; puede incluir o no a `arrastrada`.
 */
export function indiceDestino<T>(filas: readonly T[], arrastrada: T | null, destino: T | null, antes: boolean): number {
    const total = filas.length - (arrastrada !== null && filas.includes(arrastrada) ? 1 : 0);
    const idx = destino === null ? -1 : filas.indexOf(destino);
    if (idx < 0) return total;

    let pos = antes ? idx : idx + 1;
    const origen = arrastrada === null ? -1 : filas.indexOf(arrastrada);
    if (origen >= 0 && origen < pos) pos -= 1;

    return Math.max(0, Math.min(pos, total));
}

/** Primer índice donde se puede soltar: después del último EnProceso (-1 si no hay). */
export function indiceMinimo(ultimoEnProceso: number): number {
    return ultimoEnProceso >= 0 ? ultimoEnProceso + 1 : 0;
}
