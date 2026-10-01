/**
 * Acciones de Programa Tejido / Muestras sin `onclick` ni clic derecho (HANDOFF 17-02 B1/B2).
 *
 * - "Liberar órdenes" del navbar: `data-accion="dias-liberar"` → el modal de días de
 *   resources/js/componentes/dias-liberar.ts (antes, un atributo onclick en línea).
 * - Menú de la fila: clic derecho y mantener presionado (accionesTactiles en index.js).
 */
import { delegate } from '../utils/dom.ts';

/** `abrir` = mostrarModalDiasLiberar (index.js lo importa; aquí no, para probarlo sin SweetAlert). */
export function enlazarDiasLiberar(root: Document | Element, abrir: () => unknown): () => void {
    return delegate(root, 'click', '[data-accion="dias-liberar"]', (event) => {
        event.preventDefault();
        void abrir();
    });
}
