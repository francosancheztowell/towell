/**
 * Lógica pura del índice BPM (Urdido y Engomado): filtros de la tabla y autollenado de campos.
 * Sin DOM: se prueba con node (tests/Js/urdeng-bpm.test.mjs).
 */

export interface EstadoFiltros {
    /** Mostrar folios Terminados. */
    terminados: boolean;
    /** Solo los folios que recibe el usuario en sesión. */
    misFolios: boolean;
    /** Todos (anula terminados y mis folios; también muestra Autorizados al supervisor). */
    todos: boolean;
    /** '' = cualquier turno. */
    turno: string;
}

export type FiltroAlternable = 'terminados' | 'misFolios' | 'todos';

export interface FilaBpm {
    status: string;
    nombreRecibe: string;
    turnoRecibe: string;
}

export interface Contexto {
    esSupervisor: boolean;
    /** Nombre del usuario en sesión (para "Mis folios"). */
    usuario: string;
}

/** Estado al abrir la pantalla y al pulsar "Limpiar": el supervisor ve terminados; el resto, sus folios. */
export function estadoInicial(esSupervisor: boolean): EstadoFiltros {
    return { terminados: esSupervisor, misFolios: !esSupervisor, todos: false, turno: '' };
}

/** Alterna un botón de filtro con las mismas exclusiones que la vista original. */
export function alternar(estado: EstadoFiltros, filtro: FiltroAlternable): EstadoFiltros {
    const nuevo = { ...estado, [filtro]: !estado[filtro] };
    if (filtro === 'todos' && nuevo.todos) {
        nuevo.terminados = false;
        nuevo.misFolios = false;
    } else if (filtro !== 'todos' && nuevo[filtro]) {
        nuevo.todos = false;
    }
    return nuevo;
}

/** ¿Se muestra la fila con estos filtros? */
export function filaVisible(fila: FilaBpm, estado: EstadoFiltros, ctx: Contexto): boolean {
    if (ctx.esSupervisor && !estado.todos && fila.status === 'Autorizado') return false;
    if (!estado.terminados && !estado.todos && fila.status === 'Terminado') return false;
    if (estado.misFolios && ctx.usuario && fila.nombreRecibe.trim().toLowerCase() !== ctx.usuario.toLowerCase()) {
        return false;
    }
    if (estado.turno && fila.turnoRecibe !== estado.turno) return false;
    return true;
}

/** Texto de la fila vacía cuando ningún folio pasa los filtros. */
export function mensajeSinResultados(estado: EstadoFiltros): string {
    return estado.misFolios && !estado.todos ? 'No tienes folios asignados' : 'Sin resultados con los filtros aplicados';
}

/** Clases de cada botón de filtro activo; inactivo comparten las grises. */
export const CLASES_FILTRO_ACTIVO: Readonly<Record<FiltroAlternable, readonly string[]>> = {
    terminados: ['bg-amber-100', 'border-amber-400', 'text-amber-800'],
    misFolios: ['bg-blue-100', 'border-blue-400', 'text-blue-800'],
    todos: ['bg-green-100', 'border-green-400', 'text-green-800'],
};
export const CLASES_FILTRO_INACTIVO: readonly string[] = ['bg-gray-50', 'border-gray-300', 'text-gray-700'];

/**
 * Autollenado de un <select>: sus atributos data-llenar-<dato>="<id destino>" dicen qué data-<dato>
 * de la opción elegida va a qué input. Devuelve [id destino, valor] (valor '' si la opción no lo trae).
 */
export function camposAutollenado(
    select: Readonly<Record<string, string | undefined>>,
    opcion: Readonly<Record<string, string | undefined>>,
): [string, string][] {
    const pares: [string, string][] = [];
    for (const [clave, destino] of Object.entries(select)) {
        if (!clave.startsWith('llenar') || clave.length <= 'llenar'.length || !destino) continue;
        const dato = clave.charAt(6).toLowerCase() + clave.slice(7);
        pares.push([destino, opcion[dato] ?? '']);
    }
    return pares;
}
