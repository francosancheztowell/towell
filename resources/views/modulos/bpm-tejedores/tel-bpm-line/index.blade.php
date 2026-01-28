@extends('layouts.app')

@section('page-title', 'BPM - Checklist')

@section('navbar-right')
<div class="flex items-center gap-2">
    @if($header->Status === 'Creado')
        <form method="POST" action="{{ route('tel-bpm.finish', $header->Folio) }}" id="form-finish" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 transition" id="btn-finish">
                <i class="fa-solid fa-check mr-1"></i>Terminado
            </button>
        </form>
    @elseif($header->Status === 'Terminado')
        @if(!empty($esSupervisor) && $esSupervisor)
            <form method="POST" action="{{ route('tel-bpm.authorize', $header->Folio) }}" id="form-authorize" class="inline">
                @csrf @method('PATCH')
                <button type="button" class="px-4 py-2 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 transition" id="btn-authorize">
                    <i class="fa-solid fa-thumbs-up mr-1"></i>Autorizar
                </button>
            </form>
            <form method="POST" action="{{ route('tel-bpm.reject', $header->Folio) }}" id="form-reject" class="inline">
                @csrf @method('PATCH')
                <button type="button" class="px-4 py-2 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 transition" id="btn-reject">
                    <i class="fa-solid fa-times mr-1"></i>Rechazar
                </button>
            </form>
        @else
            <button type="button" class="px-4 py-2 bg-green-300 text-white rounded-lg cursor-not-allowed" title="Solo un supervisor puede autorizar" disabled>Autorizar</button>
            <button type="button" class="px-4 py-2 bg-amber-300 text-white rounded-lg cursor-not-allowed" title="Solo un supervisor puede rechazar" disabled>Rechazar</button>
        @endif
    @endif
</div>
@endsection

@section('content')
<div class="max-w-full mx-auto p-2 pb-4">
    {{-- Header --}}
    <div class="bg-white rounded-xl border p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-md text-slate-500">Folio</div>
                <div class="text-xl font-semibold">{{ $header->Folio }}</div>
            </div>
            <div>
                <div class="text-md text-slate-500">Fecha</div>
                <div class="font-medium">{{ optional($header->Fecha)->format('d/m/Y H:i') }}</div>
            </div>
            <div>
                <div class="text-md text-slate-500">Recibe</div>
                <div class="font-medium">{{ $header->CveEmplRec }} — {{ $header->NombreEmplRec }} (Turno {{ $header->TurnoRecibe }})</div>
            </div>
            <div>
                <div class="text-md text-slate-500">Entrega</div>
                <div class="font-medium">{{ $header->CveEmplEnt }} — {{ $header->NombreEmplEnt }} (Turno {{ $header->TurnoEntrega }})</div>
            </div>
            <div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-md
                    {{ $header->Status==='Autorizado' ? 'bg-green-100 text-green-800' :
                       ($header->Status==='Terminado' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') }}">
                    {{ $header->Status }}
                </span>
            </div>
        </div>
    </div>

    {{-- Tabla de checklist --}}
    <div id="grid-wrapper" class="overflow-auto rounded-lg border bg-white" style="max-height: calc(100vh - 280px);">
        <table id="grid" class="min-w-full text-sm">
            <thead class="bg-blue-500 text-white sticky top-0 z-30">
                <tr>
                    <th class="px-1 py-2 text-left w-8 font-semibold sticky left-0 bg-blue-500 z-40">#</th>
                    <th class="px-1 py-2 text-left font-semibold sticky left-8 bg-blue-500 z-40 min-w-[120px] max-w-[120px]">Actividad</th>
                    @foreach($telares as $t)
                        <th class="px-1 py-2 text-center font-semibold min-w-[60px] sticky top-0 bg-blue-500 z-30" data-telar="{{ $t }}">
                            <div class="truncate text-xs">{{ $t }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $map = [];
                    foreach ($lineas as $ln) {
                        $map[$ln->Orden][$ln->NoTelarId] = $ln->Valor;
                    }
                @endphp
                @forelse($actividades as $a)
                    <tr class="hover:bg-gray-50 {{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                        <td class="px-1 py-2 text-slate-600 font-medium sticky left-0 {{ $loop->even ? 'bg-gray-50' : 'bg-white' }} z-20">{{ $loop->iteration }}</td>
                        <td class="px-1 py-2 text-gray-800 font-medium sticky left-8 {{ $loop->even ? 'bg-gray-50' : 'bg-white' }} z-20 min-w-[120px] max-w-[120px] text-xs truncate" title="{{ $a['Actividad'] }}">{{ $a['Actividad'] }}</td>
                        @foreach($telares->slice(0, 10) as $t)
                            @php $val = $map[$a['Orden']][$t] ?? null; @endphp
                            <td class="px-0.5 py-2 text-center">
                                <button
                                    class="cell-btn inline-flex items-center justify-center w-7 h-7 rounded border-2 transition
                                        {{ $val==='OK' ? 'bg-green-100 border-green-400 text-green-700 hover:bg-green-200' :
                                           ($val==='X' ? 'bg-red-100 border-red-400 text-red-700 hover:bg-red-200' :
                                           'bg-gray-50 border-gray-300 text-gray-400 hover:bg-gray-100') }}"
                                    data-orden="{{ $a['Orden'] }}"
                                    data-actividad="{{ $a['Actividad'] }}"
                                    data-telar="{{ $t }}"
                                    data-salon="{{ $salonPorTelar[$t] ?? '' }}"
                                    data-valor="{{ $val ?? '' }}"
                                    @if($header->Status !== 'Creado') disabled @endif
                                >
                                   <span class="cell-icon font-bold">{{ $val==='OK' ? '✓' : ($val==='X' ? '✗' : '○') }}</span>
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="20" class="px-4 py-4 text-center text-slate-500">No hay actividades configuradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Sección de Comentarios --}}
    <div class="bg-white rounded-lg border p-2 mt-2">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-base font-semibold text-gray-700">
                <i class="fa-solid fa-comment text-blue-600 mr-1"></i>Comentarios
            </h3>
            @if($header->Status === 'Creado')
                <button id="btn-save-comentarios" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:opacity-50 text-sm">
                    <i class="fa-solid fa-save mr-1"></i>Guardar
                </button>
            @endif
        </div>
        <textarea
            id="comentarios-textarea"
            rows="3"
            maxlength="1000"
            placeholder="Ingrese comentarios..."
            class="w-full rounded border px-2 py-1 text-sm resize-none focus:ring-1 focus:ring-blue-500 {{ $header->Status !== 'Creado' ? 'bg-gray-50' : '' }}"
            {{ $header->Status !== 'Creado' ? 'readonly' : '' }}
        >{{ $comentarios }}</textarea>

    </div>
</div>

<script>
(function(){
    const editable = @json($header->Status === 'Creado');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const toggleUrl = @json(route('tel-bpm-line.toggle', $header->Folio));

    function toast(icon, title){
        Swal.fire({ icon, title, toast:true, position:'top-end', timer:2000, showConfirmButton:false });
    }

    // Mostrar mensajes de sesión (success/error) al cargar la página
    @if(session('success'))
        toast('success', @json(session('success')));
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: @json(session('error')),
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#3085d6'
        });
    @endif

    // Tri-estado por celda: vacío → ✓ → ✗ → vacío (guarda sólo el folio actual)
    document.querySelectorAll('.cell-btn').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
            if (!editable) { toast('info','Edición no permitida'); return; }

            const payload = {
                Orden: parseInt(btn.dataset.orden),
                NoTelarId: btn.dataset.telar,
                SalonTejidoId: btn.dataset.salon || null,
                TurnoRecibe: @json($header->TurnoRecibe),
                Actividad: btn.dataset.actividad
            };

            const res = await fetch(toggleUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (!res.ok || !data.ok){
                toast('error', data.msg || 'No se pudo guardar'); return;
            }

            // Actualiza UI (NULL → OK → X → NULL)
            const next = data.valor; // 'OK' | 'X' | null
            btn.dataset.valor = next || '';
            btn.classList.remove('bg-green-100','border-green-400','text-green-700','hover:bg-green-200','bg-red-100','border-red-400','text-red-700','hover:bg-red-200','bg-gray-50','border-gray-300','text-gray-400','hover:bg-gray-100','hover:border-gray-400');
            if (next === 'OK') {
                btn.classList.add('bg-green-100','border-green-400','text-green-700','hover:bg-green-200');
                btn.querySelector('.cell-icon').innerHTML = '✓';
            } else if (next === 'X') {
                btn.classList.add('bg-red-100','border-red-400','text-red-700','hover:bg-red-200');
                btn.querySelector('.cell-icon').innerHTML = '✗';
            } else {
                btn.classList.add('bg-gray-50','border-gray-300','text-gray-400','hover:bg-gray-100','hover:border-gray-400');
                btn.querySelector('.cell-icon').innerHTML = '○';
            }
        });
    });


    // Confirmaciones de estado
    const confirmAndSubmit = (btnId, formId, title) => {
        const b = document.getElementById(btnId), f = document.getElementById(formId);
        if (!b || !f) return;
        b.addEventListener('click', ()=>{
            Swal.fire({ title, icon:'question', showCancelButton:true,
                confirmButtonText:'Aceptar', cancelButtonText:'Cancelar'
            }).then(r=>{ if(r.isConfirmed) f.submit(); });
        });
    };

    // Validación especial para Finalizar: verificar actividades sin completar
    const btnFinish = document.getElementById('btn-finish');
    const formFinish = document.getElementById('form-finish');
    if (btnFinish && formFinish) {
        btnFinish.addEventListener('click', () => {
            // Contar actividades sin completar (celdas vacías - sin OK ni X)
            const allCells = document.querySelectorAll('.cell-btn');
            let incompletas = 0;
            allCells.forEach(btn => {
                if ((btn.dataset.valor || '') === '') incompletas++;
            });

            if (incompletas > 0) {
                Swal.fire({
                    title: 'No se han completado todas las actividades',
                    text: '¿Desea continuar de todos modos?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, Finalizar',
                    cancelButtonText: 'Cancelar',
                }).then(r => {
                    if (r.isConfirmed) formFinish.submit();
                });
            } else {
                Swal.fire({
                    title: '¿Marcar como Terminado?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Aceptar',
                    cancelButtonText: 'Cancelar'
                }).then(r => {
                    if (r.isConfirmed) formFinish.submit();
                });
            }
        });
    }

    confirmAndSubmit('btn-authorize','form-authorize','¿Autorizar folio?');
    confirmAndSubmit('btn-reject','form-reject','¿Rechazar y regresar a Creado?');

    // Sobrescribir comportamiento del botón "atrás" para ir siempre al índice actualizado
    const btnBack = document.getElementById('btn-back');
    if (btnBack) {
        // Remover event listeners existentes clonando el elemento
        const newBtnBack = btnBack.cloneNode(true);
        btnBack.parentNode.replaceChild(newBtnBack, btnBack);

        // Agregar nuevo comportamiento
        newBtnBack.addEventListener('click', function() {
            window.location.href = '{{ route("tel-bpm.index") }}';
        });
    }

    // Manejo de comentarios
    const comentariosTextarea = document.getElementById('comentarios-textarea');
    const btnSaveComentarios = document.getElementById('btn-save-comentarios');
    const charCount = document.getElementById('char-count');
    const comentariosUrl = @json(route('tel-bpm-line.comentarios', $header->Folio));

    // Contador de caracteres
    comentariosTextarea?.addEventListener('input', function() {
        const count = this.value.length;
        charCount.textContent = count;
        if (count > 1000) {
            charCount.classList.add('text-red-500');
        } else {
            charCount.classList.remove('text-red-500');
        }
    });

    // Guardar comentarios
    btnSaveComentarios?.addEventListener('click', async function() {
        if (!editable) {
            toast('info', 'Edición no permitida');
            return;
        }

        const comentarios = comentariosTextarea.value;
        if (comentarios.length > 1000) {
            toast('error', 'Los comentarios exceden el límite de 1000 caracteres');
            return;
        }

        try {
            btnSaveComentarios.disabled = true;
            btnSaveComentarios.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Guardando...';

            const response = await fetch(comentariosUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    Comentarios: comentarios
                })
            });

            const data = await response.json();

            if (response.ok && data.ok) {
                toast('success', data.msg || 'Comentarios guardados');
            } else {
                toast('error', data.msg || 'Error al guardar comentarios');
            }
        } catch (error) {
            toast('error', 'Error de conexión');
        } finally {
            btnSaveComentarios.disabled = false;
            btnSaveComentarios.innerHTML = '<i class="fa-solid fa-save mr-1"></i>Guardar';
        }
    });
})();
</script>
@endsection
