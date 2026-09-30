/**
 * Estado compartido de la pantalla Programación de Requerimientos (19-05).
 * Un solo objeto por página; lo crea index.ts con la config del Blade.
 */
import type { TelarSeleccionado } from '../comun/contrato-flujo.ts';
import type { TelarEntrada, TotalesTelar, Validacion } from './logica.ts';

export interface ConfigPagina {
    rutas: {
        resumen: string;
        actualizarTelar: string;
        hilos: string;
        tamanos: string;
        creacionOrdenes: string;
    };
    telares: TelarSeleccionado[];
    opcionesUrdido: string[];
}

export interface Estado {
    cfg: ConfigPagina;
    /** Telares del grupo validado (tras el filtrado tolerante). */
    telares: TelarEntrada[];
    /** Telares que representa cada fila (índice = data-index). */
    grupos: TelarEntrada[][];
    /** Catálogos vigentes de AX por tipo (RIZO/PIE): Rizo y Pie no comparten hilos ni tamaños. */
    hilos: Record<string, string[]>;
    tamanos: Record<string, string[]>;
    validacion: (Validacion & { valido: true }) | null;
    /** Totales del resumen por telar (para prellenar metros y calcular kilos). */
    porTelar: Map<string, TotalesTelar>;
}

export interface Dom {
    raiz: HTMLElement;
    cuerpo: HTMLTableSectionElement;
    resumen: HTMLTableSectionElement;
    encabezadoResumen: HTMLElement;
    siguiente: HTMLButtonElement | null;
}

/** Clona el primer elemento de un <template id="…"> del Blade. */
export function clonar<T extends Element = HTMLElement>(id: string): T {
    const tpl = document.getElementById(id) as HTMLTemplateElement | null;
    const nodo = tpl?.content.firstElementChild?.cloneNode(true);
    if (!nodo) throw new Error(`Falta la plantilla #${id}`);
    return nodo as T;
}

/** Campo de una fila por su data-field. */
export function campo<T extends HTMLInputElement | HTMLSelectElement = HTMLInputElement>(
    fila: Element | null | undefined,
    nombre: string,
): T | null {
    return fila?.querySelector<T>(`[data-field="${nombre}"]`) ?? null;
}

/** Tipo actual de la fila (lo lee al momento: el usuario puede cambiarlo). */
export function tipoDeFila(fila: Element | null | undefined): string {
    return campo<HTMLSelectElement>(fila, 'tipo')?.value ?? '';
}
