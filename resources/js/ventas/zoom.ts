/**
 * Zoom tipo Excel para las tablas de Compara. La tabla mide todo en em sobre
 * `font-size: calc(base * var(--pvoc-zoom))`, así que cambiar la variable escala letra,
 * padding y anchos juntos sin `transform` (las columnas fijas siguen funcionando).
 */
export const ZOOM_MIN = 40;
export const ZOOM_MAX = 200;
/** Tamaño normal: 90 % (a 100 % la letra se veía grande). */
export const ZOOM_DEFAULT = 90;
/** "Ajustar" nunca agranda la tabla más allá del 100 %. */
const FIT_MAX = 100;
/** v2: al cambiar el tamaño normal, nadie se queda con el 100 % que guardó antes. */
const STORAGE_KEY = 'ventas-compara-zoom-v2';

export const clampZoom = (value: number) =>
    (Number.isFinite(value) ? Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(value))) : ZOOM_DEFAULT);

/** Como los botones − / + de Excel: salta al siguiente múltiplo de 10. */
export const stepZoom = (value: number, direction: 1 | -1) => clampZoom(direction > 0
    ? Math.floor(value / 10) * 10 + 10
    : Math.ceil(value / 10) * 10 - 10);

/** Zoom con el que la tabla (medida al zoom actual) cabe completa en el ancho visible; nunca pasa de FIT_MAX. */
export const fitZoom = (containerWidth: number, tableWidth: number, current: number) => {
    if (!(tableWidth > 0) || !(containerWidth > 0)) return clampZoom(current);
    return clampZoom(Math.min(FIT_MAX, Math.floor((current * containerWidth) / tableWidth)));
};

const readStored = () => {
    try {
        return clampZoom(Number(localStorage.getItem(STORAGE_KEY) ?? ZOOM_DEFAULT));
    } catch {
        return ZOOM_DEFAULT;
    }
};

const store = (value: number) => {
    try {
        localStorage.setItem(STORAGE_KEY, String(value));
    } catch {
        // Sin almacenamiento (ventana privada): el zoom solo dura la visita.
    }
};

/**
 * scope = elemento que recibe `--pvoc-zoom`; bar = barra con los controles;
 * visibleScroll = contenedor con scroll de la tabla que se ve ahora (para "Ajustar").
 */
export const bindZoom = (scope: HTMLElement, bar: HTMLElement, visibleScroll: () => HTMLElement | null) => {
    const range = bar.querySelector<HTMLInputElement>('[data-pvoc-zoom-range]');
    const output = bar.querySelector<HTMLElement>('[data-pvoc-zoom-value]');
    let zoom = readStored();

    const apply = (value: number) => {
        zoom = clampZoom(value);
        scope.style.setProperty('--pvoc-zoom', String(zoom / 100));
        if (range) range.value = String(zoom);
        if (output) output.textContent = `${zoom} %`;
        store(zoom);
    };

    if (range) {
        range.min = String(ZOOM_MIN);
        range.max = String(ZOOM_MAX);
        range.addEventListener('input', () => apply(Number(range.value)));
    }
    bar.querySelectorAll<HTMLElement>('[data-pvoc-zoom-step]').forEach((button) => button.addEventListener('click', () =>
        apply(stepZoom(zoom, button.dataset.pvocZoomStep === '-1' ? -1 : 1))));
    bar.querySelector('[data-pvoc-zoom-reset]')?.addEventListener('click', () => apply(ZOOM_DEFAULT));
    bar.querySelector('[data-pvoc-zoom-fit]')?.addEventListener('click', () => {
        const container = visibleScroll();
        const table = container?.querySelector('table');
        if (container && table) apply(fitZoom(container.clientWidth, table.scrollWidth, zoom));
    });
    // Ctrl + rueda, como en Excel; sin Ctrl la rueda sigue haciendo scroll.
    scope.addEventListener('wheel', (event) => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        apply(stepZoom(zoom, event.deltaY < 0 ? 1 : -1));
    }, { passive: false });

    apply(zoom);
};
