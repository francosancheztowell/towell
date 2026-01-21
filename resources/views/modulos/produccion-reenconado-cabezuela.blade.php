@extends('layouts.app')

@section('page-title', 'Producción Reenconado Cabezuela')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
        id="btn-nuevo"
        title="Nuevo"
        module="Producción Reenconado Cabezuela"

        :disabled="false"
        icon="fa-plus"
        iconColor="text-green-600"
        hoverBg="hover:bg-green-100"
    />
    <x-navbar.button-edit
        id="btn-editar"
        title="Editar"
        module="Producción Reenconado Cabezuela"
        :disabled="true"
        icon="fa-pen-to-square"
        iconColor="text-yellow-500"
        hoverBg="hover:bg-yellow-100"
    />
    <x-navbar.button-delete
        id="btn-eliminar"
        title="Eliminar"
        module="Producción Reenconado Cabezuela"
        :disabled="true"
        icon="fa-trash"
        iconColor="text-red-600"
        hoverBg="hover:bg-red-100"
    />
</div>
@endsection

@push('styles')
<style>
    #tabla-registros tr.selected {
        background-color: #3b82f6 !important;
        color: white !important;
    }
    #tabla-registros tr.selected td {
        color: white !important;
    }
</style>
@endpush



@section('content')
<div class="w-full">
    <div class="overflow-x-auto  bg-white w-full">
        <table class="min-w-full table-capture text-sm" id="tabla-registros">
            <thead class="text-white">
                <tr class="text-center align-middle">
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Folio</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Fecha</th>
                    <th class="w-[72px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Turno</th>
                    <th class="min-w-[160px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Operador</th>
                    <th class="w-[72px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Calibre</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Fibra</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Cód. Color</th>
                    <th class="min-w-[160px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Color</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Cantidad</th>
                    <th class="w-[72px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Conos</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Hrs</th>
                    <th class="w-[90px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Eficiencia</th>
                    <th class="min-w-[160px] bg-blue-500 whitespace-nowrap px-4 py-3 border-b-2 border-gray-200">Observaciones</th>
                </tr>
            </thead>
            <tbody id="rows-body" class="text-gray-800">
                @forelse($registros as $r)
                    <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50 cursor-pointer"
                        data-folio="{{ $r->Folio }}"
                        data-date="{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}"
                        data-turno="{{ $r->Turno }}"
                        data-numero-empleado="{{ $r->numero_empleado }}"
                        data-nombreempl="{{ $r->nombreEmpl }}"
                        data-calibre="{{ $r->Calibre ?? '' }}"
                        data-fibratrama="{{ $r->FibraTrama }}"
                        data-codcolor="{{ $r->CodColor }}"
                        data-color="{{ $r->Color }}"
                        data-cantidad="{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2, '.', '') }}"
                        data-conos="{{ $r->Conos }}"
                        data-horas="{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2, '.', '') }}"
                        data-eficiencia="{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2, '.', '') }}"
                        data-obs="{{ $r->Obs }}">
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Folio }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Turno }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->nombreEmpl }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Calibre }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->FibraTrama }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->CodColor }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Color }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Conos }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2) }}</td>
                        <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Obs }}</td>
                    </tr>
                @empty
                    <tr class="odd:bg-white even:bg-gray-50">
                        <td colspan="13" class="text-center text-gray-500 py-4">Sin registros</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="modalNuevo" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="fixed inset-0 bg-black/50" data-close="1"></div>
    <div class="relative bg-white w-[90vw] max-w-3xl rounded shadow-lg">
        <div class="modal-header bg-blue-500 text-white border-b flex items-center justify-between p-2.5">
            <h5 class="text-base md:text-lg font-semibold" id="modal-title">
                Nuevo Registro de Producción
            </h5>
            <div class="flex items-center gap-2">
                <button type="button" class="px-2.5 py-1.5 rounded bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium transition text-sm" id="btn-cancelar-modal">
                    Cancelar
                </button>
                <button type="button" class="px-2.5 py-1.5 rounded bg-green-600 text-white hover:bg-green-700 font-medium transition shadow-md text-sm" id="btn-guardar-nuevo">
                    Guardar
                </button>
            </div>
        </div>
        <div class="p-3 modal-scroll max-h-[85vh] overflow-y-auto pt-[72px]">
            <div class="grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <h6 class="text-sm font-semibold text-gray-700  border-b border-gray-200">
                        <i class="fa fa-info-circle mr-2 text-blue-500"></i>Información General
                    </h6>
                </div>

                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Folio</label>
                    <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Folio" readonly>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Fecha</label>
                    <input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Date">
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Turno</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Turno">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">No. Empleado</label>
                    <input type="text" disabled class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_numero_empleado">
                </div>
                <div class="col-span-12 md:col-span-6">
                    <label class="block text-sm font-medium text-gray-700 ">Nombre del Operador</label>
                    <input type="text" disabled class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_nombreEmpl">
                </div>

                <div class="col-span-12 ">
                    <h6 class="text-sm font-semibold text-gray-700 mb-2  border-b border-gray-200">
                        <i class="fa fa-box mr-2 text-blue-500"></i>Detalles del Material
                    </h6>
                </div>

                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Calibre</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Calibre">
                        <option value="">Cargando...</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Fibra</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_FibraTrama" disabled>
                        <option value="">Selecciona calibre</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Cód. Color</label>
                    <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_CodColor" disabled>
                        <option value="">Selecciona calibre</option>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Nombre del Color</label>
                    <input type="text" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Color" readonly>
                </div>

                <div class="col-span-12 ">
                    <h6 class="text-sm font-semibold text-gray-700  border-b border-gray-200">
                        <i class="fa fa-chart-line mr-2 text-blue-500"></i>Datos de Producción
                    </h6>
                </div>

                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Cantidad (kg)</label>
                    <input type="number" step="0.01" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Cantidad">
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Conos</label>
                    <input type="number" step="1" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Conos">
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Tiempo (hrs)</label>
                    <input type="number" step="0.01" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Horas">
                </div>
                <div class="col-span-6 md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 ">Eficiencia (%)</label>
                    <input type="number" step="0.01" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Eficiencia">
                </div>
                <div class="col-span-12">
                    <label class="block text-sm font-medium text-gray-700 ">Observaciones</label>
                    <textarea class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-4" id="f_Obs" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    'use strict';

    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    @endif

    @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: '<ul class="text-left list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
            confirmButtonText: 'Aceptar'
        });
    @endif

    const DOM = {
        nuevoBtn: document.getElementById('btn-nuevo'),
        editarBtn: document.getElementById('btn-editar'),
        eliminarBtn: document.getElementById('btn-eliminar'),
        saveBtn: document.getElementById('btn-guardar-nuevo'),
        tbody: document.getElementById('rows-body'),
        modalEl: document.getElementById('modalNuevo'),
        modalBackdrop: document.getElementById('modalNuevo').querySelector('[data-close="1"]'),
        modalCancelBtn: document.getElementById('btn-cancelar-modal'),
        modalTitle: document.getElementById('modal-title'),
        obsEl: document.getElementById('f_Obs'),
        calibreEl: document.getElementById('f_Calibre'),
        fibraEl: document.getElementById('f_FibraTrama'),
        codColorEl: document.getElementById('f_CodColor'),
        colorEl: document.getElementById('f_Color')
    };

    const state = {
        selectedRow: null,
        mode: 'create'
    };

    const apiRoutes = {
        calibres: "{{ route('tejido.produccion.reenconado.calibres') }}",
        fibras: "{{ route('tejido.produccion.reenconado.fibras') }}",
        colores: "{{ route('tejido.produccion.reenconado.colores') }}"
    };

    const cache = {
        calibres: null,
        fibras: new Map(),
        colores: new Map()
    };

    const formatNumber = (v, d = 2) => (v === null || v === undefined || v === '') ? '' : Number(v).toFixed(d);

    const getFieldValue = (id) => document.getElementById(id)?.value || null;

    const setFieldValue = (id, value) => {
        const field = document.getElementById(id);
        if (field) field.value = value || '';
    };

    const setSelectOptions = (select, options, placeholder, selectedValue = '') => {
        if (!select) return;
        select.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        select.appendChild(placeholderOption);

        options.forEach((opt) => {
            const option = document.createElement('option');
            if (typeof opt === 'string') {
                option.value = opt;
                option.textContent = opt;
            } else {
                option.value = opt.value;
                option.textContent = opt.label;
                if (opt.name) option.dataset.name = opt.name;
            }
            select.appendChild(option);
        });

        select.value = selectedValue || '';
        select.disabled = options.length === 0;
    };

    const ensureOption = (select, value, label, name = '') => {
        if (!select || !value) return;
        const exists = Array.from(select.options).some(opt => opt.value === value);
        if (!exists) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label || value;
            if (name) option.dataset.name = name;
            select.appendChild(option);
        }
    };

    const getRecordData = () => ({
        Folio: getFieldValue('f_Folio'),
        Date: getFieldValue('f_Date'),
        Turno: getFieldValue('f_Turno'),
        numero_empleado: getFieldValue('f_numero_empleado'),
        nombreEmpl: getFieldValue('f_nombreEmpl'),
        Calibre: getFieldValue('f_Calibre'),
        FibraTrama: getFieldValue('f_FibraTrama'),
        CodColor: getFieldValue('f_CodColor'),
        Color: getFieldValue('f_Color'),
        Cantidad: getFieldValue('f_Cantidad'),
        Conos: getFieldValue('f_Conos'),
        Horas: getFieldValue('f_Horas'),
        Eficiencia: getFieldValue('f_Eficiencia'),
        Obs: getFieldValue('f_Obs')
    });

    const loadRecordToModal = (row) => {
        setFieldValue('f_Folio', row.dataset.folio);
        setFieldValue('f_Date', row.dataset.date);
        setFieldValue('f_Turno', row.dataset.turno);
        setFieldValue('f_numero_empleado', row.dataset.numeroEmpleado);
        setFieldValue('f_nombreEmpl', row.dataset.nombreempl);
        setFieldValue('f_Calibre', row.dataset.calibre);
        setFieldValue('f_FibraTrama', row.dataset.fibratrama);
        setFieldValue('f_CodColor', row.dataset.codcolor);
        setFieldValue('f_Color', row.dataset.color);
        setFieldValue('f_Cantidad', row.dataset.cantidad);
        setFieldValue('f_Conos', row.dataset.conos);
        setFieldValue('f_Horas', row.dataset.horas);
        setFieldValue('f_Eficiencia', row.dataset.eficiencia);
        setFieldValue('f_Obs', row.dataset.obs);
    };

    const updateRowFromData = (row, data) => {
        const cells = row.querySelectorAll('td');
        cells[0].textContent = data.Folio ?? '';
        cells[1].textContent = data.Date ?? '';
        cells[2].textContent = data.Turno ?? '';
        cells[3].textContent = data.nombreEmpl ?? '';
        cells[4].textContent = data.Calibre ?? '';
        cells[5].textContent = data.FibraTrama ?? '';
        cells[6].textContent = data.CodColor ?? '';
        cells[7].textContent = data.Color ?? '';
        cells[8].textContent = formatNumber(data.Cantidad);
        cells[9].textContent = data.Conos ?? '';
        cells[10].textContent = formatNumber(data.Horas);
        cells[11].textContent = formatNumber(data.Eficiencia);
        cells[12].textContent = data.Obs ?? '';

        row.dataset.folio = data.Folio ?? '';
        row.dataset.date = data.Date ?? '';
        row.dataset.turno = data.Turno ?? '';
        row.dataset.numeroEmpleado = data.numero_empleado ?? '';
        row.dataset.nombreempl = data.nombreEmpl ?? '';
        row.dataset.calibre = data.Calibre ?? '';
        row.dataset.fibratrama = data.FibraTrama ?? '';
        row.dataset.codcolor = data.CodColor ?? '';
        row.dataset.color = data.Color ?? '';
        row.dataset.cantidad = formatNumber(data.Cantidad, 2);
        row.dataset.conos = data.Conos ?? '';
        row.dataset.horas = formatNumber(data.Horas, 2);
        row.dataset.eficiencia = formatNumber(data.Eficiencia, 2);
        row.dataset.obs = data.Obs ?? '';
    };

    const rowHtml = (r) => `
        <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50 cursor-pointer"
            data-folio="${r.Folio ?? ''}"
            data-date="${r.Date ?? ''}"
            data-turno="${r.Turno ?? ''}"
            data-numero-empleado="${r.numero_empleado ?? ''}"
            data-nombreempl="${r.nombreEmpl ?? ''}"
            data-calibre="${r.Calibre ?? ''}"
            data-fibratrama="${r.FibraTrama ?? ''}"
            data-codcolor="${r.CodColor ?? ''}"
            data-color="${r.Color ?? ''}"
            data-cantidad="${r.Cantidad ?? ''}"
            data-conos="${r.Conos ?? ''}"
            data-horas="${r.Horas ?? ''}"
            data-eficiencia="${r.Eficiencia ?? ''}"
            data-obs="${r.Obs ?? ''}">
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Folio ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Date ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Turno ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.nombreEmpl ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Calibre ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.FibraTrama ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.CodColor ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Color ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${formatNumber(r.Cantidad)}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Conos ?? ''}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${formatNumber(r.Horas)}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${formatNumber(r.Eficiencia)}</td>
            <td class="text-center whitespace-nowrap px-4 py-3">${r.Obs ?? ''}</td>
        </tr>`;

    const showModal = () => {
        DOM.modalEl.querySelectorAll('input, textarea, select').forEach(i => i.value = '');
        DOM.modalEl.classList.remove('hidden');
        DOM.modalEl.classList.add('flex');
        setTimeout(() => document.getElementById('f_Calibre')?.focus(), 100);
    };

    const hideModal = () => {
        DOM.modalEl.classList.add('hidden');
        DOM.modalEl.classList.remove('flex');
    };

    const determinarTurnoActual = () => {
        const ahora = new Date();
        const tiempoActual = ahora.getHours() * 60 + ahora.getMinutes();
        if (tiempoActual >= 390 && tiempoActual < 870) return 1;
        if (tiempoActual >= 870 && tiempoActual < 1350) return 2;
        return 3;
    };

    const getCalibres = async () => {
        if (cache.calibres) return cache.calibres;
        setSelectOptions(DOM.calibreEl, [], 'Cargando...');
        try {
            const { data } = await axios.get(apiRoutes.calibres);
            const items = (data?.data || []).map(i => i.ItemId).filter(Boolean);
            cache.calibres = items;
            return items;
        } catch (e) {
            toastr.error('No se pudieron cargar calibres');
            return [];
        }
    };

    const getFibras = async (itemId) => {
        if (cache.fibras.has(itemId)) return cache.fibras.get(itemId);
        try {
            const { data } = await axios.get(apiRoutes.fibras, { params: { itemId } });
            const items = (data?.data || []).map(i => i.ConfigId).filter(Boolean);
            cache.fibras.set(itemId, items);
            return items;
        } catch (e) {
            toastr.error('No se pudieron cargar fibras');
            return [];
        }
    };

    const getColores = async (itemId) => {
        if (cache.colores.has(itemId)) return cache.colores.get(itemId);
        try {
            const { data } = await axios.get(apiRoutes.colores, { params: { itemId } });
            const items = (data?.data || []).map(c => ({
                value: c.InventColorId,
                label: `${c.InventColorId} - ${c.Name}`,
                name: c.Name
            })).filter(c => c.value);
            cache.colores.set(itemId, items);
            return items;
        } catch (e) {
            toastr.error('No se pudieron cargar colores');
            return [];
        }
    };

    const resetDependents = () => {
        setSelectOptions(DOM.fibraEl, [], 'Selecciona calibre');
        setSelectOptions(DOM.codColorEl, [], 'Selecciona calibre');
        setFieldValue('f_Color', '');
    };

    const loadDependents = async (itemId, selections = {}) => {
        if (!itemId) {
            resetDependents();
            return;
        }
        setSelectOptions(DOM.fibraEl, [], 'Cargando...');
        setSelectOptions(DOM.codColorEl, [], 'Cargando...');

        const [fibras, colores] = await Promise.all([
            getFibras(itemId),
            getColores(itemId)
        ]);

        setSelectOptions(DOM.fibraEl, fibras, 'Selecciona fibra', selections.fibra || '');
        setSelectOptions(DOM.codColorEl, colores, 'Selecciona color', selections.codColor || '');

        if (selections.fibra) {
            ensureOption(DOM.fibraEl, selections.fibra, selections.fibra);
            DOM.fibraEl.value = selections.fibra;
        }

        if (selections.codColor) {
            ensureOption(DOM.codColorEl, selections.codColor, selections.codColor, selections.colorName);
            DOM.codColorEl.value = selections.codColor;
        }

        const selected = DOM.codColorEl.selectedOptions?.[0];
        setFieldValue('f_Color', selected?.dataset?.name || selections.colorName || '');
    };

    const initMaterialSelectors = async (selections = {}) => {
        const calibres = await getCalibres();
        setSelectOptions(DOM.calibreEl, calibres, 'Selecciona calibre', selections.calibre || '');
        if (selections.calibre) {
            ensureOption(DOM.calibreEl, selections.calibre, selections.calibre);
            DOM.calibreEl.value = selections.calibre;
        }
        await loadDependents(selections.calibre, selections);
    };

    const validateRecord = (record) => {
        const requiredFields = [
            ['Date', 'La fecha es requerida'],
            ['Turno', 'El turno es requerido'],
            ['numero_empleado', 'El número de empleado es requerido'],
            ['nombreEmpl', 'El nombre es requerido'],
            ['Calibre', 'El calibre es requerido'],
            ['FibraTrama', 'La fibra es requerida'],
            ['CodColor', 'El código de color es requerido'],
            ['Color', 'El color es requerido'],
            ['Cantidad', 'La cantidad es requerida'],
            ['Conos', 'Los conos son requeridos'],
            ['Horas', 'Las horas son requeridas'],
            ['Eficiencia', 'La eficiencia es requerida'],
            ['Obs', 'Las observaciones son requeridas']
        ];

        for (const [key, msg] of requiredFields) {
            if (!record[key] && record[key] !== 0) {
                toastr.warning(msg);
                return false;
            }
        }
        return true;
    };

    const setButtonLoading = (loading) => {
        DOM.saveBtn.disabled = loading;
        DOM.saveBtn.innerHTML = loading
            ? '<i class="fa fa-spinner fa-spin mr-2"></i>Guardando...'
            : '<i class="fa fa-save mr-2"></i>Guardar';
    };

    const guardar = async () => {
        const record = getRecordData();
        if (!validateRecord(record)) return;

        setButtonLoading(true);

        try {
            if (state.mode === 'edit' && record.Folio) {
                const url = `{{ route('tejido.produccion.reenconado.update', ['folio' => '__F__']) }}`.replace('__F__', encodeURIComponent(record.Folio));
                const { data } = await axios.put(url, { record });
                if (data?.success) {
                    if (state.selectedRow) updateRowFromData(state.selectedRow, data.data);
                    hideModal();
                    toastr.success('Registro actualizado');
                } else {
                    toastr.error('No se pudo actualizar');
                }
            } else {
                const url = `{{ route('tejido.produccion.reenconado.store') }}`;
                const { data } = await axios.post(url, { modal: 1, record });
                if (data?.success) {
                    DOM.tbody.insertAdjacentHTML('afterbegin', rowHtml(data.data));
                    bindRowClicks();
                    hideModal();
                    toastr.success('Registro guardado exitosamente');
                } else {
                    toastr.error('No se pudo guardar el registro');
                }
            }
        } catch (e) {
            const msg = e?.response?.data?.message || 'Error al guardar el registro';
            toastr.error(msg);
        } finally {
            setButtonLoading(false);
        }
    };

    const updateActionButtons = () => {
        const hasSelection = !!state.selectedRow;
        DOM.editarBtn.disabled = !hasSelection;
        DOM.eliminarBtn.disabled = !hasSelection;
    };

    const bindRowClicks = () => {
        DOM.tbody.querySelectorAll('tr').forEach(row => {
            row.addEventListener('click', () => {
                if (state.selectedRow) {
                    state.selectedRow.classList.remove('selected');
                    state.selectedRow.classList.remove('text-white');
                }
                state.selectedRow = row;
                row.classList.add('selected');
                row.classList.add('text-white');
                updateActionButtons();
            });
        });
    };

    const initNuevo = async () => {
        state.mode = 'create';
        if (state.selectedRow) {
            state.selectedRow.classList.remove('selected');
            state.selectedRow.classList.remove('text-white');
            state.selectedRow = null;
            updateActionButtons();
        }
        showModal();
        DOM.modalTitle.innerHTML = '<i class="fa fa-plus-circle mr-2"></i>Nuevo Registro de Producción';
        resetDependents();
        await initMaterialSelectors();

        try {
            const url = `{{ route('tejido.produccion.reenconado.generar-folio') }}`;
            const { data } = await axios.post(url);
            if (data?.success) {
                setFieldValue('f_Folio', data.folio);
                setFieldValue('f_Date', data.fecha || new Date().toISOString().split('T')[0]);
                setFieldValue('f_Turno', data.turno || '1');
                setFieldValue('f_nombreEmpl', data.usuario);
                setFieldValue('f_numero_empleado', data.numero_empleado);
            } else {
                setFieldValue('f_Folio', `TEMP-${Date.now()}`);
                setFieldValue('f_Date', new Date().toISOString().split('T')[0]);
                setFieldValue('f_Turno', determinarTurnoActual());
            }
        } catch (e) {
            setFieldValue('f_Folio', `TEMP-${Date.now()}`);
            setFieldValue('f_Date', new Date().toISOString().split('T')[0]);
            setFieldValue('f_Turno', determinarTurnoActual());
        }
    };

    const initEditar = async () => {
        if (!state.selectedRow) {
            toastr.info('Selecciona un registro');
            return;
        }
        state.mode = 'edit';
        showModal();
        loadRecordToModal(state.selectedRow);
        await initMaterialSelectors({
            calibre: state.selectedRow.dataset.calibre || '',
            fibra: state.selectedRow.dataset.fibratrama || '',
            codColor: state.selectedRow.dataset.codcolor || '',
            colorName: state.selectedRow.dataset.color || ''
        });
        DOM.modalTitle.innerHTML = `<i class="fa fa-edit mr-2"></i>Editar Registro · Folio ${state.selectedRow.dataset.folio || ''}`;
    };

    const initEliminar = async () => {
        if (!state.selectedRow) {
            toastr.info('Selecciona un registro');
            return;
        }
        const folio = state.selectedRow.dataset.folio;
        if (!folio) {
            toastr.error('Folio inválido');
            return;
        }

        const result = await Swal.fire({
            title: `¿Eliminar folio ${folio}?`,
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
        });

        if (!result.isConfirmed) return;

        try {
            const url = `{{ route('tejido.produccion.reenconado.destroy', ['folio' => '__F__']) }}`.replace('__F__', encodeURIComponent(folio));
            const { data } = await axios.delete(url);
            if (data?.success) {
                state.selectedRow.remove();
                state.selectedRow = null;
                state.mode = 'create';
                updateActionButtons();
                Swal.fire({ icon: 'success', title: 'Eliminado', text: 'Registro eliminado' });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar' });
            }
        } catch (e) {
            const msg = e?.response?.data?.message || 'Error al eliminar el registro';
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        }
    };

    updateActionButtons();
    bindRowClicks();

    DOM.nuevoBtn.addEventListener('click', initNuevo);
    DOM.editarBtn.addEventListener('click', initEditar);
    DOM.eliminarBtn.addEventListener('click', initEliminar);
    DOM.saveBtn.addEventListener('click', guardar);
    DOM.modalBackdrop.addEventListener('click', hideModal);
    DOM.modalCancelBtn.addEventListener('click', hideModal);
    DOM.calibreEl.addEventListener('change', async (e) => {
        const itemId = e.target.value;
        resetDependents();
        await loadDependents(itemId);
    });
    DOM.codColorEl.addEventListener('change', (e) => {
        const selected = e.target.selectedOptions?.[0];
        setFieldValue('f_Color', selected?.dataset?.name || '');
    });

    if (DOM.obsEl) {
        DOM.obsEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                DOM.obsEl.blur();
            }
        });
    }
})();
</script>
@endsection
