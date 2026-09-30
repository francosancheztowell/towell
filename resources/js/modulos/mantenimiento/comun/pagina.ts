/**
 * Utilidades de página compartidas por las pantallas de Mantenimiento (19-08).
 * Receta: .planning/phases/19-modulos/19-00-RECETA.md §2 y §4.
 */
import { HttpError } from '../../../utils/http.ts';

/** Lee el JSON de un atributo data-* (p. ej. data-pagina='@json($config)'). */
export function leerDatos<T>(el: HTMLElement | null, clave = 'pagina'): T | null {
    const crudo = el?.dataset[clave];
    if (!crudo) return null;
    try {
        return JSON.parse(crudo) as T;
    } catch {
        return null;
    }
}

/** Cuerpo JSON de los endpoints de paros: `data` en éxito; `error` (422) o `message` (500, SEC-07) en fallo. */
export interface RespuestaApi<T = unknown> {
    success?: boolean;
    data?: T;
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
}

/**
 * Mensaje para el usuario. El 422 de paros trae `error` (primer error de campo ya
 * resuelto) y el 500 trae `message` genérico: ninguno lleva getMessage() (SEC-07).
 */
export function mensajeError(err: unknown, porDefecto: string): string {
    if (err instanceof HttpError) {
        const cuerpo = (err.data ?? {}) as RespuestaApi;
        return cuerpo.error || cuerpo.message || porDefecto;
    }
    return porDefecto;
}

/** Sustituye un marcador de la plantilla de ruta resuelta en PHP (`__ID__`, `__DEPTO__`…). */
export function rutaCon(plantilla: string, valores: Record<string, string>): string {
    return Object.entries(valores).reduce((url, [marcador, valor]) => url.replace(marcador, encodeURIComponent(valor)), plantilla);
}

/** Deja el select con una sola opción vacía de texto `texto`. */
export function soloPlaceholder(select: HTMLSelectElement, texto: string): void {
    select.replaceChildren(new Option(texto, ''));
}

/**
 * En las pantallas de paros se oculta el acceso "Paro" del navbar: ya se está
 * dentro del flujo y un segundo toque abría otro alta encima.
 */
export function ocultarBotonParo(urlNuevoParo: string): void {
    const ruta = new URL(urlNuevoParo, window.location.origin).pathname;
    document.querySelectorAll<HTMLAnchorElement>('a[href]').forEach((enlace) => {
        if (new URL(enlace.href, window.location.origin).pathname === ruta) {
            enlace.style.display = 'none';
        }
    });
}
