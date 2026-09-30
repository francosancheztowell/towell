/** Lógica pura del detalle de líneas diarias (tabla bajo la grilla y modal "Detalle del Telar"). */

export interface LineaDiaria {
    Fecha?: string | null;
    Cantidad?: number | string | null;
    Kilos?: number | string | null;
    Aplicacion?: number | string | null;
    Trama?: number | string | null;
    Combina1?: number | string | null;
    Combina2?: number | string | null;
    Combina3?: number | string | null;
    Combina4?: number | string | null;
    Combina5?: number | string | null;
    Rizo?: number | string | null;
    Pie?: number | string | null;
    MtsPie?: number | string | null;
    MtsRizo?: number | string | null;
}

/** Columnas numéricas en el orden de la tabla (después de Fecha). */
export const COLUMNAS_LINEA = [
    'Cantidad', 'Kilos', 'Aplicacion', 'Trama', 'Combina1', 'Combina2', 'Combina3', 'Combina4', 'Combina5',
    'Rizo', 'Pie', 'MtsPie', 'MtsRizo',
] as const satisfies readonly (keyof LineaDiaria)[];

export type ColumnaLinea = (typeof COLUMNAS_LINEA)[number];

/** Enteros en UI: fracción ≥ 0.5 redondea arriba (Math.round), separador de miles en-US. */
export function formatoEntero(v: unknown): string {
    if (v === null || v === undefined || v === '') return '';
    const num = Number(v);
    if (Number.isNaN(num)) return String(v);
    return Math.round(num).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

/** Las líneas de la respuesta: soporta paginate ({data:{data:[]}}) o arreglo simple. */
export function lineasDeRespuesta(data: unknown): LineaDiaria[] | null {
    const d = data as { data?: unknown } | null | undefined;
    const page = (d?.data ?? d) as { data?: unknown } | null | undefined;
    const items = page?.data ?? page;
    return Array.isArray(items) ? (items as LineaDiaria[]) : null;
}

/** Suma por columna (parseFloat, lo no numérico cuenta 0). */
export function totalesLineas(items: readonly LineaDiaria[]): Record<ColumnaLinea, number> {
    const totales = Object.fromEntries(COLUMNAS_LINEA.map((c) => [c, 0])) as Record<ColumnaLinea, number>;
    for (const it of items) {
        for (const c of COLUMNAS_LINEA) totales[c] += parseFloat(String(it[c] ?? '')) || 0;
    }
    return totales;
}

/** Fecha corta local, como `new Date(Fecha).toLocaleDateString()`. */
export function fechaLinea(fecha: string | null | undefined): string {
    return fecha ? new Date(fecha).toLocaleDateString() : '';
}
