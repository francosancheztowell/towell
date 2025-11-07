@extends('layouts.app')

@section('title', 'Producción · Reenconado Cabezuela')
@section('page-title')
Producción » Reenconado Cabezuela
@endsection

@push('styles')
<style>
  .table-capture thead th{ position: sticky; top: 0; background:#f8fafc; z-index:5; border-bottom:2px solid #e5e7eb;}
  .table-capture input, .table-capture select { min-width: 110px; }
  .table-capture .w-sm { width: 90px; }
  .table-capture .w-xs { width: 72px; }
  .table-capture .w-lg { min-width: 160px; }
</style>
@endpush

@section('content')
<div class="container mx-auto px-3 md:px-6 py-4">
        @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
                <div class="alert alert-danger">
                        <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                @endforeach
                        </ul>
                </div>
        @endif

        <div class="flex items-center justify-between mb-3">
                <div></div>
                <div>
                        <button type="button" id="btn-nuevo" class="btn btn-primary">
                                <i class="fa fa-plus"></i> Nuevo
                        </button>
                </div>
        </div>

        <div class="table-responsive rounded shadow-sm bg-white">
                <table class="table table-sm table-bordered align-middle mb-0 table-capture" id="tabla-registros">
                        <thead>
                                <tr class="text-center align-middle">
                                        <th class="w-sm">Folio</th>
                                        <th class="w-sm">Fecha</th>
                                        <th class="w-xs">Turno</th>
                                        <th class="w-sm">Operador</th>
                                        <th class="w-lg">Nombre</th>
                                        <th class="w-xs">Calibre</th>
                                        <th class="w-sm">Fibra</th>
                                        <th class="w-sm">Cod color</th>
                                        <th class="w-lg">Nombre color</th>
                                        <th class="w-sm">Cantidad</th>
                                        <th class="w-xs">Conos</th>
                                        <th class="w-sm">Tiempo (hrs)</th>
                                        <th class="w-sm">Eficiencia</th>
                                        <th class="w-lg">Obs</th>
                                        <th class="w-xs">Acciones</th>
                                </tr>
                        </thead>
                        <tbody id="rows-body">
                                @forelse($registros as $r)
                                        <tr>
                                                <td>{{ $r->Folio }}</td>
                                                <td>{{ $r->Date ? $r->Date->format('Y-m-d') : '' }}</td>
                                                <td class="text-center">{{ $r->Turno }}</td>
                                                <td>{{ $r->numero_empleado }}</td>
                                                <td>{{ $r->nombreEmpl }}</td>
                                                <td class="text-end">{{ is_null($r->Calibre) ? '' : number_format($r->Calibre, 2) }}</td>
                                                <td>{{ $r->FibraTrama }}</td>
                                                <td>{{ $r->CodColor }}</td>
                                                <td>{{ $r->Color }}</td>
                                                <td class="text-end">{{ is_null($r->Cantidad) ? '' : number_format($r->Cantidad, 2) }}</td>
                                                <td class="text-center">{{ $r->Conos }}</td>
                                                <td class="text-end">{{ is_null($r->Horas) ? '' : number_format($r->Horas, 2) }}</td>
                                                <td class="text-end">{{ is_null($r->Eficiencia) ? '' : number_format($r->Eficiencia, 2) }}</td>
                                                <td>{{ $r->Obs }}</td>
                                                <td class="text-center">
                                                        <button class="btn btn-outline-secondary btn-sm" disabled title="Editar (próximamente)"><i class="fa fa-pen"></i></button>
                                                        <button class="btn btn-outline-danger btn-sm" disabled title="Eliminar (próximamente)"><i class="fa fa-trash"></i></button>
                                                </td>
                                        </tr>
                                @empty
                                        <tr><td colspan="15" class="text-center text-muted">Sin registros</td></tr>
                                @endforelse
                        </tbody>
                </table>
        </div>
</div>

<!-- Modal Nuevo -->
<div class="modal fade" id="modalNuevo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                                <div class="col-6 col-md-2">
                        <label class="form-label">Folio</label>
                                    <input type="text" class="form-control form-control-sm" id="f_Folio" readonly>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control form-control-sm" id="f_Date">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Turno</label>
                        <input type="number" min="1" max="3" class="form-control form-control-sm" id="f_Turno">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Operador</label>
                        <input type="text" class="form-control form-control-sm" id="f_numero_empleado">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control form-control-sm" id="f_nombreEmpl">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Calibre</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="f_Calibre">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Fibra</label>
                        <input type="text" class="form-control form-control-sm" id="f_FibraTrama">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Cod color</label>
                        <input type="text" class="form-control form-control-sm" id="f_CodColor">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Nombre color</label>
                        <input type="text" class="form-control form-control-sm" id="f_Color">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Cantidad</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="f_Cantidad">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Conos</label>
                        <input type="number" step="1" class="form-control form-control-sm" id="f_Conos">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Tiempo (hrs)</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="f_Horas">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Eficiencia</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="f_Eficiencia">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Obs</label>
                        <input type="text" class="form-control form-control-sm" id="f_Obs">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-guardar-nuevo">
                    <i class="fa fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
    </div>

<script>
(function(){
    const nuevoBtn = document.getElementById('btn-nuevo');
    const saveBtn = document.getElementById('btn-guardar-nuevo');
    const tbody = document.getElementById('rows-body');
    const modalEl = document.getElementById('modalNuevo');
    let modalInstance;

    function rowHtml(r){
        const nf = (v, d=2) => (v===null||v===undefined||v==='') ? '' : Number(v).toFixed(d);
        return `
            <tr>
                <td>${r.Folio??''}</td>
                <td>${r.Date??''}</td>
                <td class="text-center">${r.Turno??''}</td>
                <td>${r.numero_empleado??''}</td>
                <td>${r.nombreEmpl??''}</td>
                <td class="text-end">${nf(r.Calibre)}</td>
                <td>${r.FibraTrama??''}</td>
                <td>${r.CodColor??''}</td>
                <td>${r.Color??''}</td>
                <td class="text-end">${nf(r.Cantidad)}</td>
                <td class="text-center">${r.Conos??''}</td>
                <td class="text-end">${nf(r.Horas)}</td>
                <td class="text-end">${nf(r.Eficiencia)}</td>
                <td>${r.Obs??''}</td>
                <td class="text-center">
                    <button class="btn btn-outline-secondary btn-sm" disabled title="Editar (próximamente)"><i class="fa fa-pen"></i></button>
                    <button class="btn btn-outline-danger btn-sm" disabled title="Eliminar (próximamente)"><i class="fa fa-trash"></i></button>
                </td>
            </tr>`;
    }

    function openModal(){
        if(!modalInstance){ modalInstance = new bootstrap.Modal(modalEl); }
        // limpiar
        modalEl.querySelectorAll('input').forEach(i=> i.value='');
        modalInstance.show();
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
            const url = `{{ route('produccion.reenconado_cabezuela.store') }}`;
            const {data} = await axios.post(url, { modal: 1, record });
            if(data && data.success){
                tbody.insertAdjacentHTML('afterbegin', rowHtml(data.data));
                if(modalInstance) modalInstance.hide();
                toastr.success('Registro guardado');
            }else{
                toastr.error('No se pudo guardar');
            }
        }catch(e){
            const msg = e?.response?.data?.message || 'Error al guardar';
            toastr.error(msg);
        }
    }

    nuevoBtn.addEventListener('click', openModal);
    saveBtn.addEventListener('click', guardar);
        // Al abrir el modal, generar y precargar datos
        nuevoBtn.addEventListener('click', async ()=>{
            try{
                const url = `{{ route('tejido.produccion.reenconado.generar-folio') }}`;
                const {data} = await axios.post(url);
                if(data && data.success){
                    document.getElementById('f_Folio').value = data.folio || '';
                    document.getElementById('f_Date').value = data.fecha || new Date().toISOString().split('T')[0];
                    document.getElementById('f_Turno').value = determinarTurnoActual();
                    document.getElementById('f_nombreEmpl').value = data.usuario || '';
                    document.getElementById('f_numero_empleado').value = data.numero_empleado || '';
                }
            }catch(e){ console.error(e); }
        });
})();
</script>
@endsection
