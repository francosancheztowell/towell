/**
 * Captura de Marcas Finales (nuevo / editar / visualizar): reglas puras, sin DOM.
 * Vista: resources/views/modulos/marcas-finales/nuevo-marcas.blade.php.
 */

export type TipoCampo = 'efi' | 'marcas' | 'horas' | 'trama' | 'pie' | 'rizo' | 'otros';

/** Rango permitido por columna (el mismo `max` que pinta el Blade). */
export const RANGOS: Readonly<Record<TipoCampo, readonly [number, number]>> = {
    marcas: [0, 250],
    horas: [0, 9999],
    efi: [0, 100],
    trama: [0, 100],
    pie: [0, 100],
    rizo: [0, 100],
    otros: [0, 100],
};

/** Orden en que se arma cada línea al guardar. */
export const CAMPOS: readonly TipoCampo[] = ['efi', 'trama', 'pie', 'rizo', 'otros', 'marcas', 'horas'];

/** Marcas sugeridas al entrar a una celda de Marcas en cero. */
export const MARCAS_SUGERIDAS = 100;

export function rango(tipo: string): readonly [number, number] {
    return RANGOS[tipo as TipoCampo] ?? [0, 100];
}

/** Al escribir: si se pasa del máximo se corta ahí; si no, se deja lo que escribió (null). */
export function limitarAlEscribir(tipo: string, texto: string): string | null {
    const [, max] = rango(tipo);
    const val = parseFloat(texto);
    return !Number.isNaN(val) && val > max ? String(max) : null;
}

/** Al salir de la celda: vacío o basura → 0, y el valor dentro del rango. */
export function normalizarAlSalir(tipo: string, texto: string): string {
    const [min, max] = rango(tipo);
    const val = parseFloat(texto);
    if (Number.isNaN(val)) return '0';
    if (val < min) return String(min);
    if (val > max) return String(max);
    return texto;
}

/**
 * Al entrar a una celda en cero: % Efi propone el STD del telar (si lo hay) y Marcas propone 100.
 * Devuelve null si no hay que cambiar el valor.
 */
export function sugerencia(tipo: string, texto: string, recomendado: string | undefined): string | null {
    if ((parseFloat(texto) || 0) !== 0) return null;
    if (tipo === 'efi') {
        const rec = parseInt(recomendado ?? '0', 10);
        return rec > 0 ? String(rec) : null;
    }
    if (tipo === 'marcas') return String(MARCAS_SUGERIDAS);
    return null;
}

/** Nombre del campo en el payload de store: efi → PorcentajeEfi, marcas → Marcas… */
export function claveServidor(tipo: TipoCampo): string {
    return tipo === 'efi' ? 'PorcentajeEfi' : tipo.charAt(0).toUpperCase() + tipo.slice(1);
}

export type LineaPayload = { NoTelarId: string } & Record<string, number | string>;

/** Una línea del payload: cada campo numérico (vacío o inválido → 0). */
export function construirLinea(telar: string, valores: Partial<Record<TipoCampo, string>>): LineaPayload {
    const linea: LineaPayload = { NoTelarId: telar };
    for (const tipo of CAMPOS) linea[claveServidor(tipo)] = parseFloat(valores[tipo] ?? '') || 0;
    return linea;
}

/**
 * % Efi guardado → valor de la celda. Se guarda como entero 0-100; los folios viejos lo tienen
 * como fracción (0.82). Antes esos salían en 0 y al guardar se perdía el dato.
 */
export function efiDesdeBd(valor: unknown): number {
    const n = Number(valor ?? 0);
    if (!Number.isFinite(n)) return 0;
    if (n > 0 && n < 1) return Math.round(n * 100);
    return Math.trunc(n);
}

/** Línea guardada (show) → valores de celda por tipo. */
export function valoresDesdeLinea(l: Record<string, unknown>): Record<TipoCampo, string> {
    const v = (x: unknown): string => String(x ?? 0);
    return {
        efi: String(efiDesdeBd(l.Eficiencia)),
        marcas: v(l.Marcas),
        horas: v(l.Horas),
        trama: v(l.Trama),
        pie: v(l.Pie),
        rizo: v(l.Rizo),
        otros: v(l.Otros),
    };
}

/** Cuerpo de error de generar-folio. */
export interface ErrorFolio {
    message?: string;
    folio_existente?: string;
    creado_por_otro?: boolean;
}

export type ResultadoFolio =
    | { tipo: 'en-creacion'; folio: string }
    | { tipo: 'en-proceso'; folio: string; mensaje: string }
    | { tipo: 'duplicado'; mensaje: string }
    | { tipo: 'invalido'; mensaje: string }
    | { tipo: 'error' };

/** Qué hacer con una respuesta de error de generar-folio (mismas ramas que el script anterior). */
export function clasificarErrorFolio(status: number, data: ErrorFolio | null | undefined): ResultadoFolio {
    const d = data ?? {};
    if (status === 400 && d.folio_existente) {
        return d.creado_por_otro
            ? { tipo: 'en-creacion', folio: d.folio_existente }
            : { tipo: 'en-proceso', folio: d.folio_existente, mensaje: d.message ?? '' };
    }
    if (status === 409 && d.folio_existente) {
        return { tipo: 'duplicado', mensaje: d.message || 'Ya existe un folio con la misma fecha y turno.' };
    }
    if (status === 422) return { tipo: 'invalido', mensaje: d.message || 'Debe seleccionar fecha y turno.' };
    return { tipo: 'error' };
}
