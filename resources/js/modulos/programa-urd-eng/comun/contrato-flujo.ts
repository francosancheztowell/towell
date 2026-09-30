/**
 * Contrato de datos del flujo de Programa Urdido-Engomado (19-05):
 *
 *   reservar-programar ──(?telares=JSON  +  sessionStorage['selectedTelares'])──▶ programacion-requerimientos
 *   programacion-requerimientos ──(?telares=JSON)──▶ creacion-ordenes
 *
 * Solo tipos y funciones puras (sin DOM): lo importan las tres pantallas y los tests node.
 * El servidor (ReservarProgramarController::parseTelaresFromQuery) lee el mismo `?telares=`.
 */

/** Clave de sessionStorage que deja reservar-programar y consume (y borra) programación de requerimientos. */
export const CLAVE_SESION_TELARES = 'selectedTelares';

/** Parámetro de query con el JSON de telares, en las dos navegaciones. */
export const PARAM_TELARES = 'telares';

/**
 * Telar seleccionado en reservar-programar (su `SelectedTelar`).
 * Selección individual manda solo id…tipo_atado; la múltiple manda el objeto completo.
 * El controller agrega/corrige `id`, `fecha` y `turno` (enriquecerTelaresConId).
 */
export interface TelarSeleccionado {
    id?: string | number | null;
    no_telar: string | null;
    tipo?: string | null;
    cuenta?: string | null;
    salon?: string | null;
    calibre?: string | number | null;
    hilo?: string | null;
    fecha?: string | null;
    turno?: string | number | null;
    tipo_atado?: string | null;
    no_julio?: string;
    julios?: string[];
    ordenes?: string[];
    max_julios?: number;
    no_orden?: string;
    reservado?: boolean;
    programado?: boolean;
    is_reservado?: boolean;
    is_programado?: boolean;
    /** Campos opcionales que algunas pantallas ya traen capturados. */
    tamano?: string | null;
    urdido?: string | null;
    metros?: string | number | null;
    kilos?: string | number | null;
    fecha_req?: string | null;
}

/** Un renglón por telar que programación de requerimientos manda a creación de órdenes. */
export interface TelarRequerimiento {
    no_telar: string;
    /** YYYY-MM-DD */
    fecha_req: string;
    cuenta: string;
    calibre: number | null;
    hilo: string;
    tamano: string;
    urdido: string;
    tipo: 'Rizo' | 'Pie' | string;
    /** Siempre '' desde esta pantalla: creación de órdenes resuelve el destino por telar. */
    destino: string;
    tipo_atado: string;
    /** Número sin separadores de miles, como texto ('1234.50'). */
    metros: string;
    kilos: string;
    agrupar: true;
}

/**
 * Interpreta el JSON de telares venga de la query (ya decodificada por URLSearchParams)
 * o de sessionStorage (JSON plano). Tolera un JSON doblemente codificado. Nunca lanza.
 */
export function parsearTelares<T = TelarSeleccionado>(crudo: string | null | undefined): T[] {
    if (!crudo) return [];
    for (const intento of [() => crudo, () => decodeURIComponent(crudo)]) {
        try {
            const valor: unknown = JSON.parse(intento());
            return Array.isArray(valor) ? (valor as T[]) : [];
        } catch {
            // se intenta la siguiente forma
        }
    }
    return [];
}

/** URL de destino con `?telares=<JSON>` (respeta una query que ya traiga la ruta). */
export function urlConTelares(base: string, telares: readonly unknown[]): string {
    const separador = base.includes('?') ? '&' : '?';
    return `${base}${separador}${PARAM_TELARES}=${encodeURIComponent(JSON.stringify(telares))}`;
}
