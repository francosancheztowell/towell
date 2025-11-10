@extends('layouts.app')

@section('page-title', 'BPM - Checklist')

@section('content')
<div class="max-w-[1200px] mx-auto p-4">
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

        <div class="flex items-center gap-2">
            @if($header->Status === 'Creado')
                <form method="POST" action="{{ route('tel-bpm.finish', $header->Folio) }}" id="form-finish">
                    @csrf @method('PATCH')
                    <button type="button" class="rounded-lg px-4 py-2 bg-sky-600 text-white hover:bg-sky-700" id="btn-finish">
                        Terminado
                    </button>
                </form>
            @elseif($header->Status === 'Terminado')
                <form method="POST" action="{{ route('tel-bpm.authorize', $header->Folio) }}" id="form-authorize">
                    @csrf @method('PATCH')
                    <button type="button" class="rounded-lg px-4 py-2 bg-green-600 text-white hover:bg-green-700" id="btn-authorize">
                        Autorizar
                    </button>
                </form>
                <form method="POST" action="{{ route('tel-bpm.reject', $header->Folio) }}" id="form-reject">
                    @csrf @method('PATCH')
                    <button type="button" class="rounded-lg px-4 py-2 bg-amber-600 text-white hover:bg-amber-700" id="btn-reject">
                        Rechazar
                    </button>
                </form>
            @endif
            {{-- <a href="{{ route('tel-bpm.index') }}" class="rounded-lg px-3 py-2 border hover:bg-slate-50">Regresar</a> --}}
        </div>
    </div>

    {{-- Tabla de checklist --}}
    <div id="grid-wrapper" class="overflow-x-auto rounded-lg border bg-white">
        <table id="grid" class="min-w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="px-3 py-2 text-left w-12">#</th>
                    <th class="px-3 py-2 text-left">Actividad</th>
                    {{-- Columnas de telar existentes --}}
                    @foreach($telares as $t)
                        <th class="px-3 py-2 text-center telar-col" data-telar="{{ $t }}">{{ $t }}<div class="text-[10px] text-slate-500">T: {{ $header->TurnoEntrega }}</div></th>
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
                    <tr class="border-t">
                        <td class="px-3 py-2 text-slate-500">{{ $a['Orden'] }}</td>
                        <td class="px-3 py-2">{{ $a['Actividad'] }}</td>

                        @foreach($telares as $t)
                            @php $val = $map[$a['Orden']][$t] ?? null; @endphp
                            <td class="px-3 py-2 text-center">
                                <button
                                    class="cell-btn inline-flex items-center justify-center w-8 h-8 rounded-md border
                                        {{ $val==='OK' ? 'bg-green-50 border-green-300' : ($val==='X' ? 'bg-red-50 border-red-300' : 'bg-white') }}"
                                    data-orden="{{ $a['Orden'] }}"
                                    data-actividad="{{ $a['Actividad'] }}"
                                    data-telar="{{ $t }}"
                                    data-salon="{{ $salonPorTelar[$t] ?? '' }}"
                                    data-valor="{{ $val ?? '' }}"
                                    @if($header->Status !== 'Creado') disabled @endif
                                >
                                   <span class="cell-icon text-lg">
                                        {!! $val==='OK' ? '✓' : ($val==='X' ? '✗' : '&nbsp;') !!}
                                   </span>
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="20" class="px-3 py-6 text-center text-slate-500">No hay actividades configuradas.</td></tr>
                @endforelse
            </tbody>
        </table>
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
        th.className = 'px-3 py-2 text-center telar-col';
        th.dataset.telar = telar;
        th.innerHTML = `${telar}<div class="text-[10px] text-slate-500">T: {{ $header->TurnoEntrega }}</div>`;
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
            btn.className = 'cell-btn inline-flex items-center justify-center w-8 h-8 rounded-md border bg-white';
            btn.dataset.orden = orden;
            btn.dataset.actividad = actTxt;
            btn.dataset.telar = telar;
            btn.dataset.salon = salon;
            btn.dataset.valor = '';
            btn.innerHTML = '<span class="cell-icon text-lg">&nbsp;</span>';

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
})();
</script>
@endsection
