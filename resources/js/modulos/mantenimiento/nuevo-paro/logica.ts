/**
 * Reglas del alta de paro sin DOM (tests en tests/Js/mantenimiento-nuevo-paro.test.mjs).
 */

export interface Maquina {
    MaquinaId: string | number;
    Nombre?: string;
    Departamento?: string;
    DepartamentoOrigen?: string;
}

export interface Falla {
    Id?: number | string;
    Falla?: string;
    Descripcion?: string | null;
}

export const LARGO_ORDEN_TRABAJO = 20;

/** Las órdenes son un folio sin espacios; los operadores pegan texto extra detrás. */
export function sinEspacios(valor: string): string {
    return valor.replace(/\s+/g, '');
}

/** Orden sugerida por el servidor, ya lista para el input (sin espacios, máx. 20). */
export function ordenSugerida(valor: unknown): string {
    return sinEspacios(String(valor ?? '')).slice(0, LARGO_ORDEN_TRABAJO);
}

/** Departamento del catálogo que coincide con el área del usuario (sin distinguir mayúsculas ni espacios). */
export function departamentoDelArea(departamentos: string[], area: string | null | undefined): string | null {
    const buscada = (area ?? '').trim().toUpperCase();
    if (!buscada) return null;
    return departamentos.find((d) => d.trim().toUpperCase() === buscada) ?? null;
}

export function esCalidad(departamento: string): boolean {
    return departamento.trim().toUpperCase() === 'CALIDAD';
}

/**
 * Calidad reporta sobre máquinas de Urdido/Engomado: la OT sugerida se busca en
 * el programa del departamento de origen de la máquina.
 */
export function departamentoParaOrden(departamento: string, origenMaquina: string | undefined): string {
    if (!esCalidad(departamento)) return departamento;
    return origenMaquina === 'Urdido' || origenMaquina === 'Engomado' ? origenMaquina : departamento;
}

export const GRUPOS_CALIDAD = ['Tejido', 'Urdido', 'Engomado'] as const;

/**
 * Máquinas agrupadas por origen (solo Calidad, que mezcla telares con Urdido y
 * Engomado). null = lista plana.
 */
export function gruposDeMaquinas(departamento: string, maquinas: Maquina[]): Array<{ grupo: string; maquinas: Maquina[] }> | null {
    if (!esCalidad(departamento) || !maquinas.some((m) => m.DepartamentoOrigen)) return null;
    return GRUPOS_CALIDAD
        .map((grupo) => ({ grupo, maquinas: maquinas.filter((m) => m.DepartamentoOrigen === grupo) }))
        .filter((g) => g.maquinas.length > 0);
}

/**
 * Opciones de Falla y Descripción. Ambas valen el Id de CatParosFallas: así las
 * dos apuntan siempre a la MISMA fila del catálogo.
 */
export function opcionesDeFallas(fallas: Falla[]): { fallas: Array<[string, string]>; descripciones: Array<[string, string]> } {
    const opciones = { fallas: [] as Array<[string, string]>, descripciones: [] as Array<[string, string]> };
    for (const item of fallas) {
        const id = String(item.Id ?? '').trim();
        const falla = String(item.Falla ?? '').trim();
        const descripcion = String(item.Descripcion ?? '').trim();
        if (!id || !falla) continue;
        opciones.fallas.push([id, falla]);
        if (descripcion) opciones.descripciones.push([id, descripcion]);
    }
    return opciones;
}
