/**
 * Reporte "OT x Fecha" (mecánicos).
 * Vista: resources/views/modulos/mecanicos/reportes/ot-fecha.blade.php.
 * Mantiene el rango válido (la fecha final no puede quedar antes de la inicial), solo
 * habilita Consultar con ambas fechas, y exporta Excel/PDF o genera la imagen para Telegram
 * con el rango y el mecánico consultados (los del form oculto, no los del filtro sin enviar).
 */
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { type Campos, descargarYEnviarImagen } from '../comun/imagen.ts';

function filtrosConsultados(form: HTMLFormElement): Campos {
    return qsa<HTMLInputElement>('input[name]', form)
        .filter((input) => input.name !== '_token')
        .map((input): [string, string] => [input.name, input.value]);
}

onReady(() => {
    const raiz = qs('#reporte-ot-fecha');
    const desde = qs<HTMLInputElement>('#desde');
    const hasta = qs<HTMLInputElement>('#hasta');
    const consultar = qs<HTMLButtonElement>('#btn-consultar');
    if (!raiz || !desde || !hasta || !consultar) return;

    const actualizar = (): void => {
        hasta.min = desde.value;
        if (desde.value && hasta.value && hasta.value < desde.value) hasta.value = desde.value;
        consultar.disabled = !desde.value || !hasta.value;
    };

    desde.addEventListener('change', actualizar);
    hasta.addEventListener('change', actualizar);
    actualizar();

    const d = raiz.dataset;

    delegate<HTMLButtonElement>(document, 'click', '[data-ot-fecha-descargar]', (_e, boton) => {
        const form = qs<HTMLFormElement>('#form-export-ot-fecha');
        if (!form) return;
        form.action = (boton.dataset.otFechaDescargar === 'pdf' ? d.pdfUrl : d.excelUrl) ?? '';
        form.submit();
    });

    delegate(document, 'click', '[data-ot-fecha-imagen]', () => {
        const hoja = qs('#hoja-ot-fecha');
        const form = qs<HTMLFormElement>('#form-export-ot-fecha');
        if (!hoja || !form) return;
        void descargarYEnviarImagen(hoja, d.nombreImagen ?? 'ot-fecha.png', d.imagenUrl ?? '', filtrosConsultados(form));
    });
});
