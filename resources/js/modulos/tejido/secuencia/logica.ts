/**
 * Lógica pura de las secuencias de Tejido (19-02): validación del formulario, payload y orden
 * tras arrastrar. Sin DOM, probada en tests/Js/tejido-secuencia.test.mjs.
 */

export type ModoFormulario = 'crear' | 'editar';

export interface CampoSecuencia {
    nombre: string;
    tipo: 'number' | 'text' | 'select' | 'textarea';
    /** true = siempre; 'editar' = solo al editar (al crear, vacío = automático). */
    requerido: boolean | 'editar';
    placeholder?: string;
    max?: number;
    min?: number;
    opciones?: string[];
}

export interface OrdenSecuencia {
    /** Campo que identifica la fila en el payload de orden (Id o NoTelarId). */
    llave: string;
    /** Campo que recibe la posición (Secuencia u Orden). */
    campo: string;
}

export type Valores = Record<string, string>;

export function esRequerido(campo: CampoSecuencia, modo: ModoFormulario): boolean {
    return campo.requerido === true || (campo.requerido === 'editar' && modo === 'editar');
}

/** Mensaje del primer campo requerido vacío, o null si el formulario está completo. */
export function validar(campos: CampoSecuencia[], valores: Valores, modo: ModoFormulario): string | null {
    for (const campo of campos) {
        if (esRequerido(campo, modo) && (valores[campo.nombre] ?? '').trim() === '') {
            return `El campo ${campo.nombre} es requerido`;
        }
    }
    return null;
}

/** Payload como lo mandaba la vista anterior: números con parseInt, texto recortado, vacío → null. */
export function construirPayload(campos: CampoSecuencia[], valores: Valores): Record<string, string | number | null> {
    const payload: Record<string, string | number | null> = {};
    for (const campo of campos) {
        const crudo = (valores[campo.nombre] ?? '').trim();
        if (crudo === '') {
            payload[campo.nombre] = null;
        } else if (campo.tipo === 'number') {
            const n = parseInt(crudo, 10);
            payload[campo.nombre] = Number.isNaN(n) ? null : n;
        } else {
            payload[campo.nombre] = crudo;
        }
    }
    return payload;
}

/** Payload de `orden` a partir de las llaves de las filas en su nuevo orden (1..n). */
export function ordenDesdeLlaves(llaves: string[], orden: OrdenSecuencia): Record<string, number>[] {
    return llaves.map((llave, i) => ({ [orden.llave]: parseInt(llave, 10), [orden.campo]: i + 1 }));
}

/** ¿Se suelta arriba (antes) o abajo (después) de la fila destino? */
export function soltarAntes(clientY: number, top: number, alto: number): boolean {
    return clientY < top + alto / 2;
}
