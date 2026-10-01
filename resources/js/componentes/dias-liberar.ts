/**
 * "Rango de días a considerar" antes de Liberar órdenes (Programa Tejido / Muestras).
 * Vivía como <script> inline en components/navbar/navbar.blade.php (HANDOFF PT B2).
 * Los datos salen de #navbar-dias-liberar (data-dias, data-base) que pinta el navbar; el botón
 * de components/navbar/sections/programa-tejido.blade.php sigue llamando a
 * window.mostrarModalDiasLiberar() (puente: lo llama otro archivo).
 */
import { escapeHtml } from '../utils/format.ts';
import { notify } from '../utils/notifications.ts';

export const ID_DATOS = 'navbar-dias-liberar';
/** Mismo tope que el max del input: dentro del diálogo la validación nativa no corre. */
export const DIAS_MAX = 999.999;

/** Mensaje de error o null si el valor sirve (0 a 999.999, hasta 3 decimales). */
export function validarDias(valor: string | null | undefined): string | null {
    const texto = (valor ?? '').toString().trim();
    const numero = Number(texto);
    if (texto === '' || Number.isNaN(numero) || numero < 0 || numero > DIAS_MAX) return 'Por favor ingrese un número válido';

    const partes = texto.split('.');
    if (partes.length > 1 && (partes[1] ?? '').length > 3) return 'Máximo 3 decimales permitidos';

    return null;
}

export function urlLiberar(base: string, dias: string): string {
    return `${base}/liberar-ordenes?dias=${encodeURIComponent(dias)}`;
}

export async function mostrarModalDiasLiberar(): Promise<void> {
    const datos = document.getElementById(ID_DATOS);
    if (!datos) return;
    const diasActual = datos.dataset.dias || '10.999';
    const base = datos.dataset.base || '/planeacion/programa-tejido';

    const html = `<div class="text-left">
        <label for="rangoDias" class="block text-sm font-medium text-gray-700 mb-2">Ingrese el número de días (decimales permitidos, máx. 3)</label>
        <input type="number" id="rangoDias" step="0.001" min="0" max="999.999" value="${escapeHtml(diasActual)}" placeholder="10.999" class="w-full">
    </div>`;
    const campo = (root: HTMLElement): HTMLInputElement | null => root.querySelector<HTMLInputElement>('#rangoDias');

    const dias = await notify.form<string>({
        title: 'Rango de días a considerar',
        html,
        icon: 'question',
        confirmText: 'Aceptar',
        cancelText: 'Cancelar',
        didOpen: (root) => {
            // Mensajes propios (validarDias) en lugar de la burbuja nativa del max/step.
            root.closest('form')?.setAttribute('novalidate', '');
            const input = campo(root);
            input?.focus();
            input?.select();
        },
        preConfirm: (ctx) => {
            const valor = campo(ctx.raiz)?.value ?? '';
            const error = validarDias(valor);
            if (error) {
                ctx.error(error);
                return false;
            }

            return valor;
        },
    });

    if (typeof dias === 'string') {
        window.location.href = urlLiberar(base, dias);
    }
}
