/**
 * "Rango de días a considerar" antes de Liberar órdenes (Programa Tejido / Muestras).
 * Vivía como <script> inline en components/navbar/navbar.blade.php (HANDOFF PT B2).
 * Los datos salen de #navbar-dias-liberar (data-dias, data-base) que pinta el navbar; el botón
 * de components/navbar/sections/programa-tejido.blade.php sigue llamando a
 * window.mostrarModalDiasLiberar() (puente: lo llama otro archivo).
 */
import Swal from 'sweetalert2';

export const ID_DATOS = 'navbar-dias-liberar';

/** Mensaje de error o null si el valor sirve (número >= 0 con hasta 3 decimales). */
export function validarDias(valor: string | null | undefined): string | null {
    const texto = (valor ?? '').toString().trim();
    const numero = Number(texto);
    if (texto === '' || Number.isNaN(numero) || numero < 0) return 'Por favor ingrese un número válido';

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
    const diasActual = datos.dataset.dias ?? '10.999';
    const base = datos.dataset.base ?? '/planeacion/programa-tejido';

    const contenido = document.createElement('div');
    contenido.className = 'text-left';
    const etiqueta = document.createElement('label');
    etiqueta.htmlFor = 'rangoDias';
    etiqueta.className = 'block text-sm font-medium text-gray-700 mb-2';
    etiqueta.textContent = 'Ingrese el número de días (decimales permitidos, máx. 3)';
    const input = document.createElement('input');
    Object.assign(input, { type: 'number', id: 'rangoDias', step: '0.001', min: '0', max: '999.999', value: diasActual, placeholder: '10.999' });
    input.className = 'swal2-input w-full';
    input.style.margin = '0';
    input.style.width = '100%';
    contenido.append(etiqueta, input);

    const result = await Swal.fire({
        title: 'Rango de días a considerar',
        html: contenido,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#6b7280',
        focusConfirm: false,
        didOpen: () => {
            input.focus();
            input.select();
        },
        preConfirm: () => {
            const error = validarDias(input.value);
            if (error) {
                Swal.showValidationMessage(error);
                return false;
            }

            return input.value;
        },
    });

    if (result.isConfirmed && typeof result.value === 'string') {
        window.location.href = urlLiberar(base, result.value);
    }
}
