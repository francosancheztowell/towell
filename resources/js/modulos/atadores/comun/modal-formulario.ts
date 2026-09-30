/**
 * Formulario en un x-ui.modal-base (antes un SweetAlert2 con html + preConfirm).
 * Resuelve con los datos al enviar el <form> (la validación `required` la hace el navegador)
 * o con null si el modal se cierra (×, Cancelar, Esc).
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';

export function pedirFormulario(modalId: string, formId: string): Promise<FormData | null> {
    const modal = document.getElementById(modalId);
    const form = document.getElementById(formId);
    if (!modal || !(form instanceof HTMLFormElement)) return Promise.resolve(null);
    form.reset();

    return new Promise((resolve) => {
        let resuelto = false;
        const terminar = (valor: FormData | null): void => {
            if (resuelto) return;
            resuelto = true;
            observador.disconnect();
            form.removeEventListener('submit', alEnviar);
            resolve(valor);
        };
        const alEnviar = (ev: Event): void => {
            ev.preventDefault();
            terminar(new FormData(form));
            cerrarPorId(modalId);
        };
        const observador = new MutationObserver(() => {
            if (modal.classList.contains('hidden')) terminar(null);
        });
        observador.observe(modal, { attributes: true, attributeFilter: ['class'] });
        form.addEventListener('submit', alEnviar);
        abrir(modalId);
    });
}
