/**
 * Producción Engomado — configuración de la página y helpers de DOM compartidos
 * por filas.ts, oficiales.ts, formulacion.ts y finalizar.ts.
 */
import { notify } from '../../../utils/notifications.ts';
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { calcularNeto, limitarBruto, parsearOficiales } from './logica.ts';

export interface ConfigProduccion {
    rutas: {
        catalogosJulios: string;
        usuarios: string;
        guardarOficial: string;
        eliminarOficial: string;
        actualizarTurnoOficial: string;
        actualizarFecha: string;
        actualizarJulioTara: string;
        actualizarKgBruto: string;
        actualizarCamposProduccion: string;
        actualizarCampoOrden: string;
        actualizarHoras: string;
        verificarFormulaciones: string;
        finalizar: string;
        marcarListo: string;
        pdf: string;
        salida: string;
    };
    orden: { id: number; folio: string } | null;
    maxKgBruto: number | null;
    puedeEditar: boolean;
    usuario: { nombre: string; numero: string };
}

let config: ConfigProduccion | null = null;

export function iniciarConfig(raiz: HTMLElement): ConfigProduccion | null {
    config = leerDatos<ConfigProduccion>(raiz);
    return config;
}

export function cfg(): ConfigProduccion {
    if (!config) throw new Error('Producción Engomado: configuración no inicializada');
    return config;
}

/** Aviso y false si el usuario no puede modificar (el backend audita igual). */
export function requireCanEdit(): boolean {
    if (!cfg().puedeEditar) {
        notify.warning('No tiene permiso para modificar en este módulo');
        return false;
    }
    return true;
}

export function cuerpoTabla(): HTMLTableSectionElement | null {
    return document.getElementById('tabla-produccion-body') as HTMLTableSectionElement | null;
}

export function filaDe(registroId: string): HTMLTableRowElement | null {
    return document.querySelector<HTMLTableRowElement>(`tr[data-registro-id="${CSS.escape(registroId)}"]`);
}

export function campo<T extends HTMLElement = HTMLInputElement>(fila: ParentNode, nombre: string): T | null {
    return fila.querySelector<T>(`[data-field="${nombre}"]`);
}

export function valorDe(fila: ParentNode, selector: string): string {
    const nodo = fila.querySelector<HTMLInputElement | HTMLSelectElement>(selector);
    return nodo ? (nodo.value ?? '').trim() : '';
}

/** La fila ya tiene al menos un oficial (requisito para editar cualquier campo). */
export function tieneOficial(registroId: string): boolean {
    const texto = filaDe(registroId)?.querySelector<HTMLElement>('.oficial-texto');
    if (!texto) return false;
    return parsearOficiales(texto.dataset.oficialesJson).length > 0;
}

export function avisarOficialRequerido(): void {
    notify.warning('Debes seleccionar un oficial antes de actualizar este campo');
}

/** Fila marcada "Listo" (checkbox Finalizar): no editable hasta desmarcarla. */
export function esFilaBloqueada(registroId: string): boolean {
    const check = filaDe(registroId)?.querySelector<HTMLInputElement>('.checkbox-finalizar');
    return !!check?.checked;
}

export const MENSAJE_FILA_FINALIZADA = 'Este registro ya está parcialmente finalizado. Desmarca la casilla para editarlo.';

export function verificarFilaNoFinalizada(registroId: string): boolean {
    if (esFilaBloqueada(registroId)) {
        notify.info(MENSAJE_FILA_FINALIZADA);
        return false;
    }
    return true;
}

/** Guardas comunes de todo campo editable de una fila. */
export function puedeEditarFila(registroId: string, exigirOficial = true): boolean {
    if (!requireCanEdit()) return false;
    if (!verificarFilaNoFinalizada(registroId)) return false;
    if (exigirOficial && !tieneOficial(registroId)) {
        avisarOficialRequerido();
        return false;
    }
    return true;
}

export function marcarCampoError(elemento: Element | null, conError: boolean): void {
    if (!elemento) return;
    if (conError) {
        elemento.classList.add('border-red-500', 'border-2');
        elemento.classList.remove('border-gray-300');
    } else {
        elemento.classList.remove('border-red-500', 'border-2');
        elemento.classList.add('border-gray-300');
    }
}


/** Aplica el tope de Kg. Bruto y recalcula Kg. Neto de la fila. Devuelve true si recortó el bruto. */
export function calcularNetoFila(fila: HTMLElement): boolean {
    const bruto = campo(fila, 'kg_bruto');
    const tara = campo(fila, 'tara');
    const neto = campo(fila, 'kg_neto');
    if (!bruto || !tara || !neto) return false;

    const max = cfg().maxKgBruto;
    const antes = bruto.value;
    if (max !== null) {
        bruto.max = String(max);
        bruto.title = `Kg. Bruto máximo: ${max.toFixed(0)}`;
        bruto.value = limitarBruto(bruto.value, max);
    } else {
        bruto.removeAttribute('max');
        bruto.removeAttribute('title');
    }

    const valor = calcularNeto(bruto.value, tara.value);
    neto.value = valor.toFixed(2);
    marcarCampoError(neto, valor < 0);
    return bruto.value !== antes;
}
