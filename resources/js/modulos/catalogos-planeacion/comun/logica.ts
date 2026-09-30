/**
 * Funciones puras compartidas por los catálogos de Planeación (sin DOM → tests node).
 * La configuración viene de App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista.
 */
import type { CampoCatalogo, Registro } from '../../../catalogos/catalog-base.ts';

export type ModoFiltro = 'contiene' | 'igual' | 'min' | 'max';

export interface FiltroCatalogo {
    nombre: string;
    campo: string;
    etiqueta: string;
    modo: ModoFiltro;
    control?: 'text' | 'number' | 'select';
    /** El valor del filtro se divide entre esto antes de comparar (Eficiencia: % → 0..1). */
    escala?: number;
    /** Valor de la fila cuando viene vacío (Densidad: 'Normal'). */
    porDefecto?: string;
    depende?: string;
    opcionesPor?: Record<string, Array<string | number>>;
}

export interface CampoPlaneacion extends CampoCatalogo {
    depende?: string;
    opcionesPor?: Record<string, Array<string | number>>;
    porDefecto?: string;
    sufijo?: string;
}

export interface ConfigPlaneacion {
    clave: string;
    ruta: string;
    endpoint: string;
    llave: string;
    campos: CampoPlaneacion[];
    filtros: FiltroCatalogo[];
    textos: Record<string, string>;
}

export type ValoresFiltro = Record<string, string>;

const texto = (v: unknown): string => (v === null || v === undefined ? '' : String(v));

/** ¿La fila pasa todos los filtros con valor? (vacío = sin filtro, como antes). */
export function coincide(valores: Registro, filtros: readonly FiltroCatalogo[], activos: ValoresFiltro): boolean {
    return filtros.every((f) => {
        const buscado = (activos[f.nombre] ?? '').trim();
        if (buscado === '') return true;
        const bruto = texto(valores[f.campo]).trim();
        const valor = bruto === '' && f.porDefecto !== undefined ? f.porDefecto : bruto;

        switch (f.modo) {
            case 'igual':
                return valor === buscado;
            case 'min':
            case 'max': {
                const limite = Number(buscado) / (f.escala ?? 1);
                const numero = Number(valor || 0);
                if (!Number.isFinite(limite)) return true;

                return f.modo === 'min' ? numero >= limite : numero <= limite;
            }
            default:
                return valor.toLowerCase().includes(buscado.toLowerCase());
        }
    });
}

export function filtrosActivos(activos: ValoresFiltro): number {
    return Object.values(activos).filter((v) => v.trim() !== '').length;
}

/** min > max en el mismo campo (Eficiencia/Velocidad/Peso): mensaje o null. */
export function rangoInvalido(filtros: readonly FiltroCatalogo[], activos: ValoresFiltro): string | null {
    for (const min of filtros.filter((f) => f.modo === 'min')) {
        const max = filtros.find((f) => f.modo === 'max' && f.campo === min.campo);
        const a = (activos[min.nombre] ?? '').trim();
        const b = max ? (activos[max.nombre] ?? '').trim() : '';
        if (a !== '' && b !== '' && Number(a) > Number(b)) {
            return `${min.etiqueta} no puede ser mayor que ${max?.etiqueta ?? 'el máximo'}`;
        }
    }

    return null;
}

/** data-valores de la fila (JSON de Blade); {} si no se puede leer. */
export function leerValores(json: string | undefined): Registro {
    if (!json) return {};
    try {
        const v = JSON.parse(json) as unknown;

        return v && typeof v === 'object' && !Array.isArray(v) ? (v as Registro) : {};
    } catch {
        return {};
    }
}

/** Opciones de un select que depende de otro (Telar según Salón). */
export function opcionesDependientes(
    opcionesPor: Record<string, Array<string | number>> | undefined,
    padre: string,
): string[] {
    return (opcionesPor?.[padre] ?? []).map((o) => String(o));
}

/** Primer campo obligatorio vacío → mensaje (igual que CatalogBase.validar). */
export function faltanteObligatorio(campos: readonly CampoCatalogo[], datos: Registro): string | null {
    const f = campos.find((c) => c.requerido && texto(datos[c.nombre]).trim() === '');

    return f ? `${f.etiqueta ?? f.nombre} es obligatorio` : null;
}

/** '' → null; número válido → number; lo demás → null (matriz de hilos, factor, calibre…). */
export function numeroONull(v: unknown): number | null {
    const s = texto(v).trim();
    if (s === '') return null;
    const n = Number(s);

    return Number.isFinite(n) ? n : null;
}
