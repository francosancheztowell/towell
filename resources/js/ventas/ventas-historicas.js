/**
 * Ventas históricas: slicers estilo Excel (filtrado cruzado) + reportes tipo tabla dinámica,
 * cada uno con vista de tabla o de gráfica.
 *
 * Por ahora trabaja con datos mock generados en el cliente. Cuando exista la fuente real,
 * basta con reemplazar loadRecords() por registros con el mismo shape que buildMockRecords().
 */
import Chart from 'chart.js/auto';

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const EMPRESA_ORDER = ['TOWEL', 'TEXTIL'];

/** Orden natural de cada dimensión en tablas y ejes (los slicers de año van descendentes, como en Excel). */
const SORTERS = {
    empresa: (a, b) => EMPRESA_ORDER.indexOf(a) - EMPRESA_ORDER.indexOf(b),
    anio: (a, b) => Number(a) - Number(b),
    mes: (a, b) => MESES.indexOf(a) - MESES.indexOf(b),
};
const comparatorFor = (key) => SORTERS[key] || ((a, b) => String(a).localeCompare(String(b), 'es'));
const sortValues = (key, values) => values.sort(comparatorFor(key));

/**
 * type: switch = una opción o todas · chips = selección múltiple con clic ·
 * search = buscador con sugerencias · dropdown = lista desplegable con casillas.
 */
const SLICERS = [
    { key: 'empresa', label: 'Empresa', type: 'switch', allLabel: 'Ambas' },
    { key: 'anio', label: 'Año', type: 'chips', order: (a, b) => Number(b) - Number(a) },
    { key: 'semestre', label: 'Semestre', type: 'chips', short: (value) => value.replace(' Semestre', '') },
    { key: 'mes', label: 'Mes', type: 'chips' },
    { key: 'tipoPedido', label: 'Tipo Pedido', type: 'chips' },
    { key: 'tipoMaterial', label: 'Tipo de Material', type: 'chips' },
    { key: 'calidad', label: 'Calidad', type: 'chips' },
    { key: 'cliente', label: 'Nombre Cliente', type: 'search', placeholder: 'Buscar cliente…' },
    { key: 'agente', label: 'Agente Venta', type: 'dropdown', placeholder: 'Buscar agente…' },
];
const SUGGESTION_LIMIT = 40;

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
// Datos mock
// ---------------------------------------------------------------------------

/** PRNG con semilla: el mock sale idéntico en cada carga. */
const seededRandom = (seed) => () => {
    seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const pickWeighted = (random, entries) => {
    const total = entries.reduce((sum, [, weight]) => sum + weight, 0);
    let roll = random() * total;
    for (const [value, weight] of entries) {
        roll -= weight;
        if (roll <= 0) return value;
    }
    return entries[entries.length - 1][0];
};

const MOCK_AGENTES = [
    'Alberto T.', 'Alejandro M.', 'Fabian Q.', 'Francisco P.', 'Gabriela O.', 'Hector G.', 'Jahaziel M.',
    'José R.', 'Juan Pablo R.', 'Laura G.', 'Luis F.', 'Maria del Rayo', 'Monica M.', 'Othon O.',
    'Rafael B.', 'Sin Agente', 'Sostenes T.', 'Tienda Towell', '(en blanco)',
];

const MOCK_CLIENTES = [
    'ABASTECEDORA HOTELERA DEL NORTE SA DE CV', 'BLANCOS Y TEXTILES DEL BAJIO SA DE CV', 'CADENA COMERCIAL ORIENTE SA DE CV',
    'COMERCIALIZADORA ALTAMAR SA DE CV', 'DISTRIBUIDORA DE BLANCOS PACIFICO', 'GRUPO HOTELERO RIVIERA SA DE CV',
    'HOSPITALES DEL CENTRO SC', 'IMPORTADORA TEXTIL DEL GOLFO', 'LAVANDERIAS INDUSTRIALES MONTERREY',
    'MAYORISTA DEL HOGAR SA DE CV', 'NOVEDADES TEXTILES JALISCO', 'OPERADORA DE CLUBES DEPORTIVOS',
    'PROVEEDORA DE SPAS Y RESORTS', 'SERVICIOS HOSPITALARIOS DEL SURESTE', 'SUPER TIENDAS LA ESTRELLA',
    'TEXTILES FINOS DE PUEBLA', 'TIENDA TOWELL (MOSTRADOR)', 'UNIFORMES Y BLANCOS DEL VALLE',
    'VENTA DIRECTA EXPORTACION', 'ZONA LIBRE COMERCIAL SA DE CV',
];

/** Perfil anual por empresa: volumen, kilos por pieza, precio base y % de descuento. */
const MOCK_PROFILE = {
    TOWEL: {
        piezas: 9_000_000, kgPza: 0.21, precio: 31.5, precioAlza: 2.1, desc: 0.105,
        materiales: [['TOALLA', 60], ['BATA', 18], ['PONCHOS', 10], ['FELPA', 12]],
        tipos: [['CE', 55], ['RS', 25], ['CE HT', 12], ['2das / 3ras', 8]],
    },
    TEXTIL: {
        piezas: 1_500_000, kgPza: 0.44, precio: 44, precioAlza: 1.4, desc: 0.004,
        materiales: [['FELPA', 62], ['TOALLA', 28], ['BATA', 10]],
        tipos: [['CE', 70], ['RS', 20], ['2das / 3ras', 10]],
    },
};

const buildMockRecords = () => {
    const random = seededRandom(20260924);
    // Cada cliente tiene un agente fijo, así el filtrado cruzado se comporta como en la vida real.
    const agentePorCliente = Object.fromEntries(MOCK_CLIENTES.map((cliente, index) => [cliente, MOCK_AGENTES[index % MOCK_AGENTES.length]]));
    const records = [];

    Object.entries(MOCK_PROFILE).forEach(([empresa, profile]) => {
        for (let anio = 2019; anio <= 2026; anio += 1) {
            const lastMonth = anio === 2026 ? 8 : 12;
            const yearFactor = 0.8 + random() * 0.4;
            for (let mes = 1; mes <= lastMonth; mes += 1) {
                const rowsInMonth = 18;
                const weights = Array.from({ length: rowsInMonth }, () => random() ** 2 + 0.05);
                const weightTotal = weights.reduce((sum, weight) => sum + weight, 0);
                const monthPiezas = (profile.piezas / 12) * yearFactor * (0.85 + random() * 0.3);

                weights.forEach((weight) => {
                    const tipoPedido = pickWeighted(random, profile.tipos);
                    const calidad = tipoPedido === '2das / 3ras'
                        ? pickWeighted(random, [['SEGUNDAS', 70], ['TERCERA', 30]])
                        : pickWeighted(random, [['1RAS', 96], ['MUESTRAS', 4]]);
                    const cliente = MOCK_CLIENTES[Math.floor(random() * MOCK_CLIENTES.length)];
                    const piezas = monthPiezas * (weight / weightTotal);
                    const precio = (profile.precio + (anio - 2019) * profile.precioAlza) * (calidad === '1RAS' ? 1 : 0.55) * (0.9 + random() * 0.2);
                    const ventasBrutas = piezas * precio;
                    // 2019 no registraba descuentos en el histórico original.
                    const descuentos = anio === 2019 ? 0 : ventasBrutas * profile.desc * (0.7 + random() * 0.6);

                    records.push({
                        empresa,
                        anio: String(anio),
                        mes: MESES[mes - 1],
                        semestre: mes <= 6 ? '1er Semestre' : '2do Semestre',
                        tipoPedido,
                        tipoMaterial: pickWeighted(random, profile.materiales),
                        calidad,
                        cliente,
                        agente: agentePorCliente[cliente],
                        piezas,
                        kilos: piezas * profile.kgPza * (0.9 + random() * 0.2),
                        ventasBrutas,
                        descuentos,
                    });
                });
            }
        }
    });

    return records;
};

const loadRecords = () => buildMockRecords();

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
    const records = loadRecords();
    const allYears = sortValues('anio', [...new Set(records.map((record) => record.anio))]);
    const state = {
        selected: Object.fromEntries(SLICERS.map(({ key }) => [key, new Set()])),
        openPanel: null,
        search: Object.fromEntries(SLICERS.map(({ key }) => [key, ''])),
        view: Object.fromEntries([...REPORTS, { id: 'comparativo', defaultView: 'table' }].map(({ id, defaultView }) => [id, defaultView])),
        metric: Object.fromEntries([...REPORTS.map(({ id }) => id), 'comparativo'].map((id) => [id, 'importeNeto'])),
        collapsed: Object.fromEntries(REPORTS.map(({ id }) => [id, new Set()])),
        groupIds: Object.fromEntries(REPORTS.map(({ id }) => [id, new Set()])),
        compare: { anioA: allYears[allYears.length - 2] ?? allYears[0], anioB: allYears[allYears.length - 1], meses: new Set() },
    };
    const charts = {};
    const elements = {
        slicers: root.querySelector('[data-vh-slicers]'),
        summary: root.querySelector('[data-vh-summary]'),
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

    // --- Slicers -----------------------------------------------------------

    const slicerBox = (key) => elements.slicers.querySelector(`[data-vh-slicer="${key}"]`);

    /** Coincidencias del buscador: primero las que tienen datos con los filtros actuales. */
    const matchingValues = (slicer, available) => {
        const term = state.search[slicer.key].trim().toLowerCase();
        return allValues[slicer.key]
            .filter((value) => !term || String(value).toLowerCase().includes(term))
            .sort((a, b) => Number(available.has(b)) - Number(available.has(a)));
    };

    const chipHtml = (slicer, value, available) => {
        const selected = state.selected[slicer.key].has(value);
        const classes = ['vh-chip', selected && 'is-selected', !available.has(value) && 'is-empty'].filter(Boolean).join(' ');
        const label = slicer.short ? slicer.short(value) : value;
        return `<button type="button" class="${classes}" data-vh-chip="${escapeHtml(value)}" title="${escapeHtml(value)}" aria-pressed="${selected}">${escapeHtml(label)}</button>`;
    };

    const renderSwitch = (slicer, box, available) => {
        const [current] = state.selected[slicer.key];
        const option = (value, label, empty = false) => `<button type="button" role="radio" class="${[
            (current ?? '') === value && 'is-active', empty && 'is-empty',
        ].filter(Boolean).join(' ')}" data-vh-switch="${escapeHtml(value)}" aria-checked="${(current ?? '') === value}">${escapeHtml(label)}</button>`;
        box.querySelector('[data-vh-items]').innerHTML = option('', slicer.allLabel)
            + allValues[slicer.key].map((value) => option(value, value, !available.has(value))).join('');
    };

    const renderSearch = (slicer, box, available) => {
        const selected = state.selected[slicer.key];
        box.querySelector('[data-vh-pills]').innerHTML = [...selected].map((value) => `<span class="vh-pill" title="${escapeHtml(value)}">
            <span>${escapeHtml(value)}</span>
            <button type="button" data-vh-pill-remove="${escapeHtml(value)}" aria-label="Quitar ${escapeHtml(value)}">×</button>
        </span>`).join('');

        const list = box.querySelector('[data-vh-items]');
        const isOpen = state.openPanel === slicer.key;
        list.hidden = !isOpen;
        if (!isOpen) return;
        const values = matchingValues(slicer, available).filter((value) => !selected.has(value));
        list.innerHTML = values.slice(0, SUGGESTION_LIMIT).map((value, index) => `<button type="button" role="option" class="vh-option ${
            available.has(value) ? '' : 'is-empty'} ${index === 0 ? 'is-first' : ''}" data-vh-suggest="${escapeHtml(value)}">${escapeHtml(value)}</button>`).join('')
            || '<span class="vh-slicer-empty">Sin coincidencias</span>';
    };

    const renderDropdown = (slicer, box, available) => {
        const selected = state.selected[slicer.key];
        const [first] = selected;
        box.querySelector('[data-vh-dd-label]').textContent = !selected.size
            ? 'Todos' : (selected.size === 1 ? first : `${selected.size} seleccionados`);

        const isOpen = state.openPanel === slicer.key;
        box.querySelector('[data-vh-panel]').hidden = !isOpen;
        box.querySelector('[data-vh-dd-toggle]').setAttribute('aria-expanded', String(isOpen));
        if (!isOpen) return;
        box.querySelector('[data-vh-items]').innerHTML = matchingValues(slicer, available).map((value) => `<label class="vh-check ${
            available.has(value) ? '' : 'is-empty'}">
            <input type="checkbox" data-vh-check="${escapeHtml(value)}" ${selected.has(value) ? 'checked' : ''}>
            <span>${escapeHtml(value)}</span>
        </label>`).join('') || '<span class="vh-slicer-empty">Sin coincidencias</span>';
    };

    const renderSlicer = (slicer) => {
        const box = slicerBox(slicer.key);
        const available = availableValues(slicer.key);
        box.querySelector('[data-vh-clear]').hidden = !state.selected[slicer.key].size;
        box.classList.toggle('is-filtered', state.selected[slicer.key].size > 0);

        if (slicer.type === 'switch') renderSwitch(slicer, box, available);
        else if (slicer.type === 'search') renderSearch(slicer, box, available);
        else if (slicer.type === 'dropdown') renderDropdown(slicer, box, available);
        else box.querySelector('[data-vh-items]').innerHTML = allValues[slicer.key].map((value) => chipHtml(slicer, value, available)).join('');
    };

    const renderSummary = () => {
        const active = SLICERS.filter(({ key }) => state.selected[key].size);
        elements.summary.innerHTML = active.length
            ? active.map(({ key, label }) => `<span class="vh-summary-pill">${escapeHtml(label)}: <strong>${escapeHtml([...state.selected[key]].join(', '))}</strong></span>`).join('')
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

    const renderReports = () => {
        REPORTS.forEach(({ id }) => renderReport(id));
        renderReport('comparativo');
    };

    const refresh = () => {
        SLICERS.forEach(renderSlicer);
        renderSummary();
        renderReports();
    };

    // --- Montaje y eventos -----------------------------------------------------

    const controlHtml = (slicer) => {
        const label = escapeHtml(slicer.label);
        if (slicer.type === 'switch') return `<div class="vh-switch" role="radiogroup" aria-label="${label}" data-vh-items></div>`;
        if (slicer.type === 'search') {
            return `<div class="vh-combo">
                <input type="search" class="vh-input" data-vh-search placeholder="${escapeHtml(slicer.placeholder)}" aria-label="${label}" autocomplete="off">
                <div class="vh-popover vh-suggest" role="listbox" data-vh-items hidden></div>
            </div>
            <div class="vh-pills" data-vh-pills></div>`;
        }
        if (slicer.type === 'dropdown') {
            return `<div class="vh-combo">
                <button type="button" class="vh-input vh-dd-button" data-vh-dd-toggle aria-haspopup="true" aria-expanded="false">
                    <span data-vh-dd-label>Todos</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="vh-popover vh-dd-panel" data-vh-panel hidden>
                    <input type="search" class="vh-input" data-vh-search placeholder="${escapeHtml(slicer.placeholder)}" aria-label="Buscar en ${label}" autocomplete="off">
                    <div class="vh-dd-list" data-vh-items></div>
                </div>
            </div>`;
        }
        return `<div class="vh-chips" data-vh-items></div>`;
    };

    const renderSlicers = () => {
        elements.slicers.innerHTML = SLICERS.map((slicer) => `
            <div class="vh-filter vh-filter-${slicer.type}" data-vh-slicer="${slicer.key}">
                <div class="vh-filter-label">
                    <span>${escapeHtml(slicer.label)}</span>
                    <button type="button" class="vh-filter-clear" data-vh-clear title="Borrar filtro" aria-label="Borrar filtro de ${escapeHtml(slicer.label)}" hidden>×</button>
                </div>
                ${controlHtml(slicer)}
            </div>`).join('');
    };

    const slicerFor = (element) => SLICERS.find(({ key }) => key === element.closest('[data-vh-slicer]')?.dataset.vhSlicer);

    const openPanel = (key) => {
        const previous = state.openPanel;
        state.openPanel = key;
        [previous, key].filter(Boolean).forEach((panelKey) => renderSlicer(SLICERS.find((slicer) => slicer.key === panelKey)));
    };

    const pickSuggestion = (slicer, value) => {
        state.selected[slicer.key].add(value);
        state.search[slicer.key] = '';
        slicerBox(slicer.key).querySelector('[data-vh-search]').value = '';
        state.openPanel = null;
        refresh();
    };

    const bindSlicerEvents = () => {
        // mousedown en vez de click para que la sugerencia se tome antes de que el input pierda el foco.
        elements.slicers.addEventListener('mousedown', (event) => {
            const suggestion = event.target.closest('[data-vh-suggest]');
            if (!suggestion) return;
            event.preventDefault();
            pickSuggestion(slicerFor(suggestion), suggestion.dataset.vhSuggest);
        });

        elements.slicers.addEventListener('click', (event) => {
            const slicer = slicerFor(event.target);
            if (!slicer) return;
            const selected = state.selected[slicer.key];
            const target = event.target;

            const switchOption = target.closest('[data-vh-switch]');
            if (switchOption) {
                selected.clear();
                if (switchOption.dataset.vhSwitch) selected.add(switchOption.dataset.vhSwitch);
                refresh();
                return;
            }

            const chip = target.closest('[data-vh-chip]');
            if (chip) {
                const value = chip.dataset.vhChip;
                selected.has(value) ? selected.delete(value) : selected.add(value);
                refresh();
                return;
            }

            const pillRemove = target.closest('[data-vh-pill-remove]');
            if (pillRemove) {
                selected.delete(pillRemove.dataset.vhPillRemove);
                refresh();
                return;
            }

            if (target.closest('[data-vh-dd-toggle]')) {
                openPanel(state.openPanel === slicer.key ? null : slicer.key);
                if (state.openPanel) slicerBox(slicer.key).querySelector('[data-vh-panel] [data-vh-search]').focus();
                return;
            }

            if (target.closest('[data-vh-clear]')) {
                selected.clear();
                refresh();
            }
        });

        elements.slicers.addEventListener('change', (event) => {
            const checkbox = event.target.closest('[data-vh-check]');
            if (!checkbox) return;
            const selected = state.selected[slicerFor(checkbox).key];
            checkbox.checked ? selected.add(checkbox.dataset.vhCheck) : selected.delete(checkbox.dataset.vhCheck);
            refresh();
        });

        elements.slicers.addEventListener('input', (event) => {
            const input = event.target.closest('[data-vh-search]');
            if (!input) return;
            const slicer = slicerFor(input);
            state.search[slicer.key] = input.value;
            if (state.openPanel !== slicer.key) openPanel(slicer.key);
            else renderSlicer(slicer);
        });

        elements.slicers.addEventListener('focusin', (event) => {
            const input = event.target.closest('.vh-filter-search [data-vh-search]');
            if (input && state.openPanel !== slicerFor(input).key) openPanel(slicerFor(input).key);
        });

        elements.slicers.addEventListener('keydown', (event) => {
            const input = event.target.closest('[data-vh-search]');
            if (!input) return;
            const slicer = slicerFor(input);
            if (event.key === 'Escape') {
                openPanel(null);
                input.blur();
            } else if (event.key === 'Enter' && slicer.type === 'search') {
                event.preventDefault();
                const first = slicerBox(slicer.key).querySelector('[data-vh-suggest]');
                if (first) pickSuggestion(slicer, first.dataset.vhSuggest);
            }
        });

        // Clic fuera de un buscador o desplegable abierto: se cierra.
        document.addEventListener('mousedown', (event) => {
            if (!state.openPanel) return;
            if (!slicerBox(state.openPanel).querySelector('.vh-combo').contains(event.target)) openPanel(null);
        });
    };

    elements.reports.innerHTML = [
        ...REPORTS.map((report) => reportShell(report)),
        reportShell(
            { id: 'comparativo', title: 'Comparativo por Año' },
            'Usa sus propios selectores de año y mes; el resto de los filtros sí aplica.',
        ),
    ].join('');

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

    renderSlicers();
    bindSlicerEvents();
    refresh();
};

export const mountVentasHistoricas = (scope = document) => {
    scope.querySelectorAll('[data-ventas-historicas]').forEach(initVentasHistoricas);
};
