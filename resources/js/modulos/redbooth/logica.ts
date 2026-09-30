/** Valores del servidor para el modal de Redbooth (#redbooth-boot, application/json). */
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
