/**
 * Visualizar Cortes de Eficiencia por fecha (19-02). Vista: modulos/cortes-eficiencia/visualizar-cortes-eficiencia.blade.php.
 * Excel, PDF, imagen (PDF → pdf.js → JPEG, se descarga y se manda por Telegram) y Telegram con el PDF.
 */
import { delegate, onReady, qs } from '../../../../utils/dom.ts';
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { exigirExito, leerDatos, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import { descargarBlob, ocupado, pdfAJpeg, pedirArchivo } from '../comun/archivos.ts';

interface ConfigVisualizar {
    fecha: string;
    rutas: { excel: string; pdf: string; telegram: string; telegramImagen: string };
}

function iniciar(): void {
    const raiz = qs('#pagina-visualizar');
    const cfg = leerDatos<ConfigVisualizar>(raiz);
    if (!raiz || !cfg) return;
    const { fecha, rutas } = cfg;
    // Un clic a la vez por botón (evita dobles envíos a Telegram).
    const enCurso = new Set<string>();

    const pdf = (): Promise<Blob> => pedirArchivo('post', rutas.pdf, { fecha });

    async function excel(): Promise<void> {
        try {
            descargarBlob(await pedirArchivo('post', rutas.excel, { fecha }), `cortes_eficiencia_${fecha}.xlsx`);
        } catch (err) {
            notify.error(mensajeError(err, 'No se pudo exportar el Excel.'));
        }
    }

    async function descargarPdf(): Promise<void> {
        try {
            descargarBlob(await pdf(), `cortes_eficiencia_${fecha}.pdf`);
        } catch (err) {
            notify.error(mensajeError(err, 'Ocurrió un error al intentar descargar el PDF.'));
        }
    }

    async function compartirImagen(boton: HTMLButtonElement): Promise<void> {
        const restaurar = ocupado(boton, 'Generando...');
        try {
            const imagen = await pdfAJpeg(await pdf(), { escala: 2.2, calidad: 0.92, todasLasPaginas: false });
            const nombre = `cortes_eficiencia_${fecha}.jpg`;
            descargarBlob(imagen, nombre);

            ocupado(boton, 'Enviando Telegram...');
            const datos = new FormData();
            datos.append('imagen', new File([imagen], nombre, { type: 'image/jpeg' }), nombre);
            datos.append('fecha', fecha);
            const r = exigirExito(await http.upload<RespuestaApi>(rutas.telegramImagen, datos), 'No se pudo enviar la imagen por Telegram.');
            notify.success(r.message ? `Imagen descargada. ${r.message}` : 'Imagen descargada y enviada por Telegram exitosamente.');
        } catch (err) {
            notify.error(mensajeError(err, 'No se pudo generar/enviar la imagen.'));
        } finally {
            restaurar();
        }
    }

    async function telegram(boton: HTMLButtonElement): Promise<void> {
        const restaurar = ocupado(boton, 'Enviando...');
        try {
            const r = exigirExito(await http.post<RespuestaApi>(rutas.telegram, { fecha }), 'No se pudo enviar la notificación por Telegram.');
            notify.success(r.message || 'Reporte enviado por Telegram exitosamente.');
        } catch (err) {
            notify.error(mensajeError(err, 'Ocurrió un error al intentar enviar la notificación por Telegram.'));
        } finally {
            restaurar();
        }
    }

    const acciones: Record<string, (boton: HTMLButtonElement) => Promise<void>> = {
        excel,
        pdf: descargarPdf,
        imagen: compartirImagen,
        telegram,
    };

    delegate<HTMLButtonElement>(raiz, 'click', 'button[data-accion]', (_ev, boton) => {
        const nombre = boton.dataset.accion ?? '';
        const accion = acciones[nombre];
        if (!accion || enCurso.has(nombre)) return;
        enCurso.add(nombre);
        void accion(boton).finally(() => enCurso.delete(nombre));
    });
}

onReady(iniciar);
