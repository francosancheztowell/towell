export interface Punto {
    x: number;
    y: number;
}

export interface NodoFlujo {
    id: string;
    x: number;
    y: number;
    ancho: number;
    alto: number;
    titulo: string;
    subtitulo?: string;
    valor?: string;
    /** Línea de aviso (ámbar) al pie de la caja. */
    nota?: string;
    color: string;
    datos?: unknown;
}

export interface AristaFlujo {
    id: string;
    de: string;
    a: string;
    etiqueta: string;
    color?: string;
    /** Y de control para curvas largas que rodean otras cajas. */
    curva?: number;
    /** Anclas explícitas; si faltan se calculan según la posición relativa de las cajas. */
    desde?: Punto;
    hasta?: Punto;
    /** Puntos de control Bézier explícitos (x1, y1, x2, y2). */
    controles?: [number, number, number, number];
    /** Posición fija de la etiqueta. */
    posEtiqueta?: Punto;
    datos?: unknown;
}

export interface SeccionFlujo {
    texto: string;
    x: number;
    y: number;
}

export interface DiagramaFlujo {
    ancho: number;
    alto: number;
    nodos: NodoFlujo[];
    aristas: AristaFlujo[];
    secciones?: SeccionFlujo[];
}

export interface OpcionesFlujo {
    onNodo?(nodo: NodoFlujo): void;
    onArista?(arista: AristaFlujo): void;
    tooltipNodo?(nodo: NodoFlujo): string;
    tooltipArista?(arista: AristaFlujo): string;
}

export interface HandleFlujo {
    destruir(): void;
    actualizar(diagrama: DiagramaFlujo): void;
}
