import { formatNumber } from '../../utils/format.ts';
import type { AristaFlujo, DiagramaFlujo, NodoFlujo } from './tipos.ts';

/**
 * Fixture estático del flujo de almacenes del Kardex de agosto 2026
 * (Kardex_Materia_Prima_y_Produccion_en_Proceso_Agosto_2026.html, flow()). Solo para la galería.
 */

export interface DatosNodoKardex {
    nombre: string;
    inicial: number | null;
    final: number | null;
    /** Ajuste por recuentos / regresos (solo pretejidos). */
    ajuste?: number;
}

export interface DatosAristaKardex {
    desglose: Array<{ concepto: string; kg: number }>;
}

const kg0 = (n: number): string => formatNumber(n, 0);

function almacen(
    id: string,
    x: number,
    y: number,
    ancho: number,
    alto: number,
    color: string,
    nombre: string,
    inicial: number,
    final: number,
    ajuste?: number,
): NodoFlujo {
    const datos: DatosNodoKardex = { nombre, inicial, final, ...(ajuste === undefined ? {} : { ajuste }) };
    const nodo: NodoFlujo = { id, x, y, ancho, alto, titulo: id, subtitulo: nombre, valor: `${kg0(inicial)} → ${kg0(final)} kg`, color, datos };
    if (ajuste !== undefined) nodo.nota = `± recuentos / regresos: ${ajuste > 0 ? '+' : ''}${kg0(ajuste)}`;

    return nodo;
}

function arista(
    id: string,
    de: string,
    a: string,
    desglose: Array<{ concepto: string; kg: number }>,
    etiqueta: string,
    extra: Partial<AristaFlujo> = {},
): AristaFlujo {
    const datos: DatosAristaKardex = { desglose };

    return { id, de, a, etiqueta, datos, ...extra };
}

const total = (kg: number, concepto = 'Neto'): Array<{ concepto: string; kg: number }> => [{ concepto, kg }];

export const ejemploKardex: DiagramaFlujo = {
    ancho: 1380,
    alto: 560,
    nodos: [
        almacen('A-MP', 10, 60, 150, 70, '#0f766e', 'Materia Prima (Hilo)', 127288, 110654),
        almacen('A-MPBB', 10, 150, 150, 70, '#059669', 'Materia Prima BB', 44771, 32864),
        almacen('A-EP-URDID', 230, 100, 160, 70, '#2563eb', 'Urdido en Proceso', 8212, 10479),
        almacen('A-JUL/URD', 460, 100, 150, 70, '#0ea5e9', 'Julios Urdidos', 6862, 9065),
        almacen('A-JUL/TELA', 680, 100, 150, 70, '#7c3aed', 'Julios Engomados', 24652, 26417),
        almacen('A-EP-TEJID', 900, 100, 160, 70, '#db2777', 'Tejido en Proceso (Julios)', 29410, 25482),
        {
            id: 'A-CRUDO',
            x: 1160,
            y: 180,
            ancho: 180,
            alto: 80,
            titulo: 'A-CRUDO',
            subtitulo: 'Tela Cruda',
            valor: `+ ${formatNumber(278200.82, 2)} kg tejidos en telares`,
            color: '#334155',
            datos: { nombre: 'Tela Cruda', inicial: null, final: null } satisfies DatosNodoKardex,
        },
        almacen('A-PTE-JACQ', 560, 300, 150, 78, '#ea580c', 'Pretejido Jacquard (Trama)', 925, 1128, 4307),
        almacen('A-PTE-LISO', 740, 300, 150, 78, '#f59e0b', 'Pretejido Lisos (Trama)', 818, 781, -10904),
        almacen('A-PTE-ITEM', 920, 300, 150, 78, '#b91c1c', 'Pretejido Itema (Trama)', 1952, 1373, -2617),
        almacen('A-MATENG', 60, 472, 160, 60, '#92400e', 'Materiales Engomado', 7846, 1690),
        almacen('A-FORMENG', 300, 472, 160, 60, '#ca8a04', 'Formulación Engomado', 0, 0),
    ],
    aristas: [
        arista('mp-urdid', 'A-MP', 'A-EP-URDID', total(137115), kg0(137115), { desde: { x: 160, y: 95 }, hasta: { x: 230, y: 125 } }),
        arista('mpbb-urdid', 'A-MPBB', 'A-EP-URDID', total(76373), kg0(76373), { desde: { x: 160, y: 185 }, hasta: { x: 230, y: 150 } }),
        arista('urdid-julurd', 'A-EP-URDID', 'A-JUL/URD', total(211688, 'Salidas'), kg0(211688)),
        arista('julurd-jultela', 'A-JUL/URD', 'A-JUL/TELA', total(179320, 'Salidas'), kg0(179320), { desde: { x: 610, y: 120 }, hasta: { x: 680, y: 120 } }),
        arista('jultela-eptejid', 'A-JUL/TELA', 'A-EP-TEJID', total(182173), kg0(182173)),
        arista('julurd-eptejid', 'A-JUL/URD', 'A-EP-TEJID', total(34555, 'Karl Mayer (neto)'), `${kg0(34555)} (KM)`, {
            color: '#5598e7',
            curva: 215,
            desde: { x: 610, y: 170 },
            hasta: { x: 905, y: 172 },
        }),
        arista('eptejid-crudo', 'A-EP-TEJID', 'A-CRUDO', total(213767, 'Salidas'), kg0(213767), {
            color: '#1c5cab',
            desde: { x: 1060, y: 150 },
            hasta: { x: 1160, y: 205 },
        }),
        arista('mateng-formeng', 'A-MATENG', 'A-FORMENG', total(11869, 'Salidas'), kg0(11869)),
        arista('formeng-jultela', 'A-FORMENG', 'A-JUL/TELA', total(228091, 'Salidas (goma a WP2/WP3)'), `${kg0(228091)} (goma → WP2/WP3)`, {
            controles: [430, 380, 510, 200],
            desde: { x: 380, y: 472 },
            hasta: { x: 696, y: 172 },
            posEtiqueta: { x: 452, y: 400 },
        }),
        arista(
            'mp-jacq',
            'A-MP',
            'A-PTE-JACQ',
            [
                { concepto: 'A-PTE-JACQ', kg: 13636 },
                { concepto: 'A-PTE-LISO', kg: 10772 },
                { concepto: 'A-PTE-ITEM', kg: 12932 },
            ],
            `MP: ${kg0(13636)} / ${kg0(10772)} / ${kg0(12932)}`,
            { color: '#86b6ef', desde: { x: 160, y: 110 }, hasta: { x: 560, y: 318 } },
        ),
        arista(
            'mpbb-jacq',
            'A-MPBB',
            'A-PTE-JACQ',
            [
                { concepto: 'A-PTE-JACQ', kg: 7703 },
                { concepto: 'A-PTE-LISO', kg: 7203 },
                { concepto: 'A-PTE-ITEM', kg: 4097 },
            ],
            `MPBB: ${kg0(7703)} / ${kg0(7203)} / ${kg0(4097)}`,
            { color: '#a5c4ef', desde: { x: 160, y: 205 }, hasta: { x: 560, y: 348 } },
        ),
        arista('jacq-crudo', 'A-PTE-JACQ', 'A-CRUDO', total(25444, 'Salidas'), kg0(25444), {
            color: '#5598e7',
            curva: 442,
            desde: { x: 635, y: 378 },
            hasta: { x: 1170, y: 262 },
        }),
        arista('liso-crudo', 'A-PTE-LISO', 'A-CRUDO', total(7107, 'Salidas'), kg0(7107), {
            color: '#5598e7',
            curva: 418,
            desde: { x: 815, y: 378 },
            hasta: { x: 1200, y: 262 },
        }),
        arista('item-crudo', 'A-PTE-ITEM', 'A-CRUDO', total(14991, 'Salidas'), kg0(14991), {
            color: '#5598e7',
            curva: 398,
            desde: { x: 995, y: 378 },
            hasta: { x: 1230, y: 262 },
        }),
    ],
    secciones: [
        { texto: 'HILO', x: 14, y: 30 },
        { texto: 'TRAMA EN TELARES', x: 570, y: 290 },
        { texto: 'PREPARACIÓN DE GOMA (ENGOMADO)', x: 64, y: 462 },
    ],
};
