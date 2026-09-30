/**
 * Matriz de Calibres: reglas puras (antes public/js/catalogs/MatrizCalibresCatalog.js).
 * Tipos = App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::TIPOS_MATRIZ_CALIBRES
 * (= MatrizCalibreClave::TIPOS; BARRA1..4 = Karl Mayer, una clave por barra).
 */
import type { Registro } from '../../../catalogos/catalog-base.ts';

export const TIPOS_CON_CUENTA = ['RIZO', 'PIE', 'BARRA1', 'BARRA2', 'BARRA3', 'BARRA4'];
const REQUERIDOS = ['Tipo', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId'];

const t = (v: unknown): string => String(v ?? '').trim();

export function validarCalibre(datos: Registro): string | null {
    const faltante = REQUERIDOS.find((c) => t(datos[c]) === '');
    if (faltante) return `${faltante} es obligatorio`;
    const tipo = t(datos.Tipo);
    const conCalibre = t(datos.Calibre) !== '';
    const conFibra = t(datos.FibraId) !== '';
    if (tipo === 'PIE' && !conCalibre && !conFibra) return 'Para Pie debe existir al menos Fibra o Calibre';
    if (tipo !== 'PIE' && (!conCalibre || !conFibra)) return `Fibra y Calibre son obligatorios para ${tipo}`;
    if (TIPOS_CON_CUENTA.includes(tipo) && t(datos.Cuenta) === '') return `Cuenta es obligatoria para ${tipo}`;

    return null;
}

/** Mayúsculas, TRAMA sin cuenta y calibre redondeado a un decimal (null si no es > 0). */
export function procesarCalibre(datos: Registro): Registro {
    const tipo = t(datos.Tipo).toUpperCase();
    const calibre = Number.parseFloat(t(datos.Calibre));

    return {
        ...datos,
        Tipo: tipo,
        FibraId: t(datos.FibraId).toUpperCase() || null,
        Cuenta: tipo === 'TRAMA' ? null : t(datos.Cuenta).toUpperCase(),
        Calibre: Number.isFinite(calibre) && calibre > 0 ? Math.round(calibre * 10) / 10 : null,
    };
}

/** Estado de los controles según el tipo: TRAMA no lleva cuenta; PIE no exige calibre/fibra. */
export function reglasPorTipo(tipo: string): { cuentaHabilitada: boolean; calibreRequerido: boolean } {
    return { cuentaHabilitada: tipo !== 'TRAMA', calibreRequerido: tipo !== 'PIE' };
}

/** Búsqueda local de la barra: tipo exacto + texto en cualquier columna. */
export function coincideCalibre(v: Registro, termino: string, tipo: string): boolean {
    if (tipo !== '' && t(v.Tipo) !== tipo) return false;
    if (termino === '') return true;
    const pajar = ['Id', 'Tipo', 'Calibre', 'FibraId', 'Cuenta', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId']
        .map((c) => t(v[c]).toLowerCase())
        .join(' ');

    return pajar.includes(termino.toLowerCase());
}

/** Un tipo que no está en la lista se conserva al editar (si no, el select quedaba en "Seleccione…"). */
export function tiposConActual(tipos: string[], actual: unknown): string[] {
    const tipo = t(actual).toUpperCase();

    return tipo !== '' && !tipos.includes(tipo) ? [...tipos, tipo] : tipos;
}
