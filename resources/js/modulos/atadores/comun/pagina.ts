/**
 * Utilidades de página de Atadores (19-03). Receta: .planning/phases/19-modulos/19-00-RECETA.md §2 y §4.
 * (Tejido y Urdido tienen su propia copia; HANDOFF 19-02 T2 pide unificarlas en utils/.)
 */
import { HttpError } from '../../../utils/http.ts';

/** Cuerpo JSON de los endpoints del módulo: /atadores/save y devoluciones responden `ok`. */
export interface RespuestaAtadores {
    ok?: boolean;
    success?: boolean;
    message?: string;
    error?: string;
    trace_id?: string;
    [clave: string]: unknown;
}

/** Error de negocio devuelto con HTTP 200 y ok:false. */
export class ErrorAtadores extends Error {}

/** Config de la página en data-pagina='@json($config)' del nodo raíz. */
export function leerPagina<T>(id: string): { raiz: HTMLElement; datos: T } | null {
    const raiz = document.getElementById(id);
    const crudo = raiz?.dataset.pagina;
    if (!raiz || !crudo) return null;
    try {
        return { raiz, datos: JSON.parse(crudo) as T };
    } catch {
        return null;
    }
}

/** Lanza ErrorAtadores si la respuesta no trae ok:true (el contrato de siempre de estos endpoints). */
export function exigirOk<T extends RespuestaAtadores>(respuesta: T, porDefecto: string): T {
    if (respuesta?.ok !== true) throw new ErrorAtadores(respuesta?.message || respuesta?.error || porDefecto);
    return respuesta;
}

/**
 * Mensaje para el usuario. El backend ya no manda getMessage() (SEC-07), así que su texto es seguro de
 * mostrar; en un 422 se prefiere el primer error de campo.
 */
export function mensajeError(err: unknown, porDefecto: string): string {
    if (err instanceof HttpError) {
        const cuerpo = (err.data ?? {}) as RespuestaAtadores;
        const primero = err.errors ? Object.values(err.errors).flat()[0] : undefined;
        return primero || cuerpo.message || cuerpo.error || porDefecto;
    }
    if (err instanceof ErrorAtadores) return err.message;
    return porDefecto;
}

/** Muestra un elemento unos segundos (indicadores de "Guardado"). */
export function mostrarUnRato(el: Element | null, ms = 2000): void {
    if (!el) return;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), ms);
}

export function valorDe(id: string): string {
    const el = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null;
    return el ? el.value.trim() : '';
}

export function ponerValor(id: string, valor: unknown): void {
    const el = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null;
    if (el) el.value = valor == null ? '' : String(valor);
}
