/**
 * Ancho de columnas tipo Excel para las tablas de Compara: arrastrar el borde derecho del
 * encabezado cambia el ancho y el doble clic lo ajusta al contenido. Los anchos van en em,
 * así que escalan con el zoom (zoom.ts). Mientras nadie mueve una columna, la tabla conserva
 * su ancho automático; al primer arrastre se miden todas y pasa a `table-layout: fixed`.
 */
export const COLUMN_MIN_EM = 2;
/** Ancho de una columna sin medida guardada (p. ej. una medida que se acaba de mostrar). */
const DEFAULT_EM: Record<string, number> = { label: 13 };
const DEFAULT_OTHER_EM = 7;
const STORAGE_KEY = 'ventas-compara-anchos-v1';
/** Filas que revisa el doble clic: suficiente para lo que se ve sin recorrer miles de nodos. */
const AUTOFIT_ROWS = 400;

type Widths = Record<string, number>;

/** Claves en el orden de las celdas: etiqueta, serie × medida, Δ × medida, Cumpl. Pzas., Cumplimiento Actual, Estatus. */
export const columnKeys = (series: string[], metrics: string[]) => [
    'label',
    ...series.flatMap((serie) => metrics.map((metric) => `${serie}:${metric}`)),
    ...metrics.map((metric) => `delta:${metric}`),
    'cumpl',
    'cumplActual',
    'estatus',
];

const round = (value: number) => Math.round(value * 100) / 100;

export const resizeWidth = (startEm: number, deltaPx: number, fontPx: number) =>
    (fontPx > 0 ? Math.max(COLUMN_MIN_EM, round(startEm + deltaPx / fontPx)) : startEm);

const widthOf = (key: string, widths: Widths) => widths[key] ?? DEFAULT_EM[key] ?? DEFAULT_OTHER_EM;

export const tableWidthEm = (keys: string[], widths: Widths) => round(keys.reduce((total, key) => total + widthOf(key, widths), 0));

export const colgroupHtml = (keys: string[], widths: Widths) => `<colgroup>${keys.map((key) =>
    `<col data-col="${key}"${widths[key] === undefined ? '' : ` style="width:${widths[key]}em"`}>`).join('')}</colgroup>`;

/** Anchos por tabla (planreal, summary, analisis), recordados por navegador. */
export const createWidthStore = () => {
    let all: Record<string, Widths> = {};
    try {
        all = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') ?? {};
    } catch {
        all = {};
    }
    const save = () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
        } catch {
            // Sin almacenamiento: los anchos duran lo que dure la página.
        }
    };
    return {
        get: (panel: string): Widths => all[panel] ?? {},
        set: (panel: string, widths: Widths) => { all[panel] = widths; },
        clear: (panel: string) => { delete all[panel]; save(); },
        save,
    };
};

export type WidthStore = ReturnType<typeof createWidthStore>;

/** Atributos de la <table>: con anchos guardados, layout fijo y ancho total en em. */
export const tableAttributes = (keys: string[], widths: Widths) => (Object.keys(widths).length
    ? ` class="pvoc-table is-resized" style="width:${tableWidthEm(keys, widths)}em"`
    : ' class="pvoc-table"');

/** Asa para arrastrar, dentro de cada encabezado de columna. */
export const resizeHandle = (key: string) => `<span class="pvoc-col-resize" data-col-resize="${key}" title="Arrastra para cambiar el ancho; doble clic para ajustarlo" aria-hidden="true"></span>`;

const fontPx = (table: HTMLElement) => parseFloat(getComputedStyle(table).fontSize) || 0;
const colKeys = (table: HTMLElement) => [...table.querySelectorAll<HTMLElement>('col')].map((col) => col.dataset.col ?? '');

const applyWidths = (table: HTMLElement, widths: Widths) => {
    table.querySelectorAll<HTMLElement>('col').forEach((col) => {
        const width = widths[col.dataset.col ?? ''];
        if (width !== undefined) col.style.width = `${width}em`;
    });
    table.style.width = `${tableWidthEm(colKeys(table), widths)}em`;
    table.classList.add('is-resized');
};

/** Primer arrastre: se mide cada columna (con la fila de total, que siempre trae todas) y se fija. */
const freeze = (table: HTMLElement, widths: Widths) => {
    if (table.classList.contains('is-resized')) return widths;
    const font = fontPx(table);
    const keys = colKeys(table);
    const cells = [...(table.querySelector('tr.pvoc-row-total')?.children ?? [])];
    const measured: Widths = { ...widths };
    if (font > 0 && cells.length === keys.length) {
        keys.forEach((key, index) => { measured[key] = round(cells[index]!.getBoundingClientRect().width / font); });
    }
    applyWidths(table, measured);
    return measured;
};

/** Ancho del texto de la celda (sin importar el ancho actual de la columna) más su padding. */
const contentPx = (cell: Element) => {
    const range = document.createRange();
    range.selectNodeContents(cell.querySelector('.pvoc-label-text') ?? cell);
    const style = getComputedStyle(cell);
    return range.getBoundingClientRect().width + parseFloat(style.paddingLeft) + parseFloat(style.paddingRight) + 2;
};

const autofitEm = (table: HTMLElement, key: string, header: Element) => {
    const keys = colKeys(table);
    const index = keys.indexOf(key);
    const rows = [...table.querySelectorAll('tbody tr')].slice(0, AUTOFIT_ROWS);
    const widths = rows.filter((row) => row.children.length === keys.length).map((row) => contentPx(row.children[index]!));
    const font = fontPx(table);
    return font > 0 ? resizeWidth(0, Math.max(contentPx(header), ...widths), font) : undefined;
};

/** container = contenedor con scroll de una tabla; panel = su clave en el store. */
export const bindColumnResize = (container: HTMLElement, panel: string, store: WidthStore) => {
    container.addEventListener('pointerdown', (event) => {
        const handle = (event.target as Element).closest<HTMLElement>('[data-col-resize]');
        const table = handle?.closest<HTMLElement>('table');
        if (!handle || !table || event.button !== 0) return;
        event.preventDefault();
        const key = handle.dataset.colResize ?? '';
        const widths = freeze(table, store.get(panel));
        const start = widths[key] ?? DEFAULT_OTHER_EM;
        const startX = event.clientX;
        const font = fontPx(table);
        handle.classList.add('is-resizing');
        handle.setPointerCapture(event.pointerId);

        const move = (moveEvent: PointerEvent) => {
            widths[key] = resizeWidth(start, moveEvent.clientX - startX, font);
            applyWidths(table, widths);
        };
        const end = () => {
            handle.classList.remove('is-resizing');
            handle.removeEventListener('pointermove', move);
            store.set(panel, widths);
            store.save();
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', end, { once: true });
        handle.addEventListener('pointercancel', end, { once: true });
    });

    container.addEventListener('dblclick', (event) => {
        const handle = (event.target as Element).closest<HTMLElement>('[data-col-resize]');
        const table = handle?.closest<HTMLElement>('table');
        const header = handle?.closest('th');
        if (!handle || !table || !header) return;
        event.stopPropagation();
        const key = handle.dataset.colResize ?? '';
        const widths = freeze(table, store.get(panel));
        const fitted = autofitEm(table, key, header);
        if (fitted !== undefined) widths[key] = fitted;
        applyWidths(table, widths);
        store.set(panel, widths);
        store.save();
    }, true);
};
