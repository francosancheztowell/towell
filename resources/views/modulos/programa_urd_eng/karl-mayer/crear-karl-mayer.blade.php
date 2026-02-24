{{--
    Vista Karl Mayer - Programación de Requerimientos
    Diseño similar a Editar Orden Urdido (sin tabla de producción)
    Puro frontend
--}}

@extends('layouts.app')

@section('page-title', 'Programación Karl Mayer')

@section('navbar-right')
<x-navbar.button-create
    id="btnCrearOrden"
    type="submit"
    form="form-karl-mayer"
    title="Crear Orden"
    icon="fa-save"
    iconColor="text-white"
    hoverBg="hover:bg-blue-600"
    bg="bg-blue-500"
    text="Crear Orden"
/>
@endsection

@section('content')
<style>
    .sort-icon { opacity: 0.5; transition: opacity 0.2s; }
    .sortable:hover .sort-icon { opacity: 1; }
    .sortable.sort-asc .sort-icon::before { content: "\f0de"; }
    .sortable.sort-desc .sort-icon::before { content: "\f0dd"; }
    .sortable.sort-asc .sort-icon, .sortable.sort-desc .sort-icon { opacity: 1; }
</style>
<form id="form-karl-mayer" method="post" action="{{ route('programa.urd.eng.crear.orden.karl.mayer') }}">
    @csrf
@php
    $inputBaseClass = 'w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
    $inputTablaClass = 'w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
    $filasJulios = [
        ['obs_placeholder' => ''],
        ['obs_placeholder' => ''],
        ['obs_placeholder' => ''],
        ['obs_placeholder' => ''],
    ];
@endphp
<div class="w-full">
    <div class="bg-white p-3 mb-4 shadow-sm border border-gray-200">
        {{-- Primera fila: No. Telar, Barras, Fibra, Tamaño, Cuenta, Calibre, Metros --}}
        <div class="grid gap-1.5 mb-1.5" style="display: grid; grid-template-columns: 0.7fr 1.2fr 1fr 0.9fr 0.7fr 0.7fr 0.7fr;">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">No. Telar</label>
                <select required name="no_telar" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                    <option value="401">401</option>
                    <option value="402">402</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Barras</label>
                <select required name="barras" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fibra</label>
                <select required id="input-fibra" name="fibra" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tamaño</label>
                <input type="text" required id="input-tamano" name="tamano" placeholder=""
                    class="{{ $inputBaseClass }}" list="input-tamano-options" autocomplete="off">
                <datalist id="input-tamano-options"></datalist>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuenta</label>
                <input type="text" required id="input-cuenta" name="cuenta" placeholder="" readonly
                    class="{{ $inputBaseClass }} bg-gray-100" title="Se completa al elegir Fibra y Tamaño">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Calibre</label>
                <input type="text" required id="input-calibre" name="calibre" placeholder="" readonly
                    class="{{ $inputBaseClass }} bg-gray-100" title="Se completa al elegir Fibra y Tamaño">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metros</label>
                <input type="number" step="0.01" required name="metros" value="" placeholder=""
                    class="{{ $inputBaseClass }}">
            </div>
        </div>

        {{-- Segunda fila: Fecha Programada, Tipo Atado, Bom Urdido, Lote Proveedor --}}
        <div class="grid gap-1.5" style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fecha Programada</label>
                <input type="date" required name="fecha_programada" value="{{ date('Y-m-d') }}" placeholder=""
                    class="{{ $inputBaseClass }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo Atado</label>
                <select required name="tipo_atado" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                    <option value="Normal" selected>Normal</option>
                    <option value="Especial">Especial</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Urdido</label>
                <input type="text" required id="input-lmat" name="bom_id" placeholder=""
                    class="{{ $inputBaseClass }}"
                    autocomplete="off"
                    list="input-lmat-options">
                <datalist id="input-lmat-options"></datalist>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Lote Proveedor</label>
                <input type="text" readonly id="input-lote-proveedor" name="lote_proveedor" placeholder=""
                    class="{{ $inputBaseClass }} bg-gray-100"
                    title="Se completa al seleccionar un registro del inventario">
            </div>
        </div>

        {{-- Tablas: Resumen materiales (izq) + Inventario detalle (der) - más ancho a la derecha --}}
        <div class="mt-2 grid gap-2" style="display: grid; grid-template-columns: minmax(160px, 0.22fr) minmax(0, 1fr);">
            {{-- Tabla izquierda: Resumen (Articulo, Config, Consumo, Kilos) --}}
            <div class="border border-gray-400 rounded overflow-hidden">
                <div class="overflow-auto" style="height: 220px; max-height: 220px;">
                    <table id="tabla-resumen-lmat" class="min-w-full text-sm">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-2 py-1.5 text-center font-semibold">Articulo</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Config</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Consumo</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Kilos</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-resumen-lmat-body" class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="4" class="px-2 py-3 text-center text-gray-500 text-sm">Ingrese Bom Urdido (lmat)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- Tabla derecha: Detalle inventario con selección --}}
            <div class="border border-gray-400 rounded overflow-hidden flex flex-col" style="height: 260px;">
                <div class="overflow-auto flex-1 min-h-0">
                    <table id="tabla-detalle-lmat" class="min-w-full text-sm">
                        <thead class="text-white sticky top-0 z-10 whitespace-nowrap">
                            <tr>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="itemId">Articulo <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="configId">Config <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="inventSizeId">Tamaño <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="inventColorId">Color <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="inventLocationId">Almacen <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="inventBatchId">Lote <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="wmsLocationId">Localidad <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="inventSerialId">Serie <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="noProv">No Prov. <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="loteProv">Lote Prov. <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="prodDate">Fecha <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="conos">Conos <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="kilos">Kilos <i class="fa-solid fa-sort sort-icon ml-1"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 w-10">Seleccionar</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-detalle-lmat-body" class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="14" class="px-2 py-3 text-center text-gray-500 text-sm">Ingrese Bom Urdido (lmat)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="tabla-detalle-lmat-footer" class="shrink-0 border-t border-gray-200 text-sm py-1.5 overflow-x-hidden">
                    <table class="min-w-full text-sm" style="table-layout: fixed; width: 100%;">
                        <tr>
                            <td class="px-1.5 py-0 font-medium bg-blue-50"><span id="txt-total-registros">Total: 0</span></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td id="txt-total-conos" class="px-1.5 py-0 text-center font-semibold bg-white whitespace-nowrap" style="min-width: 50px;"></td>
                            <td id="txt-total-kilos" class="px-1.5 py-0 text-center font-semibold bg-blue-50 whitespace-nowrap" style="min-width: 70px;"></td>
                            <td class="px-1.5 py-0 w-10 bg-white"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tercera sección: No. Julio / Hilos + Observaciones --}}
        <div class="mt-2 grid gap-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div>
                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="min-w-full text-sm">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-2 py-1.5 text-center font-semibold">No. Julio</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Hilos</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Obs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($filasJulios as $filaJulio)
                            <tr>
                                <td class="px-2 py-1.5 text-center">
                                    <input type="number" min="1" name="julios[]"
                                        value="{{ $loop->first ? 4 : '' }}"
                                        class="{{ $inputTablaClass }}">
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <input type="number" min="1" name="hilos[]"
                                        class="{{ $inputTablaClass }}">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="text" placeholder="{{ $filaJulio['obs_placeholder'] }}" name="obs[]"
                                        class="{{ $inputTablaClass }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Observaciones del Programa</label>
                <textarea rows="3" name="observaciones" placeholder=""
                    class="{{ $inputTablaClass }} resize-none"></textarea>
            </div>
        </div>
    </div>
</div>
</form>

@push('scripts')
<script>
(() => {
    const ROUTES = {
        buscarBomUrdido: '{{ route("programa.urd.eng.buscar.bom.urdido") }}',
        materialesCompleto: '{{ route("programa.urd.eng.materiales.urdido.completo") }}',
        hilos: '{{ route("programa.urd.eng.hilos") }}',
        tamanos: '{{ route("programa.urd.eng.tamanos") }}',
        crearOrdenKarlMayer: '{{ route("programa.urd.eng.crear.orden.karl.mayer") }}',
        indexProgramaUrdEng: '{{ route("programa.urd.eng.index") }}',
    };

    const TABLE_COLS = { resumen: 4, detalle: 14 };
    const MESSAGES = {
        sinDatos: 'Sin datos',
        totalRegistros: 'Total: ',
    };

    let opcionesTamanos = [];

    const elements = {
        inputLmat: document.getElementById('input-lmat'),
        inputLoteProveedor: document.getElementById('input-lote-proveedor'),
        inputCuenta: document.getElementById('input-cuenta'),
        inputCalibre: document.getElementById('input-calibre'),
        inputTamano: document.getElementById('input-tamano'),
        inputFibra: document.getElementById('input-fibra'),
        datalistTamano: document.getElementById('input-tamano-options'),
        datalistLmat: document.getElementById('input-lmat-options'),
        tbodyResumen: document.getElementById('tabla-resumen-lmat-body'),
        tbodyDetalle: document.getElementById('tabla-detalle-lmat-body'),
        txtTotalRegistros: document.getElementById('txt-total-registros'),
        txtTotalConos: document.getElementById('txt-total-conos'),
        txtTotalKilos: document.getElementById('txt-total-kilos'),
    };

    if (!elements.tbodyResumen || !elements.tbodyDetalle) return;

    let materialesDetalle = [];
    let sortState = { column: null, direction: null };

    const debounce = (fn, ms) => {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn(...args), ms);
        };
    };

    const toFloat = (value, fallback = 0) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    const toInt = (value, fallback = 0) => {
        const parsed = parseInt(value, 10);
        return Number.isInteger(parsed) ? parsed : fallback;
    };

    const toNumber = (v, def = 0) => {
        if (v === null || v === undefined) return def;
        const num = parseFloat(String(v).replace(/,/g, ''));
        return Number.isNaN(num) ? def : num;
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };

    const formatKilos = (value) => {
        const kilos = toFloat(value, 0);
        return kilos.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatFecha = (value) => {
        const raw = String(value ?? '').trim();
        if (!raw) return '';

        const yyyyMmDd = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (yyyyMmDd) return `${yyyyMmDd[3]}/${yyyyMmDd[2]}/${yyyyMmDd[1]}`;

        const ddMmYyyy = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (ddMmYyyy) return raw;

        const date = new Date(raw);
        if (!Number.isNaN(date.getTime())) {
            return date.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        return raw;
    };

    const notifyWarning = (message) => {
        if (!message) return;
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'Aviso', text: message });
            return;
        }
        window.alert(message);
    };

    const setTableMessage = (tbody, colspan, message) => {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-2 py-3 text-center text-gray-500 text-sm">${escapeHtml(message)}</td></tr>`;
    };

    const resetTotales = () => {
        if (elements.txtTotalRegistros) {
            elements.txtTotalRegistros.textContent = `${MESSAGES.totalRegistros}0`;
        }
        if (elements.txtTotalConos) elements.txtTotalConos.textContent = '';
        if (elements.txtTotalKilos) elements.txtTotalKilos.textContent = '';
    };

    const actualizarTotalesSeleccionados = () => {
        const checks = elements.tbodyDetalle?.querySelectorAll('.chk-detalle-lmat:checked') ?? [];
        let totalConos = 0;
        let totalKilos = 0;

        checks.forEach((checkbox) => {
            const row = checkbox.closest('tr');
            if (!row) return;
            totalConos += toInt(row.dataset.conos, 0);
            totalKilos += toFloat(row.dataset.kilos, 0);
        });

        if (elements.txtTotalRegistros) {
            elements.txtTotalRegistros.textContent = `${MESSAGES.totalRegistros}${checks.length}`;
        }
        if (elements.txtTotalConos) {
            elements.txtTotalConos.textContent = checks.length > 0 ? String(totalConos) : '';
        }
        if (elements.txtTotalKilos) {
            elements.txtTotalKilos.textContent = checks.length > 0 ? formatKilos(totalKilos) : '';
        }

        if (elements.inputLoteProveedor) {
            let lote = '';
            for (const chk of checks) {
                const v = String(chk.closest('tr')?.dataset?.lote ?? '').trim();
                if (v) { lote = v; break; }
            }
            elements.inputLoteProveedor.value = lote;
        }
    };

    const renderResumen = (rows = []) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            setTableMessage(elements.tbodyResumen, TABLE_COLS.resumen, MESSAGES.sinDatos);
            return;
        }

        elements.tbodyResumen.innerHTML = rows.map((row) => `
            <tr class="hover:bg-gray-50">
                <td class="px-2 py-1.5 text-center">${escapeHtml(row.articulo)}</td>
                <td class="px-2 py-1.5 text-center">${escapeHtml(row.config)}</td>
                <td class="px-2 py-1.5 text-center">${escapeHtml(row.consumo ?? '')}</td>
                <td class="px-2 py-1.5 text-center">${escapeHtml(row.kilos ?? '')}</td>
            </tr>
        `).join('');
    };

    const getDetalleCellClass = (index) => (index % 2 === 0 ? 'bg-blue-50' : 'bg-white');

    function ordenarMateriales(materiales, column, direction) {
        if (!column || !direction || !materiales || !materiales.length) return materiales;
        const sorted = [...materiales].sort((a, b) => {
            let valA, valB;
            switch (column) {
                case 'itemId': valA = String(a.ItemId || '').toLowerCase(); valB = String(b.ItemId || '').toLowerCase(); break;
                case 'configId': valA = String(a.ConfigId || '').toLowerCase(); valB = String(b.ConfigId || '').toLowerCase(); break;
                case 'inventSizeId': valA = String(a.InventSizeId || '').toLowerCase(); valB = String(b.InventSizeId || '').toLowerCase(); break;
                case 'inventColorId': valA = String(a.InventColorId || '').toLowerCase(); valB = String(b.InventColorId || '').toLowerCase(); break;
                case 'inventLocationId': valA = String(a.InventLocationId || '').toLowerCase(); valB = String(b.InventLocationId || '').toLowerCase(); break;
                case 'inventBatchId': valA = String(a.InventBatchId || '').toLowerCase(); valB = String(b.InventBatchId || '').toLowerCase(); break;
                case 'wmsLocationId': valA = String(a.WMSLocationId || '').toLowerCase(); valB = String(b.WMSLocationId || '').toLowerCase(); break;
                case 'inventSerialId': valA = String(a.InventSerialId || '').toLowerCase(); valB = String(b.InventSerialId || '').toLowerCase(); break;
                case 'loteProv': valA = String(a.TwCalidadFlog || '').toLowerCase(); valB = String(b.TwCalidadFlog || '').toLowerCase(); break;
                case 'noProv': valA = String(a.TwClienteFlog || '').toLowerCase(); valB = String(b.TwClienteFlog || '').toLowerCase(); break;
                case 'prodDate':
                    valA = a.ProdDate ? new Date(a.ProdDate).getTime() : 0;
                    valB = b.ProdDate ? new Date(b.ProdDate).getTime() : 0;
                    break;
                case 'conos': valA = toNumber(a.TwTiras, 0); valB = toNumber(b.TwTiras, 0); break;
                case 'kilos': valA = toNumber(a.PhysicalInvent, 0); valB = toNumber(b.PhysicalInvent, 0); break;
                default: return 0;
            }
            if (valA < valB) return direction === 'asc' ? -1 : 1;
            if (valA > valB) return direction === 'asc' ? 1 : -1;
            return 0;
        });
        return sorted;
    }

    const actualizarIconosOrdenamiento = (column, direction) => {
        document.querySelectorAll('#tabla-detalle-lmat th.sortable').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
            if (th.dataset.sort === column) th.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
        });
    };

    let ordenamientoInicializado = false;
    const initOrdenamientoTabla = () => {
        if (ordenamientoInicializado) return;
        document.querySelectorAll('#tabla-detalle-lmat th.sortable').forEach(th => {
            th.addEventListener('click', () => {
                const column = th.dataset.sort;
                if (!column) return;
                let newDirection = 'asc';
                if (sortState.column === column && sortState.direction === 'asc') newDirection = 'desc';
                sortState.column = column;
                sortState.direction = newDirection;
                actualizarIconosOrdenamiento(column, newDirection);
                const selecciones = getSelectedIds();
                const materialesOrdenados = ordenarMateriales(materialesDetalle, column, newDirection);
                const frag = document.createDocumentFragment();
                materialesOrdenados.forEach(m => {
                    const checkboxKey = `${m.ItemId || ''}_${m.InventSerialId || ''}`;
                    const checked = selecciones.has(checkboxKey) ? ' checked' : '';
                    const kilos = toNumber(m.PhysicalInvent, 0);
                    const conos = toNumber(m.TwTiras, 0);
                    const lotePr = m.TwCalidadFlog || '-';
                    const noProv = m.TwClienteFlog || '-';
                    const prodDate = m.ProdDate ? formatFecha(m.ProdDate) : (m.ProdDate || '-');
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-blue-50';
                    tr.dataset.materialData = JSON.stringify(m);
                    tr.dataset.id = checkboxKey;
                    tr.dataset.conos = String(conos);
                    tr.dataset.kilos = String(kilos);
                    tr.dataset.lote = String(m.InventBatchId ?? '').trim();
                    tr.innerHTML = `
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(0)}">${escapeHtml(m.ItemId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(1)}">${escapeHtml(m.ConfigId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(2)}">${escapeHtml(m.InventSizeId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(3)}">${escapeHtml(m.InventColorId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(4)}">${escapeHtml(m.InventLocationId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(5)}">${escapeHtml(m.InventBatchId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(6)}">${escapeHtml(m.WMSLocationId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(7)}">${escapeHtml(m.InventSerialId)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(8)}">${escapeHtml(noProv)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(9)}">${escapeHtml(lotePr)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(10)}">${escapeHtml(prodDate)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(11)}">${conos.toFixed(0)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(12)}">${formatKilos(kilos)}</td>
                        <td class="px-1.5 py-1 text-center ${getDetalleCellClass(13)}">
                            <input type="checkbox" class="chk-detalle-lmat w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-id="${escapeHtml(checkboxKey)}"${checked}>
                        </td>
                    `;
                    frag.appendChild(tr);
                });
                elements.tbodyDetalle.innerHTML = '';
                elements.tbodyDetalle.appendChild(frag);
                actualizarTotalesSeleccionados();
            });
        });
        ordenamientoInicializado = true;
    };

    const getSelectedIds = () => {
        const checks = elements.tbodyDetalle?.querySelectorAll('.chk-detalle-lmat:checked') ?? [];
        return new Set(Array.from(checks).map((chk) => chk.dataset.id).filter(Boolean));
    };

    const renderDetalle = (materiales = [], preserveSelection = true) => {
        const selectedIds = preserveSelection ? getSelectedIds() : new Set();

        if (!Array.isArray(materiales) || materiales.length === 0) {
            setTableMessage(elements.tbodyDetalle, TABLE_COLS.detalle, MESSAGES.sinDatos);
            resetTotales();
            materialesDetalle = [];
            return;
        }

        materialesDetalle = materiales;
        let materialesParaRender = materiales;
        if (sortState.column && sortState.direction) {
            materialesParaRender = ordenarMateriales(materiales, sortState.column, sortState.direction);
        }

        const frag = document.createDocumentFragment();
        for (const m of materialesParaRender) {
            const checkboxKey = `${m.ItemId || ''}_${m.InventSerialId || ''}`;
            const checked = selectedIds.has(checkboxKey) ? ' checked' : '';
            const kilos = toNumber(m.PhysicalInvent, 0);
            const conos = toNumber(m.TwTiras, 0);
            const lotePr = m.TwCalidadFlog || '-';
            const noProv = m.TwClienteFlog || '-';
            const prodDate = m.ProdDate ? formatFecha(m.ProdDate) : (m.ProdDate || '-');

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-blue-50';
            tr.dataset.materialData = JSON.stringify(m);
            tr.dataset.id = checkboxKey;
            tr.dataset.conos = String(conos);
            tr.dataset.kilos = String(kilos);
            tr.dataset.lote = String(m.InventBatchId ?? '').trim();
            tr.innerHTML = `
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(0)}">${escapeHtml(m.ItemId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(1)}">${escapeHtml(m.ConfigId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(2)}">${escapeHtml(m.InventSizeId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(3)}">${escapeHtml(m.InventColorId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(4)}">${escapeHtml(m.InventLocationId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(5)}">${escapeHtml(m.InventBatchId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(6)}">${escapeHtml(m.WMSLocationId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(7)}">${escapeHtml(m.InventSerialId)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(8)}">${escapeHtml(noProv)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(9)}">${escapeHtml(lotePr)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(10)}">${escapeHtml(prodDate)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(11)}">${conos.toFixed(0)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(12)}">${formatKilos(kilos)}</td>
                <td class="px-1.5 py-1 text-center ${getDetalleCellClass(13)}">
                    <input type="checkbox" class="chk-detalle-lmat w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-id="${escapeHtml(checkboxKey)}"${checked}>
                </td>
            `;
            frag.appendChild(tr);
        }
        elements.tbodyDetalle.innerHTML = '';
        elements.tbodyDetalle.appendChild(frag);
        initOrdenamientoTabla();
        if (sortState.column && sortState.direction) actualizarIconosOrdenamiento(sortState.column, sortState.direction);
        actualizarTotalesSeleccionados();
    };

    const buildUrl = (baseUrl, params = {}) => {
        const url = new URL(baseUrl, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                url.searchParams.set(key, String(value));
            }
        });
        return url.toString();
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    };

    const cargarHilosYTamanos = async () => {
        try {
            const [resHilos, resTamanos] = await Promise.all([
                fetchJson(ROUTES.hilos),
                fetchJson(ROUTES.tamanos),
            ]);
            const hilos = (resHilos?.success && Array.isArray(resHilos?.data))
                ? resHilos.data.map((i) => i.ConfigId || '').filter(Boolean)
                : [];
            opcionesTamanos = (resTamanos?.success && Array.isArray(resTamanos?.data))
                ? resTamanos.data.map((i) => i.InventSizeId || '').filter(Boolean)
                : [];

            if (elements.inputFibra) {
                const opts = hilos.map((h) => `<option value="${escapeHtml(h)}">${escapeHtml(h)}</option>`).join('');
                elements.inputFibra.innerHTML = '<option value="">Seleccionar...</option>' + opts;
            }
            if (elements.datalistTamano) {
                elements.datalistTamano.innerHTML = '';
            }
        } catch (error) {
            console.error('Error al cargar hilos/tamaños:', error);
        }
    };

    const actualizarOpcionesTamano = () => {
        const val = String(elements.inputTamano?.value ?? '').trim().toLowerCase();
        if (!elements.datalistTamano) return;
        if (val.length < 2) {
            elements.datalistTamano.innerHTML = '';
            return;
        }
        const filtrados = opcionesTamanos.filter((t) =>
            String(t).toLowerCase().includes(val)
        );
        elements.datalistTamano.innerHTML = filtrados
            .slice(0, 5)
            .map((t) => `<option value="${escapeHtml(t)}">`)
            .join('');
    };

    /** Al elegir Fibra y Tamaño, rellena Cuenta y Calibre desde Tamaño (ej: 2960-12/1 → Cuenta=2960, Calibre=12) */
    const rellenarCuentaYCalibreDesdeTamano = () => {
        const tamano = String(elements.inputTamano?.value ?? '').trim();
        if (!tamano) {
            if (elements.inputCuenta) elements.inputCuenta.value = '';
            if (elements.inputCalibre) elements.inputCalibre.value = '';
            return;
        }
        const match = tamano.match(/^([^-]+)-([^/]+)\/1$/);
        if (match) {
            if (elements.inputCuenta) elements.inputCuenta.value = match[1].trim();
            if (elements.inputCalibre) elements.inputCalibre.value = match[2].trim();
        } else {
            if (elements.inputCuenta) elements.inputCuenta.value = tamano;
            if (elements.inputCalibre) elements.inputCalibre.value = '';
        }
    };

    const cargarMaterialesLmat = async () => {
        const bomId = String(elements.inputLmat?.value ?? '').trim();
        if (!bomId) {
            renderResumen([]);
            renderDetalle([]);
            if (elements.inputLoteProveedor) elements.inputLoteProveedor.value = '';
            return;
        }

        const kilosTotal = 1;

        try {
            const data = await fetchJson(buildUrl(ROUTES.materialesCompleto, { bomId, kilosTotal }));
            if (data?.error) {
                renderResumen([]);
                renderDetalle([]);
                if (elements.inputLoteProveedor) elements.inputLoteProveedor.value = '';
                notifyWarning(data.error);
                return;
            }

            renderResumen(data?.resumen ?? []);
            renderDetalle(data?.detalle ?? []);

            if (elements.inputLoteProveedor) elements.inputLoteProveedor.value = '';
        } catch (error) {
            console.error('Error cargar materiales lmat:', error);
            renderResumen([]);
            renderDetalle([]);
            if (elements.inputLoteProveedor) elements.inputLoteProveedor.value = '';
        }
    };

    const buscarBomUrdido = debounce(async (query) => {
        if (!elements.datalistLmat) return;

        const term = String(query ?? '').trim();
        if (term.length < 2) {
            elements.datalistLmat.innerHTML = '';
            return;
        }

        try {
            const data = await fetchJson(buildUrl(ROUTES.buscarBomUrdido, { q: term }));
            const rows = Array.isArray(data) ? data : (data?.data ?? []);

            elements.datalistLmat.innerHTML = rows
                .slice(0, 15)
                .map((item) => {
                    const value = item?.BOMID ?? item?.bomId ?? '';
                    const name = item?.NAME ?? item?.name ?? '';
                    return value ? `<option value="${escapeHtml(value)}">${escapeHtml(name)}</option>` : '';
                })
                .filter(Boolean)
                .join('');
        } catch (error) {
            console.error('Error buscar BOM:', error);
            elements.datalistLmat.innerHTML = '';
        }
    }, 300);

    const form = document.getElementById('form-karl-mayer');

    const getMaterialesSeleccionados = () => {
        const checks = elements.tbodyDetalle?.querySelectorAll('.chk-detalle-lmat:checked') ?? [];
        return Array.from(checks).map((chk) => {
            const tr = chk.closest('tr');
            if (!tr) return null;
            let material;
            try {
                material = tr.dataset.materialData ? JSON.parse(tr.dataset.materialData) : null;
            } catch (e) {
                material = null;
            }
            if (material) {
                return {
                    itemId: material.ItemId || '',
                    configId: material.ConfigId || '',
                    inventSizeId: material.InventSizeId || '',
                    inventColorId: material.InventColorId || '',
                    inventLocationId: material.InventLocationId || '',
                    inventBatchId: material.InventBatchId || '',
                    wmsLocationId: material.WMSLocationId || '',
                    inventSerialId: material.InventSerialId || '',
                    kilos: toNumber(material.PhysicalInvent, 0),
                    conos: toNumber(material.TwTiras, 0),
                    loteProv: material.TwCalidadFlog || '',
                    noProv: material.TwClienteFlog || '',
                    prodDate: material.ProdDate || null,
                };
            }
            return {
                itemId: tr.dataset.itemId ?? '',
                configId: tr.dataset.configId ?? '',
                inventSizeId: tr.dataset.inventSizeId ?? '',
                inventColorId: tr.dataset.inventColorId ?? '',
                inventLocationId: tr.dataset.inventLocationId ?? '',
                inventBatchId: tr.dataset.inventBatchId ?? '',
                wmsLocationId: tr.dataset.wmsLocationId ?? '',
                inventSerialId: tr.dataset.inventSerialId ?? '',
                kilos: toNumber(tr.dataset.kilos, 0),
                conos: toNumber(tr.dataset.conos, 0),
                loteProv: tr.dataset.loteProv ?? '',
                noProv: tr.dataset.noProv ?? '',
                prodDate: tr.dataset.fecha || null,
            };
        }).filter((m) => m && (m.itemId || m.inventSerialId));
    };

    const validarFormulario = () => {
        if (!form) return false;
        const v = (name) => String(form.querySelector(`[name="${name}"]`)?.value ?? '').trim();
        if (!v('no_telar') || !v('barras') || !v('fibra') || !v('tamano') || !v('cuenta') || !v('calibre')) return false;
        const metros = parseFloat(v('metros'));
        if (!Number.isFinite(metros) || metros < 0) return false;
        if (!v('fecha_programada') || !v('tipo_atado') || !v('bom_id')) return false;
        const materiales = getMaterialesSeleccionados();
        if (materiales.length === 0) return false;
        const julios = form.querySelectorAll('input[name="julios[]"]');
        const hilos = form.querySelectorAll('input[name="hilos[]"]');
        let tieneJulioOHilo = false;
        for (let i = 0; i < Math.max(julios.length, hilos.length); i++) {
            const j = String(julios[i]?.value ?? '').trim();
            const h = String(hilos[i]?.value ?? '').trim();
            if (j !== '' || h !== '') { tieneJulioOHilo = true; break; }
        }
        return tieneJulioOHilo;
    };

    const actualizarEstadoBotonCrear = () => {
        const btn = document.getElementById('btnCrearOrden');
        if (btn) btn.disabled = !validarFormulario();
    };

    const bindEvents = () => {
        const cargarDebounced = debounce(cargarMaterialesLmat, 350);
        const rellenarTamanoDebounced = debounce(rellenarCuentaYCalibreDesdeTamano, 200);

        elements.inputLmat?.addEventListener('change', cargarMaterialesLmat);
        elements.inputLmat?.addEventListener('blur', (event) => {
            if (elements.tbodyDetalle?.contains(event.relatedTarget)) return;
            cargarMaterialesLmat();
        });
        elements.inputLmat?.addEventListener('input', (event) => {
            buscarBomUrdido(event.target.value);
        });
        elements.inputLmat?.addEventListener('focus', () => {
            const current = String(elements.inputLmat.value ?? '').trim();
            if (current.length >= 2) buscarBomUrdido(current);
        });

        elements.inputTamano?.addEventListener('change', rellenarCuentaYCalibreDesdeTamano);
        elements.inputTamano?.addEventListener('input', () => {
            actualizarOpcionesTamano();
            rellenarTamanoDebounced();
        });
        elements.inputFibra?.addEventListener('change', rellenarCuentaYCalibreDesdeTamano);

        form?.addEventListener('input', actualizarEstadoBotonCrear);
        form?.addEventListener('change', actualizarEstadoBotonCrear);

        // Bloquear escritura en Lote Proveedor (solo se rellena al seleccionar inventario)
        if (elements.inputLoteProveedor) {
            const bloqueoLote = (e) => { e.preventDefault(); e.stopPropagation(); };
            elements.inputLoteProveedor.addEventListener('keydown', bloqueoLote);
            elements.inputLoteProveedor.addEventListener('keypress', bloqueoLote);
            elements.inputLoteProveedor.addEventListener('paste', bloqueoLote);
            elements.inputLoteProveedor.addEventListener('input', (e) => { e.preventDefault(); });
        }

        elements.tbodyDetalle.addEventListener('change', (event) => {
            if (event.target && event.target.matches('.chk-detalle-lmat')) {
                actualizarTotalesSeleccionados();
                actualizarEstadoBotonCrear();
            }
        });

        document.getElementById('btnCrearOrden')?.addEventListener('click', (e) => {
            e.preventDefault();
            form?.requestSubmit();
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!validarFormulario()) {
                const materiales = getMaterialesSeleccionados();
                if (materiales.length === 0 && elements.tbodyDetalle?.querySelector('tr[data-item-id]')) {
                    notifyWarning('Seleccione al menos un material de la tabla de inventario.');
                }
                return;
            }
            const btn = document.getElementById('btnCrearOrden');
            const originalText = btn?.innerHTML ?? '';
            if (btn) {
                btn.disabled = true;
                if (btn.querySelector('span')) btn.querySelector('span').textContent = 'Guardando...';
            }

            const formData = new FormData(form);
            const payload = {
                _token: formData.get('_token'),
                no_telar: formData.get('no_telar'),
                barras: formData.get('barras'),
                fibra: formData.get('fibra'),
                tamano: formData.get('tamano'),
                cuenta: formData.get('cuenta'),
                calibre: formData.get('calibre'),
                metros: formData.get('metros'),
                fecha_programada: formData.get('fecha_programada'),
                tipo_atado: formData.get('tipo_atado'),
                bom_id: formData.get('bom_id'),
                lote_proveedor: formData.get('lote_proveedor'),
                observaciones: formData.get('observaciones'),
                julios: formData.getAll('julios[]'),
                hilos: formData.getAll('hilos[]'),
                obs: formData.getAll('obs[]'),
                materiales: getMaterialesSeleccionados(),
            };

            try {
                const response = await fetch(ROUTES.crearOrdenKarlMayer, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: JSON.stringify(payload),
                });

                let data;
                try {
                    data = await response.json();
                } catch (parseErr) {
                    console.error('Respuesta no JSON:', parseErr);
                    const errText = await response.text();
                    const errMsg = response.status === 422 ? 'Error de validación. Revise los datos.' :
                        (response.status >= 500 ? 'Error del servidor.' : 'Error inesperado.');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
                    } else {
                        alert(errMsg);
                    }
                    return;
                }

                if (data.success) {
                    const folio = data.data?.folio ?? '';
                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Creado',
                            text: 'Orden creada. Folio: ' + folio,
                        });
                        window.location.href = ROUTES.indexProgramaUrdEng;
                    } else {
                        alert('Orden creada. Folio: ' + folio);
                        window.location.href = ROUTES.indexProgramaUrdEng;
                    }
                } else {
                    const errMsg = data.errors ? Object.values(data.errors).flat().join('\n') : (data.error ?? 'Error desconocido');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
                    } else {
                        alert(errMsg);
                    }
                }
            } catch (error) {
                console.error('Error crear orden Karl Mayer:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.' });
                } else {
                    alert('No se pudo conectar con el servidor.');
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    if (btn.querySelector('span')) btn.querySelector('span').textContent = 'Crear Orden';
                }
            }
        });
    };

    bindEvents();

    cargarHilosYTamanos();

    actualizarEstadoBotonCrear();

    if (String(elements.inputLmat?.value ?? '').trim()) {
        setTimeout(() => {
            cargarMaterialesLmat();
            actualizarEstadoBotonCrear();
        }, 500);
    }
})();
</script>
@endpush
@endsection
