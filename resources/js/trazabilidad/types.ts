export type DetailType = 'flogs' | 'trazabilidad' | 'produccion';

export interface TrazabilidadFilters {
    flog: string;
    articulo: string;
    tamano: string;
    color: string;
    mes: string;
    metrica: 'cantidad' | 'peso';
}

export interface TrazabilidadConfig {
    rutas?: {
        redbooth?: string;
        detalles?: Partial<Record<DetailType, string>>;
    };
}

export interface DetailResponse {
    html: string;
    meta: Record<string, unknown>;
}

export interface MatrixPeriod {
    nivel: 'mes' | 'semana' | 'dia';
    indices: number[];
    mesClave: string;
    semanaClave: string | null;
}

export interface MatrixDetailRow {
    articulo: string;
    color: string;
    total: number;
    /** Disperso: solo llegan los índices de día con valor. */
    valores: Record<number, number>;
}

export interface RollosRow {
    orden?: string;
    articulo?: string;
    nombreArticulo?: string;
    color?: string;
    nombreColor?: string;
    cantidad?: number | string;
    peso?: number | string;
}

export interface RedboothOrder {
    registroId?: number | string;
    source?: 'programa' | 'catcodificados';
    flogAsignacion?: string;
    totalOrdenes?: number;
}

export interface RedboothResponse {
    ordenes?: RedboothOrder[];
    primerVinculo?: RedboothOrder | null;
}

// http, notify, Swal, Livewire y jQuery se tipan en resources/js/types/global.d.ts.
declare global {
    interface Window {
        abrirModalRedboothProgramaTejido?: (order: RedboothOrder) => void;
    }
}
