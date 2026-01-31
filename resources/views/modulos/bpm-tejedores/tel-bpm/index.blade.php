@extends('layouts.app')

@section('page-title', 'BPM Tejedores')

@section('navbar-right')
<div class="flex items-center gap-3">
    {{-- Botones estilo BPM Engomado --}}
    <x-navbar.button-report
    id="btn-open-filters"
    title="Filtros"
    icon="fa-filter"
    text="Filtrar"
    bg="bg-green-600"
    module="BPM Tejedores"
    iconColor="text-white"
    class="text-white" />
    <x-navbar.button-edit
    id="btn-consult"
    title="Consultar folio"
    module="BPM Tejedores"/>
    <x-navbar.button-delete module="BPM Tejedores" id="btn-delete" title="Eliminar folio"/>
    <x-navbar.button-create module="BPM Tejedores" id="btn-open-create" title="Nuevo folio"/>
</div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto p-4 pb-8">
    {{-- Flash messages --}}
    {{-- Tabla --}}
    <div class="overflow-x-auto overflow-y-auto rounded-lg bg-white shadow-sm pb-3" style="max-height: 70vh;">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Folio</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Fecha</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">No Recibe</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Nombre Recibe</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Turno Recibe</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">No Entrega</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Nombre Entrega</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Turno Entrega</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Cve Autoriza</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Nombre Autoriza</th>
                    <th class="text-left px-4 py-3 font-semibold min-w-[200px]">Comentarios</th>
                </tr>
            </thead>
            <tbody id="tb-body">
                @forelse($items as $row)
                    @php
                        $rowBg = $loop->even ? 'bg-gray-50' : 'bg-white';
                        $statusClass = $row->Status === 'Autorizado'
                            ? 'bg-green-100 text-green-800'
                            : ($row->Status === 'Terminado' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800');
                    @endphp
                    <tr class="table-row hover:bg-blue-50 cursor-pointer {{ $rowBg }}"
                        data-folio="{{ $row->Folio }}"
                        data-status="{{ $row->Status }}"
                        data-cveent="{{ $row->CveEmplEnt }}"
                        data-noment="{{ $row->NombreEmplEnt }}"
                        data-turnoent="{{ $row->TurnoEntrega }}"
                        data-cverec="{{ $row->CveEmplRec }}"
                        data-nomrec="{{ $row->NombreEmplRec }}"
                        data-turnorec="{{ $row->TurnoRecibe }}"
                        style="{{ $row->Status === 'Autorizado' ? 'display: none;' : '' }}">
                        <td class="px-4 py-3 font-semibold {{ $rowBg }}">
                            <a class="text-blue-700 hover:underline" href="{{ route('tel-bpm-line.index', $row->Folio) }}">{{ $row->Folio }}</a>
                        </td>
                        <td class="px-4 py-3 {{ $rowBg }}">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $statusClass }}">
                                {{ $row->Status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 {{ $rowBg }}">{{ optional($row->Fecha)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono {{ $rowBg }}">{{ $row->CveEmplRec }}</td>
                        <td class="px-4 py-3 {{ $rowBg }}">{{ $row->NombreEmplRec }}</td>
                        <td class="px-4 py-3 {{ $rowBg }}">{{ $row->TurnoRecibe }}</td>
                        <td class="px-4 py-3 font-mono {{ $rowBg }}">{{ $row->CveEmplEnt }}</td>
                        <td class="px-4 py-3 {{ $rowBg }}">{{ $row->NombreEmplEnt }}</td>
                        <td class="px-4 py-3 {{ $rowBg }}">{{ $row->TurnoEntrega }}</td>
                        <td class="px-4 py-3 font-mono {{ $rowBg }}">{{ $row->CveEmplAutoriza }}</td>
                        <td class="px-4 py-3 {{ $rowBg }}">{{ $row->NomEmplAutoriza }}</td>
                        <td class="px-4 py-3 max-w-[200px] {{ $rowBg }}">
                            @if($row->Comentarios)
                                <div class="truncate text-gray-700" title="{{ $row->Comentarios }}">
                                    {{ $row->Comentarios }}
                                </div>
                            @else
                                <span class="text-gray-400 italic">Sin comentarios</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="no-results"><td colspan="12" class="px-4 py-6 text-center text-slate-500">Sin resultados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Sin paginación visual --}}
</div>

{{-- Modal CREAR --}}
<div id="modal-create" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-5">
    <div class="flex items-center justify-center mb-4 relative">
        <h2 class="text-lg font-semibold text-center">Nuevo Folio</h2>
        <button data-close="#modal-create" class="absolute right-0 text-slate-500 hover:text-slate-700">&times;</button>
    </div>

    <form id="form-create" method="POST" action="{{ route('tel-bpm.store') }}">
        @csrf
        <input type="hidden" name="_mode" value="create">

        <div class="space-y-6">
            <!-- Fecha y Hora -->
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Fecha y hora</label>
                    <input type="text" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                           value="{{ optional($fechaActual)->format('d/m/Y H:i') }}">
                </div>
            </div>

            <!-- Sección RECIBE -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                    <i class="fa-solid fa-arrow-down text-green-600 mr-2"></i>RECIBE
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nombre</label>
                        <input type="text" name="NombreEmplRec" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                               value="{{ $operadorUsuario->nombreEmpl ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">No. Operador</label>
                        <input type="text" name="CveEmplRec" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                               value="{{ $operadorUsuario->numero_empleado ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Turno</label>
                        <input type="text" name="TurnoRecibe" readonly class="w-full rounded-lg border px-3 py-2 bg-slate-50"
                               value="{{ $operadorUsuario->Turno ?? '' }}">
                    </div>
                </div>
            </div>

            <!-- Sección ENTREGA -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-200">
                    <i class="fa-solid fa-arrow-up text-blue-600 mr-2"></i>ENTREGA
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nombre <span class="text-red-600">*</span></label>
                        <select name="CveEmplEnt" id="sel-entrega" class="w-full rounded-lg border px-3 py-2 @error('CveEmplEnt') border-red-500 @enderror @error('NombreEmplEnt') border-red-500 @enderror" required>
                            <option value="">Seleccione…</option>
                            @php
                                $noRecibe = $operadorUsuario->numero_empleado ?? '';
                                $operadoresUnicos = ($operadoresEntrega ?? collect())->unique('numero_empleado')->filter(function($op) use ($noRecibe) {
                                    return $op->numero_empleado !== $noRecibe;
                                });
                            @endphp
                            @foreach($operadoresUnicos as $op)
                                <option value="{{ $op->numero_empleado }}" data-nombre="{{ $op->nombreEmpl }}" data-turno="{{ $op->Turno }}"
                                    {{ old('CveEmplEnt') == $op->numero_empleado ? 'selected' : '' }}>{{ $op->nombreEmpl }}</option>
                            @endforeach
                        </select>
                        @error('CveEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        @error('NombreEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        <input type="hidden" name="NombreEmplEnt" id="inp-nombre-ent" value="{{ old('NombreEmplEnt') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">No. Operador <span class="text-red-600">*</span></label>
                        <input type="text" name="CveEmplEntTxt" id="inp-cve-ent" maxlength="30" value="{{ old('CveEmplEnt') }}"
                               class="w-full rounded-lg border px-3 py-2 bg-slate-50" required readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Turno <span class="text-red-600">*</span></label>
                        <input type="text" name="TurnoEntrega" id="inp-turno-ent" maxlength="10" value="{{ old('TurnoEntrega') }}"
                               class="w-full rounded-lg border px-3 py-2 bg-slate-50 @error('TurnoEntrega') border-red-500 @enderror" required readonly>
                        @error('TurnoEntrega')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button type="submit" class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 w-full p-2">
                Crear folio
            </button>
            <button type="button" data-close="#modal-create" class="rounded-lg px-4 py-2 text-black border-black border w-full transition">
                Cancelar
            </button>
        </div>
    </form>
  </div>
</div>

{{-- Modal EDITAR --}}
<div id="modal-edit" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Editar folio</h2>
        <button data-close="#modal-edit" class="text-slate-500 hover:text-slate-700">&times;</button>
    </div>

    <form id="form-edit" method="POST" action="#">
        @csrf
        @method('PUT')
        <input type="hidden" name="pk" id="pk-edit" value="{{ old('pk') }}">
        <input type="hidden" name="_mode" value="edit">

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">No Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="CveEmplEnt" id="edit-cve" maxlength="30" value="{{ old('CveEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('CveEmplEnt') border-red-500 @enderror" required>
                @error('CveEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Nombre Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="NombreEmplEnt" id="edit-nombre" maxlength="150" value="{{ old('NombreEmplEnt') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('NombreEmplEnt') border-red-500 @enderror" required>
                @error('NombreEmplEnt')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno Entrega <span class="text-red-600">*</span></label>
                <input type="text" name="TurnoEntrega" id="edit-turno" maxlength="10" value="{{ old('TurnoEntrega') }}"
                       class="w-full rounded-lg border px-3 py-2 @error('TurnoEntrega') border-red-500 @enderror" required>
                @error('TurnoEntrega')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button class="rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Guardar cambios
            </button>
            <button type="button" data-close="#modal-edit" class="rounded-lg px-4 py-2 border hover:bg-slate-50">
                Cancelar
            </button>
        </div>
    </form>
  </div>
</div>

{{-- Modal FILTROS --}}
<div id="modal-filters" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800">
            <i class="fa-solid fa-filter text-purple-600 mr-2"></i>Filtros
        </h2>
        <button data-close="#modal-filters" class="text-slate-500 hover:text-slate-700 text-5xl leading-none">&times;</button>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-4">
        {{-- Mostrar Autorizados d--}}
        <button type="button" id="btn-filter-authorized"
                class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
            <i class="fa-solid fa-check-circle text-2xl mb-2 block"></i>
            <div class="font-semibold text-sm">Autorizados</div>
        </button>

        {{-- Mis Folios --}}
        <button type="button" id="btn-filter-my-folios"
                class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-blue-100 border-blue-400 text-blue-800">
            <i class="fa-solid fa-user text-2xl mb-2 block"></i>
            <div class="font-semibold text-sm">Mis Folios</div>
        </button>

        {{-- Mostrar Todos --}}
        <button type="button" id="btn-filter-all"
                class="filter-btn p-4 rounded-lg border-2 transition-all text-center {{ request('show_all') ? 'bg-green-100 border-green-400 text-green-800' : 'bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100' }}">
            <i class="fa-solid fa-list text-2xl mb-2 block"></i>
            <div class="font-semibold text-sm">Todos</div>
        </button>

        {{-- Turno --}}
        <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
            <label class="block text-xs text-gray-600 mb-2 text-center">
                <i class="fa-solid fa-clock mr-1"></i>Turno
            </label>
            <select id="filter-turno" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                <option value="">Todos</option>
                <option value="1" {{ request('turno') == '1' ? 'selected' : '' }}>Turno 1</option>
                <option value="2" {{ request('turno') == '2' ? 'selected' : '' }}>Turno 2</option>
                <option value="3" {{ request('turno') == '3' ? 'selected' : '' }}>Turno 3</option>
            </select>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" id="btn-clear-filters" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white transition text-sm">
            <i class="fa-solid fa-eraser mr-1"></i>Limpiar
        </button>
    </div>
  </div>
</div>

<style>
    tr.selected a,
    tr.selected span,
    tr.selected div {
        color: white !important;
    }
    tr.selected .bg-green-100,
    tr.selected .bg-amber-100,
    tr.selected .bg-slate-100 {
        background-color: rgba(255, 255, 255, 0.2) !important;
        color: white !important;
    }
    tr.selected,
    tr.selected td {
        background-color: rgb(59, 130, 246) !important; /* bg-blue-500 */
        color: white !important;
    }
    tr.selected:hover,
    tr.selected:hover td {
        background-color: rgb(37, 99, 235) !important; /* bg-blue-600 */
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const qs  = s => document.querySelector(s);
    const qsa = s => [...document.querySelectorAll(s)];
    const logDebugUrl = @json(route('tel-bpm.log-debug'));
    function logStep(step, msg) {
        try {
            const u = new URL(logDebugUrl);
            u.searchParams.set('step', step);
            if (msg) u.searchParams.set('msg', String(msg).slice(0, 200));
            fetch(u.toString(), { method: 'GET', keepalive: true }).catch(() => {});
        } catch (e) {}
    }

    // Abrir/Cerrar modales
    const open = sel => {
        const m = qs(sel);
        if (!m) { logStep('modal_not_found', sel); return; }
        m.classList.remove('hidden');
        m.classList.add('flex');
        logStep('modal_opened', sel);
    };
    const close = sel => { const m = qs(sel); if(!m) return; m.classList.add('hidden'); m.classList.remove('flex'); }
    qsa('[data-close]').forEach(b => b.addEventListener('click', () => close(b.dataset.close)));

    // Modal de Filtros - Filtrado sin recargar
    const btnOpenFilters = qs('#btn-open-filters');
    const filterBadge = qs('#filter-badge');
    const btnClearFilters = qs('#btn-clear-filters');
    const filterTurno = qs('#filter-turno');
    const btnFilterAuthorized = qs('#btn-filter-authorized');
    const btnFilterMyFolios = qs('#btn-filter-my-folios');
    const btnFilterAll = qs('#btn-filter-all');

    // Estado de filtros - Por defecto mostrar solo mis folios y ocultar Autorizados
    let filterState = {
        showAuthorized: false,  // Por defecto ocultar Autorizados
        myFolios: true,  // Por defecto activo
        showAll: false,
        turno: ''
    };

    // Obtener nombre del usuario actual
    const userName = @json(auth()->user()->nombre ?? '');

    // Obtener tbody una vez al inicio
    const tbody = qs('#tb-body');

    // Función para aplicar filtros
    function applyFilters() {
        const rows = qsa('.table-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const status = row.dataset.status;
            const nomRec = (row.dataset.nomrec || '').trim();
            const turnoRec = row.dataset.turnorec || '';

            let show = true;

            // Filtro por estado Autorizado (por defecto ocultar)
            if (!filterState.showAuthorized && !filterState.showAll && status === 'Autorizado') {
                show = false;
            }

            // Filtro Mis Folios - compara nombre con Nombre Recibe
            if (filterState.myFolios && userName) {
                if (nomRec.toLowerCase() !== userName.toLowerCase()) {
                    show = false;
                }
            }

            // Filtro por Turno - solo Turno Recibe
            if (filterState.turno) {
                if (turnoRec !== filterState.turno) {
                    show = false;
                }
            }

            // Mostrar/ocultar fila
            if (show) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Actualizar badge (siempre visible si hay algún filtro activo, incluyendo myFolios por defecto)
        const hasActiveFilters = filterState.showAuthorized || filterState.myFolios ||
                                 filterState.showAll || filterState.turno;
        if (hasActiveFilters) {
            filterBadge?.classList.remove('hidden');
        } else {
            filterBadge?.classList.add('hidden');
        }

        // Mostrar mensaje si no hay resultados
        let emptyRow = tbody?.querySelector('tr.no-results');
        if (visibleCount === 0) {
            if (!emptyRow) {
                const tr = document.createElement('tr');
                tr.className = 'no-results';
                const message = getEmptyMessage();
                tr.innerHTML = `<td colspan="12" class="px-4 py-6 text-center text-slate-500">
                    <div class="flex flex-col items-center gap-2">
                        <i class="fa-solid fa-inbox text-4xl text-gray-300"></i>
                        <span class="text-base font-medium">${message}</span>
                    </div>
                </td>`;
                tbody?.appendChild(tr);
            } else {
                // Actualizar mensaje si ya existe
                const message = getEmptyMessage();
                emptyRow.querySelector('td').innerHTML = `
                    <div class="flex flex-col items-center gap-2">
                        <i class="fa-solid fa-inbox text-4xl text-gray-300"></i>
                        <span class="text-base font-medium">${message}</span>
                    </div>
                `;
            }
        } else {
            emptyRow?.remove();
        }
    }

    function getEmptyMessage() {
        if (filterState.myFolios && !filterState.showAll) {
            return 'No tienes folios asignados';
        }
        return 'Sin resultados con los filtros aplicados';
    }

    // Actualizar estado visual de botones
    function updateFilterButtons() {
        toggleBtn(btnFilterAuthorized, filterState.showAuthorized, ['bg-green-100','border-green-400','text-green-800'], ['bg-gray-50','border-gray-300','text-gray-700']);
        toggleBtn(btnFilterMyFolios, filterState.myFolios, ['bg-blue-100','border-blue-400','text-blue-800'], ['bg-gray-50','border-gray-300','text-gray-700']);
        toggleBtn(btnFilterAll, filterState.showAll, ['bg-green-100','border-green-400','text-green-800'], ['bg-gray-50','border-gray-300','text-gray-700']);
    }

    function toggleBtn(btn, isOn, onClasses, offClasses) {
        if (!btn) return;
        onClasses.forEach(cls => btn.classList.toggle(cls, isOn));
        offClasses.forEach(cls => btn.classList.toggle(cls, !isOn));
    }

    btnOpenFilters?.addEventListener('click', () => open('#modal-filters'));

    // Botón Autorizados
    btnFilterAuthorized?.addEventListener('click', function() {
        filterState.showAuthorized = !filterState.showAuthorized;
        if (filterState.showAuthorized) {
            filterState.showAll = false;
        }
        updateFilterButtons();
        applyFilters();
    });

    // Botón Mis Folios
    btnFilterMyFolios?.addEventListener('click', function() {
        filterState.myFolios = !filterState.myFolios;
        if (filterState.myFolios) {
            filterState.showAll = false;
        }
        updateFilterButtons();
        applyFilters();
    });

    // Botón Todos
    btnFilterAll?.addEventListener('click', function() {
        filterState.showAll = !filterState.showAll;
        if (filterState.showAll) {
            filterState.showAuthorized = false;
            filterState.myFolios = false;
        }
        updateFilterButtons();
        applyFilters();
    });

    // Select Turno
    filterTurno?.addEventListener('change', function() {
        filterState.turno = this.value || '';
        applyFilters();
    });

    btnClearFilters?.addEventListener('click', () => {
        filterState = {
            showAuthorized: false,  // Por defecto ocultar Autorizados
            myFolios: true,  // Volver al estado por defecto
            showAll: false,
            turno: ''
        };
        filterTurno.value = '';
        updateFilterButtons();
        applyFilters();
        close('#modal-filters');
    });

    // Inicializar filtros
    applyFilters();
    updateFilterButtons();

    // Crear: validar que el usuario exista como operador y tenga permisos
    const usuarioEsOperador = @json($usuarioEsOperador ?? false);
    const noRecibeInput = document.querySelector('#form-create input[name="CveEmplRec"]');
    qs('#btn-open-create')?.addEventListener('click', ()=> {
        if (!usuarioEsOperador) {
            Swal.fire({
                icon: 'error',
                title: 'No eres operador registrado',
                text: 'No es posible crear el folio porque tu usuario no existe en la tabla de operadores.',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        open('#modal-create');
    });

    const formCreate = qs('#form-create');

    // Seleccionar fila y accionar desde barra superior
    let selected = null;
    const btnConsult = qs('#btn-consult');
    const btnEditTop = qs('#btn-edit');
    const btnDeleteTop = qs('#btn-delete');
    const consultUrlTpl = @json(route('tel-bpm-line.index', ':FOLIO'));
    const updateUrlTpl = @json(route('tel-bpm.update', ':FOLIO'));
    const deleteUrlTpl = @json(route('tel-bpm.destroy', ':FOLIO'));

    function setDisabled(btn, val) {
        if (!btn) return;
        btn.disabled = !!val;
        btn.classList.toggle('opacity-50', !!val);
        btn.classList.toggle('cursor-not-allowed', !!val);
    }

    function updateActions() {
        const hasSel = !!selected;
        setDisabled(btnConsult, !hasSel);
        const editable = hasSel && selected.dataset.status === 'Creado';
        setDisabled(btnEditTop, !editable);
        setDisabled(btnDeleteTop, !editable);
    }

    function clearSelection() {
        (tbody?.querySelectorAll('tr.selected') || []).forEach(tr => tr.classList.remove('selected'));
    }

    tbody?.addEventListener('click', (e)=>{
        const tr = e.target.closest('tr');
        if (!tr) return;
        clearSelection();
        tr.classList.add('selected');
        selected = tr;
        updateActions();
    });

    btnConsult?.addEventListener('click', ()=>{
        if (!selected) return Swal.fire('Selecciona un folio','Debes seleccionar un folio para consultar','info');
        const folio = selected.dataset.folio;
        window.location.href = consultUrlTpl.replace(':FOLIO', folio);
    });

    // Autocompletar entrega según selección y validar distinto a recibe
    const selEntrega   = qs('#sel-entrega');
    const inpNomEnt    = qs('#inp-nombre-ent');
    const inpCveEnt    = qs('#inp-cve-ent');
    const inpTurnoEnt  = qs('#inp-turno-ent');
    selEntrega?.addEventListener('change', ()=>{
        const opt = selEntrega.options[selEntrega.selectedIndex];
        const nom = opt?.dataset?.nombre || '';
        const tur = opt?.dataset?.turno || '';
        // Validar que no sea mismo operador que Recibe
        const noRecibe = noRecibeInput?.value || '';
        if (selEntrega.value && noRecibe && selEntrega.value === noRecibe) {
            Swal.fire({
                icon: 'warning',
                title: 'Operador duplicado',
                text: 'Entrega y Recibe no pueden ser el mismo operador.'
            });
            selEntrega.value = '';
            inpNomEnt.value = '';
            inpCveEnt.value = '';
            inpTurnoEnt.value = '';
            return;
        }
        inpNomEnt.value = nom;
        inpCveEnt.value = selEntrega.value || '';
        inpTurnoEnt.value = tur;
    });

    // Si hay un valor previo (old) en el select, disparemos el cambio para autocompletar
    if (selEntrega && selEntrega.value) {
        const event = new Event('change');
        selEntrega.dispatchEvent(event);
    }

    // Editar (desde barra superior)
    const formEdit  = qs('#form-edit');
    const pkEdit    = qs('#pk-edit');
    const editCve   = qs('#edit-cve');
    const editNom   = qs('#edit-nombre');
    const editTurno = qs('#edit-turno');
    btnEditTop?.addEventListener('click', ()=>{
        if (!selected) return Swal.fire('Selecciona un folio','Debes seleccionar un folio para editar','info');
        if (selected.dataset.status !== 'Creado') return Swal.fire('No editable','Sólo se puede editar en estado Creado','warning');
        const folio  = selected.dataset.folio;
        pkEdit.value = folio;
        editCve.value   = selected.dataset.cveent || '';
        editNom.value   = selected.dataset.noment || '';
        editTurno.value = selected.dataset.turnoent || '';
        formEdit.action = updateUrlTpl.replace(':FOLIO', folio);
        open('#modal-edit');
    });

    // Eliminar (SweetAlert modal)
    const deleteForm = document.createElement('form');
    deleteForm.method = 'POST';
    deleteForm.className = 'hidden';
    deleteForm.innerHTML = `@csrf @method('DELETE')`;
    document.body.appendChild(deleteForm);

    btnDeleteTop?.addEventListener('click', ()=>{
        if (!selected) return Swal.fire('Selecciona un folio','Debes seleccionar un folio para eliminar','info');
        const status = selected.dataset.status;
        if (status !== 'Creado') {
            return Swal.fire({
                icon: 'error',
                title: 'Eliminación no permitida',
                text: `No se puede eliminar un folio en estado "${status}". Solo se pueden eliminar folios en estado "Creado".`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6'
            });
        }
        const folio = selected.dataset.folio;
        Swal.fire({
            title: '¿Eliminar?',
            text: `Se eliminará el folio ${folio}.`,
            icon: 'warning', showCancelButton:true,
            confirmButtonText: 'Sí, eliminar', cancelButtonText:'Cancelar'
        }).then(r => {
            if (r.isConfirmed) {
                deleteForm.action = deleteUrlTpl.replace(':FOLIO', folio);
                deleteForm.submit();
            }
        });
    });

    // Reabrir modal si hubo errores de validación
    @if($errors->any())
        @if(old('_mode') === 'edit')
            open('#modal-edit');
        @else
            open('#modal-create');
        @endif
    @endif

    // Inicializa estado de acciones según selección
    updateActions();
})();
</script>
@if(session('error'))
<script>
  (function(){
    Swal.fire({
      icon: 'warning',
      title: 'Aviso',
      text: @json(session('error')),
      confirmButtonText: 'Entendido'
    });
  })();
</script>
@endif

@if($errors->any())
<script>
  (function(){
    Swal.fire({
      icon: 'error',
      title: 'No se pudo crear el folio',
      html: @json(implode('<br>', $errors->all())),
      confirmButtonText: 'Entendido'
    }).then(function(){ document.getElementById('btn-open-create') && document.getElementById('btn-open-create').click(); });
  })();
</script>
@endif

@if(session('success'))
<script>
  (function(){
    const message = @json(session('success'));
    Swal.fire({
      icon: 'success',
      title: 'Listo',
      text: message,
      toast: true,
      position: 'top-end',
      timer: 2500,
      showConfirmButton: false
    });
    // Auto-refrescar solo cuando se crea, termina, autoriza o rechaza (no al eliminar)
    if (typeof message === 'string' && (message.includes('creado') || message.includes('Terminado') || message.includes('Autorizado') || message.includes('Creado'))) {
      setTimeout(function() { window.location.reload(); }, 1800);
    }
  })();
</script>
@endif
@endsection
