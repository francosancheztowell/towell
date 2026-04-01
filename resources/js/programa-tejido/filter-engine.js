/**
 * filter-engine.js — Motor de filtrado puro para Programa Tejido.
 * Sin efectos secundarios DOM. Sin dependencias PHP.
 * Importado desde app.js y expuesto como window.PTFilterEngine.
 */

/**
 * Verifica si un valor de celda coincide con un filtro.
 * @param {string} cellValue  — valor normalizado (lowercase, trimmed)
 * @param {{ operator: string, value: string }} filter
 * @returns {boolean}
 */
export function checkFilterMatch(cellValue, filter) {
    const filterValue = String(filter.value ?? '').toLowerCase().trim();
    const cv = String(cellValue ?? '').toLowerCase().trim();

    switch (filter.operator) {
        case 'equals':    return cv === filterValue;
        case 'starts':    return cv.startsWith(filterValue);
        case 'ends':      return cv.endsWith(filterValue);
        case 'not':       return !cv.includes(filterValue);
        case 'empty':     return cv === '';
        case 'notEmpty':  return cv !== '';
        default:          return cv.includes(filterValue); // 'contains'
    }
}

/**
 * Agrupa filtros por columna para lógica OR-intra-columna / AND-inter-columna.
 * @param {Array<{column: string, operator: string, value: string}>} filters
 * @returns {Record<string, Array<{operator: string, value: string}>>}
 */
export function groupFiltersByColumn(filters) {
    return filters.reduce((acc, f) => {
        if (!acc[f.column]) acc[f.column] = [];
        acc[f.column].push({ value: String(f.value ?? '').trim().toLowerCase(), operator: f.operator ?? 'contains' });
        return acc;
    }, {});
}

/**
 * Evalúa si una fila (representada por su objeto de datos planos) pasa los filtros personalizados.
 * @param {Record<string, string>} rowData   — objeto del PT_FILTER_INDEX
 * @param {Record<string, Array>} filtersByColumn — resultado de groupFiltersByColumn()
 * @returns {boolean}
 */
export function rowMatchesCustomFilters(rowData, filtersByColumn) {
    return Object.entries(filtersByColumn).every(([column, columnFilters]) => {
        const cellValue = String(rowData[column] ?? '').toLowerCase().trim();
        return columnFilters.some(filter => checkFilterMatch(cellValue, filter));
    });
}

/**
 * Verifica si un valor de fecha está dentro de un rango.
 * @param {string} dateStr  — fecha en formato 'YYYY-MM-DD' o 'YYYY-MM-DD HH:MM:SS'
 * @param {string|null} desde
 * @param {string|null} hasta
 * @returns {boolean}
 */
export function dateInRange(dateStr, desde, hasta) {
    if (!dateStr) return false;
    const normalized = dateStr.split(' ')[0]; // solo fecha
    if (desde && normalized < desde) return false;
    if (hasta && normalized > hasta) return false;
    return true;
}
