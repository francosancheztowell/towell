/**
 * Creación de órdenes (Programa Urd-Eng): lógica pura, sin DOM. Se prueba con node
 * (tests/Js/programa-urd-eng-creacion-ordenes.test.mjs).
 *
 * Caracteriza lo que hacía public/js/modulos/programa_urd_eng/creacion-ordenes.js (BUG-033):
 * agrupar telares, resolver destino, filas de materiales, validaciones y el payload de
 * POST programa.urd.eng.crear.ordenes, que debe salir IDÉNTICO al del JS viejo.
 */
import { formatDate, formatNumber } from '../../../utils/format.ts';
import { aNumero, materialAPayload } from '../comun/inventario-materiales.ts';
import type { MaterialInventario, MaterialPayload } from '../comun/inventario-materiales.ts';

/* =================== Tipos =================== */

/**
 * Telar tal como llega por `?telares=` (TelarRequerimiento de comun/contrato-flujo.ts, que
 * aquí se lee con tipos laxos: el servidor lo re-decodifica y un número puede venir como texto).
 */
export interface TelarCrudo {
    no_telar?: string | number | null;
    fecha_req?: string | null;
    cuenta?: string | number | null;
    calibre?: string | number | null;
    hilo?: string | null;
    tamano?: string | null;
    urdido?: string | null;
    tipo?: string | null;
    destino?: string | null;
    tipo_atado?: string | null;
    metros?: string | number | null;
    kilos?: string | number | null;
    agrupar?: boolean | null;
    maquina_urd?: string | null;
    maquinaId?: string | null;
}

export interface Telar extends Omit<TelarCrudo, 'tipo' | 'hilo' | 'tamano' | 'calibre' | 'metros' | 'kilos' | 'agrupar'> {
    tipo: string;
    hilo: string | null;
    tamano: string | null;
    calibre: number | null;
    metros: number;
    kilos: number;
    agrupar: boolean;
}

export interface Grupo {
    telares: Telar[];
    telaresStr: string | number;
    cuenta: string | number;
    /** Agrupados: número o null; individuales: número o ''. */
    calibre: number | null | '';
    hilo: string;
    tamano: string;
    tipo: string;
    urdido: string;
    tipoAtado: string;
    destino: string;
    fechaReq: string;
    metros: number;
    kilos: number;
    maquinaId: string;
}

export interface MaterialUrdido {
    ItemId?: string | null;
    ConfigId?: string | null;
    BomQty?: number | string | null;
    [clave: string]: unknown;
}

export type MaterialEngomado = MaterialInventario;

export interface FilaConstruccion {
    julios: string;
    hilos: string;
    observaciones: string;
}

/** Valores crudos de los controles de "Datos de Engomado" (Tabla 5). */
export interface DatosEngomadoCrudos {
    nucleo: string;
    noTelas: string;
    anchoBalonas: string;
    metrajeTelas: string;
    cuendeadosMin: string;
    maquinaEngomado: string;
    lMatEngomado: string;
    bomFormula: string;
    observaciones: string;
}

export type CampoEngomado = keyof DatosEngomadoCrudos;

export interface GrupoPayload {
    telaresStr: string | number;
    noTelarId: string | number;
    tipo: string;
    cuenta: string | number;
    calibre: number | null;
    fechaReq: string;
    fibra: string;
    hilo: string;
    tamano: string;
    inventSizeId: string;
    metros: number;
    kilos: number;
    noProduccion: string;
    salonTejidoId: string;
    destino: string;
    maquinaId: string;
    bomId: string;
    tipoAtado: string;
    status: 'Programado';
}

export type MaterialEngomadoPayload = MaterialPayload & { status: 'Programado' };

export interface PayloadCrearOrdenes {
    grupo: GrupoPayload;
    materialesEngomado: MaterialEngomadoPayload[];
    construccionUrdido: FilaConstruccion[];
    datosEngomado: DatosEngomadoCrudos;
    fechaRequerimiento: string;
}

/* =================== Utilidades =================== */

const esNulo = (v: unknown): v is null | undefined => v === null || v === undefined;
export const enBlanco = (v: unknown): boolean => esNulo(v) || String(v).trim() === '';

/** Número tolerante a comas ("1,234.5"); `def` si no es número. */
export function aNum(v: unknown, def = 0): number {
    return aNumero(v, def);
}

/** Número es-MX con decimales fijos (como el fmtNumber viejo); no numérico → ''. */
export function numero(v: unknown, decimales = 2): string {
    const n = aNumero(v, null);
    return n === null ? '' : formatNumber(n, decimales);
}

/** Fecha dd/mm/aaaa tomando solo la parte de fecha ("2025-01-15 14:30:00" → 15/01/2025). */
export function fechaCorta(valor: unknown): string {
    if (enBlanco(valor)) return '';
    let parte = String(valor).trim();
    if (parte.includes(' ')) parte = parte.split(' ')[0] ?? '';
    if (parte.includes('T')) parte = parte.split('T')[0] ?? '';
    return /^\d{4}-\d{2}-\d{2}$/.test(parte) ? formatDate(parte) || parte : parte;
}

export function normalizarTipo(tipo: unknown): string {
    const up = String(tipo || '').toUpperCase().trim();
    if (up === 'RIZO') return 'Rizo';
    if (up === 'PIE') return 'Pie';
    return tipo ? String(tipo) : '';
}

/* =================== Telares y grupos =================== */

export function normalizarTelares(arr: readonly TelarCrudo[] | null | undefined): Telar[] {
    return (arr || []).map((t) => ({
        ...t,
        tipo: normalizarTipo(t.tipo) || 'Rizo',
        hilo: !enBlanco(t.hilo) ? String(t.hilo).trim() : null,
        tamano: !enBlanco(t.tamano) ? String(t.tamano).trim() : null,
        calibre: !enBlanco(t.calibre) ? parseFloat(String(t.calibre)) : null,
        metros: aNum(t.metros, 0),
        kilos: aNum(t.kilos, 0),
        agrupar: !!t.agrupar,
    }));
}

/**
 * Los telares con `agrupar` se juntan por cuenta|tipo|urdido|tipo de atado (el hilo y el
 * calibre no son clave); los demás quedan solos, al final.
 */
export function agruparTelares(telares: readonly Telar[]): Grupo[] {
    const grupos = new Map<string, Omit<Grupo, 'telaresStr'>>();
    const solos: Telar[] = [];

    for (const telar of telares) {
        if (!telar.agrupar) {
            solos.push(telar);
            continue;
        }
        const tipo = normalizarTipo(telar.tipo) || 'Rizo';
        const cuenta = String(telar.cuenta || '').trim();
        const urdido = String(telar.urdido || '').trim();
        const tipoAtado = String(telar.tipo_atado || 'Normal').trim();
        const clave = `${cuenta}|${tipo.toUpperCase()}|${urdido}|${tipoAtado}`;

        let g = grupos.get(clave);
        if (!g) {
            g = {
                telares: [],
                cuenta,
                calibre: !enBlanco(telar.calibre) ? parseFloat(String(telar.calibre)) : null,
                hilo: !enBlanco(telar.hilo) ? String(telar.hilo).trim() : '',
                tamano: !enBlanco(telar.tamano) ? String(telar.tamano).trim() : '',
                tipo,
                urdido,
                tipoAtado,
                destino: String(telar.destino || '').trim(),
                fechaReq: telar.fecha_req || '',
                metros: 0,
                kilos: 0,
                maquinaId: telar.urdido || telar.maquina_urd || telar.maquinaId || '',
            };
            grupos.set(clave, g);
        }
        g.telares.push(telar);
        g.metros += telar.metros || 0;
        g.kilos += telar.kilos || 0;
    }

    const salida: Grupo[] = [...grupos.values()].map((g) => ({
        ...g,
        destino: g.telares.length > 1 ? '' : g.destino,
        telaresStr: g.telares.map((t) => t.no_telar).join(','),
    }));
    for (const t of solos) {
        salida.push({
            telares: [t],
            telaresStr: t.no_telar ?? '',
            cuenta: t.cuenta || '',
            calibre: t.calibre !== null ? t.calibre : '',
            hilo: t.hilo || '',
            tamano: t.tamano || '',
            tipo: normalizarTipo(t.tipo) || 'Rizo',
            urdido: t.urdido || '',
            tipoAtado: t.tipo_atado || 'Normal',
            destino: t.destino || '',
            fechaReq: t.fecha_req || '',
            metros: t.metros || 0,
            kilos: t.kilos || 0,
            maquinaId: t.urdido || t.maquina_urd || t.maquinaId || '',
        });
    }
    return salida;
}

/** Id de la fila de la tabla principal (igual que antes: fila-<índice>-<telares>). */
export function idFila(indice: number, g: Grupo): string {
    return `fila-${indice}-${g.telaresStr}`;
}

/** Clases del distintivo de tipo. */
export function clasesTipo(tipo: string): string {
    const up = tipo.toUpperCase();
    if (up === 'RIZO') return 'bg-rose-100 text-rose-700';
    if (up === 'PIE') return 'bg-teal-100 text-teal-700';
    return 'bg-gray-100 text-gray-700';
}

/* =================== Destino =================== */

export const DESTINOS = ['Itema Nuevo', 'Itema Viejo', 'Jacquard Sulzer', 'Jacquard Smit', 'Smit'] as const;

export function normalizarDestino(destino: unknown): string {
    const valor = String(destino || '').trim();
    if (!valor) return '';
    const n = valor.toUpperCase().replace(/\s+/g, ' ');
    if (n === 'ITEMA NUEVO') return 'Itema Nuevo';
    if (n === 'ITEMA VIEJO') return 'Itema Viejo';
    if (n === 'JACQUARD SULZER' || n === 'SULZER') return 'Jacquard Sulzer';
    if (n === 'JACQUARD SMIT' || n === 'JACQUARD' || n === 'JAC') return 'Jacquard Smit';
    if (n === 'SMIT' || n === 'SMITH') return 'Smit';
    return '';
}

export function requiereDestinoManual(g: Pick<Grupo, 'telares'>): boolean {
    return g.telares.length > 1;
}

export function destinoPorTelar(noTelar: unknown): string {
    const n = parseInt(String(noTelar ?? ''), 10);
    if (!n) return '';
    if (n >= 207 && n <= 211) return 'Jacquard Sulzer';
    if ([201, 202, 203, 204, 205, 206, 213, 214, 215].includes(n)) return 'Jacquard Smit';
    if (n >= 305 && n <= 316) return 'Smit';
    if ([303, 304, 317, 318].includes(n)) return 'Itema Viejo';
    if ([299, 300, 301, 302, 319, 320].includes(n)) return 'Itema Nuevo';
    return '';
}

/** Destino inicial: vacío si el grupo tiene varios telares; si no, el explícito o el del telar. */
export function destinoInicial(g: Pick<Grupo, 'telares' | 'destino'>): string {
    if (requiereDestinoManual(g)) return '';
    const explicito = normalizarDestino(g.destino);
    if (explicito) return explicito;
    return g.telares.length === 1 ? destinoPorTelar(g.telares[0]?.no_telar) : '';
}

/* =================== Materiales =================== */

export interface FilaMaterialUrdido {
    articulo: string;
    config: string;
    consumo: string;
    kilos: string;
}

/** Filas de la Tabla 2: consumo a 3 decimales y kilos = kilos programados × consumo. */
export function filasMaterialesUrdido(materiales: readonly MaterialUrdido[], kilosProgramados: unknown): FilaMaterialUrdido[] {
    const kilosProg = aNum(kilosProgramados, 0);
    return materiales.map((m) => {
        const consumo = Math.round(aNum(m.BomQty, 0) * 1000) / 1000;
        return {
            articulo: m.ItemId || '-',
            config: m.ConfigId || '-',
            consumo: numero(consumo, 3),
            kilos: numero(kilosProg * consumo),
        };
    });
}

/** Query de materiales-engomado: itemIds[] y configIds[] sin repetir (mismo orden que antes). */
export function queryMaterialesEngomado(materiales: readonly MaterialUrdido[]): string | null {
    const itemIds = [...new Set(materiales.map((m) => m.ItemId).filter((v): v is string => Boolean(v)))];
    if (!itemIds.length) return null;
    const configIds = [...new Set(materiales.map((m) => m.ConfigId).filter((v): v is string => !enBlanco(v)))];
    const params = new URLSearchParams();
    itemIds.forEach((v) => params.append('itemIds[]', v));
    configIds.forEach((v) => params.append('configIds[]', v));
    return params.toString();
}

/** URL con query (respeta una query que ya traiga la ruta). */
export function conQuery(url: string, query: Record<string, string> | string): string {
    const texto = typeof query === 'string' ? query : new URLSearchParams(query).toString();
    if (!texto) return url;
    return `${url}${url.includes('?') ? '&' : '?'}${texto}`;
}

/** Celdas de texto de la Tabla 3 (13 columnas, antes del checkbox). */
export function celdasMaterialEngomado(m: MaterialEngomado): string[] {
    return [
        m.ItemId || '-',
        m.ConfigId || '-',
        m.InventSizeId || '-',
        m.InventColorId || '-',
        m.InventLocationId || '-',
        m.InventBatchId || '-',
        m.WMSLocationId || '-',
        m.InventSerialId || '-',
        m.TwCalidadFlog || '-',
        m.TwClienteFlog || '-',
        m.ProdDate ? fechaCorta(m.ProdDate) : '-',
        aNum(m.TwTiras, 0).toFixed(0),
        numero(aNum(m.PhysicalInvent, 0)),
    ];
}

/** Totales del pie de la Tabla 3 sobre lo seleccionado (conos redondeados, kilos a 2 decimales). */
export function totalesSeleccion(materiales: readonly MaterialEngomado[]): { registros: number; conos: string; kilos: string } {
    let conos = 0;
    let kilos = 0;
    for (const m of materiales) {
        conos += aNum(aNum(m.TwTiras, 0).toFixed(0), 0);
        kilos += aNum(numero(aNum(m.PhysicalInvent, 0)), 0);
    }
    return { registros: materiales.length, conos: conos.toFixed(0), kilos: numero(kilos) };
}

/** Metraje por tela = metros del grupo / No. de telas (vacío si alguno es 0). */
export function metrajePorTela(metros: unknown, noTelas: unknown): string {
    const m = aNum(metros, 0);
    const t = aNum(noTelas, 2);
    return m > 0 && t > 0 ? numero(m / t, 2) : '';
}

/** Tope de julios por fila de construcción. */
export const MAX_JULIOS = 15;

/* =================== Validación y payload de crear-ordenes =================== */

/** Material marcado: ids del checkbox y el objeto de su fila (si se tiene). */
export interface MaterialMarcado {
    materialId: string;
    serialId: string;
    material: MaterialEngomado | null;
}

export interface EstadoGrupo {
    grupo: Grupo;
    bomId: string;
    destinoSeleccionado: string;
    requiereDestinoManual: boolean;
    materialesEngomado: MaterialEngomado[];
}

export interface EntradaCrear {
    estado: EstadoGrupo | null;
    /** Valor del select de destino de la fila (respaldo si el estado no lo tiene). */
    destinoSelect: string;
    marcados: MaterialMarcado[];
    construccion: Partial<FilaConstruccion>[];
    engomado: DatosEngomadoCrudos;
}

export type Foco = { tipo: 'destino' } | { tipo: 'julios'; fila: number } | { tipo: 'engomado'; campo: CampoEngomado };

export interface Aviso {
    titulo: string;
    texto: string;
    foco?: Foco;
}

export type ResultadoValidacion =
    | { ok: true; destino: string; datos: Omit<PayloadCrearOrdenes, 'fechaRequerimiento'> }
    | { ok: false; aviso: Aviso };

/** Campos obligatorios de Datos de Engomado, en el orden en que se enfocan. */
export const CAMPOS_ENGOMADO_OBLIGATORIOS: ReadonlyArray<readonly [CampoEngomado, string]> = [
    ['nucleo', 'Núcleo'],
    ['noTelas', 'No. de Telas'],
    ['anchoBalonas', 'Ancho Balonas'],
    ['metrajeTelas', 'Metraje de Telas'],
    ['cuendeadosMin', 'Cuendeados Mín. por Tela'],
    ['maquinaEngomado', 'Máquina Engomado'],
    ['lMatEngomado', 'L Mat Engomado'],
];

export function camposFaltantes(d: DatosEngomadoCrudos): Array<readonly [CampoEngomado, string]> {
    return CAMPOS_ENGOMADO_OBLIGATORIOS.filter(([campo]) => String(d[campo] ?? '').trim() === '');
}

/** Datos de engomado tal como los mandaba el JS viejo (solo L.Mat, fórmula y observaciones van recortados). */
export function datosEngomadoPayload(d: DatosEngomadoCrudos): DatosEngomadoCrudos {
    return {
        nucleo: d.nucleo || '',
        noTelas: d.noTelas || '',
        anchoBalonas: d.anchoBalonas || '',
        metrajeTelas: d.metrajeTelas || '',
        cuendeadosMin: d.cuendeadosMin || '',
        maquinaEngomado: d.maquinaEngomado || '',
        lMatEngomado: (d.lMatEngomado || '').trim(),
        bomFormula: (d.bomFormula || '').trim(),
        observaciones: (d.observaciones || '').trim(),
    };
}

/**
 * Filas de construcción con datos (julios o hilos). Una fila con más de 15 julios detiene
 * la creación (antes solo avisaba y seguía sin esa fila: bug corregido en 19-05).
 */
export function leerConstruccion(filas: readonly Partial<FilaConstruccion>[]): { ok: true; filas: FilaConstruccion[] } | { ok: false; fila: number } {
    const salida: FilaConstruccion[] = [];
    for (const [i, f] of filas.entries()) {
        const julios = (f.julios ?? '').trim();
        const hilos = (f.hilos ?? '').trim();
        const observaciones = (f.observaciones ?? '').trim();
        if (enBlanco(julios) && enBlanco(hilos)) continue;
        if (!enBlanco(julios) && Number(julios) > MAX_JULIOS) return { ok: false, fila: i };
        salida.push({ julios, hilos, observaciones });
    }
    return { ok: true, filas: salida };
}

/** Materiales marcados → payload: primero se busca en los del grupo, si no, el de la fila. */
export function materialesSeleccionados(marcados: readonly MaterialMarcado[], completos: readonly MaterialEngomado[]): MaterialEngomadoPayload[] {
    const salida: MaterialEngomadoPayload[] = [];
    for (const { materialId, serialId, material: deFila } of marcados) {
        const material =
            completos.find((m) => (m.ItemId || '') === materialId && (m.InventSerialId || '') === serialId) ?? deFila;
        if (!material) continue;
        const base = materialAPayload(material);
        salida.push({
            ...base,
            itemId: base.itemId || materialId || '',
            inventSerialId: base.inventSerialId || serialId || '',
            status: 'Programado',
        });
    }
    return salida;
}

export function grupoPayload(g: Grupo, destino: string, bomId: string): GrupoPayload {
    return {
        telaresStr: g.telaresStr || '',
        noTelarId: g.telares[0]?.no_telar || g.telaresStr || '',
        tipo: g.tipo || '',
        cuenta: g.cuenta || '',
        calibre: g.calibre !== null && g.calibre !== '' ? parseFloat(String(g.calibre)) : null,
        fechaReq: g.fechaReq || '',
        fibra: g.hilo || '',
        hilo: g.hilo || '',
        tamano: g.tamano || '',
        inventSizeId: g.tamano || '',
        metros: g.metros || 0,
        kilos: g.kilos || 0,
        noProduccion: '',
        salonTejidoId: destino,
        destino,
        maquinaId: g.maquinaId || g.urdido || '',
        bomId,
        tipoAtado: g.tipoAtado || 'Normal',
        status: 'Programado',
    };
}

const aviso = (titulo: string, texto: string, foco?: Foco): ResultadoValidacion =>
    ({ ok: false, aviso: foco ? { titulo, texto, foco } : { titulo, texto } });

/** Validaciones de "Crear Órdenes" en el mismo orden que el JS viejo y armado del payload (sin fecha). */
export function validarCreacion(e: EntradaCrear): ResultadoValidacion {
    const est = e.estado;
    if (!est) return aviso('Selección requerida', 'Por favor, seleccione una fila de la tabla principal.');

    if (enBlanco(est.bomId)) {
        return aviso('BOM ID requerido', 'Por favor, ingrese un BOM ID (L.Mat Urdido) para la fila seleccionada.');
    }

    const destino = normalizarDestino(est.destinoSeleccionado || e.destinoSelect || '');
    if (enBlanco(destino)) {
        return aviso(
            'Destino requerido',
            est.requiereDestinoManual
                ? 'Seleccione el destino del grupo antes de crear la orden.'
                : 'Seleccione un destino válido antes de crear la orden.',
            { tipo: 'destino' },
        );
    }

    if (enBlanco((est.grupo.hilo || '').trim())) {
        return aviso(
            'Fibra/Hilo requerido',
            'La fila seleccionada no tiene fibra/hilo. Regrese a Programación de Requerimientos y seleccione la fibra correcta.',
        );
    }

    if (!e.marcados.length) {
        return aviso('Materiales requeridos', 'Por favor, seleccione al menos un material de engomado.');
    }
    const materialesEngomado = materialesSeleccionados(e.marcados, est.materialesEngomado);

    const construccion = leerConstruccion(e.construccion);
    if (!construccion.ok) {
        return aviso('Valor fuera de rango', `El número de julios no puede ser mayor a ${MAX_JULIOS}.`, {
            tipo: 'julios',
            fila: construccion.fila,
        });
    }
    if (!construccion.filas.length) {
        return aviso(
            'Construcción requerida',
            'Por favor, complete al menos una fila de construcción urdido (No. Julios o Hilos).',
        );
    }

    const faltan = camposFaltantes(e.engomado);
    const primero = faltan[0];
    if (primero) {
        return aviso(
            'Campos requeridos',
            `Por favor, complete los siguientes campos requeridos de Datos de Engomado: ${faltan.map(([, etiqueta]) => etiqueta).join(', ')}.`,
            { tipo: 'engomado', campo: primero[0] },
        );
    }

    return {
        ok: true,
        destino,
        datos: {
            grupo: grupoPayload(est.grupo, destino, est.bomId),
            materialesEngomado,
            construccionUrdido: construccion.filas,
            datosEngomado: datosEngomadoPayload(e.engomado),
        },
    };
}

/** Payload final de POST programa.urd.eng.crear.ordenes (mismo orden de claves que antes). */
export function armarPayload(datos: Omit<PayloadCrearOrdenes, 'fechaRequerimiento'>, fechaRequerimiento: string): PayloadCrearOrdenes {
    return {
        grupo: datos.grupo,
        materialesEngomado: datos.materialesEngomado,
        construccionUrdido: datos.construccionUrdido,
        datosEngomado: datos.datosEngomado,
        fechaRequerimiento,
    };
}
