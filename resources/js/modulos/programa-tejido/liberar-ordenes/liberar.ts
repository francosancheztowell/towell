/**
 * Liberar Órdenes — selección de renglones, validaciones previas y POST de liberar.
 */
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { faltaLMat, mensajeLiberar, sinComas, validarMetricasProduccion, type ConfigLiberar, type RegistroLiberar } from './logica.ts';

interface RespuestaLiberar {
    success?: boolean;
    message?: string;
    fileData?: string;
    fileName?: string;
    redirectUrl?: string;
}

/** Casillas de selección disponibles (se excluyen las deshabilitadas). */
function checkboxesSeleccionables(): HTMLInputElement[] {
    return Array.from(document.querySelectorAll<HTMLInputElement>('.row-checkbox')).filter((cb) => !cb.disabled);
}

export function toggleSeleccionarTodo(): void {
    const checkboxes = checkboxesSeleccionables();
    if (!checkboxes.length) return;
    const nuevoEstado = !checkboxes.every((cb) => cb.checked);
    checkboxes.forEach((cb) => { cb.checked = nuevoEstado; });
    const selectAll = document.getElementById('selectAllCheckbox') as HTMLInputElement | null;
    if (selectAll) selectAll.checked = nuevoEstado;
}

export function updateSelectAllCheckbox(): void {
    const selectAll = document.getElementById('selectAllCheckbox') as HTMLInputElement | null;
    if (!selectAll) return;
    const checkboxes = checkboxesSeleccionables();
    selectAll.checked = checkboxes.length > 0 && checkboxes.every((cb) => cb.checked);
}

/** Valor de una celda: span calculado, select, input o texto; null si está vacía. */
function valorCelda(row: Element | null, columnName: string): string | null {
    const cell = row ? row.querySelector(`[data-column="${columnName}"]`) : null;
    if (!cell) return null;

    const spanCalcColumns = ['Repeticiones', 'SaldoMarbete', 'MtsRollo', 'PzasRollo', 'TotalPzas', 'NoTiras'];
    if (spanCalcColumns.includes(columnName)) {
        const span = cell.querySelector('span');
        if (span && span.hasAttribute('data-calculated-value')) return span.getAttribute('data-calculated-value');
    }
    const control = cell.querySelector<HTMLSelectElement | HTMLInputElement>('select, input');
    if (control) {
        const value = control.value ? control.value.trim() : '';
        return value === '' ? null : value;
    }
    const text = cell.textContent ? cell.textContent.trim() : '';
    return text === '' ? null : text;
}

const datoFila = (valor: string | undefined) => (valor != null && String(valor).trim() !== '' ? String(valor).replace(/,/g, '').trim() : null);

/**
 * Renglones marcados y visibles. Una fila oculta por un filtro no se libera: las casillas
 * vienen marcadas por defecto y filtrar sólo aplica display:none.
 */
export function obtenerRegistrosSeleccionados(): RegistroLiberar[] {
    return Array.from(document.querySelectorAll<HTMLInputElement>('.row-checkbox:checked'))
        .filter((cb) => {
            const row = cb.closest<HTMLElement>('tr.row-data');
            return !!row && row.style.display !== 'none';
        })
        .map((cb) => {
            const row = cb.closest<HTMLElement>('tr');
            const celda = (c: string) => valorCelda(row, c);
            const numero = (c: string) => sinComas(celda(c));
            const flogCheck = row?.querySelector<HTMLInputElement>('.flog-check');
            return {
                id: cb.getAttribute('data-id'),
                prioridad: row?.querySelector<HTMLInputElement>('.prioridad-input')?.value.trim() ?? '',
                saldoPedido: datoFila(row?.dataset.saldoPedido),
                noTiras: datoFila(row?.dataset.noTiras) ?? numero('NoTiras'),
                codigoDibujo: celda('CodigoDibujo'),
                bomId: celda('BomId'),
                bomName: celda('BomName'),
                hiloAX: celda('HiloAX'),
                pesoRollo: numero('PesoRollo'),
                repeticiones: numero('Repeticiones'),
                saldoMarbete: numero('SaldoMarbete'),
                mtsRollo: numero('MtsRollo'),
                pzasRollo: numero('PzasRollo'),
                totalRollos: numero('TotalRollos'),
                totalPzas: numero('TotalPzas'),
                densidad: numero('Densidad'),
                observaciones: celda('Observaciones'),
                cambioRepaso: celda('CambioRepaso'),
                combinaTram: celda('CombinaTrama'),
                noProduccion: celda('no_produccion'),
                // null = el renglón no decide (no está en el catálogo): el servidor no toca AsignarFlogs.
                asignarFlogs: flogCheck ? flogCheck.checked : null,
                flogsId: row ? (row.querySelector<HTMLInputElement>('.flog-input')?.value || '').trim() || null : null,
            };
        });
}

function descargarExcelBase64(data: string, fileName?: string): void {
    const bytes = Uint8Array.from(atob(data), (c) => c.charCodeAt(0));
    const blob = new Blob([bytes], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const link = document.createElement('a');
    const blobUrl = window.URL.createObjectURL(blob);
    link.href = blobUrl;
    link.download = fileName || 'liberar-ordenes.xlsx';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    setTimeout(() => {
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    }, 100);
}

/** Aviso de validación previo a liberar (modal, como antes). */
const aviso = (titulo: string, texto: string) => void notify.alert(texto, titulo, 'warning');

export function liberarOrdenes(cfg: ConfigLiberar): void {
    const registros = obtenerRegistrosSeleccionados().filter((r) => r.id);

    if (!registros.length) {
        aviso('Sin registros', 'Selecciona al menos un registro para liberar.');
        return;
    }
    if (faltaLMat(registros)) {
        aviso('L.Mat obligatorio', 'Cada registro seleccionado debe tener L.Mat y Nombre L.Mat antes de liberar.');
        return;
    }
    const flogMalo = Array.from(document.querySelectorAll('.row-checkbox:checked'))
        .map((cb) => cb.closest('tr.row-data')?.querySelector<HTMLInputElement>('.flog-input'))
        .find((inp) => inp && inp.dataset.flogInvalido === 'true');
    if (flogMalo) {
        aviso('Flog inexistente', `El flog "${(flogMalo.value || '').trim()}" no existe o no está vigente en AX. Corrígelo antes de liberar.`);
        flogMalo.focus();
        return;
    }
    if (document.querySelector('tr[data-bom-no-vigente="true"]')) {
        aviso('L.Mat no vigente', 'Hay renglones con un L.Mat que ya no está vigente en AX (marcados en rojo). Corrígelos antes de liberar.');
        return;
    }
    const errMetricas = validarMetricasProduccion(registros);
    if (errMetricas) {
        aviso('Datos de producción incompletos', errMetricas);
        return;
    }

    // Confirmación con el POST dentro (botones deshabilitados mientras corre y error en el mismo modal).
    void notify.form<RespuestaLiberar>({
        title: 'Liberar órdenes',
        html: `Se actualizarán <strong>${registros.length}</strong> registros seleccionados.`,
        icon: 'question',
        confirmText: 'Liberar',
        cancelText: 'Cancelar',
        width: 'md',
        preConfirm: async (ctx) => {
            try {
                const data = await http.post<RespuestaLiberar>(cfg.rutas.procesar, { registros });
                if (!data?.success) throw new Error(mensajeLiberar(data));
                return data;
            } catch (error) {
                ctx.error(error instanceof HttpError ? mensajeLiberar(error.data) : (error as Error).message);
                return false;
            }
        },
    }).then((payload) => {
        if (!payload) return;
        if (payload.fileData) descargarExcelBase64(payload.fileData, payload.fileName);
        void notify.alert(payload.message || 'Se actualizaron los registros seleccionados.', 'Órdenes liberadas', 'success').then(() => {
            window.location.href = payload.redirectUrl || cfg.rutas.redirect;
        });
    });
}
