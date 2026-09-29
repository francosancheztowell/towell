/** Botones del navbar de la captura: imagen del corte, Telegram y Finalizar. */
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { alertaError, exigirExito, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import { descargarBlob, ocupado, pdfAJpeg, pedirArchivo } from '../comun/archivos.ts';
import { confirmarFinalizar, finalizarFolio, validarParaFinalizar } from '../comun/finalizar.ts';
import { actualizarEstadoBotonesHeader, botones, cfg, estado, rutaFolio } from './estado.ts';
import { guardarEnServidor } from './guardado.ts';
import { aplicarModoSoloLectura } from './tabla.ts';

export async function notificarTelegram(): Promise<void> {
    if (!estado.folio) {
        void notify.alert('Aún no hay un folio activo para notificar.', 'Sin folio', 'warning');
        return;
    }
    const btn = botones.telegram();
    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-60');
    }
    try {
        await guardarEnServidor(false);
        const data = exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.notificarTelegram, { folio: estado.folio, fecha: estado.fecha || '' }),
            'No se pudo enviar la notificación por Telegram.',
        );
        await notify.alert(data.message || 'Reporte enviado por Telegram.', 'Éxito', 'success');
    } catch (err) {
        alertaError(err, 'No se pudo notificar por Telegram.');
    } finally {
        btn?.classList.remove('opacity-60');
        actualizarEstadoBotonesHeader();
    }
}

export async function finalizar(): Promise<void> {
    const folio = estado.folio;
    if (!folio) {
        void notify.alert('Aún no hay un folio activo para finalizar.', 'Sin folio', 'warning');
        return;
    }
    if (estado.status === 'Finalizado') {
        void notify.alert(`El folio ${folio} ya está finalizado.`, 'Ya finalizado', 'info');
        return;
    }
    try {
        await guardarEnServidor(false);
        if (!(await validarParaFinalizar(rutaFolio(cfg().rutas.corte, folio)))) return;
        if (!(await confirmarFinalizar(folio))) return;
        if (await finalizarFolio(rutaFolio(cfg().rutas.finalizar, folio), 'El folio se finalizó correctamente.')) {
            estado.status = 'Finalizado';
            aplicarModoSoloLectura();
            actualizarEstadoBotonesHeader();
        }
    } catch (err) {
        alertaError(err, 'No se pudo finalizar el folio.');
    }
}

/** Imagen del corte: PDF del servidor → pdf.js (todas las páginas) → JPEG. */
export async function capturarImagen(): Promise<void> {
    const folio = estado.folio;
    if (!folio) {
        notify.warning('Sin folio activo: guarda el corte antes de descargar la imagen.');
        return;
    }
    const restaurar = ocupado(botones.imagen());
    try {
        const pdf = await pedirArchivo('get', rutaFolio(cfg().rutas.pdf, folio));
        const imagen = await pdfAJpeg(pdf, { escala: 2.5, calidad: 0.93, todasLasPaginas: true });
        descargarBlob(imagen, `corte_eficiencia_${folio}_${estado.fecha || 'captura'}.jpg`);
        notify.success('Imagen descargada');
    } catch (err) {
        notify.error(`No se pudo generar la imagen: ${mensajeError(err, 'error al generar el PDF')}`);
    } finally {
        restaurar();
    }
}
