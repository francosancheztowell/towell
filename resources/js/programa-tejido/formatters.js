/**
 * Formatea un valor de eficiencia (0–1) como porcentaje entero.
 * @param {number} n
 * @returns {string}
 */
export function formatEficiencia(n) {
    if (!Number.isFinite(n)) return String(n ?? '');
    return `${Math.round(n * 100)}%`;
}

/**
 * Formatea un valor de fecha como dd/mm/yyyy (locale es-MX).
 * Retorna cadena vacía si el valor es falsy o el año es <= 1970.
 * @param {string|Date|null} raw
 * @returns {string}
 */
export function formatDate(raw) {
    if (!raw) return '';
    try {
        const dt = raw instanceof Date ? raw : new Date(raw);
        if (dt.getFullYear() <= 1970) return '';
        return dt.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
        return '';
    }
}

/**
 * Formatea un valor de fecha y hora como dd/mm/yyyy HH:mm (locale es-MX).
 * Retorna cadena vacía si el valor es falsy o el año es <= 1970.
 * @param {string|Date|null} raw
 * @returns {string}
 */
export function formatDateTime(raw) {
    if (!raw) return '';
    try {
        const dt = raw instanceof Date ? raw : new Date(raw);
        if (dt.getFullYear() <= 1970) return '';
        return dt.toLocaleDateString('es-MX', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    } catch {
        return '';
    }
}

/**
 * Formatea un número con 2 decimales (locale es-MX).
 * @param {string|number|null} raw
 * @returns {string}
 */
export function formatNumber(raw) {
    const n = Number(raw);
    if (!Number.isFinite(n)) return raw == null ? '' : String(raw);
    return n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
