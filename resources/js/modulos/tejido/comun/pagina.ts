/**
 * Utilidades de página compartidas por las pantallas de Tejido (19-02).
 * Receta: .planning/phases/19-modulos/19-00-RECETA.md §2 y §4.
 */
import { HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';

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

/** Cuerpo JSON típico de los endpoints del módulo. */
export interface RespuestaApi {
    success?: boolean;
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
    [clave: string]: unknown;
}

/** Error de negocio devuelto con HTTP 200 y success:false (contrato legacy). */
export class ErrorApi extends Error {}

/**
 * Mensaje para el usuario a partir de un error. Unos endpoints mandan `error` y otros `message`;
 * ninguno manda ya getMessage() (SEC-07), así que el texto del servidor es seguro de mostrar.
 * En un 422 se prefiere el primer error de campo.
 */
export function mensajeError(err: unknown, porDefecto: string): string {
    if (err instanceof HttpError) {
        const cuerpo = (err.data ?? {}) as RespuestaApi;
        const primero = err.errors ? Object.values(err.errors).flat()[0] : undefined;
        return primero || cuerpo.message || cuerpo.error || porDefecto;
    }
    if (err instanceof ErrorApi) return err.message;
    return porDefecto;
}

/** Lanza ErrorApi si la respuesta vino con success:false. */
export function exigirExito<T extends RespuestaApi>(respuesta: T, porDefecto: string): T {
    if (respuesta && respuesta.success === false) throw new ErrorApi(respuesta.message || respuesta.error || porDefecto);
    return respuesta;
}

/** Alerta de error bloqueante con el mensaje seguro del servidor. */
export function alertaError(err: unknown, porDefecto: string): void {
    void notify.alert(mensajeError(err, porDefecto), 'Error', 'error');
}
