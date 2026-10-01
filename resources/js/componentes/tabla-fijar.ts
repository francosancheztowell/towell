/**
 * Fijar columnas, como en AX: clic derecho (o mantener presionado en tablet) sobre un encabezado
 * de una tabla con `data-fijar-columnas="<clave>"` abre un menú: Fijar columna / Soltar columna /
 * Soltar todas. La columna fija se queda pegada a la izquierda al desplazar en horizontal; varias
 * se apilan en el orden de la tabla. Se recuerdan por navegador (localStorage), por título de
 * columna: así sobreviven a pestañas con columnas distintas (Cuotas STD / Real).
 *
 * Las celdas no se tocan (Livewire las vuelve a pintar y quitaría lo que se les ponga): todo va
 * en un <style> por tabla con :nth-child, que se recalcula si cambia el ancho o el encabezado.
 * En x-tabla: prop `fijar-columnas="<clave>"`.
 */
import { accionesTactiles, type PosicionAcciones } from '../utils/acciones-tactiles.ts';

const PREFIJO = 'towell:columnas-fijas:';
const SELECTOR_TH = 'table[data-fijar-columnas] > thead > tr:first-child > th';

export interface ColumnaFija {
    /** Posición 1-based de la columna (para :nth-child). */
    n: number;
    /** Distancia en px al borde izquierdo: ancho de las fijas que van antes. */
    left: number;
}

/** Reglas CSS de las columnas fijas de una tabla. */
export function cssColumnasFijas(clave: string, fijas: ColumnaFija[]): string {
    const t = `table[data-fijar-columnas="${clave.replace(/["\\]/g, '')}"]`;
    const ultima = fijas.at(-1)?.n;

    return fijas
        .map(({ n, left }) => {
            const c = `:nth-child(${n})`;
            const sombra = n === ultima ? ' box-shadow: inset -1px 0 0 var(--color-slate-300), 6px 0 6px -6px rgb(0 0 0 / 0.3);' : '';
            return [
                `${t} tr > ${c} { position: sticky; left: ${left}px; z-index: 5;${sombra} }`,
                `${t} > thead > tr > ${c} { z-index: 25; }`,
                `${t} > thead > tr[data-fila-filtros] > ${c} { background-color: var(--color-white); }`,
                // Fondo opaco: lo que pasa por debajo al desplazar no se transparenta. Mismos
                // colores que la cebra y la selección (app.css).
                `${t} > tbody > tr > ${c} { background-color: var(--color-white); }`,
                `${t}.tabla-cebra > tbody > tr:nth-child(even of :not([hidden], [data-filtro-col-oculta])) > ${c} { background-color: var(--color-slate-50); }`,
                `${t} > tbody > tr > td.col-acento${c} { background-image: linear-gradient(rgb(147 51 234 / 0.07), rgb(147 51 234 / 0.07)); }`,
                `${t}.tabla-seleccionable > tbody > tr[aria-selected='true'] > ${c} { background-color: var(--color-blue-500); }`,
            ].join('\n');
        })
        .join('\n');
}

function leer(clave: string): string[] {
    try {
        const valor: unknown = JSON.parse(localStorage.getItem(PREFIJO + clave) ?? '[]');
        return Array.isArray(valor) ? valor.filter((t): t is string => typeof t === 'string') : [];
    } catch {
        return [];
    }
}

function guardar(clave: string, titulos: string[]): void {
    try {
        localStorage.setItem(PREFIJO + clave, JSON.stringify(titulos));
    } catch {
        // Sin almacenamiento (modo privado): la columna queda fija solo hasta recargar.
    }
}

const titulo = (th: HTMLTableCellElement): string => th.textContent?.trim() ?? '';

function encabezados(tabla: HTMLTableElement): HTMLTableCellElement[] {
    return [...(tabla.tHead?.rows[0]?.cells ?? [])];
}

/** Pinta (o repinta) las columnas fijas de la tabla según lo guardado. */
export function aplicarColumnasFijas(tabla: HTMLTableElement): void {
    const clave = tabla.dataset.fijarColumnas ?? '';
    const titulos = leer(clave);
    const fijas: ColumnaFija[] = [];
    let left = 0;

    encabezados(tabla).forEach((th, i) => {
        if (!titulos.includes(titulo(th))) return;
        fijas.push({ n: i + 1, left });
        left += th.getBoundingClientRect().width;
    });

    const id = `columnas-fijas-${clave}`;
    let estilo = document.getElementById(id);
    if (!estilo) {
        estilo = document.createElement('style');
        estilo.id = id;
        document.head.append(estilo);
    }
    estilo.textContent = cssColumnasFijas(clave, fijas);
}

const vigiladas = new WeakSet<HTMLTableElement>();

/** Recalcula al cambiar el ancho de la tabla o su encabezado (pestañas, filtros, datos). */
function vigilar(tabla: HTMLTableElement): void {
    if (vigiladas.has(tabla)) return;
    vigiladas.add(tabla);

    let pendiente = false;
    const repintar = (): void => {
        if (pendiente) return;
        pendiente = true;
        queueMicrotask(() => {
            pendiente = false;
            if (tabla.isConnected) aplicarColumnasFijas(tabla);
        });
    };
    new ResizeObserver(repintar).observe(tabla);
    if (tabla.tHead) new MutationObserver(repintar).observe(tabla.tHead, { childList: true, subtree: true, characterData: true });
    aplicarColumnasFijas(tabla);
}

let menu: HTMLElement | null = null;

function cerrarMenu(): void {
    menu?.remove();
    menu = null;
}

function opcion(texto: string, accion: () => void): HTMLButtonElement {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.setAttribute('role', 'menuitem');
    boton.textContent = texto;
    boton.addEventListener('click', () => {
        accion();
        cerrarMenu();
    });
    return boton;
}

function abrirMenu(th: HTMLElement, pos: PosicionAcciones): void {
    const tabla = th.closest<HTMLTableElement>('table[data-fijar-columnas]');
    if (!tabla || !(th instanceof HTMLTableCellElement)) return;
    vigilar(tabla);
    cerrarMenu();

    const clave = tabla.dataset.fijarColumnas ?? '';
    const nombre = titulo(th);
    const fijas = leer(clave);
    const cambiar = (titulos: string[]): void => {
        guardar(clave, titulos);
        aplicarColumnasFijas(tabla);
    };

    menu = document.createElement('div');
    menu.className = 'tabla-fijar-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', `Columna ${nombre}`);
    menu.append(
        fijas.includes(nombre)
            ? opcion('Soltar columna', () => cambiar(fijas.filter((t) => t !== nombre)))
            : opcion('Fijar columna', () => cambiar([...fijas, nombre])),
    );
    if (fijas.length > 0) menu.append(opcion('Soltar todas', () => cambiar([])));

    document.body.append(menu);
    // Dentro de la ventana aunque el clic sea en el borde.
    const { width, height } = menu.getBoundingClientRect();
    menu.style.left = `${Math.min(pos.x, window.innerWidth - width - 8)}px`;
    menu.style.top = `${Math.min(pos.y, window.innerHeight - height - 8)}px`;
    menu.querySelector('button')?.focus();
}

/** Una vez por documento: el menú se engancha por delegación (sirve para tablas que llegan después). */
export function escucharColumnasFijas(): void {
    accionesTactiles(document, SELECTOR_TH, abrirMenu);

    document.addEventListener('pointerdown', (e) => {
        if (menu && !menu.contains(e.target as Node)) cerrarMenu();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarMenu();
    });
    window.addEventListener('scroll', cerrarMenu, { capture: true, passive: true });
    // Fijadas en otra pestaña de la misma pantalla: se reflejan aquí también.
    window.addEventListener('storage', (e) => {
        if (e.key?.startsWith(PREFIJO)) {
            document.querySelectorAll<HTMLTableElement>('table[data-fijar-columnas]').forEach(aplicarColumnasFijas);
        }
    });
}

/** Pinta lo guardado en las tablas presentes (al cargar y tras wire:navigate). */
export function iniciarColumnasFijas(root: ParentNode = document): void {
    root.querySelectorAll<HTMLTableElement>('table[data-fijar-columnas]').forEach(vigilar);
}
