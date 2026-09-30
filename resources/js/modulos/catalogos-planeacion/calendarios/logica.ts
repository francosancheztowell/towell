/**
 * Catálogo de Calendarios: reglas puras (sin DOM). Antes repartidas en el <script> de
 * catalagos/calendarios/index y sus 9 modales. Tests: tests/Js/catalogos-calendarios.test.ts.
 */

export const TURNOS = [1, 2, 3] as const;
export type Turno = (typeof TURNOS)[number];

export const DIAS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as const;
export type Dia = (typeof DIAS)[number];

export const NOMBRE_DIA: Record<Dia, string> = {
    lunes: 'Lunes', martes: 'Martes', miercoles: 'Miercoles', jueves: 'Jueves', viernes: 'Viernes', sabado: 'Sabado', domingo: 'Domingo',
};

/** Horas por defecto de un turno activo y hora de arranque del turno 1. */
export const HORAS_POR_DEFECTO = 8;
export const INICIO_TURNO_1 = '06:30';

/** Celda de la plantilla: turno × día. */
export interface Celda {
    activo: boolean;
    horas: number;
}

export type Plantilla = Record<Turno, Record<Dia, Celda>>;

export interface Horario {
    inicio: string;
    fin: string;
}

const segundos = (hora: string): number => {
    const [h = 0, m = 0, s = 0] = hora.split(':').map((p) => Number.parseInt(p, 10) || 0);

    return h * 3600 + m * 60 + s;
};

/** "6:30:00" (hora sin cero a la izquierda, como el modal de siempre). */
export function textoHora(total: number): string {
    const t = ((total % 86400) + 86400) % 86400;

    return `${Math.floor(t / 3600)}:${String(Math.floor((t % 3600) / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

/**
 * Inicio y fin de cada turno de un día: los turnos activos con horas se encadenan desde las 6:30;
 * el fin se muestra 2 s antes del inicio del siguiente (así se veía). null = turno sin horario.
 */
export function horariosDelDia(celdas: Record<Turno, Celda>): Record<Turno, Horario | null> {
    let cursor = segundos(INICIO_TURNO_1);
    const salida = {} as Record<Turno, Horario | null>;
    for (const turno of TURNOS) {
        const { activo, horas } = celdas[turno];
        if (!activo || horas <= 0) {
            salida[turno] = null;
            continue;
        }
        const fin = cursor + Math.round(horas * 3600);
        salida[turno] = { inicio: textoHora(cursor), fin: textoHora(fin - 2) };
        cursor = fin;
    }

    return salida;
}

/** Tope de horas para un turno: lo que dejan libre los demás turnos activos del día (máx. 24). */
export function maximoHoras(celdas: Record<Turno, Celda>, turno: Turno): number {
    const otros = TURNOS.filter((t) => t !== turno && celdas[t].activo).reduce((s, t) => s + celdas[t].horas, 0);

    return Math.max(0, 24 - otros);
}

export function celdasDelDia(plantilla: Plantilla, dia: Dia): Record<Turno, Celda> {
    return { 1: plantilla[1][dia], 2: plantilla[2][dia], 3: plantilla[3][dia] };
}

/** Mensaje de error de la plantilla o null (fechas, tope de 24 h por día). */
export function validarPlantilla(fechaInicial: string, fechaFinal: string, plantilla: Plantilla): string | null {
    if (!fechaInicial || !fechaFinal) return 'Por favor completa las fechas';
    if (fechaFinal < fechaInicial) return 'La fecha final debe ser posterior o igual a la fecha inicial';
    for (const dia of DIAS) {
        const celdas = celdasDelDia(plantilla, dia);
        const activos = TURNOS.filter((t) => celdas[t].activo);
        const total = activos.reduce((s, t) => s + celdas[t].horas, 0);
        if (activos.length && total > 24.01) {
            return `La suma de horas para ${NOMBRE_DIA[dia]} no puede ser mayor a 24 (actual: ${total})`;
        }
    }

    return null;
}

export type TurnosPayload = Partial<Record<Turno, Partial<Record<Dia, { horas: number; inicio: string; fin: string; activo: true }>>>>;

/** Lo que espera el servidor: solo turnos/días activos con horas, con su horario calculado. */
export function construirTurnos(plantilla: Plantilla): TurnosPayload {
    const salida: TurnosPayload = {};
    for (const dia of DIAS) {
        const horarios = horariosDelDia(celdasDelDia(plantilla, dia));
        for (const turno of TURNOS) {
            const h = horarios[turno];
            if (!h) continue;
            (salida[turno] ??= {})[dia] = { horas: plantilla[turno][dia].horas, inicio: h.inicio, fin: h.fin, activo: true };
        }
    }

    return salida;
}

/** Plantilla a partir del detalle del servidor (edición) o por defecto (alta: todo activo con 8 h). */
export function plantillaInicial(detalle: Partial<Record<string, Partial<Record<string, { horas?: number; activo?: boolean }>>>> | null): Plantilla {
    const p = {} as Plantilla;
    for (const turno of TURNOS) {
        p[turno] = {} as Record<Dia, Celda>;
        for (const dia of DIAS) {
            const d = detalle?.[String(turno)]?.[dia];
            p[turno][dia] = detalle
                ? { activo: d?.activo === true, horas: d?.activo === true ? Number(d?.horas ?? 0) || 0 : 0 }
                : { activo: true, horas: HORAS_POR_DEFECTO };
        }
    }

    return p;
}

/**
 * Siguiente calendario de la secuencia "Calendario Tejido N" / "Calendario TejN" (mira Nombre y
 * CalendarioId, como antes).
 */
export function siguienteCalendario(lista: ReadonlyArray<{ CalendarioId?: string | null; Nombre?: string | null }>): { id: string; nombre: string } {
    let max = 0;
    for (const c of lista) {
        for (const texto of [c.Nombre ?? '', c.CalendarioId ?? '']) {
            const m = /Calendario\s+Tej(?:ido\s*)?(\d+)/i.exec(texto);
            if (m?.[1]) max = Math.max(max, Number.parseInt(m[1], 10));
        }
    }
    const n = max + 1;

    return { id: `Calendario Tej${n}`, nombre: `Calendario Tejido ${n}` };
}

/** Validación de una línea (alta/edición). */
export function validarLinea(d: { CalendarioId?: string | undefined; FechaInicio: string; FechaFin: string; HorasTurno: string; Turno: string }): string | null {
    if ((d.CalendarioId !== undefined && d.CalendarioId.trim() === '') || !d.FechaInicio || !d.FechaFin || d.HorasTurno === '' || !d.Turno) {
        return 'Por favor completa todos los campos';
    }
    const horas = Number(d.HorasTurno);
    if (!Number.isFinite(horas) || horas < 0) return 'Las horas deben ser un número válido mayor o igual a 0';
    if (d.FechaFin <= d.FechaInicio) return 'La fecha de fin debe ser posterior a la fecha de inicio';

    return null;
}

/** Validación del borrado por rango. */
export function validarRango(fechaInicio: string, fechaFin: string, turnos: number[]): string | null {
    if (!fechaInicio || !fechaFin) return 'Por favor completa ambas fechas';
    if (fechaFin <= fechaInicio) return 'La fecha de fin debe ser posterior a la fecha de inicio';
    if (!turnos.length) return 'Por favor selecciona al menos un turno para eliminar';

    return null;
}

// ============ Filtros por columna ============

export type TablaFiltro = 'tab' | 'line';

export interface FiltroColumna {
    tabla: TablaFiltro;
    columna: string;
    valor: string;
}

/** Columnas por tabla, en el orden de las celdas de la fila. */
export const COLUMNAS: Record<TablaFiltro, Array<{ campo: string; titulo: string }>> = {
    tab: [
        { campo: 'CalendarioId', titulo: 'No Calendario' },
        { campo: 'Nombre', titulo: 'Nombre' },
    ],
    line: [
        { campo: 'CalendarioId', titulo: 'No Calendario' },
        { campo: 'FechaInicio', titulo: 'Inicio (Fecha Hora)' },
        { campo: 'FechaFin', titulo: 'Fin (Fecha Hora)' },
        { campo: 'HorasTurno', titulo: 'Horas' },
        { campo: 'Turno', titulo: 'Turno' },
    ],
};

/** ¿La fila (textos de sus celdas) pasa todos los filtros de su tabla? (contiene, sin mayúsculas). */
export function pasaFiltros(textos: readonly string[], tabla: TablaFiltro, filtros: readonly FiltroColumna[]): boolean {
    return filtros
        .filter((f) => f.tabla === tabla)
        .every((f) => {
            const i = COLUMNAS[tabla].findIndex((c) => c.campo === f.columna);

            return (textos[i] ?? '').toLowerCase().includes(f.valor.toLowerCase());
        });
}

export function filtroDuplicado(filtros: readonly FiltroColumna[], nuevo: FiltroColumna): boolean {
    return filtros.some((f) => f.tabla === nuevo.tabla && f.columna === nuevo.columna && f.valor === nuevo.valor);
}
