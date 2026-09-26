/**
 * Utilidades de página compartidas por las pantallas de Urdido y Engomado (19-01).
 * Receta: .planning/phases/19-modulos/19-00-RECETA.md §2 y §4.
 */
import { HttpError } from '../../../utils/http.ts';

/** Lee el JSON de un atributo data-* (p. ej. data-pagina='@json(...)'). */
export function leerDatos<T>(el: HTMLElement | null, clave = 'pagina'): T | null {
    const crudo = el?.dataset[clave];
    if (!crudo) return null;
    try {
        return JSON.parse(crudo) as T;
    } catch {
        return null;
    }
}

/** Respuesta JSON típica de los endpoints del módulo. */
export interface RespuestaApi {
    success?: boolean;
    message?: string;
    error?: string;
    [clave: string]: unknown;
}

/**
 * Mensaje para el usuario a partir de un error. Los endpoints viejos mandan `error`,
 * los nuevos (HandlesApiErrors) `message`; ninguno manda ya getMessage() (SEC-07).
 */
export function mensajeError(err: unknown, porDefecto: string): string {
    if (err instanceof HttpError) {
        const cuerpo = (err.data ?? {}) as RespuestaApi;
        return cuerpo.message || cuerpo.error || porDefecto;
    }
    if (err instanceof ErrorApi) return err.message;
    return porDefecto;
}

/** Error de negocio devuelto con HTTP 200 y success:false (contrato legacy). */
export class ErrorApi extends Error {}

/** Lanza ErrorApi si la respuesta trae success:false. */
export function exigirExito<T extends RespuestaApi>(r: T, porDefecto: string): T {
    if (r && r.success === false) throw new ErrorApi(r.message || r.error || porDefecto);
    return r;
}

/** Plantilla de ruta con marcador: route('x', ['id' => '__ID__']) → url con el valor. */
export function rutaCon(plantilla: string, valores: Record<string, string | number>): string {
    return Object.entries(valores).reduce(
        (url, [k, v]) => url.split(`__${k.toUpperCase()}__`).join(encodeURIComponent(String(v))),
        plantilla,
    );
}

/** Crea un elemento con clases, texto y atributos (evita innerHTML con datos). */
export function el<K extends keyof HTMLElementTagNameMap>(
    tag: K,
    opciones: { clase?: string; texto?: string | number | null; attrs?: Record<string, string> } = {},
    ...hijos: (Node | string | null | undefined | false)[]
): HTMLElementTagNameMap[K] {
    const nodo = document.createElement(tag);
    if (opciones.clase) nodo.className = opciones.clase;
    if (opciones.texto !== undefined && opciones.texto !== null) nodo.textContent = String(opciones.texto);
    for (const [k, v] of Object.entries(opciones.attrs ?? {})) nodo.setAttribute(k, v);
    for (const h of hijos) if (h) nodo.append(h);
    return nodo;
}

/** Ícono Font Awesome decorativo. */
export function icono(clases: string): HTMLElement {
    return el('i', { clase: clases, attrs: { 'aria-hidden': 'true' } });
}
