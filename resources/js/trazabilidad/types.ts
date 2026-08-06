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

interface HttpOptions {
    params?: object;
    signal?: AbortSignal;
}

interface HttpClient {
    get<T>(url: string, options?: HttpOptions): Promise<T>;
}

interface NotificationClient {
    success(message: string): void;
    warning(message: string): void;
    error(message: string): void;
}

interface LivewireClient {
    dispatch(event: string, params?: Record<string, unknown>): void;
}

interface Select2Bridge {
    data(key: string): unknown;
    select2(command: 'close' | 'destroy' | Record<string, unknown>): void;
    off(events: string): Select2Bridge;
    on(events: string, handler: (event: Event) => void): Select2Bridge;
}

interface JQueryBridge {
    (element: Element): Select2Bridge;
}

declare global {
    interface Window {
        http: HttpClient;
        notify?: NotificationClient;
        Livewire?: LivewireClient;
        jQuery?: JQueryBridge;
        abrirModalRedboothProgramaTejido?: (order: RedboothOrder) => void;
        Swal?: {
            fire(options: Record<string, unknown>): Promise<unknown>;
        };
    }
}
