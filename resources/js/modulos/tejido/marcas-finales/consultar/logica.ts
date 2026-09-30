/**
 * Consultar Marcas Finales: reglas puras (botones por estado, validación antes de finalizar,
 * edición de supervisor). Vista: resources/views/modulos/marcas-finales/marcasFinales.blade.php.
 */

export const EN_PROCESO = 'En Proceso';

/** Botones deshabilitados según selección y estado (true = deshabilitado). */
export function botonesDeshabilitados(folio: string | null, status: string | null): {
    editar: boolean;
    finalizar: boolean;
    visualizar: boolean;
    supervisor: boolean;
} {
    const hay = folio !== null && folio !== '';
    const enProceso = status === EN_PROCESO;
    return { editar: !hay || !enProceso, finalizar: !hay || !enProceso, visualizar: !hay, supervisor: !hay };
}

/** Vacío, no numérico o ≤ 0. */
export function esVacioOCero(v: unknown): boolean {
    if (v === null || v === undefined) return true;
    if (typeof v === 'string' && v.trim() === '') return true;
    const n = Number(v);
    return Number.isNaN(n) || n <= 0;
}

/** Columnas que se revisan antes de finalizar, con su etiqueta. */
export const CAMPOS_FINALIZAR: readonly (readonly [string, string])[] = [
    ['Eficiencia', '% Efi'],
    ['Marcas', 'Marcas'],
    ['Horas', 'Horas'],
    ['Trama', 'Trama'],
    ['Pie', 'Pie'],
    ['Rizo', 'Rizo'],
    ['Otros', 'Otros'],
];

export interface LineaIncompleta {
    telar: string;
    campos: string[];
}

/** Telares con algún campo vacío o en cero. */
export function lineasIncompletas(lineas: readonly Record<string, unknown>[]): LineaIncompleta[] {
    const salida: LineaIncompleta[] = [];
    lineas.forEach((l, i) => {
        const campos = CAMPOS_FINALIZAR.filter(([clave]) => esVacioOCero(l[clave])).map(([, etiqueta]) => etiqueta);
        if (campos.length) salida.push({ telar: String(l.NoTelarId || `Línea ${i + 1}`), campos });
    });
    return salida;
}

/** Texto de la confirmación cuando hay campos vacíos. */
export function resumenIncompletas(incompletas: readonly LineaIncompleta[]): string {
    const total = incompletas.reduce((acc, item) => acc + item.campos.length, 0);
    return `Hay ${total} campo(s) vacío(s) o en cero en ${incompletas.length} telar(es). ¿Deseas continuar?`;
}

/** Fecha del JSON (Y-m-d o ISO) → valor de <input type="date">. */
export function fechaIso(valor: unknown): string {
    const m = /^(\d{4}-\d{2}-\d{2})/.exec(String(valor ?? ''));
    return m ? m[1]! : '';
}

export interface DatosRegistro {
    Date: string;
    Turno: string;
    numero_empleado: string;
    nombreEmpl: string;
    Status: string;
}

/** Validación del modal de supervisor: null si está bien, o el mensaje. */
export function validarRegistro(d: DatosRegistro): string | null {
    return !d.Date || !d.Turno ? 'Fecha y Turno son obligatorios.' : null;
}
