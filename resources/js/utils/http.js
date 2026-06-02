/**
 * Cliente HTTP unificado de la aplicación.
 *
 * Envuelve axios (ya configurado con CSRF y X-Requested-With en bootstrap.js) para:
 *  - Reaplicar el token CSRF fresco en cada llamada (por si la meta cambió).
 *  - Devolver directamente el cuerpo JSON (response.data), no el objeto Response.
 *  - Normalizar los errores: siempre lanza un Error con .status, .data y .errors (422 de Laravel).
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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function withCsrf(config = {}) {
    return {
        ...config,
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(config.headers || {}),
        },
    };
}

function normalizeError(err) {
    const response = err.response;
    const data = response?.data;
    const message =
        (data && data.message) ||
        (typeof data === 'string' && data) ||
        err.message ||
        'Error de comunicación con el servidor';

    const normalized = new Error(message);
    normalized.status = response?.status ?? 0;
    normalized.data = data ?? null;
    normalized.errors = (data && data.errors) || null; // Errores de validación 422 de Laravel
    normalized.original = err;
    return normalized;
}

async function request(promise) {
    try {
        const res = await promise;
        return res.data;
    } catch (err) {
        throw normalizeError(err);
    }
}

export const http = {
    get: (url, config) => request(axios.get(url, withCsrf(config))),
    post: (url, data, config) => request(axios.post(url, data, withCsrf(config))),
    put: (url, data, config) => request(axios.put(url, data, withCsrf(config))),
    patch: (url, data, config) => request(axios.patch(url, data, withCsrf(config))),
    delete: (url, config) => request(axios.delete(url, withCsrf(config))),

    /**
     * Subida de archivos (FormData). NO fija Content-Type: el navegador añade
     * el boundary multipart automáticamente.
     */
    upload: (url, formData, config) => request(axios.post(url, formData, withCsrf(config))),

    csrfToken,
};

export default http;
