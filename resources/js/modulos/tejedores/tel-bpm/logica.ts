/**
 * Lógica pura del índice BPM Tejedores: alcance de la tabla (Mis folios / Autorizados / Todos).
 * Sin DOM: se prueba con node (tests/Js/tel-bpm.test.ts). Turno y demás columnas los cubre el
 * filtro por columna de la tabla (componentes/tabla-columnas.ts).
 *
 * Difiere de BPM Urdido/Engomado (urdido/comun/bpm/logica.ts): aquí lo oculto por defecto es
 * Autorizado, no Terminado, y el supervisor arranca viendo todos.
 */

export interface AlcanceTelBpm {
    /** Mostrar folios Autorizados. */
    autorizados: boolean;
    /** Solo los folios que recibe el usuario en sesión. */
    misFolios: boolean;
    /** Todos (anula los otros dos). */
    todos: boolean;
}

export type FiltroTelBpm = keyof AlcanceTelBpm;

export const FILTROS_TEL_BPM: readonly FiltroTelBpm[] = ['misFolios', 'autorizados', 'todos'];

export function alcanceInicial(esSupervisor: boolean): AlcanceTelBpm {
    return { autorizados: false, misFolios: !esSupervisor, todos: esSupervisor };
}

/** "Todos" apaga los otros dos; encender cualquiera de los otros apaga "Todos". */
export function alternar(alcance: AlcanceTelBpm, filtro: FiltroTelBpm): AlcanceTelBpm {
    const nuevo = { ...alcance, [filtro]: !alcance[filtro] };
    if (filtro === 'todos' && nuevo.todos) {
        nuevo.autorizados = false;
        nuevo.misFolios = false;
    } else if (filtro !== 'todos' && nuevo[filtro]) {
        nuevo.todos = false;
    }
    return nuevo;
}

export function filaVisible(fila: { status: string; nombreRecibe: string }, alcance: AlcanceTelBpm, usuario: string): boolean {
    if (!alcance.autorizados && !alcance.todos && fila.status === 'Autorizado') return false;
    if (alcance.misFolios && usuario && fila.nombreRecibe.trim().toLowerCase() !== usuario.trim().toLowerCase()) return false;
    return true;
}

export function mensajeSinResultados(alcance: AlcanceTelBpm): string {
    return alcance.misFolios && !alcance.todos ? 'No tienes folios asignados' : 'Sin resultados con los filtros aplicados';
}
