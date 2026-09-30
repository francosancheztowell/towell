export type TableKey = 'telares' | 'inventario';

export type SortDirection = 'asc' | 'desc';

export interface SortRule {
    column: string;
    direction: SortDirection;
}

/** Filtros por columna de una tabla (menú de encabezado y chips): columna → valor. */
export type FilterMap = Record<string, string>;

export type FilterStore = Record<TableKey, FilterMap>;

/** Fila cruda tal como llega del backend (claves en snake_case / PascalCase). */
export type RawRow = Record<string, unknown>;

export interface PuApi {
    inventarioTelares: string;
    inventarioDisponibleGet: string;
    programarRequerimientos: string;
    actualizarTelar: string;
    reservarInventario: string;
    liberarTelar: string;
}

export interface PuCan {
    modificar: boolean;
    crear: boolean;
    eliminar: boolean;
}

export interface PuConfig {
    api: PuApi;
    can: PuCan;
    telares: RawRow[];
}

/** Selección individual de telar normalizada para el resto del flujo. */
export interface SelectedTelar {
    id: string | null;
    no_telar: string | null;
    tipo: string;
    cuenta: string;
    salon: string;
    calibre: string;
    hilo: string;
    no_julio: string;
    /** Julios asignados a la fila: hasta cuatro en una barra de Karl Mayer, uno en rizo/pie. */
    julios: string[];
    /** Orden de cada julio, en la misma posición que `julios`. */
    ordenes: string[];
    max_julios: number;
    no_orden: string;
    fecha: string;
    turno: string;
    tipo_atado: string;
    reservado: boolean;
    programado: boolean;
    is_reservado: boolean;
    is_programado: boolean;
}

/** Pieza de inventario seleccionada. */
export interface SelectedInventario {
    itemId: string;
    configId: string;
    inventSizeId: string;
    inventColorId: string;
    inventLocationId: string;
    inventBatchId: string;
    wmsLocationId: string;
    inventSerialId: string;
    metros: number;
    numJulio: string;
    tipo: string;
    data: RawRow | undefined;
}

/** Aviso para el usuario que devuelve la lógica pura (el DOM decide cómo mostrarlo). */
export interface Aviso {
    tipo: 'success' | 'info' | 'warning' | 'error';
    titulo: string;
    texto?: string;
}
