/**
 * Reporte de Marcas Finales por fecha (19-02): Excel, PDF y envío por Telegram.
 * Vista: resources/views/modulos/marcas-finales/reporte-marcas.blade.php (config en data-pagina).
 */
import { delegate, onReady, qs } from '../../../../utils/dom.ts';
import { csrfToken, http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { exigirExito, leerDatos, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import { nombrePdf } from './logica.ts';

interface ConfigReporte {
    fecha: string;
    rutas: { excel: string; pdf: string; telegram: string };
}

function iniciar(): void {
    const raiz = qs('#pagina-marcas-reporte');
    const cfg = leerDatos<ConfigReporte>(raiz);
    if (!raiz || !cfg) return;

    /** Excel: POST de formulario normal para que el navegador descargue el archivo. */
    function exportarExcel(): void {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = cfg!.rutas.excel;
        for (const [name, value] of [['_token', csrfToken()], ['fecha', cfg!.fecha]] as const) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    async function descargarPdf(boton: HTMLButtonElement): Promise<void> {
        boton.disabled = true;
        try {
            const blob = await http.post<Blob>(cfg!.rutas.pdf, new URLSearchParams({ fecha: cfg!.fecha }), {
                responseType: 'blob',
                headers: { Accept: 'application/pdf' },
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nombrePdf(cfg!.fecha);
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch {
            notify.error('No se pudo generar el PDF.');
        } finally {
            boton.disabled = false;
        }
    }

    async function notificarTelegram(boton: HTMLButtonElement): Promise<void> {
        const original = Array.from(boton.childNodes, (n) => n.cloneNode(true));
        const icono = document.createElement('i');
        icono.className = 'fa fa-spinner fa-spin mr-2';
        icono.setAttribute('aria-hidden', 'true');
        boton.disabled = true;
        boton.replaceChildren(icono, ' Enviando...');
        try {
            const d = exigirExito(
                await http.post<RespuestaApi>(cfg!.rutas.telegram, new URLSearchParams({ fecha: cfg!.fecha })),
                'No se pudo enviar la notificación por Telegram.',
            );
            notify.success(d.message || 'Reporte enviado por Telegram exitosamente.');
        } catch (err) {
            notify.error(mensajeError(err, 'No se pudo enviar la notificación por Telegram.'));
        } finally {
            boton.disabled = false;
            boton.replaceChildren(...original);
        }
    }

    const acciones: Record<string, (boton: HTMLButtonElement) => void> = {
        excel: () => exportarExcel(),
        pdf: (b) => void descargarPdf(b),
        telegram: (b) => void notificarTelegram(b),
    };
    delegate<HTMLButtonElement>(raiz, 'click', '[data-accion]', (ev, boton) => {
        const accion = acciones[boton.dataset.accion ?? ''];
        if (!accion || boton.disabled) return;
        ev.preventDefault();
        accion(boton);
    });
}

onReady(iniciar);
