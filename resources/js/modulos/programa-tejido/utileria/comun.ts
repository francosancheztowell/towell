/** Utilería — piezas compartidas por Mover y Finalizar. */
import { el, icono } from '../../urdido/comun/pagina.ts';
import { HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';

type Icono = 'success' | 'error' | 'warning' | 'question';

/**
 * Aviso tras procesar: cierra el modal "Procesando…". Con timer (éxito con cierre solo) es
 * un toast; si no, un aviso modal. El color de botón por pantalla ya no aplica.
 */
export function aviso(icon: Icono, title: string, text: string, _confirmButtonColor?: string, timer?: number): Promise<unknown> {
    if (timer && icon === 'success') {
        notify.close();
        notify.success(text || title);
        return Promise.resolve();
    }
    return notify.alert(text, title, icon);
}

/** Modal "Procesando…" mientras corre la petición. */
export function procesando(text: string): void {
    void notify.loading(text);
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
