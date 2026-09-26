/**
 * Producción Engomado — "Imprimir producción parcial" y "Finalizar".
 */
import { HttpError, http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { cfg, requireCanEdit } from './contexto.ts';
import { validarRegistrosCompletos } from './validacion.ts';

const urlPdf = (ordenId: number, parcial: boolean): string =>
    `${cfg().rutas.pdf}?orden_id=${encodeURIComponent(ordenId)}&tipo=engomado${parcial ? '&parcial=1' : ''}`;

/** Mensaje de error de una respuesta blob (el PDF responde JSON cuando falla). */
async function mensajeDeBlob(err: unknown, porDefecto: string): Promise<string> {
    if (err instanceof HttpError && err.data instanceof Blob) {
        try {
            const cuerpo = JSON.parse(await err.data.text()) as RespuestaApi;
            return cuerpo.message || cuerpo.error || porDefecto;
        } catch {
            return porDefecto;
        }
    }
    return err instanceof HttpError && err.status === 0 ? 'Error de conexión al generar el PDF.' : mensajeError(err, porDefecto);
}

export async function imprimirProduccionParcial(): Promise<void> {
    const btn = document.getElementById('btn-imprimir-parcial') as HTMLButtonElement | null;
    if (btn?.disabled) return;
    const orden = cfg().orden;
    if (!orden) {
        notify.warning('No hay orden seleccionada');
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.title = 'Generando PDF...';
    }
    try {
        const blob = await http.get<Blob>(urlPdf(orden.id, true), { responseType: 'blob' });
        window.open(URL.createObjectURL(blob), '_blank');
        // Queda deshabilitado: los registros ya tienen Impresion=1 en BD.
        if (btn) btn.title = 'Registros ya impresos. Marca nuevos como "Listo" para habilitar.';
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.title = 'Imprimir producción parcial';
        }
        void notify.alert(await mensajeDeBlob(err, 'No se pudo generar el PDF.'), 'Error', 'error');
    }
}

export async function finalizar(): Promise<void> {
    if (!requireCanEdit()) return;

    const faltantes = validarRegistrosCompletos();
    if (faltantes.length) {
        const primerError = document.querySelector<HTMLElement>('.border-red-500');
        if (primerError) {
            primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => primerError.focus(), 500);
        }
        void notify.validation({ campos: faltantes }, 'Registros incompletos');
        return;
    }

    const orden = cfg().orden;
    if (!orden) {
        notify.warning('No hay orden seleccionada');
        return;
    }

    try {
        const url = `${cfg().rutas.verificarFormulaciones}?folio=${encodeURIComponent(orden.folio)}`;
        const r = await http.get<RespuestaApi & { tieneFormulaciones?: boolean }>(url);
        if (!r.success || !r.tieneFormulaciones) {
            notify.error(`No se puede finalizar. Debe existir al menos una formulación con el Folio ${orden.folio} antes de finalizar.`);
            return;
        }
    } catch (err) {
        void notify.alert(mensajeError(err, 'Error al verificar formulaciones. Por favor, intenta nuevamente.'), 'Error', 'error');
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

    void notify.loading('Finalizando...');
    try {
        exigirExito(await http.post<RespuestaApi>(cfg().rutas.finalizar, { orden_id: orden.id }), 'Error al finalizar el registro');
        window.open(urlPdf(orden.id, false), '_blank');
        notify.close();
        notify.success('El registro ha sido marcado como finalizado y el PDF se ha generado');
        window.setTimeout(() => window.location.assign(cfg().rutas.salida), 2000);
    } catch (err) {
        notify.close();
        void notify.alert(mensajeError(err, 'Error al finalizar el registro. Por favor, intenta nuevamente.'), 'Error', 'error');
    }
}
