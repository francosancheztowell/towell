import { mountVentasHistoricas } from './ventas-historicas';
import { createMultiSelect } from './multi-select';

const MONTH_NAMES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const monthName = (value) => MONTH_NAMES[Number(value) - 1] ?? value;

/** multi = selección múltiple (Set, vacío = todos); el resto es un select de una sola opción. */
const FILTERS = [
    { key: 'anio', label: 'Año' },
    { key: 'mes', label: 'Mes', multi: true, format: monthName },
    { key: 'empresa', label: 'Empresa' },
    { key: 'tipo', label: 'Tipo', multi: true },
    { key: 'cliente', label: 'Cliente', multi: true },
    { key: 'tamano', label: 'Tamaño', multi: true },
    { key: 'articulo', label: 'Artículo', multi: true },
];
/** Grupos de columnas que se pueden ocultar desde el menú "Columnas". */
const COLUMN_GROUPS = [['plan', 'Plan'], ['pedido', 'Pedido'], ['real', 'Real'], ['delta', 'Δ (diferencia)'], ['cumplimiento', 'Cumplimiento y estatus']];
const allColumns = () => new Set(COLUMN_GROUPS.map(([key]) => key));

const DESGLOSES = [['', '(ninguno)'], ['empresa', 'Empresa'], ['tipo', 'Tipo de pedido'], ['cliente', 'Cliente'], ['articulo', 'Artículo']];

const DETAIL_LEVELS = [
    ['empresa'], ['tipo'], [(item) => `${item.clienteCodigo} ${item.cliente}`],
    [(item) => `${item.articuloCodigo} ${item.articulo} · ${item.linea} · ${item.tamano} · ${item.color}`],
];

/** Orden de negocio de los tipos de pedido; los no listados van al final en orden alfabético. */
const TIPO_ORDEN = ['CE', 'CE HT', 'RS', '2das / 3ra'];
const tipoRank = (value) => {
    const index = TIPO_ORDEN.indexOf(String(value).trim());
    return index === -1 ? TIPO_ORDEN.length : index;
};
const compareGroup = (selector) => (selector === 'tipo'
    ? ([a], [b]) => tipoRank(a) - tipoRank(b) || String(a).localeCompare(String(b))
    : ([a], [b]) => String(a).localeCompare(String(b)));

const emptyFilters = () => Object.fromEntries(FILTERS.map(({ key, multi }) => [key, multi ? new Set() : '']));

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

const bindTabs = (root) => {
    root.querySelectorAll('[data-pvoc-tab]').forEach((button) => button.addEventListener('click', () => {
        const activeTab = button.dataset.pvocTab;
        root.querySelectorAll('[data-pvoc-tab]').forEach((tab) => {
            const active = tab === button;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
        });
        root.querySelectorAll('[data-pvoc-panel]').forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.pvocPanel !== activeTab));
        root.dispatchEvent(new CustomEvent('pvoc:tab', { detail: activeTab }));
    }));

    // Subsecciones dentro de una pestaña (p.ej. Compara › Resumen General / Análisis Histórico).
    root.querySelectorAll('[data-pvoc-subtab]').forEach((button) => button.addEventListener('click', () => {
        const panel = button.closest('[data-pvoc-panel]');
        const active = button.dataset.pvocSubtab;
        panel.querySelectorAll('[data-pvoc-subtab]').forEach((tab) => {
            tab.classList.toggle('is-active', tab === button);
            tab.setAttribute('aria-selected', String(tab === button));
        });
        panel.querySelectorAll('[data-pvoc-subpanel]').forEach((subpanel) =>
            subpanel.classList.toggle('is-hidden', subpanel.dataset.pvocSubpanel !== active));
    }));
};

/**
 * Botón "Filtrar" del navbar (fuera del root, vía @section('navbar-right')): abre el panel con los
 * filtros de Compara. Solo se muestra en la pestaña Compara; Ventas históricas tiene sus propios filtros.
 */
const bindFilterPanel = (root) => {
    const button = document.getElementById('btn-filtrar-ventas-compara');
    const panel = root.querySelector('[data-pvoc-filter-panel]');
    if (!button || !panel) return;

    const setOpen = (open) => {
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
    };

    button.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(panel.hidden);
    });
    panel.querySelector('[data-pvoc-filter-close]').addEventListener('click', () => setOpen(false));
    // Los desplegables de cada filtro viven dentro del panel, así que un clic en ellos no lo cierra.
    document.addEventListener('mousedown', (event) => {
        if (!panel.hidden && !panel.contains(event.target) && !button.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden && !panel.querySelector('.pvoc-multi-panel:not([hidden])')) setOpen(false);
    });
    root.addEventListener('pvoc:tab', (event) => {
        button.hidden = event.detail !== 'summary';
        setOpen(false);
    });
};

document.querySelectorAll('[data-ventas-pvoc-dashboard]').forEach(async (root) => {
    // Pestañas y ventas históricas no dependen del payload PV vs OC: funcionan aunque éste falle.
    bindTabs(root);
    bindFilterPanel(root);
    mountVentasHistoricas(root);

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
        comparison: 'plan-pedido', grouping: 'origin',
        filters: emptyFilters(),
        desglose: '',
        expanded: { summary: new Set(), analisis: new Set() },
        columns: { summary: allColumns(), analisis: allColumns() },
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
    }[key]);

    const matchesFilter = (record, { key, multi }) => {
        const selected = state.filters[key];
        const value = String(filterValue(record, key));
        return multi ? (!selected.size || selected.has(value)) : (!selected || value === selected);
    };

    const filteredRecords = () => records.filter((record) => FILTERS.every((filter) => matchesFilter(record, filter)));

    /** Nivel de detalle bajo cada mes: el elegido en Desglose o, sin él, Empresa › Tipo › Cliente › Artículo. */
    const detailLevels = () => (state.desglose
        ? [[(item) => String(filterValue(item, state.desglose))]]
        : DETAIL_LEVELS);

    /** label = encabezado de la primera columna · expandDepth = niveles que abre "Expandir todo". */
    const TABLES = {
        summary: { label: 'Empresa / Tipo / Cliente', expandDepth: 3, levels: () => DETAIL_LEVELS },
        analisis: {
            label: 'Año / Mes',
            expandDepth: 2,
            dashZero: true,
            levels: () => [
                ['anio', (value) => `Año ${value}`],
                ['mes', (value) => monthName(value)],
                ...detailLevels(),
            ],
        },
    };

    const fragment = (html) => document.createRange().createContextualFragment(html);

    const renderFilters = () => {
        const fields = FILTERS.map(({ key, label, multi, format }) => {
            const values = [...new Set(records.map((record) => String(filterValue(record, key))))].sort();
            if (multi) {
                return createMultiSelect({ label, values, format, selected: state.filters[key], onChange: renderTables }).element;
            }
            return fragment(`<label class="pvoc-select-label">${label}
                <select data-pvoc-filter="${key}">
                    <option value="">Todos</option>
                    ${values.map((value) => `<option value="${escapeHtml(value)}" ${state.filters[key] === value ? 'selected' : ''}>${escapeHtml(value)}</option>`).join('')}
                </select>
            </label>`);
        });
        const dashboardControls = fragment(`<label class="pvoc-select-label">Columnas
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
            </label>`);
        elements.filters.replaceChildren(...fields, dashboardControls);
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
    const isVisible = (panel, group) => state.columns[panel].has(group);
    const visibleColumnCount = (panel) => 1 + ['plan', 'pedido', 'real', 'delta'].filter((group) => isVisible(panel, group)).length * METRICS.length
        + (isVisible(panel, 'cumplimiento') ? 2 : 0);

    const numberCells = (totals, panel) => {
        const { dashZero } = TABLES[panel];
        const [left, right] = comparisonSeries();
        const percentage = totals[left].piezas ? (totals[right].piezas / totals[left].piezas) * 100 : 0;
        const seriesCells = Object.keys(SERIES).filter((series) => isVisible(panel, series)).map((series) => METRICS.map(([metric]) => {
            const value = totals[series][metric];
            return `<td class="pvoc-number pvoc-${series}">${dashZero && !value ? '–' : formatNumber(value)}</td>`;
        }).join('')).join('');
        const deltaCells = isVisible(panel, 'delta')
            ? METRICS.map(([metric]) => totals[right][metric] - totals[left][metric]).map((value) =>
                `<td class="pvoc-number pvoc-delta ${value < 0 ? 'is-negative' : 'is-positive'}">${value > 0 ? '+' : ''}${formatNumber(value)}</td>`).join('')
            : '';
        const status = percentage >= 100 ? ['En meta', 'is-success'] : percentage >= 85 ? ['Parcial', 'is-warning'] : ['Bajo', 'is-danger'];
        const complianceCells = isVisible(panel, 'cumplimiento')
            ? `<td class="pvoc-number">${percentage.toFixed(1)}%</td><td><span class="pvoc-status ${status[1]}">${status[0]}</span></td>`
            : '';
        return `${seriesCells}${deltaCells}${complianceCells}`;
    };

    const tableHeader = (panel) => {
        const series = Object.entries(SERIES).filter(([key]) => isVisible(panel, key));
        const delta = isVisible(panel, 'delta');
        const compliance = isVisible(panel, 'cumplimiento');
        return `<table class="pvoc-table"><thead>
            <tr class="pvoc-table-groups">
                <th rowspan="2" class="pvoc-label">${TABLES[panel].label}</th>
                ${series.map(([, { label, className }]) => `<th colspan="3" class="pvoc-${className}">${label}</th>`).join('')}
                ${delta ? `<th colspan="3" class="pvoc-delta">Δ ${comparisonSeries().map((key) => SERIES[key].label).join(' − ')}</th>` : ''}
                ${compliance ? '<th rowspan="2">Cumpl.</th><th rowspan="2">Estatus</th>' : ''}
            </tr>
            <tr>${series.map(([key]) => METRICS.map(([, label]) => `<th class="pvoc-${key}">${label}</th>`).join('')).join('')}
                ${delta ? METRICS.map(([, label]) => `<th class="pvoc-delta">${label}</th>`).join('') : ''}</tr>
        </thead><tbody>`;
    };

    const buildTree = (items, levels, parentKey = '') => {
        if (!levels.length) return [];
        const [selector, formatter] = levels[0];
        const groups = new Map();
        items.forEach((item) => {
            const value = typeof selector === 'function' ? selector(item) : item[selector];
            if (!groups.has(value)) groups.set(value, []);
            groups.get(value).push(item);
        });
        return [...groups.entries()].sort(compareGroup(selector)).map(([value, children], index) => {
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
        const row = `<tr class="pvoc-level-${Math.min(level, 3)}"><td class="pvoc-label">${indentation}${toggle}${escapeHtml(node.label)}</td>${numberCells(sum(node.items), panel)}</tr>`;
        return row + (isExpanded ? node.children.map((child) => renderNode(child, level + 1, panel)).join('') : '');
    };

    const tableContainer = (panel) => root.querySelector(`[data-pvoc-table="${panel}"]`);

    const renderTable = (panel) => {
        const filtered = filteredRecords();
        const rows = buildTree(filtered, TABLES[panel].levels()).map((node) => renderNode(node, 0, panel)).join('');
        tableContainer(panel).innerHTML = `${tableHeader(panel)}${rows || `<tr><td colspan="${visibleColumnCount(panel)}" class="pvoc-empty">No hay datos para los filtros seleccionados.</td></tr>`}
            <tr class="pvoc-row-total"><td class="pvoc-label">Total general</td>${numberCells(sum(filtered), panel)}</tr></tbody></table>`;
    };

    const toggleNode = (panel, node) => {
        state.expanded[panel].has(node) ? state.expanded[panel].delete(node) : state.expanded[panel].add(node);
        renderTable(panel);
    };

    /** Abre los nodos hasta la profundidad indicada recorriendo el árbol, sin re-renderizar por nivel. */
    const expandTo = (panel, depth) => {
        const walk = (nodes, level) => nodes.forEach((node) => {
            if (level >= depth || !node.children.length) return;
            state.expanded[panel].add(node.id);
            walk(node.children, level + 1);
        });
        walk(buildTree(filteredRecords(), TABLES[panel].levels()), 0);
        renderTable(panel);
    };

    /** Con los filtros escondidos en el panel, el botón del navbar indica cuántos hay activos. */
    const filterButtonLabel = document.querySelector('#btn-filtrar-ventas-compara span');
    const syncFilterCount = () => {
        if (!filterButtonLabel) return;
        const active = FILTERS.filter(({ key, multi }) => (multi ? state.filters[key].size : state.filters[key])).length;
        filterButtonLabel.textContent = active ? `Filtrar (${active})` : 'Filtrar';
    };

    const renderTables = () => {
        Object.keys(TABLES).forEach(renderTable);
        syncFilterCount();
    };

    Object.keys(TABLES).forEach((panel) => {
        const container = tableContainer(panel);
        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-pvoc-node]');
            if (button) toggleNode(panel, button.dataset.pvocNode);
        });
        // Doble clic en cualquier parte de la fila la abre o cierra (p.ej. un mes para desglosarlo).
        container.addEventListener('dblclick', (event) => {
            if (event.target.closest('[data-pvoc-node]')) return;
            const button = event.target.closest('tr')?.querySelector('[data-pvoc-node]');
            if (button) toggleNode(panel, button.dataset.pvocNode);
        });
    });

    root.querySelector('[data-pvoc-clear]').addEventListener('click', () => {
        state.filters = emptyFilters(); renderFilters(); renderTables();
    });
    root.querySelectorAll('[data-pvoc-expand]').forEach((button) => button.addEventListener('click', () => {
        const panel = button.dataset.pvocExpand;
        expandTo(panel, TABLES[panel].expandDepth);
    }));
    root.querySelectorAll('[data-pvoc-collapse]').forEach((button) => button.addEventListener('click', () => {
        const panel = button.dataset.pvocCollapse;
        state.expanded[panel].clear(); renderTable(panel);
    }));

    const desgloseSelect = root.querySelector('[data-pvoc-desglose]');
    desgloseSelect.innerHTML = DESGLOSES.map(([key, label]) => `<option value="${key}">${escapeHtml(label)}</option>`).join('');
    desgloseSelect.addEventListener('change', () => {
        state.desglose = desgloseSelect.value;
        renderTable('analisis');
    });

    root.querySelectorAll('[data-pvoc-columns]').forEach((wrapper) => {
        const panel = wrapper.dataset.pvocColumns;
        const toggle = wrapper.querySelector('[data-pvoc-columns-toggle]');
        const menu = wrapper.querySelector('[data-pvoc-columns-menu]');
        const setOpen = (open) => {
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        };
        menu.innerHTML = COLUMN_GROUPS.map(([key, label]) => `<label class="pvoc-multi-option">
            <input type="checkbox" value="${key}" ${isVisible(panel, key) ? 'checked' : ''}><span>${escapeHtml(label)}</span>
        </label>`).join('');
        toggle.addEventListener('click', () => setOpen(menu.hidden));
        menu.addEventListener('change', (event) => {
            event.target.checked ? state.columns[panel].add(event.target.value) : state.columns[panel].delete(event.target.value);
            renderTable(panel);
        });
        document.addEventListener('mousedown', (event) => { if (!wrapper.contains(event.target)) setOpen(false); });
    });

    renderFilters();
    renderTables();
    expandTo('analisis', 1);
});
