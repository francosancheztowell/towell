/**
 * Producción Engomado — lógica pura (sin DOM), probada con node --test en
 * tests/Js/urdeng-produccion-engomado.test.mjs. (19-01, unidad E)
 */

/** Oficial tal como vive en data-oficiales-json de la celda (lo pinta el Blade). */
export interface OficialFila {
    numero: number | string;
    nombre: string | null;
    clave: string | null;
    metros: number | string | null;
    turno: number | string | null;
}

/** Oficial tal como lo recibe guardar-oficial. */
export interface OficialGuardar {
    numero_oficial: number;
    cve_empl: string | null;
    nom_empl: string | null;
    turno: string | null;
    metros: number | null;
}

/** Captura de una fila del modal de oficiales. */
export interface CapturaOficial {
    numero: number;
    clave: string;
    nombre?: string;
    turno: string;
    metros?: string;
}

/** Campos de la fila de producción que se validan antes de marcar "Listo" o finalizar. */
export interface DatosFila {
    fecha: string;
    tieneOficial: boolean;
    turno: string;
    hInicio: string;
    hFin: string;
    julio: string;
    kgBruto: string;
    tara: string;
    kgNeto: string;
    metros: string;
    solidos: string;
    canoa1: string;
    canoa2: string;
    ubicacion: string;
}

/** data-field de la fila → columna de EngProduccionEngomado (actualizar-campos-produccion). */
export const CAMPO_PRODUCCION: Readonly<Record<string, string>> = {
    solidos: 'Solidos',
    temp_canoa1: 'Canoa1',
    temp_canoa2: 'Canoa2',
    humedad: 'Humedad',
    ubicacion: 'Ubicacion',
    roturas: 'Roturas',
};

const texto = (v: unknown): string => (v === null || v === undefined ? '' : String(v)).trim();

/** parseFloat que devuelve 0 para vacío/no numérico (como `parseFloat(x) || 0`). */
export function numeroO0(valor: unknown): number {
    const n = parseFloat(texto(valor));
    return Number.isNaN(n) ? 0 : n;
}

/** Kg. Neto = Kg. Bruto − Tara (vacíos cuentan como 0). */
export function calcularNeto(bruto: unknown, tara: unknown): number {
    return numeroO0(bruto) - numeroO0(tara);
}

/**
 * Tope de Kg. Bruto: si el valor excede el máximo devuelve el máximo con 2 decimales,
 * si no, el valor tal cual. Sin máximo (null) nunca cambia.
 */
export function limitarBruto(valor: string, max: number | null): string {
    if (max === null) return valor;
    const n = parseFloat(valor);
    return !Number.isNaN(n) && n > max ? max.toFixed(2) : valor;
}

/** Redondea a `decimales` si es numérico; si no, devuelve el texto recortado. */
export function redondear(valor: string, decimales = 2): string {
    const t = texto(valor);
    if (t === '') return '';
    const n = parseFloat(t);
    return Number.isNaN(n) ? t : n.toFixed(decimales);
}

/** Valor que se manda a actualizar-campos-produccion según la columna. */
export function valorParaCampo(campo: string, valor: string | null): string | number | null {
    if (valor === null || valor === '') return null;
    if (campo === 'Ubicacion') return valor;
    if (campo === 'Roturas') return parseInt(valor, 10);
    if (campo === 'Solidos') return parseFloat(valor).toFixed(2);
    return parseFloat(valor);
}

/** '2026-09-25' → '25/09' (texto del botón de fecha). */
export function fechaCorta(iso: string): string | null {
    const partes = iso.split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}` : null;
}

/** Hora local 'HH:MM'. */
export function horaActual(ahora: Date = new Date()): string {
    return `${String(ahora.getHours()).padStart(2, '0')}:${String(ahora.getMinutes()).padStart(2, '0')}`;
}

/** Valor a resaltar al abrir el selector de temperatura: las canoas en 0 abren en 80. */
export function valorInicialSelector(campo: string | null, actual: string): string {
    const esCanoa = campo === 'temp_canoa1' || campo === 'temp_canoa2';
    return esCanoa && (actual === '0' || actual === '') ? '80' : actual;
}

// ─── Oficiales ──────────────────────────────────────────────────────────

/** Texto de dos líneas de un oficial: clave (L1) y "nombre (Tn)" (L2). */
export function etiquetaOficial(of: Pick<OficialFila, 'clave' | 'nombre' | 'turno'>): { clave: string; nombreConTurno: string } {
    const clave = texto(of.clave);
    const nombre = texto(of.nombre);
    const turno = texto(of.turno);
    return {
        clave: clave || '-',
        nombreConTurno: (nombre || clave || '-') + (turno ? ` (T${turno})` : ''),
    };
}

/** Tooltip de la celda: "Nombre (T1), Otro (T-)"; 'Sin oficiales' si no hay. */
export function tooltipOficiales(oficiales: OficialFila[]): string {
    const partes = oficiales
        .map((of) => {
            const etiqueta = texto(of.nombre) || texto(of.clave);
            const turno = texto(of.turno) || '-';
            return etiqueta ? `${etiqueta} (T${turno})` : null;
        })
        .filter((p): p is string => p !== null);
    return oficiales.length === 0 ? 'Sin oficiales' : partes.join(', ');
}

/** Oficiales guardados → forma de data-oficiales-json. */
export function oficialesParaCelda(oficiales: OficialGuardar[]): OficialFila[] {
    return oficiales.map((o) => ({
        numero: o.numero_oficial,
        nombre: o.nom_empl || '',
        clave: o.cve_empl || '',
        metros: o.metros || '',
        turno: o.turno || '',
    }));
}

/** Suma de metros de los oficiales (columna Metros de la fila). */
export function sumaMetros(oficiales: Pick<OficialGuardar, 'metros'>[]): number {
    return oficiales.reduce((acc, o) => acc + numeroO0(o.metros), 0);
}

/** Lee data-oficiales-json sin lanzar. */
export function parsearOficiales(json: string | null | undefined): OficialFila[] {
    if (!json) return [];
    try {
        const v: unknown = JSON.parse(json);
        return Array.isArray(v) ? (v as OficialFila[]) : [];
    } catch {
        return [];
    }
}

/** Oficial 1..3 de la celda, o uno vacío. */
export function oficialNumero(oficiales: OficialFila[], numero: number): OficialFila {
    return (
        oficiales.find((o) => parseInt(String(o.numero), 10) === numero) ?? {
            numero,
            nombre: '',
            clave: '',
            metros: '',
            turno: '',
        }
    );
}

function agrupar(items: { numero: number; clave: string }[]): Map<string, number[]> {
    const vistos = new Map<string, number[]>();
    const repetidos = new Map<string, number[]>();
    for (const item of items) {
        const nums = vistos.get(item.clave);
        if (!nums) {
            vistos.set(item.clave, [item.numero]);
        } else {
            nums.push(item.numero);
            repetidos.set(item.clave, nums);
        }
    }
    return repetidos;
}

/** No. Operador repetido entre oficiales: clave → números de oficial. */
export function clavesRepetidas(filas: CapturaOficial[]): Map<string, number[]> {
    return agrupar(filas.filter((f) => f.clave.trim() !== '').map((f) => ({ numero: f.numero, clave: f.clave.trim() })));
}

/** Turno repetido entre oficiales con clave: turno → números de oficial. */
export function turnosRepetidos(filas: CapturaOficial[]): Map<string, number[]> {
    return agrupar(
        filas
            .filter((f) => f.clave.trim() !== '' && f.turno.trim() !== '')
            .map((f) => ({ numero: f.numero, clave: f.turno.trim() })),
    );
}

/** Mensajes para el usuario de los duplicados (vacío = sin duplicados). */
export function mensajesDuplicados(claves: Map<string, number[]>, turnos: Map<string, number[]>): string[] {
    const mensajes: string[] = [];
    const primeraClave = claves.entries().next();
    if (!primeraClave.done) {
        const [clave, nums] = primeraClave.value;
        mensajes.push(`El No. Operador ${clave} está repetido entre oficiales (${nums.join(', ')}).`);
    }
    const primerTurno = turnos.entries().next();
    if (!primerTurno.done) {
        const [turno, nums] = primerTurno.value;
        mensajes.push(`El Turno ${turno} está repetido entre oficiales (${nums.join(', ')}). No puede haber dos oficiales con el mismo turno.`);
    }
    return mensajes;
}

/** Captura del modal → oficiales a guardar (solo los que tienen clave o nombre). */
export function oficialesAGuardar(filas: CapturaOficial[]): OficialGuardar[] {
    return filas
        .filter((f) => f.clave.trim() !== '' || texto(f.nombre) !== '')
        .map((f) => ({
            numero_oficial: f.numero,
            cve_empl: f.clave.trim() || null,
            nom_empl: texto(f.nombre) || null,
            turno: f.turno || null,
            metros: texto(f.metros) ? parseFloat(texto(f.metros)) : null,
        }));
}

/**
 * Propagación de oficiales a las filas siguientes:
 * - con Oficial 2: en las filas siguientes se borra el Oficial 1 y el 2 pasa a ser el 1;
 * - sin Oficial 2: se copian todos con sus mismos números.
 */
export function planPropagacion(oficiales: OficialGuardar[]): { reemplazarPrimero: boolean; oficiales: OficialGuardar[] } {
    const segundo = oficiales.find((o) => o.numero_oficial === 2);
    return segundo
        ? { reemplazarPrimero: true, oficiales: [{ ...segundo, numero_oficial: 1 }] }
        : { reemplazarPrimero: false, oficiales };
}

/**
 * Metros a mandar al propagar un oficial a otra fila: guardar-oficial exige metros > 0.
 * Se conservan los metros que ya tenía esa posición en la fila destino; si no tenía, los del
 * oficial de origen; null = no hay metros válidos y la fila no se toca.
 */
export function metrosParaPropagar(existentes: unknown, origen: unknown): number | null {
    for (const v of [existentes, origen]) {
        const n = numeroO0(v);
        if (n > 0) return n;
    }
    return null;
}

/**
 * Índices de las filas que reciben la propagación: las siguientes a `actual` hasta
 * (sin incluir) la primera que ya tiene H. Inicio.
 */
export function filasAPropagar(horasInicio: string[], actual: number): number[] {
    const indices: number[] = [];
    if (actual < 0) return indices;
    for (let i = actual + 1; i < horasInicio.length; i++) {
        if (texto(horasInicio[i]) !== '') break;
        indices.push(i);
    }
    return indices;
}

// ─── Validación de filas ────────────────────────────────────────────────

/** Campos faltantes de una fila (vacío = completa). Etiquetas = las que ve el usuario. */
export function camposFaltantes(d: DatosFila, maxKgBruto: number | null): string[] {
    const faltan: string[] = [];
    const vacio = (v: string): boolean => texto(v) === '';

    if (vacio(d.fecha)) faltan.push('Fecha');
    if (!d.tieneOficial) faltan.push('Oficial');
    if (vacio(d.turno)) faltan.push('Turno');
    if (vacio(d.hInicio)) faltan.push('H. Inicio');
    if (vacio(d.hFin)) faltan.push('H. Fin');
    if (vacio(d.julio)) faltan.push('Julio');
    if (vacio(d.kgBruto)) {
        faltan.push('Kg. Bruto');
    } else {
        const bruto = parseFloat(d.kgBruto);
        if (maxKgBruto !== null && !Number.isNaN(bruto) && bruto > maxKgBruto) {
            faltan.push('Kg. Bruto (excede el máximo permitido)');
        }
    }
    if (vacio(d.tara)) faltan.push('Tara');
    if (!vacio(d.kgNeto)) {
        const neto = parseFloat(d.kgNeto);
        if (!Number.isNaN(neto) && neto < 0) faltan.push('Kg. Neto (no puede ser negativo)');
    }
    if (vacio(d.metros)) faltan.push('Metros');
    if (vacio(d.solidos)) faltan.push('Sólidos');
    if (vacio(d.canoa1) || texto(d.canoa1) === '0') faltan.push('Temp Canoa 1');
    if (vacio(d.canoa2) || texto(d.canoa2) === '0') faltan.push('Temp Canoa 2');
    if (vacio(d.ubicacion)) faltan.push('Ubicación');
    return faltan;
}

/** Selector del control a marcar en rojo por cada etiqueta de camposFaltantes. */
export const SELECTOR_CAMPO: Readonly<Record<string, string>> = {
    Fecha: 'input.input-fecha',
    Oficial: '.oficial-texto',
    Turno: 'select[data-field="turno"]',
    'H. Inicio': 'input[data-field="h_inicio"]',
    'H. Fin': 'input[data-field="h_fin"]',
    Julio: 'select[data-field="no_julio"]',
    'Kg. Bruto': 'input[data-field="kg_bruto"]',
    'Kg. Bruto (excede el máximo permitido)': 'input[data-field="kg_bruto"]',
    Tara: 'input[data-field="tara"]',
    'Kg. Neto (no puede ser negativo)': 'input[data-field="kg_neto"]',
    Metros: 'input[data-field="metros"]',
    Sólidos: 'input[data-field="solidos"]',
    Ubicación: 'select[data-field="ubicacion"]',
    'Temp Canoa 1': 'button[data-campo="temp_canoa1"]',
    'Temp Canoa 2': 'button[data-campo="temp_canoa2"]',
};

/** Renglones del aviso "Registros incompletos" al finalizar. */
export function renglonesFaltantes(faltaMerma: boolean, filas: { fila: number; campos: string[] }[]): string[] {
    const renglones: string[] = faltaMerma ? ['Merma con Goma', 'Merma sin Goma'] : [];
    for (const f of filas) renglones.push(`Fila ${f.fila}: ${f.campos.join(', ')}`);
    return renglones;
}
