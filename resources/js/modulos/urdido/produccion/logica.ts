/**
 * Lógica pura de Producción Urdido (19-01). Sin DOM: se prueba con node
 * (tests/Js/urdeng-produccion-urdido.test.mjs).
 */

export type Valor = string | number | null | undefined;

// ─── Pesos ──────────────────────────────────────────────────────────

/**
 * Kg. Bruto, Tara y Kg. Neto son pesos: siempre 2 decimales. Las columnas son `real` en
 * SQL Server, así que 287.4 vuelve como 287.39999; sin esto el artefacto se ve en pantalla
 * y se arrastra a la resta del neto.
 */
export function peso(v: Valor): string {
    const n = parseFloat(String(v ?? ''));
    return Number.isNaN(n) ? '' : n.toFixed(2);
}

export function pesoNum(v: Valor): number {
    const n = parseFloat(String(v ?? ''));
    return Number.isNaN(n) ? 0 : Math.round(n * 100) / 100;
}

/** Kg. Neto = Bruto − Tara, a 2 decimales (vacío cuenta como 0). */
export function netoDe(bruto: Valor, tara: Valor): number {
    return Math.round((pesoNum(bruto) - pesoNum(tara)) * 100) / 100;
}

/** Neto negativo o por encima del máximo permitido (700 kg en Urdido; null = sin tope). */
export function netoFueraDeRango(neto: number, max: number | null): boolean {
    if (neto < 0) return true;
    return max !== null && neto > max;
}

/** Bruto máximo para no pasar el neto permitido con la tara dada. */
export function brutoMaximo(tara: number, max: number): number {
    return max + tara;
}

/**
 * El bruto tecleado excede el máximo. Nunca se reescribe lo que tecleó el operador: un 7500
 * corregido a 700 en silencio queda como un peso plausible pero falso. Solo se marca.
 */
export function brutoExcede(bruto: Valor, tara: number, max: number | null): boolean {
    if (max === null) return false;
    const n = parseFloat(String(bruto ?? ''));
    return !Number.isNaN(n) && n > brutoMaximo(tara, max);
}

// ─── Fechas y horas ─────────────────────────────────────────────────

/** 'YYYY-MM-DD' → 'DD/MM' (lo que muestra el botón de fecha); null si no tiene esa forma. */
export function fechaCorta(iso: string): string | null {
    const partes = iso.split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}` : null;
}

/** Hora local 'HH:MM'. */
export function horaActual(ahora: Date = new Date()): string {
    return `${String(ahora.getHours()).padStart(2, '0')}:${String(ahora.getMinutes()).padStart(2, '0')}`;
}

// ─── Roturas y campos por fila ──────────────────────────────────────

/** data-field del display de roturas → columna de UrdProduccionUrdido. */
export const CAMPO_ROTURA: Readonly<Record<string, string>> = {
    hilat: 'Hilatura',
    maq: 'Maquina',
    operac: 'Operac',
    transf: 'Transf',
};

/** Campos que se pueden editar con la fila marcada como lista (parcialmente finalizada). */
export const CAMPOS_EDITABLES_EN_PARCIAL: readonly string[] = ['Vueltas', 'Diametro'];

/** Texto de la celda de oficiales: vacío o 'Sin oficiales' = no hay oficial. */
export function tieneOficial(texto: string | null | undefined): boolean {
    const t = (texto ?? '').trim();
    return t !== '' && t !== 'Sin oficiales';
}

/** Valores de una fila que se revisan antes de marcarla lista o de finalizar la orden. */
export interface CamposFila {
    fecha: string;
    oficial: string;
    hInicio: string;
    hFin: string;
    noJulio: string;
    kgBruto: string;
    tara: string;
    kgNeto: string;
    metros: string;
    vueltas?: string;
    diametro?: string;
}

export const NETO_NEGATIVO = 'Kg. Neto (no puede ser negativo)';
export const NETO_EXCEDE = 'Kg. Neto (excede el máximo permitido)';

/** Nombre del campo faltante → selector del control a marcar en rojo. */
export const SELECTOR_CAMPO: Readonly<Record<string, string>> = {
    Fecha: 'input.input-fecha',
    Oficial: '.oficial-texto',
    'H. Inicio': 'input[data-field="h_inicio"]',
    'H. Fin': 'input[data-field="h_fin"]',
    'No. Julio': 'select[data-field="no_julio"]',
    'Kg. Bruto': 'input[data-field="kg_bruto"]',
    Tara: 'input[data-field="tara"]',
    [NETO_NEGATIVO]: 'input[data-field="kg_neto"]',
    [NETO_EXCEDE]: 'input[data-field="kg_neto"]',
    Metros: 'input[data-field="metros"]',
    Vueltas: 'input[data-field="vueltas"]',
    Diámetro: 'input[data-field="diametro"]',
};

/** Campos requeridos que faltan en la fila (mismo orden y textos que la vista anterior). */
export function camposFaltantes(f: CamposFila, esKarlMayer: boolean, max: number | null): string[] {
    const faltan: string[] = [];
    const vacio = (v: string | undefined): boolean => !v || v.trim() === '';

    if (!f.fecha) faltan.push('Fecha');
    if (!tieneOficial(f.oficial)) faltan.push('Oficial');
    if (!f.hInicio) faltan.push('H. Inicio');
    if (!f.hFin) faltan.push('H. Fin');
    if (!f.noJulio) faltan.push('No. Julio');
    if (vacio(f.kgBruto)) faltan.push('Kg. Bruto');
    if (vacio(f.tara)) faltan.push('Tara');
    if (f.kgNeto) {
        const neto = parseFloat(f.kgNeto);
        if (!Number.isNaN(neto) && neto < 0) faltan.push(NETO_NEGATIVO);
        if (max !== null && !Number.isNaN(neto) && neto > max) faltan.push(NETO_EXCEDE);
    }
    if (vacio(f.metros)) faltan.push('Metros');
    if (esKarlMayer) {
        if (vacio(f.vueltas)) faltan.push('Vueltas');
        if (vacio(f.diametro)) faltan.push('Diámetro');
    }
    return faltan;
}

// ─── Julios: un julio no puede estar en dos filas ───────────────────

export interface JulioCatalogo {
    julio: string | number;
    tara?: string | number | null;
}

export interface OpcionJulio {
    valor: string;
    tara: string;
}

/** Julios elegidos en las OTRAS filas (valores no vacíos, excepto el de la fila `indice`). */
export function juliosOcupados(valores: readonly string[], indice: number): Set<string> {
    const ocupados = new Set<string>();
    valores.forEach((v, i) => {
        if (i !== indice && v) ocupados.add(v);
    });
    return ocupados;
}

/** Opciones del select de una fila: el catálogo sin los julios ocupados en otras filas. */
export function opcionesJulio(catalogo: readonly JulioCatalogo[], ocupados: Set<string>): OpcionJulio[] {
    return catalogo
        .filter((item) => !ocupados.has(String(item.julio)))
        .map((item) => ({ valor: String(item.julio), tara: String(item.tara || '0') }));
}

// ─── Oficiales ──────────────────────────────────────────────────────

/** Oficial tal como lo guarda la fila en data-oficiales-json. */
export interface OficialFila {
    numero: number | string;
    nombre: string | null;
    clave: string | null;
    metros: number | string | null;
    turno: number | string | null;
}

/** Oficial tal como lo recibe guardar-oficial. */
export interface OficialPayload {
    numero_oficial: number;
    cve_empl: string | null;
    nom_empl: string | null;
    turno: string | null;
    metros: number | null;
}

/** Lee data-oficiales-json; cualquier cosa rara → []. */
export function leerOficiales(json: string | null | undefined): OficialFila[] {
    if (!json) return [];
    try {
        const datos: unknown = JSON.parse(json);
        return Array.isArray(datos) ? (datos as OficialFila[]) : [];
    } catch {
        return [];
    }
}

/** Oficial de la posición `numero` (1..3) o uno vacío. */
export function oficialEn(oficiales: readonly OficialFila[], numero: number): OficialFila {
    return (
        oficiales.find((o) => Number(o.numero) === numero) ?? { numero, nombre: '', clave: '', metros: '', turno: '' }
    );
}

/** Payload guardado → forma de data-oficiales-json. */
export function aOficialesFila(oficiales: readonly OficialPayload[]): OficialFila[] {
    return oficiales.map((o) => ({
        numero: o.numero_oficial,
        nombre: o.nom_empl || null,
        clave: o.cve_empl || null,
        metros: o.metros || null,
        turno: o.turno || null,
    }));
}

/** Texto compacto de la celda: claves en el primer renglón y nombre (T turno) por oficial. */
export function resumenOficiales(oficiales: readonly OficialFila[]): {
    codigos: string;
    nombres: { nombre: string; turno: string }[];
} {
    const codigos = oficiales.map((o) => String(o.clave ?? '').trim()).filter(Boolean);
    const nombres = oficiales
        .map((o) => ({
            nombre: String(o.nombre ?? '').trim(),
            turno: o.turno !== null && o.turno !== undefined && o.turno !== '' ? String(o.turno) : '-',
        }))
        .filter((o) => o.nombre !== '');
    return { codigos: codigos.length ? codigos.join(', ') : '-', nombres };
}

/** Suma de metros de los oficiales ('' si no hay: así se mostraba en la columna Metros). */
export function sumaMetros(oficiales: readonly { metros: Valor }[]): number | '' {
    const suma = oficiales.reduce((acc, o) => acc + (parseFloat(String(o.metros ?? '')) || 0), 0);
    return suma > 0 ? suma : '';
}

/** Posiciones con valor repetido: valor → números de oficial (solo los que se repiten). */
export function repetidos(items: readonly { numero: number; valor: string }[]): Map<string, number[]> {
    const vistos = new Map<string, number[]>();
    const rep = new Map<string, number[]>();
    for (const { numero, valor } of items) {
        if (!valor) continue;
        const nums = vistos.get(valor);
        if (!nums) {
            vistos.set(valor, [numero]);
        } else {
            nums.push(numero);
            rep.set(valor, nums);
        }
    }
    return rep;
}

/** Oficiales sin metros > 0 (el servidor los rechaza: metros required|gt:0). */
export function oficialesSinMetros(oficiales: readonly OficialPayload[]): number[] {
    return oficiales.filter((o) => !(o.metros !== null && o.metros > 0)).map((o) => o.numero_oficial);
}

/** Metros > 0 de la posición `numero` en una fila, o null. */
export function metrosDe(oficiales: readonly OficialFila[], numero: number): number | null {
    const m = parseFloat(String(oficialEn(oficiales, numero).metros ?? ''));
    return m > 0 ? m : null;
}

/**
 * Cómo se propagan los oficiales a las filas siguientes (sin H. Inicio):
 *  - con Oficial 2: el 2 pasa a ser el 1 (cambio de turno) → 'segundo'.
 *  - sin Oficial 2: se copian todos con su mismo número → 'todos'.
 */
export function modoPropagacion(oficiales: readonly OficialPayload[]): { modo: 'segundo'; segundo: OficialPayload } | { modo: 'todos' } {
    const segundo = oficiales.find((o) => o.numero_oficial === 2);
    return segundo ? { modo: 'segundo', segundo } : { modo: 'todos' };
}

/** Motivo de rechazo de guardar-oficial: errores 422 aplanados, o `error`, o genérico. */
export function motivoRechazo(data: unknown): string {
    const cuerpo = (data ?? {}) as { errors?: Record<string, string[] | string>; error?: string; message?: string };
    if (cuerpo.errors) return Object.values(cuerpo.errors).flat().join(' ');
    return cuerpo.error || cuerpo.message || 'Error desconocido';
}
