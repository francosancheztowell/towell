/** Reglas puras de las tablas de Compara (sin DOM, para poder probarlas). */
export type Series = 'plan' | 'pedido' | 'real';
/** planreal = VtasR. vs PlanVtas; summary = Resumen General; analisis = Análisis Histórico. */
export type Panel = 'planreal' | 'summary' | 'analisis';

type PanelLayout = {
    series: Series[];
    /** Par fijo [izquierda, derecha] del Δ y el Cumpl.; sin él manda el filtro "Comparar Δ y %". */
    comparison?: [Series, Series];
    deltaLabel?: string;
};

export const PANEL_LAYOUT: Record<Panel, PanelLayout> = {
    planreal: { series: ['plan', 'real'], comparison: ['plan', 'real'], deltaLabel: 'Diferencia (Real − Plan)' },
    summary: { series: ['plan', 'pedido', 'real'] },
    analisis: { series: ['plan', 'pedido', 'real'] },
};

/** Etiqueta + (series + Δ) × medidas + Cumpl. y Estatus. */
export const columnCount = (seriesCount: number, metricCount: number) => 1 + (seriesCount + 1) * metricCount + 2;

/** Las filas de empresa (subtotales Towel / Textil) llevan su propio color. */
export const empresaRowClass = (field: string | undefined, label: string) => {
    if (field !== 'empresa') return '';
    return ` pvoc-empresa pvoc-empresa-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
};
