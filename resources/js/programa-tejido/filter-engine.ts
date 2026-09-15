/**
 * filter-engine.ts — Motor de filtrado puro para Programa Tejido.
 * Sin efectos secundarios DOM. Sin dependencias PHP.
 * Importado desde app.js y expuesto como window.PTFilterEngine.
 */

/** El operador llega del DOM: cualquier string, y lo no reconocido cae en 'contains'. */
export type FilterOperator =
    | 'equals' | 'starts' | 'ends' | 'not' | 'empty' | 'notEmpty' | 'contains';

export interface ColumnFilter {
    operator: FilterOperator | string;
    value: string;
}

/** Filtro personalizado tal como lo guarda el modal, con la columna a la que aplica. */
export interface CustomFilter extends ColumnFilter {
    column: string;
}

/** OR dentro de cada columna, AND entre columnas. */
export type FiltersByColumn = Record<string, ColumnFilter[]>;

/** Fila aplanada del PT_FILTER_INDEX: columna -> valor de celda. */
export type RowData = Record<string, string | null | undefined>;

export function checkFilterMatch(
    cellValue: string | null | undefined,
    filter: ColumnFilter,
): boolean {
    const filterValue = String(filter.value ?? '').toLowerCase().trim();
    const cv = String(cellValue ?? '').toLowerCase().trim();

    switch (filter.operator) {
        case 'equals': return cv === filterValue;
        case 'starts': return cv.startsWith(filterValue);
        case 'ends': return cv.endsWith(filterValue);
        case 'not': return !cv.includes(filterValue);
        case 'empty': return cv === '';
        case 'notEmpty': return cv !== '';
        default: return cv.includes(filterValue); // 'contains'
    }
}

export function groupFiltersByColumn(filters: CustomFilter[]): FiltersByColumn {
    return filters.reduce<FiltersByColumn>((acc, f) => {
        // Con noUncheckedIndexedAccess el acceso por indice es posiblemente undefined,
        // asi que la lista se crea explicitamente en vez de asumirla.
        const delaColumna = acc[f.column] ?? (acc[f.column] = []);
        delaColumna.push({
            value: String(f.value ?? '').trim().toLowerCase(),
            operator: f.operator ?? 'contains',
        });

        return acc;
    }, {});
}

export function rowMatchesCustomFilters(
    rowData: RowData,
    filtersByColumn: FiltersByColumn,
): boolean {
    return Object.entries(filtersByColumn).every(([column, columnFilters]) => {
        const cellValue = String(rowData[column] ?? '').toLowerCase().trim();

        return columnFilters.some((filter) => checkFilterMatch(cellValue, filter));
    });
}

/**
 * @param dateStr 'YYYY-MM-DD' o 'YYYY-MM-DD HH:MM:SS'
 */
export function dateInRange(
    dateStr: string | null | undefined,
    desde: string | null | undefined,
    hasta: string | null | undefined,
): boolean {
    if (!dateStr) return false;
    // split() siempre devuelve al menos un elemento, pero noUncheckedIndexedAccess
    // no lo sabe: el ?? deja explicito que no hay caso vacio.
    const normalized = dateStr.split(' ')[0] ?? dateStr;
    if (desde && normalized < desde) return false;
    if (hasta && normalized > hasta) return false;

    return true;
}
