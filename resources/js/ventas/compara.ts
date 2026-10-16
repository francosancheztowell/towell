/** Reglas puras de las tablas de Compara (sin DOM, para poder probarlas). */
export type Series = 'plan' | 'pedido' | 'real';
/** planreal = VtasR. vs PlanVtas (oculta en la sidebar); summary = Resumen General; analisis = Análisis Histórico. */
export type Panel = 'planreal' | 'summary' | 'analisis';

type PanelLayout = {
    series: Series[];
    /** Par fijo [izquierda, derecha] del Δ y el Cumpl. Pzas.; sin él manda el filtro "Comparar Δ y %". */
    comparison?: [Series, Series];
    deltaLabel?: string;
};

export const PANEL_LAYOUT: Record<Panel, PanelLayout> = {
    planreal: { series: ['plan', 'real'], comparison: ['plan', 'real'], deltaLabel: 'Diferencia (Real − Plan)' },
    summary: { series: ['plan', 'pedido', 'real'] },
    analisis: { series: ['plan', 'pedido', 'real'] },
};

/** Etiqueta + (series + Δ) × medidas + Cumpl. Pzas., Cumplimiento Actual y Estatus. */
export const columnCount = (seriesCount: number, metricCount: number) => 1 + (seriesCount + 1) * metricCount + 3;

/** Los subtotales de empresa (Towel / Textil) y las filas de artículo llevan su propio color. */
export const rowClass = (field: string | undefined, label: string) => {
    if (field === 'articulo') return ' pvoc-articulo';
    if (field !== 'empresa') return '';
    return ` pvoc-empresa pvoc-empresa-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
};

export type Today = { anio: number; mes: number; dia: number };

/** Fecha de hoy en America/Mexico_City (la del navegador puede estar en otra zona). */
export const mexicoToday = (now = new Date()): Today => {
    const [anio, mes, dia] = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Mexico_City' })
        .format(now).split('-').map(Number);
    return { anio: anio!, mes: mes!, dia: dia! };
};

/** Parte del mes que ya pasó: 1 en meses pasados, días transcurridos ÷ días del mes en el actual, 0 en futuros. */
export const elapsedFraction = (anio: number, mes: number, today: Today) => {
    if (anio < today.anio || (anio === today.anio && mes < today.mes)) return 1;
    if (anio === today.anio && mes === today.mes) return today.dia / new Date(anio, mes, 0).getDate();
    return 0;
};

type PlanRow = { anio: string; mes: string; plan: { piezas: number } };

/** Cumplimiento Actual: % del plan de piezas que se debería llevar a hoy (plan a la fecha ÷ plan total × 100). */
export const expectedCompliance = (rows: PlanRow[], today: Today) => {
    let total = 0;
    let dueByToday = 0;
    rows.forEach(({ anio, mes, plan }) => {
        const piezas = plan.piezas || 0;
        total += piezas;
        dueByToday += piezas * elapsedFraction(Number(anio), Number(mes), today);
    });
    return total ? (dueByToday / total) * 100 : 0;
};

type KpiRow = { anio: string; mes: string; plan: { vn: number; kilos: number }; real: { vn: number; kilos: number } };

export type ClosingKpis = {
    /** P. de Mes y Vta R. al corte, en V.N. ($). */
    plan: number;
    real: number;
    planKg: number;
    /** Dif Neta Plan − Real: positiva = lo que falta vender. */
    diferencia: number;
    /** % de Avance: real ÷ plan × 100, y lo que se esperaría llevar hoy si se vendiera parejo. */
    avance: number;
    avanceEsperado: number;
    /** Proyección de cierre a ritmo actual: real ÷ parte del mes transcurrida (mes cerrado = lo vendido). */
    proyeccion: number;
    proyeccionKg: number;
    diasRestantes: number;
    /** Ritmo requerido: V.N. por día natural que falta para llegar al plan; null si ya no quedan días. */
    ritmo: number | null;
};

/** KPIs de cierre de Resumen General sobre los combos filtrados (uno o varios meses). */
export const closingKpis = (rows: KpiRow[], today: Today): ClosingKpis => {
    const months = new Map<string, { anio: number; mes: number; planVn: number; realVn: number; realKg: number }>();
    let planKg = 0;
    rows.forEach(({ anio, mes, plan, real }) => {
        const key = `${anio}-${mes}`;
        const month = months.get(key) ?? { anio: Number(anio), mes: Number(mes), planVn: 0, realVn: 0, realKg: 0 };
        month.planVn += plan.vn || 0;
        month.realVn += real.vn || 0;
        month.realKg += real.kilos || 0;
        planKg += plan.kilos || 0;
        months.set(key, month);
    });

    let plan = 0; let real = 0; let due = 0; let proyeccion = 0; let proyeccionKg = 0; let diasRestantes = 0;
    months.forEach(({ anio, mes, planVn, realVn, realKg }) => {
        const fraction = elapsedFraction(anio, mes, today);
        const days = new Date(anio, mes, 0).getDate();
        plan += planVn;
        real += realVn;
        due += planVn * fraction;
        // Un mes futuro (fracción 0) no tiene ritmo que extrapolar: se proyecta lo que ya trae.
        proyeccion += fraction > 0 ? realVn / fraction : realVn;
        proyeccionKg += fraction > 0 ? realKg / fraction : realKg;
        diasRestantes += Math.round(days * (1 - fraction));
    });

    const diferencia = plan - real;
    return {
        plan, real, planKg, diferencia,
        avance: plan ? (real / plan) * 100 : 0,
        avanceEsperado: plan ? (due / plan) * 100 : 0,
        proyeccion, proyeccionKg, diasRestantes,
        ritmo: diasRestantes > 0 ? Math.max(0, diferencia) / diasRestantes : null,
    };
};
