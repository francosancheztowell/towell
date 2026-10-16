/** Reglas del Kardex sin DOM: se prueban en tests/Js/kardex-logica.test.ts. */
import type { ArticuloKardex, Celda, DetalleCrudo, Renglon, SeccionKardex } from './tipos.ts';

/** Color fijo por almacén, el mismo en todo el módulo (flujo, tablas, encabezados). */
export const COLOR_ALMACEN: Record<string, string> = {
    'A-MP': '#0f766e',
    'A-MPBB': '#059669',
    'A-EP-URDID': '#2563eb',
    'A-JUL/URD': '#0ea5e9',
    'A-MATENG': '#92400e',
    'A-FORMENG': '#ca8a04',
    'A-JUL/TELA': '#7c3aed',
    'A-EP-TEJID': '#db2777',
    'A-PTE-JACQ': '#ea580c',
    'A-PTE-LISO': '#f59e0b',
    'A-PTE-ITEM': '#b91c1c',
    'A-CRUDO': '#334155',
};

export const colorAlmacen = (alm: string): string => COLOR_ALMACEN[alm] ?? '#64748b';

/** Almacén que representa cada sección de la hoja REs. */
export const ALMACEN_SECCION: Record<string, string> = {
    mp: 'A-MP',
    urdido: 'A-EP-URDID',
    julurd: 'A-JUL/URD',
    jultela: 'A-JUL/TELA',
    ptejq: 'A-PTE-JACQ',
    ptelisos: 'A-PTE-LISO',
    pteitema: 'A-PTE-ITEM',
    tejido: 'A-EP-TEJID',
};

const CERO = 0.005;

export const esCero = (v: number): boolean => Math.abs(v) <= CERO;

/** Encabezados del libro a texto legible: quita el sufijo Kg y corrige las erratas conocidas. */
export function limpiarEncabezado(h: string): string {
    return h
        .replace(/ /g, ' ')
        .trim()
        .replace(/\s?K[Gg]\.?$/, '')
        .replace('Exitencia', 'Existencia')
        .replace('Jaqcuard', 'Jacquard')
        .replace('Kilos Iniciales', 'Inicial')
        .replace('INICIAL', 'Inicial')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Índices de `valores` que tienen algún dato distinto de cero (las columnas vacías no se pintan). */
export function columnasVisibles(seccion: SeccionKardex): number[] {
    const filas = seccion.total ? [...seccion.renglones, seccion.total] : seccion.renglones;
    const indices: number[] = [];
    for (let i = 0; i < seccion.encabezados.length - 1; i++) {
        const conDato = filas.some((r) => {
            const v = r.valores[i];
            return typeof v === 'number' ? !esCero(v) : typeof v === 'string' && v !== '';
        });
        if (conDato) indices.push(i);
    }

    return indices;
}

const numero = (v: Celda | undefined): number => (typeof v === 'number' ? v : -1e18);

/** Orden descendente (dir 1) o ascendente (dir -1) por la columna `col`; no muta. */
export function ordenarRenglones(renglones: Renglon[], col: number, dir: 1 | -1): Renglon[] {
    return [...renglones].sort((a, b) => dir * (numero(b.valores[col]) - numero(a.valores[col])));
}

export interface GrupoTelares {
    tipo: string;
    renglones: Renglon[];
    sumas: number[];
}

const ORDEN_TIPOS = ['JACQUARD', 'ITEMA', 'SMITH', 'KM'];

/** Sección TEJIDO agrupada por tipo de telar (Jacquard, Itema, Smith, KM, otros). */
export function agruparTejidoPorTipo(seccion: SeccionKardex, telarTipo: Record<string, string>): GrupoTelares[] {
    const grupos = new Map<string, Renglon[]>();
    for (const r of seccion.renglones) {
        const tipo = telarTipo[r.etiqueta] ?? 'OTROS';
        grupos.set(tipo, [...(grupos.get(tipo) ?? []), r]);
    }
    const tipos = [...grupos.keys()].sort((a, b) => {
        const ia = ORDEN_TIPOS.indexOf(a);
        const ib = ORDEN_TIPOS.indexOf(b);
        return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
    });
    const columnas = seccion.encabezados.length - 1;

    return tipos.map((tipo) => {
        const renglones = grupos.get(tipo) ?? [];
        const sumas = Array.from({ length: columnas }, (_, i) =>
            Math.round(renglones.reduce((acc, r) => acc + (typeof r.valores[i] === 'number' ? (r.valores[i] as number) : 0), 0) * 100) / 100,
        );
        return { tipo, renglones, sumas };
    });
}

/** Cómo se desglosa cada sección por artículo: qué almacenes y qué campo compara con la etiqueta. */
const DESGLOSE: Record<string, { almacenes: string[]; por: 'th' | 'loc' | 'art' | 'todo' }> = {
    mp: { almacenes: ['A-MP', 'A-MPBB'], por: 'th' },
    urdido: { almacenes: ['A-EP-URDID'], por: 'loc' },
    julurd: { almacenes: ['A-JUL/URD'], por: 'loc' },
    jultela: { almacenes: ['A-JUL/TELA'], por: 'art' },
    ptejq: { almacenes: ['A-PTE-JACQ'], por: 'todo' },
    ptelisos: { almacenes: ['A-PTE-LISO'], por: 'todo' },
    pteitema: { almacenes: ['A-PTE-ITEM'], por: 'todo' },
    tejido: { almacenes: ['A-EP-TEJID'], por: 'loc' },
};

export type ArticuloDesglose = ArticuloKardex & { alm: string };

/** Artículos de la hoja Kdx que componen un renglón de la sección, del saldo mayor al menor. */
export function desgloseRenglon(clave: string, etiqueta: string, articulos: Record<string, ArticuloKardex[]>): ArticuloDesglose[] {
    const regla = DESGLOSE[clave];
    if (!regla) return [];
    const valor = etiqueta.toUpperCase();
    const coincide = (r: ArticuloKardex): boolean => {
        if (regla.por === 'th') return r.th.toUpperCase() === valor;
        if (regla.por === 'loc') return r.loc.toUpperCase() === valor;
        if (regla.por === 'art') return r.a.toUpperCase() === valor;
        return true;
    };

    return regla.almacenes
        .flatMap((alm) => (articulos[alm] ?? []).filter(coincide).map((r) => ({ ...r, alm })))
        .filter((r) => !esCero(Math.abs(r.ini) + Math.abs(r.ent) + Math.abs(r.sal) + Math.abs(r.saldo)))
        .sort((a, b) => Math.abs(b.saldo) - Math.abs(a.saldo));
}

export type Tono = 'ok' | 'warn' | 'serious' | 'crit' | 'na';

export interface Estado {
    tono: Tono;
    texto: string;
}

/** Estatus del cuadre de un almacén: final del ERP contra la meta del libro. */
export function estadoCuadre(finalErp: number | null, metaLibro: number | null): Estado {
    if (finalErp === null || metaLibro === null) return { tono: 'na', texto: 'Sin referencia' };
    const dif = Math.abs(finalErp - metaLibro);
    const base = Math.abs(metaLibro);
    if (dif < 1) return { tono: 'ok', texto: 'Cuadra' };
    if ((base > 1 ? dif / base : dif) < 0.05) return { tono: 'warn', texto: 'Dif. menor' };
    if (dif < base * 0.5) return { tono: 'serious', texto: 'Revisar' };

    return { tono: 'crit', texto: 'No cuadra' };
}

/** Semáforo del % de segundas (fracción en kg): > 15 % crítico, > 8 % aviso. */
export function tonoSegundas(fraccion: number): Tono {
    if (fraccion > 0.15) return 'crit';
    if (fraccion > 0.08) return 'warn';

    return 'ok';
}

export const porcentaje = (parte: number, total: number, decimales = 1): string =>
    `${(total ? (100 * parte) / total : 0).toFixed(decimales)}%`;

export interface FiltrosCrudo {
    busqueda: string;
    tamano: string;
    telar: string;
    salon: string;
}

export function filtrarCrudo(detalle: DetalleCrudo[], f: FiltrosCrudo): DetalleCrudo[] {
    const q = f.busqueda.trim().toUpperCase();

    return detalle.filter(
        (r) =>
            (!q || `${r.articulo} ${r.nombre}`.toUpperCase().includes(q)) &&
            (!f.tamano || r.tamano === f.tamano) &&
            (!f.telar || r.telar === f.telar) &&
            (!f.salon || r.salon === f.salon),
    );
}

export interface ArticuloTejido {
    articulo: string;
    nombre: string;
    tamano: string;
    piezas: number;
    kg: number;
    segPiezas: number;
    segKg: number;
    /** Telares ordenados del que más kg tejió al que menos. */
    telares: string[];
}

/** Artículo + tamaño con sus totales y en qué telares se tejió; del más pesado al más ligero. */
export function agruparArticulos(detalle: DetalleCrudo[]): ArticuloTejido[] {
    const grupos = new Map<string, ArticuloTejido & { kgTelar: Map<string, number> }>();
    for (const r of detalle) {
        const clave = `${r.articulo}|${r.tamano}`;
        const g = grupos.get(clave) ?? { articulo: r.articulo, nombre: r.nombre, tamano: r.tamano, piezas: 0, kg: 0, segPiezas: 0, segKg: 0, telares: [], kgTelar: new Map() };
        g.piezas += r.piezas;
        g.kg += r.kg;
        g.segPiezas += r.segPiezas;
        g.segKg += r.segKg;
        g.kgTelar.set(r.telar, (g.kgTelar.get(r.telar) ?? 0) + r.kg);
        grupos.set(clave, g);
    }

    return [...grupos.values()]
        .map(({ kgTelar, ...g }) => ({ ...g, telares: [...kgTelar.entries()].sort((a, b) => b[1] - a[1]).map(([t]) => t) }))
        .sort((a, b) => b.kg - a.kg);
}

export interface SalonTejido {
    salon: string;
    telares: number;
    piezas: number;
    kg: number;
    segPiezas: number;
    segKg: number;
}

export function agruparSalones(detalle: DetalleCrudo[]): SalonTejido[] {
    const grupos = new Map<string, SalonTejido & { setTelares: Set<string> }>();
    for (const r of detalle) {
        const g = grupos.get(r.salon) ?? { salon: r.salon, telares: 0, piezas: 0, kg: 0, segPiezas: 0, segKg: 0, setTelares: new Set() };
        g.piezas += r.piezas;
        g.kg += r.kg;
        g.segPiezas += r.segPiezas;
        g.segKg += r.segKg;
        g.setTelares.add(r.telar);
        grupos.set(r.salon, g);
    }

    return [...grupos.values()].map(({ setTelares, ...g }) => ({ ...g, telares: setTelares.size })).sort((a, b) => b.kg - a.kg);
}

export interface MovimientoSimulado {
    dia: number;
    referencia: string;
    tipo: 'E' | 'S';
    kg: number;
}

/** PRNG determinista (mulberry32): mismo artículo, mismos movimientos en cada carga. */
function aleatorio(semilla: number): () => number {
    let s = semilla >>> 0;
    return () => {
        s = (s + 0x6d2b79f5) >>> 0;
        let t = s;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function semillaDe(texto: string): number {
    let h = 2166136261;
    for (let i = 0; i < texto.length; i++) h = Math.imul(h ^ texto.charCodeAt(i), 16777619);
    return h;
}

/** Reparte `total` en `partes` montos positivos que suman exacto al centavo. */
function repartir(total: number, partes: number, azar: () => number): number[] {
    const pesos = Array.from({ length: partes }, () => 0.4 + azar());
    const suma = pesos.reduce((a, b) => a + b, 0);
    const montos = pesos.map((p) => Math.round((total * p * 100) / suma) / 100);
    const ajuste = Math.round((total - montos.reduce((a, b) => a + b, 0)) * 100) / 100;
    montos[montos.length - 1] = Math.round(((montos[montos.length - 1] ?? 0) + ajuste) * 100) / 100;

    return montos;
}

/**
 * Movimientos simulados de un artículo: sus entradas y salidas del libro repartidas en días
 * del mes. Placeholder hasta conectar el archivo de movimientos real.
 */
export function movimientosSimulados(r: ArticuloDesglose, diasMes = 31): MovimientoSimulado[] {
    const azar = aleatorio(semillaDe(`${r.alm}|${r.a}|${r.c}|${r.t}|${r.loc}`));
    const generar = (total: number, tipo: 'E' | 'S', referencia: string): MovimientoSimulado[] => {
        if (esCero(total)) return [];
        const partes = Math.min(8, Math.max(1, Math.round(Math.abs(total) / 900)));
        return repartir(Math.abs(total), partes, azar).map((kg) => ({
            dia: 1 + Math.floor(azar() * diasMes),
            referencia,
            tipo,
            kg: tipo === 'E' ? kg : -kg,
        }));
    };

    return [...generar(r.ent, 'E', 'Producción / transferencia'), ...generar(r.sal, 'S', 'Línea de producción')].sort((a, b) => a.dia - b.dia);
}
