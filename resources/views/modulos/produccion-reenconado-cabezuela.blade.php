@extends('layouts.app')

@section('title', 'Producción · Reenconado Cabezuela')
@section('page-title')
Producción Reenconado Cabezuela
@endsection

@push('styles')
<style>
    .table-capture thead th{ position: sticky; top: 0; z-index:5; border-bottom:2px solid #e5e7eb;}
    .table-capture input, .table-capture select { min-width: 110px; }
    .table-capture .w-sm { width: 90px; }
    .table-capture .w-xs { width: 72px; }
    .table-capture .w-lg { min-width: 160px; }
    .table-capture td, .table-capture th { padding: 0.5rem 0.75rem; }
</style>
@endpush

@section('content')
<div class="container mx-auto px-3 md:px-6 py-4">
        @if(session('success'))
                <div class="rounded-md bg-green-100 text-green-800 px-3 py-2 mb-3">{{ session('success') }}</div>
        @endif
        @if($errors->any())
                <div class="rounded-md bg-red-100 text-red-800 px-3 py-2 mb-3">
                        <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                @endforeach
                        </ul>
                </div>
        @endif

        <div class="flex items-center justify-between mb-3">
                <div></div>
                <div class="flex gap-2">
                        <button type="button" id="btn-nuevo" class="px-4 py-2 rounded bg-green-500 text-white shadow hover:bg-green-600 transition font-medium">
                                <i class="fa fa-plus"></i> Nuevo Registro
                        </button>
                </div>
        </div>

        <div class="overflow-x-auto rounded shadow bg-white">
                <table class="min-w-full table-capture text-sm" id="tabla-registros">
                        <thead class="text-white">
                <tr class="text-center align-middle">
                    <th class="w-xs bg-blue-500 whitespace-nowrap">Acciones</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Folio</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Fecha</th>
                    <th class="w-xs bg-blue-500 whitespace-nowrap">Turno</th>
                    <th class="w-lg bg-blue-500 whitespace-nowrap">Operador</th>
                    <th class="w-xs bg-blue-500 whitespace-nowrap">Calibre</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Fibra</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Cód. Color</th>
                    <th class="w-lg bg-blue-500 whitespace-nowrap">Color</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Cantidad</th>
                    <th class="w-xs bg-blue-500 whitespace-nowrap">Conos</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Hrs</th>
                    <th class="w-sm bg-blue-500 whitespace-nowrap">Eficiencia</th>
                    <th class="w-lg bg-blue-500 whitespace-nowrap">Observaciones</th>
                </tr>
                        </thead>
                        <tbody id="rows-body" class="text-gray-800">
                                @forelse($registros as $r)
                                        <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50">
                                                <td class="whitespace-nowrap">{{ $r->Folio }}</td>
                                                <td class="whitespace-nowrap">{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}</td>
                                                <td class="text-center whitespace-nowrap">{{ $r->Turno }}</td>
                                                <td class="whitespace-nowrap">{{ $r->nombreEmpl }}</td>
                                                <td class="text-right whitespace-nowrap">{{ is_null($r->Calibre) ? '' : number_format($r->Calibre, 2) }}</td>
                                                <td class="whitespace-nowrap">{{ $r->FibraTrama }}</td>
                                                <td class="whitespace-nowrap">{{ $r->CodColor }}</td>
                                                <td class="whitespace-nowrap">{{ $r->Color }}</td>
                                                <td class="text-right whitespace-nowrap">{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2) }}</td>
                                                <td class="text-center whitespace-nowrap">{{ $r->Conos }}</td>
                                                <td class="text-right whitespace-nowrap">{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2) }}</td>
                                                <td class="text-right whitespace-nowrap">{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2) }}</td>
                                                <td class="whitespace-nowrap">{{ $r->Obs }}</td>
                                        </tr>
                                @empty
                                        <tr class="odd:bg-white even:bg-gray-50"><td colspan="13" class="text-center text-gray-500 py-3">Sin registros</td></tr>
                                @endforelse
                        </tbody>
                </table>
        </div>
</div>

<!-- Modal Nuevo (Tailwind) -->
<div id="modalNuevo" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="fixed inset-0 bg-black/50" data-close="1"></div>
    <div class="relative bg-white w-[95vw] max-w-6xl max-h-[85vh] overflow-y-auto rounded shadow-lg">
            <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-blue-600 text-white border-b flex items-center justify-between p-4">
                <h5 class="text-xl font-semibold"><i class="fa fa-plus-circle mr-2"></i>Nuevo Registro de Producción</h5>
                <button type="button" id="btn-cerrar-modal" class="p-2 hover:bg-white/20 rounded transition" aria-label="Close">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-4">
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
                        <select class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Turno">
                            <option value="1">1 (6:30 - 14:30)</option>
                            <option value="2">2 (14:30 - 22:30)</option>
                            <option value="3">3 (22:30 - 6:30)</option>
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

                    <!-- Sección: Detalles del Material -->
                    <div class="col-span-12 mt-2">
                        <h6 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                            <i class="fa fa-box mr-2 text-blue-500"></i>Detalles del Material
                        </h6>
                    </div>

                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Calibre</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Calibre">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fibra</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_FibraTrama">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cód. Color</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_CodColor">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Color</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Color">
                    </div>

                    <!-- Sección: Producción -->
                    <div class="col-span-12 mt-2">
                        <h6 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                            <i class="fa fa-chart-line mr-2 text-blue-500"></i>Datos de Producción
                        </h6>
                    </div>

                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad (kg)</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Cantidad">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Conos</label>
                        <input type="number" step="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Conos">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo (hrs)</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Horas">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Eficiencia (%)</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Eficiencia">
                    </div>
                    <div class="col-span-12">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                        <textarea class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="f_Obs" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-t flex justify-end gap-3">
                <button type="button" class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium transition" id="btn-cancelar-modal">
                    <i class="fa fa-times mr-2"></i>Cancelar
                </button>
                <button type="button" class="px-5 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 font-medium transition shadow-md" id="btn-guardar-nuevo">
                    <i class="fa fa-save mr-2"></i>Guardar Registro
                </button>
            </div>
    </div>
    </div>

<script>
(function(){
    const nuevoBtn = document.getElementById('btn-nuevo');
    const saveBtn = document.getElementById('btn-guardar-nuevo');
    const tbody = document.getElementById('rows-body');
    const modalEl = document.getElementById('modalNuevo');
    const modalBackdrop = modalEl.querySelector('[data-close="1"]');
    const modalCloseBtn = document.getElementById('btn-cerrar-modal');
    const modalCancelBtn = document.getElementById('btn-cancelar-modal');

    function rowHtml(r){
        const nf = (v, d=2) => (v===null||v===undefined||v==='') ? '' : Number(v).toFixed(d);
        return `
            <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50">
                <td class="whitespace-nowrap">${r.Folio??''}</td>
                <td class="whitespace-nowrap">${r.Date??''}</td>
                <td class="text-center whitespace-nowrap">${r.Turno??''}</td>
                <td class="whitespace-nowrap">${r.nombreEmpl??''}</td>
                <td class="text-right whitespace-nowrap">${nf(r.Calibre)}</td>
                <td class="whitespace-nowrap">${r.FibraTrama??''}</td>
                <td class="whitespace-nowrap">${r.CodColor??''}</td>
                <td class="whitespace-nowrap">${r.Color??''}</td>
                <td class="text-right whitespace-nowrap">${nf(r.Cantidad)}</td>
                <td class="text-center whitespace-nowrap">${r.Conos??''}</td>
                <td class="text-right whitespace-nowrap">${nf(r.Horas)}</td>
                <td class="text-right whitespace-nowrap">${nf(r.Eficiencia)}</td>
                <td class="whitespace-nowrap">${r.Obs??''}</td>
            </tr>`;
    }

    function showModal(){
        modalEl.querySelectorAll('input, textarea, select').forEach(i=> i.value='');
        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        setTimeout(()=> document.getElementById('f_Date')?.focus(), 100);
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

        async function guardar(){
        const record = {
            Folio: document.getElementById('f_Folio').value || null,
            Date: document.getElementById('f_Date').value || null,
            Turno: document.getElementById('f_Turno').value || null,
            numero_empleado: document.getElementById('f_numero_empleado').value || null,
            nombreEmpl: document.getElementById('f_nombreEmpl').value || null,
            Calibre: document.getElementById('f_Calibre').value || null,
            FibraTrama: document.getElementById('f_FibraTrama').value || null,
            CodColor: document.getElementById('f_CodColor').value || null,
            Color: document.getElementById('f_Color').value || null,
            Cantidad: document.getElementById('f_Cantidad').value || null,
            Conos: document.getElementById('f_Conos').value || null,
            Horas: document.getElementById('f_Horas').value || null,
            Eficiencia: document.getElementById('f_Eficiencia').value || null,
            Obs: document.getElementById('f_Obs').value || null,
        };

        // Validación básica
        if (!record.Date) {
            toastr.warning('La fecha es requerida');
            return;
        }

        // Deshabilitar botón mientras guarda
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>Guardando...';

        try{
            const url = `{{ route('tejido.produccion.reenconado.store') }}`;
            const {data} = await axios.post(url, { modal: 1, record });
            if(data && data.success){
                tbody.insertAdjacentHTML('afterbegin', rowHtml(data.data));
                hideModal();
                toastr.success('Registro guardado exitosamente');
            }else{
                toastr.error('No se pudo guardar el registro');
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

    nuevoBtn.addEventListener('click', async () => {
        // Primero mostrar el modal
        showModal();
        
        try {
            const url = `{{ route('tejido.produccion.reenconado.generar-folio') }}`;
            const {data} = await axios.post(url);
            
            console.log('Datos recibidos del backend:', data);
            
            if(data && data.success){
                document.getElementById('f_Folio').value = data.folio || '';
                document.getElementById('f_Date').value = data.fecha || new Date().toISOString().split('T')[0];
                document.getElementById('f_Turno').value = data.turno || '1';
                document.getElementById('f_nombreEmpl').value = data.usuario || '';
                document.getElementById('f_numero_empleado').value = data.numero_empleado || '';
                
                console.log('Campos rellenados:', {
                    folio: document.getElementById('f_Folio').value,
                    fecha: document.getElementById('f_Date').value,
                    turno: document.getElementById('f_Turno').value,
                    nombre: document.getElementById('f_nombreEmpl').value,
                    numero: document.getElementById('f_numero_empleado').value
                });
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
})();
</script>
@endsection
