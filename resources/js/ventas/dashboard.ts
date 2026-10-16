import { escapeHtml, formatNumber as formatEsMx } from '../utils/format';
import { http } from '../utils/http';
import { HISTORY_SECTIONS, mountVentasHistoricas } from './ventas-historicas';
import { mountPedidosHistoricos } from './pedidos-historicos';
import { createMultiSelect } from './multi-select';
import { bindRowSelection, clearRowSelection } from './row-selection';
import { PANEL_LAYOUT, closingKpis, columnCount, expectedCompliance, mexicoToday, rowClass, type Panel, type Series } from './compara';
import { bindColumnResize, colgroupHtml, columnKeys, createWidthStore, resizeHandle, tableAttributes } from './column-resize';
import { bindLabelScroll, labelScrollBar } from './label-scroll';
import { bindSidebar } from './sidebar';
import { bindZoom } from './zoom';

type Metric = 'piezas' | 'kilos' | 'vb' | 'desc' | 'vn';
type Metrics = Record<Metric, number>;
type Totals = Record<Series, Metrics>;
type FilterKey = 'anio' | 'mes' | 'empresa' | 'tipo' | 'cliente' | 'agente' | 'tamano' | 'articulo';
/** Set = selección múltiple (vacío = todos); string = select de una opción ('' = todos). */
type Filters = Record<FilterKey, Set<string> | string>;
type Tab = 'summary' | 'pedidos' | 'history';
type SfField = 'anio' | 'mes' | 'empresa' | 'tipo' | 'nombreCte' | 'artCode' | 'artName' | 'config' | 'tamano' | 'colorName' | 'agente';
type PvPayload = { sf: string[]; nf: string[]; series: string[]; dict: string[]; rows: (string | number)[][] };
type PvRecord = Record<'anio' | 'mes' | 'empresa' | 'tipo' | 'cliente' | 'articuloCodigo' | 'articulo' | 'linea' | 'tamano' | 'color' | 'agente', string>
    & Totals & { filtros: Record<FilterKey, string> };
/** [campo o selector, formato de la etiqueta, clave de orden (por defecto, el campo)]. */
type Level = [
    selector: 'anio' | 'mes' | 'empresa' | 'tipo' | 'cliente' | ((item: PvRecord) => string),
    formatter?: ((value: string) => string) | undefined,
    sortKey?: string,
];
/** field = campo por el que se agrupó el nodo ('empresa', 'tipo'…), para colorear empresas y artículos. */
type TreeNode = { id: string; label: string; field: string | undefined; items: PvRecord[]; children: TreeNode[] };

const MONTH_NAMES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const monthName = (value: string) => MONTH_NAMES[Number(value) - 1] ?? value;

/** multi = selección múltiple (Set, vacío = todos); el resto es un select de una sola opción. */
const FILTERS: { key: FilterKey; label: string; multi?: boolean; format?: (value: string) => string }[] = [
    { key: 'anio', label: 'Año', multi: true },
    { key: 'mes', label: 'Mes', multi: true, format: monthName },
    { key: 'empresa', label: 'Empresa' },
    { key: 'tipo', label: 'Tipo', multi: true },
    { key: 'cliente', label: 'Cliente', multi: true },
    { key: 'agente', label: 'Agente', multi: true },
    { key: 'tamano', label: 'Tamaño', multi: true },
    { key: 'articulo', label: 'Artículo', multi: true },
];
/** El menú "Columnas" oculta medidas (Piezas, Kg, V.B., Desc., V.N.) en todas las series a la vez. */
const allColumns = () => new Set<string>(METRICS.map(([key]) => key));

const DESGLOSES = [['empresa', 'Empresa'], ['tipo', 'Tipo de pedido'], ['cliente', 'Cliente'], ['articulo', 'Artículo']];

const DETAIL_LEVELS: Level[] = [
    ['empresa'], ['tipo'], ['cliente'],
    [(item) => `${item.articuloCodigo} ${item.articulo} · ${item.linea} · ${item.tamano} · ${item.color}`, undefined, 'articulo'],
];

/** Orden de negocio de los tipos de pedido; los no listados van al final en orden alfabético. */
const TIPO_ORDEN = ['CE', 'CE HT', 'RS', '2das / 3ra'];
const tipoRank = (value: string) => {
    const index = TIPO_ORDEN.indexOf(String(value).trim());
    return index === -1 ? TIPO_ORDEN.length : index;
};
/** Towel siempre antes que Textil (los valores ya vienen normalizados por normalizeEmpresa). */
const EMPRESA_ORDEN = ['Towel', 'Textil'];
const empresaRank = (value: string) => {
    const index = EMPRESA_ORDEN.indexOf(String(value));
    return index === -1 ? EMPRESA_ORDEN.length : index;
};
const RANKS: Partial<Record<string, (value: string) => number>> = { tipo: tipoRank, empresa: empresaRank };
type Group = [string, PvRecord[]];
/** key = campo del nivel ('tipo', 'empresa'…); los niveles con selector de función lo declaran aparte. */
const compareGroup = (key: Level[0] | string) => {
    const rank = typeof key === 'string' ? RANKS[key] : undefined;
    return rank
        ? ([a]: Group, [b]: Group) => rank(a) - rank(b) || String(a).localeCompare(String(b))
        : ([a]: Group, [b]: Group) => String(a).localeCompare(String(b));
};

const emptyFilters = () => Object.fromEntries(FILTERS.map(({ key, multi }) => [key, multi ? new Set<string>() : ''])) as Filters;
/** Un filtro sin selección no filtra. */
const isActive = (value: Set<string> | string) => (typeof value === 'string' ? Boolean(value) : value.size > 0);

/** Medidas de cada serie (vb/desc/vn vienen del payload: AMOUNT, AMOUNTDES, AMOUNTNETO). */
const METRICS: [Metric, string][] = [['piezas', 'Piezas'], ['kilos', 'Kg'], ['vb', 'V.B.'], ['desc', 'Desc.'], ['vn', 'V.N.']];
const SERIES: Record<Series, { label: string; className: string }> = {
    plan: { label: 'Plan', className: 'plan' },
    pedido: { label: 'Pedido', className: 'pedido' },
    real: { label: 'Real', className: 'real' },
};

const formatNumber = (value: number) => formatEsMx(value || 0, 0);

/** TOWEL/TEXTIL → Towel/Textil. Solo para mostrar, no cambia el valor de filtrado. */
const normalizeEmpresa = (value: unknown) => {
    const text = String(value ?? '');
    return text.length ? text.charAt(0) + text.slice(1).toLowerCase() : text;
};

const emptyMetrics = () => Object.fromEntries(METRICS.map(([metric]) => [metric, 0])) as Metrics;
const addMetrics = (target: Metrics, row: Metrics) => {
    METRICS.forEach(([metric]) => { target[metric] += row[metric] || 0; });
};

/**
 * PvVsOcPayloadBuilder manda { sf, nf, series, dict, rows }: Plan, Pedido y Real ya vienen cruzados
 * en SQL, una fila por combo (índices al diccionario de sf seguidos de nf × series). Aquí solo se
 * arma el objeto de cada combo; los valores de filtro se calculan una vez y no en cada filtrado.
 */
const decodePayload = ({ sf, nf, series, dict, rows }: PvPayload): PvRecord[] => rows.map((row) => {
    const field = Object.fromEntries(sf.map((name, index) => [name, dict[Number(row[index])] ?? ''])) as Record<SfField, string>;
    const record = {
        anio: field.anio, mes: field.mes,
        empresa: normalizeEmpresa(field.empresa), tipo: field.tipo,
        cliente: field.nombreCte,
        articuloCodigo: field.artCode, articulo: field.artName,
        linea: field.config, tamano: field.tamano, color: field.colorName, agente: field.agente,
    } as PvRecord;
    series.forEach((serie, serieIndex) => {
        const offset = sf.length + serieIndex * nf.length;
        record[serie as Series] = Object.fromEntries(nf.map((metric, index) => [metric, Number(row[offset + index]) || 0])) as Metrics;
    });
    record.filtros = {
        anio: record.anio,
        mes: record.mes,
        empresa: record.empresa,
        tipo: record.tipo,
        cliente: record.cliente,
        agente: record.agente,
        tamano: record.tamano,
        articulo: `${record.articuloCodigo} ${record.articulo}`,
    };
    return record;
});

/** Un año de Compara: cada payload trae su propio diccionario, así que se decodifica por separado. */
const fetchYear = async (root: HTMLElement, anio: string) => decodePayload(await http.get<PvPayload>(root.dataset.comparaUrl ?? '', { params: { anio } }));

/** Aviso de error en lugar del "Cargando…" de cada contenedor. */
const showLoadError = (containers: Element[] | NodeListOf<Element>, message: string) => containers.forEach((container) => {
    container.innerHTML = `<div class="ventas-pvoc-alert" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <span>${escapeHtml(message)}</span></div>`;
});

/**
 * Botón "Filtrar" del navbar (fuera del root, vía @section('navbar-right')): abre el panel de filtros
 * de la pestaña activa (Compara, Pedidos Históricos o Ventas históricas). Cada pestaña avisa cuántos filtros tiene activos
 * con el evento 'pvoc:filtros' ({ tab, count }) y el botón muestra el de la pestaña visible.
 */
const bindFilterPanel = (root: HTMLElement) => {
    const button = document.getElementById('btn-filtrar-ventas-compara');
    const summary = root.querySelector<HTMLElement>('[data-pvoc-filter-panel]');
    const history = root.querySelector<HTMLElement>('[data-vh-filter-panel]');
    const pedidos = root.querySelector<HTMLElement>('[data-ph-filter-panel]');
    if (!button || !summary || !history || !pedidos) return;
    const panels: Record<Tab, { element: HTMLElement; title: string }> = {
        summary: { element: summary, title: 'Filtrar Compara' },
        pedidos: { element: pedidos, title: 'Filtrar Pedidos Históricos' },
        history: { element: history, title: 'Filtrar Ventas históricas' },
    };

    const label = button.querySelector('span');
    const counts: Record<Tab, number> = { summary: 0, pedidos: 0, history: 0 };
    let activeTab: Tab = 'summary';
    const panel = () => panels[activeTab].element;

    const setOpen = (open: boolean) => {
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
        setOpen(Boolean(panel().hidden));
    });
    Object.values(panels).forEach(({ element }) =>
        element.querySelector('[data-pvoc-filter-close]')?.addEventListener('click', () => setOpen(false)));
    // Los desplegables de cada filtro viven dentro del panel, así que un clic en ellos no lo cierra.
    document.addEventListener('mousedown', (event) => {
        if (!panel().hidden && !panel().contains(event.target as Node) && !button.contains(event.target as Node)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel().hidden && !panel().querySelector('.pvoc-multi-panel:not([hidden])')) setOpen(false);
    });
    root.addEventListener('pvoc:tab', (event) => {
        setOpen(false);
        activeTab = (event as CustomEvent<Tab>).detail;
        syncButton();
    });
    root.addEventListener('pvoc:filtros', (event) => {
        const { tab, count } = (event as CustomEvent<{ tab: Tab; count: number }>).detail;
        counts[tab] = count;
        syncButton();
    });
};

document.querySelectorAll<HTMLElement>('[data-ventas-pvoc-dashboard]').forEach(async (root) => {
    // Pestañas y ventas históricas no dependen del payload PV vs OC: funcionan aunque éste falle.
    bindSidebar(root, HISTORY_SECTIONS);
    bindFilterPanel(root);
    // Zoom tipo Excel para las tablas de Compara; "Ajustar" mide la tabla de la subsección visible.
    const comparaPanel = root.querySelector<HTMLElement>('[data-pvoc-panel="summary"]');
    const zoomBar = root.querySelector<HTMLElement>('[data-pvoc-zoom]');
    if (comparaPanel && zoomBar) {
        bindZoom(comparaPanel, zoomBar, () => comparaPanel.querySelector<HTMLElement>('[data-pvoc-subpanel]:not(.is-hidden) .pvoc-table-scroll'));
    }
    mountPedidosHistoricos(root, (container) => showLoadError([container], 'No se pudieron cargar los pedidos históricos. Intenta nuevamente en unos minutos.'));
    mountVentasHistoricas(root, (container) => showLoadError([container], 'No se pudieron cargar las ventas históricas. Intenta nuevamente en unos minutos.'));

    // Primero la lista de años y solo el más reciente: con eso se pinta; los demás llegan en segundo plano.
    let records: PvRecord[];
    let allYears: string[];
    try {
        const { anios } = await http.get<{ anios: (string | number)[] }>(root.dataset.comparaAniosUrl ?? '');
        allYears = anios.map(String);
        records = allYears[0] ? await fetchYear(root, allYears[0]) : [];
    } catch (error) {
        console.error('No se pudo cargar el dashboard de Ventas.', error);
        showLoadError(root.querySelectorAll('[data-pvoc-table]'), 'No se pudo cargar la información de Ventas. Intenta nuevamente en unos minutos.');
        return;
    }

    // De entrada (y al limpiar) se filtran el año y el mes actuales (o el año más reciente si el actual no tiene datos).
    const { anio: currentYear, mes: currentMonth } = mexicoToday();
    const latestYear = allYears.includes(String(currentYear)) ? String(currentYear) : (allYears[0] ?? '');
    // El valor del mes se toma de los datos cargados para no asumir su formato (con o sin cero inicial).
    const currentMonthValue = records.find((record) => Number(record.mes) === currentMonth)?.mes ?? String(currentMonth);
    const loadedYears = new Set(allYears.slice(0, 1));
    const failedYears = new Set<string>();
    const loading = new Map<string, Promise<void>>();
    let warnedYearFailure = false;

    /** Pide un año una sola vez (el fondo y una vista que lo necesita comparten la misma promesa). */
    const loadYear = (year: string) => {
        if (!loading.has(year)) {
            loading.set(year, fetchYear(root, year).then((chunk) => {
                chunk.forEach((record) => records.push(record));
                loadedYears.add(year);
            }).catch((error) => {
                console.error(`No se pudo cargar el año ${year} de Ventas.`, error);
                failedYears.add(year);
                if (!warnedYearFailure) {
                    warnedYearFailure = true;
                    window.notify?.warning('No se pudieron cargar todos los años de Ventas; se muestran los que ya están cargados.');
                }
            }));
        }
        return loading.get(year)!;
    };

    /** Años que el filtro actual necesita y aún no llegan (sin selección de año = todos). */
    const missingYears = () => {
        const selected = state.filters.anio as Set<string>;
        const needed = selected.size ? allYears.filter((year) => selected.has(year)) : allYears;
        return needed.filter((year) => !loadedYears.has(year) && !failedYears.has(year));
    };
    const defaultFilters = () => {
        const filters = emptyFilters();
        if (latestYear) (filters.anio as Set<string>).add(latestYear);
        (filters.mes as Set<string>).add(currentMonthValue);
        return filters;
    };

    const state = {
        comparison: 'plan-pedido',
        filters: defaultFilters(),
        desglose: 'empresa' as FilterKey,
        expanded: { planreal: new Set<string>(), summary: new Set<string>(), analisis: new Set<string>() } as Record<Panel, Set<string>>,
        columns: { planreal: allColumns(), summary: allColumns(), analisis: allColumns() } as Record<Panel, Set<string>>,
    };
    const elements = {
        filters: root.querySelector<HTMLElement>('[data-pvoc-filters]')!,
    };

    const filterValue = (record: PvRecord, key: FilterKey) => record.filtros[key];

    const matchesFilter = (record: PvRecord, { key }: { key: FilterKey }) => {
        const selected = state.filters[key];
        const value = filterValue(record, key);
        return typeof selected === 'string' ? (!selected || value === selected) : (!selected.size || selected.has(value));
    };

    /** Los filtros sin selección no filtran: se descartan antes de recorrer los ~48k combos. */
    const filteredRecords = () => {
        const active = FILTERS.filter(({ key }) => isActive(state.filters[key]));
        return active.length ? records.filter((record) => active.every((filter) => matchesFilter(record, filter))) : records;
    };

    /** Nivel de detalle bajo cada mes: el elegido en Desglose (siempre hay uno; Empresa por defecto). */
    const detailLevels = (): Level[] => [[(item) => String(filterValue(item, state.desglose)), undefined, state.desglose]];

    /** label = encabezado de la primera columna. */
    const TABLES: Record<Panel, { label: string; dashZero?: boolean; levels: () => Level[] }> = {
        planreal: { label: 'Empresa / Tipo / Cliente', levels: () => DETAIL_LEVELS },
        summary: { label: 'Empresa / Tipo / Cliente', levels: () => DETAIL_LEVELS },
        analisis: {
            label: 'Año / Mes',
            dashZero: true,
            levels: () => [
                ['anio', (value) => `Año ${value}`],
                ['mes', (value) => monthName(value)],
                ...detailLevels(),
            ],
        },
    };

    /** Solo las tablas que trae la vista: VtasR. vs PlanVtas está oculta y no se pinta. */
    const PANELS = (Object.keys(TABLES) as Panel[]).filter((panel) => root.querySelector(`[data-pvoc-table="${panel}"]`));
    /** Fecha de hoy para el Cumplimiento Actual; se toma de nuevo en cada pintado. */
    let today = mexicoToday();
    /** Anchos de columna tipo Excel (column-resize.ts), por tabla y recordados por navegador. */
    const columnWidths = createWidthStore();

    const fragment = (html: string) => document.createRange().createContextualFragment(html);

    const renderFilters = () => {
        const fields = FILTERS.map(({ key, label, multi, format }) => {
            // Los años se conocen desde el inicio; el resto de opciones crece conforme llegan los años.
            const values = key === 'anio' ? [...allYears].sort() : [...new Set(records.map((record) => filterValue(record, key)))].sort();
            if (multi) {
                return createMultiSelect({ label, values, format, selected: state.filters[key] as Set<string>, onChange: renderTables }).element;
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
        elements.filters.querySelectorAll<HTMLSelectElement>('[data-pvoc-filter]').forEach((select) => select.addEventListener('change', () => {
            state.filters[select.dataset.pvocFilter as FilterKey] = select.value;
            renderTables();
        }));
        elements.filters.querySelector('[data-pvoc-comparison]')!.addEventListener('change', (event) => {
            state.comparison = (event.target as HTMLSelectElement).value;
            renderTables();
        });
    };

    /** Una sola pasada por los combos (antes eran 15: una por serie y medida). */
    const sum = (items: PvRecord[]) => {
        const totals: Totals = { plan: emptyMetrics(), pedido: emptyMetrics(), real: emptyMetrics() };
        items.forEach((item) => {
            addMetrics(totals.plan, item.plan);
            addMetrics(totals.pedido, item.pedido);
            addMetrics(totals.real, item.real);
        });
        return totals;
    };

    /** Par del Δ y el Cumpl. Pzas.: fijo en VtasR. vs PlanVtas; en el resto lo elige "Comparar Δ y %". */
    const comparisonSeries = (panel: Panel) => PANEL_LAYOUT[panel].comparison ?? state.comparison.split('-') as [Series, Series];
    /** Medidas visibles del panel, en el orden de METRICS. */
    const visibleMetrics = (panel: Panel) => METRICS.filter(([metric]) => state.columns[panel].has(metric));
    const visibleColumnCount = (panel: Panel) => columnCount(PANEL_LAYOUT[panel].series.length, visibleMetrics(panel).length);

    const numberCells = (items: PvRecord[], panel: Panel) => {
        const { dashZero } = TABLES[panel];
        const totals = sum(items);
        const metrics = visibleMetrics(panel);
        const [left, right] = comparisonSeries(panel);
        const percentage = totals[left].piezas ? (totals[right].piezas / totals[left].piezas) * 100 : 0;
        const seriesCells = PANEL_LAYOUT[panel].series.map((series) => metrics.map(([metric]) => {
            const value = totals[series][metric];
            return `<td class="pvoc-number pvoc-${series}">${dashZero && !value ? '–' : formatNumber(value)}</td>`;
        }).join('')).join('');
        const deltaCells = metrics.map(([metric]) => totals[right][metric] - totals[left][metric]).map((value) =>
            `<td class="pvoc-number pvoc-delta ${value < 0 ? 'is-negative' : 'is-positive'}">${value > 0 ? '+' : ''}${formatNumber(value)}</td>`).join('');
        const status = percentage >= 100 ? ['En meta', 'is-success'] : percentage >= 85 ? ['Parcial', 'is-warning'] : ['Bajo', 'is-danger'];
        const complianceCells = `<td class="pvoc-number">${percentage.toFixed(1)}%</td><td class="pvoc-number">${expectedCompliance(items, today).toFixed(1)}%</td><td><span class="pvoc-status ${status[1]}">${status[0]}</span></td>`;
        return `${seriesCells}${deltaCells}${complianceCells}`;
    };

    const tableHeader = (panel: Panel) => {
        const metrics = visibleMetrics(panel);
        const { series, deltaLabel } = PANEL_LAYOUT[panel];
        const metricHeaders = (className: string) => metrics.map(([metric, label]) =>
            `<th class="pvoc-${className}">${label}${resizeHandle(`${className}:${metric}`)}</th>`).join('');
        const keys = columnKeys(series, metrics.map(([metric]) => metric));
        const widths = columnWidths.get(panel);
        return `<table${tableAttributes(keys, widths)}>${colgroupHtml(keys, widths)}<thead>
            <tr class="pvoc-table-groups">
                <th rowspan="2" class="pvoc-label"><span class="pvoc-label-text">${TABLES[panel].label}</span>${labelScrollBar()}${resizeHandle('label')}</th>
                ${series.map((key) => `<th colspan="${metrics.length}" class="pvoc-${SERIES[key].className}">${SERIES[key].label}</th>`).join('')}
                <th colspan="${metrics.length}" class="pvoc-delta">${deltaLabel ?? `Δ ${comparisonSeries(panel).map((key) => SERIES[key].label).join(' − ')}`}</th>
                <th rowspan="2">Cumpl. Pzas.${resizeHandle('cumpl')}</th><th rowspan="2">Cumplimiento Actual${resizeHandle('cumplActual')}</th><th rowspan="2">Estatus${resizeHandle('estatus')}</th>
            </tr>
            <tr>${series.map(metricHeaders).join('')}${metricHeaders('delta')}</tr>
        </thead><tbody>`;
    };

    const buildTree = (items: PvRecord[], levels: Level[], parentKey = ''): TreeNode[] => {
        if (!levels[0]) return [];
        const [selector, formatter, sortKey = selector] = levels[0];
        const groups = new Map<string, PvRecord[]>();
        items.forEach((item) => {
            const value = typeof selector === 'function' ? selector(item) : item[selector];
            const group = groups.get(value);
            if (group) group.push(item); else groups.set(value, [item]);
        });
        return [...groups.entries()].sort(compareGroup(sortKey)).map(([value, children], index) => {
            const id = `${parentKey}/${value}-${index}`;
            return {
                id,
                label: formatter ? formatter(value) : value,
                field: typeof sortKey === 'string' ? sortKey : undefined,
                items: children,
                children: buildTree(children, levels.slice(1), id),
            };
        });
    };

    const renderNode = (node: TreeNode, level: number, panel: Panel): string => {
        const expandable = node.children.length > 0;
        const isExpanded = state.expanded[panel].has(node.id);
        const indentation = '&nbsp;'.repeat(level * 2);
        const toggle = expandable
            ? `<button type="button" class="pvoc-expander" data-pvoc-node="${escapeHtml(node.id)}" aria-expanded="${isExpanded}">${isExpanded ? '▾' : '▸'}</button>`
            : '<span class="pvoc-expander-placeholder">•</span>';
        const row = `<tr class="pvoc-level-${Math.min(level, 3)}${rowClass(node.field, node.label)}" data-row-key="${escapeHtml(node.id)}"><td class="pvoc-label" title="${escapeHtml(node.label)}"><span class="pvoc-label-text">${indentation}${toggle}${escapeHtml(node.label)}</span></td>${numberCells(node.items, panel)}</tr>`;
        return row + (isExpanded ? node.children.map((child) => renderNode(child, level + 1, panel)).join('') : '');
    };

    const tableContainer = (panel: Panel) => root.querySelector<HTMLElement>(`[data-pvoc-table="${panel}"]`)!;

    /** Barra de scroll de los nombres de cada tabla (label-scroll.ts). */
    const labelScrolls = {} as Record<Panel, () => void>;

    /**
     * Tarjetas de cierre de Resumen General: en V.N. ($) salvo las de kg. Cada una tiene su color de
     * fondo fijo para reconocerla de un vistazo; el estado (adelantado/atrasado) va en su nota.
     */
    const kpiContainer = root.querySelector<HTMLElement>('[data-pvoc-kpis]');
    const renderKpis = (items: PvRecord[]) => {
        if (!kpiContainer) return;
        const k = closingKpis(items, today);
        const money = (value: number) => `$${formatNumber(value)}`;
        const kg = (value: number) => `<span class="pvoc-kpi-value pvoc-kpi-kg">${formatNumber(value)} kg</span>`;
        const ofPlan = (value: number, base: number, empty: string) => (base ? `${((value / base) * 100).toFixed(1)}% del plan` : empty);
        const card = (kind: string, label: string, value: string, note: string, extra = '') =>
            `<div class="pvoc-kpi pvoc-kpi-${kind}"><span class="pvoc-kpi-label">${label}</span><span class="pvoc-kpi-value">${value}</span>${extra}<span class="pvoc-kpi-note">${note}</span></div>`;
        const bar = `<span class="pvoc-kpi-bar" style="--pvoc-kpi-pct:${Math.min(100, k.avance).toFixed(1)}%;--pvoc-kpi-mark:${Math.min(100, k.avanceEsperado).toFixed(1)}%" aria-hidden="true"></span>`;
        const onTrack = k.avance >= k.avanceEsperado;
        kpiContainer.replaceChildren(fragment([
            card('plan', 'P. de Mes', money(k.plan), 'Plan del periodo', kg(k.planKg)),
            card('pedido', 'Pedidos', money(k.pedido), ofPlan(k.pedido, k.plan, 'Sin plan'), kg(k.pedidoKg)),
            card('real', 'Vta R. al Corte', money(k.real), `Al ${String(today.dia).padStart(2, '0')}/${String(today.mes).padStart(2, '0')}/${today.anio}`, kg(k.realKg)),
            card('dif', 'Dif Neta Plan − Real', money(Math.abs(k.diferencia)), k.diferencia <= 0 ? 'Sobre el plan' : 'Por vender'),
            card('avance', '% de Avance', `${k.avance.toFixed(1)}%`, `Esperado a hoy: ${k.avanceEsperado.toFixed(1)}% · ${onTrack ? 'en ritmo' : 'atrasado'}`, bar),
            card('proyeccion', 'Proyección de Cierre', money(k.proyeccion), ofPlan(k.proyeccion, k.plan, 'Sin plan')),
            card('proyeccion-kg', 'Proyección de Cierre x Kg', `${formatNumber(k.proyeccionKg)} kg`, ofPlan(k.proyeccionKg, k.planKg, 'Sin plan en kg')),
            card('ritmo', 'Ritmo Requerido', k.ritmo === null ? '—' : `${money(k.ritmo)}/día`,
                k.ritmo === null ? 'Periodo cerrado' : `${k.diasRestantes} ${k.diasRestantes === 1 ? 'día restante' : 'días restantes'}`),
        ].join('')));
    };

    const renderTable = (panel: Panel) => {
        today = mexicoToday();
        const filtered = filteredRecords();
        if (panel === 'summary') renderKpis(filtered);
        const rows = buildTree(filtered, TABLES[panel].levels()).map((node) => renderNode(node, 0, panel)).join('');
        tableContainer(panel).innerHTML = `${tableHeader(panel)}${rows || `<tr><td colspan="${visibleColumnCount(panel)}" class="pvoc-empty">No hay datos para los filtros seleccionados.</td></tr>`}
            <tr class="pvoc-row-total"><td class="pvoc-label"><span class="pvoc-label-text">Total general</span></td>${numberCells(filtered, panel)}</tr></tbody></table>`;
        labelScrolls[panel]?.();
    };

    const toggleNode = (panel: Panel, node: string) => {
        if (state.expanded[panel].has(node)) state.expanded[panel].delete(node); else state.expanded[panel].add(node);
        renderTable(panel);
    };

    /** Abre los nodos hasta la profundidad indicada recorriendo el árbol, sin re-renderizar por nivel. */
    const expandTo = (panel: Panel, depth: number) => {
        const walk = (nodes: TreeNode[], level: number) => nodes.forEach((node) => {
            if (level >= depth || !node.children.length) return;
            state.expanded[panel].add(node.id);
            walk(node.children, level + 1);
        });
        walk(buildTree(filteredRecords(), TABLES[panel].levels()), 0);
        renderTable(panel);
    };

    /** Con los filtros escondidos en el panel, el botón del navbar indica cuántos hay activos. */
    const syncFilterCount = () => {
        const count = FILTERS.filter(({ key }) => isActive(state.filters[key])).length;
        root.dispatchEvent(new CustomEvent('pvoc:filtros', { detail: { tab: 'summary', count } }));
    };

    /** Solo lo llaman los filtros (y Limpiar): tocar un filtro también suelta la fila seleccionada. */
    let renderToken = 0;
    const renderTables = () => {
        const token = ++renderToken;
        PANELS.forEach((panel) => clearRowSelection(tableContainer(panel)));
        syncFilterCount();

        // Si el filtro pide un año que aún no llega, se muestra "Cargando…" y se pinta al recibirlo.
        const missing = missingYears();
        if (missing.length) {
            PANELS.forEach((panel) => {
                tableContainer(panel).innerHTML = '<div class="pvoc-loading" role="status"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><span>Cargando años restantes…</span></div>';
            });
            Promise.all(missing.map(loadYear)).then(() => { if (token === renderToken) renderTables(); });
            return;
        }
        PANELS.forEach(renderTable);
    };

    PANELS.forEach((panel) => {
        const container = tableContainer(panel);
        // Antes que el expander: la fila se marca antes de que toggleNode re-pinte la tabla.
        bindRowSelection(container);
        bindColumnResize(container, panel, columnWidths);
        labelScrolls[panel] = bindLabelScroll(container);
        container.addEventListener('click', (event) => {
            const button = (event.target as Element).closest<HTMLElement>('[data-pvoc-node]');
            if (button) toggleNode(panel, button.dataset.pvocNode ?? '');
        });
        // Doble clic en cualquier parte de la fila la abre o cierra (p.ej. un mes para desglosarlo).
        container.addEventListener('dblclick', (event) => {
            const target = event.target as Element;
            if (target.closest('[data-pvoc-node]')) return;
            const button = target.closest('tr')?.querySelector<HTMLElement>('[data-pvoc-node]');
            if (button) toggleNode(panel, button.dataset.pvocNode ?? '');
        });
    });

    // "Anchos automáticos": la tabla visible vuelve al ancho natural de sus columnas.
    root.querySelector('[data-pvoc-widths-reset]')?.addEventListener('click', () => {
        const panel = root.querySelector<HTMLElement>('[data-pvoc-subpanel]:not(.is-hidden) [data-pvoc-table]')?.dataset.pvocTable as Panel | undefined;
        if (!panel) return;
        columnWidths.clear(panel);
        renderTable(panel);
    });

    root.querySelector('[data-pvoc-clear]')?.addEventListener('click', () => {
        state.filters = defaultFilters(); renderFilters(); renderTables();
    });

    const desgloseSelect = root.querySelector<HTMLSelectElement>('[data-pvoc-desglose]')!;
    desgloseSelect.innerHTML = DESGLOSES.map(([key, label]) => `<option value="${key}">${escapeHtml(label)}</option>`).join('');
    desgloseSelect.addEventListener('change', () => {
        state.desglose = desgloseSelect.value as FilterKey;
        renderTable('analisis');
    });

    root.querySelectorAll<HTMLElement>('[data-pvoc-columns]').forEach((wrapper) => {
        const panel = wrapper.dataset.pvocColumns as Panel;
        const toggle = wrapper.querySelector<HTMLElement>('[data-pvoc-columns-toggle]')!;
        const menu = wrapper.querySelector<HTMLElement>('[data-pvoc-columns-menu]')!;
        const setOpen = (open: boolean) => {
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        };
        menu.innerHTML = METRICS.map(([key, label]) => `<label class="pvoc-multi-option">
            <input type="checkbox" value="${key}" ${state.columns[panel].has(key) ? 'checked' : ''}><span>${escapeHtml(label)}</span>
        </label>`).join('');
        toggle.addEventListener('click', () => setOpen(Boolean(menu.hidden)));
        menu.addEventListener('change', (event) => {
            const input = event.target as HTMLInputElement;
            const visible = state.columns[panel];
            // Siempre queda al menos una medida: sin ninguna, la tabla no tendría columnas numéricas.
            if (!input.checked && visible.size === 1) {
                input.checked = true;
                return;
            }
            if (input.checked) visible.add(input.value); else visible.delete(input.value);
            renderTable(panel);
        });
        document.addEventListener('mousedown', (event) => { if (!wrapper.contains(event.target as Node)) setOpen(false); });
    });

    renderFilters();
    renderTables();
    // Al entrar, cada empresa (o año en Análisis) se muestra abierta.
    PANELS.forEach((panel) => expandTo(panel, 1));

    // Resto de años en segundo plano, del más reciente al más antiguo. Las tablas no se repintan: solo
    // cambian las opciones de los filtros, y no mientras el usuario tiene un desplegable abierto.
    const refreshFilters = () => {
        if (root.querySelector('.pvoc-multi-panel:not([hidden])')) {
            setTimeout(refreshFilters, 1000);
            return;
        }
        renderFilters();
    };
    (async () => {
        for (const year of allYears) {
            if (loadedYears.has(year)) continue;
            await loadYear(year);
            refreshFilters();
        }
    })();
});
