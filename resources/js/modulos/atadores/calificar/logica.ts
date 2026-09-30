/** Lógica pura de "Calificar atadores" (sin DOM). Tests: tests/Js/atadores-calificar.test.mjs. */

export const MERMA_MAX = 5;

export interface Aviso {
    titulo: string;
    texto: string;
    /** id del campo a enfocar */
    foco?: string;
}

export function normalizarMerma(valor: number): number {
    return Math.round((valor + Number.EPSILON) * 100) / 100;
}

export function textoMerma(valor: number): string {
    return normalizarMerma(valor).toString();
}

export const AVISO_MERMA_RANGO: Aviso = { titulo: 'Merma fuera de rango', texto: 'La merma no puede ser mayor a 5 kg.' };

/** Lo que se hace con lo que se teclea en Merma Kg. */
export function evaluarMermaTecleada(texto: string): 'vacia' | 'excede' | 'programar' {
    if (texto === '') return 'vacia';
    const n = parseFloat(texto);

    return !Number.isNaN(n) && n > MERMA_MAX ? 'excede' : 'programar';
}

/** Merma lista para guardar, o el aviso que corresponde. */
export function mermaParaGuardar(texto: string): { valor: number } | { aviso: Aviso } {
    const n = parseFloat(texto);
    if (Number.isNaN(n)) return { aviso: { titulo: 'Valor inválido', texto: 'La merma debe ser un número válido' } };
    if (n > MERMA_MAX) return { aviso: AVISO_MERMA_RANGO };

    return { valor: normalizarMerma(n) };
}

export interface EstadoParaTerminar {
    esKm: boolean;
    maquinas: boolean[];
    actividades: boolean[];
    devolucion: boolean;
    /** Karl Mayer: una fila por julio del atado anterior. */
    filasKm: { julio: string; metros: string; kilos: string }[];
    /** Jacquard / SMIT */
    dev: { julio: string; ubicacion: string; metros: string; kilos: string };
    merma: string;
}

const numeroPositivo = (texto: string): boolean => {
    const n = texto.trim() === '' ? Number.NaN : parseFloat(texto);

    return n > 0;
};

/** Primer motivo por el que no se puede terminar el atado (el mismo orden de siempre), o null. */
export function revisarParaTerminar(e: EstadoParaTerminar): Aviso | null {
    if (!e.esKm) {
        if (!e.maquinas.some(Boolean)) {
            return { titulo: 'Máquinas pendientes', texto: 'Debe marcar al menos una máquina antes de terminar el atado.' };
        }
        if (e.actividades.length === 0 || !e.actividades.every(Boolean)) {
            return { titulo: 'Actividades pendientes', texto: 'Todas las actividades deben estar marcadas antes de terminar el atado.' };
        }
    }

    if (e.devolucion && e.esKm) {
        if (e.filasKm.length === 0) {
            return { titulo: 'Sin atado anterior', texto: 'No hay un atado anterior de esta barra. Desmarca Devolución para terminar sin devolver.' };
        }
        if (e.filasKm.some((f) => f.julio.trim() === '' || !numeroPositivo(f.metros) || !numeroPositivo(f.kilos))) {
            return { titulo: 'Devolución incompleta', texto: 'Cada julio del atado anterior debe tener Metros y Kilos antes de terminar el atado.' };
        }
    } else if (e.devolucion) {
        if (e.dev.julio === '') {
            return { titulo: 'Julio pendiente', texto: 'Debe seleccionar un Julio en la devolución antes de terminar el atado.', foco: 'dev_no_julio' };
        }
        if (e.dev.ubicacion === '') {
            return { titulo: 'Ubicación pendiente', texto: 'Falta capturar la ubicación de la devolución antes de terminar el atado.' };
        }
        if (!numeroPositivo(e.dev.metros) || !numeroPositivo(e.dev.kilos)) {
            return { titulo: 'Devolución incompleta', texto: 'La devolución registrada debe tener Metros y Kilos capturados antes de terminar el atado.' };
        }
    }

    const merma = e.merma.trim() === '' ? Number.NaN : parseFloat(e.merma);
    if (Number.isNaN(merma)) return { titulo: 'Merma pendiente', texto: 'Captura la merma (Kg) antes de terminar el atado.' };
    if (merma > MERMA_MAX) return { ...AVISO_MERMA_RANGO, foco: 'mergaKg' };

    return null;
}

/** Clave del operador al inicio de la celda ("111 - Nombre"), o null si no hay. */
export function claveOperador(texto: string): string | null {
    const limpio = texto.trim();
    if (!limpio || limpio === '-') return null;

    return /^(\d{1,10})\b/.exec(limpio)?.[1] ?? null;
}

/** Solo quien marcó una actividad la puede desmarcar. */
export function puedeDesmarcar(textoOperador: string, cveActual: string | null | undefined): boolean {
    const cve = claveOperador(textoOperador);

    return cve === null || !cveActual || cve === String(cveActual);
}

/** Cuerpo de POST /atadores/devoluciones para Jacquard / SMIT. */
export function payloadDevolucion(refId: number | string | null, v: Record<string, string>): Record<string, unknown> {
    const vacio = (clave: string): string | null => (v[clave] ?? '') || null;
    const numero = (clave: string): number | null => ((v[clave] ?? '') !== '' ? parseFloat(v[clave] ?? '') : null);

    return {
        ref_id: refId,
        telar: vacio('dev_telar'),
        no_julio: vacio('dev_no_julio'),
        no_produccion: vacio('dev_lote'),
        kilos: numero('dev_kilos'),
        metros: numero('dev_metros'),
        ubicacion: vacio('dev_ubicacion'),
        fecha_devol: vacio('dev_fecha'),
        cuenta: vacio('dev_cuenta'),
        calibre: vacio('dev_calibre'),
        hilo: vacio('dev_hilo'),
        tipo: vacio('dev_tipo'),
        obs: vacio('dev_obs'),
    };
}

/** Julio a dejar seleccionado: el que ya estaba, el guardado o el sugerido por el servidor. */
export function julioASeleccionar(actual: string, guardado: string | null | undefined, sugerido: string | null | undefined): string {
    return actual.trim() || String(guardado ?? '').trim() || String(sugerido ?? '').trim();
}
