/**
 * Libro del Kardex que sirve /kardex/datos/libro (App\Services\Kardex\KardexLibroBuilder) y cómo se
 * mezcla con los datos simulados: lo que viene del libro reemplaza; lo demás queda marcado `simulado`.
 */
import type { DiagramaFlujo } from '../../componentes/diagrama-flujo/index.ts';
import { formatNumber } from '../../utils/format.ts';
import type { ArticuloKardex, DatosKardex, SeccionKardex } from './tipos.ts';

export interface Periodo {
    anio: number;
    mes: number;
}

export interface SaldoLibro {
    inicial: number;
    final: number;
}

export interface LibroKardex {
    periodo: Periodo & { nombre: string; corte: string };
    kpis: { julUrd: number; julTela: number; crudoKg: number; crudoPz: number };
    secciones: SeccionKardex[];
    articulos: Record<string, ArticuloKardex[]>;
    crudo: { referenciaKdx: Record<string, number> };
    flujo: { nodos: Record<string, SaldoLibro>; aristas: Record<string, number>; crudoKg: number };
}

/** Último día del mes anterior en dd/mm/aaaa: el corte del inventario inicial. */
export function corteInicial(p: Periodo): string {
    const d = new Date(p.anio, p.mes - 1, 0);
    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

export function aplicarLibro(base: DatosKardex, libro: LibroKardex): DatosKardex {
    const delLibro = new Map(libro.secciones.map((s) => [s.clave, s]));

    return {
        ...base,
        meta: { ...base.meta, mes: libro.periodo.nombre, corte: libro.periodo.corte, corte_inicial: corteInicial(libro.periodo) },
        kpis: { ...base.kpis, ...libro.kpis },
        secciones: base.secciones.map((s) => delLibro.get(s.clave) ?? { ...s, simulado: true }),
        articulos: { ...base.articulos, ...libro.articulos },
        crudo: { ...base.crudo, referenciaKdx: { ...base.crudo.referenciaKdx, ...libro.crudo.referenciaKdx } },
        libro: libro.flujo,
    };
}

const kg0 = (n: number): string => formatNumber(n, 0);

/** Etiquetas de flecha que llevan un sufijo además de los kg. */
const SUFIJO: Record<string, string> = { 'julurd-eptejid': ' (KM)' };

/** El diagrama con las cajas y flechas que da el libro; las demás quedan con `datos.simulado`. */
export function flujoConLibro(diagrama: DiagramaFlujo, flujo: LibroKardex['flujo']): DiagramaFlujo {
    return {
        ...diagrama,
        nodos: diagrama.nodos.map((n) => {
            const saldo = flujo.nodos[n.id];
            const datos = (n.datos ?? {}) as Record<string, unknown>;
            if (saldo) return { ...n, valor: `${kg0(saldo.inicial)} → ${kg0(saldo.final)} kg`, datos: { ...datos, inicial: saldo.inicial, final: saldo.final } };
            if (n.id === 'A-CRUDO') return { ...n, valor: `+ ${formatNumber(flujo.crudoKg, 2)} kg tejidos en telares` };
            return { ...n, datos: { ...datos, simulado: true } };
        }),
        aristas: diagrama.aristas.map((a) => {
            const kg = flujo.aristas[a.id];
            if (kg === undefined) return { ...a, datos: { ...((a.datos ?? {}) as Record<string, unknown>), simulado: true } };
            return { ...a, etiqueta: `${kg0(kg)}${SUFIJO[a.id] ?? ''}`, datos: { desglose: [{ concepto: 'Libro (hoja Kdx)', kg }] } };
        }),
    };
}

export const esSimulado = (datos: unknown): boolean => typeof datos === 'object' && datos !== null && (datos as { simulado?: unknown }).simulado === true;
