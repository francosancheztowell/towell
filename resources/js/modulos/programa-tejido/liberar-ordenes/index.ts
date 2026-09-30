/**
 * Liberar Órdenes (Programa Tejido y Muestras). Antes: ~1 900 líneas de <script> inline en
 * modulos/programa-tejido/liberar-ordenes/index.blade.php. La vista pasa rutas, columnas y
 * el peso estándar de Karl Mayer en data-pagina.
 *
 * Ningún campo se guarda solo: todo viaja al presionar "Liberar".
 */
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { delegate, onReady } from '../../../utils/dom.ts';
import { autocompletarDesdeAx, enlazarBom, enlazarFlogs } from './autocompletar.ts';
import { configurarPesoKarlMayer, enlazarCamposEditables } from './calculos.ts';
import { iniciarColumnas, openFiltersModal, openHideColumnsModal, openPinColumnsModal } from './columnas.ts';
import { liberarOrdenes, toggleSeleccionarTodo, updateSelectAllCheckbox } from './liberar.ts';
import { prioridadesIniciales, type ConfigLiberar } from './logica.ts';

/** Prioridad vacía → la anterior del telar (data-prioridad-anterior) o la del renglón de arriba. */
function rellenarPrioridades(): void {
    const inputs = Array.from(document.querySelectorAll<HTMLInputElement>('.prioridad-input'));
    const valores = prioridadesIniciales(inputs.map((i) => ({ valor: i.value, anterior: i.getAttribute('data-prioridad-anterior') || '' })));
    inputs.forEach((input, i) => { input.value = valores[i] ?? input.value; });
}

/** Clic en la fila (no en casilla ni campos) la marca como referencia visual; solo una a la vez. */
function enlazarFilaReferencia(tabla: HTMLElement): void {
    delegate(tabla, 'click', 'tr.row-data', (e, row) => {
        if ((e.target as Element).closest('input, select, button, a, .row-checkbox')) return;
        if (row.classList.contains('row-selected')) {
            row.classList.remove('row-selected');
            return;
        }
        document.querySelectorAll('tr.row-data.row-selected').forEach((r) => r.classList.remove('row-selected'));
        row.classList.add('row-selected');
    });
}

onReady(() => {
    const raiz = document.getElementById('liberar-ordenes-config');
    const cfg = leerDatos<ConfigLiberar>(raiz);
    if (!raiz || !cfg) return;
    configurarPesoKarlMayer(cfg.pesoKarlMayer);

    // Botones del navbar y casilla "Seleccionar todo" (antes onclick=).
    const acciones: Record<string, () => void> = {
        'fijar-columnas': openPinColumnsModal,
        'ocultar-columnas': openHideColumnsModal,
        filtros: () => openFiltersModal(),
        liberar: () => liberarOrdenes(cfg),
        'seleccionar-todo': toggleSeleccionarTodo,
    };
    delegate(document, 'click', '[data-accion]', (_e, el) => acciones[el.dataset.accion ?? '']?.());

    // Todas las casillas marcadas por defecto.
    document.querySelectorAll<HTMLInputElement>('.row-checkbox').forEach((cb) => {
        cb.checked = true;
        cb.addEventListener('change', updateSelectAllCheckbox);
    });
    const selectAll = document.getElementById('selectAllCheckbox') as HTMLInputElement | null;
    if (selectAll) selectAll.checked = true;

    rellenarPrioridades();
    enlazarFlogs(cfg.rutas);
    const tabla = document.getElementById('mainTable');
    if (tabla) enlazarFilaReferencia(tabla);
    iniciarColumnas(cfg.columnas);
    enlazarCamposEditables();
    // L.Mat y Nombre L.Mat ya vienen resueltos del controlador (con el salón del renglón).
    autocompletarDesdeAx(cfg.rutas);
    enlazarBom(cfg.rutas);
});
