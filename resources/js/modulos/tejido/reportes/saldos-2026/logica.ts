/**
 * Lógica pura de Saldos 2026 (19-02): mapa de columnas con rowspan/colspan, filtros, grupos
 * de orden compartida, orden por columna y separadores entre telares. Sin DOM: la prueba
 * tests/Js/tejido-reportes.test.mjs.
 */

export interface Span {
    colSpan: number;
    rowSpan: number;
}

export interface MapaColumnas {
    /** inicio[fila][celda] = columna visual donde empieza esa celda. */
    inicio: number[][];
    /** Número de columnas visuales (solo cuentan las celdas de una columna). */
    total: number;
}

/** Columna visual de cada celda de una tabla, respetando rowspan y colspan (como Excel). */
export function mapaColumnas(filas: Span[][]): MapaColumnas {
    const ocupado: Array<Set<number>> = [];
    const ocupar = (r: number, c: number): void => {
        (ocupado[r] ??= new Set()).add(c);
    };
    const inicio: number[][] = [];
    let total = 0;

    filas.forEach((celdas, ri) => {
        let ci = 0;
        inicio[ri] = celdas.map((celda) => {
            while (ocupado[ri]?.has(ci)) ci++;
            const cs = Math.max(1, celda.colSpan || 1);
            const rs = Math.max(1, celda.rowSpan || 1);
            const col = ci;
            if (cs === 1) total = Math.max(total, col + 1);
            for (let r = 0; r < rs; r++) for (let c = 0; c < cs; c++) ocupar(ri + r, col + c);
            ci += cs;
            return col;
        });
    });

    return { inicio, total };
}

export const VACIO = '(vacío)';

/** Clave de una celda en el filtro por valores (texto recortado; vacío → "(vacío)"). */
export function claveValor(texto: string): string {
    const t = texto.trim();
    return t === '' ? VACIO : t;
}

export interface ValorConteo {
    valor: string;
    conteo: number;
}

/** Valores únicos de una columna con su conteo: "(vacío)" primero y el resto en orden alfabético es-MX. */
export function contarValores(textos: string[]): ValorConteo[] {
    const conteos = new Map<string, number>();
    for (const t of textos) {
        const k = claveValor(t);
        conteos.set(k, (conteos.get(k) ?? 0) + 1);
    }
    return [...conteos.entries()]
        .sort(([a], [b]) => {
            if (a === VACIO) return -1;
            if (b === VACIO) return 1;
            return a.localeCompare(b, 'es', { sensitivity: 'base' });
        })
        .map(([valor, conteo]) => ({ valor, conteo }));
}

export interface Filtros {
    /** Fila de inputs: columna → texto (se busca "contiene", sin mayúsculas). */
    texto: Record<number, string>;
    /** Modal tipo Excel: columna → valores permitidos. */
    valores: Record<number, string[]>;
}

/** ¿La fila (textos por columna visual) pasa los filtros? */
export function filaPasa(textos: ReadonlyArray<string | undefined>, filtros: Filtros): boolean {
    for (const [col, q] of Object.entries(filtros.texto)) {
        const v = q.trim().toLowerCase();
        if (v && !(textos[Number(col)] ?? '').trim().toLowerCase().includes(v)) return false;
    }
    for (const [col, permitidos] of Object.entries(filtros.valores)) {
        if (!permitidos.length) continue;
        if (!permitidos.includes(claveValor(textos[Number(col)] ?? ''))) return false;
    }
    return true;
}

/** Nuevo estado del filtro por valores al aplicar el modal: todos o ninguno = sin filtro. */
export function aplicarSeleccion(
    valores: Record<number, string[]>,
    col: number,
    seleccion: string[],
    totalOpciones: number,
): Record<number, string[]> {
    const nuevo = { ...valores };
    if (seleccion.length === 0 || seleccion.length === totalOpciones) delete nuevo[col];
    else nuevo[col] = [...seleccion];
    return nuevo;
}

export interface FilaGrupo {
    /** Pertenece a una orden compartida (data-es-grupo="1"). */
    esGrupo: boolean;
    /** Es el líder del grupo o una fila suelta (data-lider="1"). */
    lider: boolean;
}

/**
 * Bloques que se mueven y se filtran juntos: cada fila que no es "no-líder de un grupo" abre
 * un bloque, y los no-líderes que la siguen se le pegan (así se ordenaba antes).
 */
export function bloques<T extends FilaGrupo>(filas: readonly T[]): T[][] {
    const out: T[][] = [];
    for (const fila of filas) {
        const cola = out[out.length - 1];
        if (fila.esGrupo && !fila.lider && cola) cola.push(fila);
        else out.push([fila]);
    }
    return out;
}

/** Visibilidad final: si cualquier fila de un grupo pasa el filtro, se ve el grupo completo. */
export function visibilidadConGrupos<T extends FilaGrupo>(filas: readonly T[], pasa: (f: T) => boolean): boolean[] {
    const indice = new Map<T, number>();
    filas.forEach((f, i) => indice.set(f, i));
    const visible = filas.map(pasa);
    for (const bloque of bloques(filas)) {
        if (!bloque[0]?.esGrupo) continue;
        const alguna = bloque.some((f) => visible[indice.get(f) ?? -1]);
        for (const f of bloque) visible[indice.get(f) ?? -1] = alguna;
    }
    return visible;
}

/** Compara dos textos de celda: numérico si ambos lo son (acepta "1,234"), si no alfabético es-MX. */
export function compararTextos(a: string, b: string, dir: 'asc' | 'desc' = 'asc'): number {
    const na = parseFloat(a.replace(/,/g, ''));
    const nb = parseFloat(b.replace(/,/g, ''));
    const cmp = !Number.isNaN(na) && !Number.isNaN(nb) ? na - nb : a.localeCompare(b, 'es', { sensitivity: 'base' });
    return dir === 'asc' ? cmp : -cmp;
}

/** Ordena por el texto del líder de cada bloque; los no-líderes viajan con él. Orden estable. */
export function ordenarPorBloques<T extends FilaGrupo>(filas: readonly T[], texto: (f: T) => string, dir: 'asc' | 'desc'): T[] {
    return bloques(filas)
        .map((b, i) => ({ b, i, t: texto(b[0] as T) }))
        .sort((x, y) => compararTextos(x.t, y.t, dir) || x.i - y.i)
        .flatMap((x) => x.b);
}

export interface FilaTelar extends FilaGrupo {
    noTelar: string;
    ordCompartida: string;
}

/** Índices de las filas que llevan un separador antes (cambia el telar y no es la misma orden compartida). */
export function separadoresAntes(filas: readonly FilaTelar[]): number[] {
    const out: number[] = [];
    for (let i = 1; i < filas.length; i++) {
        const prev = filas[i - 1] as FilaTelar;
        const fila = filas[i] as FilaTelar;
        const mismoTelar = prev.noTelar.trim() === fila.noTelar.trim();
        const ordP = prev.ordCompartida.trim();
        const mismaCompartida = prev.esGrupo && fila.esGrupo && ordP !== '' && ordP === fila.ordCompartida.trim();
        if (!mismoTelar && !mismaCompartida) out.push(i);
    }
    return out;
}

/** Posición del menú contextual dentro del viewport (mismo margen que antes: 220×340). */
export function posicionMenu(x: number, y: number, anchoVentana: number, altoVentana: number): { x: number; y: number } {
    return {
        x: x + 220 > anchoVentana ? Math.max(0, anchoVentana - 224) : x,
        y: y + 340 > altoVentana ? Math.max(0, altoVentana - 344) : y,
    };
}

/** Texto del title del botón Filtrar. */
export function tituloBotonFiltro(activos: number): string {
    return activos > 0 ? `Hay ${activos} filtro(s) activo(s). Clic para limpiar.` : 'Filtrar por columna';
}
