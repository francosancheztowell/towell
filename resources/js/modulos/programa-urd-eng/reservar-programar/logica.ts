/**
 * Reservar y programar (Programa Urd-Eng): lógica pura, sin DOM. La usan los módulos de
 * tablas, selección y reservas de esta carpeta; tests en tests/Js/programa-urd-eng-reservar-programar.test.mjs.
 */
import type { TelarSeleccionado } from '../comun/contrato-flujo.ts';
import type { Aviso, FilterMap, RawRow, SelectedInventario, SelectedTelar, SortRule } from './types.ts';

/* ---------- Lecturas seguras sobre filas crudas ---------- */

export const s = (v: unknown, d = ''): string => (v === null || v === undefined ? d : String(v));

const upper = (v: unknown): string => s(v).toUpperCase().trim();

/** Copia profunda de filas JSON (lo que llega del servidor). */
export const clonar = (rows: RawRow[]): RawRow[] => JSON.parse(JSON.stringify(rows)) as RawRow[];

/** Normaliza `data` (objeto único u arreglo) a lista de filas. */
export const asRows = (d: unknown): RawRow[] => {
    if (Array.isArray(d)) return d as RawRow[];
    if (typeof d === 'object' && d !== null) return [d as RawRow];
    return [];
};

/* ---------- Formato ---------- */

const BADGE_SALON: Record<string, string> = {
    Jacquard: 'bg-pink-100 text-pink-700',
    JACQUARD: 'bg-pink-100 text-pink-700',
    Itema: 'bg-purple-100 text-purple-700',
    ITEMA: 'bg-purple-100 text-purple-700',
    Smith: 'bg-cyan-100 text-cyan-700',
    SMIT: 'bg-cyan-100 text-cyan-700',
    'Karl Mayer': 'bg-amber-100 text-amber-700',
    'KARL MAYER': 'bg-amber-100 text-amber-700',
    Sulzer: 'bg-lime-100 text-lime-700',
    SULZER: 'bg-lime-100 text-lime-700',
};

const FECHA_CORTA: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short', year: 'numeric' };

export const fmt = {
    salonBadge(salon: unknown): string {
        return BADGE_SALON[s(salon, 'Jacquard').trim()] ?? 'bg-indigo-100 text-indigo-700';
    },
    tipoBadge(t: unknown): string {
        const u = upper(s(t, '-'));
        if (u === 'RIZO') return 'bg-rose-100 text-rose-700';
        if (u === 'PIE') return 'bg-teal-100 text-teal-700';
        return 'bg-gray-100 text-gray-700';
    },
    num(n: unknown, d = 2): string {
        if (n === null || n === undefined || n === '') return '';
        const val = Number(n);
        return Number.isNaN(val) ? '' : val.toFixed(d);
    },
    date(iso: unknown): string {
        if (!iso) return '';
        const str = String(iso).trim();
        const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            const y = parseInt(m[1] ?? '', 10);
            const mon = parseInt(m[2] ?? '', 10) - 1;
            const day = parseInt(m[3] ?? '', 10);
            const d = new Date(y, mon, day);
            if (!Number.isNaN(d.getTime()) && d.getFullYear() === y && d.getMonth() === mon && d.getDate() === day) {
                return d.toLocaleDateString('es-ES', FECHA_CORTA);
            }
        }
        const d = new Date(str);
        return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('es-ES', FECHA_CORTA);
    },
};

/* ---------- Comparaciones ---------- */

export const eq = {
    str: (a: unknown, b: unknown): boolean => upper(a) === upper(b),
    num: (a: unknown, b: unknown): boolean => {
        const x = Number(a);
        const y = Number(b);
        if (Number.isNaN(x) || Number.isNaN(y)) return String(a) === String(b);
        return Math.abs(x - y) < 1e-6;
    },
};

/** InventBatchId = prefijo de InventSerialId (ej. 00061-744 → 00061). Para comparar lote. */
export const deriveInventBatchFromSerial = (serialId: unknown, batchId: unknown): string => {
    const serial = s(serialId).trim();
    if (!serial || serial.indexOf('-') === -1) return s(batchId);
    const prefijo = (serial.split('-')[0] ?? '').trim();
    return prefijo || s(batchId);
};

/** Coincidencia de lote: inventBatchId o prefijo de InventSerialId debe coincidir con telNoOrden. */
export const matchLote = (telNoOrden: string, invBatchId: string, invSerialId: string): boolean => {
    if (!telNoOrden) return true;
    const batch = invBatchId.trim();
    const derived = deriveInventBatchFromSerial(invSerialId, batch);
    return batch === telNoOrden || derived === telNoOrden;
};

/** Coincidencia de cuenta: telar "3156" ⇒ InventSizeId que empiece por "3156". */
export const matchCuenta = (cuentaTelar: unknown, inventSizeId: unknown): boolean => {
    const a = s(cuentaTelar).replace(/\s+/g, '').toUpperCase();
    const b = s(inventSizeId).replace(/\s+/g, '').toUpperCase();
    if (!a) return false;
    return b.startsWith(a);
};

interface TelarGroup {
    tipo: unknown;
    calibre: unknown;
    salon: unknown;
}

/** Selección múltiple: mismo tipo, calibre y salón (la cuenta puede variar). */
export const sameGroup = (a: TelarGroup, b: TelarGroup): boolean =>
    eq.str(a.tipo, b.tipo) && eq.num(a.calibre, b.calibre) && eq.str(a.salon, b.salon);

export const normalizeTipo = (t: unknown): string => {
    const u = upper(t);
    if (u === 'RIZO') return 'Rizo';
    if (u === 'PIE') return 'Pie';
    return u || '-';
};

/* ---------- Estado de una fila de telar ---------- */

interface Reservable {
    no_orden?: unknown;
    reservado?: unknown;
    is_reservado?: unknown;
    programado?: unknown;
    is_programado?: unknown;
}

export const hasNoOrden = (telar: Reservable | null | undefined): boolean => s(telar?.no_orden).trim() !== '';

export const rowNoJulio = (r: RawRow): string => s(r.no_julio).trim();
export const rowNoOrden = (r: RawRow): string => s(r.no_orden).trim();

/** Una barra de Karl Mayer se alimenta de hasta cuatro julios; rizo y pie, de uno. */
export const rowJulios = (r: RawRow): string[] =>
    Array.isArray(r.julios) ? (r.julios as unknown[]).map((j) => s(j).trim()).filter(Boolean) : [rowNoJulio(r)].filter(Boolean);

export const rowOrdenes = (r: RawRow): string[] => {
    const julios = rowJulios(r);
    const crudas = Array.isArray(r.ordenes) ? (r.ordenes as unknown[]).map((o) => s(o).trim()) : [rowNoOrden(r)];
    return julios.map((_, i) => crudas[i] ?? '');
};

export const rowMaxJulios = (r: RawRow): number => Number(r.max_julios) || 1;

/** En Karl Mayer el tipo es 1..4: sin el prefijo "Barra" se lee como un número suelto. */
export const etiquetaTipo = (tel: Pick<SelectedTelar, 'tipo' | 'max_julios'>): string => {
    const tipo = s(tel.tipo).trim();
    if (!tipo) return 'N/A';
    return (tel.max_julios || 1) > 1 ? `Barra ${tipo}` : tipo;
};

export const etiquetasOrdenes = (r: RawRow): string[] => {
    const ordenes = rowMaxJulios(r) > 1 ? rowOrdenes(r) : [rowNoOrden(r)];
    return ordenes.map((o) => s(o).trim()).filter(Boolean);
};

/** Estado canónico de una fila cruda: misma regla para badge, sort y filtros.
 * El backend manda `Reservado`/`Programado` capitalizados y no siempre vienen los flags en
 * minúscula; julio + orden también implican reservado. */
export const rowReservado = (r: RawRow): boolean =>
    r.reservado === true || r.is_reservado === true || r.Reservado === true || (rowNoJulio(r) !== '' && rowNoOrden(r) !== '');

export const rowProgramado = (r: RawRow): boolean => r.programado === true || r.is_programado === true || r.Programado === true;

export type Estado = 'reservado' | 'programado' | 'libre';

export const estadoDe = (r: RawRow): Estado => (rowReservado(r) ? 'reservado' : rowProgramado(r) ? 'programado' : 'libre');

export const isReservado = (telar: Reservable | null | undefined): boolean => {
    if (typeof telar?.reservado === 'boolean') return telar.reservado;
    if (typeof telar?.is_reservado === 'boolean') return telar.is_reservado;
    return false;
};

export const isProgramado = (telar: Reservable | null | undefined): boolean => {
    if (typeof telar?.programado === 'boolean') return telar.programado;
    if (typeof telar?.is_programado === 'boolean') return telar.is_programado;
    return false;
};

/** Misma fila de telar: por id cuando lo hay (una barra se repite con otra fecha), si no telar + tipo. */
export const esMismoTelar = (r: RawRow, ref: { id: string | null; no_telar: string | null; tipo: string }): boolean =>
    ref.id ? s(r.id) === ref.id : s(r.no_telar) === s(ref.no_telar) && upper(r.tipo) === upper(ref.tipo);

/** Telar seleccionado a partir del dataset de su fila (<tr data-*>). */
export function telarDesdeDataset(ds: Record<string, string | undefined>, tipoAtado = 'Normal'): SelectedTelar {
    const reservado = ds.isReservado === 'true';
    const programado = ds.isProgramado === 'true';
    return {
        id: s(ds.id) || null,
        no_telar: s(ds.telar) || null,
        tipo: normalizeTipo(ds.tipo),
        cuenta: s(ds.cuenta),
        salon: s(ds.salon),
        calibre: s(ds.calibre),
        hilo: s(ds.hilo),
        no_julio: s(ds.noJulio),
        julios: s(ds.julios).split(',').filter(Boolean),
        ordenes: s(ds.ordenes).split(','),
        max_julios: Number(ds.maxJulios) || 1,
        no_orden: s(ds.noOrden),
        fecha: s(ds.fecha),
        turno: s(ds.turno),
        tipo_atado: tipoAtado,
        reservado,
        programado,
        is_reservado: reservado,
        is_programado: programado,
    };
}

/** Fecha del dataset de la fila: solo AAAA-MM-DD si viene con hora. */
export const fechaDataset = (fecha: unknown): string => {
    const f = s(fecha).trim();
    return f.match(/^(\d{4}-\d{2}-\d{2})/)?.[1] ?? f;
};

/* ---------- Orden ---------- */

const COLS_FECHA = ['fecha', 'ProdDate'];
const COLS_NUM = ['no_telar', 'no_julio', 'no_orden', 'calibre', 'metros', 'Metros', 'InventQty'];
const PESO_ESTADO: Record<Estado, number> = { reservado: 2, programado: 1, libre: 0 };

export function ordenarFilas(data: RawRow[], sorts: SortRule[] = []): RawRow[] {
    if (!Array.isArray(sorts) || !sorts.length) return [...data];

    return [...data].sort((a, b) => {
        for (const rule of sorts) {
            const col = rule.column;
            if (col === 'estado') {
                // 'estado' no es un campo de la fila: se deriva. Antes caía en "vacío" y no ordenaba.
                const cmpEstado = PESO_ESTADO[estadoDe(a)] - PESO_ESTADO[estadoDe(b)];
                if (cmpEstado !== 0) return rule.direction === 'asc' ? cmpEstado : -cmpEstado;
                continue;
            }
            const av: unknown = a[col];
            const bv: unknown = b[col];
            const emptyA = av === null || av === undefined || av === '';
            const emptyB = bv === null || bv === undefined || bv === '';

            if (emptyA && emptyB) continue;
            if (emptyA) return 1;
            if (emptyB) return -1;

            let cmp: number;
            if (COLS_FECHA.includes(col)) {
                cmp = new Date(s(av)).getTime() - new Date(s(bv)).getTime();
            } else if (COLS_NUM.includes(col)) {
                cmp = (parseFloat(s(av)) || 0) - (parseFloat(s(bv)) || 0);
            } else if (col === 'reservado' || col === 'programado') {
                cmp = (av ? 1 : 0) - (bv ? 1 : 0);
            } else {
                const as = s(av).toLowerCase();
                const bs = s(bv).toLowerCase();
                cmp = as < bs ? -1 : as > bs ? 1 : 0;
            }

            if (cmp !== 0) return rule.direction === 'asc' ? cmp : -cmp;
        }
        return 0;
    });
}

/**
 * Siguiente orden al tocar un encabezado. Clic simple: asc → desc → sin orden (sobre una sola
 * columna). Con Shift/Ctrl/Cmd (`additive`) se acumula: asc → desc → fuera de la lista.
 */
export function siguienteOrden(actual: SortRule[], col: string, additive = false): SortRule[] {
    const current = actual.map((r) => ({ ...r }));
    const idx = current.findIndex((r) => r.column === col);

    if (!additive) {
        if (idx === 0) return current[0]?.direction === 'asc' ? [{ column: col, direction: 'desc' }] : [];
        return [{ column: col, direction: 'asc' }];
    }
    const regla = current[idx];
    if (!regla) return [...current, { column: col, direction: 'asc' }];
    if (regla.direction === 'asc') {
        regla.direction = 'desc';
        return current;
    }
    current.splice(idx, 1);
    return current;
}

/* ---------- Filtros ---------- */

export interface FiltroColumna {
    column: string;
    value: string;
}

/** Filtros con valor de un mapa columna → valor. */
export const filtrosActivos = (map: FilterMap): FiltroColumna[] =>
    Object.entries(map)
        .map(([column, value]) => ({ column, value: s(value) }))
        .filter((f) => f.value.trim() !== '');

const VERDADEROS = ['1', 'true', 'si', 'sí', 'yes', 'activo', 'reservado', 'programado'];
const SIN_TELAR = ['null', 'vacío', 'vacio', 'disponible', ''];

/** Filtro local por columna (texto parcial, fechas por día, números con tolerancia). */
export function filtrarLocal(data: RawRow[], list: FiltroColumna[]): RawRow[] {
    if (!list.length || !data.length) return data;

    return data.filter((item) =>
        list.every((f) => {
            const col = f.column;
            const val = s(f.value).toLowerCase().trim();
            if (!col || !val) return true;

            const itemVal: unknown = item[col];

            if (col === 'estado') return estadoDe(item).includes(val);
            if (col === 'reservado' || col === 'programado') return Boolean(itemVal) === VERDADEROS.includes(val);
            if (col === 'NoTelarId') {
                if (SIN_TELAR.includes(val)) return !itemVal;
                return s(itemVal).toLowerCase().includes(val);
            }
            if (itemVal == null || itemVal === '') return false;
            if (col === 'InventSizeId') return s(itemVal).toLowerCase().startsWith(val);

            if (COLS_FECHA.includes(col)) {
                const dItem = new Date(s(itemVal));
                const dFil = new Date(val);
                if (!Number.isNaN(dItem.getTime()) && !Number.isNaN(dFil.getTime())) {
                    return dItem.toDateString() === dFil.toDateString();
                }
            }

            if (['calibre', 'metros', 'InventQty', 'Metros'].includes(col)) {
                const a = parseFloat(s(itemVal));
                const b = parseFloat(val);
                if (!Number.isNaN(a) && !Number.isNaN(b)) {
                    return Math.abs(a - b) < 0.001 || s(itemVal).toLowerCase().includes(val);
                }
            }

            return s(itemVal).toLowerCase().includes(val);
        }),
    );
}

/** Piezas del inventario que le sirven al telar seleccionado. */
export function filtrarInventarioPorTelar(data: RawRow[], tel: SelectedTelar): RawRow[] {
    const telCuenta = s(tel.cuenta).trim();
    const telTipo = upper(tel.tipo);
    const telNo = tel.no_telar ?? '';
    const telJulio = tel.no_julio;
    const telNoOrden = s(tel.no_orden).trim();
    const telJulios = tel.julios ?? [];
    const esBarra = (tel.max_julios || 1) > 1;
    const quedanHuecos = telJulios.length < (tel.max_julios || 1);

    return data.filter((r) => {
        const noTelarAsignado = s(r.NoTelarId);
        const hasTelar = noTelarAsignado !== '';
        const invTipo = upper(r.Tipo);
        const inventBatchId = s(r.InventBatchId).trim();
        const inventSerialId = s(r.InventSerialId).trim();

        // La pieza reservada para este telar se muestra siempre.
        if (hasTelar && noTelarAsignado === telNo) return true;

        // Una barra con hueco admite julios de otra orden. Rizo y Pie, y una barra ya llena,
        // siguen amarrados al lote que ya tienen.
        if (!(esBarra && quedanHuecos) && telNoOrden && !matchLote(telNoOrden, inventBatchId, inventSerialId)) return false;

        // Misma cuenta (InventSizeId inicia con la cuenta del telar).
        if (telCuenta && !matchCuenta(telCuenta, r.InventSizeId)) return false;

        // Si el telar ya tiene No. Julio, esa pieza concreta. En una barra llena, las cuatro;
        // si aún cabe otra, no se oculta el resto.
        if (esBarra && !quedanHuecos) return telJulios.includes(inventSerialId);
        if (!esBarra && telJulio) return inventSerialId === telJulio;

        // Piezas asignadas a otro telar, fuera.
        if (hasTelar && noTelarAsignado !== telNo) return false;

        // Mismo tipo (Rizo/Pie o la barra 1..4).
        if (telTipo && invTipo && invTipo !== telTipo) return false;

        return true;
    });
}

/* ---------- Chips de filtro rápido ---------- */

export const salonesDe = (rows: RawRow[]): string[] => {
    const seen = new Set<string>();
    for (const r of rows) {
        const v = s(r.salon).trim();
        if (v) seen.add(v);
    }
    return [...seen].sort((a, b) => a.localeCompare(b, 'es'));
};

export const salonCorto = (salon: string): string => {
    const key = salon.trim().toUpperCase();
    if (key === 'ITEMA' || key === 'ITE') return 'SMI';
    const parts = salon.trim().split(/\s+/);
    if (parts.length > 1) return parts.map((p) => (p[0] ?? '').toUpperCase()).join('').slice(0, 3);
    return salon.trim().slice(0, 3).toUpperCase();
};

export const ESTADOS_CHIP: ReadonlyArray<readonly [string, string]> = [
    ['', 'Todos'],
    ['libre', 'Libre'],
    ['reservado', 'Reservado'],
    ['programado', 'Programado'],
];

/** Chip de salón: segundo toque sobre el mismo lo quita. */
export function alternarSalon(map: FilterMap, v: string): void {
    if (s(map.salon) === v) delete map.salon;
    else map.salon = v;
}

/** Chip de estado: "Todos" o el mismo estado lo quitan. */
export function alternarEstado(map: FilterMap, v: string): void {
    if (v === '' || s(map.estado) === v) delete map.estado;
    else map.estado = v;
}

/* ---------- Selección múltiple de telares (checkbox) ---------- */

/** Por qué no entra un telar a la selección múltiple, o null si entra. */
export function motivoRechazoMultiple(item: SelectedTelar, seleccion: SelectedTelar[]): Aviso | null {
    if (item.is_reservado || item.is_programado) {
        return { tipo: 'info', titulo: 'Telar no disponible', texto: 'No se puede usar en selección múltiple' };
    }
    if (hasNoOrden(item)) {
        return { tipo: 'info', titulo: 'Telar con orden', texto: 'No se puede usar en selección múltiple' };
    }
    const ref = seleccion[0];
    if (!ref) return null;
    if (isReservado(ref) || isProgramado(ref)) {
        return {
            tipo: 'info',
            titulo: 'Telar no disponible en selección',
            texto: 'No se pueden agregar más telares a una selección con telares reservados o programados',
        };
    }
    if (hasNoOrden(ref)) {
        return {
            tipo: 'info',
            titulo: 'Telar con orden en selección',
            texto: 'No se pueden agregar más telares a una selección que contiene telares con orden',
        };
    }
    if (!sameGroup(item, ref)) {
        return {
            tipo: 'warning',
            titulo: 'Selección incompatible',
            texto: 'Solo puedes seleccionar telares con el mismo Tipo, Calibre y Salón. La cuenta puede variar.',
        };
    }
    return null;
}

const mismoItem = (a: SelectedTelar, b: SelectedTelar): boolean =>
    b.id ? String(a.id) === String(b.id) : a.no_telar === b.no_telar && eq.str(a.tipo, b.tipo);

export const agregarAMultiple = (seleccion: SelectedTelar[], item: SelectedTelar): SelectedTelar[] =>
    seleccion.some((t) => mismoItem(t, item)) ? seleccion : [...seleccion, item];

export const quitarDeMultiple = (seleccion: SelectedTelar[], item: SelectedTelar): SelectedTelar[] =>
    seleccion.filter((t) => !mismoItem(t, item));

/** ¿La fila está en la selección múltiple? (por id, o telar + tipo). */
export const enMultiple = (seleccion: SelectedTelar[], r: RawRow): boolean => {
    const rowId = s(r.id);
    const telarNo = s(r.no_telar);
    const tipo = upper(r.tipo);
    return seleccion.some((t) => (rowId && t.id ? String(t.id) === rowId : t.no_telar === telarNo && upper(t.tipo) === tipo));
};

/* ---------- Selección de piezas de inventario ---------- */

export type ResultadoPieza = { ok: true; seleccion: SelectedInventario[] } | { ok: false; aviso: Aviso };

/**
 * Elegir una pieza para el telar: rizo/pie reemplazan la selección; una barra de Karl Mayer
 * acumula hasta llenar sus huecos (tocar de nuevo una pieza la quita).
 */
export function elegirPieza(actual: SelectedInventario[], item: SelectedInventario, tel: SelectedTelar | null): ResultadoPieza {
    if (tel?.tipo) {
        const telTipo = upper(tel.tipo);
        const invTipo = upper(item.tipo);
        if (telTipo && invTipo && telTipo !== invTipo) {
            return { ok: false, aviso: { tipo: 'warning', titulo: 'Tipo distinto', texto: 'El tipo de la pieza no coincide con el telar' } };
        }
    }

    const max = tel?.max_julios ?? 1;
    if (max <= 1) return { ok: true, seleccion: [item] };

    const huecos = max - (tel?.julios.length ?? 0);
    const ix = actual.findIndex((i) => i.inventSerialId === item.inventSerialId);
    if (ix > -1) return { ok: true, seleccion: actual.filter((_, i) => i !== ix) };
    if (huecos <= 0) {
        return { ok: false, aviso: { tipo: 'info', titulo: 'Barra llena', texto: `La barra ${tel?.tipo} ya tiene sus ${max} julios` } };
    }
    if (actual.length >= huecos) {
        return { ok: false, aviso: { tipo: 'info', titulo: 'Límite de julios', texto: `Solo quedan ${huecos} julio(s) libres en esta barra` } };
    }
    return { ok: true, seleccion: [...actual, item] };
}

export interface FilaLote {
    disabled: boolean;
    inventBatchId: string;
    tipo: string;
}

/** Lote para "Seleccionar todos los julios de este lote": la primera pieza elegida o la orden del telar. */
export const loteDeSeleccion = (tel: SelectedTelar, sel: SelectedInventario[]): string =>
    s(sel[0]?.inventBatchId || tel.no_orden).trim();

/**
 * Julios libres del lote que caben en la barra. El tipo se valida también: con "Quitar Filtro"
 * la tabla muestra piezas de otras barras. Un julio KM sin tipo entra en cualquier barra.
 */
export function candidatasLote<T extends FilaLote>(filas: T[], lote: string, telTipo: string, huecos: number): T[] {
    const tipo = upper(telTipo);
    return filas
        .filter((r) => !r.disabled && r.inventBatchId.trim() === lote && (!tipo || r.tipo.trim() === '' || upper(r.tipo) === tipo))
        .slice(0, Math.max(0, huecos));
}

/* ---------- Botones de la barra ---------- */

/** true = deshabilitado. */
export interface EstadoBotones {
    programar: boolean;
    reservar: boolean;
    liberar: boolean;
}

export function estadoBotones(tel: SelectedTelar | null, inv: SelectedInventario[], multiples: SelectedTelar[]): EstadoBotones {
    // Reservar: telar + piezas del mismo tipo (Rizo/Pie o la barra 1..4). Pieza KM sin tipo: cualquier barra.
    const tiposMatch =
        !!tel &&
        inv.length > 0 &&
        inv.every((i) => {
            const invTipo = s(i.tipo || i.data?.Tipo).trim();
            return !invTipo || eq.str(tel.tipo, invTipo);
        });

    // Telar reservado: solo liberar (y, si es una barra con hueco, reservar otro julio).
    if (tel && isReservado(tel)) {
        const quedanHuecos = tel.julios.length < (tel.max_julios || 1);
        return { programar: true, reservar: !(quedanHuecos && tiposMatch), liberar: false };
    }

    const reservar = !tiposMatch;
    const bloqueaProgramar = (t: SelectedTelar): boolean => isReservado(t) || isProgramado(t) || hasNoOrden(t);

    // Programar: la selección múltiple tiene prioridad.
    if (multiples.length > 0) return { programar: multiples.some(bloqueaProgramar), reservar, liberar: true };
    if (tel?.no_telar) return { programar: bloqueaProgramar(tel), reservar, liberar: true };
    return { programar: true, reservar, liberar: true };
}

/* ---------- Programar (navegación a Programación de requerimientos) ---------- */

export type ResultadoProgramar = { ok: true; telares: TelarSeleccionado[] } | { ok: false; aviso: Aviso };

const AVISO_CON_ORDEN: Aviso = { tipo: 'info', titulo: 'Telar con orden', texto: 'No se puede programar un telar que ya tiene No. Orden' };

/**
 * Telares que viajan a Programación de requerimientos. Selección múltiple tal cual; individual,
 * completada con la fila original por si el dataset venía vacío.
 */
export function telaresParaProgramar(tel: SelectedTelar | null, multiples: SelectedTelar[], base: RawRow[]): ResultadoProgramar {
    if (multiples.length > 0) {
        if (multiples.some(isProgramado)) {
            return { ok: false, aviso: { tipo: 'info', titulo: 'Telar programado', texto: 'No se puede programar un telar que ya está programado' } };
        }
        if (multiples.some(hasNoOrden)) return { ok: false, aviso: AVISO_CON_ORDEN };
        return { ok: true, telares: multiples.map((t) => ({ ...t })) };
    }

    if (!tel?.no_telar) return { ok: false, aviso: { tipo: 'warning', titulo: 'Selecciona un telar' } };
    if (isReservado(tel)) return { ok: false, aviso: { tipo: 'info', titulo: 'Telar reservado', texto: 'No se puede programar un telar reservado' } };
    if (isProgramado(tel)) {
        return { ok: false, aviso: { tipo: 'info', titulo: 'Telar programado', texto: 'No se puede volver a programar un telar ya programado' } };
    }
    if (hasNoOrden(tel)) return { ok: false, aviso: AVISO_CON_ORDEN };

    const completo = base.find((t) => esMismoTelar(t, tel));
    const de = (campo: string, d = ''): string => (completo ? s(completo[campo], d) : d);

    return {
        ok: true,
        telares: [
            {
                id: tel.id ?? (completo ? s(completo.id) || null : null),
                no_telar: tel.no_telar,
                tipo: tel.tipo,
                cuenta: tel.cuenta || de('cuenta'),
                salon: tel.salon || de('salon'),
                calibre: tel.calibre || de('calibre'),
                hilo: tel.hilo || de('hilo'),
                fecha: tel.fecha || de('fecha'),
                turno: tel.turno || de('turno'),
                tipo_atado: tel.tipo_atado || de('tipo_atado', 'Normal'),
            },
        ],
    };
}

/* ---------- Reservar ---------- */

/** Piezas que se pueden mandar (con su fila de inventario). */
export const piezasReservables = (sel: SelectedInventario[]): SelectedInventario[] => sel.filter((i) => i.data);

/** Validación previa a reservar; null si se puede. */
export function validarReserva(tel: SelectedTelar | null, piezas: SelectedInventario[]): Aviso | null {
    if (!tel?.no_telar) return { tipo: 'warning', titulo: 'Aviso', texto: 'Selecciona un telar' };
    if (!tel.id) {
        return {
            tipo: 'error',
            titulo: 'Error',
            texto: 'No se pudo identificar el registro del telar. Por favor, selecciona el telar nuevamente.',
        };
    }
    const huecos = (tel.max_julios || 1) - tel.julios.length;
    if (isReservado(tel) && huecos <= 0) return { tipo: 'warning', titulo: 'Aviso', texto: 'Este telar ya está reservado' };
    if (!piezas.length) return { tipo: 'warning', titulo: 'Aviso', texto: 'Selecciona una fila de inventario' };
    if (tel.julios.some((j) => piezas.some((i) => i.inventSerialId === j))) {
        return { tipo: 'warning', titulo: 'Aviso', texto: 'Ese julio ya está en esta barra' };
    }
    if (piezas.length > huecos) return { tipo: 'warning', titulo: 'Aviso', texto: `Solo quedan ${huecos} julio(s) libres en esta barra` };

    const telTipo = s(tel.tipo).trim();
    const distinta = piezas.some((i) => {
        const invTipo = s(i.data?.Tipo ?? i.tipo).trim();
        return invTipo && telTipo && !eq.str(invTipo, telTipo);
    });
    if (distinta) return { tipo: 'warning', titulo: 'Advertencia', texto: 'El tipo de la pieza no coincide con el telar.' };
    if (piezas.some((i) => i.data?.NoTelarId)) return { tipo: 'warning', titulo: 'Aviso', texto: 'Esa pieza ya tiene telar asignado' };
    return null;
}

/** Lote de la pieza (lo que queda como No. Orden del telar). */
export const loteDePieza = (pieza: SelectedInventario): string => pieza.inventBatchId || s(pieza.data?.InventBatchId);

/** Cuerpo de POST reservar-inventario para una pieza (mismo contrato que antes de 19-05). */
export function payloadReserva(tel: SelectedTelar, pieza: SelectedInventario): Record<string, unknown> {
    const it = pieza.data ?? {};
    return {
        NoTelarId: tel.no_telar,
        SalonTejidoId: tel.salon || null,
        ItemId: it.ItemId,
        ConfigId: it.ConfigId ?? null,
        InventSizeId: it.InventSizeId ?? null,
        InventColorId: it.InventColorId ?? null,
        InventLocationId: it.InventLocationId ?? null,
        InventBatchId: it.InventBatchId ?? null,
        WMSLocationId: it.WMSLocationId ?? null,
        InventSerialId: it.InventSerialId ?? null,
        Tipo: normalizeTipo(tel.tipo),
        Metros: it.Metros ?? null,
        InventQty: it.InventQty ?? null,
        ProdDate: it.ProdDate ?? null,
        fecha: tel.fecha || null,
        turno: tel.turno || null,
        tej_inventario_telares_id: parseInt(tel.id ?? '', 10),
        telar: {
            metros: pieza.metros || 0,
            no_julio: pieza.numJulio || '',
            no_orden: loteDePieza(pieza),
            localidad: pieza.wmsLocationId || s(it.WMSLocationId),
        },
    };
}

/** Edición optimista de la fila del telar al reservar una pieza. */
export function aplicarReservaLocal(row: RawRow, pieza: SelectedInventario, lote: string, maxTelar: number): void {
    const serial = pieza.numJulio || '';
    const esBarra = (Number(row.max_julios) || maxTelar || 1) > 1;
    row.metros = pieza.metros || 0;
    if (!esBarra) {
        row.no_julio = serial;
        row.no_orden = lote;
        row.julios = serial ? [serial] : [];
        row.ordenes = lote ? [lote] : [];
        return;
    }
    const julios = rowJulios(row);
    const ordenes = rowOrdenes(row);
    if (serial && !julios.includes(serial)) {
        julios.push(serial);
        ordenes.push(lote);
    }
    row.julios = julios;
    row.ordenes = ordenes;
    row.no_julio = julios[0] ?? '';
    row.no_orden = ordenes[0] ?? '';
    row.reservado = true;
}

/** Deja la fila del telar liberada; `d` es lo que devolvió el servidor (undefined = optimista). */
export function aplicarLiberado(row: RawRow, d: RawRow | undefined): void {
    row.metros = d?.metros ?? 0;
    row.no_julio = s(d?.no_julio);
    row.no_julio2 = '';
    row.no_julio3 = '';
    row.no_julio4 = '';
    row.julios = [];
    row.no_orden = s(d?.no_orden);
    row.no_orden2 = '';
    row.no_orden3 = '';
    row.no_orden4 = '';
    row.ordenes = [];
    // La fibra no se pierde al liberar: solo se refresca con lo que devuelve el servidor.
    if (d) row.hilo = s(d.hilo);
    row.reservado = false;
    row.is_reservado = false;
    row.programado = false;
    row.is_programado = false;
}

/* ---------- Edición de cuenta / calibre ---------- */

export type CampoEditable = 'cuenta' | 'calibre';

export const esCampoEditable = (v: unknown): v is CampoEditable => v === 'cuenta' || v === 'calibre';

/** Valor que se guarda: la cuenta tal cual; el calibre como número (vacío → null). */
export const valorEditado = (campo: CampoEditable, crudo: string): string | number | null =>
    campo === 'cuenta' ? crudo : crudo !== '' ? parseFloat(crudo) : null;

/** Cuerpo de POST actualizar-telar para cuenta/calibre. */
export function payloadEdicion(campo: CampoEditable, crudo: string, ref: { id: number | null; no_telar: string; tipo: string }): Record<string, unknown> {
    return { no_telar: ref.no_telar, tipo: normalizeTipo(ref.tipo), id: ref.id, [campo]: valorEditado(campo, crudo) };
}
