/** Lógica pura del tablero Programa Atadores (sin DOM). Tests: tests/Js/atadores-programa.test.mjs. */

export interface ContextoRol {
    esTejedor: boolean;
    esSupervisor: boolean;
    telaresUsuario: string[];
    /** true si se entró con ?filtro=… (el backend ya mandó todo, sin recorte por rol). */
    filtroGlobalActivo: boolean;
}

/** ¿El estatus entra en la clave de filtro (botones de estatus del navbar)? */
export function coincideEstatus(status: string, telar: string, filtro: string, ctx: Pick<ContextoRol, 'esTejedor' | 'telaresUsuario'>): boolean {
    switch (filtro) {
        case 'creados':
        case 'activo':
            return status === 'Activo';
        case 'activo-proceso':
            return status === 'Activo' || status === 'En Proceso';
        case 'en-proceso':
            return status === 'En Proceso';
        case 'calificados':
            return status === 'Calificado';
        case 'terminados':
            // Un tejedor solo ve terminados de sus telares.
            return status === 'Terminado' && (!ctx.esTejedor || ctx.telaresUsuario.length === 0 || ctx.telaresUsuario.includes(String(telar)));
        case 'autorizados':
            return status === 'Autorizado';
        default:
            return false;
    }
}

export interface FilaTablero {
    status: string;
    telar: string;
    /** data-<columna> de la fila */
    valores: Readonly<Record<string, string>>;
}

/** Estatus en unión (cualquier filtro del modal) y columnas en intersección (menú de columna). */
export function filaVisible(fila: FilaTablero, filtros: readonly string[], columnas: Readonly<Record<string, string>>, ctx: ContextoRol): boolean {
    const porEstatus = filtros.length === 0 || filtros.some((f) => coincideEstatus(fila.status, fila.telar, f, ctx));
    if (!porEstatus) return false;

    return Object.entries(columnas).every(([col, buscado]) => (fila.valores[col] ?? '').toLowerCase().includes(buscado.toLowerCase()));
}

export const COLUMNAS_NUMERICAS: readonly string[] = ['metros', 'calibre', 'julio', 'orden'];

/** Atributo data-* por el que se ordena cada columna. */
export function atributoOrden(columna: string): string {
    if (columna === 'julio') return 'data-no-julio';
    if (columna === 'orden') return 'data-no-orden';
    if (columna === 'hr-paro') return 'data-hora-paro';

    return `data-${columna}`;
}

export function numeroOrden(valor: string | null | undefined): number {
    if (valor === '' || valor === null || valor === undefined) return -999999;
    const n = parseFloat(valor);

    return Number.isNaN(n) ? -999999 : n;
}

export function comparar(a: string, b: string, numerica: boolean, dir: 'asc' | 'desc'): number {
    const cmp = numerica ? numeroOrden(a) - numeroOrden(b) : a.localeCompare(b, undefined, { numeric: true });

    return dir === 'asc' ? cmp : -cmp;
}

/**
 * Qué hace un botón del modal de filtros: navegar (el backend tiene que mandar otro universo de filas)
 * o solo cambiar los filtros del navegador. Mismas reglas que antes:
 *  - "Autorizado" siempre va al servidor (?filtro=autorizados), y quitarlo regresa si no queda otro filtro.
 *  - Sin filtro global activo, cualquier filtro pide ?filtro=todos (sin recorte por área/cargo).
 */
export function aplicarFiltro(
    tipo: string,
    filtros: readonly string[],
    filtroGlobalActivo: boolean,
    base: string,
): { navegar: string } | { filtros: string[] } {
    if (tipo === 'autorizados') {
        if (filtros.includes('autorizados')) {
            const resto = filtros.filter((f) => f !== 'autorizados');
            return resto.length === 0 ? { navegar: base + (filtroGlobalActivo ? '?filtro=todos' : '') } : { filtros: resto };
        }
        return { navegar: `${base}?filtro=autorizados` };
    }
    if (tipo === 'todos') {
        return filtroGlobalActivo ? { filtros: [] } : { navegar: `${base}?filtro=todos` };
    }
    const nuevos = filtros.includes(tipo) ? filtros.filter((f) => f !== tipo) : [...filtros, tipo];
    if (!filtroGlobalActivo) {
        const vista = nuevos.join(',');
        return { navegar: `${base}?filtro=todos${vista ? `&vista=${encodeURIComponent(vista)}` : ''}` };
    }

    return { filtros: nuevos };
}

/** Botones del modal que se pintan activos. */
export function botonesActivos(filtros: readonly string[], ctx: Pick<ContextoRol, 'esSupervisor' | 'filtroGlobalActivo'>): string[] {
    if (filtros.length > 0) return [...filtros];
    // El supervisor sin filtro ve por defecto Activo, En Proceso y Calificados.
    return ctx.esSupervisor && !ctx.filtroGlobalActivo ? ['calificados', 'activo', 'en-proceso'] : ['todos'];
}

/** Motivo por el que una fila no se puede seleccionar para iniciar, o null. */
export function motivoNoIniciar(status: string, horaParo: string, noJulio: string, noOrden: string): string | null {
    if (status !== 'Autorizado' && !horaParo.trim()) return 'El telar debe registrar la hora de paro antes de iniciar el atado';
    if (!noJulio || !noOrden) return 'El registro seleccionado no tiene los datos necesarios (No. Julio o No. Orden)';

    return null;
}
