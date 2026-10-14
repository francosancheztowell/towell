/**
 * Posición del panel del filtro múltiple (position: fixed) respecto a su botón.
 * Sin DOM para poder probarla: en tablet el viewport es bajo y el panel debe caber o abrir hacia arriba.
 */
export type ButtonRect = { top: number; bottom: number; left: number; width: number };
export type Viewport = { width: number; height: number };
export type PanelPlacement = { top: number | null; bottom: number | null; left: number; minWidth: number; maxHeight: number };

const GAP = 4;
const MARGIN = 8;
const MAX_HEIGHT = 320;
const MIN_WIDTH = 200;
/** Por debajo de este alto disponible abajo, el panel se abre hacia arriba si ahí hay más espacio. */
const MIN_COMFORTABLE_HEIGHT = 200;

export const panelPlacement = (rect: ButtonRect, viewport: Viewport, panelWidth: number): PanelPlacement => {
    const minWidth = Math.max(rect.width, MIN_WIDTH);
    const left = Math.max(MARGIN, Math.min(rect.left, viewport.width - Math.max(panelWidth, minWidth) - MARGIN));
    const below = viewport.height - rect.bottom - GAP - MARGIN;
    const above = rect.top - GAP - MARGIN;

    if (below < MIN_COMFORTABLE_HEIGHT && above > below) {
        return { top: null, bottom: viewport.height - rect.top + GAP, left, minWidth, maxHeight: Math.min(MAX_HEIGHT, above) };
    }
    return { top: rect.bottom + GAP, bottom: null, left, minWidth, maxHeight: Math.min(MAX_HEIGHT, Math.max(below, 0)) };
};
