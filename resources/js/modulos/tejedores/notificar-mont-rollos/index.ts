/**
 * Tejedores › Cortado de rollo. Vista: resources/views/modulos/tejedores/notificar-mont-rollos/index.blade.php.
 * Telar (dropdown del navbar) → órdenes del telar → marbetes de la orden → Notificar (libera el marbete).
 * Rutas en data-* de #cortadoRollo.
 */
import { escapeHtml } from '../../../utils/format.ts';
import { delegate, onReady } from '../../../utils/dom.ts';

interface Orden {
    SalonTejidoId?: string | null;
    NoProduccion: string;
    FechaInicio?: string | null;
    TamanoClave?: string | null;
    NombreProducto?: string | null;
    EnProceso?: number | string;
}

interface Marbete {
    PurchBarCode?: string | null;
    ItemId?: string | null;
    QtySched?: number | string | null;
    CUANTAS?: number | string | null;
    InventSizeId?: string | null;
    InventBatchId?: string | null;
    WMSLocationId?: string | null;
    Salon?: string | null;
}

interface Respuesta {
    success?: boolean;
    error?: string;
    mensaje?: string;
    ordenes?: Orden[];
    datos?: Marbete[];
}

const CLASE_BTN = 'flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors ';
const SPINNER = `<tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 text-base">
    <svg class="animate-spin h-6 w-6 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg><p class="mt-2">Cargando órdenes del telar...</p></td></tr>`;

/** Los endpoints responden 4xx/5xx con { error }: se trata igual que un success:false. */
async function pedir(promesa: Promise<Respuesta>): Promise<Respuesta> {
    try {
        return await promesa;
    } catch (err) {
        const e = err as { status?: number; data?: Respuesta | null };
        if (!e.status) throw err;
        return e.data ?? {};
    }
}

const txt = (v: unknown): string => escapeHtml(v === null || v === undefined || v === '' ? 'N/A' : String(v));

function fecha(valor: string | null | undefined): string {
    return valor ? new Date(valor).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'N/A';
}

function piezas(valor: unknown): string {
    const n = Number(valor);
    return Number.isFinite(n) ? String(Math.trunc(n)) : 'N/A';
}

function filaOrden(orden: Orden, telar: string): string {
    const badge = Number(orden.EnProceso) === 1
        ? '<span class="ml-1 inline-flex px-1.5 py-0.5 bg-green-500 text-white text-[10px] rounded-full font-medium leading-none align-middle">EN PROCESO</span>'
        : '';
    return `<tr class="hover:bg-gray-50 transition-colors cursor-pointer orden-row">
        <td class="w-[10%] px-4 py-3 text-sm text-gray-700 truncate">${txt(orden.SalonTejidoId)}</td>
        <td class="w-[25%] px-4 py-3 text-sm text-gray-900 font-semibold truncate">${escapeHtml(orden.NoProduccion)}${badge}</td>
        <td class="w-[15%] px-4 py-3 text-sm text-gray-600 truncate">${fecha(orden.FechaInicio)}</td>
        <td class="w-[12%] px-4 py-3 text-sm text-gray-600 truncate">${txt(orden.TamanoClave)}</td>
        <td class="w-[30%] px-4 py-3 text-sm text-gray-600 truncate" title="${txt(orden.NombreProducto)}">${txt(orden.NombreProducto)}</td>
        <td class="w-[8%] px-4 py-3 text-center">
          <input type="checkbox" aria-label="Seleccionar orden ${escapeHtml(orden.NoProduccion)}"
                 class="checkbox-produccion-cortado w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
                 data-telar="${escapeHtml(telar)}" data-salon="${escapeHtml(orden.SalonTejidoId ?? '')}"
                 data-produccion="${escapeHtml(orden.NoProduccion)}">
        </td></tr>`;
}

function filaMarbete(m: Marbete, i: number): string {
    const td = 'px-4 py-3 text-sm text-gray-700 text-center truncate';
    return `<tr class="hover:bg-blue-50 cursor-pointer transition-colors marbete-row" data-index="${i}">
        <td class="w-[10%] ${td}">${txt(m.CUANTAS)}</td>
        <td class="w-[16%] px-4 py-3 text-sm text-gray-900 text-center font-medium truncate">${txt(m.PurchBarCode)}</td>
        <td class="w-[14%] ${td}">${txt(m.ItemId)}</td>
        <td class="w-[10%] ${td}">${txt(m.InventSizeId)}</td>
        <td class="w-[16%] ${td}">${txt(m.InventBatchId)}</td>
        <td class="w-[10%] ${td}">${txt(m.WMSLocationId)}</td>
        <td class="w-[10%] ${td}">${piezas(m.QtySched)}</td>
        <td class="w-[14%] ${td}">${txt(m.Salon)}</td></tr>`;
}

/** Quita la selección de las filas de `tbody` y, si `fila`, la marca con `fondo`. */
function marcarFila(tbody: HTMLElement, fila: HTMLElement | null, fondo: string): void {
    tbody.querySelectorAll('tr').forEach((tr) => {
        tr.classList.remove(fondo, 'selected');
        tr.querySelectorAll('td').forEach((td) => {
            if (!td.classList.replace('text-white', 'text-gray-700') && !/text-gray-\d00/.test(td.className)) td.classList.add('text-gray-700');
        });
    });
    if (!fila) return;
    fila.classList.add(fondo, 'selected');
    fila.querySelectorAll('td').forEach((td) => {
        td.classList.remove('text-gray-900', 'text-gray-600', 'text-gray-700');
        td.classList.add('text-white');
    });
}

function iniciar(raiz: HTMLElement): void {
    const $ = (id: string): HTMLElement => document.getElementById(id)!;
    const select = $('selectTelarCortado') as HTMLSelectElement;
    const ordenesBox = $('tablaProduccionesCortadoContainer');
    const ordenesBody = $('tablaProduccionesCortadoBody');
    const sinOrdenes = $('noDataProduccionesCortado');
    const marbetesBox = $('tablaProduccionCortadoContainer');
    const marbetesBody = $('tablaProduccionCortadoBody');
    const estado = $('mensajeEstadoCortado');
    const btnNotificar = $('btnNotificarCortado') as HTMLButtonElement;
    const { rutaOrdenes = '', rutaMarbetes = '', rutaInsertar = '' } = raiz.dataset;
    let marbetes: Marbete[] = [];

    function botonNotificar(activo: boolean): void {
        btnNotificar.disabled = !activo;
        btnNotificar.className = CLASE_BTN + (activo
            ? 'bg-blue-600 hover:bg-blue-700 text-white cursor-pointer'
            : 'bg-gray-200 text-gray-400 cursor-not-allowed');
    }

    function mensaje(texto: string, tipo: 'error' | 'info'): void {
        estado.textContent = texto;
        estado.className = `text-center text-base sm:text-lg py-5 bg-white rounded-xl shadow border border-gray-200 ${tipo === 'error' ? 'text-red-600' : 'text-blue-600'}`;
        estado.style.display = 'block';
        marbetesBox.style.display = 'none';
        botonNotificar(false);
    }

    function limpiarMarbetes(): void {
        marbetesBox.style.display = 'none';
        estado.style.display = 'none';
        botonNotificar(false);
    }

    async function cargarOrdenes(telar: string): Promise<void> {
        limpiarMarbetes();
        ordenesBody.innerHTML = SPINNER;
        ordenesBox.style.display = 'block';
        sinOrdenes.classList.add('hidden');
        try {
            const data = await pedir(window.http.get(rutaOrdenes.replace(':telar', encodeURIComponent(telar))));
            const ordenes = data.success ? (data.ordenes ?? []) : [];
            ordenesBody.innerHTML = ordenes.map((o) => filaOrden(o, telar)).join('');
            if (!ordenes.length) {
                sinOrdenes.textContent = 'No hay órdenes disponibles para este telar';
                sinOrdenes.classList.remove('hidden');
            }
        } catch {
            ordenesBody.innerHTML = '<tr><td colspan="6" class="px-4 py-4 text-center text-red-500 text-base">Error al cargar las órdenes del telar</td></tr>';
        }
    }

    async function cargarMarbetes(cb: HTMLInputElement): Promise<void> {
        mensaje('Cargando marbetes de la producción seleccionada...', 'info');
        const { produccion = '', telar = '', salon = '' } = cb.dataset;
        try {
            const data = await pedir(window.http.get(rutaMarbetes, { params: { no_produccion: produccion, no_telar: telar, salon } }));
            marbetes = data.success ? (data.datos ?? []) : [];
            if (!marbetes.length) {
                const msg = data.error || 'No se encontraron marbetes disponibles';
                return mensaje(data.mensaje ? `${msg}\n${data.mensaje}` : msg, 'error');
            }
            marbetesBody.innerHTML = marbetes.map(filaMarbete).join('');
            estado.style.display = 'none';
            marbetesBox.style.display = 'block';
        } catch (err) {
            mensaje(`Error al cargar marbetes: ${(err as Error).message}`, 'error');
        }
    }

    delegate<HTMLInputElement>(ordenesBody, 'change', '.checkbox-produccion-cortado', (_e, cb) => {
        ordenesBody.querySelectorAll<HTMLInputElement>('.checkbox-produccion-cortado').forEach((otro) => {
            if (otro !== cb) otro.checked = false;
        });
        marcarFila(ordenesBody, cb.checked ? cb.closest('tr') : null, 'bg-blue-600');
        if (cb.checked) void cargarMarbetes(cb);
        else limpiarMarbetes();
    });

    delegate(marbetesBody, 'click', 'tr.marbete-row', (_e, tr) => {
        const yaSeleccionada = tr.classList.contains('selected');
        marcarFila(marbetesBody, yaSeleccionada ? null : tr, 'bg-blue-500');
        botonNotificar(!yaSeleccionada);
    });

    select.addEventListener('change', () => {
        const telar = select.value;
        $('mensajeSeleccionaTelar').style.display = telar ? 'none' : 'flex';
        if (telar) return void cargarOrdenes(telar);
        ordenesBox.style.display = 'none';
        limpiarMarbetes();
    });

    iniciarDropdown(select);

    btnNotificar.addEventListener('click', async () => {
        const fila = marbetesBody.querySelector<HTMLElement>('tr.selected');
        const marbete = marbetes[Number(fila?.dataset.index)];
        if (!marbete) return void window.notify.alert('Debe seleccionar un marbete de la tabla', 'Selección requerida', 'warning');

        const ok = await window.notify.confirm({
            icon: 'question',
            title: '¿Confirmar liberación?',
            text: `¿Está seguro de liberar el marbete ${marbete.PurchBarCode}?`,
            confirmText: 'Sí, liberar',
        });
        if (!ok) return;

        try {
            void window.notify.loading('Procesando...');
            const data = await pedir(window.http.post(rutaInsertar, { marbetes: [marbete] }));
            if (!data.success) return void window.notify.alert(data.error || 'No se pudieron insertar los marbetes', 'Error', 'error');
            window.notify.close();
            window.notify.success(data.mensaje || '¡Marbete liberado!');
            // Recargar las órdenes del telar tras liberar.
            if (select.value) void cargarOrdenes(select.value);
            else limpiarMarbetes();
        } catch (err) {
            void window.notify.alert(`Error al insertar marbetes: ${(err as Error).message}`, 'Error de conexión', 'error');
        }
    });
}

/** Dropdown de telares del navbar: sincroniza el <select> oculto (fuente de verdad). */
function iniciarDropdown(select: HTMLSelectElement): void {
    const boton = document.getElementById('btnDropdownTelaresCortado');
    const menu = document.getElementById('menuDropdownTelaresCortado');
    const icono = document.getElementById('iconDropdownCortado');
    const etiqueta = document.getElementById('labelTelarSeleccionado');
    if (!boton || !menu) return;

    const abrir = (si: boolean): void => {
        menu.classList.toggle('hidden', !si);
        icono?.classList.toggle('rotate-180', si);
        icono?.classList.toggle('rotate-0', !si);
    };

    boton.addEventListener('click', (e) => {
        e.stopPropagation();
        abrir(menu.classList.contains('hidden'));
    });

    document.addEventListener('click', (e) => {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target as Node)) abrir(false);
    });

    delegate(menu, 'click', 'button[data-telar]', (e, b) => {
        e.stopPropagation();
        abrir(false);
        const telar = b.dataset.telar ?? '';
        if (etiqueta) etiqueta.textContent = telar ? `Telar ${telar}` : 'Telares';
        select.value = telar;
        select.dispatchEvent(new Event('change'));
    });
}

onReady(() => {
    const raiz = document.getElementById('cortadoRollo');
    if (raiz) iniciar(raiz);
});
