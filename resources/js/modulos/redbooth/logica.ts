/** Valores del servidor para el modal de Redbooth (data-redbooth-boot del modal). */
export interface RedboothBoot {
    contexto: 'programa' | 'catcodificados';
    rutas: {
        show: string;
        destroy: string;
        descargaArchivo: string;
        proyectos: string;
        store: string;
    };
}

const RUTAS = ['show', 'destroy', 'descargaArchivo', 'proyectos', 'store'] as const;

/** null si el JSON no está o le falta una ruta: el modal no se inicia (como antes sin el markup). */
export function leerBoot(texto: string | null | undefined): RedboothBoot | null {
    let datos: unknown;
    try {
        datos = JSON.parse(texto ?? '');
    } catch {
        return null;
    }
    if (!datos || typeof datos !== 'object') return null;
    const { contexto, rutas } = datos as { contexto?: unknown; rutas?: Record<string, unknown> };
    if (!rutas || RUTAS.some((r) => typeof rutas[r] !== 'string' || rutas[r] === '')) return null;

    return {
        contexto: contexto === 'catcodificados' ? 'catcodificados' : 'programa',
        rutas: Object.fromEntries(RUTAS.map((r) => [r, rutas[r] as string])) as RedboothBoot['rutas'],
    };
}

/** Fecha de Redbooth: `YYYY-MM-DD` (día local), epoch en segundos o ISO; '—' si no hay. */
export function fmtDate(value: unknown): string {
    if (!value) return '—';
    if (/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        const [year, month, day] = String(value).split('-').map(Number) as [number, number, number];
        return new Date(year, month - 1, day).toLocaleDateString('es-MX', { dateStyle: 'medium' });
    }
    const numericValue = Number(value);
    const date = Number.isFinite(numericValue) && numericValue > 100000000
        ? new Date(numericValue * 1000)
        : new Date(value as string);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
}

export interface UsuarioRedbooth {
    name?: string;
    profile_avatar_url?: string;
    avatar_url?: string;
    micro_avatar_url?: string;
}

/** Avatar solo si es https (el HTML de Redbooth no es de confianza). */
export function safeAvatarUrl(user: UsuarioRedbooth | null | undefined, origen = 'https://localhost'): string {
    const candidate = user?.profile_avatar_url || user?.avatar_url || user?.micro_avatar_url || '';
    if (!candidate) return '';
    try {
        const url = new URL(candidate, origen);
        return url.protocol === 'https:' ? url.href : '';
    } catch {
        return '';
    }
}

/** Iniciales para el avatar (máx. 2); 'RB' si no hay nombre. */
export function iniciales(autor: string): string {
    return autor.split(/\s+/).slice(0, 2).map((p) => p.charAt(0)).join('').toUpperCase() || 'RB';
}

/** Texto del estado de la tarea. */
export function textoEstado(status: unknown): string {
    const s = String(status || '').toLowerCase();
    if (s === 'open') return 'Abierto';
    if (s === 'resolved') return 'Finalizado';
    return (status as string) || 'Sin estado';
}

/** Primer error de validación, o el message, o el genérico. */
export function mensajeRespuesta(data: unknown, porDefecto: string): string {
    const d = (data && typeof data === 'object' ? data : {}) as { errors?: Record<string, unknown>; message?: unknown };
    const primero = Object.values(d.errors || {}).flat().find(Boolean);
    if (primero) return String(primero);
    return typeof d.message === 'string' && d.message ? d.message : porDefecto;
}

const ETIQUETAS_PERMITIDAS = new Set(['A', 'BR', 'CODE', 'DIV', 'EM', 'HR', 'IMG', 'LI', 'OL', 'P', 'SPAN', 'STRONG', 'TABLE', 'TBODY', 'TD', 'TH', 'THEAD', 'TR', 'UL']);

/**
 * Limpia el HTML de Redbooth dentro de `raiz` (ya parseado en un documento inerte): quita
 * comentarios y todos los atributos, desenvuelve las etiquetas fuera de la lista y reescribe
 * las imágenes al proxy de descarga (solo las que apuntan a /files/<id>/). Devuelve las
 * imágenes que quedaron, con su URL y nombre.
 */
export function limpiarNodos(raiz: ParentNode, urlArchivo: (fileId: string) => string): { img: Element; url: string; nombre: string }[] {
    const imagenes: { img: Element; url: string; nombre: string }[] = [];
    const clean = (parent: ParentNode) => {
        Array.from(parent.childNodes).forEach((node) => {
            if (node.nodeType === 8 /* COMMENT_NODE */) { node.remove(); return; }
            if (node.nodeType !== 1 /* ELEMENT_NODE */) return;
            const elem = node as Element;
            if (!ETIQUETAS_PERMITIDAS.has(elem.tagName)) {
                elem.replaceWith(...Array.from(elem.childNodes));
                clean(parent);
                return;
            }
            if (elem.tagName === 'IMG') {
                const fileId = (elem.getAttribute('src') || '').match(/\/files\/(\d+)\//)?.[1] || '';
                const alt = elem.getAttribute('alt') || 'Imagen adjunta';
                Array.from(elem.attributes).forEach((a) => elem.removeAttribute(a.name));
                if (!fileId) { elem.remove(); return; }
                const url = urlArchivo(fileId);
                elem.setAttribute('src', url);
                elem.setAttribute('alt', alt);
                elem.setAttribute('loading', 'lazy');
                imagenes.push({ img: elem, url, nombre: alt });
            } else {
                Array.from(elem.attributes).forEach((a) => elem.removeAttribute(a.name));
            }
            clean(elem);
        });
    };
    clean(raiz);
    return imagenes;
}
