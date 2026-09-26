/**
 * Ventas históricas: filtros de selección múltiple con filtrado cruzado + reportes tipo tabla dinámica,
 * cada uno con vista de tabla o de gráfica.
 *
 * Los datos vienen de dbo.TwHistoricosVentas, ya agrupados en SQL (ver VentasHistoricasPayloadBuilder).
 */
import Chart from 'chart.js/auto';
import { createMultiSelect } from './multi-select';

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const MESES_NOMBRE = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const monthName = (value) => MESES_NOMBRE[MESES.indexOf(value)] ?? value;
const EMPRESA_ORDER = ['TOWEL', 'TEXTIL'];

/** Orden natural de cada dimensión en tablas y ejes (los slicers de año van descendentes, como en Excel). */
const SORTERS = {
    empresa: (a, b) => EMPRESA_ORDER.indexOf(a) - EMPRESA_ORDER.indexOf(b),
    anio: (a, b) => Number(a) - Number(b),
    mes: (a, b) => MESES.indexOf(a) - MESES.indexOf(b),
};
const comparatorFor = (key) => SORTERS[key] || ((a, b) => String(a).localeCompare(String(b), 'es'));
const sortValues = (key, values) => values.sort(comparatorFor(key));

/** Filtros de selección múltiple (mismo componente que Resumen general); order = orden en la lista. */
const SLICERS = [
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
const COLUMNS = [
    ['piezas', 'Piezas', 0], ['kilos', 'Kilos', 0], ['ventasBrutas', 'Ventas Brutas', 0], ['descuentos', 'Descuentos', 0],
    ['importeNeto', 'Importe Neto', 0], ['pvbPza', 'PVB x Pza', 2], ['pvbKg', 'PVB x Kg', 2], ['descPza', 'Desc x Pza', 2],
    ['descKg', 'Desc x Kg', 2], ['pctDesc', '% Desc', 2], ['pvnPza', 'PVN x Pza', 2], ['pvnKg', 'PVN x Kg', 2],
];

/** Solo medidas aditivas: apilar o comparar precios promedio en una gráfica engaña. */
const CHART_METRICS = [
    ['importeNeto', 'Importe Neto'], ['ventasBrutas', 'Ventas Brutas'], ['descuentos', 'Descuentos'],
    ['piezas', 'Piezas'], ['kilos', 'Kilos'],
];

/** [métrica, etiqueta, decimales] del comparativo por año. */
const COMPARE_COLUMNS = [
    ['piezas', 'Pzas', 0], ['kilos', 'Kilos', 0], ['ventasBrutas', 'V.B.', 0], ['descuentos', 'Desc.', 0],
    ['importeNeto', 'V.N.', 0], ['pvbKg', 'PVB KG', 2], ['pvnKg', 'PVN KG', 2],
];

/**
 * Reportes tipo tabla dinámica. levels = agrupación de filas (el último nivel son las filas hoja);
 * chart = cómo se dibuja en vista de gráfica.
 */
const REPORTS = [
    {
        id: 'anual', title: 'Ventas Anuales', labelHeader: 'Empresa / Año', levels: ['empresa', 'anio'],
        defaultView: 'table', chart: { type: 'bar', x: 'anio', series: 'empresa' },
    },
    {
        id: 'tipoPedido', title: 'Ventas Empresa y Tipo de Pedido', labelHeader: 'Empresa / Tipo de pedido', levels: ['empresa', 'tipoPedido'],
        defaultView: 'table', chart: { type: 'bar', x: 'tipoPedido', series: 'empresa' },
    },
    {
        id: 'mensual', title: 'Ventas Mensuales', labelHeader: 'Empresa / Mes', levels: ['empresa', 'mes'],
        defaultView: 'chart', chart: { type: 'line', x: 'mes', series: 'empresa' },
    },
    {
        id: 'agente', title: 'Ventas x Agente de Ventas', labelHeader: 'Agente de ventas', levels: ['agente'], sortLeavesBy: 'importeNeto',
        defaultView: 'chart', chart: { type: 'bar', x: 'agente', horizontal: true, sortBy: 'metric' },
    },
    {
        id: 'material', title: 'Ventas x Tipo de Material', labelHeader: 'Empresa / Calidad / Tipo de material', levels: ['empresa', 'calidad', 'tipoMaterial'],
        defaultView: 'table', chart: { type: 'bar', x: 'tipoMaterial', series: 'calidad', stacked: true },
    },
];

/** Paleta categórica validada (orden fijo, nunca ciclado): el color sigue a la entidad, no al rango. */
const SERIES_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
const CHART_INK = '#52514e';
const CHART_GRID = '#e7e5e4';

const formatNumber = (value, decimals = 2) => new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: decimals, maximumFractionDigits: decimals,
}).format(Number.isFinite(value) ? value : 0);
const formatCompact = (value) => new Intl.NumberFormat('es-MX', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));

// ---------------------------------------------------------------------------
// Datos
// ---------------------------------------------------------------------------

/** Valores vacíos en la fuente (p.ej. factura sin agente) se muestran como en la tabla dinámica de Excel. */
const BLANK = '(en blanco)';

/**
 * VentasHistoricasPayloadBuilder manda { sf, nf, dict, rows }: cada fila son índices al
 * diccionario (campos de texto, en el orden de sf) seguidos de los totales numéricos (nf).
 */
const loadRecords = (root) => {
    let payload;
    try {
        payload = JSON.parse(root.dataset.historico || 'null');
    } catch {
        payload = null;
    }
    if (!payload || !Array.isArray(payload.rows)) return [];

    const { sf, nf, dict, rows } = payload;
    return rows.map((row) => {
        const record = {};
        sf.forEach((field, index) => { record[field] = dict[row[index]] || BLANK; });
        nf.forEach((field, index) => { record[field] = Number(row[sf.length + index]) || 0; });
        record.mes = MESES[Number(record.mes) - 1] ?? record.mes;
        return record;
    });
};
// ---------------------------------------------------------------------------
// Agregación
// ---------------------------------------------------------------------------

const aggregate = (items) => {
    const totals = items.reduce((acc, item) => {
        acc.piezas += item.piezas;
        acc.kilos += item.kilos;
        acc.ventasBrutas += item.ventasBrutas;
        acc.descuentos += item.descuentos;
        return acc;
    }, { piezas: 0, kilos: 0, ventasBrutas: 0, descuentos: 0 });

    const ratio = (numerator, denominator) => (denominator ? numerator / denominator : 0);
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

const groupBy = (items, key) => items.reduce((groups, item) => {
    if (!groups.has(item[key])) groups.set(item[key], []);
    groups.get(item[key]).push(item);
    return groups;
}, new Map());

const sumMetric = (items, metric) => aggregate(items)[metric];

// ---------------------------------------------------------------------------
// Gráficas
// ---------------------------------------------------------------------------

const buildChart = (canvas, { type, labels, datasets, horizontal = false, stacked = false }) => {
    const valueAxis = horizontal ? 'x' : 'y';
    const categoryAxis = horizontal ? 'y' : 'x';
    const styled = datasets.map(({ label, data, color }) => (type === 'line'
        ? { label, data, borderColor: color, backgroundColor: color, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: 0 }
        : {
            label, data, backgroundColor: color,
            // Extremos de dato redondeados y anclados a la base; en pilas, 2px de separación del color de fondo.
            borderRadius: stacked ? 0 : 4, borderSkipped: 'start',
            borderWidth: stacked ? { top: 2 } : 0, borderColor: '#ffffff',
            maxBarThickness: 36,
        }));

    return new Chart(canvas, {
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
                    callbacks: { label: (context) => `${context.dataset.label}: ${formatNumber(context.parsed[valueAxis])}` },
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
    });
};

// ---------------------------------------------------------------------------
// Componente
// ---------------------------------------------------------------------------

const initVentasHistoricas = (root) => {
    const records = loadRecords(root);
    const allYears = sortValues('anio', [...new Set(records.map((record) => record.anio))]);
    const state = {
        selected: Object.fromEntries(SLICERS.map(({ key }) => [key, new Set()])),
        view: Object.fromEntries([...REPORTS, { id: 'comparativo', defaultView: 'table' }].map(({ id, defaultView }) => [id, defaultView])),
        metric: Object.fromEntries([...REPORTS.map(({ id }) => id), 'comparativo'].map((id) => [id, 'importeNeto'])),
        collapsed: Object.fromEntries(REPORTS.map(({ id }) => [id, new Set()])),
        groupIds: Object.fromEntries(REPORTS.map(({ id }) => [id, new Set()])),
        compare: { anioA: allYears[allYears.length - 2] ?? allYears[0], anioB: allYears[allYears.length - 1], meses: new Set() },
        activeReport: REPORTS[0].id,
    };
    const charts = {};
    const elements = {
        slicers: root.querySelector('[data-vh-slicers]'),
        summary: root.querySelector('[data-vh-summary]'),
        subtabs: root.querySelector('[data-vh-subtabs]'),
        reports: root.querySelector('[data-vh-reports]'),
    };

    const allValues = Object.fromEntries(SLICERS.map(({ key, order }) => {
        const values = sortValues(key, [...new Set(records.map((record) => record[key]))]);
        return [key, order ? values.sort(order) : values];
    }));

    /** El color de una serie depende de su posición en el catálogo completo, no en el resultado filtrado. */
    const colorFor = (key, value) => SERIES_COLORS[Math.max(0, allValues[key].indexOf(value)) % SERIES_COLORS.length];

    const matches = (record, except = []) => SLICERS.every(({ key }) =>
        except.includes(key) || !state.selected[key].size || state.selected[key].has(record[key]));

    const filteredRecords = () => records.filter((record) => matches(record));

    /** Valores que siguen teniendo datos con el resto de filtros aplicados (sin contar el propio). */
    const availableValues = (key) => new Set(records.filter((record) => matches(record, [key])).map((record) => record[key]));

    // --- Filtros -------------------------------------------------------------

    const pickers = Object.fromEntries(SLICERS.map((slicer) => [slicer.key, createMultiSelect({
        label: slicer.label,
        values: allValues[slicer.key],
        selected: state.selected[slicer.key],
        format: slicer.format,
        onChange: () => refresh(),
    })]));

    const renderSlicer = ({ key }) => pickers[key].setAvailable(availableValues(key));

    const renderSummary = () => {
        const active = SLICERS.filter(({ key }) => state.selected[key].size);
        elements.summary.innerHTML = active.length
            ? active.map(({ key, label, format = (value) => value }) => `<span class="vh-summary-pill">${escapeHtml(label)}: <strong>${escapeHtml([...state.selected[key]].map(format).join(', '))}</strong></span>`).join('')
            : '<span class="vh-summary-none">Sin filtros aplicados</span>';
    };

    // --- Reportes tipo tabla dinámica ----------------------------------------

    const metricCells = (totals) => COLUMNS.map(([key, , decimals]) =>
        `<td class="pvoc-number">${formatNumber(totals[key], decimals)}${key === 'pctDesc' ? '%' : ''}</td>`).join('');

    const sortGroups = (report, key, groups, isLeaf) => (isLeaf && report.sortLeavesBy
        ? groups.sort(([, a], [, b]) => sumMetric(b, report.sortLeavesBy) - sumMetric(a, report.sortLeavesBy))
        : groups.sort(([a], [b]) => comparatorFor(key)(a, b)));

    const renderTree = (report, items, levels, depth = 0, path = '') => {
        const [key] = levels;
        const isLeaf = levels.length === 1;
        const indent = `style="padding-left: ${0.7 + depth * 1.4}rem"`;

        return sortGroups(report, key, [...groupBy(items, key).entries()], isLeaf).map(([value, children]) => {
            if (isLeaf) {
                return `<tr class="vh-row-leaf"><td class="pvoc-label" ${indent}>${escapeHtml(value)}</td>${metricCells(aggregate(children))}</tr>`;
            }
            const id = `${path}/${value}`;
            state.groupIds[report.id].add(id);
            const collapsed = state.collapsed[report.id].has(id);
            const header = `<tr class="vh-row-group vh-depth-${Math.min(depth, 2)}"><td class="pvoc-label" colspan="${COLUMNS.length + 1}" ${indent}>
                <button type="button" class="pvoc-expander" data-vh-toggle="${escapeHtml(id)}" aria-expanded="${!collapsed}">${collapsed ? '⊞' : '⊟'}</button>${escapeHtml(value)}
            </td></tr>`;
            const body = collapsed ? '' : renderTree(report, children, levels.slice(1), depth + 1, id);
            const subtotal = `<tr class="vh-row-subtotal vh-depth-${Math.min(depth, 2)}"><td class="pvoc-label" ${indent}>Total ${escapeHtml(value)}</td>${metricCells(aggregate(children))}</tr>`;
            return header + body + subtotal;
        }).join('');
    };

    const renderReportTable = (report, container, data) => {
        state.groupIds[report.id].clear();
        const body = renderTree(report, data, report.levels);
        container.innerHTML = `<table class="pvoc-table vh-table">
            <thead><tr><th class="pvoc-label">${escapeHtml(report.labelHeader)}</th>${COLUMNS.map(([, label]) => `<th>${label}</th>`).join('')}</tr></thead>
            <tbody>
                ${body || `<tr><td colspan="${COLUMNS.length + 1}" class="pvoc-empty">No hay datos para los filtros seleccionados.</td></tr>`}
                <tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${metricCells(aggregate(data))}</tr>
            </tbody>
        </table>`;
    };

    const renderReportChart = (report, canvas, data) => {
        const { type, x, series, horizontal, stacked, sortBy } = report.chart;
        const metric = state.metric[report.id];
        const metricLabel = CHART_METRICS.find(([key]) => key === metric)[1];
        const byX = groupBy(data, x);
        const labels = sortBy === 'metric'
            ? [...byX.keys()].sort((a, b) => sumMetric(byX.get(b), metric) - sumMetric(byX.get(a), metric))
            : sortValues(x, [...byX.keys()]);

        const datasets = series
            ? sortValues(series, [...new Set(data.map((record) => record[series]))]).map((value) => ({
                label: value,
                color: colorFor(series, value),
                data: labels.map((label) => sumMetric(byX.get(label).filter((record) => record[series] === value), metric)),
            }))
            : [{ label: metricLabel, color: SERIES_COLORS[0], data: labels.map((label) => sumMetric(byX.get(label), metric)) }];

        // Las barras horizontales crecen con el número de categorías para que ninguna etiqueta se encime.
        canvas.parentElement.style.height = horizontal ? `${Math.max(320, labels.length * 26 + 60)}px` : '';
        charts[report.id] = buildChart(canvas, { type, labels, datasets, horizontal, stacked });
        canvas.setAttribute('aria-label', `${report.title}: ${metricLabel}`);
    };

    // --- Comparativo por año -------------------------------------------------

    const compareBase = () => records.filter((record) => matches(record, ['anio', 'mes', 'semestre'])
        && (!state.compare.meses.size || state.compare.meses.has(record.mes)));

    const compareCells = (totals) => COMPARE_COLUMNS.map(([key, , decimals]) =>
        `<td class="pvoc-number">${formatNumber(totals[key], decimals)}</td>`).join('');

    const compareHeader = (firstLabel) => `<thead><tr><th class="pvoc-label">${firstLabel}</th>${COMPARE_COLUMNS.map(([, label]) => `<th>${label}</th>`).join('')}</tr></thead>`;

    const yearTable = (base, anio, key) => {
        const items = base.filter((record) => record.anio === anio);
        const rows = sortValues(key, [...groupBy(items, key).keys()]).map((value) =>
            `<tr><td class="pvoc-label">${escapeHtml(value)}</td>${compareCells(aggregate(items.filter((record) => record[key] === value)))}</tr>`).join('');
        return `<div class="pvoc-table-scroll vh-compare-table"><table class="pvoc-table vh-table">
            ${compareHeader(key === 'mes' ? `Mes · ${escapeHtml(anio)}` : `Semestre · ${escapeHtml(anio)}`)}
            <tbody>${rows || `<tr><td colspan="${COMPARE_COLUMNS.length + 1}" class="pvoc-empty">Sin datos para ${escapeHtml(anio)}.</td></tr>`}
                <tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${compareCells(aggregate(items))}</tr></tbody>
        </table></div>`;
    };

    const variationTable = (base) => {
        const { anioA, anioB } = state.compare;
        const variationCells = (itemsA, itemsB) => {
            const a = aggregate(itemsA);
            const b = aggregate(itemsB);
            return COMPARE_COLUMNS.map(([key]) => {
                if (!a[key]) return '<td class="pvoc-number vh-muted">—</td>';
                const change = (b[key] / a[key] - 1) * 100;
                return `<td class="pvoc-number ${change < 0 ? 'is-negative' : 'is-positive'}">${change > 0 ? '+' : ''}${formatNumber(change)}%</td>`;
            }).join('');
        };
        const itemsA = base.filter((record) => record.anio === anioA);
        const itemsB = base.filter((record) => record.anio === anioB);
        const semestres = sortValues('semestre', [...new Set(base.map((record) => record.semestre))]);
        const rows = semestres.map((semestre) => `<tr><td class="pvoc-label">${escapeHtml(semestre)}</td>${variationCells(
            itemsA.filter((record) => record.semestre === semestre),
            itemsB.filter((record) => record.semestre === semestre),
        )}</tr>`).join('');

        return `<div class="pvoc-table-scroll vh-compare-table"><table class="pvoc-table vh-table">
            <thead><tr class="pvoc-table-groups"><th colspan="${COMPARE_COLUMNS.length + 1}">Compara Semestre · ${escapeHtml(anioB)} vs ${escapeHtml(anioA)}</th></tr></thead>
            ${compareHeader('Semestre')}
            <tbody>${rows}<tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${variationCells(itemsA, itemsB)}</tr></tbody>
        </table></div>`;
    };

    const renderCompareControls = () => {
        const container = elements.reports.querySelector('[data-vh-compare-controls]');
        const yearChips = (slot) => allYears.slice().reverse().map((anio) =>
            `<button type="button" class="vh-chip ${state.compare[slot] === anio ? 'is-selected' : ''}" data-vh-compare-year="${slot}" data-vh-value="${escapeHtml(anio)}" aria-pressed="${state.compare[slot] === anio}">${escapeHtml(anio)}</button>`).join('');
        const monthChips = MESES.map((mes) => {
            const on = state.compare.meses.has(mes);
            return `<button type="button" class="vh-chip ${on ? 'is-selected' : ''}" data-vh-compare-month data-vh-value="${mes}" aria-pressed="${on}">${mes}</button>`;
        }).join('');
        const field = (label, chips, clear = '') => `<div class="vh-filter">
            <div class="vh-filter-label"><span>${label}</span>${clear}</div>
            <div class="vh-chips">${chips}</div>
        </div>`;

        container.innerHTML = field('Año A', yearChips('anioA'))
            + field('Mes', monthChips, `<button type="button" class="vh-filter-clear" data-vh-compare-clear title="Borrar filtro" aria-label="Borrar filtro de mes" ${state.compare.meses.size ? '' : 'hidden'}>×</button>`)
            + field('Año B', yearChips('anioB'));
    };

    const renderCompare = () => {
        const section = elements.reports.querySelector('[data-vh-report="comparativo"]');
        const base = compareBase();
        const { anioA, anioB } = state.compare;
        renderCompareControls();

        if (state.view.comparativo === 'table') {
            section.querySelector('[data-vh-table]').innerHTML = `
                <div class="vh-compare-grid">
                    ${yearTable(base, anioA, 'mes')}${yearTable(base, anioB, 'mes')}
                    ${yearTable(base, anioA, 'semestre')}${yearTable(base, anioB, 'semestre')}
                </div>
                ${variationTable(base)}`;
            return;
        }

        const metric = state.metric.comparativo;
        const labels = MESES.filter((mes) => !state.compare.meses.size || state.compare.meses.has(mes));
        const datasets = [anioA, anioB].map((anio, index) => ({
            label: anio,
            color: SERIES_COLORS[index],
            data: labels.map((mes) => sumMetric(base.filter((record) => record.anio === anio && record.mes === mes), metric)),
        }));
        charts.comparativo = buildChart(section.querySelector('canvas'), { type: 'bar', labels, datasets });
    };

    // --- Contenedores de reporte ---------------------------------------------

    const reportShell = ({ id, title, levels = [] }, note = '') => `
        <section class="vh-report" data-vh-report="${id}">
            <header class="vh-report-header">
                <div>
                    <h3>${escapeHtml(title)}</h3>
                    ${note ? `<p>${note}</p>` : ''}
                </div>
                <div class="vh-report-tools">
                    <label class="vh-metric" data-vh-metric-wrap>Medida
                        <select data-vh-metric>${CHART_METRICS.map(([key, label]) => `<option value="${key}">${label}</option>`).join('')}</select>
                    </label>
                    ${levels.length > 1 ? `<span class="vh-tree-tools" data-vh-tree-tools>
                        <button type="button" class="pvoc-button pvoc-button-small" data-vh-expand-report>Expandir</button>
                        <button type="button" class="pvoc-button pvoc-button-small" data-vh-collapse-report>Colapsar</button>
                    </span>` : ''}
                    <div class="vh-view-toggle" role="group" aria-label="Vista de ${escapeHtml(title)}">
                        <button type="button" data-vh-view="table"><i class="fa-solid fa-table" aria-hidden="true"></i> Tabla</button>
                        <button type="button" data-vh-view="chart"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Gráfica</button>
                    </div>
                </div>
            </header>
            ${id === 'comparativo' ? '<div class="vh-compare-controls" data-vh-compare-controls></div>' : ''}
            <div data-vh-table></div>
            <div class="vh-chart" data-vh-chart><canvas role="img"></canvas></div>
        </section>`;

    const syncReportChrome = (id) => {
        const section = elements.reports.querySelector(`[data-vh-report="${id}"]`);
        const isChart = state.view[id] === 'chart';
        section.querySelectorAll('[data-vh-view]').forEach((button) => {
            const active = button.dataset.vhView === state.view[id];
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        section.querySelector('[data-vh-metric-wrap]').classList.toggle('is-hidden', !isChart);
        section.querySelector('[data-vh-metric]').value = state.metric[id];
        section.querySelector('[data-vh-tree-tools]')?.classList.toggle('is-hidden', isChart);
        section.querySelector('[data-vh-table]').classList.toggle('is-hidden', isChart);
        section.querySelector('[data-vh-chart]').classList.toggle('is-hidden', !isChart);
        return section;
    };

    const renderReport = (id) => {
        charts[id]?.destroy();
        delete charts[id];
        const section = syncReportChrome(id);

        if (id === 'comparativo') {
            renderCompare();
            return;
        }

        const report = REPORTS.find((candidate) => candidate.id === id);
        const data = filteredRecords();
        if (state.view[id] === 'chart') {
            renderReportChart(report, section.querySelector('canvas'), data);
        } else {
            const container = section.querySelector('[data-vh-table]');
            container.className = 'pvoc-table-scroll vh-report-table';
            renderReportTable(report, container, data);
        }
    };

    // Solo se dibuja la subsección visible: Chart.js no puede medir un canvas oculto.
    const renderReports = () => renderReport(state.activeReport);

    const syncSubtabs = () => {
        elements.subtabs.querySelectorAll('[data-vh-subtab]').forEach((button) => {
            const active = button.dataset.vhSubtab === state.activeReport;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', String(active));
        });
        elements.reports.querySelectorAll('[data-vh-report]').forEach((section) =>
            section.classList.toggle('is-hidden', section.dataset.vhReport !== state.activeReport));
    };

    const refresh = () => {
        SLICERS.forEach(renderSlicer);
        renderSummary();
        renderReports();
    };

    // --- Montaje y eventos -----------------------------------------------------

    elements.slicers.replaceChildren(...SLICERS.map(({ key }) => pickers[key].element));

    const compareReport = { id: 'comparativo', title: 'Comparativo por Año' };

    elements.subtabs.innerHTML = [...REPORTS, compareReport].map(({ id, title }) =>
        `<button type="button" role="tab" aria-selected="false" data-vh-subtab="${id}">${escapeHtml(title)}</button>`).join('');

    elements.subtabs.addEventListener('click', (event) => {
        const button = event.target.closest('[data-vh-subtab]');
        if (!button || button.dataset.vhSubtab === state.activeReport) return;
        state.activeReport = button.dataset.vhSubtab;
        syncSubtabs();
        renderReports();
    });

    elements.reports.innerHTML = [
        ...REPORTS.map((report) => reportShell(report)),
        reportShell(compareReport, 'Usa sus propios selectores de año y mes; el resto de los filtros sí aplica.'),
    ].join('');
    syncSubtabs();

    elements.reports.addEventListener('click', (event) => {
        const section = event.target.closest('[data-vh-report]');
        if (!section) return;
        const id = section.dataset.vhReport;
        const target = event.target;

        const viewButton = target.closest('[data-vh-view]');
        if (viewButton) {
            state.view[id] = viewButton.dataset.vhView;
            renderReport(id);
            return;
        }

        const toggle = target.closest('[data-vh-toggle]');
        if (toggle) {
            const groupId = toggle.dataset.vhToggle;
            state.collapsed[id].has(groupId) ? state.collapsed[id].delete(groupId) : state.collapsed[id].add(groupId);
            renderReport(id);
            return;
        }

        if (target.closest('[data-vh-expand-report]')) {
            state.collapsed[id].clear();
            renderReport(id);
            return;
        }

        if (target.closest('[data-vh-collapse-report]')) {
            // Solo el primer nivel: colapsar también los internos obligaría a abrir uno por uno después.
            state.groupIds[id].forEach((groupId) => { if (groupId.split('/').length === 2) state.collapsed[id].add(groupId); });
            renderReport(id);
            return;
        }

        const yearChip = target.closest('[data-vh-compare-year]');
        if (yearChip) {
            state.compare[yearChip.dataset.vhCompareYear] = yearChip.dataset.vhValue;
            renderReport('comparativo');
            return;
        }

        const monthChip = target.closest('[data-vh-compare-month]');
        if (monthChip) {
            const { meses } = state.compare;
            const mes = monthChip.dataset.vhValue;
            meses.has(mes) ? meses.delete(mes) : meses.add(mes);
            renderReport('comparativo');
            return;
        }

        if (target.closest('[data-vh-compare-clear]')) {
            state.compare.meses.clear();
            renderReport('comparativo');
        }
    });

    elements.reports.addEventListener('change', (event) => {
        const select = event.target.closest('[data-vh-metric]');
        if (!select) return;
        const id = select.closest('[data-vh-report]').dataset.vhReport;
        state.metric[id] = select.value;
        renderReport(id);
    });

    root.querySelector('[data-vh-clear-all]')?.addEventListener('click', () => {
        SLICERS.forEach(({ key }) => state.selected[key].clear());
        refresh();
    });

    refresh();
};

export const mountVentasHistoricas = (scope = document) => {
    scope.querySelectorAll('[data-ventas-historicas]').forEach(initVentasHistoricas);
};
