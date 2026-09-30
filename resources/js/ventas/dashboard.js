import { http } from '../utils/http';
import { mountVentasHistoricas } from './ventas-historicas';
import { createMultiSelect } from './multi-select';
import { bindRowSelection, clearRowSelection } from './row-selection';

const MONTH_NAMES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const monthName = (value) => MONTH_NAMES[Number(value) - 1] ?? value;

/** multi = selección múltiple (Set, vacío = todos); el resto es un select de una sola opción. */
const FILTERS = [
    { key: 'anio', label: 'Año', multi: true },
    { key: 'mes', label: 'Mes', multi: true, format: monthName },
    { key: 'empresa', label: 'Empresa' },
    { key: 'tipo', label: 'Tipo', multi: true },
    { key: 'cliente', label: 'Cliente', multi: true },
    { key: 'tamano', label: 'Tamaño', multi: true },
    { key: 'articulo', label: 'Artículo', multi: true },
];
/** El menú "Columnas" oculta medidas (Piezas, Kg, V.B., Desc., V.N.) en todas las series a la vez. */
const allColumns = () => new Set(METRICS.map(([key]) => key));

const DESGLOSES = [['empresa', 'Empresa'], ['tipo', 'Tipo de pedido'], ['cliente', 'Cliente'], ['articulo', 'Artículo']];

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
/** Towel siempre antes que Textil (los valores ya vienen normalizados por normalizeEmpresa). */
const EMPRESA_ORDEN = ['Towel', 'Textil'];
const empresaRank = (value) => {
    const index = EMPRESA_ORDEN.indexOf(String(value));
    return index === -1 ? EMPRESA_ORDEN.length : index;
};
const RANKS = { tipo: tipoRank, empresa: empresaRank };
/** key = campo del nivel ('tipo', 'empresa'…); los niveles con selector de función lo declaran aparte. */
const compareGroup = (key) => {
    const rank = RANKS[key];
    return rank
        ? ([a], [b]) => rank(a) - rank(b) || String(a).localeCompare(String(b))
        : ([a], [b]) => String(a).localeCompare(String(b));
};

const emptyFilters = () => Object.fromEntries(FILTERS.map(({ key, multi }) => [key, multi ? new Set() : '']));

/** Medidas de cada serie (vb/desc/vn vienen del payload: AMOUNT, AMOUNTDES, AMOUNTNETO). */
const METRICS = [['piezas', 'Piezas'], ['kilos', 'Kg'], ['vb', 'V.B.'], ['desc', 'Desc.'], ['vn', 'V.N.']];
const SERIES = {
    plan: { label: 'Plan', className: 'plan' },
    pedido: { label: 'Pedido', className: 'pedido' },
    real: { label: 'Real', className: 'real' },
};

const formatNumber = (value) => new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(value || 0);
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));

/** TOWEL/TEXTIL → Towel/Textil. Solo para mostrar, no cambia el valor de filtrado. */
const normalizeEmpresa = (value) => {
    const text = String(value ?? '');
    return text.length ? text.charAt(0) + text.slice(1).toLowerCase() : text;
};

const emptyMetrics = () => Object.fromEntries(METRICS.map(([metric]) => [metric, 0]));
const addMetrics = (target, row) => {
    METRICS.forEach(([metric]) => { target[metric] += row[metric] || 0; });
};

/**
 * PvVsOcPayloadBuilder manda { sf, nf, series, dict, rows }: Plan, Pedido y Real ya vienen cruzados
 * en SQL, una fila por combo (índices al diccionario de sf seguidos de nf × series). Aquí solo se
 * arma el objeto de cada combo; los valores de filtro se calculan una vez y no en cada filtrado.
 */
const decodePayload = ({ sf, nf, series, dict, rows }) => rows.map((row) => {
    const field = Object.fromEntries(sf.map((name, index) => [name, dict[row[index]] ?? '']));
    const record = {
        anio: field.anio, mes: field.mes,
        empresa: normalizeEmpresa(field.empresa), tipo: field.tipo,
        clienteCodigo: field.cve, cliente: field.nombreCte,
        articuloCodigo: field.artCode, articulo: field.artName,
        linea: field.config, tamano: field.tamano, color: field.colorName,
    };
    series.forEach((serie, serieIndex) => {
        const offset = sf.length + serieIndex * nf.length;
        record[serie] = Object.fromEntries(nf.map((metric, index) => [metric, Number(row[offset + index]) || 0]));
    });
    record.filtros = {
        anio: record.anio,
        mes: record.mes,
        empresa: record.empresa,
        tipo: record.tipo,
        cliente: `${record.clienteCodigo} ${record.cliente}`,
        tamano: record.tamano,
        articulo: `${record.articuloCodigo} ${record.articulo}`,
    };
    return record;
});

/** Aviso de error en lugar del "Cargando…" de cada contenedor. */
const showLoadError = (containers, message) => containers.forEach((container) => {
    container.innerHTML = `<div class="ventas-pvoc-alert" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <span>${escapeHtml(message)}</span></div>`;
});

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
 * Botón "Filtrar" del navbar (fuera del root, vía @section('navbar-right')): abre el panel de filtros
 * de la pestaña activa (Compara o Ventas históricas). Cada pestaña avisa cuántos filtros tiene activos
 * con el evento 'pvoc:filtros' ({ tab, count }) y el botón muestra el de la pestaña visible.
 */
const bindFilterPanel = (root) => {
    const button = document.getElementById('btn-filtrar-ventas-compara');
    const panels = {
        summary: { element: root.querySelector('[data-pvoc-filter-panel]'), title: 'Filtrar Compara' },
        history: { element: root.querySelector('[data-vh-filter-panel]'), title: 'Filtrar Ventas históricas' },
    };
    if (!button || Object.values(panels).some(({ element }) => !element)) return;

    const label = button.querySelector('span');
    const counts = { summary: 0, history: 0 };
    let activeTab = 'summary';
    const panel = () => panels[activeTab].element;

    const setOpen = (open) => {
        Object.values(panels).forEach(({ element }) => { element.hidden = true; });
        panel().hidden = !open;
        button.setAttribute('aria-expanded', String(open));
    };
    const syncButton = () => {
        button.title = panels[activeTab].title;
        button.setAttribute('aria-controls', panel().id);
        if (label) label.textContent = counts[activeTab] ? `Filtrar (${counts[activeTab]})` : 'Filtrar';
    };

    button.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(panel().hidden);
    });
    Object.values(panels).forEach(({ element }) =>
        element.querySelector('[data-pvoc-filter-close]').addEventListener('click', () => setOpen(false)));
    // Los desplegables de cada filtro viven dentro del panel, así que un clic en ellos no lo cierra.
    document.addEventListener('mousedown', (event) => {
        if (!panel().hidden && !panel().contains(event.target) && !button.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel().hidden && !panel().querySelector('.pvoc-multi-panel:not([hidden])')) setOpen(false);
    });
    root.addEventListener('pvoc:tab', (event) => {
        setOpen(false);
        activeTab = event.detail;
        syncButton();
    });
    root.addEventListener('pvoc:filtros', (event) => {
        counts[event.detail.tab] = event.detail.count;
        syncButton();
    });
};

document.querySelectorAll('[data-ventas-pvoc-dashboard]').forEach(async (root) => {
    // Pestañas y ventas históricas no dependen del payload PV vs OC: funcionan aunque éste falle.
    bindTabs(root);
    bindFilterPanel(root);
    mountVentasHistoricas(root, (container) => showLoadError([container], 'No se pudieron cargar las ventas históricas. Intenta nuevamente en unos minutos.'));

    let records;
    try {
        records = decodePayload(await http.get(root.dataset.comparaUrl));
    } catch (error) {
        console.error('No se pudo cargar el dashboard de Ventas.', error);
        showLoadError(root.querySelectorAll('[data-pvoc-table]'), 'No se pudo cargar la información de Ventas. Intenta nuevamente en unos minutos.');
        return;
    }

    // El payload trae todos los años; de entrada (y al limpiar) se filtra el más reciente.
    const latestYear = records.reduce((max, record) => (String(record.anio) > max ? String(record.anio) : max), '');
    const defaultFilters = () => {
        const filters = emptyFilters();
        if (latestYear) filters.anio.add(latestYear);
        return filters;
    };

    const state = {
        comparison: 'plan-pedido',
        filters: defaultFilters(),
        desglose: 'empresa',
        expanded: { summary: new Set(), analisis: new Set() },
        columns: { summary: allColumns(), analisis: allColumns() },
    };
    const elements = {
        filters: root.querySelector('[data-pvoc-filters]'),
    };

    const filterValue = (record, key) => record.filtros[key];

    const matchesFilter = (record, { key, multi }) => {
        const selected = state.filters[key];
        const value = filterValue(record, key);
        return multi ? (!selected.size || selected.has(value)) : (!selected || value === selected);
    };

    /** Los filtros sin selección no filtran: se descartan antes de recorrer los ~48k combos. */
    const filteredRecords = () => {
        const active = FILTERS.filter(({ key, multi }) => (multi ? state.filters[key].size : state.filters[key]));
        return active.length ? records.filter((record) => active.every((filter) => matchesFilter(record, filter))) : records;
    };

    /** Nivel de detalle bajo cada mes: el elegido en Desglose (siempre hay uno; Empresa por defecto). */
    const detailLevels = () => [[(item) => String(filterValue(item, state.desglose)), undefined, state.desglose]];

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
            const values = [...new Set(records.map((record) => filterValue(record, key)))].sort();
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
        const dashboardControls = fragment(`<label class="pvoc-select-label">Comparar Δ y %
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
        elements.filters.querySelector('[data-pvoc-comparison]').addEventListener('change', (event) => {
            state.comparison = event.target.value;
            renderTables();
        });
    };

    /** Una sola pasada por los combos (antes eran 15: una por serie y medida). */
    const sum = (items) => {
        const totals = { plan: emptyMetrics(), pedido: emptyMetrics(), real: emptyMetrics() };
        items.forEach((item) => {
            addMetrics(totals.plan, item.plan);
            addMetrics(totals.pedido, item.pedido);
            addMetrics(totals.real, item.real);
        });
        return totals;
    };

    const comparisonSeries = () => state.comparison.split('-');
    /** Medidas visibles del panel, en el orden de METRICS. */
    const visibleMetrics = (panel) => METRICS.filter(([metric]) => state.columns[panel].has(metric));
    /** Etiqueta + (Plan, Pedido, Real, Δ) × medidas visibles + Cumpl. y Estatus. */
    const visibleColumnCount = (panel) => 1 + 4 * visibleMetrics(panel).length + 2;

    const numberCells = (totals, panel) => {
        const { dashZero } = TABLES[panel];
        const metrics = visibleMetrics(panel);
        const [left, right] = comparisonSeries();
        const percentage = totals[left].piezas ? (totals[right].piezas / totals[left].piezas) * 100 : 0;
        const seriesCells = Object.keys(SERIES).map((series) => metrics.map(([metric]) => {
            const value = totals[series][metric];
            return `<td class="pvoc-number pvoc-${series}">${dashZero && !value ? '–' : formatNumber(value)}</td>`;
        }).join('')).join('');
        const deltaCells = metrics.map(([metric]) => totals[right][metric] - totals[left][metric]).map((value) =>
            `<td class="pvoc-number pvoc-delta ${value < 0 ? 'is-negative' : 'is-positive'}">${value > 0 ? '+' : ''}${formatNumber(value)}</td>`).join('');
        const status = percentage >= 100 ? ['En meta', 'is-success'] : percentage >= 85 ? ['Parcial', 'is-warning'] : ['Bajo', 'is-danger'];
        const complianceCells = `<td class="pvoc-number">${percentage.toFixed(1)}%</td><td><span class="pvoc-status ${status[1]}">${status[0]}</span></td>`;
        return `${seriesCells}${deltaCells}${complianceCells}`;
    };

    const tableHeader = (panel) => {
        const metrics = visibleMetrics(panel);
        const metricHeaders = (className) => metrics.map(([, label]) => `<th class="pvoc-${className}">${label}</th>`).join('');
        return `<table class="pvoc-table"><thead>
            <tr class="pvoc-table-groups">
                <th rowspan="2" class="pvoc-label">${TABLES[panel].label}</th>
                ${Object.values(SERIES).map(({ label, className }) => `<th colspan="${metrics.length}" class="pvoc-${className}">${label}</th>`).join('')}
                <th colspan="${metrics.length}" class="pvoc-delta">Δ ${comparisonSeries().map((key) => SERIES[key].label).join(' − ')}</th>
                <th rowspan="2">Cumpl.</th><th rowspan="2">Estatus</th>
            </tr>
            <tr>${Object.keys(SERIES).map(metricHeaders).join('')}${metricHeaders('delta')}</tr>
        </thead><tbody>`;
    };

    const buildTree = (items, levels, parentKey = '') => {
        if (!levels.length) return [];
        const [selector, formatter, sortKey = selector] = levels[0];
        const groups = new Map();
        items.forEach((item) => {
            const value = typeof selector === 'function' ? selector(item) : item[selector];
            if (!groups.has(value)) groups.set(value, []);
            groups.get(value).push(item);
        });
        return [...groups.entries()].sort(compareGroup(sortKey)).map(([value, children], index) => {
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
        const row = `<tr class="pvoc-level-${Math.min(level, 3)}" data-row-key="${escapeHtml(node.id)}"><td class="pvoc-label">${indentation}${toggle}${escapeHtml(node.label)}</td>${numberCells(sum(node.items), panel)}</tr>`;
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
    const syncFilterCount = () => {
        const count = FILTERS.filter(({ key, multi }) => (multi ? state.filters[key].size : state.filters[key])).length;
        root.dispatchEvent(new CustomEvent('pvoc:filtros', { detail: { tab: 'summary', count } }));
    };

    /** Solo lo llaman los filtros (y Limpiar): tocar un filtro también suelta la fila seleccionada. */
    const renderTables = () => {
        Object.keys(TABLES).forEach((panel) => clearRowSelection(tableContainer(panel)));
        Object.keys(TABLES).forEach(renderTable);
        syncFilterCount();
    };

    Object.keys(TABLES).forEach((panel) => {
        const container = tableContainer(panel);
        // Antes que el expander: la fila se marca antes de que toggleNode re-pinte la tabla.
        bindRowSelection(container);
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
        state.filters = defaultFilters(); renderFilters(); renderTables();
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
        menu.innerHTML = METRICS.map(([key, label]) => `<label class="pvoc-multi-option">
            <input type="checkbox" value="${key}" ${state.columns[panel].has(key) ? 'checked' : ''}><span>${escapeHtml(label)}</span>
        </label>`).join('');
        toggle.addEventListener('click', () => setOpen(menu.hidden));
        menu.addEventListener('change', (event) => {
            const visible = state.columns[panel];
            // Siempre queda al menos una medida: sin ninguna, la tabla no tendría columnas numéricas.
            if (!event.target.checked && visible.size === 1) {
                event.target.checked = true;
                return;
            }
            event.target.checked ? visible.add(event.target.value) : visible.delete(event.target.value);
            renderTable(panel);
        });
        document.addEventListener('mousedown', (event) => { if (!wrapper.contains(event.target)) setOpen(false); });
    });

    renderFilters();
    renderTables();
    expandTo('analisis', 1);
});
