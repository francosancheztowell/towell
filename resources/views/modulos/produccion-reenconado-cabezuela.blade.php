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
                        <button type="button" id="btn-reporte" class="px-3 py-2 rounded bg-amber-300 text-black shadow hover:opacity-90 transition">
                                <i class="fa fa-file-alt"></i> Reporte
                        </button>
                        <button type="button" id="btn-nuevo" class="px-3 py-2 rounded bg-green-500 text-white shadow hover:bg-green-600 transition">
                                <i class="fa fa-plus"></i> Nuevo
                        </button>
                        <button type="button" id="btn-modificar" class="px-3 py-2 rounded bg-amber-400 text-black shadow hover:opacity-90 transition">
                                <i class="fa fa-edit"></i> Modificar
                        </button>
                        <button type="button" id="btn-eliminar" class="px-3 py-2 rounded bg-red-500 text-white shadow hover:bg-red-600 transition">
                                <i class="fa fa-trash"></i> Eliminar
                        </button>
                </div>
        </div>

        <div class="overflow-x-auto rounded shadow bg-white">
                <table class="min-w-full table-capture text-sm" id="tabla-registros">
                        <thead class="text-white">
                <tr class="text-center align-middle">
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Folio</th>
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Fecha</th>
                    <th class="w-xs bg-blue-400 whitespace-nowrap">Turno</th>
                    <th class="w-lg bg-blue-400 whitespace-nowrap">Nombre</th>
                    <th class="w-xs bg-blue-400 whitespace-nowrap">Calibre</th>
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Fibra</th>
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Cod color</th>
                    <th class="w-lg bg-blue-400 whitespace-nowrap">Nombre color</th>
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Cantidad</th>
                    <th class="w-xs bg-blfue-400 whitespace-nowrap">Conos</th>
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Tiempo (hrs)</th>
                    <th class="w-sm bg-blue-400 whitespace-nowrap">Eficiencia</th>
                    <th class="w-lg bg-blue-400 whitespace-nowrap">Obs</th>
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
            <div class="sticky top-0 bg-white border-b flex items-center justify-between p-3">
                <h5 class="text-lg font-semibold">Nuevo registro</h5>
                <button type="button" id="btn-cerrar-modal" class="p-2 text-gray-500 hover:text-gray-700" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="p-3">
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Folio</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Folio" readonly>
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Fecha</label>
                        <input type="date" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Date">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Turno</label>
                        <input type="number" min="1" max="3" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Turno">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700">Operador</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_numero_empleado">
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_nombreEmpl">
                    </div>

                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Calibre</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Calibre">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Fibra</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_FibraTrama">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Cod color</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_CodColor">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700">Nombre color</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Color">
                    </div>

                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Cantidad</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Cantidad">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Conos</label>
                        <input type="number" step="1" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Conos">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Tiempo (hrs)</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Horas">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Eficiencia</label>
                        <input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Eficiencia">
                    </div>
                    <div class="col-span-12 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Obs</label>
                        <input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" id="f_Obs">
                    </div>
                </div>
            </div>
            <div class="p-3 border-t flex justify-end gap-2">
                <button type="button" class="px-3 py-2 rounded bg-gray-200 hover:bg-gray-300" id="btn-cancelar-modal">Cancelar</button>
                <button type="button" class="px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700" id="btn-guardar-nuevo">
                    <i class="fa fa-save"></i> Guardar
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
                <td class="whitespace-nowrap">${r.numero_empleado??''}</td>
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
                <td class="text-center whitespace-nowrap">
                    <button class="px-2 py-1 rounded border text-gray-600 border-gray-300 text-xs" disabled title="Editar (próximamente)"><i class="fa fa-pen"></i></button>
                    <button class="px-2 py-1 rounded border text-red-600 border-red-300 text-xs" disabled title="Eliminar (próximamente)"><i class="fa fa-trash"></i></button>
                </td>
            </tr>`;
    }

    function showModal(){
        modalEl.querySelectorAll('input').forEach(i=> i.value='');
        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        // foco al primer campo editable
        setTimeout(()=> document.getElementById('f_Date')?.focus(), 0);
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

        try{
            const url = `{{ route('tejido.produccion.reenconado.store') }}`;
            const {data} = await axios.post(url, { modal: 1, record });
            if(data && data.success){
                tbody.insertAdjacentHTML('afterbegin', rowHtml(data.data));
                hideModal();
                toastr.success('Registro guardado');
            }else{
                toastr.error('No se pudo guardar');
            }
        }catch(e){
            const msg = e?.response?.data?.message || 'Error al guardar';
            toastr.error(msg);
        }
    }

    nuevoBtn.addEventListener('click', () => {
        const fechaActual = new Date().toISOString().split('T')[0];
        const folioTemporal = `TEMP-${Date.now()}`; // Genera un folio temporal único
        document.getElementById('f_Folio').value = folioTemporal;
        document.getElementById('f_Date').value = fechaActual;
        document.getElementById('f_Turno').value = determinarTurnoActual();
        document.getElementById('f_nombreEmpl').value = ''; // Limpia el nombre del empleado
        document.getElementById('f_numero_empleado').value = ''; // Limpia el número del empleado
        showModal();
    });
    saveBtn.addEventListener('click', guardar);
    modalBackdrop.addEventListener('click', hideModal);
    modalCloseBtn.addEventListener('click', hideModal);
    modalCancelBtn.addEventListener('click', hideModal);
})();
</script>
@endsection
