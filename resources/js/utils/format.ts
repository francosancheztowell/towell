/**
 * Formato compartido: una sola copia de escapeHtml, debounce y los formateadores
 * es-MX (antes había ~13 escapeHtml y ~5 debounce repartidos por vistas y módulos).
 *
 * Sin DOM: todo funciona igual en el navegador y en los tests de node.
 */

const LOCALE = 'es-MX';
const TIME_ZONE = 'America/Mexico_City';

const HTML_ESCAPES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/** Escapa texto para interpolarlo en HTML (contenido o atributo). null/undefined → ''. */
export function escapeHtml(value: unknown): string {
    return value == null ? '' : String(value).replace(/[&<>"']/g, (ch) => HTML_ESCAPES[ch] ?? ch);
}

export type Debounced<A extends unknown[]> = ((...args: A) => void) & { cancel(): void };

/** Retrasa `fn` hasta que pasen `wait` ms sin nuevas llamadas; `.cancel()` descarta la pendiente. */
export function debounce<A extends unknown[]>(fn: (...args: A) => void, wait = 300): Debounced<A> {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const debounced = (...args: A): void => {
        if (timer !== undefined) clearTimeout(timer);
        timer = setTimeout(() => {
            timer = undefined;
            fn(...args);
        }, wait);
    };

    debounced.cancel = (): void => {
        if (timer !== undefined) clearTimeout(timer);
        timer = undefined;
    };

    return debounced;
}

function toNumber(value: unknown): number | null {
    if (value == null || value === '') return null;
    const n = typeof value === 'number' ? value : Number(String(value).replace(/,/g, ''));

    return Number.isFinite(n) ? n : null;
}

/**
 * Número con separador de miles es-MX. Con `decimals` fija los decimales; sin él
 * muestra hasta 2. Vacío, null o no numérico → ''.
 */
export function formatNumber(value: unknown, decimals?: number): string {
    const n = toNumber(value);
    if (n === null) return '';

    return n.toLocaleString(
        LOCALE,
        decimals === undefined
            ? { maximumFractionDigits: 2 }
            : { minimumFractionDigits: decimals, maximumFractionDigits: decimals },
    );
}

/**
 * Las fechas "YYYY-MM-DD" (sin hora) son fechas de calendario: se muestran tal cual,
 * sin corrimiento de zona. Todo lo demás se interpreta como instante y se muestra
 * en hora de Ciudad de México.
 */
function toDate(value: unknown): { date: Date; calendar: boolean } | null {
    if (value == null || value === '') return null;
    if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : { date: value, calendar: false };

    const text = String(value).trim();
    const day = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (day) {
        const date = new Date(Date.UTC(Number(day[1]), Number(day[2]) - 1, Number(day[3])));

        return Number.isNaN(date.getTime()) ? null : { date, calendar: true };
    }

    const date = new Date(typeof value === 'number' ? value : text);

    return Number.isNaN(date.getTime()) ? null : { date, calendar: false };
}

/** Fecha dd/mm/aaaa (o el formato de `options`) en es-MX. Inválida o vacía → ''. */
export function formatDate(value: unknown, options: Intl.DateTimeFormatOptions = {}): string {
    const parsed = toDate(value);
    if (!parsed) return '';

    return parsed.date.toLocaleDateString(LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...options,
        timeZone: parsed.calendar ? 'UTC' : TIME_ZONE,
    });
}

/** Fecha y hora dd/mm/aaaa, HH:mm (24 h) en hora de Ciudad de México. Inválida o vacía → ''. */
export function formatDateTime(value: unknown, options: Intl.DateTimeFormatOptions = {}): string {
    const parsed = toDate(value);
    if (!parsed) return '';

    return parsed.date.toLocaleString(LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        ...options,
        timeZone: parsed.calendar ? 'UTC' : TIME_ZONE,
    });
}
