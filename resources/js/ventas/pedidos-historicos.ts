/**
 * Pedidos Históricos (Ventas > Compara): árbol Mes > Empresa > Tipo de pedido > Cliente > OC > artículo
 * sobre dbo.TwHistoricosPedidos. A diferencia de la tabla dinámica de Excel, los datos del artículo
 * (tamaño, color, precio, fechas) se muestran dentro de su fila y no en columnas que estarían vacías
 * en cada nivel de grupo; las columnas son solo las medidas, que todas las filas tienen.
 * Se pinta con nodos (textContent), no con innerHTML: los textos vienen de la base.
 */
import { formatNumber } from '../utils/format';
import { http } from '../utils/http';
import { createMultiSelect, type MultiSelect } from './multi-select';
import { bindRowSelection } from './row-selection';
import {
    ESTATUS, FILTER_KEYS, LEVEL_LABELS, buildTree, decodePedidos, isOpen, matchesLine, monthName, periodLabel, progress, sumLines,
    type FilterKey, type Filters, type PedidoLinea, type PedidosPayload, type Totals, type TreeNode,
} from './pedidos-logica';

/** Más filas que esto vuelven lenta la tabla en tablet; se avisa para acotar con filtros o búsqueda. */
const MAX_ROWS = 2500;
const COLUMNS = 6;
/** Niveles abiertos al entrar: Mes y Empresa, así se ve cada Tipo de pedido con su total. */
const OPEN_DEPTH = 2;

const LEVEL_ICONS: Record<TreeNode['level'], string> = {
    periodo: 'fa-calendar', empresa: 'fa-industry', tipo: 'fa-tag', cliente: 'fa-building', oc: 'fa-file-lines',
};

/** Nombre de cada artículo para mostrarlo junto a su código en el filtro Art (se llena al cargar años). */
const ARTICLE_NAMES = new Map<string, string>();
const articleLabel = (code: string) => (ARTICLE_NAMES.get(code) ? `${code} · ${ARTICLE_NAMES.get(code)}` : code);

const FILTERS: { key: FilterKey; label: string; format?: (value: string) => string }[] = [
    { key: 'anio', label: 'Año' },
    { key: 'mes', label: 'Mes', format: monthName },
    { key: 'empresa', label: 'Empresa' },
    { key: 'tipo', label: 'Tipo de pedido' },
    { key: 'cliente', label: 'Cliente' },
    { key: 'articulo', label: 'Art', format: articleLabel },
    { key: 'talla', label: 'Tamaño' },
    { key: 'color', label: 'Color' },
    { key: 'codigo', label: 'Código de barras' },
    { key: 'estatus', label: 'Estatus' },
];

const qty = (value: number) => formatNumber(value, Number.isInteger(Math.round(value * 100) / 100) ? 0 : 2);
const money = (value: number) => `$${formatNumber(value, 2)}`;

// --- Construcción de nodos ------------------------------------------------------

type Child = Node | string | null | undefined | false;
type Attrs = Record<string, string | undefined>;

/** Elemento con atributos (class, title, data-*, aria-*, style) e hijos; los textos van como texto. */
const h = (tag: string, attrs: Attrs = {}, ...children: Child[]) => {
    const element = document.createElement(tag);
    Object.entries(attrs).forEach(([name, value]) => { if (value !== undefined) element.setAttribute(name, value); });
    element.append(...children.filter((child): child is Node | string => Boolean(child)));
    return element;
};

const icon = (name: string, attrs: Attrs = {}) => h('i', { class: `fa-solid ${name}`, 'aria-hidden': 'true', ...attrs });

const bar = (pct: number, done: boolean) => h('span', { class: `ph-bar${done ? ' is-done' : ''}`, style: `--ph-pct:${pct.toFixed(1)}%` });

const metricCells = (totals: Totals) => {
    const pct = progress(totals);
    return [
        h('td', { class: 'pvoc-number' }, qty(totals.cantidad)),
        h('td', { class: 'pvoc-number' }, qty(totals.entregado)),
        h('td', { class: `pvoc-number ${totals.pendiente > 0 ? 'ph-pendiente' : 'ph-cero'}` }, qty(totals.pendiente)),
        h('td', { class: 'ph-avance' }, bar(pct, totals.cantidad > 0 && totals.pendiente <= 0), h('span', { class: 'ph-pct' }, `${formatNumber(pct, 0)}%`)),
        h('td', { class: 'pvoc-number ph-importe' }, money(totals.importe)),
    ];
};

const nodeMeta = (node: TreeNode) => {
    if (node.level === 'oc') {
        const count = `${node.lines.length} ${node.lines.length === 1 ? 'línea' : 'líneas'}`;
        return node.created ? `Creada ${node.created} · ${count}` : count;
    }
    if (node.level !== 'empresa') return `${node.orders} ${node.orders === 1 ? 'OC' : 'OCs'}`;
    return '';
};

const groupRow = (node: TreeNode, open: boolean) => {
    const meta = nodeMeta(node);
    const label = LEVEL_LABELS[node.level];
    const value = node.level === 'periodo' ? periodLabel(node.value) : node.value;
    return h('tr', { class: `ph-row ph-grupo ph-nivel-${node.depth}`, 'data-row-key': node.id },
        h('td', { class: 'pvoc-label ph-concepto', style: `--ph-depth:${node.depth}` },
            h('button', { type: 'button', class: 'ph-toggle', 'data-ph-toggle': node.id, 'aria-expanded': String(open), 'aria-label': `${open ? 'Cerrar' : 'Abrir'} ${value}` },
                icon('fa-chevron-right')),
            icon(`${LEVEL_ICONS[node.level]} ph-nivel-icono`, { title: label }),
            h('span', { class: 'ph-valor', title: `${label}: ${value}` }, value || '(sin dato)'),
            meta && h('span', { class: 'ph-meta' }, meta)),
        ...metricCells(node.totals));
};

const chip = (text: string, title: string, extra = '') => h('span', { class: `ph-chip${extra}`, title }, text);

const articleRow = (line: PedidoLinea, key: string, depth: number) => {
    const color = [line.colorId, line.color].filter(Boolean).join(' · ');
    const meta = h('span', { class: 'ph-art-meta' },
        chip(`Art. ${line.articulo}`, 'Artículo'),
        line.talla && chip(line.talla, 'Tamaño'),
        color && chip(color, 'Color', ' ph-chip-color'),
        line.codigo && line.codigo !== line.articulo && chip(`Cód. ${line.codigo}`, 'Código de barras'),
        h('span', { class: 'ph-facts' },
            h('span', { title: 'Precio unitario' }, `${money(line.precio)} c/u`),
            line.facturado
                ? h('span', { title: 'Fecha de factura' }, `Facturado ${line.facturado}`)
                : h('span', { class: 'ph-sin-factura' }, 'Sin facturar')));
    return h('tr', { class: 'ph-row ph-articulo', 'data-row-key': key },
        h('td', { class: 'pvoc-label ph-concepto', style: `--ph-depth:${depth}` },
            h('div', { class: 'ph-art' }, h('span', { class: 'ph-art-nombre', title: line.nombre }, line.nombre || line.articulo), meta)),
        ...metricCells(line));
};

const messageRow = (text: string, className: string) => h('tr', {}, h('td', { colspan: String(COLUMNS), class: className }, text));

const kpi = (label: string, value: string, extra: Child = null, modifier = '') =>
    h('div', { class: `ph-kpi${modifier}` }, h('span', { class: 'ph-kpi-label' }, label), h('span', { class: 'ph-kpi-value' }, value), extra);

// --- Componente -----------------------------------------------------------------

const initPedidosHistoricos = (root: HTMLElement, years: string[]) => {
    const elements = {
        kpis: root.querySelector<HTMLElement>('[data-ph-kpis]')!,
        chips: root.querySelector<HTMLElement>('[data-ph-chips]')!,
        table: root.querySelector<HTMLElement>('[data-ph-table]')!,
        slicers: root.querySelector<HTMLElement>('[data-ph-slicers]')!,
    };

    const loaded = new Map<string, PedidoLinea[]>();
    const loading = new Map<string, Promise<void>>();
    const failed = new Set<string>();
    const filters = Object.fromEntries(FILTER_KEYS.map((key) => [key, new Set<string>()])) as Filters;
    // Los multi-select leen estos arreglos por referencia: se actualizan en sitio al llegar otro año.
    const values = Object.fromEntries(FILTER_KEYS.map((key) => [key, [] as string[]])) as Record<FilterKey, string[]>;
    values.anio.push(...years);
    values.estatus.push(ESTATUS.pendiente, ESTATUS.completo);

    /** Nodos que el usuario abrió o cerró con su flecha. */
    const state = { toggled: new Set<string>() };

    // --- Datos por año -------------------------------------------------------

    const wantedYears = () => (filters.anio.size ? years.filter((year) => filters.anio.has(year)) : years);
    const missingYears = () => wantedYears().filter((year) => !loaded.has(year) && !failed.has(year));

    const loadYear = (year: string) => {
        const pending = loading.get(year) ?? http.get<PedidosPayload>(root.dataset.pedidosUrl ?? '', { params: { anio: year } })
            .then((payload) => { loaded.set(year, decodePedidos(payload)); })
            .catch((error: unknown) => { failed.add(year); console.error(`No se pudieron cargar los pedidos de ${year}.`, error); })
            .finally(() => { loading.delete(year); });
        loading.set(year, pending);
        return pending;
    };

    const allLines = () => wantedYears().flatMap((year) => loaded.get(year) ?? []);

    const refreshValues = () => (['mes', 'empresa', 'tipo', 'cliente', 'articulo', 'talla', 'color', 'codigo'] as const).forEach((key) => {
        const distinct = new Set<string>();
        loaded.forEach((lines) => lines.forEach((line) => {
            distinct.add(line[key]);
            if (key === 'articulo' && line.nombre) ARTICLE_NAMES.set(line.articulo, line.nombre);
        }));
        const sorted = [...distinct].sort(key === 'mes'
            ? (a, b) => Number(a) - Number(b)
            : (a, b) => a.localeCompare(b, 'es', { numeric: true }));
        values[key].splice(0, values[key].length, ...sorted);
    });

    // --- Filtros -------------------------------------------------------------

    const pickers = Object.fromEntries(FILTERS.map(({ key, label, format }) => [key, createMultiSelect({
        label, values: values[key], selected: filters[key], format, onChange: () => { void update(); },
    })])) as Record<FilterKey, MultiSelect>;
    elements.slicers.replaceChildren(...FILTERS.map(({ key }) => pickers[key].element));

    const syncPickers = (lines: PedidoLinea[]) => FILTER_KEYS.forEach((key) => {
        if (key === 'anio') return pickers.anio.sync();
        pickers[key].setAvailable(new Set(lines.filter((line) => matchesLine(line, filters, key)).map((line) => line[key])));
    });

    const renderChips = () => {
        const chips = FILTERS.flatMap(({ key, label, format }) => [...filters[key]].map((value) =>
            h('button', { type: 'button', class: 'ph-filter-chip', 'data-ph-chip': key, 'data-ph-value': value, title: `Quitar filtro ${label}` },
                h('span', { class: 'ph-filter-chip-key' }, label), ` ${format ? format(value) : value} `, icon('fa-xmark'))));
        elements.chips.replaceChildren(...(chips.length ? chips : [h('span', { class: 'ph-filter-none' }, 'Sin filtros: todos los pedidos.')]));
    };

    // --- KPIs y árbol --------------------------------------------------------

    const renderKpis = (lines: PedidoLinea[]) => {
        const totals = sumLines(lines);
        const orders = new Set(lines.map((line) => `${line.cliente}|${line.oc}`)).size;
        const clients = new Set(lines.map((line) => line.cliente)).size;
        const pct = progress(totals);
        elements.kpis.replaceChildren(
            kpi('Órdenes de compra', formatNumber(orders, 0), h('span', { class: 'ph-kpi-note' }, `${formatNumber(clients, 0)} ${clients === 1 ? 'cliente' : 'clientes'}`)),
            kpi('Cantidad pedida', qty(totals.cantidad)),
            kpi('Entregado', qty(totals.entregado), h('span', { class: 'ph-kpi-progress' }, bar(pct, pct >= 100), `${formatNumber(pct, 1)}%`)),
            kpi('Pendiente', qty(totals.pendiente), null, totals.pendiente > 0 ? ' is-warning' : ''),
            kpi('Importe', money(totals.importe), null, ' is-primary'),
        );
    };

    const treeRows = (lines: PedidoLinea[]) => {
        const rows: HTMLElement[] = [];
        let truncated = false;
        const push = (row: () => HTMLElement) => { if (rows.length < MAX_ROWS) rows.push(row()); else truncated = true; };

        const walk = (nodes: TreeNode[]) => nodes.forEach((node) => {
            const open = isOpen(node, OPEN_DEPTH, state.toggled);
            push(() => groupRow(node, open));
            if (!open || truncated) return;
            if (node.level === 'oc') node.lines.forEach((line, i) => push(() => articleRow(line, `${node.id}#${i}`, node.depth + 1)));
            else walk(node.children);
        });
        walk(buildTree(lines));
        return { rows, truncated };
    };

    const renderTable = (lines: PedidoLinea[]) => {
        const { rows, truncated } = treeRows(lines);
        const pending = missingYears();
        const failedYears = wantedYears().filter((year) => failed.has(year));
        const notices = [
            truncated && `Se muestran las primeras ${formatNumber(MAX_ROWS, 0)} filas. Usa Filtrar para acotar o cierra algunos niveles.`,
            pending.length > 0 && `Cargando ${pending.join(', ')}…`,
            failedYears.length > 0 && `No se pudieron cargar los pedidos de ${failedYears.join(', ')}.`,
        ].filter((text): text is string => Boolean(text)).map((text) => messageRow(text, 'ph-aviso'));
        const empty = pending.length ? 'Cargando pedidos…' : 'No hay pedidos para los filtros seleccionados.';

        const head = h('tr', {},
            h('th', { class: 'pvoc-label' }, 'Mes › Empresa › Tipo › Cliente › OC › Artículo'),
            ...['Cantidad', 'Entregado', 'Pendiente'].map((label) => h('th', {}, label)),
            h('th', { class: 'ph-col-avance' }, 'Avance'),
            h('th', {}, 'Importe'));
        const total = h('tr', { class: 'pvoc-row-total' }, h('td', { class: 'pvoc-label' }, 'Total general'), ...metricCells(sumLines(lines)));

        elements.table.replaceChildren(h('table', { class: 'pvoc-table ph-table' },
            h('thead', {}, head),
            h('tbody', {}, ...(rows.length ? rows : [messageRow(empty, 'pvoc-empty')]), ...notices),
            h('tfoot', {}, total)));
    };

    const render = () => {
        const base = allLines();
        const filtered = base.filter((line) => matchesLine(line, filters));
        syncPickers(base);
        renderChips();
        renderKpis(filtered);
        renderTable(filtered);
        const count = FILTER_KEYS.filter((key) => filters[key].size).length;
        root.closest('[data-ventas-pvoc-dashboard]')?.dispatchEvent(new CustomEvent('pvoc:filtros', { detail: { tab: 'pedidos', count } }));
    };

    /** Pinta con lo que ya hay y vuelve a pintar cuando llegan los años que faltan. */
    const update = async () => {
        render();
        const missing = missingYears();
        if (!missing.length) return;
        await Promise.all(missing.map(loadYear));
        refreshValues();
        render();
    };

    // --- Eventos -------------------------------------------------------------

    elements.table.addEventListener('click', (event) => {
        const toggle = (event.target as Element).closest<HTMLElement>('[data-ph-toggle]');
        if (!toggle) return;
        const id = toggle.dataset.phToggle ?? '';
        if (state.toggled.has(id)) state.toggled.delete(id); else state.toggled.add(id);
        render();
    });

    elements.chips.addEventListener('click', (event) => {
        const chipButton = (event.target as Element).closest<HTMLElement>('[data-ph-chip]');
        if (!chipButton) return;
        filters[chipButton.dataset.phChip as FilterKey]?.delete(chipButton.dataset.phValue ?? '');
        void update();
    });

    root.querySelector('[data-ph-clear-all]')?.addEventListener('click', () => {
        FILTER_KEYS.forEach((key) => filters[key].clear());
        void update();
    });

    bindRowSelection(elements.table);

    // De entrada, el año y el mes actuales (o el año más reciente si el actual no tiene pedidos).
    const [currentYear = '', currentMonth = ''] = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Mexico_City' }).format(new Date()).split('-');
    const year = years.includes(currentYear) ? currentYear : years[0];
    if (year) filters.anio.add(year);
    void update().then(() => {
        const month = String(Number(currentMonth));
        if (year !== currentYear || !values.mes.includes(month)) return;
        filters.mes.add(month);
        render();
    });
};

/**
 * Se monta la primera vez que se abre la sección (evento 'pvoc:tab' con 'pedidos'): el payload pesa
 * y no debe retrasar Compara. scope = raíz del dashboard.
 */
export const mountPedidosHistoricos = (scope: HTMLElement, onError: (container: HTMLElement) => void) => {
    scope.querySelectorAll<HTMLElement>('[data-pedidos-historicos]').forEach((root) => {
        let mounted = false;
        scope.addEventListener('pvoc:tab', async (event) => {
            if ((event as CustomEvent<string>).detail !== 'pedidos' || mounted) return;
            mounted = true;
            try {
                const { anios } = await http.get<{ anios: (string | number)[] }>(root.dataset.pedidosAniosUrl ?? '');
                initPedidosHistoricos(root, anios.map(String));
            } catch (error) {
                console.error('No se pudieron cargar los pedidos históricos.', error);
                onError(root.querySelector<HTMLElement>('[data-ph-table]')!);
            }
        });
    });
};
