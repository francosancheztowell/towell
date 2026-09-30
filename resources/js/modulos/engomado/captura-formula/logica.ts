/**
 * Captura de Fórmula: funciones puras (sin DOM) → tests/Js/urdeng-formula.test.mjs.
 */

/** Componente de la fórmula tal como lo maneja la tabla del modal (AX o EngFormulacionLine). */
export interface Componente {
    Id?: number | undefined;
    ItemId?: string | null;
    ItemName?: string | null;
    ConfigId?: string | null;
    ConsumoUnitario?: number | string | null;
    ConsumoTotal?: number | string | null;
    Unidad?: string | null;
    Almacen?: string | null;
    /** Fila agregada a mano en el modal (select de artículo). */
    esNuevo?: boolean;
}

export interface LimiteConsumo {
    max: number;
    title: string;
    alertTitle: string;
    alertText: string;
}

export const STATUS_FINALIZADOS = ['FINALIZADO', 'TERMINADO'];
/** Consumo Total: AE-021 máx 10; no-agua máx 100; agua máx = Litros. */
export const CONSUMO_TOTAL_MAX_NO_AGUA = 100;
export const CONSUMO_TOTAL_MAX_AE_021 = 10;
export const LITROS_MAX = 1500;

export function normalizarStatus(status: unknown): string {
    return String(status ?? '').trim().toUpperCase();
}

export function statusEsFinalizado(status: unknown): boolean {
    return STATUS_FINALIZADOS.includes(normalizarStatus(status));
}

/** parseFloat que devuelve 0 en vez de NaN (el `parseFloat(x) || 0` de la vista vieja). */
export function num(valor: unknown): number {
    const n = parseFloat(String(valor ?? ''));
    return Number.isNaN(n) ? 0 : n;
}

export function redondear2(valor: number): number {
    return Math.round(valor * 100) / 100;
}

export function esComponenteAe021(comp: Componente): boolean {
    return String(comp.ItemId ?? '').trim().toUpperCase() === 'AE-021';
}

export function esComponenteAgua(comp: Componente): boolean {
    const id = String(comp.ItemId ?? '').toLowerCase();
    const nombre = String(comp.ItemName ?? '').toLowerCase();
    return id.includes('agua') || nombre.includes('agua');
}

export function limiteConsumo(comp: Componente, litros: number): LimiteConsumo {
    if (esComponenteAgua(comp)) {
        const max = Math.max(0, litros);
        return {
            max,
            title: `Máximo igual a Litros (${max})`,
            alertTitle: 'Consumo Total agua',
            alertText: `En el componente agua, Consumo Total no puede ser mayor a Litros (${max}).`,
        };
    }
    if (esComponenteAe021(comp)) {
        return {
            max: CONSUMO_TOTAL_MAX_AE_021,
            title: 'Máximo 10 para AE-021',
            alertTitle: 'Consumo Total máximo 10',
            alertText: 'El artículo AE-021 no puede tener un Consumo Total mayor a 10.',
        };
    }
    return {
        max: CONSUMO_TOTAL_MAX_NO_AGUA,
        title: 'Máximo 100 (excepto agua)',
        alertTitle: 'Consumo Total máximo 100',
        alertText: 'En componentes que no son agua, Consumo Total no puede ser mayor a 100.',
    };
}

/** Aplica tope: agua <= litros; AE-021 <= 10; resto <= 100. */
export function aplicarMaxConsumoTotal(comp: Componente, valor: number, litros: number): number {
    return Math.min(valor, limiteConsumo(comp, litros).max);
}

/** Texto del aviso cuando un Consumo Total pasa su tope. */
export function mensajeLimiteConsumo(comp: Componente, litros: number): string {
    const info = limiteConsumo(comp, litros);
    return `${info.alertTitle}: ${info.alertText} Revisa: ${comp.ItemId || comp.ItemName || 'componente'}`;
}

export function tieneArticulo(comp: Componente): boolean {
    return String(comp.ItemId ?? '').trim() !== '';
}

/** Componentes que se guardan: con artículo y Consumo Total > 0. */
export function componentesParaGuardar<T extends Componente>(componentes: T[]): T[] {
    return componentes.filter((c) => tieneArticulo(c) && num(c.ConsumoTotal) > 0);
}

/** Primer componente (con artículo) cuyo Consumo Total pasa su tope, o undefined. */
export function primerConsumoExcedido<T extends Componente>(componentes: T[], litros: number): T | undefined {
    return componentes.find((c) => tieneArticulo(c) && num(c.ConsumoTotal) > limiteConsumo(c, litros).max);
}

/** Normaliza un componente de EngFormulacionLine (by-id) con el tope de Consumo Total aplicado. */
export function componenteGuardado(comp: Componente, litros: number): Componente {
    return {
        Id: comp.Id,
        ItemId: comp.ItemId || '',
        ItemName: comp.ItemName || '',
        ConfigId: comp.ConfigId || '',
        ConsumoUnitario: comp.ConsumoUnitario || 0,
        ConsumoTotal: aplicarMaxConsumoTotal(comp, num(comp.ConsumoTotal), litros),
        Unidad: comp.Unidad || '',
        Almacen: comp.Almacen || '',
        esNuevo: false,
    };
}

/**
 * Query de formulas-disponibles; null si no hay bomId ni fórmula (el endpoint respondería 400).
 */
export function queryFormulasDisponibles(bomEng: string, formula: string): string | null {
    const params = new URLSearchParams();
    const b = bomEng.trim();
    const f = formula.trim();
    if (b) params.set('bomId', b);
    if (f) params.set('formula', f);
    const q = params.toString();
    return q || null;
}

/** Opciones del select de fórmula: las de AX y, al final, la guardada si AX no la trae. */
export function opcionesFormula(formulasAx: unknown, formulaGuardada: string): string[] {
    const lista = Array.isArray(formulasAx) ? formulasAx.map((f) => String(f)) : [];
    if (formulaGuardada && !lista.includes(formulaGuardada)) lista.push(formulaGuardada);
    return lista;
}

/** ¿El registro `id` es el primero (Id menor) de su folio? */
export function esPrimerRegistro(idsDelFolio: number[], id: number): boolean {
    const validos = idsDelFolio.filter((n) => n > 0);
    return validos.length > 0 && Math.min(...validos) === id;
}

export interface ValoresCaptura {
    kilos: number;
    litros: number;
    tiempo: number;
    solidos: number;
    viscocidad: number;
}

/** Validación del submit (mismos mensajes que la vista vieja). null = válido. */
export function validarCaptura(v: ValoresCaptura): string | null {
    if (Number.isNaN(v.kilos) || v.kilos < 0) return 'Los Kilos no pueden ser negativos';
    if (Number.isNaN(v.litros) || v.litros <= 0) return 'Los Litros deben ser mayor a cero';
    if (v.litros > LITROS_MAX) return 'Los Litros no pueden ser mayor a 1500';
    if (Number.isNaN(v.tiempo) || v.tiempo <= 0) return 'El Tiempo Cocinado debe ser mayor a cero';
    if (Number.isNaN(v.solidos) || v.solidos <= 0) return 'El % Sólidos debe ser mayor a cero';
    if (Number.isNaN(v.viscocidad) || v.viscocidad <= 0) return 'La Viscosidad debe ser mayor a cero';
    return null;
}

// ---- Filtros por columna ----

export const VACIO = '(Vacío)';

/** Valores únicos de una columna con su conteo, orden natural es-MX. */
export function valoresConConteo(textos: string[]): [string, number][] {
    const mapa = new Map<string, number>();
    for (const t of textos) {
        const clave = t.trim() || VACIO;
        mapa.set(clave, (mapa.get(clave) ?? 0) + 1);
    }
    return [...mapa.entries()].sort((a, b) => a[0].localeCompare(b[0], 'es', { numeric: true }));
}

/** ¿La fila (textos de sus celdas) pasa todos los filtros activos? */
export function filaPasaFiltros(celdas: string[], filtros: Map<number, Set<string>>): boolean {
    for (const [col, permitidos] of filtros) {
        const celda = celdas[col];
        if (celda === undefined) return false;
        if (!permitidos.has(celda.trim() || VACIO)) return false;
    }
    return true;
}

/**
 * Resultado de "Aplicar": null quita el filtro (todos o ninguno marcados), si no el conjunto.
 */
export function filtroDeSeleccion(marcados: string[], total: number): Set<string> | null {
    if (marcados.length === 0 || marcados.length >= total) return null;
    return new Set(marcados);
}

/** Índices de filas ordenados por fecha ISO (vacías al final, estable). */
export function ordenPorFecha(fechas: string[], asc: boolean): number[] {
    return fechas
        .map((fecha, index) => ({ fecha, index }))
        .sort((a, b) => {
            if (!a.fecha && !b.fecha) return a.index - b.index;
            if (!a.fecha) return 1;
            if (!b.fecha) return -1;
            if (a.fecha === b.fecha) return a.index - b.index;
            return asc ? a.fecha.localeCompare(b.fecha) : b.fecha.localeCompare(a.fecha);
        })
        .map((x) => x.index);
}

// ---- Calidad ----

export type Ok = 0 | 1 | null;

/** data-ok* ('' = sin capturar, '0' = ✗, '1' = ✓) → valor del PUT. */
export function okDesdeDato(valor: string | undefined): Ok {
    if (valor === '1') return 1;
    if (valor === '0') return 0;
    return null;
}

/** Estado inicial del botón del modal: sin capturar arranca en ✓ (como antes). */
export function okInicial(valor: string | undefined): '0' | '1' {
    return valor === '0' ? '0' : '1';
}

export function okSiguiente(valor: string | undefined): '0' | '1' {
    return valor === '1' ? '0' : '1';
}

/** Clases del chip de status del programa en el modal de calidad. */
export function clasesStatusPrograma(status: string): string {
    if (status === 'Finalizado') return 'bg-gray-200 text-gray-700';
    return status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500';
}
