/**
 * Reporte "Hoja de Verificación Estado Máquina" (mecánicos).
 * Vista: resources/views/modulos/mecanicos/reportes/estado-maquina.blade.php.
 * Carga las semanas del mes elegido, exporta Excel/PDF con las prioridades capturadas
 * y genera la imagen para Telegram.
 */
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { type Campos, descargarYEnviarImagen, enviarExportacion } from '../comun/imagen.ts';

interface Semana {
    lunes: string;
    etiqueta: string;
}

function prioridades(): Campos {
    return qsa<HTMLInputElement>('.js-prioridad')
        .map((input): [string, string] => [`prioridades[${input.dataset.actividadId ?? ''}]`, input.value.trim()])
        .filter(([, valor]) => valor === '1' || valor === '2' || valor === '3');
}

onReady(() => {
    const raiz = qs('#reporte-estado-maquina');
    const mes = qs<HTMLInputElement>('#mes');
    const semana = qs<HTMLSelectElement>('#semana');
    const consultar = qs<HTMLButtonElement>('#btn-consultar');
    if (!raiz || !mes || !semana || !consultar) return;
    const d = raiz.dataset;

    const actualizarConsultar = (): void => {
        consultar.disabled = !mes.value || !semana.value;
    };

    const cargarSemanas = async (valorMes: string, seleccionar: string | null): Promise<void> => {
        semana.replaceChildren(new Option('Seleccione una semana', ''));
        semana.disabled = true;
        actualizarConsultar();
        if (!valorMes) return;

        try {
            const data = await http.get<{ semanas?: Semana[] }>(d.semanasUrl ?? '', { params: { mes: valorMes } });
            (data.semanas ?? []).forEach((s) => {
                semana.add(new Option(s.etiqueta, s.lunes, false, seleccionar === s.lunes));
            });
            semana.disabled = false;
        } catch (err) {
            notify.error(err instanceof Error && err.message ? err.message : 'No se pudieron cargar las semanas');
        }
        actualizarConsultar();
    };

    mes.addEventListener('change', () => void cargarSemanas(mes.value, null));
    semana.addEventListener('change', actualizarConsultar);
    actualizarConsultar();

    if (d.mes && semana.options.length <= 1) {
        void cargarSemanas(d.mes, d.semana ?? null);
    }

    delegate<HTMLButtonElement>(document, 'click', '[data-em-descargar]', (_e, boton) => {
        const form = qs<HTMLFormElement>('#form-export-estado-maquina');
        const holder = qs('#prioridades-export');
        if (!form || !holder) return;
        const url = boton.dataset.emDescargar === 'pdf' ? d.pdfUrl : d.excelUrl;
        enviarExportacion(form, holder, url ?? '', prioridades());
    });

    delegate(document, 'click', '[data-em-imagen]', () => {
        const hoja = qs('#hoja-verificacion');
        if (!hoja) return;
        void descargarYEnviarImagen(hoja, d.nombreImagen ?? 'hoja-verificacion-estado-maquina.png', d.imagenUrl ?? '', [
            ['mes', d.mes ?? ''],
            ['semana', d.semana ?? ''],
        ]);
    });
});
