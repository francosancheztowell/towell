/**
 * Estado y utilidades compartidas de Producción Urdido: config del servidor, filas,
 * marcado de errores, avisos y llamadas HTTP con el contrato de los endpoints.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirExito, type RespuestaApi } from '../comun/pagina.ts';
import { tieneOficial } from './logica.ts';

/** data-produccion-urdido='@json(...)' de produccion/_scripts.blade.php. */
export interface ConfigProduccion {
    ordenId: number | null;
    maxKgNeto: number | null;
    esKarlMayer: boolean;
    puedeEditar: boolean;
    rutas: {
        catalogoJulios: string;
        usuarios: string;
        guardarOficial: string;
        eliminarOficial: string;
        actualizarFecha: string;
        actualizarJulioTara: string;
        actualizarKgBruto: string;
        actualizarCampo: string;
        actualizarHoras: string;
        marcarListo: string;
        finalizar: string;
        pdf: string | null;
        salida: string;
    };
}

export const ctx: { cfg: ConfigProduccion; tabla: HTMLElement | null } = {
    cfg: {
        ordenId: null,
        maxKgNeto: null,
        esKarlMayer: false,
        puedeEditar: false,
        rutas: {
            catalogoJulios: '',
            usuarios: '',
            guardarOficial: '',
            eliminarOficial: '',
            actualizarFecha: '',
            actualizarJulioTara: '',
            actualizarKgBruto: '',
            actualizarCampo: '',
            actualizarHoras: '',
            marcarListo: '',
            finalizar: '',
            pdf: null,
            salida: '/produccionProceso',
        },
    },
    tabla: null,
};

// ─── Avisos ─────────────────────────────────────────────────────────

export function alerta(icono: 'warning' | 'error' | 'info' | 'success', titulo: string, texto: string): void {
    void notify.alert(texto, titulo, icono);
}

export function requireCanEdit(): boolean {
    if (!ctx.cfg.puedeEditar) {
        notify.warning('No tiene permiso para modificar en este módulo');
        return false;
    }
    return true;
}

export function avisoOficialRequerido(): void {
    notify.warning('Debes seleccionar un oficial antes de actualizar este campo');
}

const MSG_PARCIAL = 'Este registro ya está parcialmente finalizado. Desmarca la casilla para editarlo.';

export function avisoFilaParcial(): void {
    notify.info(MSG_PARCIAL);
}

// ─── Filas ──────────────────────────────────────────────────────────

export function filaDe(registroId: string): HTMLTableRowElement | null {
    return document.querySelector<HTMLTableRowElement>(`tr[data-registro-id="${CSS.escape(registroId)}"]`);
}

export function campo<T extends HTMLElement = HTMLInputElement>(fila: ParentNode, nombre: string): T | null {
    return fila.querySelector<T>(`[data-field="${nombre}"]`);
}

export function marcarCampoError(elemento: Element | null | undefined, tieneError: boolean): void {
    if (!elemento) return;
    if (tieneError) {
        elemento.classList.add('border-red-500', 'border-2');
        elemento.classList.remove('border-gray-300');
    } else {
        elemento.classList.remove('border-red-500', 'border-2');
        elemento.classList.add('border-gray-300');
    }
}

export function verificarOficialSeleccionado(registroId: string): boolean {
    const texto = filaDe(registroId)?.querySelector('.oficial-texto')?.textContent;
    return tieneOficial(texto);
}

export function esFilaBloqueada(registroId: string): boolean {
    return filaDe(registroId)?.querySelector<HTMLInputElement>('.checkbox-finalizar')?.checked === true;
}

export function verificarFilaNoFinalizada(registroId: string): boolean {
    if (esFilaBloqueada(registroId)) {
        avisoFilaParcial();
        return false;
    }
    return true;
}

// ─── HTTP ───────────────────────────────────────────────────────────

/** POST con el contrato de los endpoints del módulo (success:false → ErrorApi). */
export async function enviar<T extends RespuestaApi = RespuestaApi>(url: string, datos: unknown, porDefecto: string): Promise<T> {
    return exigirExito(await http.post<T>(url, datos), porDefecto);
}
