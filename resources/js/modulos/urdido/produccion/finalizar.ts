/**
 * Finalizar la orden: valida todas las filas, confirma, pide confirmación extra si el servidor
 * va a descartar filas con captura incompleta (422 + requiere_confirmacion), abre el PDF para
 * imprimir y regresa a Producción en Proceso.
 */
import { HttpError, http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { escapeHtml } from '../../../utils/format.ts';
import { mensajeError, type RespuestaApi } from '../comun/pagina.ts';
import { alerta, ctx, marcarCampoError, requireCanEdit } from './contexto.ts';
import { camposDeFila } from './filas.ts';
import { SELECTOR_CAMPO, camposFaltantes } from './logica.ts';

export const SIN_ORDEN = 'No hay orden seleccionada';

interface RespuestaFinalizar extends RespuestaApi {
    requiere_confirmacion?: boolean;
    registros_a_descartar?: number;
}

/** Marca en rojo lo que falta en todas las filas. true = todo completo. */
function validarRegistrosCompletos(): boolean {
    const tbody = ctx.tabla;
    if (!tbody) return false;
    tbody.querySelectorAll('input, select').forEach((c) => marcarCampoError(c, false));

    let completo = true;
    tbody.querySelectorAll<HTMLTableRowElement>('tr[data-registro-id]').forEach((fila) => {
        const faltan = camposFaltantes(camposDeFila(fila), ctx.cfg.esKarlMayer, ctx.cfg.maxKgNeto);
        if (!faltan.length) return;
        completo = false;
        for (const campo of faltan) {
            const selector = SELECTOR_CAMPO[campo];
            if (selector) marcarCampoError(fila.querySelector(selector), true);
        }
    });
    return completo;
}

/** Abre el PDF en otra pestaña y lanza el diálogo de impresión cuando carga. */
function abrirPDFParaImprimir(url: string): void {
    const ventana = window.open(url, '_blank');
    if (!ventana) return;
    ventana.onload = () => setTimeout(() => ventana.print(), 500);
    // Respaldo por si onload no llega (visor de PDF del navegador).
    setTimeout(() => {
        try {
            ventana.print();
        } catch {
            setTimeout(() => {
                try {
                    ventana.print();
                } catch {
                    console.error('No se pudo abrir el diálogo de impresión automáticamente');
                }
            }, 1000);
        }
    }, 1000);
}

/** El 422 con requiere_confirmacion es parte del contrato: se devuelve como respuesta. */
async function enviarFinalizar(ordenId: number, confirmarDescarte: boolean): Promise<RespuestaFinalizar> {
    try {
        return await http.post<RespuestaFinalizar>(ctx.cfg.rutas.finalizar, { orden_id: ordenId, confirmar_descarte: confirmarDescarte });
    } catch (err) {
        if (err instanceof HttpError && err.status === 422 && (err.data as RespuestaFinalizar | null)?.requiere_confirmacion) {
            return err.data as RespuestaFinalizar;
        }
        throw err;
    }
}

export async function finalizar(): Promise<void> {
    if (!requireCanEdit()) return;
    if (!validarRegistrosCompletos()) {
        alerta('warning', 'Registros incompletos', 'Completa todos los registros y corrige los errores');
        return;
    }

    const ordenId = ctx.cfg.ordenId;
    if (ordenId === null) {
        alerta('warning', 'Aviso', SIN_ORDEN);
        return;
    }

    const confirmado = await notify.confirm({
        title: '¿Finalizar registro?',
        text: 'Esta acción marcará el registro como finalizado y generará el PDF',
        icon: 'question',
        confirmText: 'Sí, finalizar',
        confirmColor: '#2563eb',
    });
    if (!confirmado) return;

    try {
        void notify.loading('Finalizando...');
        let r = await enviarFinalizar(ordenId, false);

        if (!r.success && r.requiere_confirmacion) {
            notify.close();
            const descartar = await notify.confirm({
                title: 'Hay registros incompletos',
                html: `Se descartarán <b>${escapeHtml(r.registros_a_descartar ?? 0)}</b> registro(s) sin Hora Inicial u Hora Final.<br>Esta acción no se puede deshacer.`,
                confirmText: 'Descartar y finalizar',
                cancelText: 'Volver a revisar',
                confirmColor: '#dc2626',
            });
            if (!descartar) return;
            void notify.loading('Finalizando...');
            r = await enviarFinalizar(ordenId, true);
        }

        notify.close();
        if (!r.success) {
            alerta('error', 'Error', r.error || r.message || 'Error al finalizar el registro');
            return;
        }
        if (ctx.cfg.rutas.pdf) abrirPDFParaImprimir(ctx.cfg.rutas.pdf);
        notify.success('Registro finalizado: el PDF se ha generado');
        setTimeout(() => window.location.assign(ctx.cfg.rutas.salida), 2000);
    } catch (err) {
        console.error('Error al finalizar:', err);
        notify.close();
        alerta('error', 'Error', mensajeError(err, 'Error al finalizar el registro. Por favor, intenta nuevamente.'));
    }
}
