/**
 * Recargar la grilla sin perder dónde estaba el planeador: scroll vertical y horizontal
 * (116 columnas) y la fila seleccionada. Los filtros ya viajan en ?filtros= (barra-filtros.ts).
 * La fila la vuelve a seleccionar y centrar el restaurador que ya existía (selectRegistroId).
 */
const CLAVE = 'pt:vista';

export interface Vista { top: number; left: number }

type Almacen = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

export function guardarVista(scroll: Element | null, idSeleccionado: string | null, almacen: Almacen = sessionStorage): void {
    try {
        almacen.setItem(CLAVE, JSON.stringify({ top: scroll?.scrollTop ?? 0, left: scroll?.scrollLeft ?? 0 }));
        if (idSeleccionado) almacen.setItem('selectRegistroId', idSeleccionado);
    } catch { /* modo privado: se recarga igual */ }
}

/** Lee y borra (una sola vez por recarga). */
export function tomarVista(almacen: Almacen = sessionStorage): Vista | null {
    try {
        const crudo = almacen.getItem(CLAVE);
        almacen.removeItem(CLAVE);
        const v = crudo ? JSON.parse(crudo) : null;
        return v && Number.isFinite(v.top) && Number.isFinite(v.left) ? { top: v.top, left: v.left } : null;
    } catch {
        return null;
    }
}

/** Id de la fila seleccionada (seleccion.ts publica selectedRowIndex sobre window.allRows). */
export function idSeleccionado(): string | null {
    const w = window as unknown as { allRows?: ArrayLike<HTMLElement>; selectedRowIndex?: number };
    const i = w.selectedRowIndex ?? -1;
    return i >= 0 ? (w.allRows?.[i]?.dataset.id ?? null) : null;
}

export function recargarConservando(id: string | null = idSeleccionado()): void {
    guardarVista(document.querySelector('.pt-table-scroll'), id);
    window.location.reload();
}

/**
 * Al cargar: el scroll horizontal siempre; el vertical solo si no hay fila que centrar
 * (de eso se encarga el restaurador de selección).
 */
export function restaurarVista(scroll: Element | null, haySeleccionPendiente: boolean, almacen: Almacen = sessionStorage): void {
    const v = tomarVista(almacen);
    if (!v || !scroll) return;
    scroll.scrollLeft = v.left;
    if (!haySeleccionPendiente) scroll.scrollTop = v.top;
}
