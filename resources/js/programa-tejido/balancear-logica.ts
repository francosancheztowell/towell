/**
 * Lógica pura del balanceo de órdenes compartidas (sin DOM). La usa balancear.ts y la
 * prueba tests/Js/programa-tejido-balancear.test.ts.
 */

/** Registro de un grupo de orden compartida (registros-ord-compartida/{ord}). */
export interface RegistroBalanceo {
    Id: number | string;
    NoTelarId?: string | null;
    NombreProducto?: string | null;
    Posicion?: number | string | null;
    TotalPedido?: number | string | null;
    Produccion?: number | string | null;
    StdDia?: number | string | null;
    FechaInicio?: string | null;
    FechaFinal?: string | null;
    FechaCreacion?: string | null;
    HoraCreacion?: string | null;
    OrdCompartida?: number | string | null;
    OrdCompartidaLider?: number | string | boolean | null;
}

/** Valores vivos de un input de pedido del modal (dataset). */
export interface DatosInput {
    pedido: number;
    fechaInicioMs: number;
    duracionOriginalMs: number;
    fechaFinalCalcMs: number;
}

export type MapaDias = Record<string, number>;

export interface CandidatoLider {
    id: number;
    noTelarId: string;
    isLeader: boolean;
    fechaInicioMs: number | null;
    fechaInicioKey: string | null;
    fechaCreacionMs: number | null;
    pedidoActual: number;
}

/**
 * Fechas SQL/Carbon `Y-m-d` o `Y-m-d H:i:s` como calendario/hora local (evita UTC de
 * `new Date('YYYY-MM-DD')` y desfase de 1 día).
 */
export function parseFechaBackendALocal(s: unknown): Date | null {
    if (s == null || s === '') return null;
    const t = String(s).trim();
    const m = t.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{1,2}):(\d{1,2}):(\d{1,2})(?:\.\d+)?)?/);
    if (m) {
        const dt = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]),
            m[4] !== undefined ? Number(m[4]) : 0, m[5] !== undefined ? Number(m[5]) : 0, m[6] !== undefined ? Number(m[6]) : 0);
        return isNaN(dt.getTime()) ? null : dt;
    }
    const fallback = new Date(t);
    return isNaN(fallback.getTime()) ? null : fallback;
}

/** `YYYY-MM-DD` en hora local (valor de un <input type="date">). */
export function toDateInputValueLocal(d: unknown): string {
    if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '';
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** dd/mm/aaaa (es-MX); '-' si no hay fecha o es ≤ 1970. */
export function formatearFecha(fecha: unknown): string {
    if (!fecha) return '-';
    try {
        const raw = String(fecha).trim();
        const d = /^\d{4}-\d{2}-\d{2}/.test(raw) ? parseFechaBackendALocal(raw) : new Date(fecha as string);
        if (!d || isNaN(d.getTime()) || d.getFullYear() <= 1970) return '-';
        return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
        return '-';
    }
}

/** Número de un texto con formato ("1,234.5 pzas" → 1234.5); 0 si no hay número. */
export function parseNumber(val: unknown): number {
    if (val === null || val === undefined) return 0;
    const n = Number(String(val).replace(/[^0-9.\-]/g, ''));
    return isNaN(n) ? 0 : n;
}

export function parseSQLDateToMs(sql: unknown): number {
    if (!sql) return 0;
    const d = parseFechaBackendALocal(String(sql).trim());
    return !d || isNaN(d.getTime()) ? 0 : d.getTime();
}

/** Orden del grupo: Posicion, luego telar (numérico), luego Id. */
export function sortRegistrosPorFechaTelar<T extends RegistroBalanceo>(registros: readonly T[]): T[] {
    return [...registros].sort((a, b) => {
        const aPosicion = Number(a?.Posicion) || 0;
        const bPosicion = Number(b?.Posicion) || 0;
        if (aPosicion !== bPosicion) return aPosicion - bPosicion;

        const telarCmp = String(a?.NoTelarId ?? '').localeCompare(String(b?.NoTelarId ?? ''), 'es-MX', { numeric: true, sensitivity: 'base' });
        if (telarCmp !== 0) return telarCmp;

        return (Number(a?.Id) || 0) - (Number(b?.Id) || 0);
    });
}

export function parseDateOnlyTimeToMs(dateValue: unknown, timeValue: unknown = '00:00:00'): number | null {
    if (!dateValue) return null;
    const fecha = String(dateValue).trim().split(' ')[0];
    const hora = String(timeValue || '00:00:00').trim() || '00:00:00';
    const parsed = parseFechaBackendALocal(`${fecha} ${hora}`);
    return parsed && !Number.isNaN(parsed.getTime()) ? parsed.getTime() : null;
}

/** Medianoche local de una fecha. */
export function normalizeToLocalMidnight(date: Date | number | string | null | undefined): Date | null {
    if (!date) return null;
    const d = date instanceof Date ? date : new Date(date);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

/** Clave `YYYY-MM-DD` en hora local. */
export function getDateKeyLocal(date: Date | number | string | null | undefined): string | null {
    if (!date) return null;
    const d = date instanceof Date ? date : new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Días (medianoche local) de min a max, inclusive. */
export function buildDateRange(minDate: Date | number | null, maxDate: Date | number | null): Date[] {
    const res: Date[] = [];
    const min = normalizeToLocalMidnight(minDate);
    const max = normalizeToLocalMidnight(maxDate);
    if (!min || !max) return res;

    const cur = new Date(min.getTime());
    while (cur <= max) {
        res.push(new Date(cur.getTime()));
        cur.setDate(cur.getDate() + 1);
    }
    return res;
}

const redondear3 = (n: number) => Math.round((n + Number.EPSILON) * 1000) / 1000;

/** Capacidad teórica por día (piezas) = StdDía × (horas productivas ese día / 24). */
export function capByDayFromHorasPorDia(horasPorDia: MapaDias, stdDia: unknown): MapaDias | null {
    const std = Number(stdDia) || 0;
    if (std <= 0) return null;
    const cap: MapaDias = {};
    Object.entries(horasPorDia).forEach(([key, horasDia]) => {
        cap[key] = redondear3((std / 24) * horasDia);
    });
    return cap;
}

export interface LineaTiempo {
    map: MapaDias;
    min: number | null;
    max: number | null;
    capByDay: MapaDias | null;
}

/**
 * Reparte el saldo nuevo de un registro entre los días de su ventana inicio–fin (preview del
 * Gantt con los pedidos que el usuario está editando), prorrateando por horas de cada día.
 */
export function mapWithScaledTimeline(reg: RegistroBalanceo, inputsMap: Record<string, Partial<DatosInput>>): LineaTiempo {
    const datos = inputsMap[String(reg.Id)] || {};
    const stdDia = Number(reg.StdDia || 0) || 0;

    const pedidoNuevo = datos.pedido ?? Number(reg.TotalPedido || 0);
    const produccion = Number(reg.Produccion || 0);
    const saldoNuevo = Math.max(0, pedidoNuevo - produccion);
    if (saldoNuevo <= 0) return { map: {}, min: null, max: null, capByDay: null };

    const fechaInicioMs = datos.fechaInicioMs ||
        (reg.FechaInicio ? (parseFechaBackendALocal(String(reg.FechaInicio).trim())?.getTime() ?? 0) : 0);
    const fechaFinalCalcMs = datos.fechaFinalCalcMs || 0;
    // si ya hay preview exacto del backend, úsalo
    const fechaFinDestinoMs = fechaFinalCalcMs ||
        (reg.FechaFinal ? (parseFechaBackendALocal(String(reg.FechaFinal).trim())?.getTime() ?? 0) : 0);

    if (!fechaInicioMs || !fechaFinDestinoMs || fechaFinDestinoMs <= fechaInicioMs) {
        const inicioDate = fechaInicioMs ? new Date(fechaInicioMs) : null;
        const key = inicioDate ? (getDateKeyLocal(inicioDate) as string) : 'N/A';
        const minNormalized = inicioDate ? (normalizeToLocalMidnight(inicioDate) as Date).getTime() : null;
        const maxNormalized = fechaFinDestinoMs ? (normalizeToLocalMidnight(new Date(fechaFinDestinoMs)) as Date).getTime() : null;
        let capByDay: MapaDias | null = null;
        if (stdDia > 0 && key !== 'N/A') {
            const horasVentana = fechaFinDestinoMs > fechaInicioMs
                ? Math.min(24, Math.max(0, (fechaFinDestinoMs - fechaInicioMs) / 3600000))
                : 24;
            capByDay = { [key]: redondear3((stdDia / 24) * horasVentana) };
        }
        return { map: { [key]: saldoNuevo }, min: minNormalized, max: maxNormalized, capByDay };
    }

    const inicio = new Date(fechaInicioMs);
    const fin = new Date(fechaFinDestinoMs);
    const totalHoras = Math.abs(fin.getTime() - inicio.getTime()) / 1000 / 3600.0;
    if (totalHoras <= 0) {
        const key = getDateKeyLocal(inicio) as string;
        return {
            map: { [key]: saldoNuevo },
            min: (normalizeToLocalMidnight(inicio) as Date).getTime(),
            max: (normalizeToLocalMidnight(fin) as Date).getTime(),
            capByDay: stdDia > 0 ? { [key]: stdDia } : null,
        };
    }

    const startDay = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate());
    const endDay = new Date(fin.getFullYear(), fin.getMonth(), fin.getDate());
    const dias = Math.round((endDay.getTime() - startDay.getTime()) / 86400000) + 1;

    const horasPorDia: MapaDias = {};
    for (let i = 0; i < dias; i++) {
        const dia = new Date(startDay.getTime() + i * 86400000);
        const esPrimerDia = i === 0;
        const esUltimoDia = dia.toDateString() === endDay.toDateString();
        let fraccion: number;

        if (esPrimerDia && esUltimoDia) {
            fraccion = ((fin.getTime() - inicio.getTime()) / 1000) / 86400;
        } else if (esPrimerDia) {
            const segundosDesdeMedianoche = inicio.getHours() * 3600 + inicio.getMinutes() * 60 + inicio.getSeconds();
            fraccion = (86400 - segundosDesdeMedianoche) / 86400;
        } else if (esUltimoDia) {
            const realInicio = new Date(dia.getFullYear(), dia.getMonth(), dia.getDate());
            fraccion = ((fin.getTime() - realInicio.getTime()) / 1000) / 86400;
        } else {
            fraccion = 1;
        }

        if (fraccion < 0) fraccion = Math.abs(fraccion);
        const key = getDateKeyLocal(dia) as string;
        horasPorDia[key] = (horasPorDia[key] || 0) + fraccion * 24.0;
    }

    const stdHrEfectivo = saldoNuevo / totalHoras;
    const map: MapaDias = {};
    Object.entries(horasPorDia).forEach(([key, horasDia]) => {
        map[key] = (map[key] || 0) + redondear3(stdHrEfectivo * horasDia);
    });

    return {
        map,
        min: (normalizeToLocalMidnight(inicio) as Date).getTime(),
        max: (normalizeToLocalMidnight(fin) as Date).getTime(),
        capByDay: capByDayFromHorasPorDia(horasPorDia, stdDia),
    };
}

function comparePedidoDesc(a: CandidatoLider, b: CandidatoLider): number {
    if (a.pedidoActual !== b.pedidoActual) return b.pedidoActual - a.pedidoActual;
    return a.id - b.id;
}

/**
 * Orden líder del grupo. Si ya hay una persistida (OrdCompartidaLider) se respeta; si no:
 * con fechas de inicio distintas, la que empieza antes; con la misma fecha, la creada antes;
 * empate: mayor pedido, luego menor Id.
 */
export function elegirLider(items: CandidatoLider[]): CandidatoLider | null {
    if (!items.length) return null;

    // El balanceo solo ajusta pedido/fechas; no debe cambiar la orden líder ya definida.
    const persistedLeader = items.find((item) => item.isLeader);
    if (persistedLeader) return persistedLeader;

    const todasMismaFechaInicio = new Set(items.map((item) => item.fechaInicioKey ?? '__NULL__')).size <= 1;
    const campo: 'fechaInicioMs' | 'fechaCreacionMs' = todasMismaFechaInicio ? 'fechaCreacionMs' : 'fechaInicioMs';

    const ordenados = [...items].sort((a, b) => {
        const va = a[campo];
        const vb = b[campo];
        if (va !== null && vb !== null && va !== vb) return va - vb;
        if (va !== null && vb === null) return -1;
        if (va === null && vb !== null) return 1;
        return comparePedidoDesc(a, b);
    });

    return ordenados[0] ?? null;
}

/** true si el valor de data-ord-compartida identifica un grupo. */
export function tieneOrdCompartida(ord: string | null | undefined): ord is string {
    return !!ord && ord.trim() !== '' && ord !== '0' && ord.toLowerCase() !== 'null';
}

/**
 * Nuevo pedido por fila al cambiar el total: proporcional al pedido actual, sin bajar de la
 * producción, y el último absorbe lo que quede por el redondeo.
 */
export function repartirTotal(filas: readonly { valor: number; produccion: number }[], nuevoTotal: number): number[] {
    const totalActual = filas.reduce((s, f) => s + f.valor, 0);
    const diferencia = nuevoTotal - totalActual;

    if (filas.length === 1) {
        const f = filas[0] as { valor: number; produccion: number };
        return [Math.round(Math.max(f.produccion, f.valor + diferencia))];
    }

    let diferenciaRestante = diferencia;
    return filas.map((f, index) => {
        if (index === filas.length - 1) return Math.round(Math.max(f.produccion, f.valor + diferenciaRestante));
        const proporcion = totalActual > 0 ? f.valor / totalActual : 1 / filas.length;
        const nuevoValor = Math.round(Math.max(f.produccion, f.valor + diferencia * proporcion));
        diferenciaRestante -= nuevoValor - f.valor; // usar el cambio real aplicado, no el delta teórico
        return nuevoValor;
    });
}

/** Clase y título de una celda del Gantt según piezas vs. capacidad prorrateada del día. */
export function celdaGantt(qty: number, cap: number | null, fila: number): { cls: string; title: string } {
    if (qty <= 0) return { cls: '', title: '' };
    if (cap != null && cap > 0) {
        const enCap = qty >= cap * 0.92;
        const capR = Math.round(cap);
        const espacio = Math.max(0, Math.round(cap - qty));
        return {
            cls: enCap ? 'gantt-bar-at-cap' : 'gantt-bar-space',
            title: enCap
                ? `Piezas ${qty.toLocaleString('es-MX')} · ~${capR.toLocaleString('es-MX')} pzas/día prorrateado (cerca del std)`
                : `Piezas ${qty.toLocaleString('es-MX')} · Cap. prorrateada ~${capR.toLocaleString('es-MX')} · Espacio ~${espacio.toLocaleString('es-MX')} pzas`,
        };
    }
    return { cls: fila % 2 === 0 ? 'gantt-bar' : 'gantt-bar-alt', title: `${qty.toLocaleString('es-MX')} pzas (sin Std/Día para comparar)` };
}
