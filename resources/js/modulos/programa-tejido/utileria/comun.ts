/** Utilería — piezas compartidas por Mover y Finalizar. */
import { el, icono } from '../../urdido/comun/pagina.ts';
import { HttpError } from '../../../utils/http.ts';

type Icono = 'success' | 'error' | 'warning' | 'question';

/**
 * Aviso modal con el color de botón de cada pantalla (verde en Finalizar, azul en Mover,
 * rojo en errores), igual que antes. notify.alert no deja elegir el color.
 */
export function aviso(icon: Icono, title: string, text: string, confirmButtonColor?: string, timer?: number): Promise<unknown> {
    return Swal.fire({ icon, title, text, ...(confirmButtonColor ? { confirmButtonColor } : {}), ...(timer ? { timer } : {}) });
}

/** Modal "Procesando…" mientras corre la petición. */
export function procesando(text: string): void {
    void Swal.fire({
        title: 'Procesando...',
        text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
    });
}

/**
 * Resultado de un POST con contrato {success, message}. Un 4xx/5xx con JSON devuelve ese
 * JSON (antes fetch lo leía igual); sin respuesta, null (error de conexión).
 */
export async function postConResultado<T extends { success?: boolean; message?: string }>(
    peticion: Promise<T>,
): Promise<T | null> {
    try {
        return await peticion;
    } catch (err) {
        if (err instanceof HttpError && err.data && typeof err.data === 'object') return err.data as T;
        return null;
    }
}

/** Crea un elemento con clases y texto (el() de modulos/urdido/comun/pagina.ts). */
export function nodo<K extends keyof HTMLElementTagNameMap>(tag: K, clase = '', texto?: string | null): HTMLElementTagNameMap[K] {
    return el(tag, { clase, texto: texto ?? null });
}

export { icono };

/** Llena un <select> de telares (valor = índice en la lista). */
export function llenarTelares(select: HTMLSelectElement, telares: readonly { telar: string }[]): void {
    telares.forEach((t, i) => select.appendChild(new Option(t.telar, String(i))));
}
