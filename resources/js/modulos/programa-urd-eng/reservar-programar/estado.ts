/**
 * Estado compartido de Reservar y programar: configuración del servidor (data-pagina del nodo
 * raíz #pu-pagina), selección, filtros y datos de las dos tablas.
 */
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { notify } from '../../../utils/notifications.ts';
import { clonar } from './logica.ts';
import type { Aviso, FilterStore, PuConfig, RawRow, SelectedInventario, SelectedTelar, SortRule, TableKey } from './types.ts';

export interface PuState {
    filters: FilterStore;
    selectedTelar: SelectedTelar | null;
    selectedTelares: SelectedTelar[];
    /** Piezas elegidas para la reserva: una en rizo/pie, hasta cuatro en una barra de KM. */
    selectedInventarios: SelectedInventario[];
    sort: Record<TableKey, SortRule[]>;
    telaresData: RawRow[];
    telaresDataOriginal: RawRow[];
    inventarioData: RawRow[];
    inventarioDataOriginal: RawRow[];
    mostrarTodoInventario: boolean;
}

export const cfg: PuConfig = {
    api: {
        inventarioTelares: '',
        inventarioDisponibleGet: '',
        programarRequerimientos: '',
        actualizarTelar: '',
        reservarInventario: '',
        liberarTelar: '',
    },
    can: { modificar: false, crear: false, eliminar: false },
    telares: [],
};

export const state: PuState = {
    filters: { telares: {}, inventario: {} },
    selectedTelar: null,
    selectedTelares: [],
    selectedInventarios: [],
    sort: { telares: [{ column: 'no_telar', direction: 'asc' }], inventario: [] },
    telaresData: [],
    telaresDataOriginal: [],
    inventarioData: [],
    inventarioDataOriginal: [],
    mostrarTodoInventario: false,
};

/** Lee data-pagina del nodo raíz. Devuelve false si la vista no es esta pantalla. */
export function cargarConfig(raiz: HTMLElement | null): boolean {
    const leido = leerDatos<Partial<PuConfig>>(raiz);
    if (!leido) return false;
    Object.assign(cfg.api, leido.api ?? {});
    Object.assign(cfg.can, leido.can ?? {});
    cfg.telares = Array.isArray(leido.telares) ? leido.telares : [];
    state.telaresData = cfg.telares;
    state.telaresDataOriginal = clonar(cfg.telares);
    return true;
}

/* ---------- DOM ---------- */

export const $ = <T extends HTMLElement = HTMLElement>(sel: string, c: ParentNode = document): T | null => c.querySelector<T>(sel);
export const $$ = <T extends HTMLElement = HTMLElement>(sel: string, c: ParentNode = document): T[] => Array.from(c.querySelectorAll<T>(sel));

export const disable = (el: HTMLElement | null, v = true): void => {
    if (el) (el as HTMLButtonElement).disabled = v;
};

/** Aviso corto (toast). Título y texto van en una línea: "Título: texto". */
export function avisar(a: Aviso): void {
    notify[a.tipo](a.texto ? `${a.titulo}: ${a.texto}` : a.titulo);
}

/** Aviso modal (validaciones que el usuario debe ver sí o sí). */
export function alertar(a: Aviso): void {
    void notify.alert(a.texto ?? a.titulo, a.titulo, a.tipo);
}
