@extends('layouts.app')

@section('title', 'Producción · Reenconado Cabezuela')
@section('page-title')
Producción Reenconado Cabezuela
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button type="button" id="btn-reporte" class="p-2 rounded-lg transition hover:bg-purple-100" title="Reporte">
            <i class="fa fa-file text-purple-600 text-lg"></i>
        </button>
        <button type="button" id="btn-nuevo" class="p-2 rounded-lg transition hover:bg-green-100" title="Nuevo">
            <i class="fa fa-plus text-green-600 text-lg"></i>
        </button>
        <button type="button" id="btn-editar" class="p-2 rounded-lg transition hover:bg-yellow-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Editar">
            <i class="fa fa-pen text-yellow-500 text-lg"></i>
        </button>
        <button type="button" id="btn-eliminar" class="p-2 rounded-lg transition hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Eliminar">
            <i class="fa fa-trash text-red-600 text-lg"></i>
        </button>
        <button type="button" id="btn-autorizar" class="p-2 rounded-lg transition hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Cambiar Status">
            <i class="fa-solid fa-user-check text-blue-600 text-lg"></i>
        </button>
    </div>
@endsection

@push('styles')
<style>
    /* Solo estilos que no se pueden hacer con Tailwind */
    .table-capture thead th { position: sticky; top: 0; z-index: 5; }
    #tabla-registros tr.selected { background-color: #bfdbfe !important; }
    .modal-header { position: absolute; top: 0; left: 0; right: 0; z-index: 30; }
    .modal-scroll { scroll-padding-top: 72px; }
    .modal-scroll input, .modal-scroll textarea, .modal-scroll select { scroll-margin-top: 72px; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
<div class="w-full ">
        <div class="overflow-x-auto rounded shadow bg-white w-full">
                <table class="min-w-full table-capture text-base" id="tabla-registros">
                        <thead class="text-white">
                <tr class="text-center align-middle">
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Folio</th>
                    <th class="min-w-[180px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Estatus</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Fecha</th>
                    <th class="w-[100px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Turno</th>
                    <th class="min-w-[200px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Operador</th>
                    <th class="w-[100px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Calibre</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Fibra</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Cód. Color</th>
                    <th class="min-w-[180px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Color</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Cantidad</th>
                    <th class="w-[100px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Conos</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Hrs</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Capacidad</th>
                    <th class="w-[120px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Eficiencia</th>
                    <th class="min-w-[200px] bg-blue-500 whitespace-nowrap px-4 py-4 border-b-2 border-gray-200">Observaciones</th>
                </tr>
                        </thead>
                        <tbody id="rows-body" class="text-gray-800">
                                @forelse($registros as $r)
                                        @php
                                            $status = $r->status ?? 'Creado';
                                            $statusClass = match($status) {
                                                'Terminado' => 'text-green-600 font-semibold',
                                                'En Proceso' => 'text-yellow-600 font-semibold',
                                                default => 'text-gray-600'
                                            };
                                        @endphp
                                        <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50"
                                            data-folio="{{ $r->Folio }}"
                                            data-date="{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}"
                                            data-status="{{ $status }}"
                                            data-turno="{{ $r->Turno }}"
                                            data-numero_empleado="{{ $r->numero_empleado }}"
                                            data-nombreempl="{{ $r->nombreEmpl }}"
                                            data-calibre="{{ is_null($r->Calibre) ? '' : number_format($r->Calibre, 2, '.', '') }}"
                                            data-fibratrama="{{ $r->FibraTrama }}"
                                            data-codcolor="{{ $r->CodColor }}"
                                            data-color="{{ $r->Color }}"
                                            data-cantidad="{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2, '.', '') }}"
                                            data-conos="{{ $r->Conos }}"
                                            data-horas="{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2, '.', '') }}"
                                            data-capacidad="{{ $r->capacidad ?? '' }}"
                                            data-eficiencia="{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2, '.', '') }}"
                                            data-obs="{{ $r->Obs }}">
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Folio }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3 {{ $statusClass }}">{{ $status }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Turno }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->nombreEmpl }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Calibre) ? '' : number_format($r->Calibre, 2) }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->FibraTrama }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->CodColor }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Color }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2) }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Conos }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2) }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ !is_null($r->Horas) ? number_format($r->Horas * 9.3, 2) : '' }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ (!is_null($r->Cantidad) && !is_null($r->Horas) && $r->Horas > 0) ? number_format(($r->Cantidad / ($r->Horas * 9.3)) * 100, 2) . '%' : '' }}</td>
                                                <td class="text-center whitespace-nowrap px-4 py-3">{{ $r->Obs }}</td>
                                        </tr>
                                @empty
                                        <tr class="odd:bg-white even:bg-gray-50"><td colspan="15" class="text-center text-gray-500 py-4">Sin registros</td></tr>
                                @endforelse
                        </tbody>
                </table>
        </div>
</div>

<!-- Modal Nuevo (Tailwind) -->
<div id="modalNuevo" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="fixed inset-0 bg-black/50" data-close="1"></div>
    <div class="relative bg-white w-[95vw] max-w-6xl rounded shadow-lg">
            <div class="modal-header bg-gradient-to-r from-blue-500 to-blue-600 text-white border-b flex items-center justify-between p-3">
                <h5 class="text-lg md:text-xl font-semibold" id="modal-title"><i class="fa fa-plus-circle mr-2"></i>Nuevo Registro de Producción</h5>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-3 py-1.5 rounded bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium transition" id="btn-cancelar-modal">
                        <i class="fa fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="button" class="px-3 py-1.5 rounded bg-green-600 text-white hover:bg-green-700 font-medium transition shadow-md" id="btn-guardar-nuevo">
                        <i class="fa fa-save mr-1"></i>Guardar
                    </button>
                    <button type="button" id="btn-cerrar-modal" class="p-2 hover:bg-white/20 rounded transition" aria-label="Close" title="Cerrar">
                        <i class="fa fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            <div class="p-4 modal-scroll max-h-[85vh] overflow-y-auto pt-[72px] pb-7">
                <div class="grid grid-cols-12 gap-4">
                    <!-- Sección: Información General -->
                    <div class="col-span-12">
                        <h6 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                            <i class="fa fa-info-circle mr-2 text-blue-500"></i>Información General
                        </h6>
                    </div>

                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Folio</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Folio" readonly>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                        <input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Date">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                        <select class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Turno">
                            <option value="1">1 </option>
                            <option value="2">2 </option>
                            <option value="3">3 </option>
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. Empleado</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_numero_empleado">
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Operador</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_nombreEmpl">
                    </div>

                    <!-- Sección: Producción -->
                    <div class="col-span-12 mt-2">
                        <h6 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                            <i class="fa fa-chart-line mr-2 text-blue-500"></i>Datos de Producción
                        </h6>
                    </div>

                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad (kg)</label>
                        <input type="number" step="0.01" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Cantidad">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Conos</label>
                        <input type="number" step="1" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Conos">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo (hrs)</label>
                        <input type="number" step="0.01" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Horas">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Capacidad (Auto) <span class="text-gray-400 text-xs">(Hrs×9.3)</span></label>
                        <input type="number" step="0.01" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Capacidad" readonly>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Eficiencia (%) <span class="text-gray-400 text-xs">(Cantidad/Capacidad)×100</span></label>
                        <input type="text" class="w-full min-w-[110px] border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" id="f_Eficiencia" readonly>
                    </div>
                </div>
            </div>
            <!-- Footer eliminado: botones están en el header para que siempre sean visibles -->
    </div>
    </div>

<script>
(function(){
    // Mostrar alertas de sesión con SweetAlert2
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

    const nuevoBtn = document.getElementById('btn-nuevo');
    const editarBtn = document.getElementById('btn-editar');
    const eliminarBtn = document.getElementById('btn-eliminar');
    const autorizarBtn = document.getElementById('btn-autorizar');
    const saveBtn = document.getElementById('btn-guardar-nuevo');
    const tbody = document.getElementById('rows-body');
    const modalEl = document.getElementById('modalNuevo');
    const modalBackdrop = modalEl.querySelector('[data-close="1"]');
    const modalCloseBtn = document.getElementById('btn-cerrar-modal');
    const modalCancelBtn = document.getElementById('btn-cancelar-modal');
    const modalTitle = document.getElementById('modal-title');

    let selectedRow = null;
    let mode = 'create'; // 'create' | 'edit'

    function rowHtml(r){
        const nf = (v, d=2) => (v===null||v===undefined||v==='') ? '' : Number(v).toFixed(d);
        const status = r.status ?? 'Creado';
        const statusColor = status === 'Terminado' ? 'text-green-600 font-semibold' : 
                           status === 'En Proceso' ? 'text-yellow-600 font-semibold' : 
                           'text-gray-600';
        return `
            <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50"
                data-folio="${r.Folio??''}"
                data-date="${r.Date??''}"
                data-status="${status}"
                data-turno="${r.Turno??''}"
                data-numero_empleado="${r.numero_empleado??''}"
                data-nombreempl="${r.nombreEmpl??''}"
                data-calibre="${r.Calibre??''}"
                data-fibratrama="${r.FibraTrama??''}"
                data-codcolor="${r.CodColor??''}"
                data-color="${r.Color??''}"
                data-cantidad="${r.Cantidad??''}"
                data-conos="${r.Conos??''}"
                data-horas="${r.Horas??''}"
                data-capacidad="${r.capacidad??''}"
                data-eficiencia="${r.Eficiencia??''}"
                data-obs="${r.Obs??''}">
                <td class="text-center whitespace-nowrap px-4 py-3">${r.Folio??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3 ${statusColor}">${status}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.Date??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.Turno??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.nombreEmpl??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${nf(r.Calibre)}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.FibraTrama??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.CodColor??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.Color??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${nf(r.Cantidad)}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.Conos??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${nf(r.Horas)}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.capacidad??''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${(r.Cantidad && r.capacidad && r.capacidad > 0) ? nf((r.Cantidad / r.capacidad) * 100) + '%' : ''}</td>
                <td class="text-center whitespace-nowrap px-4 py-3">${r.Obs??''}</td>
            </tr>`;
    }

    function showModal(){
        modalEl.querySelectorAll('input, textarea, select').forEach(i=> i.value='');
        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        setTimeout(()=> document.getElementById('f_Calibre')?.focus(), 100);
    }

    function hideModal(){
        modalEl.classList.add('hidden');
        modalEl.classList.remove('flex');
    }

        // Determinar turno (misma lógica que Nuevo Marcas)
        function determinarTurnoActual(){
            const ahora = new Date();
            const hora = ahora.getHours();
            const minutos = ahora.getMinutes();
            const tiempoActual = hora * 60 + minutos;
            if (tiempoActual >= 390 && tiempoActual < 870) return 1;      // 6:30 - 14:30
            if (tiempoActual >= 870 && tiempoActual < 1350) return 2;     // 14:30 - 22:30
            return 3;                                                     // 22:30 - 6:30
    }

        // Función para calcular capacidad y eficiencia automáticamente
        function calcularCamposAuto(){
            const horas = parseFloat(document.getElementById('f_Horas').value) || 0;
            const cantidad = parseFloat(document.getElementById('f_Cantidad').value) || 0;
            
            // Calcular Capacidad = Horas * 9.3
            const capacidad = horas > 0 ? (horas * 9.3) : 0;
            document.getElementById('f_Capacidad').value = capacidad > 0 ? capacidad.toFixed(2) : '';
            
            // Calcular Eficiencia = (Cantidad / Capacidad) * 100
            const eficiencia = (cantidad > 0 && capacidad > 0) ? ((cantidad / capacidad) * 100) : 0;
            document.getElementById('f_Eficiencia').value = eficiencia > 0 ? eficiencia.toFixed(2) + '%' : '';
        }

        // Agregar event listeners para cálculo automático
        document.getElementById('f_Horas').addEventListener('input', calcularCamposAuto);
        document.getElementById('f_Cantidad').addEventListener('input', calcularCamposAuto);

        async function guardar(){
        const record = {
            Folio: document.getElementById('f_Folio').value || null,
            Date: document.getElementById('f_Date').value || null,
            Turno: document.getElementById('f_Turno').value || null,
            numero_empleado: document.getElementById('f_numero_empleado').value || null,
            nombreEmpl: document.getElementById('f_nombreEmpl').value || null,
            Calibre: null,
            FibraTrama: null,
            CodColor: null,
            Color: null,
            Cantidad: document.getElementById('f_Cantidad').value || null,
            Conos: document.getElementById('f_Conos').value || null,
            Horas: document.getElementById('f_Horas').value || null,
            Obs: null,
            // Eficiencia y Capacidad se calculan automáticamente en el servidor
        };

        // Validación básica
        // Eficiencia y Capacidad son calculadas automáticamente
        const requiredFields = [
            ['Date','La fecha es requerida'],
            ['Turno','El turno es requerido'],
            ['numero_empleado','El número de empleado es requerido'],
            ['nombreEmpl','El nombre es requerido'],
            ['Cantidad','La cantidad es requerida'],
            ['Conos','Los conos son requeridos'],
            ['Horas','Las horas son requeridas'],
        ];
        for(const [key, msg] of requiredFields){
            if(!record[key] && record[key] !== 0){ toastr.warning(msg); return; }
        }

        // Deshabilitar botón mientras guarda
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>Guardando...';

        try{
            if(mode === 'edit' && record.Folio){
                const url = `{{ route('tejido.produccion.reenconado.update', ['folio' => '__F__']) }}`.replace('__F__', encodeURIComponent(record.Folio));
                const {data} = await axios.put(url, { record });
                if(data && data.success){
                    // actualizar fila seleccionada
                    if(selectedRow){
                        const updated = data.data;
                        // actualizar celdas visibles
                        const cells = selectedRow.querySelectorAll('td');
                        const nf = (v, d=2) => (v===null||v===undefined||v==='') ? '' : Number(v).toFixed(d);
                        cells[0].textContent = updated.Folio ?? '';
                        cells[1].textContent = updated.status ?? 'Creado'; // Status
                        cells[2].textContent = updated.Date ?? '';
                        cells[3].textContent = updated.Turno ?? '';
                        cells[4].textContent = updated.nombreEmpl ?? '';
                        cells[5].textContent = nf(updated.Calibre);
                        cells[6].textContent = updated.FibraTrama ?? '';
                        cells[7].textContent = updated.CodColor ?? '';
                        cells[8].textContent = updated.Color ?? '';
                        cells[9].textContent = nf(updated.Cantidad);
                        cells[10].textContent = updated.Conos ?? '';
                        cells[11].textContent = nf(updated.Horas);
                        const capacidadCalc = updated.Horas ? Number(updated.Horas) * 9.3 : 0;
                        cells[12].textContent = capacidadCalc ? nf(capacidadCalc) : ''; // Capacidad calculada
                        cells[13].textContent = (updated.Cantidad && capacidadCalc > 0) ? nf((Number(updated.Cantidad) / capacidadCalc) * 100) + '%' : ''; // Eficiencia en porcentaje
                        cells[14].textContent = updated.Obs ?? '';

                        // actualizar data-* atributos
                        selectedRow.dataset.folio = updated.Folio ?? '';
                        selectedRow.dataset.date = updated.Date ?? '';
                        selectedRow.dataset.turno = updated.Turno ?? '';
                        selectedRow.dataset.numero_empleado = updated.numero_empleado ?? '';
                        selectedRow.dataset.nombreempl = updated.nombreEmpl ?? '';
                        selectedRow.dataset.calibre = updated.Calibre ?? '';
                        selectedRow.dataset.fibratrama = updated.FibraTrama ?? '';
                        selectedRow.dataset.codcolor = updated.CodColor ?? '';
                        selectedRow.dataset.color = updated.Color ?? '';
                        selectedRow.dataset.cantidad = updated.Cantidad ?? '';
                        selectedRow.dataset.conos = updated.Conos ?? '';
                        selectedRow.dataset.horas = updated.Horas ?? '';
                        selectedRow.dataset.capacidad = updated.Horas ? (Number(updated.Horas) * 9.3).toFixed(2) : '';
                        selectedRow.dataset.eficiencia = updated.Eficiencia ?? '';
                        selectedRow.dataset.obs = updated.Obs ?? '';
                    }
                    hideModal();
                    toastr.success('Registro actualizado');
                } else {
                    toastr.error('No se pudo actualizar');
                }
            } else {
                const url = `{{ route('tejido.produccion.reenconado.store') }}`;
                const {data} = await axios.post(url, { modal: 1, record });
                if(data && data.success){
                    tbody.insertAdjacentHTML('afterbegin', rowHtml(data.data));
                    bindRowClicks();
                    hideModal();
                    toastr.options.timeOut = 1200;
                    toastr.options.extendedTimeOut = 600;
                    toastr.options.progressBar = true;
                    toastr.success('Registro guardado exitosamente');
                }else{
                    toastr.error('No se pudo guardar el registro');
                }
            }
        }catch(e){
            console.error('Error al guardar:', e);
            const msg = e?.response?.data?.message || 'Error al guardar el registro';
            toastr.error(msg);
        } finally {
            // Rehabilitar botón
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa fa-save mr-2"></i>Guardar Registro';
        }
    }

    function updateActionButtons(){
        const hasSelection = !!selectedRow;
        editarBtn.disabled = !hasSelection;
        eliminarBtn.disabled = !hasSelection;
        autorizarBtn.disabled = !hasSelection;
        // Las clases disabled:opacity-50 y disabled:cursor-not-allowed ya están en el HTML
    }

    updateActionButtons();

    nuevoBtn.addEventListener('click', async () => {
        mode = 'create';
        if(selectedRow){ selectedRow.classList.remove('selected'); selectedRow = null; updateActionButtons(); }
        // Primero mostrar el modal
        showModal();
        modalTitle.innerHTML = '<i class="fa fa-plus-circle mr-2"></i>Nuevo Registro de Producción';

        try {
            const url = `{{ route('tejido.produccion.reenconado.generar-folio') }}`;
            const {data} = await axios.post(url);

            {{-- console.log('Datos recibidos del backend:', data); --}}

            if(data && data.success){
                document.getElementById('f_Folio').value = data.folio || '';
                document.getElementById('f_Date').value = data.fecha || new Date().toISOString().split('T')[0];
                document.getElementById('f_Turno').value = data.turno || '1';
                document.getElementById('f_nombreEmpl').value = data.usuario || '';
                document.getElementById('f_numero_empleado').value = data.numero_empleado || '';

                {{-- console.log('Campos rellenados:', {
                    folio: document.getElementById('f_Folio').value,
                    fecha: document.getElementById('f_Date').value,
                    turno: document.getElementById('f_Turno').value,
                    nombre: document.getElementById('f_nombreEmpl').value,
                    numero: document.getElementById('f_numero_empleado').value
                }); --}}
            } else {
                // Fallback si falla la llamada
                document.getElementById('f_Folio').value = `TEMP-${Date.now()}`;
                document.getElementById('f_Date').value = new Date().toISOString().split('T')[0];
                document.getElementById('f_Turno').value = determinarTurnoActual();
                document.getElementById('f_nombreEmpl').value = '';
                document.getElementById('f_numero_empleado').value = '';
            }
        } catch(e) {
            console.error('Error al obtener datos:', e);
            // Fallback en caso de error
            document.getElementById('f_Folio').value = `TEMP-${Date.now()}`;
            document.getElementById('f_Date').value = new Date().toISOString().split('T')[0];
            document.getElementById('f_Turno').value = determinarTurnoActual();
            document.getElementById('f_nombreEmpl').value = '';
            document.getElementById('f_numero_empleado').value = '';
        }
    });
    saveBtn.addEventListener('click', guardar);
    modalBackdrop.addEventListener('click', hideModal);
    modalCloseBtn.addEventListener('click', hideModal);
    modalCancelBtn.addEventListener('click', hideModal);

    function bindRowClicks(){
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                if(selectedRow){ selectedRow.classList.remove('selected'); }
                selectedRow = row;
                row.classList.add('selected');
                updateActionButtons();
            });
        });
    }

    bindRowClicks();

    editarBtn.addEventListener('click', () => {
        if(!selectedRow){ toastr.info('Selecciona un registro'); return; }
        mode = 'edit';
        // abrir modal primero (limpia campos)
        showModal();
        // cargar valores del selectedRow al modal
        document.getElementById('f_Folio').value = selectedRow.dataset.folio || '';
        document.getElementById('f_Date').value = selectedRow.dataset.date || '';
        document.getElementById('f_Turno').value = selectedRow.dataset.turno || '';
        document.getElementById('f_numero_empleado').value = selectedRow.dataset.numero_empleado || '';
        document.getElementById('f_nombreEmpl').value = selectedRow.dataset.nombreempl || '';
        document.getElementById('f_Cantidad').value = selectedRow.dataset.cantidad || '';
        document.getElementById('f_Conos').value = selectedRow.dataset.conos || '';
        document.getElementById('f_Horas').value = selectedRow.dataset.horas || '';
        // Calcular automáticamente capacidad y eficiencia
        calcularCamposAuto();
        modalTitle.innerHTML = `<i class="fa fa-edit mr-2"></i>Editar Registro · Folio ${selectedRow.dataset.folio || ''}`;
    });

    eliminarBtn.addEventListener('click', async () => {
        if(!selectedRow){ toastr.info('Selecciona un registro'); return; }
        const folio = selectedRow.dataset.folio;
        if(!folio){ toastr.error('Folio inválido'); return; }

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
        if(!result.isConfirmed) return;

        try{
            const url = `{{ route('tejido.produccion.reenconado.destroy', ['folio' => '__F__']) }}`.replace('__F__', encodeURIComponent(folio));
            const {data} = await axios.delete(url);
            if(data && data.success){
                selectedRow.remove();
                selectedRow = null;
                mode = 'create';
                updateActionButtons();
                Swal.fire({ icon:'success', title:'Eliminado', text:'Registro eliminado' });
            } else {
                Swal.fire({ icon:'error', title:'Error', text:'No se pudo eliminar' });
            }
        }catch(e){
            console.error('Error al eliminar:', e);
            const msg = e?.response?.data?.message || 'Error al eliminar el registro';
            Swal.fire({ icon:'error', title:'Error', text: msg });
        }
    });

    autorizarBtn.addEventListener('click', async () => {
        if(!selectedRow){ toastr.info('Selecciona un registro'); return; }
        const folio = selectedRow.dataset.folio;
        if(!folio){ toastr.error('Folio inválido'); return; }
        
        const statusActual = selectedRow.dataset.status || 'Creado';
        const proximoStatus = statusActual === 'Creado' ? 'En Proceso' : 
                             statusActual === 'En Proceso' ? 'Terminado' : 'Creado';

        try{
            const url = `{{ route('tejido.produccion.reenconado.cambiar-status', ['folio' => '__F__']) }}`.replace('__F__', encodeURIComponent(folio));
            const {data} = await axios.patch(url);
            
            if(data && data.success){
                const nuevoStatus = data.status;
                selectedRow.dataset.status = nuevoStatus;
                
                // Actualizar la celda de status en la fila (segunda columna)
                const statusCell = selectedRow.querySelectorAll('td')[1];
                statusCell.textContent = nuevoStatus;
                
                // Aplicar color según el status
                statusCell.className = 'text-center whitespace-nowrap';
                if(nuevoStatus === 'Terminado'){
                    statusCell.classList.add('text-green-600', 'font-semibold');
                } else if(nuevoStatus === 'En Proceso'){
                    statusCell.classList.add('text-yellow-600', 'font-semibold');
                } else {
                    statusCell.classList.add('text-gray-600');
                }
                
                toastr.success(`Status cambiado a: ${nuevoStatus}`);
            } else {
                toastr.error('No se pudo cambiar el status');
            }
        }catch(e){
            console.error('Error al cambiar status:', e);
            const msg = e?.response?.data?.message || 'Error al cambiar el status';
            toastr.error(msg);
        }
    });
})();
</script>
@endsection
