const FILTERS = [
    ['anio', 'Año'], ['mes', 'Mes'], ['empresa', 'Empresa'], ['tipo', 'Tipo'],
    ['cliente', 'Cliente'], ['tamano', 'Tamaño'], ['articulo', 'Artículo'], ['estatus', 'Estatus OC'],
];

const METRICS = [['piezas', 'Piezas'], ['kilos', 'Kilos'], ['vn', 'V.N.']];
const SERIES = {
    plan: { label: 'Plan', className: 'plan' },
    pedido: { label: 'Pedido', className: 'pedido' },
    real: { label: 'Real', className: 'real' },
};

const formatNumber = (value) => new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(value || 0);
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));

/**
 * PvVsOcPayloadBuilder devuelve 'GZ:' + base64(gzip(json)). El navegador ya
 * trae DecompressionStream nativo, así que no hace falta ninguna librería.
 */
const inflateGzipBase64 = async (base64) => {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
    const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('gzip'));
    const buffer = await new Response(stream).arrayBuffer();
    return new TextDecoder('utf-8').decode(buffer);
};

/** raw puede ser el objeto plano de siempre (mock) o el string comprimido del builder real. */
const decodePayload = async (raw) => {
    if (typeof raw !== 'string' || !raw.startsWith('GZ:')) return raw;
    const json = await inflateGzipBase64(raw.slice(3));
    return JSON.parse(json);
};

/** TOWEL/TEXTIL → Towel/Textil. Solo para mostrar, no cambia el valor de filtrado. */
const normalizeEmpresa = (value) => {
    const text = String(value ?? '');
    return text.length ? text.charAt(0) + text.slice(1).toLowerCase() : text;
};

const decodeCompactRow = (row, dict, sf, nf) => {
    const record = {};
    sf.forEach((name, index) => { record[name] = dict[row[index]]; });
    nf.forEach((name, index) => { record[name] = row[sf.length + index]; });
    return record;
};

const DIMENSION_FIELDS = ['empresa', 'tipo', 'cve', 'nombreCte', 'artCode', 'artName', 'config', 'tamano', 'colorCode', 'colorName', 'anio', 'mes', 'semana'];
const dimensionKey = (row) => DIMENSION_FIELDS.map((field) => row[field]).join('␟');
const emptyMetrics = () => ({ piezas: 0, kilos: 0, vn: 0 });
const addMetrics = (target, row) => {
    target.piezas += row.piezas || 0;
    target.kilos += row.kilos || 0;
    target.vn += row.vn || 0;
};

/** OC trae status por línea; al combinar varias líneas en un mismo combo, gana el peor estatus. */
const ESTADO_PRIORIDAD = { Pendiente: 3, Parcial: 2, Entregado: 1 };

/**
 * plan/oc/real llegan como líneas de factura/pedido/pronóstico sueltas, sin cruzar entre sí.
 * Acá se agrupan por la combinación de dimensiones (empresa, tipo, cliente, artículo, color,
 * tamaño, año/mes/semana) y se arma un registro "ancho" por combo, igual al shape que ya
 * entienden buildTree()/sum() más abajo.
 */
const expandCompactPayload = (payload) => {
    const { sf, nf, dict, plan = [], oc = [], real = [] } = payload;
    const combos = new Map();

    const merge = (rows, series) => {
        rows.forEach((raw) => {
            const row = decodeCompactRow(raw, dict, sf, nf);
            const key = dimensionKey(row);
            let combo = combos.get(key);
            if (!combo) {
                combo = {
                    anio: row.anio, mes: row.mes, semana: row.semana,
                    empresa: normalizeEmpresa(row.empresa), tipo: row.tipo,
                    clienteCodigo: row.cve, cliente: row.nombreCte,
                    articuloCodigo: row.artCode, articulo: row.artName,
                    linea: row.config, tamano: row.tamano, color: row.colorName,
                    estatus: '',
                    plan: emptyMetrics(), pedido: emptyMetrics(), real: emptyMetrics(),
                };
                combos.set(key, combo);
            }
            addMetrics(combo[series], row);
            if (series === 'pedido' && row.status) {
                const actual = ESTADO_PRIORIDAD[combo.estatus] || 0;
                const nuevo = ESTADO_PRIORIDAD[row.status] || 0;
                if (nuevo > actual) combo.estatus = row.status;
            }
        });
    };

    merge(plan, 'plan');
    merge(oc, 'pedido');
    merge(real, 'real');

    return [...combos.values()];
};

document.querySelectorAll('[data-ventas-pvoc-dashboard]').forEach(async (root) => {
    let raw;
    try {
        raw = JSON.parse(root.dataset.dashboard || 'null');
    } catch {
        return;
    }

    let payload;
    try {
        payload = await decodePayload(raw);
    } catch (error) {
        console.error('No se pudo decodificar el payload del dashboard de Ventas.', error);
        return;
    }

    // Sin payload (p.ej. error del servidor): el componente Livewire ya muestra su propio aviso.
    if (!payload) return;

    const records = Array.isArray(payload.records)
        ? payload.records
        : (Array.isArray(payload.sf) ? expandCompactPayload(payload) : []);

    const state = {
        activeTab: 'summary', comparison: 'plan-pedido', grouping: 'origin',
        filters: Object.fromEntries(FILTERS.map(([key]) => [key, ''])),
        expanded: { summary: new Set(), history: new Set() },
    };
    const elements = {
        filters: root.querySelector('[data-pvoc-filters]'),
        toast: root.querySelector('[data-pvoc-toast]'),
    };

    const notify = (message) => {
        elements.toast.textContent = message;
        elements.toast.classList.add('is-visible');
        window.setTimeout(() => elements.toast.classList.remove('is-visible'), 2800);
    };

    const filterValue = (record, key) => ({
        anio: record.anio,
        mes: String(record.mes).padStart(2, '0'),
        empresa: record.empresa,
        tipo: record.tipo,
        cliente: `${record.clienteCodigo} ${record.cliente}`,
        tamano: record.tamano,
        articulo: `${record.articuloCodigo} ${record.articulo}`,
        estatus: record.estatus,
    }[key]);

    const filteredRecords = () => records.filter((record) => Object.entries(state.filters)
        .every(([key, value]) => !value || String(filterValue(record, key)) === value));

    const renderFilters = () => {
        const dataFilters = FILTERS.map(([key, label]) => {
            const values = [...new Set(records.map((record) => String(filterValue(record, key))))].sort();
            return `<label class="pvoc-select-label">${label}
                <select data-pvoc-filter="${key}">
                    <option value="">Todos</option>
                    ${values.map((value) => `<option value="${escapeHtml(value)}" ${state.filters[key] === value ? 'selected' : ''}>${escapeHtml(value)}</option>`).join('')}
                </select>
            </label>`;
        }).join('');
        const dashboardControls = `<label class="pvoc-select-label">Columnas
                <select data-pvoc-grouping>
                    <option value="origin" ${state.grouping === 'origin' ? 'selected' : ''}>Por origen</option>
                    <option value="metric" ${state.grouping === 'metric' ? 'selected' : ''}>Por medida</option>
                </select>
            </label>
            <label class="pvoc-select-label">Comparar Δ y %
                <select data-pvoc-comparison>
                    <option value="plan-pedido" ${state.comparison === 'plan-pedido' ? 'selected' : ''}>Plan vs Pedido</option>
                    <option value="plan-real" ${state.comparison === 'plan-real' ? 'selected' : ''}>Plan vs Real</option>
                    <option value="pedido-real" ${state.comparison === 'pedido-real' ? 'selected' : ''}>Pedido vs Real</option>
                </select>
            </label>`;
        elements.filters.innerHTML = `${dataFilters}${dashboardControls}`;
        elements.filters.querySelectorAll('[data-pvoc-filter]').forEach((select) => select.addEventListener('change', () => {
            state.filters[select.dataset.pvocFilter] = select.value;
            renderTables();
        }));
        elements.filters.querySelector('[data-pvoc-grouping]').addEventListener('change', (event) => {
            state.grouping = event.target.value;
            notify(state.grouping === 'origin' ? 'Columnas agrupadas por origen.' : 'La vista por medida estará disponible con datos reales.');
        });
        elements.filters.querySelector('[data-pvoc-comparison]').addEventListener('change', (event) => {
            state.comparison = event.target.value;
            renderTables();
        });
    };

    const sum = (items) => ['plan', 'pedido', 'real'].reduce((totals, series) => {
        METRICS.forEach(([metric]) => {
            totals[series][metric] = items.reduce((total, item) => total + Number(item[series][metric] || 0), 0);
        });
        return totals;
    }, {
        plan: { piezas: 0, kilos: 0, vn: 0 },
        pedido: { piezas: 0, kilos: 0, vn: 0 },
        real: { piezas: 0, kilos: 0, vn: 0 },
    });

    const comparisonSeries = () => state.comparison.split('-');
    const numberCells = (totals) => {
        const [left, right] = comparisonSeries();
        const percentage = totals[left].piezas ? (totals[right].piezas / totals[left].piezas) * 100 : 0;
        const deltaCells = METRICS.map(([metric]) => totals[right][metric] - totals[left][metric]).map((value) =>
            `<td class="pvoc-number pvoc-delta ${value < 0 ? 'is-negative' : 'is-positive'}">${value > 0 ? '+' : ''}${formatNumber(value)}</td>`).join('');
        const seriesCells = Object.keys(SERIES).map((series) => METRICS.map(([metric]) =>
            `<td class="pvoc-number pvoc-${series}">${formatNumber(totals[series][metric])}</td>`).join('')).join('');
        const status = percentage >= 100 ? ['En meta', 'is-success'] : percentage >= 85 ? ['Parcial', 'is-warning'] : ['Bajo', 'is-danger'];
        return `${seriesCells}${deltaCells}<td class="pvoc-number">${percentage.toFixed(1)}%</td><td><span class="pvoc-status ${status[1]}">${status[0]}</span></td>`;
    };

    const tableHeader = () => `<table class="pvoc-table"><thead>
        <tr class="pvoc-table-groups">
            <th rowspan="2" class="pvoc-label">Empresa / Tipo / Cliente</th>
            ${Object.entries(SERIES).map(([, series]) => `<th colspan="3" class="pvoc-${series.className}">${series.label}</th>`).join('')}
            <th colspan="3" class="pvoc-delta">Δ ${comparisonSeries().map((series) => SERIES[series].label).join(' − ')}</th>
            <th rowspan="2">Cumpl.</th><th rowspan="2">Estatus</th>
        </tr>
        <tr>${Object.keys(SERIES).map((series) => METRICS.map(([, label]) => `<th class="pvoc-${series}">${label}</th>`).join('')).join('')}
            ${METRICS.map(([, label]) => `<th class="pvoc-delta">${label}</th>`).join('')}</tr>
    </thead><tbody>`;

    const buildTree = (items, levels, parentKey = '') => {
        if (!levels.length) return [];
        const [selector, formatter] = levels[0];
        const groups = new Map();
        items.forEach((item) => {
            const value = typeof selector === 'function' ? selector(item) : item[selector];
            if (!groups.has(value)) groups.set(value, []);
            groups.get(value).push(item);
        });
        return [...groups.entries()].sort(([a], [b]) => String(a).localeCompare(String(b))).map(([value, children], index) => {
            const id = `${parentKey}/${value}-${index}`;
            return {
                id,
                label: formatter ? formatter(value, children[0]) : value,
                items: children,
                children: buildTree(children, levels.slice(1), id),
            };
        });
    };

    const renderNode = (node, level, panel) => {
        const expandable = node.children.length > 0;
        const isExpanded = state.expanded[panel].has(node.id);
        const indentation = '&nbsp;'.repeat(level * 4);
        const toggle = expandable
            ? `<button type="button" class="pvoc-expander" data-pvoc-node="${escapeHtml(node.id)}" aria-expanded="${isExpanded}">${isExpanded ? '▾' : '▸'}</button>`
            : '<span class="pvoc-expander-placeholder">•</span>';
        const row = `<tr class="pvoc-level-${Math.min(level, 3)}"><td class="pvoc-label">${indentation}${toggle}${escapeHtml(node.label)}</td>${numberCells(sum(node.items))}</tr>`;
        return row + (isExpanded ? node.children.map((child) => renderNode(child, level + 1, panel)).join('') : '');
    };

    const renderTable = (panel) => {
        const container = root.querySelector(`[data-pvoc-table="${panel}"]`);
        const filtered = filteredRecords();
        const levels = panel === 'summary'
            ? [
                ['empresa'], ['tipo'], [(item) => `${item.clienteCodigo} ${item.cliente}`],
                [(item) => `${item.articuloCodigo} ${item.articulo} · ${item.linea} · ${item.tamano} · ${item.color}`],
            ]
            : [
                ['anio', (value) => `Año ${value}`],
                [(item) => String(item.mes).padStart(2, '0'), (value) => `Mes ${value}`],
                ['semana', (value) => `Semana ${String(value).padStart(2, '0')}`],
            ];
        const rows = buildTree(filtered, levels).map((node) => renderNode(node, 0, panel)).join('');
        container.innerHTML = `${tableHeader()}${rows || '<tr><td colspan="15" class="pvoc-empty">No hay datos para los filtros seleccionados.</td></tr>'}
            <tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${numberCells(sum(filtered))}</tr></tbody></table>`;
        container.querySelectorAll('[data-pvoc-node]').forEach((button) => button.addEventListener('click', () => {
            const node = button.dataset.pvocNode;
            state.expanded[panel].has(node) ? state.expanded[panel].delete(node) : state.expanded[panel].add(node);
            renderTable(panel);
        }));
    };

    const expandAll = (panel) => {
        const expandPass = () => root.querySelectorAll(`[data-pvoc-table="${panel}"] [data-pvoc-node]`)
            .forEach((node) => state.expanded[panel].add(node.dataset.pvocNode));
        expandPass(); renderTable(panel); expandPass(); renderTable(panel); expandPass(); renderTable(panel);
    };
    const renderTables = () => { renderTable('summary'); renderTable('history'); };

    root.querySelectorAll('[data-pvoc-tab]').forEach((button) => button.addEventListener('click', () => {
        state.activeTab = button.dataset.pvocTab;
        root.querySelectorAll('[data-pvoc-tab]').forEach((tab) => {
            const active = tab === button;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
        });
        root.querySelectorAll('[data-pvoc-panel]').forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.pvocPanel !== state.activeTab));
    }));
    root.querySelector('[data-pvoc-clear]').addEventListener('click', () => {
        state.filters = Object.fromEntries(FILTERS.map(([key]) => [key, ''])); renderFilters(); renderTables();
    });
    root.querySelector('[data-pvoc-filter-toggle]').addEventListener('click', (event) => {
        const hidden = elements.filters.classList.toggle('is-hidden');
        event.currentTarget.setAttribute('aria-expanded', String(!hidden));
        event.currentTarget.querySelector('.fa-chevron-up, .fa-chevron-down').className = `fa-solid fa-chevron-${hidden ? 'down' : 'up'}`;
    });
    root.querySelectorAll('[data-pvoc-expand]').forEach((button) => button.addEventListener('click', () => expandAll(button.closest('[data-pvoc-panel]').dataset.pvocPanel)));
    root.querySelectorAll('[data-pvoc-collapse]').forEach((button) => button.addEventListener('click', () => {
        const panel = button.closest('[data-pvoc-panel]').dataset.pvocPanel;
        state.expanded[panel].clear(); renderTable(panel);
    }));
    renderFilters();
    renderTables();
});
