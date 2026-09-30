/**
 * Mensaje del servidor dentro de un error de window.http (HttpError trae el JSON en `data`).
 *
 * Los módulos del bundle de la grilla usan los globales http/notify de bootstrap.js en vez de
 * importar utils/http.ts: importarlo arrastra sweetalert2 (vía sesion.ts → notifications.ts),
 * que toca el DOM al evaluarse y rompe tests/Js/programa-tejido-bundle.test.ts. Por eso aquí
 * no se usa `instanceof HttpError`.
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
