/**
 * Inventario de materiales (hilo) de Programa Urd-Eng: lógica pura compartida por
 * Karl Mayer y Creación de órdenes (tabla de inventario seleccionable). Sin DOM.
 * Fuente: `ordenarMateriales` y el mapeo material → payload eran idénticos en
 * crear-karl-mayer.blade.php y public/js/modulos/programa_urd_eng/creacion-ordenes.js (19-05).
 */

/** Fila de inventario tal como la devuelve BomMaterialesService (TI_PRO). */
export interface MaterialInventario {
    ItemId?: string | null;
    ConfigId?: string | null;
    InventSizeId?: string | null;
    InventColorId?: string | null;
    InventLocationId?: string | null;
    InventBatchId?: string | null;
    WMSLocationId?: string | null;
    InventSerialId?: string | null;
    TwCalidadFlog?: string | null;
    TwClienteFlog?: string | null;
    ProdDate?: string | null;
    TwTiras?: number | string | null;
    PhysicalInvent?: number | string | null;
    [clave: string]: unknown;
}

/** Material seleccionado tal como lo esperan crear-orden-karl-mayer / crear-ordenes. */
export interface MaterialPayload {
    itemId: string;
    configId: string;
    inventSizeId: string;
    inventColorId: string;
    inventLocationId: string;
    inventBatchId: string;
    wmsLocationId: string;
    inventSerialId: string;
    kilos: number;
    conos: number;
    loteProv: string;
    noProv: string;
    prodDate: string | null;
}

export type ColumnaInventario =
    | 'itemId'
    | 'configId'
    | 'inventSizeId'
    | 'inventColorId'
    | 'inventLocationId'
    | 'inventBatchId'
    | 'wmsLocationId'
    | 'inventSerialId'
    | 'loteProv'
    | 'noProv'
    | 'prodDate'
    | 'conos'
    | 'kilos';

export type Direccion = 'asc' | 'desc';

/** Número tolerante a comas de miles ("1,234.5"); `def` si no es número. */
export function aNumero<D>(v: unknown, def: D): number | D {
    if (v === null || v === undefined) return def;
    const num = parseFloat(String(v).replace(/,/g, ''));
    return Number.isNaN(num) ? def : num;
}

const CAMPO_TEXTO: Partial<Record<ColumnaInventario, keyof MaterialInventario>> = {
    itemId: 'ItemId',
    configId: 'ConfigId',
    inventSizeId: 'InventSizeId',
    inventColorId: 'InventColorId',
    inventLocationId: 'InventLocationId',
    inventBatchId: 'InventBatchId',
    wmsLocationId: 'WMSLocationId',
    inventSerialId: 'InventSerialId',
    loteProv: 'TwCalidadFlog',
    noProv: 'TwClienteFlog',
};

function valorOrden(m: MaterialInventario, columna: string): string | number | null {
    const campo = CAMPO_TEXTO[columna as ColumnaInventario];
    if (campo) return String(m[campo] || '').toLowerCase();
    switch (columna) {
        case 'prodDate':
            return m.ProdDate ? new Date(m.ProdDate).getTime() : 0;
        case 'conos':
            return aNumero(m.TwTiras, 0);
        case 'kilos':
            return aNumero(m.PhysicalInvent, 0);
        default:
            return null;
    }
}

/** Copia ordenada por columna (sin columna/dirección o columna desconocida: mismo orden). */
export function ordenarMateriales<T extends MaterialInventario>(
    materiales: T[],
    columna: string | null,
    direccion: Direccion | null,
): T[] {
    if (!columna || !direccion || !materiales.length) return materiales;
    return [...materiales].sort((a, b) => {
        const va = valorOrden(a, columna);
        const vb = valorOrden(b, columna);
        if (va === null || vb === null) return 0;
        if (va < vb) return direccion === 'asc' ? -1 : 1;
        if (va > vb) return direccion === 'asc' ? 1 : -1;
        return 0;
    });
}

/** Siguiente estado al tocar el encabezado: asc → desc → asc; otra columna empieza en asc. */
export function siguienteDireccion(actual: { columna: string | null; direccion: Direccion | null }, columna: string): Direccion {
    return actual.columna === columna && actual.direccion === 'asc' ? 'desc' : 'asc';
}

/** Clave de selección de una fila (ItemId_InventSerialId). */
export function claveMaterial(m: MaterialInventario): string {
    return `${m.ItemId || ''}_${m.InventSerialId || ''}`;
}

/** Material de inventario → objeto del payload (sin `status`; creación de órdenes lo agrega). */
export function materialAPayload(m: MaterialInventario): MaterialPayload {
    return {
        itemId: m.ItemId || '',
        configId: m.ConfigId || '',
        inventSizeId: m.InventSizeId || '',
        inventColorId: m.InventColorId || '',
        inventLocationId: m.InventLocationId || '',
        inventBatchId: m.InventBatchId || '',
        wmsLocationId: m.WMSLocationId || '',
        inventSerialId: m.InventSerialId || '',
        kilos: aNumero(m.PhysicalInvent, 0),
        conos: aNumero(m.TwTiras, 0),
        loteProv: m.TwCalidadFlog || '',
        noProv: m.TwClienteFlog || '',
        prodDate: m.ProdDate || null,
    };
}
