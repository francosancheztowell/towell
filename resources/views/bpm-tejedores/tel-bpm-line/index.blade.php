@extends('layouts.app')

@section('page-title', 'BPM - Checklist')

@section('navbar-right')
<div class="flex items-center gap-3">
    @if($header->Status === 'Creado')
        <form method="POST" action="{{ route('tel-bpm.finish', $header->Folio) }}" id="form-finish" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-6 py-2.5 bg-sky-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:bg-sky-600 transition-all duration-200 flex items-center gap-2 group" id="btn-finish">
                <i class="fa-solid fa-check w-4 h-4 group-hover:scale-110 transition-transform duration-200"></i>
                Terminado
            </button>
        </form>
    @elseif($header->Status === 'Terminado')
        <form method="POST" action="{{ route('tel-bpm.authorize', $header->Folio) }}" id="form-authorize" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-6 py-2.5 bg-green-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:bg-green-600 transition-all duration-200 flex items-center gap-2 group" id="btn-authorize">
                <i class="fa-solid fa-thumbs-up w-4 h-4 group-hover:scale-110 transition-transform duration-200"></i>
                Autorizar
            </button>
        </form>
        <form method="POST" action="{{ route('tel-bpm.reject', $header->Folio) }}" id="form-reject" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-6 py-2.5 bg-amber-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:bg-amber-600 transition-all duration-200 flex items-center gap-2 group" id="btn-reject">
                <i class="fa-solid fa-times w-4 h-4 group-hover:scale-110 transition-transform duration-200"></i>
                Rechazar
            </button>
        </form>
    @endif
</div>
@endsection

@section('content')
<div class="max-w-[1200px] mx-auto p-4 pb-16">
    {{-- Header --}}
    <div class="bg-white rounded-xl border p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs text-slate-500">Folio</div>
                <div class="text-xl font-semibold">{{ $header->Folio }}</div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Fecha</div>
                <div class="font-medium">{{ optional($header->Fecha)->format('d/m/Y H:i') }}</div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Recibe</div>
                <div class="font-medium">{{ $header->CveEmplRec }} — {{ $header->NombreEmplRec }} (T: {{ $header->TurnoRecibe }})</div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Entrega</div>
                <div class="font-medium">{{ $header->CveEmplEnt }} — {{ $header->NombreEmplEnt }} (T: {{ $header->TurnoEntrega }})</div>
            </div>
            <div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                    {{ $header->Status==='Autorizado' ? 'bg-green-100 text-green-800' :
                       ($header->Status==='Terminado' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') }}">
                    {{ $header->Status }}
                </span>
            </div>
        </div>
    </div>

    </div>

    {{-- Tabla de checklist --}}
    <div id="grid-wrapper" class="mx-3 -mt-14 mb-2 overflow-auto rounded-lg border bg-white shadow-sm" style="max-height: calc(100vh - 340px);">
        <table id="grid" class="min-w-full text-sm">
            <thead class="bg-gradient-to-r from-blue-500 to-blue-600 text-white sticky top-0 z-30">
                <tr>
                    <th class="px-4 py-3 text-left w-16 font-semibold sticky left-0 bg-gradient-to-r from-blue-500 to-blue-600 z-40">#</th>
                    <th class="px-4 py-3 text-left font-semibold sticky left-16 bg-gradient-to-r from-blue-500 to-blue-600 z-40 min-w-[250px]">Actividad</th>
                    {{-- Columnas de telar existentes --}}
                    @foreach($telares as $t)
                        <th class="px-4 py-3 text-center telar-col font-semibold min-w-[80px] sticky top-0 bg-gradient-to-r from-blue-500 to-blue-600 z-30" data-telar="{{ $t }}">
                            {{ $t }}
                        </th>
                    @endforeach
                </tr>
                
            </thead>
            <tbody>
                @php
                    // Mapa rápido: [Orden][Telar] => Valor
                    $map = [];
                    foreach ($lineas as $ln) {
                        $map[$ln->Orden][$ln->NoTelarId] = $ln->Valor;
                    }
                @endphp
                @forelse($actividades as $a)
                    <tr class="border-t hover:bg-gray-50 transition-colors duration-150 {{ $loop->even ? 'bg-gray-25' : 'bg-white' }}">
                        <td class="px-4 py-3 text-slate-600 font-medium sticky left-0 {{ $loop->even ? 'bg-gray-25' : 'bg-white' }} z-20 border-r">{{ $a['Orden'] }}</td>
                        <td class="px-4 py-3 text-gray-800 text-base font-medium sticky left-16 {{ $loop->even ? 'bg-gray-25' : 'bg-white' }} z-20 border-r min-w-[250px]">{{ $a['Actividad'] }}</td>

                        @foreach($telares as $t)
                            @php $val = $map[$a['Orden']][$t] ?? null; @endphp
                            <td class="px-4 py-3 text-center">
                                <button
                                    class="cell-btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-300
                                        {{ $val==='OK' ? 'bg-green-100 border-green-400 text-green-700 hover:bg-green-200' : 
                                           ($val==='X' ? 'bg-red-100 border-red-400 text-red-700 hover:bg-red-200' : 
                                           'bg-gray-50 border-gray-300 text-gray-400 hover:bg-gray-100 hover:border-gray-400') }}"
                                    data-orden="{{ $a['Orden'] }}"
                                    data-actividad="{{ $a['Actividad'] }}"
                                    data-telar="{{ $t }}"
                                    data-salon="{{ $salonPorTelar[$t] ?? '' }}"
                                    data-valor="{{ $val ?? '' }}"
                                    @if($header->Status !== 'Creado') disabled @endif
                                >
                                   <span class="cell-icon text-lg font-bold">
                                        {!! $val==='OK' ? '✓' : ($val==='X' ? '✗' : '○') !!}
                                   </span>
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="20" class="px-4 py-6 text-center text-slate-500">No hay actividades configuradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Sección de Comentarios --}}
    <div class="bg-white rounded-xl border p-4 mt-4 mb-10">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-700">
                <i class="fa-solid fa-comment text-blue-600 mr-2"></i>Comentarios Generales
            </h3>
            @if($header->Status === 'Creado')
                <button id="btn-save-comentarios" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-save mr-1"></i>Guardar
                </button>
            @endif
        </div>
        <div>
            <textarea 
                id="comentarios-textarea" 
                rows="4" 
                maxlength="1000" 
                placeholder="Ingrese comentarios generales sobre este folio..."
                class="w-full rounded-lg border px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $header->Status !== 'Creado' ? 'bg-gray-50' : '' }}"
                {{ $header->Status !== 'Creado' ? 'readonly' : '' }}
            >{{ $comentarios }}</textarea>
            <div class="flex justify-between items-center mt-2">
                <div class="text-xs text-gray-500">
                    Máximo 1000 caracteres
                </div>
                <div class="text-xs text-gray-500">
                    <span id="char-count">{{ strlen($comentarios ?? '') }}</span>/1000
                </div>
            </div>
        </div>
    </div>

    {{-- Nota --}}
    {{-- <div class="mt-3 text-xs text-slate-500">
        La edición del checklist sólo está permitida en estado <b>Creado</b>.
    </div> --}}
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const editable = @json($header->Status === 'Creado');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const toggleUrl = @json(route('tel-bpm-line.toggle', $header->Folio));

    function toast(icon, title){
        Swal.fire({ icon, title, toast:true, position:'top-end', timer:2000, showConfirmButton:false });
    }

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
            btn.classList.remove('bg-green-50','border-green-300','bg-red-50','border-red-300');
            if (next === 'OK') {
                btn.classList.add('bg-green-50','border-green-300');
                btn.querySelector('.cell-icon').innerHTML = '✓';
            } else if (next === 'X') {
                btn.classList.add('bg-red-50','border-red-300');
                btn.querySelector('.cell-icon').innerHTML = '✗';
            } else {
                btn.querySelector('.cell-icon').innerHTML = '&nbsp;';
            }
        });
    });

    // Checkbox por telar (OK/NO) asociado al que recibe
    document.querySelectorAll('.telar-ok').forEach(chk => {
        chk.addEventListener('change', async () => {
            if (!editable) { toast('info','Edición no permitida'); chk.checked = !chk.checked; return; }
            const telar = chk.dataset.telar;
            const rows = [{
                Orden: 9999,
                NoTelarId: telar,
                Actividad: 'TELAR_OK',
                SalonTejidoId: null,
                TurnoRecibe: @json($header->TurnoRecibe),
                Valor: chk.checked ? 'OK' : null
            }];
            try {
                const res = await fetch(bulkUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ rows })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.msg || 'Error al guardar');
                toast('success', chk.checked ? 'Telar OK' : 'Telar sin marcar');
            } catch (e) {
                toast('error', e.message || 'No se pudo guardar');
                chk.checked = !chk.checked; // revertir en error
            }
        });
    });

    // Agregar columna de telar (local; se guardará al primer clic/toggle)
    const formAdd = document.getElementById('form-add-col');
    const grid = document.getElementById('grid');

    formAdd?.addEventListener('submit', e=>{
        e.preventDefault();
        if (!editable) { toast('info','Edición no permitida'); return; }

        const telar = (document.getElementById('newTelar').value || '').trim();
        const salon = (document.getElementById('newSalon').value || '').trim();

        if (!telar) { toast('warning','Captura No. Telar'); return; }

        // Agrega th
        const th = document.createElement('th');
        th.className = 'px-4 py-3 text-center telar-col font-semibold min-w-[80px] sticky top-0 bg-gradient-to-r from-blue-500 to-blue-600 z-30';
        th.dataset.telar = telar;
        th.innerHTML = `${telar}`;
        grid.tHead.rows[0].appendChild(th);

        // Agrega celdas a cada fila (con botones listos para toggle)
        const rows = [...grid.tBodies[0].rows];
        rows.forEach(tr=>{
            const orden = tr.cells[0]?.textContent?.trim();
            const actTxt = tr.cells[1]?.textContent?.trim() || '';
            const td = document.createElement('td');
            td.className = 'px-3 py-2 text-center';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cell-btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-300 bg-gray-50 border-gray-300 text-gray-400 hover:bg-gray-100 hover:border-gray-400';
            btn.dataset.orden = orden;
            btn.dataset.actividad = actTxt;
            btn.dataset.telar = telar;
            btn.dataset.salon = salon;
            btn.dataset.valor = '';
            btn.innerHTML = '<span class="cell-icon text-lg font-bold">○</span>';

            btn.addEventListener('click', async ()=> {
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
                if (!res.ok || !data.ok){ toast('error', data.msg || 'No se pudo guardar'); return; }
                const next = data.valor;
                btn.dataset.valor = next || '';
                btn.classList.remove('bg-green-100','border-green-400','text-green-700','bg-red-100','border-red-400','text-red-700','bg-gray-50','border-gray-300','text-gray-400');
                if (next === 'OK') {
                    btn.classList.add('bg-green-100','border-green-400','text-green-700');
                    btn.querySelector('.cell-icon').innerHTML = '✓';
                } else if (next === 'X') {
                    btn.classList.add('bg-red-100','border-red-400','text-red-700');
                    btn.querySelector('.cell-icon').innerHTML = '✗';
                } else {
                    btn.classList.add('bg-gray-50','border-gray-300','text-gray-400');
                    btn.querySelector('.cell-icon').innerHTML = '○';
                }
            });

            td.appendChild(btn);
            tr.appendChild(td);
        });

        // limpia inputs
        document.getElementById('newTelar').value = '';
        document.getElementById('newSalon').value = '';
        toast('success','Telar agregado (local)');
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
    confirmAndSubmit('btn-finish','form-finish','¿Marcar como Terminado?');
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
