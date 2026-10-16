import { montarRender } from './render.ts';
import type { DiagramaFlujo, HandleFlujo, OpcionesFlujo } from './tipos.ts';

export type { AristaFlujo, DiagramaFlujo, HandleFlujo, NodoFlujo, OpcionesFlujo, SeccionFlujo } from './tipos.ts';

/** Dibuja `diagrama` dentro de `contenedor` (SVG interactivo). Lanza si el diagrama es inválido. */
export function montarDiagramaFlujo(
    contenedor: HTMLElement,
    diagrama: DiagramaFlujo,
    opciones: OpcionesFlujo = {},
): HandleFlujo {
    return montarRender(contenedor, diagrama, opciones);
}
