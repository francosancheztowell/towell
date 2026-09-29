/**
 * Acciones de Programa Tejido / Muestras sin `onclick` ni clic derecho (HANDOFF 17-02 B1/B2).
 *
 * - "Liberar órdenes" del navbar: `data-accion="dias-liberar"` → el modal de días de
 *   resources/js/componentes/dias-liberar.ts (antes `onclick="mostrarModalDiasLiberar()"`).
 * - Menú de la fila: además de clic derecho y mantener presionado (accionesTactiles en
 *   index.js), un botón "⋮" en el navbar que lo abre para la fila seleccionada. No va un "⋮"
 *   por fila: index.js lee el textContent de las celdas (filtros, edición inline, totales).
 */
import { botonAcciones, type PosicionAcciones } from '../utils/acciones-tactiles.ts';
import { delegate } from '../utils/dom.ts';

export const ID_BOTON_FILA = 'pt-acciones-fila';
export const SIN_SELECCION = 'Selecciona una fila para ver sus acciones';

/** `abrir` = mostrarModalDiasLiberar (index.js lo importa; aquí no, para probarlo sin SweetAlert). */
export function enlazarDiasLiberar(root: Document | Element, abrir: () => unknown): () => void {
    return delegate(root, 'click', '[data-accion="dias-liberar"]', (event) => {
        event.preventDefault();
        void abrir();
    });
}

/**
 * Pone el "⋮" en el contenedor #pt-acciones-fila del navbar (si la vista lo trae). Abre el
 * menú de la fila seleccionada; sin selección, avisa.
 */
export function enlazarBotonAccionesFila(
    abrirMenu: (fila: HTMLElement, pos: PosicionAcciones) => void,
    filaSeleccionada: () => HTMLElement | null,
    avisar: (mensaje: string) => void,
    doc: Document = document,
): HTMLButtonElement | null {
    const contenedor = doc.getElementById(ID_BOTON_FILA);
    if (!contenedor) return null;

    return botonAcciones(contenedor, (_el, pos) => {
        const fila = filaSeleccionada();
        if (!fila || !fila.isConnected) {
            avisar(SIN_SELECCION);
            return;
        }
        abrirMenu(fila, pos);
    }, { etiqueta: 'Acciones de la fila' });
}
