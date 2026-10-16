/** Forma de los datos del Kardex MP y Producción en Proceso (hoy simulados, ./datos-simulados.ts). */

export interface MetaKardex {
    mes: string;
    corte: string;
    corte_inicial: string;
    generado: string;
    fuentes: { inicial: string; movs: string; kardex: string };
}

export interface Almacen {
    id: string;
    nombre: string;
}

export interface KpisResumen {
    consumoMp: number;
    consumoMpbb: number;
    julUrd: number;
    julTela: number;
    crudoKg: number;
    crudoPz: number;
}

export interface ProduccionDiaria {
    urdido: number[];
    engomado: number[];
    tejido: number[];
}

export type Celda = number | string | null;

export interface Renglon {
    etiqueta: string;
    valores: Celda[];
}

/** Una sección de la hoja REs del libro (MP, urdido, julios, pretejidos, tejido). */
export interface SeccionKardex {
    clave: string;
    titulo: string;
    /** Los pretejidos traen un solo renglón y se pintan como tarjeta. */
    unica: boolean;
    /** encabezados[0] es la columna de etiqueta; el resto corresponde a `valores`. */
    encabezados: string[];
    renglones: Renglon[];
    total: Renglon | null;
    /** No viene del libro (MP y pretejidos todavía no se conectan). */
    simulado?: boolean;
}

export interface ArticuloKardex {
    a: string;
    c: string;
    t: string;
    o: string;
    loc: string;
    th: string;
    ini: number;
    ent: number;
    sal: number;
    aj: number;
    saldo: number;
}

export interface CuadreAlmacen {
    alm: string;
    movs: number;
    inicial: number;
    entradas: number;
    salidas: number;
    iniMasMovs: number;
    ajusteImplicito: number | null;
    finalErp: number | null;
    metaLibro: number | null;
}

export interface VerificacionColumna {
    hoja: string;
    columna: string;
    libro: number;
    movimientos: number;
    dif: number;
    regla: string;
}

export interface ColumnasKdx {
    alm: string;
    hoja: string;
    inicial: number;
    ajuste: number;
    saldo: number;
    entradas: Record<string, number>;
    salidas: Record<string, number>;
}

export interface Traspaso {
    origen: string;
    destino: string;
    kgSalida: number;
    movsSalida: number;
    kgEntrada: number;
    movsEntrada: number;
    dif: number;
    corregido: boolean;
    interno: boolean;
    fueraTramo: boolean;
}

export interface KgAlmacen {
    alm: string;
    kg: number;
}

export interface TraspasoRedirigido {
    dia: number;
    id: string;
    articulo: string;
    almacen: string;
    declarado: KgAlmacen[];
    real: KgAlmacen[];
}

export interface ProcesoCorrespondencia {
    destino: string;
    consumos: Array<{ origen: string; kg: number; movs: number }>;
    consumido: number;
    producido: number;
    movsProduccion: number;
    fuente: string;
    dif: number;
    etiquetaDif: string;
    nota: string;
}

export interface Correspondencia {
    kpi: {
        nPares: number;
        kgTraspasado: number;
        nCuadran: number;
        nRedir: number;
        kgRedir: number;
        sinEspejo: number;
        difNeta: number;
    };
    traspasos: Traspaso[];
    redirigidos: TraspasoRedirigido[];
    procesos: ProcesoCorrespondencia[];
}

export interface TelarCrudo {
    telar: string;
    tipo: string;
    pz: number;
    kg: number;
    segPz: number;
    segKg: number;
}

/** Un renglón del archivo de Entradas a Crudo: artículo + tamaño tejido en un telar. */
export interface DetalleCrudo {
    articulo: string;
    nombre: string;
    tamano: string;
    telar: string;
    salon: string;
    piezas: number;
    kg: number;
    segPiezas: number;
    segKg: number;
    diaIni: number;
    diaFin: number;
    entradas: number;
}

export interface Crudo {
    total: { kg: number; pz: number; segKg: number; segPz: number; retazoKg: number; nArts: number; nTel: number };
    referenciaKdx: Record<string, number>;
    telares: TelarCrudo[];
    detalle: DetalleCrudo[];
    /** Por día del mes: [kg tejidos, kg segundas]. */
    dia: Array<[number, number]>;
}

export interface DatosKardex {
    meta: MetaKardex;
    almacenes: Almacen[];
    kpis: KpisResumen;
    produccionDiaria: ProduccionDiaria;
    secciones: SeccionKardex[];
    telarTipo: Record<string, string>;
    articulos: Record<string, ArticuloKardex[]>;
    cuadre: CuadreAlmacen[];
    verificacion: VerificacionColumna[];
    columnasKdx: ColumnasKdx[];
    correspondencia: Correspondencia;
    crudo: Crudo;
    /** Inicial y final del libro por almacén, cuando la página ya cargó el libro del mes. */
    libro?: {
        nodos: Record<string, { inicial: number; final: number }>;
        aristas: Record<string, number>;
        crudoKg: number;
    };
}
