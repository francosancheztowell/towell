import type { AristaFlujo, DiagramaFlujo, NodoFlujo, Punto } from './tipos.ts';

export type Lado = 'derecha' | 'izquierda' | 'arriba' | 'abajo';

export interface Anclas {
    desde: Punto;
    hasta: Punto;
    ladoDesde: Lado;
    ladoHasta: Lado;
}

export interface RutaArista {
    d: string;
    etiqueta: Punto;
}

export interface Vecinos {
    nodos: string[];
    aristas: string[];
}

function num(valor: number): string {
    return String(Math.round(valor * 100) / 100);
}

function puntoLado(nodo: NodoFlujo, lado: Lado): Punto {
    switch (lado) {
        case 'derecha':
            return { x: nodo.x + nodo.ancho, y: nodo.y + nodo.alto / 2 };
        case 'izquierda':
            return { x: nodo.x, y: nodo.y + nodo.alto / 2 };
        case 'arriba':
            return { x: nodo.x + nodo.ancho / 2, y: nodo.y };
        case 'abajo':
            return { x: nodo.x + nodo.ancho / 2, y: nodo.y + nodo.alto };
    }
}

/**
 * Sale por el lado derecho y entra por el izquierdo cuando el destino queda a la derecha;
 * si queda a la izquierda se invierte; si se traslapan en horizontal, usa arriba/abajo.
 * `desde` y `hasta` de la arista sustituyen el punto pero no el lado (que orienta la curva).
 */
export function anclasArista(arista: AristaFlujo, origen: NodoFlujo, destino: NodoFlujo): Anclas {
    let ladoDesde: Lado;
    let ladoHasta: Lado;

    if (destino.x >= origen.x + origen.ancho) {
        ladoDesde = 'derecha';
        ladoHasta = 'izquierda';
    } else if (destino.x + destino.ancho <= origen.x) {
        ladoDesde = 'izquierda';
        ladoHasta = 'derecha';
    } else if (destino.y >= origen.y + origen.alto) {
        ladoDesde = 'abajo';
        ladoHasta = 'arriba';
    } else {
        ladoDesde = 'arriba';
        ladoHasta = 'abajo';
    }

    return {
        desde: arista.desde ?? puntoLado(origen, ladoDesde),
        hasta: arista.hasta ?? puntoLado(destino, ladoHasta),
        ladoDesde,
        ladoHasta,
    };
}

/** Curva cúbica y posición de la etiqueta (mismas fórmulas que el flujo original del Kardex). */
export function rutaArista(arista: AristaFlujo, anclas: Anclas): RutaArista {
    const { desde, hasta } = anclas;
    const mx = (desde.x + hasta.x) / 2;
    const my = (desde.y + hasta.y) / 2;
    const inicio = `M${num(desde.x)},${num(desde.y)}`;
    const fin = `${num(hasta.x)},${num(hasta.y)}`;
    let d: string;
    let etiqueta: Punto;

    if (arista.controles) {
        const [c1x, c1y, c2x, c2y] = arista.controles;
        d = `${inicio} C${num(c1x)},${num(c1y)} ${num(c2x)},${num(c2y)} ${fin}`;
        // Punto medio de la Bézier (t = 0.5)
        etiqueta = {
            x: (desde.x + 3 * c1x + 3 * c2x + hasta.x) / 8,
            y: (desde.y + 3 * c1y + 3 * c2y + hasta.y) / 8 - 6,
        };
    } else if (arista.curva != null) {
        const dip = arista.curva;
        d = `${inicio} C${num(mx)},${num(dip)} ${num(mx)},${num(dip)} ${fin}`;
        etiqueta = { x: mx, y: dip > desde.y ? dip + 4 : dip - 8 };
    } else if (anclas.ladoDesde === 'arriba' || anclas.ladoDesde === 'abajo') {
        d = `${inicio} C${num(desde.x)},${num(my)} ${num(hasta.x)},${num(my)} ${fin}`;
        etiqueta = { x: mx, y: my - 6 };
    } else {
        d = `${inicio} C${num(mx)},${num(desde.y)} ${num(mx)},${num(hasta.y)} ${fin}`;
        etiqueta = { x: mx, y: my - 6 };
    }

    return { d, etiqueta: arista.posEtiqueta ?? etiqueta };
}

/** Nodo seleccionado, sus aristas incidentes y los nodos del otro extremo. */
export function vecinos(diagrama: DiagramaFlujo, idNodo: string): Vecinos {
    const aristas = diagrama.aristas.filter((a) => a.de === idNodo || a.a === idNodo);
    const ids = new Set<string>([idNodo]);
    for (const a of aristas) {
        ids.add(a.de);
        ids.add(a.a);
    }

    return {
        nodos: diagrama.nodos.filter((n) => ids.has(n.id)).map((n) => n.id),
        aristas: aristas.map((a) => a.id),
    };
}

/** Una arista y sus dos extremos. */
export function vecinosArista(diagrama: DiagramaFlujo, idArista: string): Vecinos {
    const arista = diagrama.aristas.find((a) => a.id === idArista);
    if (!arista) return { nodos: [], aristas: [] };

    return {
        nodos: diagrama.nodos.filter((n) => n.id === arista.de || n.id === arista.a).map((n) => n.id),
        aristas: [arista.id],
    };
}

/** Lanza si hay ids repetidos o aristas hacia nodos que no existen. */
export function validar(diagrama: DiagramaFlujo): void {
    const nodos = new Set<string>();
    for (const n of diagrama.nodos) {
        if (nodos.has(n.id)) throw new Error(`Nodo duplicado: ${n.id}`);
        nodos.add(n.id);
    }

    const aristas = new Set<string>();
    for (const a of diagrama.aristas) {
        if (aristas.has(a.id)) throw new Error(`Arista duplicada: ${a.id}`);
        aristas.add(a.id);
        if (!nodos.has(a.de)) throw new Error(`La arista ${a.id} sale de un nodo inexistente: ${a.de}`);
        if (!nodos.has(a.a)) throw new Error(`La arista ${a.id} llega a un nodo inexistente: ${a.a}`);
    }
}

/** Anclas y ruta de una arista dentro de un diagrama ya validado. */
export function calcularArista(diagrama: DiagramaFlujo, arista: AristaFlujo): RutaArista {
    const origen = diagrama.nodos.find((n) => n.id === arista.de);
    const destino = diagrama.nodos.find((n) => n.id === arista.a);
    if (!origen || !destino) throw new Error(`Arista ${arista.id} con extremos inexistentes`);

    return rutaArista(arista, anclasArista(arista, origen, destino));
}
