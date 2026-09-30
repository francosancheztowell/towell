/**
 * Programación de Requerimientos — lógica pura (sin DOM), probada en
 * tests/Js/programa-urd-eng-programacion-requerimientos.test.mjs.
 */
import { formatNumber } from '../../../utils/format.ts';
import type { TelarRequerimiento, TelarSeleccionado } from '../comun/contrato-flujo.ts';

/* =================== Tipos =================== */

/** Telar tal como lo usa la pantalla: tipo normalizado y sin hilo (se elige aquí). */
export type TelarEntrada = Omit<TelarSeleccionado, 'tipo' | 'hilo'> & { tipo: string; hilo: string | null };

export type Validacion =
    | { valido: false; mensaje: string }
    | { valido: true; tipo: string; calibre: number | null; hilo: null };

export interface Semana {
    inicio?: string | null;
    fin?: string | null;
}

/** Fila cruda del resumen (ResumenSemanasService): claves en PascalCase o camelCase. */
export type FilaResumenCruda = Record<string, unknown>;

export interface DatosResumen {
    rizo?: FilaResumenCruda[];
    pie?: FilaResumenCruda[];
}

export interface ItemResumen {
    telar: string;
    cuenta: string;
    hilo: string;
    calibre: unknown;
    modelo: string;
    /** Metros por semana (5). */
    metros: number[];
    /** Kilos por semana (5). */
    kilos: number[];
    total: number;
    totalKilos: number;
}

export interface TotalesTelar {
    totalMetros: number;
    totalKilos: number;
}

export interface TotalesResumen {
    metros: number[];
    kilos: number[];
    totalMetros: number;
    totalKilos: number;
}

/** Valores capturados en una fila de la tabla de requerimientos (tal cual los inputs). */
export interface FilaCapturada {
    /** Texto del campo Telar (los telares del grupo separados por coma). */
    telar: string;
    fecha_req: string;
    cuenta: string;
    calibre: string;
    tamano: string;
    hilo: string;
    urdido: string;
    tipo: string;
    tipo_atado: string;
    metros: string;
    kilos: string;
    /** Telares que representa la fila (agrupados por cuenta). */
    grupo: { no_telar: string | null }[];
}

export type CampoRequerido = Exclude<keyof FilaCapturada, 'telar' | 'fecha_req' | 'grupo'>;

export const SEMANAS = 5;
export const TIPOS_JULIO = ['Rizo', 'Pie'] as const;
export const TIPOS_ATADO = ['Normal', 'Especial'] as const;
export const MAX_TAMANOS_SUGERIDOS = 60;

/* =================== Normalización =================== */

/** "RIZO"/"rizo" → "Rizo", "PIE" → "Pie"; otro valor se devuelve tal cual; vacío → ''. */
export function normalizarTipo(tipo: unknown): string {
    if (tipo === null || tipo === undefined || tipo === '') return '';
    const mayus = String(tipo).toUpperCase().trim();
    if (mayus === 'RIZO') return 'Rizo';
    if (mayus === 'PIE') return 'Pie';
    return String(tipo);
}

/** Tipo normalizado y hilo en null: el usuario elige el hilo en esta pantalla. */
export function normalizarEntrada(telares: readonly TelarSeleccionado[] | null | undefined): TelarEntrada[] {
    return (telares ?? []).map((t) => ({ ...t, tipo: normalizarTipo(t.tipo), hilo: null }));
}

function calibreNumero(valor: unknown): number | null {
    return valor !== null && valor !== undefined && valor !== '' ? parseFloat(String(valor)) : null;
}

/** Todos deben compartir tipo (obligatorio) y, si lo traen, calibre (±0.01). El hilo no se valida. */
export function validarGrupo(telares: readonly TelarEntrada[]): Validacion {
    const base = telares[0];
    if (!base) return { valido: false, mensaje: 'No hay telares seleccionados' };

    const tipoBase = String(base.tipo || '').toUpperCase().trim();
    const calBase = calibreNumero(base.calibre);
    if (!tipoBase) return { valido: false, mensaje: 'El telar debe tener un tipo definido' };

    for (const t of telares.slice(1)) {
        const tipo = String(t.tipo || '').toUpperCase().trim();
        if (tipo !== tipoBase) {
            return {
                valido: false,
                mensaje: `El telar ${t.no_telar || 'N/A'} tiene tipo "${t.tipo || 'N/A'}" pero se esperaba "${base.tipo || 'N/A'}".`,
            };
        }
        const cal = calibreNumero(t.calibre);
        if (calBase !== null && cal !== null && Math.abs(calBase - cal) >= 0.01) {
            return { valido: false, mensaje: `El telar ${t.no_telar || 'N/A'} calibre "${cal}" ≠ "${calBase}".` };
        }
    }

    return { valido: true, tipo: normalizarTipo(base.tipo), calibre: calBase, hilo: null };
}

/** Deja solo los telares del tipo/calibre validados (filtrado tolerante del original). */
export function filtrarPorGrupo(telares: readonly TelarEntrada[], v: Validacion & { valido: true }): TelarEntrada[] {
    const tipo = String(v.tipo || '').toUpperCase().trim();
    return telares.filter((t) => {
        if (String(t.tipo || '').toUpperCase().trim() !== tipo) return false;
        const cal = calibreNumero(t.calibre);
        return !(v.calibre !== null && cal !== null && Math.abs(v.calibre - cal) >= 0.01);
    });
}

/** Un grupo por cuenta, en orden de aparición; sin cuenta, cada telar va solo. */
export function agruparPorCuenta<T extends { cuenta?: string | null; no_telar?: string | null }>(telares: readonly T[]): T[][] {
    const grupos = new Map<string, T[]>();
    for (const t of telares) {
        const clave = String(t.cuenta || '').trim() || `_${t.no_telar}`;
        const grupo = grupos.get(clave);
        if (grupo) grupo.push(t);
        else grupos.set(clave, [t]);
    }
    return [...grupos.values()];
}

/* =================== Fechas y números =================== */

/** Hoy YYYY-MM-DD en la zona de la planta. */
export function fechaHoyISO(zona = 'America/Mexico_City', ahora: Date = new Date()): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: zona, year: 'numeric', month: '2-digit', day: '2-digit' }).format(ahora);
}

/** "2026-09-28" → "28/09"; vacío o inválido → ''. */
export function fechaCortaDiaMes(iso: string | null | undefined): string {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));
    return m ? `${m[3]}/${m[2]}` : '';
}

/** Rango de una semana para el encabezado ("28/09 - 04/10"); '' si falta alguna fecha. */
export function etiquetaSemana(semana: Semana | null | undefined): string {
    const ini = fechaCortaDiaMes(semana?.inicio);
    const fin = fechaCortaDiaMes(semana?.fin);
    return ini && fin ? `${ini} - ${fin}` : '';
}

/** Valor para un input numérico con miles y 2 decimales; vacío, 0 o no numérico → ''. */
export function formatearNumeroInput(valor: unknown): string {
    if (!valor) return '';
    const n = parseFloat(String(valor).replace(/,/g, ''));
    if (Number.isNaN(n)) return '';
    return formatNumber(n, 2);
}

/** Quita los separadores de miles ("1,234.50" → "1234.50"). */
export function limpiarNumeroInput(valor: unknown): string {
    if (!valor) return '';
    return String(valor).replace(/,/g, '');
}

/** Solo dígitos, punto y coma mientras se teclea. */
export function soloCaracteresNumericos(valor: string): string {
    return valor.replace(/[^\d.,]/g, '');
}

/** Celda del resumen: > 0 con 2 decimales, si no '-'. */
export function formatearCantidad(n: unknown): string {
    const v = Number(n || 0);
    return v > 0 ? formatNumber(v, 2) : '-';
}

/* =================== Tamaño, tipo y guardado =================== */

/** "4112-12.5/1" → { cuenta: '4112', calibre: '12.5' }; vacío → ambos ''; formato ajeno → null (no tocar). */
export function cuentaYCalibreDeTamano(tamano: string): { cuenta: string; calibre: string } | null {
    const valor = tamano.trim();
    if (!valor) return { cuenta: '', calibre: '' };
    const m = /^([^-]+)-([^/]+)\/\d+$/.exec(valor);
    if (!m) return null;
    return { cuenta: (m[1] ?? '').trim(), calibre: (m[2] ?? '').trim() };
}

/** Sugerencias del autocompletado de tamaño (contiene, sin mayúsculas; máx. 60). */
export function filtrarTamanos(disponibles: readonly string[], termino: string, max = MAX_TAMANOS_SUGERIDOS): string[] {
    const t = termino.trim().toLowerCase();
    return (t ? disponibles.filter((x) => x.toLowerCase().includes(t)) : [...disponibles]).slice(0, max);
}

/** Colores del select Tipo (los del original: rojo para Rizo o vacío, verde azulado para Pie). */
export function estiloTipo(tipo: string): { backgroundColor: string; color: string } {
    return normalizarTipo(tipo) === 'Pie'
        ? { backgroundColor: '#ccfbf1', color: '#0f766e' }
        : { backgroundColor: '#fee2e2', color: '#be123c' };
}

export interface ContextoFila {
    inventarioId: string;
    fecha: string;
    turno: string;
}

/** Payload de programa.urd.eng.actualizar.telar (solo inventario). */
export function payloadActualizarTelar(
    campo: string,
    valor: string,
    noTelar: string,
    tipo: string,
    fila: ContextoFila,
): Record<string, unknown> {
    const payload: Record<string, unknown> = { no_telar: noTelar, [campo]: valor, solo_inventario: true };
    const id = parseInt(fila.inventarioId, 10);
    if (fila.inventarioId !== '' && !Number.isNaN(id)) payload.id = id;
    if (fila.fecha) payload.fecha = fila.fecha;
    if (fila.turno) payload.turno = fila.turno;
    if (campo === 'tipo' && valor) payload.tipo = normalizarTipo(valor);
    else if (tipo) payload.tipo = normalizarTipo(tipo);
    return payload;
}

export interface DetalleActualizacion {
    tej_inventario_telares?: number;
    urd_programa_urdido?: number;
    eng_programa_engomado?: number;
}

/** "Hilo actualizado: 1 en TejInventarioTelares, …". */
export function mensajeGuardado(campo: string, detalle?: DetalleActualizacion | null): string {
    let mensaje = `${campo.charAt(0).toUpperCase()}${campo.slice(1)} actualizado`;
    if (!detalle) return mensaje;
    const partes: string[] = [];
    if ((detalle.tej_inventario_telares ?? 0) > 0) partes.push(`${detalle.tej_inventario_telares} en TejInventarioTelares`);
    if ((detalle.urd_programa_urdido ?? 0) > 0) partes.push(`${detalle.urd_programa_urdido} en UrdProgramaUrdido`);
    if ((detalle.eng_programa_engomado ?? 0) > 0) partes.push(`${detalle.eng_programa_engomado} en EngProgramaEngomado`);
    if (partes.length) mensaje += `: ${partes.join(', ')}`;
    return mensaje;
}

/* =================== Resumen por semana =================== */

/** Primer valor "verdadero" de las claves (semántica `a || b || …` del original). */
function primero(fila: FilaResumenCruda, claves: readonly string[], porDefecto: unknown = ''): unknown {
    for (const c of claves) if (fila[c]) return fila[c];
    return porDefecto;
}

function semanasDe(fila: FilaResumenCruda, sufijo: 'Rizo' | 'Pie'): { metros: number[]; kilos: number[] } {
    const metros: number[] = [];
    const kilos: number[] = [];
    for (let i = 0; i < SEMANAS; i++) {
        const n = i === 0 ? '' : String(i);
        metros.push(Number(primero(fila, [`SemActual${n}Mts${sufijo}`, `semActual${n}Mts${sufijo}`, `SemActual${n}`], 0) || 0));
        kilos.push(Number(primero(fila, [`SemActual${n}Kilos${sufijo}`, `semActual${n}Kilos${sufijo}`], 0) || 0));
    }
    return { metros, kilos };
}

/**
 * Filas del resumen que corresponden al grupo validado:
 * - Rizo: filtra por hilo solo si la validación trae uno (hoy nunca: hilo null).
 * - Pie: filtra por calibre con tolerancia 0.11; no filtra por hilo.
 */
export function itemsResumen(data: DatosResumen | null | undefined, v: Validacion & { valido: true }): ItemResumen[] {
    const tipo = String(v.tipo || '').toUpperCase().trim();
    const items: ItemResumen[] = [];
    const comunes = (it: FilaResumenCruda) => ({
        telar: String(primero(it, ['TelarId', 'telarId', 'Telar', 'telar'])),
        modelo: String(primero(it, ['Modelo', 'modelo'], '-')),
        total: Number(it.Total || 0),
        totalKilos: Number(it.TotalKilos || 0),
    });

    if (tipo === 'RIZO' && Array.isArray(data?.rizo)) {
        for (const it of data.rizo) {
            const hilo = String(primero(it, ['Hilo', 'hilo'])).trim();
            if (v.hilo !== null && hilo.toUpperCase() !== String(v.hilo).toUpperCase()) continue;
            items.push({
                ...comunes(it),
                cuenta: String(primero(it, ['CuentaRizo', 'cuentaRizo', 'Cuenta', 'cuenta'])),
                hilo: hilo || '-',
                calibre: v.calibre !== null ? v.calibre : primero(it, ['Calibre', 'calibre'], '-'),
                ...semanasDe(it, 'Rizo'),
            });
        }
    }

    if (tipo === 'PIE' && Array.isArray(data?.pie)) {
        for (const it of data.pie) {
            const cal = it.CalibrePie ?? it.calibrePie ?? it.Calibre ?? it.calibre;
            const calNum = calibreNumero(cal);
            if (v.calibre !== null && calNum !== null && !(Math.abs(v.calibre - calNum) <= 0.11)) continue;
            const hilo = String(primero(it, ['Hilo', 'hilo'])).trim();
            items.push({
                ...comunes(it),
                cuenta: String(primero(it, ['CuentaPie', 'cuentaPie', 'Cuenta', 'cuenta'])),
                hilo: hilo || '-',
                calibre: cal ?? '-',
                ...semanasDe(it, 'Pie'),
            });
        }
    }

    return items;
}

/** Solo se pinta la fila si alguna semana tiene metros o kilos. */
export function itemTieneDatos(item: ItemResumen): boolean {
    return item.metros.some((m, i) => m > 0 || (item.kilos[i] ?? 0) > 0);
}

/** Totales por columna de semana y generales (suma de semanas, no de la columna Total). */
export function totalesResumen(items: readonly ItemResumen[]): TotalesResumen {
    const metros = Array<number>(SEMANAS).fill(0);
    const kilos = Array<number>(SEMANAS).fill(0);
    for (const it of items) {
        for (let i = 0; i < SEMANAS; i++) {
            metros[i] = (metros[i] ?? 0) + (it.metros[i] ?? 0);
            kilos[i] = (kilos[i] ?? 0) + (it.kilos[i] ?? 0);
        }
    }
    const suma = (xs: number[]) => xs.reduce((a, b) => a + b, 0);
    return { metros, kilos, totalMetros: suma(metros), totalKilos: suma(kilos) };
}

/** Total (columna Total/TotalKilos) por telar, para prellenar metros y calcular kilos. */
export function totalesPorTelar(items: readonly ItemResumen[]): Map<string, TotalesTelar> {
    const porTelar = new Map<string, TotalesTelar>();
    for (const it of items) {
        const clave = String(it.telar || '').trim();
        const acc = porTelar.get(clave) ?? { totalMetros: 0, totalKilos: 0 };
        acc.totalMetros += Number(it.total || 0);
        acc.totalKilos += Number(it.totalKilos || 0);
        porTelar.set(clave, acc);
    }
    return porTelar;
}

/** Kilos proporcionales a los metros capturados según la relación kg/m del telar. */
export function kilosProgramados(totales: TotalesTelar | undefined, metros: unknown): number {
    if (!totales || totales.totalMetros <= 0 || totales.totalKilos <= 0) return 0;
    return (totales.totalKilos / totales.totalMetros) * (Number(metros) || 0);
}

/** Texto (multilínea) cuando no hay filas del resumen para el grupo. */
export function mensajeResumenVacio(
    data: DatosResumen | null | undefined,
    v: Validacion & { valido: true },
    semanas: readonly Semana[],
): string {
    const tipo = String(v.tipo || '').toUpperCase().trim();
    const registros = tipo === 'RIZO' ? (data?.rizo?.length ?? 0) : tipo === 'PIE' ? (data?.pie?.length ?? 0) : 0;

    let mensaje = 'No hay datos de programación en el rango de 5 semanas.';
    mensaje += `\nTipo: ${tipo || 'N/A'}`;
    mensaje += `\nCalibre: ${v.calibre ?? 'N/A'}`;
    mensaje += `\nHilo: ${tipo === 'PIE' ? 'No aplica' : (v.hilo ?? 'Todos')}`;

    if (registros > 0) {
        const filtros = tipo === 'PIE' ? 'calibre' : 'hilo/calibre';
        mensaje += `\n\nNota: Existen ${registros} registro(s) en la base de datos, pero no coinciden con los filtros aplicados (${filtros}) o no tienen fechas en el rango de las 5 semanas.`;
    } else {
        mensaje += `\n\nNota: No se encontraron registros de programación para los telares seleccionados en el rango de fechas de las 5 semanas (${semanas[0]?.inicio || 'N/A'} a ${semanas[4]?.fin || 'N/A'}).`;
    }
    return mensaje;
}

/* =================== Continuar a creación de órdenes =================== */

/** Campos que habilitan el botón Siguiente (el original no exigía urdido/tipo/tipo atado aquí: siempre traen valor). */
const CAMPOS_BOTON: readonly CampoRequerido[] = ['tamano', 'cuenta', 'calibre', 'hilo', 'metros', 'kilos'];

export const CAMPOS_REQUERIDOS: readonly { campo: CampoRequerido; etiqueta: string }[] = [
    { campo: 'cuenta', etiqueta: 'Cuenta' },
    { campo: 'calibre', etiqueta: 'Calibre' },
    { campo: 'tamano', etiqueta: 'Tamaño' },
    { campo: 'hilo', etiqueta: 'Hilo' },
    { campo: 'urdido', etiqueta: 'Urdido' },
    { campo: 'tipo', etiqueta: 'Tipo' },
    { campo: 'tipo_atado', etiqueta: 'Tipo Atado' },
    { campo: 'metros', etiqueta: 'Metros' },
    { campo: 'kilos', etiqueta: 'Kilos' },
];

export function puedeContinuar(filas: readonly FilaCapturada[]): boolean {
    const validas = filas.filter((f) => f.telar);
    if (!validas.length) return false;
    return validas.every((f) => CAMPOS_BOTON.every((c) => String(f[c] ?? '').trim() !== ''));
}

/** Primer campo requerido vacío (fila, campo y mensaje), o null si todo está capturado. */
export function primerCampoFaltante(
    filas: readonly FilaCapturada[],
): { indice: number; campo: CampoRequerido; mensaje: string } | null {
    for (const [indice, fila] of filas.entries()) {
        if (!fila.telar) continue;
        for (const { campo, etiqueta } of CAMPOS_REQUERIDOS) {
            const valor = String(fila[campo] ?? '').trim();
            if (!(campo === 'metros' || campo === 'kilos' ? limpiarNumeroInput(valor) : valor)) {
                return { indice, campo, mensaje: `Telar ${fila.telar}: complete el campo "${etiqueta}".` };
            }
        }
    }
    return null;
}

/** Un renglón por telar de cada grupo (contrato de creacion-ordenes). */
export function telaresParaCreacion(filas: readonly FilaCapturada[]): TelarRequerimiento[] {
    const salida: TelarRequerimiento[] = [];
    for (const f of filas) {
        const grupo = f.grupo.length ? f.grupo : f.telar ? [{ no_telar: f.telar }] : [];
        for (const t of grupo) {
            if (!t.no_telar) continue;
            salida.push({
                no_telar: t.no_telar,
                fecha_req: f.fecha_req,
                cuenta: f.cuenta,
                calibre: f.calibre !== '' ? parseFloat(f.calibre) : null,
                hilo: f.hilo,
                tamano: f.tamano,
                urdido: f.urdido,
                tipo: normalizarTipo(f.tipo || 'Rizo') || 'Rizo',
                destino: '',
                tipo_atado: f.tipo_atado,
                metros: limpiarNumeroInput(f.metros || '0') || '0',
                kilos: limpiarNumeroInput(f.kilos || '0') || '0',
                agrupar: true,
            });
        }
    }
    return salida;
}
