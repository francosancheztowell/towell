/**
 * Acciones sin clic derecho (UX-06). En 12 pantallas las acciones de una fila solo se abren
 * con clic derecho (`contextmenu`), que en iPad no existe. Este helper da tres caminos al
 * MISMO menú del módulo:
 *
 *  - ratón: clic derecho (como hoy);
 *  - dedo o lápiz: mantener presionado (long-press, 500 ms sin moverse más de 10 px);
 *  - botón "⋮" visible (44 px, aria-label), para quien no sabe que existe el long-press.
 *
 * No trae menú propio: `abrir(el, pos, origen)` es la función que el módulo ya usa para pintar
 * el suyo (pos = coordenadas de viewport, como clientX/clientY del contextmenu).
 *
 * Uso (módulo de la 19-xx, ver 17-02-CHECKLIST.md):
 *   import { accionesTactiles, botonAcciones } from '../../../utils/acciones-tactiles.ts';
 *   const soltar = accionesTactiles(tabla, 'tr[data-id]', (fila, pos) => abrirMenu(fila, pos.x, pos.y));
 *   filas.forEach((fila) => botonAcciones(fila, (f, pos) => abrirMenu(f, pos.x, pos.y), { contenedor: fila.lastElementChild }));
 * En Blade inline: window.accionesTactiles.enlazar(...) / window.accionesTactiles.boton(...).
 *
 * La zona que adopte el long-press debería llevar la clase `towell-acciones-zona` (app.css):
 * quita el menú nativo de iOS al mantener el dedo, sin impedir seleccionar texto.
 */

export type OrigenAcciones = 'contextmenu' | 'largo' | 'boton';

export interface PosicionAcciones {
    x: number;
    y: number;
}

export type AbrirAcciones = (el: HTMLElement, pos: PosicionAcciones, origen: OrigenAcciones) => void;

export interface OpcionesAcciones {
    /** Tiempo que hay que mantener el dedo (ms). */
    demoraMs?: number;
    /** Movimiento que cancela el long-press (px): más que esto es scroll, no una pulsación. */
    toleranciaPx?: number;
}

export const DEMORA_LARGO_MS = 500;
export const TOLERANCIA_PX = 10;
/** Ventana en la que se descartan el `contextmenu` nativo y el click que siguen a un long-press. */
const VENTANA_SUPRESION_MS = 800;

function objetivo(event: Event, selector: string, root: Element | Document): HTMLElement | null {
    const origen = event.target as Element | null;
    if (!origen || typeof origen.closest !== 'function') return null;
    const el = origen.closest<HTMLElement>(selector);

    return el && root.contains(el) ? el : null;
}

/**
 * Enlaza clic derecho + long-press sobre los descendientes de `root` que coincidan con
 * `selector` (presentes o futuros: delegación). Devuelve la función que lo desenlaza.
 */
export function accionesTactiles(
    root: Element | Document,
    selector: string,
    abrir: AbrirAcciones,
    opciones: OpcionesAcciones = {},
): () => void {
    const demora = opciones.demoraMs ?? DEMORA_LARGO_MS;
    const tolerancia = opciones.toleranciaPx ?? TOLERANCIA_PX;

    let temporizador: ReturnType<typeof setTimeout> | undefined;
    let inicio: PosicionAcciones | null = null;
    let suprimirHasta = 0;

    const cancelar = (): void => {
        clearTimeout(temporizador);
        temporizador = undefined;
        inicio = null;
    };

    const alPresionar = (event: Event): void => {
        // Un gesto nuevo: lo que se descartaba del long-press anterior ya no aplica (si el
        // navegador no mandó click al soltar, el siguiente toque no debe perderse).
        suprimirHasta = 0;
        const e = event as PointerEvent;
        if (e.pointerType === 'mouse' || (e.button ?? 0) !== 0) return;
        const el = objetivo(e, selector, root);
        if (!el) return;

        cancelar();
        inicio = { x: e.clientX, y: e.clientY };
        const pos = inicio;
        temporizador = setTimeout(() => {
            temporizador = undefined;
            inicio = null;
            suprimirHasta = Date.now() + VENTANA_SUPRESION_MS;
            abrir(el, pos, 'largo');
        }, demora);
    };

    const alMover = (event: Event): void => {
        if (!inicio) return;
        const e = event as PointerEvent;
        if (Math.abs(e.clientX - inicio.x) > tolerancia || Math.abs(e.clientY - inicio.y) > tolerancia) cancelar();
    };

    // Clic derecho con ratón. Tras un long-press, el `contextmenu` que Android dispara solo
    // se descarta: el menú ya está abierto.
    const alMenuContextual = (event: Event): void => {
        const el = objetivo(event, selector, root);
        if (!el) return;
        event.preventDefault();
        if (temporizador !== undefined) {
            // El navegador se adelantó al temporizador (long-press nativo): se abre una sola vez.
            cancelar();
            suprimirHasta = Date.now() + VENTANA_SUPRESION_MS;
            const e = event as MouseEvent;
            abrir(el, { x: e.clientX, y: e.clientY }, 'largo');
            return;
        }
        if (Date.now() < suprimirHasta) return;
        const e = event as MouseEvent;
        abrir(el, { x: e.clientX, y: e.clientY }, 'contextmenu');
    };

    // El click que el navegador manda al soltar el dedo no debe activar la fila.
    const alClick = (event: Event): void => {
        if (Date.now() >= suprimirHasta) return;
        if (!objetivo(event, selector, root)) return;
        event.preventDefault();
        event.stopPropagation();
        suprimirHasta = 0;
    };

    root.addEventListener('pointerdown', alPresionar);
    root.addEventListener('pointermove', alMover);
    root.addEventListener('pointerup', cancelar);
    root.addEventListener('pointercancel', cancelar);
    root.addEventListener('contextmenu', alMenuContextual);
    root.addEventListener('click', alClick, true);

    return () => {
        cancelar();
        root.removeEventListener('pointerdown', alPresionar);
        root.removeEventListener('pointermove', alMover);
        root.removeEventListener('pointerup', cancelar);
        root.removeEventListener('pointercancel', cancelar);
        root.removeEventListener('contextmenu', alMenuContextual);
        root.removeEventListener('click', alClick, true);
    };
}

export interface OpcionesBoton {
    /** Dónde se inserta el botón (default: el propio elemento). */
    contenedor?: Element | null;
    /** aria-label y title del botón. */
    etiqueta?: string;
}

/**
 * Inserta un botón "⋮" que abre el mismo menú. Idempotente: si el contenedor ya tiene uno,
 * lo devuelve sin duplicar (se puede llamar después de cada re-render).
 */
export function botonAcciones(el: HTMLElement, abrir: AbrirAcciones, opciones: OpcionesBoton = {}): HTMLButtonElement {
    const contenedor = opciones.contenedor ?? el;
    const existente = Array.from(contenedor.children).find((hijo) => hijo.classList.contains('towell-acciones-btn'));
    if (existente) return existente as HTMLButtonElement;

    const etiqueta = opciones.etiqueta ?? 'Más acciones';
    const boton = el.ownerDocument.createElement('button');
    boton.type = 'button';
    boton.className = 'towell-acciones-btn';
    boton.setAttribute('aria-label', etiqueta);
    boton.setAttribute('aria-haspopup', 'menu');
    boton.title = etiqueta;
    boton.textContent = '⋮';
    boton.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const r = boton.getBoundingClientRect();
        abrir(el, { x: r.left, y: r.bottom }, 'boton');
    });
    contenedor.appendChild(boton);

    return boton;
}

export const accionesTactilesApi = { enlazar: accionesTactiles, boton: botonAcciones };

export type AccionesTactiles = typeof accionesTactilesApi;
