/**
 * Modal "¿Cuándo se requiere el material?" (vista: programa_urd_eng/comun/modal-fecha-requerimiento.blade.php).
 * Sustituye al SweetAlert con <input type="datetime-local"> de Creación de órdenes y Karl Mayer (19-05).
 *
 *   const fecha = await pedirFechaRequerimiento();   // 'YYYY-MM-DDTHH:mm' o null si se cancela
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { rangoFechaRequerimiento, validarFechaRequerimiento } from './fecha-requerimiento.ts';

export function pedirFechaRequerimiento(id = 'modalFechaRequerimiento', ahora: Date = new Date()): Promise<string | null> {
    const dialog = document.getElementById(id);
    const input = dialog?.querySelector<HTMLInputElement>('[data-fecha-requerimiento-valor]');
    const error = dialog?.querySelector<HTMLElement>('[data-fecha-requerimiento-error]');
    const confirmar = dialog?.querySelector<HTMLElement>('[data-fecha-requerimiento-confirmar]');
    if (!dialog || !input || !error || !confirmar) return Promise.resolve(null);

    const { min, sugerida } = rangoFechaRequerimiento(ahora);
    input.min = min;
    input.value = sugerida;
    mostrarError(input, error, null);

    return new Promise((resolve) => {
        let resultado: string | null = null;

        const alConfirmar = (): void => {
            const mensaje = validarFechaRequerimiento(input.value, min);
            mostrarError(input, error, mensaje);
            if (mensaje) {
                input.focus();
                return;
            }
            resultado = input.value;
            cerrarPorId(id);
        };
        const alTeclear = (e: KeyboardEvent): void => {
            if (e.key === 'Enter') {
                e.preventDefault();
                alConfirmar();
            }
        };
        // Todo cierre (×, Cancelar, Esc, Confirmar) pasa por el botón × del modal-base.
        const alCerrar = (e: Event): void => {
            if (!(e.target instanceof Element) || !e.target.closest('[data-ui-modal-close]')) return;
            confirmar.removeEventListener('click', alConfirmar);
            input.removeEventListener('keydown', alTeclear);
            dialog.removeEventListener('click', alCerrar);
            resolve(resultado);
        };

        confirmar.addEventListener('click', alConfirmar);
        input.addEventListener('keydown', alTeclear);
        dialog.addEventListener('click', alCerrar);
        abrir(id);
    });
}

function mostrarError(input: HTMLInputElement, error: HTMLElement, mensaje: string | null): void {
    error.textContent = mensaje ?? '';
    error.hidden = !mensaje;
    input.classList.toggle('border-red-400', Boolean(mensaje));
    input.classList.toggle('border-gray-300', !mensaje);
    if (mensaje) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
}
