/**
 * Mensaje del servidor dentro de un error de window.http (HttpError trae el JSON en `data`).
 *
 * Los módulos del bundle de la grilla usan los globales http/notify de bootstrap.js en vez de
 * importar utils/http.ts: importarlo arrastraba sweetalert2 (vía sesion.ts → notifications.ts),
 * que tocaba el DOM al evaluarse y rompía tests/Js/programa-tejido-bundle.test.ts. Por eso aquí
 * no se usa `instanceof HttpError`. sweetalert2 ya se retiró (2026-10): la restricción sobra.
 */
export function mensajeDelServidor(err: unknown, porDefecto: string): string {
    const data = (err as { data?: unknown } | null | undefined)?.data;
    if (data && typeof data === 'object') {
        const { message, error } = data as { message?: unknown; error?: unknown };
        if (typeof message === 'string' && message !== '') return message;
        if (typeof error === 'string' && error !== '') return error;
    }
    return porDefecto;
}

/** El JSON de un error de window.http, o null si no es un error HTTP. */
export function datosDelError<T>(err: unknown): T | null {
    const data = (err as { data?: unknown } | null | undefined)?.data;
    return data && typeof data === 'object' ? (data as T) : null;
}

/** true si la petición se canceló con un AbortController (axios: ERR_CANCELED). */
export function esCancelacion(err: unknown): boolean {
    const e = err as { name?: string; code?: string; original?: { name?: string; code?: string } } | null | undefined;
    return e?.name === 'AbortError' || e?.name === 'CanceledError' || e?.code === 'ERR_CANCELED'
        || e?.original?.code === 'ERR_CANCELED' || e?.original?.name === 'CanceledError';
}

/** Código HTTP de un error de window.http (0 = no hubo respuesta: red caída). */
export function statusDelError(err: unknown): number {
    const s = (err as { status?: unknown } | null | undefined)?.status;
    return typeof s === 'number' ? s : 0;
}
