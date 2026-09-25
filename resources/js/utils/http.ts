/**
 * Cliente HTTP unificado de la aplicación.
 *
 * Envuelve axios para:
 *  - Mandar siempre Accept: application/json, X-Requested-With y el token CSRF
 *    fresco de <meta name="csrf-token"> (por si la meta cambió).
 *  - Devolver directamente el cuerpo JSON (response.data), no el objeto Response.
 *  - Normalizar los errores: siempre lanza un HttpError con .status, .data y .errors (422 de Laravel).
 *  - Avisar a quien escuche: todo fallo emite `towell:http-error` en window con
 *    detail { status, url, method } (contrato .planning/phases/11-mon-servidor/11-CONTRACT.md §4).
 *  - Sesión expirada (419 CSRF o 401 del middleware auth): un solo aviso por página y
 *    recarga (el middleware auth lleva al login).
 *
 * Reemplaza el patrón disperso `fetch(url, { headers: { 'X-CSRF-TOKEN': getCsrfToken() } }).then(r => r.json())`.
 *
 * Uso (en scripts inline de Blade, vía window.http):
 *   const data = await http.post('/ruta', { campo: 1 });
 *   if (data.success) { ... }
 *
 *   http.delete(`/ruta/${id}`)
 *     .then(data => { ... })
 *     .catch(err => notify.error(err.message));
 */
import axios from 'axios';
import type { AxiosRequestConfig, AxiosResponse } from 'axios';
import { notify } from './notifications.ts';

export type HttpConfig = AxiosRequestConfig;

export interface HttpErrorDetail {
    status: number;
    url: string;
    method: string;
}

export class HttpError extends Error {
    status = 0;
    data: unknown = null;
    /** Errores de validación 422 de Laravel ({ campo: [mensajes] }), o null. */
    errors: Record<string, string[]> | null = null;
    original: unknown = null;
}

export const HTTP_ERROR_EVENT = 'towell:http-error';

export const SESSION_EXPIRED_MESSAGE = 'Tu sesión expiró. Recargando para volver a iniciar sesión…';

/** Tiempo para leer el aviso antes de recargar. */
export const SESSION_EXPIRED_RELOAD_MS = 2500;

let sessionExpiredHandled = false;

export function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function withHeaders(config: HttpConfig = {}): HttpConfig {
    return {
        ...config,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(config.headers as Record<string, string> | undefined),
        },
    };
}

function record(value: unknown): Record<string, unknown> | null {
    return value !== null && typeof value === 'object' ? (value as Record<string, unknown>) : null;
}

function normalizeError(err: unknown): HttpError {
    const source = record(err);
    const response = record(source?.response);
    const data = response?.data;
    const body = record(data);

    const message =
        (typeof body?.message === 'string' && body.message) ||
        (typeof data === 'string' && data) ||
        (err instanceof Error && err.message) ||
        'Error de comunicación con el servidor';

    const normalized = new HttpError(message);
    normalized.status = typeof response?.status === 'number' ? response.status : 0;
    normalized.data = data ?? null;
    normalized.errors = (record(body?.errors) as Record<string, string[]> | null) ?? null;
    normalized.original = err;

    return normalized;
}

/** Ruta sin query string ni hash (el contrato no guarda parámetros). */
function stripQuery(url: string): string {
    return url.split(/[?#]/, 1)[0] ?? '';
}

function emitError(detail: HttpErrorDetail): void {
    if (typeof window === 'undefined' || typeof window.dispatchEvent !== 'function') return;
    window.dispatchEvent(new CustomEvent<HttpErrorDetail>(HTTP_ERROR_EVENT, { detail }));
}

/**
 * Toast nativo + recarga temporizada, no un modal de SweetAlert2: el catch del caller
 * suele cerrar o abrir otro modal de Swal, lo que cerraría el aviso y recargaría al instante.
 */
function handleSessionExpired(): void {
    if (sessionExpiredHandled) return;
    sessionExpiredHandled = true;

    notify.warning(SESSION_EXPIRED_MESSAGE);
    setTimeout(() => window.location.reload(), SESSION_EXPIRED_RELOAD_MS);
}

async function request<T>(method: string, url: string, run: () => Promise<AxiosResponse<T>>): Promise<T> {
    try {
        const res = await run();

        return res.data;
    } catch (err) {
        const error = normalizeError(err);
        // Una cancelación (AbortController) es intencional: no es un fallo que reportar.
        if (axios.isCancel(err)) throw error;
        emitError({ status: error.status, url: stripQuery(url), method: method.toUpperCase() });
        if (error.status === 419 || error.status === 401) handleSessionExpired();
        throw error;
    }
}

export const http = {
    get: <T = any>(url: string, config?: HttpConfig) =>
        request<T>('get', url, () => axios.get<T>(url, withHeaders(config))),
    post: <T = any>(url: string, data?: unknown, config?: HttpConfig) =>
        request<T>('post', url, () => axios.post<T>(url, data, withHeaders(config))),
    put: <T = any>(url: string, data?: unknown, config?: HttpConfig) =>
        request<T>('put', url, () => axios.put<T>(url, data, withHeaders(config))),
    patch: <T = any>(url: string, data?: unknown, config?: HttpConfig) =>
        request<T>('patch', url, () => axios.patch<T>(url, data, withHeaders(config))),
    delete: <T = any>(url: string, config?: HttpConfig) =>
        request<T>('delete', url, () => axios.delete<T>(url, withHeaders(config))),

    /**
     * Subida de archivos (FormData). NO fija Content-Type: el navegador añade
     * el boundary multipart automáticamente.
     */
    upload: <T = any>(url: string, formData: FormData, config?: HttpConfig) =>
        request<T>('post', url, () => axios.post<T>(url, formData, withHeaders(config))),

    csrfToken,
};

export type Http = typeof http;

export default http;
