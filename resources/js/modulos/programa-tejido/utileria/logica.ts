/**
 * Utilería de Planeación (Mover y Finalizar órdenes) — lógica pura, sin DOM.
 * La prueba tests/Js/programa-tejido-utileria.test.ts.
 */

export interface Telar {
    salon: string;
    telar: string;
}

export interface ConfigUtileria {
    finalizar: { telares: string; ordenes: string; procesar: string };
    mover: { telares: string; registros: string; procesar: string };
}

/** Nombre del tipo de salón para la etiqueta del panel. */
export function tipoSalonDisplay(salon: string | null | undefined): string {
    if (!salon) return '';
    const s = String(salon).toUpperCase().trim();
    if (s === 'JACQUARD' || s === 'JAC' || s === 'JACQ') return 'JACQUARD';
    if (s === 'SMIT' || s === 'SMITH' || s === 'ITEMA') return 'SMIT';
    if (s === 'KARL MAYER' || s === 'KARLMAYER' || s === 'KM') return 'KARL MAYER';
    return salon;
}

export function mismoTelar(a: Telar | null | undefined, b: Telar | null | undefined): boolean {
    if (!a || !b) return false;
    return (a.salon || '') === (b.salon || '') && (a.telar || '') === (b.telar || '');
}

/** ¿Cambió el orden o la asignación respecto a lo que trajo el servidor? */
export function hayCambios(actual: readonly { id: number }[], originales: readonly number[]): boolean {
    return actual.length !== originales.length || actual.some((r, i) => r.id !== originales[i]);
}

/**
 * Mueve el registro `id` de `origen` a `destino` (pueden ser la misma lista) en la posición
 * `indice` (o al final si es null). Devuelve false si no se encontró. Muta las listas.
 */
export function moverRegistro<T extends { id: number; isMoved?: boolean }>(
    origen: T[], destino: T[], id: number, indice: number | null,
): boolean {
    const desde = origen.findIndex((r) => r.id === id);
    if (desde === -1) return false;
    const [item] = origen.splice(desde, 1) as [T];
    let hacia = indice ?? destino.length;
    // En la misma lista, quitar el elemento recorre los de abajo.
    if (indice !== null && origen === destino && desde < hacia) hacia--;
    item.isMoved = true;
    destino.splice(hacia, 0, item);
    return true;
}

export interface OrdenFinalizar {
    id: number | string;
    produccion?: number | string | null;
}

/** Normaliza un id a número si se puede (las casillas traen texto). */
export function claveId(id: number | string): number | string {
    const n = typeof id === 'number' ? id : parseInt(String(id), 10);
    return Number.isNaN(n) ? id : n;
}

/** Alguna orden elegida sin producción (o con producción cero): no se puede finalizar. */
export function tieneSeleccionSinProduccion(seleccion: Iterable<number | string>, ordenes: readonly OrdenFinalizar[]): boolean {
    for (const id of seleccion) {
        const o = ordenes.find((x) => Number(x.id) === Number(id) || String(x.id) === String(id));
        if (!o || o.produccion == null || Number(o.produccion) === 0) return true;
    }
    return false;
}

/** Texto del pie del modal Finalizar. */
export function textoSeleccion(cantidad: number, sinProduccion: boolean): string {
    if (cantidad === 0) return '';
    if (sinProduccion) return cantidad + ' orden(es) seleccionada(s). No se puede finalizar si falta producción o la producción es cero.';
    return cantidad + ' orden(es) seleccionada(s)';
}
