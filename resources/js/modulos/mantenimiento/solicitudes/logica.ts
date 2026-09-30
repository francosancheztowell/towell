/**
 * Reglas de la lista de Solicitudes (paros) sin DOM
 * (tests en tests/Js/mantenimiento-solicitudes.test.mjs).
 */

export interface ParoFila {
    Id?: number | string;
    Folio?: string;
    Estatus?: string;
    Fecha?: string;
    Hora?: string;
    Depto?: string;
    MaquinaId?: string;
    TipoFallaId?: string;
    Falla?: string;
    NomEmpl?: string;
    CveEmpl?: string;
}

export type ModoCarga = 'default' | 'todos' | 'depto';

const txt = (v: unknown): string => String(v ?? '').trim();

/** Query del listado: default = área del usuario; todos = alcance=todos; depto = ese depto. */
export function parametrosCarga(modo: ModoCarga, depto: string | undefined, incluirTerminados: boolean): string {
    const p = new URLSearchParams();
    if (modo === 'todos') p.set('alcance', 'todos');
    else if (modo === 'depto' && depto) p.set('depto', depto);
    if (incluirTerminados) p.set('incluir_finalizados', '1');
    const qs = p.toString();
    return qs ? '?' + qs : '';
}

/** Valores distintos y ordenados de un campo (para los combos Status / Máquina). */
export function valoresUnicos(paros: ParoFila[], campo: keyof ParoFila): string[] {
    return [...new Set(paros.map((p) => txt(p[campo])).filter(Boolean))].sort();
}

/** Áreas del combo: catálogo + las que aparezcan en los paros, sin repetir. */
export function departamentosCombo(catalogo: string[], paros: ParoFila[]): string[] {
    return [...new Set([...catalogo.map(txt).filter(Boolean), ...valoresUnicos(paros, 'Depto')])].sort();
}

export interface Filtros {
    depto: string;
    status: string;
    maquina: string;
    soloMias: boolean;
}

export interface Usuario {
    nombre: string;
    numeroEmpleado: string;
}

/** ¿La fila pasa los filtros de la ventana? "Solo mis solicitudes" compara nombre o número de empleado. */
export function pasaFiltros(paro: ParoFila, f: Filtros, u: Usuario): boolean {
    if (f.depto && txt(paro.Depto) !== f.depto) return false;
    if (f.status && txt(paro.Estatus) !== f.status) return false;
    if (f.maquina && txt(paro.MaquinaId) !== f.maquina) return false;
    if (f.soloMias && txt(paro.NomEmpl) !== u.nombre && txt(paro.CveEmpl) !== u.numeroEmpleado) return false;
    return true;
}

export function hayFiltro(f: Filtros): boolean {
    return Boolean(f.depto || f.status || f.maquina || f.soloMias);
}

/**
 * Status que queda elegido tras recargar. Sin terminados solo hay Activos, así que
 * el combo vuelve a "Todos"; al activar terminados se preselecciona Activo.
 */
export function statusTrasCarga(opciones: string[], previo: string, incluirTerminados: boolean, forzarActivo: boolean): string {
    if (!incluirTerminados) return '';
    if (forzarActivo && opciones.includes('Activo')) return 'Activo';
    if (previo && opciones.includes(previo)) return previo;
    return opciones.includes('Activo') ? 'Activo' : '';
}

export function esActivo(estatus: unknown): boolean {
    return txt(estatus).toLowerCase() === 'activo';
}
