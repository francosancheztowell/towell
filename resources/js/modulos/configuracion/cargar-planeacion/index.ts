/**
 * Configuración › Cargar planeación (Excel → ReqProgramaTejido).
 * Vista: resources/views/modulos/configuracion/cargar-planeacion.blade.php; las rutas van en
 * data-* del <form id="excelForm">.
 */
import { escapeHtml } from '../../../utils/format.ts';
import { onReady } from '../../../utils/dom.ts';

interface Resultado {
    success?: boolean;
    message?: string;
    error?: string;
    [conteo: string]: unknown;
}

const TIPOS_EXCEL = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
const MAX_BYTES = 10 * 1024 * 1024;
const TEXTO_BOTON = '<i class="fas fa-upload mr-1"></i>Procesar Excel';

function resumen(data: Resultado, actualizacion: boolean): string {
    const n = (k: string): string => escapeHtml(String(data[k] || 0));
    const fila = (etiqueta: string, k: string): string => `<p><strong>${etiqueta}:</strong> ${n(k)}</p>`;
    const conteos = actualizacion
        ? [fila('Registros procesados', 'processed'), fila('Registros actualizados', 'updated')]
        : [fila('Registros eliminados', 'deleted'), fila('Registros procesados', 'processed')];
    return `<div class="text-left">
        ${conteos.join('')}
        ${fila('Registros creados', 'created')}
        ${fila('Registros omitidos', 'skipped')}
        <hr class="my-3">
        ${fila('Total antes', 'total_before')}
        ${fila('Total después', 'total_after')}
        ${actualizacion ? '<p class="text-xs text-gray-500 mt-2">Nota: Las fechas y EnProceso fueron recalculadas para cada telar.</p>' : ''}
    </div>`;
}

/** Mensaje del fallo: el servidor manda message o error; sin JSON, el texto crudo. */
function mensajeFallo(err: unknown): string {
    const e = err as { status?: number; data?: unknown; message?: string };
    if (!e.status) return e.message || 'Error desconocido';
    const cuerpo = (e.data ?? {}) as Resultado;
    const detalle = cuerpo.message || cuerpo.error || (typeof e.data === 'string' ? e.data : '') || e.message;
    return `HTTP ${e.status} - ${detalle}`;
}

onReady(() => {
    const form = document.getElementById('excelForm') as HTMLFormElement | null;
    if (!form) return;
    const archivo = document.getElementById('excel_file') as HTMLInputElement;
    const modo = document.getElementById('modoActualizacion') as HTMLInputElement;
    const boton = document.getElementById('uploadBtn') as HTMLButtonElement;
    const progreso = document.getElementById('progress-section')!;
    const barra = document.getElementById('progress-bar')!;
    const textoProgreso = document.getElementById('progress-text')!;
    const { rutaUpload = '', rutaUpdate = '', rutaPrograma = '' } = form.dataset;

    document.querySelector('[data-volver]')?.addEventListener('click', () => history.back());

    modo.addEventListener('change', () => {
        document.getElementById('modo-texto')!.textContent = modo.checked
            ? 'Datos se actualizarán en la tabla ReqProgramaTejido (NO se eliminarán registros existentes)'
            : 'Datos se importarán a la tabla ReqProgramaTejido (se eliminarán registros existentes)';
    });

    archivo.addEventListener('change', () => {
        const file = archivo.files?.[0];
        document.getElementById('file-info')!.classList.toggle('hidden', !file);
        boton.disabled = !file;
        if (!file) return;
        document.getElementById('file-name')!.textContent = file.name;
        document.getElementById('file-size')!.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const file = archivo.files?.[0];
        if (!file) return void notify.alert('Por favor selecciona un archivo Excel', 'Archivo requerido', 'warning');
        if (!TIPOS_EXCEL.includes(file.type)) {
            return void notify.alert('Por favor selecciona un archivo Excel válido (.xlsx o .xls)', 'Tipo de archivo inválido', 'error');
        }
        if (file.size > MAX_BYTES) {
            return void notify.alert('El archivo es demasiado grande. Máximo 10MB permitido.', 'Archivo demasiado grande', 'error');
        }

        const actualizacion = modo.checked;
        progreso.classList.remove('hidden');
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';

        // Progreso simulado hasta 90 %: el servidor no reporta avance.
        let avance = 0;
        const intervalo = window.setInterval(() => {
            avance = Math.min(avance + Math.random() * 15, 90);
            barra.style.width = `${avance}%`;
        }, 200);

        const datos = new FormData();
        datos.append('excel_file', file);
        try {
            const data = await http.upload<Resultado | string>(actualizacion ? rutaUpdate : rutaUpload, datos);
            if (typeof data !== 'object' || data === null) throw new Error(`Respuesta no es JSON válido: ${String(data).slice(0, 200)}`);
            if (!data.success) throw new Error(data.message || 'Error al procesar el archivo');
            window.clearInterval(intervalo);
            barra.style.width = '100%';
            textoProgreso.textContent = '¡Archivo procesado exitosamente!';
            barra.classList.replace('bg-blue-600', 'bg-green-600');
            window.setTimeout(async () => {
                const titulo = actualizacion ? '¡Archivo actualizado exitosamente!' : '¡Archivo procesado exitosamente!';
                await notify.html(resumen(data, actualizacion), titulo, 'success');
                window.location.href = rutaPrograma;
            }, 1000);
        } catch (err) {
            window.clearInterval(intervalo);
            textoProgreso.textContent = 'Error al procesar el archivo';
            barra.classList.replace('bg-blue-600', 'bg-red-600');
            void notify.alert(mensajeFallo(err), 'Error al procesar el archivo', 'error');
            boton.disabled = false;
            boton.innerHTML = TEXTO_BOTON;
        }
    });
});
