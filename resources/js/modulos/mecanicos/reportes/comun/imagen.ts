/**
 * Reportes de mecánicos (Estado de Máquina y OT Diarias): botones Excel/PDF e Imagen.
 * La imagen se captura con html2canvas, se descarga como PNG y se manda por Telegram
 * junto con `extras` (los filtros/capturas del reporte).
 */
import { http } from '../../../../utils/http.ts';
import { librerias } from '../../../../utils/librerias.ts';
import { notify } from '../../../../utils/notifications.ts';

export type Campos = [string, string][];

export async function descargarYEnviarImagen(hoja: HTMLElement, nombre: string, url: string, extras: Campos): Promise<void> {
    void notify.loading('Generando imagen...');
    try {
        const html2canvas = await librerias.html2canvas();
        const canvas = await html2canvas(hoja, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            width: hoja.scrollWidth,
            windowWidth: Math.max(hoja.scrollWidth, hoja.clientWidth),
        });
        const blob = await new Promise<Blob>((resolve, reject) => {
            canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('No se pudo crear la imagen.'))), 'image/png');
        });
        const enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(blob);
        enlace.download = nombre;
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
        URL.revokeObjectURL(enlace.href);

        const datos = new FormData();
        datos.append('imagen', new File([blob], nombre, { type: 'image/png' }), nombre);
        extras.forEach(([campo, valor]) => datos.append(campo, valor));
        try {
            await http.upload(url, datos);
        } catch {
            notify.warning('La imagen se descargó, pero no se pudo enviar por Telegram.');
            return;
        }
        notify.success('Imagen descargada y enviada por Telegram.');
    } catch (err) {
        notify.error(err instanceof Error && err.message ? err.message : 'No se pudo generar la imagen.');
    } finally {
        notify.close();
    }
}

/** Llena el form oculto de exportación con `campos` (name, valor) y lo envía a `url`. */
export function enviarExportacion(form: HTMLFormElement, holder: HTMLElement, url: string, campos: Campos): void {
    holder.replaceChildren(
        ...campos.map(([name, value]) => Object.assign(document.createElement('input'), { type: 'hidden', name, value })),
    );
    form.action = url;
    form.submit();
}
