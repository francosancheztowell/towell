/**
 * Piezas compartidas por los catálogos CRUD de Urdido/Engomado (19-01, unidad C):
 * Julios, Máquinas, Ubicaciones y Núcleos. Todos siguen el mismo patrón: se selecciona una
 * fila, el navbar habilita Editar/Eliminar y el formulario vive en un modal de Blade.
 *
 * Vive aquí (y no en modulos/urdido/comun/) porque esa carpeta no era de esta unidad;
 * HANDOFF: moverlo a comun/ cuando se consolide.
 */
import { delegate } from '../../../utils/dom.ts';
import { HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { mensajeError } from './pagina.ts';

/** Clases que alternan en la fila seleccionada / no seleccionada. */
export interface EstiloSeleccion {
    seleccionada: string[];
    normal: string[];
}

/** Selección de una sola fila: tocar la seleccionada la deselecciona (como antes). */
export interface Seleccion {
    actual(): HTMLTableRowElement | null;
    limpiar(): void;
}

export function crearSeleccion(
    cuerpo: HTMLElement,
    selectorFila: string,
    estilo: EstiloSeleccion | 'aria',
    alCambiar: (fila: HTMLTableRowElement | null) => void,
): Seleccion {
    let actual: HTMLTableRowElement | null = null;

    const marcar = (fila: HTMLTableRowElement, si: boolean): void => {
        if (estilo === 'aria') {
            fila.setAttribute('aria-selected', si ? 'true' : 'false');
            return;
        }
        fila.classList.remove(...(si ? estilo.normal : estilo.seleccionada));
        fila.classList.add(...(si ? estilo.seleccionada : estilo.normal));
    };

    const fijar = (fila: HTMLTableRowElement | null): void => {
        if (actual) marcar(actual, false);
        actual = fila;
        if (actual) marcar(actual, true);
        alCambiar(actual);
    };

    delegate<HTMLTableRowElement>(cuerpo, 'click', selectorFila, (_ev, fila) => {
        fijar(fila === actual ? null : fila);
    });

    return {
        actual: () => (actual && actual.isConnected ? actual : null),
        limpiar: () => fijar(null),
    };
}

/**
 * Habilita o deshabilita los botones Editar/Eliminar del navbar. El componente los pinta
 * habilitados cuando hay permiso, por eso la página los apaga al iniciar.
 */
export function activarBotones(botones: (HTMLButtonElement | null)[], habilitar: boolean, conCursor = true): void {
    for (const boton of botones) {
        if (!boton) continue;
        boton.disabled = !habilitar;
        boton.classList.toggle('opacity-50', !habilitar);
        boton.classList.toggle('cursor-not-allowed', !habilitar);
        if (conCursor) boton.classList.toggle('cursor-pointer', habilitar);
    }
}

/** Acciones por data-accion, con un solo listener en el documento (navbar + contenido). */
export function escucharAcciones(acciones: Record<string, (el: HTMLElement, ev: Event) => void>): void {
    delegate(document, 'click', '[data-accion]', (ev, el) => {
        const accion = acciones[el.dataset.accion ?? ''];
        if (!accion) return;
        ev.preventDefault();
        accion(el, ev);
    });
}

/** Error de un campo de x-ui.field (el <p data-ui-field-error> con id "<campo>-error"). */
export function errorCampo(control: HTMLElement | null, mensaje: string | null): void {
    if (!control) return;
    const p = document.getElementById(`${control.id}-error`);
    if (mensaje) {
        control.setAttribute('aria-invalid', 'true');
        if (p) {
            p.textContent = mensaje;
            p.hidden = false;
        }
        control.focus();
    } else {
        control.removeAttribute('aria-invalid');
        if (p) {
            p.textContent = '';
            p.hidden = true;
        }
    }
}

/** Aviso de un error de guardado/eliminación: 422 con detalle → lista; lo demás → toast. */
export function avisarError(err: unknown, porDefecto: string): void {
    if (err instanceof HttpError && err.status === 422 && err.errors) {
        void notify.validation(err.errors);
        return;
    }
    if (err instanceof HttpError && (err.status === 419 || err.status === 401)) return; // http ya avisa y recarga
    notify.error(mensajeError(err, porDefecto));
}

/** Toast de éxito y recarga (antes: Swal de 1.5 s y location.reload()). */
export function exitoYRecargar(mensaje: string, ms = 1200): void {
    notify.success(mensaje);
    window.setTimeout(() => window.location.reload(), ms);
}

/** Ícono de "Restablecer" girando mientras navega (antes animarRestablecer* del componente). */
export function girar(boton: HTMLElement): void {
    boton.querySelector('i')?.classList.add('fa-spin');
}
