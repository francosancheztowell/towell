/**
 * Alineación — lógica pura (sin DOM). La prueba tests/Js/programa-tejido-alineacion.test.ts.
 */

export type FilaAlineacion = Record<string, unknown> & { _tieneParoActivo?: boolean };

export interface FiltroAlineacion {
    column: string;
    value: string;
}

export interface ConfigAlineacion {
    columnas: string[];
    columnLabels: Record<string, string>;
    apiUrl: string;
    items: FilaAlineacion[];
}

const texto = (v: unknown) => (v != null ? String(v) : '');

/** Filas que pasan los filtros: por columna, el valor (sin mayúsculas) debe estar en la lista. */
export function filtrarFilas(filas: readonly FilaAlineacion[], filtros: readonly FiltroAlineacion[]): FilaAlineacion[] {
    if (!filtros.length) return [...filas];
    const porColumna: Record<string, string[]> = {};
    filtros.forEach((f) => {
        (porColumna[f.column] ??= []).push(String(f.value || '').toLowerCase().trim());
    });
    return filas.filter((row) => Object.entries(porColumna).every(([col, valores]) => valores.includes(texto(row[col]).toLowerCase().trim())));
}

/**
 * Texto de una celda. PesoGRM2 a 3 decimales y DiasPorEjecutar a 2; AnchoToalla (Med. Cen.)
 * no se formatea ("6/2" se truncaría a "6.000"). DiasEficiencia ya viene calculado.
 */
export function valorCelda(columna: string, value: unknown): string {
    const raw = value !== null && value !== undefined && value !== '' ? String(value) : '';
    const n = parseFloat(String(value));
    if (raw !== '' && !isNaN(n)) {
        if (columna === 'PesoGRM2') return n.toFixed(3);
        if (columna === 'DiasPorEjecutar') return n.toFixed(2);
    }
    return raw;
}

/** Clases del renglón según selección, paro activo y paridad. */
export function claseFila(seleccionada: boolean, paroActivo: boolean, par: boolean): string {
    let base: string;
    if (seleccionada && paroActivo) base = 'alineacion-row-alerta alineacion-row-alerta-selected';
    else if (seleccionada) base = 'alineacion-row-selected bg-blue-500 text-white hover:bg-blue-600';
    else if (paroActivo) base = 'alineacion-row-alerta';
    else base = par ? 'bg-white hover:bg-gray-100' : 'bg-gray-50 hover:bg-gray-200';
    return 'alineacion-selectable-row cursor-pointer transition-colors ' + base;
}

/** Clases de la celda (sin el índice de columna final). "Raz. S/N" en SI: fondo rojo. */
export function claseCelda(columna: string, raw: string, seleccionadaSinParo: boolean): string {
    if (columna === 'RazSN' && raw.trim().toUpperCase() === 'SI') {
        return 'px-3 py-1.5 border-b border-r border-red-700 whitespace-nowrap text-sm font-bold bg-red-600 text-white column-';
    }
    return seleccionadaSinParo
        ? 'px-3 py-1.5 border-b border-r border-blue-400 whitespace-nowrap text-sm text-white column-'
        : 'px-3 py-1.5 border-b border-r border-gray-200 whitespace-nowrap text-sm text-gray-700 column-';
}

/** Valores distintos (no vacíos) de una columna con su conteo, ordenados. */
export function valoresColumna(filas: readonly FilaAlineacion[], columna: string): [string, number][] {
    const conteo = new Map<string, number>();
    filas.forEach((row) => {
        const v = texto(row[columna]).trim();
        conteo.set(v, (conteo.get(v) ?? 0) + 1);
    });
    return Array.from(conteo.entries()).filter(([v]) => Boolean(v)).sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : 0));
}
