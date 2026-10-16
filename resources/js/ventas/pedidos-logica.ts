/**
 * Reglas puras de Pedidos Históricos (sin DOM ni imports, para probarlas con node --test).
 * Jerarquía: Empresa > Tipo de pedido > Cliente > OC > artículo. Los datos del artículo
 * (tamaño, color, precio, fechas) solo existen en la hoja, así que viajan con ella en vez
 * de ocupar columnas que quedarían vacías en los niveles de grupo.
 */

export const PAYLOAD_VERSION = 1;

const TEXT_FIELDS = [
    'mes', 'empresa', 'tipo', 'cliente', 'oc', 'articulo', 'nombre', 'talla', 'colorId', 'color', 'codigo', 'creado', 'facturado',
] as const;
const NUMBER_FIELDS = ['precio', 'cantidad', 'entregado', 'pendiente', 'importe'] as const;

type TextField = typeof TEXT_FIELDS[number];
type NumberField = typeof NUMBER_FIELDS[number];

export type PedidosPayload = { v: number; anio: string; sf: string[]; nf: string[]; dict: string[]; rows: number[][] };
export type PedidoLinea = Record<TextField | 'anio' | 'estatus', string> & Record<NumberField, number>;

export const LEVELS = ['empresa', 'tipo', 'cliente', 'oc'] as const;
export type Level = typeof LEVELS[number];

export const LEVEL_LABELS: Record<Level, string> = { empresa: 'Empresa', tipo: 'Tipo de pedido', cliente: 'Cliente', oc: 'Orden de compra' };

export type Totals = { cantidad: number; entregado: number; pendiente: number; importe: number };

export type TreeNode = {
    id: string;
    level: Level;
    depth: number;
    value: string;
    totals: Totals;
    /** Subgrupos; vacío en el nivel OC, que lleva sus líneas en `lines`. */
    children: TreeNode[];
    lines: PedidoLinea[];
    /** OCs distintas debajo del nodo (Cliente y superiores). */
    orders: number;
    /** Fecha de creación de la OC si todas sus líneas la comparten; '' si varía. */
    created: string;
};

export const ESTATUS = { pendiente: 'Con pendiente', completo: 'Entregado' } as const;

export const MONTH_NAMES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

export const monthName = (mes: string) => MONTH_NAMES[Number(mes) - 1] ?? mes;

/** Convierte el payload compacto (índices al diccionario) en líneas. Rechaza formatos que no conoce. */
export const decodePedidos = (payload: PedidosPayload): PedidoLinea[] => {
    if (payload.v !== PAYLOAD_VERSION) throw new Error(`Formato de Pedidos Históricos no soportado: v${payload.v}`);
    const textIndex = TEXT_FIELDS.map((field) => payload.sf.indexOf(field));
    const numberIndex = NUMBER_FIELDS.map((field) => payload.nf.indexOf(field));
    if ([...textIndex, ...numberIndex].includes(-1)) throw new Error('Faltan campos en Pedidos Históricos.');

    return payload.rows.map((row) => {
        const line = { anio: String(payload.anio) } as PedidoLinea;
        TEXT_FIELDS.forEach((field, i) => { line[field] = payload.dict[row[textIndex[i]!]!] ?? ''; });
        NUMBER_FIELDS.forEach((field, i) => { line[field] = Number(row[payload.sf.length + numberIndex[i]!]) || 0; });
        line.estatus = line.pendiente > 0 ? ESTATUS.pendiente : ESTATUS.completo;
        return line;
    });
};

export const FILTER_KEYS = ['anio', 'mes', 'empresa', 'tipo', 'cliente', 'articulo', 'talla', 'color', 'codigo', 'estatus'] as const;
export type FilterKey = typeof FILTER_KEYS[number];
export type Filters = Record<FilterKey, Set<string>>;

/** Nada seleccionado en un filtro = todos; except ignora un filtro (para calcular sus propias opciones). */
export const matchesLine = (line: PedidoLinea, filters: Filters, except?: FilterKey) =>
    FILTER_KEYS.every((key) => key === except || !filters[key].size || filters[key].has(line[key]));

export const sumLines = (lines: PedidoLinea[]): Totals => lines.reduce((acc, line) => {
    acc.cantidad += line.cantidad;
    acc.entregado += line.entregado;
    acc.pendiente += line.pendiente;
    acc.importe += line.importe;
    return acc;
}, { cantidad: 0, entregado: 0, pendiente: 0, importe: 0 });

/** % entregado de la cantidad pedida (0–100). */
export const progress = (totals: Totals) => (totals.cantidad > 0 ? Math.min(100, (totals.entregado / totals.cantidad) * 100) : 0);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });
/**
 * Orden de negocio (el mismo de Resumen General): Towel antes que Textil y los tipos CE, CE HT, RS
 * y al final 2das / 3ra. Lo que no está en la lista va después, en orden alfabético.
 */
const BUSINESS_ORDER: Partial<Record<Level, string[]>> = {
    empresa: ['towel', 'textil'],
    tipo: ['ce', 'ce ht', 'rs', '2das / 3ra'],
};

const compareValues = (level: Level, a: string, b: string) => {
    const order = BUSINESS_ORDER[level];
    if (order) {
        const rank = (value: string) => { const i = order.indexOf(value.trim().toLowerCase()); return i === -1 ? order.length : i; };
        const diff = rank(a) - rank(b);
        if (diff) return diff;
    }
    return collator.compare(a, b);
};

/** Artículos por nombre; las líneas del mismo artículo, por fecha de factura (dd/mm/aaaa). */
const dateKey = (date: string) => date.split('/').reverse().join('');
const compareLines = (a: PedidoLinea, b: PedidoLinea) =>
    collator.compare(a.nombre, b.nombre) || collator.compare(a.articulo, b.articulo) || dateKey(a.facturado).localeCompare(dateKey(b.facturado));

export const buildTree = (lines: PedidoLinea[], depth = 0, parentId = ''): TreeNode[] => {
    const level = LEVELS[depth]!;
    const groups = new Map<string, PedidoLinea[]>();
    lines.forEach((line) => {
        const key = line[level];
        const bucket = groups.get(key);
        if (bucket) bucket.push(line); else groups.set(key, [line]);
    });

    return [...groups.entries()]
        .sort(([a], [b]) => compareValues(level, a, b))
        .map(([value, groupLines]) => {
            const id = `${parentId}/${value}`;
            const isOrder = level === 'oc';
            const createdDates = isOrder ? new Set(groupLines.map((line) => line.creado)) : new Set<string>();
            return {
                id,
                level,
                depth,
                value,
                totals: sumLines(groupLines),
                children: isOrder ? [] : buildTree(groupLines, depth + 1, id),
                lines: isOrder ? [...groupLines].sort(compareLines) : [],
                orders: isOrder ? 1 : new Set(groupLines.map((line) => `${line.cliente}|${line.oc}`)).size,
                created: createdDates.size === 1 ? [...createdDates][0]! : '',
            };
        });
};

/** Un nodo está abierto si su profundidad es menor que openDepth, salvo que el usuario lo haya invertido con su flecha (toggled). */
export const isOpen = (node: Pick<TreeNode, 'id' | 'depth'>, openDepth: number, toggled: Set<string>) =>
    (node.depth < openDepth) !== toggled.has(node.id);
