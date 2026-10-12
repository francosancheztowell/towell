/**
 * Ventas históricas: filtros de selección múltiple con filtrado cruzado + reportes tipo tabla dinámica,
 * cada uno con vista de tabla o de gráfica.
 *
 * Los datos vienen de dbo.TwHistoricosVentas, ya agrupados en SQL (ver VentasHistoricasPayloadBuilder).
 */
import Chart from 'chart.js/auto';
import type { ChartConfiguration, ChartDataset } from 'chart.js';
import { escapeHtml, formatNumber as formatEsMx } from '../utils/format';
import { http } from '../utils/http';
import { createMultiSelect } from './multi-select';
import { bindRowSelection, clearRowSelection } from './row-selection';

/** Campos de texto del payload (sf) que usan los filtros y los reportes. */
type Field = 'empresa' | 'anio' | 'semestre' | 'mes' | 'tipoPedido' | 'tipoMaterial' | 'calidad' | 'cliente' | 'agente';
type Totals = ReturnType<typeof aggregate>;
type MetricKey = keyof Totals;
type VhRecord = Record<Field, string> & Record<'piezas' | 'kilos' | 'ventasBrutas' | 'descuentos', number>;
type Comparator = (a: string, b: string) => number;
type CompareSlot = 'anioA' | 'anioB';
type HistoricoPayload = { sf: string[]; nf: string[]; dict: string[]; rows: (string | number)[][] };
type Report = {
    id: string;
    title: string;
    labelHeader: string;
    levels: Field[];
    sortLeavesBy?: MetricKey;
    chart: { type: 'bar' | 'line'; x: Field; series?: Field; horizontal?: boolean; stacked?: boolean; sortBy?: 'metric' };
};
type ChartSeries = { label: string; data: number[]; color: string };

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const MESES_NOMBRE = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const monthName = (value: string) => MESES_NOMBRE[MESES.indexOf(value)] ?? value;
const EMPRESA_ORDER = ['TOWEL', 'TEXTIL'];

/** Orden natural de cada dimensión en tablas y ejes (los slicers de año van descendentes, como en Excel). */
const SORTERS: Partial<Record<string, Comparator>> = {
    empresa: (a, b) => EMPRESA_ORDER.indexOf(a) - EMPRESA_ORDER.indexOf(b),
    anio: (a, b) => Number(a) - Number(b),
    mes: (a, b) => MESES.indexOf(a) - MESES.indexOf(b),
};
const comparatorFor = (key: string): Comparator => SORTERS[key] || ((a, b) => String(a).localeCompare(String(b), 'es'));
const sortValues = (key: string, values: string[]) => values.sort(comparatorFor(key));

/** Filtros de selección múltiple (mismo componente que Resumen general); order = orden en la lista. */
const SLICERS: { key: Field; label: string; order?: Comparator; format?: (value: string) => string }[] = [
    { key: 'empresa', label: 'Empresa' },
    { key: 'anio', label: 'Año', order: (a, b) => Number(b) - Number(a) },
    { key: 'semestre', label: 'Semestre' },
    { key: 'mes', label: 'Mes', format: monthName },
    { key: 'tipoPedido', label: 'Tipo Pedido' },
    { key: 'tipoMaterial', label: 'Tipo de Material' },
    { key: 'calidad', label: 'Calidad' },
    { key: 'cliente', label: 'Nombre Cliente' },
    { key: 'agente', label: 'Agente Venta' },
];

/** [métrica, etiqueta, decimales]: los montos sin decimales para que las 13 columnas quepan en pantalla. */
const COLUMNS: [MetricKey, string, number][] = [
    ['piezas', 'Piezas', 0], ['kilos', 'Kilos', 0], ['ventasBrutas', 'Ventas Brutas', 0], ['descuentos', 'Descuentos', 0],
    ['importeNeto', 'Importe Neto', 0], ['pvbPza', 'PVB x Pza', 2], ['pvbKg', 'PVB x Kg', 2], ['descPza', 'Desc x Pza', 2],
    ['descKg', 'Desc x Kg', 2], ['pctDesc', '% Desc', 2], ['pvnPza', 'PVN x Pza', 2], ['pvnKg', 'PVN x Kg', 2],
];

/** Solo medidas aditivas: apilar o comparar precios promedio en una gráfica engaña. */
const CHART_METRICS: [MetricKey, string][] = [
    ['importeNeto', 'Importe Neto'], ['ventasBrutas', 'Ventas Brutas'], ['descuentos', 'Descuentos'],
    ['piezas', 'Piezas'], ['kilos', 'Kilos'],
];

/** [métrica, etiqueta, decimales] del comparativo por año. */
const COMPARE_COLUMNS: [MetricKey, string, number][] = [
    ['piezas', 'Pzas', 0], ['kilos', 'Kilos', 0], ['ventasBrutas', 'V.B.', 0], ['descuentos', 'Desc.', 0],
    ['importeNeto', 'V.N.', 0], ['pvbKg', 'PVB KG', 2], ['pvnKg', 'PVN KG', 2],
];

/**
 * Reportes tipo tabla dinámica. levels = agrupación de filas (el último nivel son las filas hoja);
 * chart = cómo se dibuja en vista de gráfica.
 */
const REPORTS: Report[] = [
    {
        id: 'anual', title: 'Ventas Anuales', labelHeader: 'Empresa / Año', levels: ['empresa', 'anio'],
        chart: { type: 'bar', x: 'anio', series: 'empresa' },
    },
    {
        id: 'tipoPedido', title: 'Ventas Empresa y Tipo de Pedido', labelHeader: 'Empresa / Tipo de pedido', levels: ['empresa', 'tipoPedido'],
        chart: { type: 'bar', x: 'tipoPedido', series: 'empresa' },
    },
    {
        id: 'mensual', title: 'Ventas Mensuales', labelHeader: 'Empresa / Mes', levels: ['empresa', 'mes'],
        chart: { type: 'line', x: 'mes', series: 'empresa' },
    },
    {
        id: 'agente', title: 'Ventas x Agente de Ventas', labelHeader: 'Agente de ventas', levels: ['agente'], sortLeavesBy: 'importeNeto',
        chart: { type: 'bar', x: 'agente', horizontal: true, sortBy: 'metric' },
    },
    {
        id: 'material', title: 'Ventas x Tipo de Material', labelHeader: 'Empresa / Calidad / Tipo de material', levels: ['empresa', 'calidad', 'tipoMaterial'],
        chart: { type: 'bar', x: 'tipoMaterial', series: 'calidad', stacked: true },
    },
];

const COMPARE_REPORT = { id: 'comparativo', title: 'Comparativo por Año' };

/** Subsecciones de Ventas históricas, en el orden de la sidebar. */
export const HISTORY_SECTIONS = [...REPORTS, COMPARE_REPORT].map(({ id, title }) => ({ id, title }));

const isHistoryReport = (id: string | undefined): id is string => HISTORY_SECTIONS.some((section) => section.id === id);

/** Paleta categórica validada (orden fijo, nunca ciclado): el color sigue a la entidad, no al rango. */
const SERIES_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
/** Comparativo por año: Año A en verde y Año B en azul, igual en tablas, selectores y gráfica. */
const COMPARE_COLORS: Record<CompareSlot, string> = { anioA: '#15885e', anioB: '#2a78d6' };
const CHART_INK = '#52514e';
const CHART_GRID = '#e7e5e4';

const formatNumber = (value: number, decimals = 2) => formatEsMx(Number.isFinite(value) ? value : 0, decimals);
const compactFormat = new Intl.NumberFormat('es-MX', { notation: 'compact', maximumFractionDigits: 1 });
const formatCompact = (value: number | string) => compactFormat.format(Number(value));

// ---------------------------------------------------------------------------
// Datos
// ---------------------------------------------------------------------------

/** Valores vacíos en la fuente (p.ej. factura sin agente) se muestran como en la tabla dinámica de Excel. */
const BLANK = '(en blanco)';

/**
 * VentasHistoricasPayloadBuilder manda { sf, nf, dict, rows }: cada fila son índices al
 * diccionario (campos de texto, en el orden de sf) seguidos de los totales numéricos (nf).
 */
const loadRecords = (payload: HistoricoPayload | null | undefined): VhRecord[] => {
    if (!payload || !Array.isArray(payload.rows)) return [];

    const { sf, nf, dict, rows } = payload;
    return rows.map((row) => {
        const record: Record<string, string | number> = {};
        sf.forEach((field, index) => { record[field] = dict[Number(row[index])] || BLANK; });
        nf.forEach((field, index) => { record[field] = Number(row[sf.length + index]) || 0; });
        record.mes = MESES[Number(record.mes) - 1] ?? record.mes ?? BLANK;
        return record as VhRecord;
    });
};
// ---------------------------------------------------------------------------
// Agregación
// ---------------------------------------------------------------------------

const aggregate = (items: VhRecord[]) => {
    const totals = items.reduce((acc, item) => {
        acc.piezas += item.piezas;
        acc.kilos += item.kilos;
        acc.ventasBrutas += item.ventasBrutas;
        acc.descuentos += item.descuentos;
        return acc;
    }, { piezas: 0, kilos: 0, ventasBrutas: 0, descuentos: 0 });

    const ratio = (numerator: number, denominator: number) => (denominator ? numerator / denominator : 0);
    const importeNeto = totals.ventasBrutas - totals.descuentos;

    return {
        ...totals,
        importeNeto,
        pvbPza: ratio(totals.ventasBrutas, totals.piezas),
        pvbKg: ratio(totals.ventasBrutas, totals.kilos),
        descPza: ratio(totals.descuentos, totals.piezas),
        descKg: ratio(totals.descuentos, totals.kilos),
        pctDesc: ratio(totals.descuentos, totals.ventasBrutas) * 100,
        pvnPza: ratio(importeNeto, totals.piezas),
        pvnKg: ratio(importeNeto, totals.kilos),
    };
};

const groupBy = (items: VhRecord[], key: Field) => items.reduce((groups, item) => {
    const group = groups.get(item[key]);
    if (group) group.push(item); else groups.set(item[key], [item]);
    return groups;
}, new Map<string, VhRecord[]>());

const sumMetric = (items: VhRecord[], metric: MetricKey) => aggregate(items)[metric];

// ---------------------------------------------------------------------------
// Gráficas
// ---------------------------------------------------------------------------

type ChartSpec = { type: 'bar' | 'line'; labels: string[]; datasets: ChartSeries[]; horizontal?: boolean | undefined; stacked?: boolean | undefined };

const buildChart = (canvas: HTMLCanvasElement, { type, labels, datasets, horizontal = false, stacked = false }: ChartSpec) => {
    const valueAxis = horizontal ? 'x' : 'y';
    const categoryAxis = horizontal ? 'y' : 'x';
    const styled = datasets.map(({ label, data, color }): ChartDataset<'bar' | 'line', number[]> => (type === 'line'
        ? { label, data, borderColor: color, backgroundColor: color, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: 0 }
        : {
            label, data, backgroundColor: color,
            // Extremos de dato redondeados y anclados a la base; en pilas, 2px de separación del color de fondo.
            borderRadius: stacked ? 0 : 4, borderSkipped: 'start',
            borderWidth: stacked ? { top: 2 } : 0, borderColor: '#ffffff',
            maxBarThickness: 36,
        }));

    const config: ChartConfiguration<'bar' | 'line', number[], string> = {
        type,
        data: { labels, datasets: styled },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            animation: false,
            interaction: type === 'line' ? { mode: 'index', intersect: false } : { mode: 'nearest', intersect: true },
            plugins: {
                legend: {
                    display: datasets.length > 1,
                    position: 'top',
                    align: 'start',
                    labels: { usePointStyle: true, boxWidth: 8, color: CHART_INK },
                },
                tooltip: {
                    callbacks: { label: (context) => `${context.dataset.label}: ${formatNumber(context.parsed[valueAxis] ?? 0)}` },
                },
            },
            scales: {
                [categoryAxis]: { stacked, grid: { display: false }, ticks: { color: CHART_INK, autoSkip: false } },
                [valueAxis]: {
                    stacked, beginAtZero: true,
                    grid: { color: CHART_GRID }, border: { display: false },
                    ticks: { color: CHART_INK, callback: (value) => formatCompact(value) },
                },
            },
        },
    };
    return new Chart(canvas, config);
};

// ---------------------------------------------------------------------------
// Componente
// ---------------------------------------------------------------------------

const initVentasHistoricas = (root: HTMLElement, payload: HistoricoPayload) => {
    const records = loadRecords(payload);
    const allYears = sortValues('anio', [...new Set(records.map((record) => record.anio))]);
    const state = {
        selected: Object.fromEntries(SLICERS.map(({ key }) => [key, new Set<string>()])) as Record<Field, Set<string>>,
        metric: Object.fromEntries([...REPORTS.map(({ id }) => id), 'comparativo'].map((id) => [id, 'importeNeto'])) as Record<string, MetricKey>,
        collapsed: Object.fromEntries(REPORTS.map(({ id }) => [id, new Set<string>()])) as Record<string, Set<string>>,
        compare: {
            anioA: allYears[allYears.length - 2] ?? allYears[0],
            anioB: allYears[allYears.length - 1],
            meses: new Set<string>(),
        } as Record<CompareSlot, string | undefined> & { meses: Set<string> },
        // El reporte lo elige la sidebar (data-vh-requested); sin elección, el primero.
        activeReport: isHistoryReport(root.dataset.vhRequested) ? root.dataset.vhRequested : REPORTS[0]!.id,
    };
    const charts: Partial<Record<string, Chart>> = {};
    // La vista Blade trae los dos contenedores; si faltaran, mountVentasHistoricas muestra el error.
    const elements = {
        slicers: root.querySelector<HTMLElement>('[data-vh-slicers]')!,
        reports: root.querySelector<HTMLElement>('[data-vh-reports]')!,
    };

    const allValues = Object.fromEntries(SLICERS.map(({ key, order }) => {
        const values = sortValues(key, [...new Set(records.map((record) => record[key]))]);
        return [key, order ? values.sort(order) : values];
    })) as Record<Field, string[]>;

    /** El color de una serie depende de su posición en el catálogo completo, no en el resultado filtrado. */
    const colorFor = (key: Field, value: string) => SERIES_COLORS[Math.max(0, allValues[key].indexOf(value)) % SERIES_COLORS.length]!;

    const matches = (record: VhRecord, except: Field[] = []) => SLICERS.every(({ key }) =>
        except.includes(key) || !state.selected[key].size || state.selected[key].has(record[key]));

    const filteredRecords = () => records.filter((record) => matches(record));

    /** Valores que siguen teniendo datos con el resto de filtros aplicados (sin contar el propio). */
    const availableValues = (key: Field) => new Set(records.filter((record) => matches(record, [key])).map((record) => record[key]));

    // --- Filtros -------------------------------------------------------------

    const pickers = Object.fromEntries(SLICERS.map((slicer) => [slicer.key, createMultiSelect({
        label: slicer.label,
        values: allValues[slicer.key],
        selected: state.selected[slicer.key],
        format: slicer.format,
        onChange: () => refresh(),
    })])) as Record<Field, ReturnType<typeof createMultiSelect>>;

    const renderSlicer = ({ key }: { key: Field }) => pickers[key].setAvailable(availableValues(key));

    // --- Reportes tipo tabla dinámica ----------------------------------------

    const metricCells = (totals: Totals) => COLUMNS.map(([key, , decimals]) =>
        `<td class="pvoc-number">${formatNumber(totals[key], decimals)}${key === 'pctDesc' ? '%' : ''}</td>`).join('');

    const sortGroups = (report: Report, key: Field, groups: [string, VhRecord[]][], isLeaf: boolean) => (isLeaf && report.sortLeavesBy
        ? groups.sort(([, a], [, b]) => sumMetric(b, report.sortLeavesBy!) - sumMetric(a, report.sortLeavesBy!))
        : groups.sort(([a], [b]) => comparatorFor(key)(a, b)));

    const renderTree = (report: Report, items: VhRecord[], levels: Field[], depth = 0, path = ''): string => {
        const key = levels[0]!;
        const isLeaf = levels.length === 1;
        const indent = `style="padding-left: ${0.7 + depth * 1.4}rem"`;

        return sortGroups(report, key, [...groupBy(items, key).entries()], isLeaf).map(([value, children]) => {
            if (isLeaf) {
                return `<tr class="vh-row-leaf" data-row-key="${escapeHtml(`${path}/${value}`)}"><td class="pvoc-label" ${indent}>${escapeHtml(value)}</td>${metricCells(aggregate(children))}</tr>`;
            }
            const id = `${path}/${value}`;
            const collapsed = state.collapsed[report.id]!.has(id);
            const header = `<tr class="vh-row-group vh-depth-${Math.min(depth, 2)}" data-row-key="${escapeHtml(id)}"><td class="pvoc-label" colspan="${COLUMNS.length + 1}" ${indent}>
                <button type="button" class="pvoc-expander" data-vh-toggle="${escapeHtml(id)}" aria-expanded="${!collapsed}">${collapsed ? '⊞' : '⊟'}</button>${escapeHtml(value)}
            </td></tr>`;
            const body = collapsed ? '' : renderTree(report, children, levels.slice(1), depth + 1, id);
            const subtotal = `<tr class="vh-row-subtotal vh-depth-${Math.min(depth, 2)}" data-row-key="${escapeHtml(`${id}#total`)}"><td class="pvoc-label" ${indent}>Total ${escapeHtml(value)}</td>${metricCells(aggregate(children))}</tr>`;
            return header + body + subtotal;
        }).join('');
    };

    const renderReportTable = (report: Report, container: HTMLElement, data: VhRecord[]) => {
        const body = renderTree(report, data, report.levels);
        container.innerHTML = `<table class="pvoc-table vh-table">
            <thead><tr><th class="pvoc-label">${escapeHtml(report.labelHeader)}</th>${COLUMNS.map(([, label]) => `<th>${label}</th>`).join('')}</tr></thead>
            <tbody>
                ${body || `<tr><td colspan="${COLUMNS.length + 1}" class="pvoc-empty">No hay datos para los filtros seleccionados.</td></tr>`}
                <tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${metricCells(aggregate(data))}</tr>
            </tbody>
        </table>`;
    };

    const renderReportChart = (report: Report, canvas: HTMLCanvasElement, data: VhRecord[]) => {
        const { type, x, series, horizontal, stacked, sortBy } = report.chart;
        const metric = state.metric[report.id] ?? 'importeNeto';
        const metricLabel = CHART_METRICS.find(([key]) => key === metric)?.[1] ?? metric;
        const byX = groupBy(data, x);
        const labels = sortBy === 'metric'
            ? [...byX.keys()].sort((a, b) => sumMetric(byX.get(b) ?? [], metric) - sumMetric(byX.get(a) ?? [], metric))
            : sortValues(x, [...byX.keys()]);

        const datasets = series
            ? sortValues(series, [...new Set(data.map((record) => record[series]))]).map((value) => ({
                label: value,
                color: colorFor(series, value),
                data: labels.map((label) => sumMetric((byX.get(label) ?? []).filter((record) => record[series] === value), metric)),
            }))
            : [{ label: metricLabel, color: SERIES_COLORS[0]!, data: labels.map((label) => sumMetric(byX.get(label) ?? [], metric)) }];

        // Las barras horizontales crecen con el número de categorías para que ninguna etiqueta se encime.
        canvas.parentElement!.style.height = horizontal ? `${Math.max(320, labels.length * 26 + 60)}px` : '';
        charts[report.id] = buildChart(canvas, { type, labels, datasets, horizontal, stacked });
        canvas.setAttribute('aria-label', `${report.title}: ${metricLabel}`);
    };

    // --- Comparativo por año -------------------------------------------------
    // Distribución como la hoja de Excel: Año A | tabla A | meses | tabla B | Año B, y debajo los
    // semestres de cada año y la variación B vs A. Las filas de A y B se alinean (mismos meses).

    const compareBase = () => records.filter((record) => matches(record, ['anio', 'mes', 'semestre'])
        && (!state.compare.meses.size || state.compare.meses.has(record.mes)));

    const compareColgroup = `<colgroup><col class="vh-cmp-col-label">${COMPARE_COLUMNS.map(() => '<col>').join('')}</colgroup>`;
    const compareHeader = (firstLabel: string) => `<tr>
        <th class="pvoc-label">${firstLabel}</th>${COMPARE_COLUMNS.map(([, label]) => `<th>${label}</th>`).join('')}
    </tr>`;
    const compareCells = (items: VhRecord[]) => {
        if (!items.length) return COMPARE_COLUMNS.map(() => '<td class="pvoc-number vh-muted">–</td>').join('');
        const totals = aggregate(items);
        return COMPARE_COLUMNS.map(([key, , decimals]) => `<td class="pvoc-number">${formatNumber(totals[key], decimals)}</td>`).join('');
    };

    /** Tabla de un año (A o B) por mes o por semestre; values = filas compartidas con el otro año. */
    const yearTable = (base: VhRecord[], slot: CompareSlot, key: 'mes' | 'semestre', values: string[], area: string) => {
        const anio = state.compare[slot];
        const items = base.filter((record) => record.anio === anio);
        const byValue = groupBy(items, key);
        const label = key === 'mes' ? 'Mes' : 'Semestre';
        const rows = values.map((value) =>
            `<tr><td class="pvoc-label">${escapeHtml(value)}</td>${compareCells(byValue.get(value) ?? [])}</tr>`).join('');
        return `<div class="pvoc-table-scroll vh-cmp-block vh-cmp-${area}"><table class="pvoc-table vh-cmp-table vh-cmp-${slot === 'anioA' ? 'a' : 'b'}">
            ${compareColgroup}
            <thead>
                <tr class="vh-cmp-caption"><th colspan="${COMPARE_COLUMNS.length + 1}">${label} · ${escapeHtml(anio ?? '')}</th></tr>
                ${compareHeader(label)}
            </thead>
            <tbody>${rows || `<tr><td colspan="${COMPARE_COLUMNS.length + 1}" class="pvoc-empty">Sin datos.</td></tr>`}
                <tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${compareCells(items)}</tr></tbody>
        </table></div>`;
    };

    const variationTable = (base: VhRecord[], semestres: string[]) => {
        const { anioA, anioB } = state.compare;
        const variationCells = (itemsA: VhRecord[], itemsB: VhRecord[]) => {
            const a = aggregate(itemsA);
            const b = aggregate(itemsB);
            return COMPARE_COLUMNS.map(([key]) => {
                if (!a[key] || !itemsB.length) return '<td class="pvoc-number vh-muted">–</td>';
                const change = (b[key] / a[key] - 1) * 100;
                return `<td class="pvoc-number ${change < 0 ? 'is-negative' : 'is-positive'}">${change > 0 ? '+' : ''}${formatNumber(change)}%</td>`;
            }).join('');
        };
        const itemsA = base.filter((record) => record.anio === anioA);
        const itemsB = base.filter((record) => record.anio === anioB);
        const rows = semestres.map((semestre) => `<tr><td class="pvoc-label">${escapeHtml(semestre)}</td>${variationCells(
            itemsA.filter((record) => record.semestre === semestre),
            itemsB.filter((record) => record.semestre === semestre),
        )}</tr>`).join('');

        return `<div class="pvoc-table-scroll vh-cmp-block vh-cmp-cs"><table class="pvoc-table vh-cmp-table vh-cmp-var">
            ${compareColgroup}
            <thead>
                <tr class="vh-cmp-caption"><th colspan="${COMPARE_COLUMNS.length + 1}">Compara Semestre · ${escapeHtml(anioB ?? '')} vs ${escapeHtml(anioA ?? '')}</th></tr>
                ${compareHeader('Semestre')}
            </thead>
            <tbody>${rows}<tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${variationCells(itemsA, itemsB)}</tr></tbody>
        </table></div>`;
    };

    /** Selector de año en columna, a un lado de su tabla (como el segmentador de Excel). */
    const yearSlicer = (slot: CompareSlot, area: string) => {
        const label = slot === 'anioA' ? 'Año A' : 'Año B';
        const chips = allYears.slice().reverse().map((anio) => {
            const on = state.compare[slot] === anio;
            return `<button type="button" class="vh-chip ${on ? 'is-selected' : ''}" data-vh-compare-year="${slot}" data-vh-value="${escapeHtml(anio)}" aria-pressed="${on}">${escapeHtml(anio)}</button>`;
        }).join('');
        return `<div class="vh-cmp-slicer vh-cmp-${area} vh-cmp-${slot === 'anioA' ? 'a' : 'b'}" role="group" aria-label="${label}">
            <div class="vh-filter-label"><span>${label}</span></div>
            <div class="vh-cmp-years">${chips}</div>
        </div>`;
    };

    const monthSlicer = () => {
        const chips = MESES.map((mes) => {
            const on = state.compare.meses.has(mes);
            return `<button type="button" class="vh-chip ${on ? 'is-selected' : ''}" data-vh-compare-month data-vh-value="${mes}" aria-pressed="${on}">${mes}</button>`;
        }).join('');
        return `<div class="vh-cmp-slicer vh-cmp-mo" role="group" aria-label="Mes">
            <div class="vh-filter-label"><span>Mes</span><button type="button" class="vh-filter-clear" data-vh-compare-clear title="Borrar filtro" aria-label="Borrar filtro de mes" ${state.compare.meses.size ? '' : 'hidden'}>×</button></div>
            <div class="vh-cmp-months">${chips}</div>
        </div>`;
    };

    const renderCompare = () => {
        const section = elements.reports.querySelector<HTMLElement>('[data-vh-report="comparativo"]')!;
        const base = compareBase();
        const { anioA, anioB } = state.compare;
        const bothYears = base.filter((record) => record.anio === anioA || record.anio === anioB);
        const meses = sortValues('mes', [...new Set(bothYears.map((record) => record.mes))]);
        const semestres = sortValues('semestre', [...new Set(bothYears.map((record) => record.semestre))]);

        section.querySelector<HTMLElement>('[data-vh-table]')!.innerHTML = [
            yearSlicer('anioA', 'ya'),
            yearTable(base, 'anioA', 'mes', meses, 'ma'),
            monthSlicer(),
            yearTable(base, 'anioB', 'mes', meses, 'mb'),
            yearSlicer('anioB', 'yb'),
            // Abajo: Compara Semestre grande a la izquierda; semestres de A y B apilados a la derecha.
            `<div class="vh-cmp-bottom">
                ${variationTable(base, semestres)}
                ${yearTable(base, 'anioA', 'semestre', semestres, 'sa')}
                ${yearTable(base, 'anioB', 'semestre', semestres, 'sb')}
            </div>`,
        ].join('');

        const metric = state.metric.comparativo ?? 'importeNeto';
        const labels = MESES.filter((mes) => !state.compare.meses.size || state.compare.meses.has(mes));
        const datasets = (['anioA', 'anioB'] as const).map((slot) => ({
            label: state.compare[slot] ?? '',
            color: COMPARE_COLORS[slot],
            data: labels.map((mes) => sumMetric(base.filter((record) => record.anio === state.compare[slot] && record.mes === mes), metric)),
        }));
        const canvas = section.querySelector('canvas')!;
        charts.comparativo = buildChart(canvas, { type: 'bar', labels, datasets });
        canvas.setAttribute('aria-label', `Importe neto por mes: ${anioA} vs ${anioB}`);
    };

    // --- Contenedores de reporte ---------------------------------------------

    /** Cada subsección muestra su tabla y, debajo, su gráfica (medida fija: la de state.metric). */
    const reportShell = ({ id }: { id: string }) => `
        <section class="vh-report" data-vh-report="${id}">
            <div data-vh-table ${id === 'comparativo' ? 'class="vh-cmp"' : ''}></div>
            ${id === 'comparativo' ? '<h3 class="vh-chart-title">Importe neto por mes</h3>' : ''}
            <div class="vh-chart" data-vh-chart><canvas role="img"></canvas></div>
        </section>`;

    const renderReport = (id: string) => {
        charts[id]?.destroy();
        delete charts[id];

        if (id === 'comparativo') {
            renderCompare();
            return;
        }

        const section = elements.reports.querySelector<HTMLElement>(`[data-vh-report="${id}"]`);
        const report = REPORTS.find((candidate) => candidate.id === id);
        if (!section || !report) return;
        const data = filteredRecords();
        const container = section.querySelector<HTMLElement>('[data-vh-table]')!;
        container.className = 'pvoc-table-scroll vh-report-table';
        renderReportTable(report, container, data);
        renderReportChart(report, section.querySelector('canvas')!, data);
    };

    // Solo se dibuja la subsección visible: Chart.js no puede medir un canvas oculto.
    const renderReports = () => renderReport(state.activeReport);

    const syncReports = () => {
        elements.reports.querySelectorAll<HTMLElement>('[data-vh-report]').forEach((section) =>
            section.classList.toggle('is-hidden', section.dataset.vhReport !== state.activeReport));
    };

    /** Tocar un filtro (slicers o selectores del comparativo) suelta la fila seleccionada. */
    const clearSelections = () => elements.reports.querySelectorAll<HTMLElement>('[data-vh-table]').forEach(clearRowSelection);

    /** El botón "Filtrar" del navbar muestra cuántos filtros hay activos (lo escucha dashboard.ts). */
    const syncFilterCount = () => {
        const count = SLICERS.filter(({ key }) => state.selected[key].size).length;
        root.dispatchEvent(new CustomEvent('pvoc:filtros', { bubbles: true, detail: { tab: 'history', count } }));
    };

    const refresh = () => {
        clearSelections();
        SLICERS.forEach(renderSlicer);
        renderReports();
        syncFilterCount();
    };

    // --- Montaje y eventos -----------------------------------------------------

    elements.slicers.replaceChildren(...SLICERS.map(({ key }) => pickers[key].element));

    // La sidebar (sidebar.ts) elige el reporte con 'vh:report'.
    root.addEventListener('vh:report', (event) => {
        const id = (event as CustomEvent<string>).detail;
        if (!isHistoryReport(id) || id === state.activeReport) return;
        state.activeReport = id;
        syncReports();
        renderReports();
    });

    elements.reports.innerHTML = [...REPORTS, COMPARE_REPORT].map(reportShell).join('');
    elements.reports.querySelectorAll<HTMLElement>('[data-vh-table]').forEach(bindRowSelection);
    syncReports();

    elements.reports.addEventListener('click', (event) => {
        const target = event.target as Element;
        const section = target.closest<HTMLElement>('[data-vh-report]');
        const id = section?.dataset.vhReport;
        if (!id) return;

        const toggle = target.closest<HTMLElement>('[data-vh-toggle]');
        const collapsed = state.collapsed[id];
        if (toggle && collapsed) {
            const groupId = toggle.dataset.vhToggle ?? '';
            if (collapsed.has(groupId)) collapsed.delete(groupId); else collapsed.add(groupId);
            renderReport(id);
            return;
        }

        if (target.closest('[data-vh-compare-year], [data-vh-compare-month], [data-vh-compare-clear]')) clearSelections();

        const yearChip = target.closest<HTMLElement>('[data-vh-compare-year]');
        if (yearChip) {
            state.compare[yearChip.dataset.vhCompareYear as CompareSlot] = yearChip.dataset.vhValue;
            renderReport('comparativo');
            return;
        }

        const monthChip = target.closest<HTMLElement>('[data-vh-compare-month]');
        if (monthChip) {
            const { meses } = state.compare;
            const mes = monthChip.dataset.vhValue ?? '';
            if (meses.has(mes)) meses.delete(mes); else meses.add(mes);
            renderReport('comparativo');
            return;
        }

        if (target.closest('[data-vh-compare-clear]')) {
            state.compare.meses.clear();
            renderReport('comparativo');
        }
    });

    root.querySelector('[data-vh-clear-all]')?.addEventListener('click', () => {
        SLICERS.forEach(({ key }) => state.selected[key].clear());
        refresh();
    });

    refresh();
};

/**
 * Los datos se piden en segundo plano desde que carga la página, pero la pestaña se dibuja hasta
 * que se abre por primera vez: Chart.js no puede medir un canvas oculto y así no retrasa Compara.
 * scope = raíz del dashboard, que emite 'pvoc:tab' al cambiar de pestaña.
 */
export const mountVentasHistoricas = (scope: HTMLElement, onError: (container: HTMLElement) => void) => {
    scope.querySelectorAll<HTMLElement>('[data-ventas-historicas]').forEach((root) => {
        const datos = http.get<HistoricoPayload>(root.dataset.historicoUrl ?? '');
        datos.catch(() => {});
        let montado = false;

        scope.addEventListener('pvoc:tab', async (event) => {
            if ((event as CustomEvent<string>).detail !== 'history' || montado) return;
            montado = true;
            try {
                initVentasHistoricas(root, await datos);
            } catch (error) {
                console.error('No se pudieron cargar las ventas históricas.', error);
                onError(root.querySelector<HTMLElement>('[data-vh-reports]')!);
            }
        });
    });
};
