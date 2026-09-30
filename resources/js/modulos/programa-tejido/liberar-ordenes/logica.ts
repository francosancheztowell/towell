/**
 * Liberar Órdenes — lógica pura (sin DOM). La prueba tests/Js/programa-tejido-liberar.test.ts.
 * Las fórmulas son las mismas que usa el servidor al liberar (LiberarMarbetesCalculator).
 */

export interface ColumnaLiberar {
    field: string;
    label: string;
}

export interface ConfigLiberar {
    rutas: {
        procesar: string;
        redirect: string;
        tipoHilo: string;
        bom: string;
        codigoDibujo: string;
        flog: string;
        flogs: string;
    };
    columnas: ColumnaLiberar[];
    /** Peso de rollo estándar de Karl Mayer (LiberarOrdenesController::PESO_ROLLO_KG_KARL_MAYER). */
    pesoKarlMayer: string;
}

/** Registro que se manda a liberar (mismo contrato que tenía el <script> inline). */
export interface RegistroLiberar {
    id: string | null;
    prioridad: string;
    saldoPedido: string | null;
    noTiras: string | null;
    codigoDibujo: string | null;
    bomId: string | null;
    bomName: string | null;
    hiloAX: string | null;
    pesoRollo: string | null;
    repeticiones: string | null;
    saldoMarbete: string | null;
    mtsRollo: string | null;
    pzasRollo: string | null;
    totalRollos: string | null;
    totalPzas: string | null;
    densidad: string | null;
    observaciones: string | null;
    cambioRepaso: string | null;
    combinaTram: string | null;
    noProduccion: string | null;
    asignarFlogs: boolean | null;
    flogsId: string | null;
}

/** Número de la grilla ("1,234.5" → 1234.5); vacío o no numérico → 0. */
export function parseNumeroGrid(value: unknown): number {
    if (value === null || value === undefined) return 0;
    const cleaned = String(value).replace(/,/g, '').trim();
    return cleaned === '' ? 0 : parseFloat(cleaned) || 0;
}

/** Valor numérico sin comas de formato; null si no hay. */
export function sinComas(value: string | null): string | null {
    if (!value) return null;
    const cleaned = value.replace(/,/g, '');
    return cleaned === '' ? null : cleaned;
}

/**
 * Prioridad inicial de cada renglón: la suya; si está vacía, data-prioridad-anterior; si
 * tampoco hay, la del renglón de arriba (ya resuelta).
 */
export function prioridadesIniciales(filas: readonly { valor: string; anterior: string }[]): string[] {
    const res: string[] = [];
    filas.forEach((f, i) => {
        const actual = f.valor.trim();
        if (actual) res.push(f.valor);
        else if (f.anterior) res.push(f.anterior);
        else if (i > 0 && (res[i - 1] ?? '').trim()) res.push((res[i - 1] ?? '').trim());
        else res.push(f.valor);
    });
    return res;
}

export interface EntradaRollo {
    pesoRollo: number;
    pesoCrudo: number;
    noTiras: number;
    largoCrudo: number;
    esFelpa: boolean;
    esKm: boolean;
    /** FEL en AX o felpa (sin Karl Mayer): marbetes ×2, mts y pzas por rollo ÷2. */
    ajusteFel: boolean;
}

export interface SalidaRollo {
    repeticiones: number;
    mtsRollo: number | null;
    pzasRollo: number | null;
}

/** Repeticiones, metros y piezas por rollo; null si falta un dato (se limpian las celdas). */
export function calcularRollo(e: EntradaRollo): SalidaRollo | null {
    if ((!e.esFelpa && !e.esKm && e.pesoRollo <= 0) || e.pesoCrudo <= 0 || e.noTiras <= 0) return null;

    const repeticiones = Math.trunc(((e.pesoRollo / e.pesoCrudo) / e.noTiras) * 1000);
    let mtsRollo = e.largoCrudo > 0 && repeticiones > 0 ? (e.largoCrudo * repeticiones) / 100 : null;
    let pzasRollo = repeticiones > 0 ? Math.round(repeticiones * e.noTiras) : null;

    if (e.ajusteFel) {
        if (mtsRollo !== null && Number.isFinite(mtsRollo)) mtsRollo = mtsRollo / 2;
        if (pzasRollo !== null && Number.isFinite(pzasRollo)) pzasRollo = Math.round(pzasRollo / 2);
    }
    return { repeticiones, mtsRollo, pzasRollo };
}

/** Rollos para cubrir la base del pedido; null si no se puede calcular. */
export function totalRollos(basePedido: number, pzasRollo: number | null): number | null {
    return pzasRollo && pzasRollo > 0 && basePedido > 0 ? Math.ceil(basePedido / pzasRollo) : null;
}

/** true si el tamaño de AX es FEL. */
export function esInventSizeFel(inventSizeId: string | undefined): boolean {
    const v = (inventSizeId || '').trim();
    return v !== '' && v.toUpperCase().includes('FEL');
}

/** Comb Trama opcional. Tiras, saldo/toallas y métricas no pueden estar en cero o vacíos. */
export function validarMetricasProduccion(registros: readonly RegistroLiberar[]): string | null {
    for (let i = 0; i < registros.length; i++) {
        const reg = registros[i] as RegistroLiberar;
        const idx = ` Registro ${i + 1}.`;
        if (parseNumeroGrid(reg.noTiras) <= 0) return 'Las tiras deben ser mayores a cero (no se puede liberar con tiras vacías o en cero).' + idx;
        if (parseNumeroGrid(reg.saldoPedido) <= 0) return 'El saldo pedido en toallas debe ser mayor a cero.' + idx;
        if (parseNumeroGrid(reg.repeticiones) <= 0) return 'Repeticiones deben ser mayores a cero según la fórmula (revisa peso de rollo, peso crudo y tiras).' + idx;
        if (parseNumeroGrid(reg.saldoMarbete) <= 0) return 'No marbetes no puede ser cero ni vacío; debe coincidir con la fórmula.' + idx;
        if (parseNumeroGrid(reg.mtsRollo) <= 0) return 'Metros x rollo deben ser mayores a cero.' + idx;
        if (parseNumeroGrid(reg.pzasRollo) <= 0) return 'Pzas x rollo deben ser mayores a cero según la fórmula.' + idx;
        if (parseNumeroGrid(reg.totalRollos) <= 0) return 'Total rollos debe ser mayor a cero.' + idx;
        if (parseNumeroGrid(reg.totalPzas) <= 0) return 'Total piezas (toallas) debe ser mayor a cero.' + idx;
    }
    return null;
}

/** Algún registro sin L.Mat o sin Nombre L.Mat. */
export function faltaLMat(registros: readonly RegistroLiberar[]): boolean {
    return registros.some((r) => !(r.bomId ?? '').trim() || !(r.bomName ?? '').trim());
}

/** Mensaje de error de liberar: primer error de validación, el message o el genérico. */
export function mensajeLiberar(data: unknown): string {
    const d = (data && typeof data === 'object' ? data : {}) as { message?: unknown; errors?: unknown; trace_id?: unknown };
    let msg = typeof d.message === 'string' && d.message ? d.message : 'Error al liberar las órdenes.';
    if (d.errors && typeof d.errors === 'object') {
        const first = Object.values(d.errors as Record<string, unknown>).flat().find(Boolean);
        if (first) msg = String(first);
    }
    // 500 genérico (SEC-07): el código de referencia es lo que el usuario le pasa a Sistemas.
    return typeof d.trace_id === 'string' && d.trace_id ? `${msg} (ref: ${d.trace_id})` : msg;
}

/** Filtro de texto (modal "Filtros"): la celda contiene el valor, sin distinguir mayúsculas. */
export interface FiltroTexto {
    column: string;
    value: string;
}

/** Filtro tipo Excel: valores permitidos por columna ('(vacío)' = celda vacía). */
export type FiltrosColumna = Record<string, string[]>;

export const VACIO = '(vacío)';

/** Clave de un valor en el filtro tipo Excel. */
export const claveFiltro = (valor: string) => (valor === '' ? VACIO : valor);

/** ¿La fila (valor por columna) pasa los filtros? `valor` devuelve '' si no hay celda. */
export function filaPasaFiltros(
    valor: (columna: string) => string | null,
    filtros: readonly FiltroTexto[],
    porColumna: FiltrosColumna,
): boolean {
    for (const f of filtros) {
        const texto = (valor(f.column) ?? '').toLowerCase();
        if (!texto.includes(f.value.toLowerCase().trim())) return false;
    }
    for (const [columna, permitidos] of Object.entries(porColumna)) {
        if (!permitidos) continue;
        if (permitidos.length === 0) return false;
        if (!permitidos.includes(claveFiltro(valor(columna) ?? ''))) return false;
    }
    return true;
}

/** Valores únicos de una columna con su conteo, ordenados sin distinguir mayúsculas. */
export function valoresUnicos(valores: readonly string[]): [string, number][] {
    const conteo = new Map<string, number>();
    valores.forEach((v) => {
        const k = claveFiltro(v);
        conteo.set(k, (conteo.get(k) ?? 0) + 1);
    });
    return Array.from(conteo.entries()).sort((a, b) => a[0].localeCompare(b[0], undefined, { sensitivity: 'base' }));
}
