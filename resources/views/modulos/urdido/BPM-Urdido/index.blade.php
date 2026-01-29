@extends('layouts.app')

@section('page-title', 'BPM Urdido')

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Botón de Filtros --}}
        <x-navbar.button-report
        id="btn-open-filters"
        title="Filtros"
        icon="fa-filter"
        text="Filtrar"
        module="BPM (Buenas Practicas Manufactura) Urd"
        iconColor="text-white"
        class="text-white"
        bg="bg-green-600" />
        <x-navbar.button-create
        module="BPM (Buenas Practicas Manufactura) Urd"
        onclick="openCreateModal()"
        title="Crear Registro"/>
        <x-navbar.button-edit
        onclick="openChecklist()"
        id="btn-checklist"
        module="BPM (Buenas Practicas Manufactura) Urd"
        title="Abrir Checklist"/>
    </div>
@endsection

@section( 'content')
    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif
    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '{{ session('error') }}',
                    confirmButtonColor: '#3b82f6'
                });
            });
        </script>
    @endif

    <div class="overflow-x-auto overflow-y-auto rounded-lg border bg-white shadow-sm mt-4 mx-4" style="max-height: 70vh;">
        <table id="bpmTable" class="min-w-full text-sm">
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
                </tr>
            </thead>
            <tbody id="tb-body">
                @forelse($items as $item)
                    @php
                        $statusClass = match($item->Status) {
                            'Creado' => 'bg-blue-100 text-blue-800',
                            'Terminado' => 'bg-yellow-100 text-yellow-800',
                            'Autorizado' => 'bg-green-100 text-green-800',
                            default => 'bg-gray-100 text-gray-800'
                        };
                    @endphp
                    <tr class="table-row hover:bg-blue-50 cursor-pointer transition-colors"
                        onclick="selectRow(this, {{ $item->Id }})"
                        data-id="{{ $item->Id }}"
                        data-folio="{{ $item->Folio }}"
                        data-status="{{ $item->Status }}"
                        data-fecha="{{ $item->Fecha ? $item->Fecha->format('Y-m-d') : '' }}"
                        data-cveemplrec="{{ $item->CveEmplRec }}"
                        data-nombreemplrec="{{ $item->NombreEmplRec }}"
                        data-turnorecibe="{{ $item->TurnoRecibe }}"
                        data-cveemplent="{{ $item->CveEmplEnt }}"
                        data-nombreemplent="{{ $item->NombreEmplEnt }}"
                        data-turnoentrega="{{ $item->TurnoEntrega }}"
                        data-cveemplautoriza="{{ $item->CveEmplAutoriza }}"
                        data-nombreemplautoriza="{{ $item->NombreEmplAutoriza }}"
                        {{-- aliases para el filtro (mismo naming que tejedores) --}}
                        data-nomrec="{{ $item->NombreEmplRec }}"
                        data-turnorec="{{ $item->TurnoRecibe }}"
                        data-turnoent="{{ $item->TurnoEntrega }}">
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $item->Folio }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                {{ $item->Status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Fecha ? $item->Fecha->format('d/m/Y') : '' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplRec }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplRec }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">{{ $item->TurnoRecibe }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplEnt }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplEnt }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">{{ $item->TurnoEntrega }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplAutoriza }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplAutoriza }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                            No hay registros disponibles
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal FILTROS --}}
    <div id="modal-filters" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-filter text-purple-600 mr-2"></i>Filtros
                </h2>
                <button type="button" data-close="#modal-filters"
                        class="text-slate-500 hover:text-slate-700 text-5xl leading-none">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                {{-- Mostrar Finalizados --}}
                <button type="button" id="btn-filter-finished"
                        class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-check-circle text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Finalizados</div>
                </button>

                {{-- Mis Folios --}}
                <button type="button" id="btn-filter-my-folios"
                        class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-blue-100 border-blue-400 text-blue-800">
                    <i class="fa-solid fa-user text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Mis Folios</div>
                </button>

                {{-- Mostrar Todos --}}
                <button type="button" id="btn-filter-all"
                        class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-list text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Todos</div>
                </button>

                {{-- Turno --}}
                <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                    <label class="block text-xs text-gray-600 mb-2 text-center">
                        <i class="fa-solid fa-clock mr-1"></i>Turno
                    </label>
                    <select id="filter-turno"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="">Todos</option>
                        <option value="1">Turno 1</option>
                        <option value="2">Turno 2</option>
                        <option value="3">Turno 3</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" id="btn-clear-filters"
                        class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white transition text-sm">
                    <i class="fa-solid fa-eraser mr-1"></i>Limpiar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Crear -->
    <div id="createModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 rounded-t-lg">
                <h3 class="text-lg font-semibold text-white">Crear Nuevo Registro</h3>
            </div>
            <form action="{{ route('urd-bpm.store') }}" method="POST" class="p-4">
                @csrf
                <!-- Status oculto, siempre será "Creado" -->
                <input type="hidden" name="Status" value="Creado">

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha *</label>
                        <input type="date" name="Fecha" value="{{ date('Y-m-d') }}" required readonly class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-blue-500 bg-gray-50">
                    </div>

                </div>

                    <!-- Sección: Quien Recibe (autollenado con usuario actual) -->
                    <div class="mb-3">
                        <h4 class="text-sm font-semibold text-green-700 mb-2">Quien Recibe</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" id="create_NombreEmplRec" name="NombreEmplRec" value="{{ auth()->user()->nombre ?? '' }}" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                                <input type="text" id="create_CveEmplRec" name="CveEmplRec" value="{{ auth()->user()->numero_empleado ?? '' }}" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                                <input type="text" id="create_TurnoRecibe" name="TurnoRecibe" value="{{ auth()->user()->turno ?? '' }}" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Quien Entrega (selección y autocompletado) -->
                    <div class="mb-3">
                        <h4 class="text-sm font-semibold text-blue-700 mb-2">Quien Entrega</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Nombre <span class="text-red-600">*</span></label>
                                <select id="select_NombreEmplEnt" name="NombreEmplEnt" onchange="fillEntrega(this)" required class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-blue-500 @error('NombreEmplEnt') border-red-500 @enderror">
                                    <option value="">Seleccione...</option>
                                    @foreach($usuarios as $usuario)
                                        <option value="{{ $usuario->nombre }}"
                                                data-numero="{{ $usuario->numero_empleado }}"
                                                data-turno="{{ $usuario->turno }}">
                                            {{ $usuario->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('NombreEmplEnt')
                                    <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                                <input type="text" id="input_CveEmplEnt" name="CveEmplEnt" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                                <input type="text" id="input_TurnoEntrega" name="TurnoEntrega" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Máquina <span class="text-red-600">*</span></label>
                            <select id="select_Maquina" name="MaquinaId" onchange="fillMaquina(this)" required class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-blue-500 @error('MaquinaId') border-red-500 @enderror">
                                <option value="">Seleccione...</option>
                                @foreach($maquinas as $maquina)
                                    <option value="{{ $maquina->MaquinaId }}"
                                            data-nombre="{{ $maquina->Nombre }}"
                                            data-departamento="{{ $maquina->Departamento }}">
                                        {{ $maquina->Nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('MaquinaId')
                                <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Departamento</label>
                            <input type="text" id="input_Departamento" name="Departamento" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeCreateModal()" class="px-3 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 w-full">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 w-full">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 px-4 py-3 rounded-t-lg">
                <h3 class="text-lg font-semibold text-white">Editar Registro</h3>
            </div>
            <form id="editForm" method="POST" class="p-4">
                @csrf
                @method('PUT')
                <!-- Status oculto -->
                <input type="hidden" id="edit_Status" name="Status">

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="col-span-2 bg-yellow-50 border-2 border-yellow-300 rounded-lg p-3">
                        <label class="block text-sm font-semibold text-yellow-800 mb-1">
                            <i class="fa-solid fa-hashtag mr-1"></i>
                            No. Folio
                        </label>
                        <div class="text-2xl font-bold text-yellow-600" id="edit_FolioDisplay"></div>
                        <input type="hidden" id="edit_Folio" name="Folio">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha *</label>
                        <input type="date" id="edit_Fecha" name="Fecha" required class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                </div>

                <!-- Sección: Quien Entrega -->
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-blue-700 mb-2">Quien Entrega</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="edit_NombreEmplEnt" name="NombreEmplEnt" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="edit_CveEmplEnt" name="CveEmplEnt" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" id="edit_TurnoEntrega" name="TurnoEntrega" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <!-- Sección: Quien Recibe -->
                <div class="mb-3">
                    <h4 class="text-sm font-semibold text-green-700 mb-2">Quien Recibe</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <select id="edit_select_NombreEmplRec" name="NombreEmplRec" onchange="fillEditRecibe(this)" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                                <option value="">Seleccione...</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->nombre }}"
                                            data-numero="{{ $usuario->numero_empleado }}"
                                            data-turno="{{ $usuario->turno }}">
                                        {{ $usuario->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="edit_input_CveEmplRec" name="CveEmplRec" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" id="edit_input_TurnoRecibe" name="TurnoRecibe" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeEditModal()" class="px-3 py-1.5 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 w-full">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm text-white bg-yellow-600 rounded hover:bg-yellow-700 w-full">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold text-white">Eliminar Registro</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-4">¿Está seguro de que desea eliminar este registro?</p>
                <p class="text-sm text-gray-500 mb-6">Esta acción no se puede deshacer.</p>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ======== FILTROS (igual a tejedores) ========
        (function () {
            const qs = (s) => document.querySelector(s);
            const qsa = (s) => [...document.querySelectorAll(s)];

            const open = (sel) => {
                const m = qs(sel);
                if (!m) return;
                m.classList.remove('hidden');
            };
            const close = (sel) => {
                const m = qs(sel);
                if (!m) return;
                m.classList.add('hidden');
            };

            qsa('[data-close]').forEach((b) =>
                b.addEventListener('click', () => close(b.dataset.close))
            );

            const btnOpenFilters = qs('#btn-open-filters');
            const filterBadge = qs('#filter-badge');
            const btnClearFilters = qs('#btn-clear-filters');
            const filterTurno = qs('#filter-turno');
            const btnFilterFinished = qs('#btn-filter-finished');
            const btnFilterMyFolios = qs('#btn-filter-my-folios');
            const btnFilterAll = qs('#btn-filter-all');

            let filterState = {
                showFinished: false,
                myFolios: true, // default
                showAll: false,
                turno: '',
            };

            const userName = @json(auth()->user()->nombre ?? '');
            const tbody = qs('#tb-body');

            function applyFilters() {
                const rows = qsa('.table-row');
                let visibleCount = 0;

                rows.forEach((row) => {
                    const status = row.dataset.status;
                    const nomRec = (row.dataset.nomrec || '').trim();
                    const turnoRec = row.dataset.turnorec || '';

                    let show = true;

                    if (!filterState.showFinished && !filterState.showAll && status === 'Terminado') {
                        show = false;
                    }

                    if (filterState.myFolios && userName) {
                        if (nomRec.toLowerCase() !== userName.toLowerCase()) {
                            show = false;
                        }
                    }

                    if (filterState.turno) {
                        if (turnoRec !== filterState.turno) {
                            show = false;
                        }
                    }

                    if (show) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                const hasActiveFilters =
                    filterState.showFinished ||
                    filterState.myFolios ||
                    filterState.showAll ||
                    filterState.turno;
                if (hasActiveFilters) filterBadge?.classList.remove('hidden');
                else filterBadge?.classList.add('hidden');

                let emptyRow = tbody?.querySelector('tr.no-results');
                if (visibleCount === 0) {
                    if (!emptyRow) {
                        const tr = document.createElement('tr');
                        tr.className = 'no-results';
                        let message = 'Sin resultados con los filtros aplicados';
                        if (filterState.myFolios && !filterState.showAll) {
                            message = 'No tienes folios asignados';
                        }
                        tr.innerHTML = `<td colspan="11" class="px-4 py-6 text-center text-slate-500">
                            <div class="flex flex-col items-center gap-2">
                                <i class="fa-solid fa-inbox text-4xl text-gray-300"></i>
                                <span class="text-base font-medium">${message}</span>
                            </div>
                        </td>`;
                        tbody?.appendChild(tr);
                    } else {
                        let message = 'Sin resultados con los filtros aplicados';
                        if (filterState.myFolios && !filterState.showAll) {
                            message = 'No tienes folios asignados';
                        }
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

            function updateFilterButtons() {
                btnFilterFinished?.classList.toggle('bg-amber-100', filterState.showFinished);
                btnFilterFinished?.classList.toggle('border-amber-400', filterState.showFinished);
                btnFilterFinished?.classList.toggle('text-amber-800', filterState.showFinished);
                btnFilterFinished?.classList.toggle('bg-gray-50', !filterState.showFinished);
                btnFilterFinished?.classList.toggle('border-gray-300', !filterState.showFinished);
                btnFilterFinished?.classList.toggle('text-gray-700', !filterState.showFinished);

                btnFilterMyFolios?.classList.toggle('bg-blue-100', filterState.myFolios);
                btnFilterMyFolios?.classList.toggle('border-blue-400', filterState.myFolios);
                btnFilterMyFolios?.classList.toggle('text-blue-800', filterState.myFolios);
                btnFilterMyFolios?.classList.toggle('bg-gray-50', !filterState.myFolios);
                btnFilterMyFolios?.classList.toggle('border-gray-300', !filterState.myFolios);
                btnFilterMyFolios?.classList.toggle('text-gray-700', !filterState.myFolios);

                btnFilterAll?.classList.toggle('bg-green-100', filterState.showAll);
                btnFilterAll?.classList.toggle('border-green-400', filterState.showAll);
                btnFilterAll?.classList.toggle('text-green-800', filterState.showAll);
                btnFilterAll?.classList.toggle('bg-gray-50', !filterState.showAll);
                btnFilterAll?.classList.toggle('border-gray-300', !filterState.showAll);
                btnFilterAll?.classList.toggle('text-gray-700', !filterState.showAll);
            }

            btnOpenFilters?.addEventListener('click', () => open('#modal-filters'));

            btnFilterFinished?.addEventListener('click', function () {
                filterState.showFinished = !filterState.showFinished;
                if (filterState.showFinished) filterState.showAll = false;
                updateFilterButtons();
                applyFilters();
            });

            btnFilterMyFolios?.addEventListener('click', function () {
                filterState.myFolios = !filterState.myFolios;
                if (filterState.myFolios) filterState.showAll = false;
                updateFilterButtons();
                applyFilters();
            });

            btnFilterAll?.addEventListener('click', function () {
                filterState.showAll = !filterState.showAll;
                if (filterState.showAll) {
                    filterState.showFinished = false;
                    filterState.myFolios = false;
                }
                updateFilterButtons();
                applyFilters();
            });

            filterTurno?.addEventListener('change', function () {
                filterState.turno = this.value || '';
                applyFilters();
            });

            btnClearFilters?.addEventListener('click', () => {
                filterState = {
                    showFinished: false,
                    myFolios: true,
                    showAll: false,
                    turno: '',
                };
                if (filterTurno) filterTurno.value = '';
                updateFilterButtons();
                applyFilters();
                close('#modal-filters');
            });

            applyFilters();
            updateFilterButtons();
        })();

        let selectedRowId = null;
        let selectedFolio = null;

        // Autocompletar campos de Quien Recibe
        function fillRecibe(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');
            const turno = selectedOption.getAttribute('data-turno');

            document.getElementById('input_CveEmplRec').value = numero || '';
            document.getElementById('input_TurnoRecibe').value = turno || '';
        }

        // Autocompletar departamento según máquina seleccionada
        function fillMaquina(select) {
            const selectedOption = select.options[select.selectedIndex];
            const departamento = selectedOption.getAttribute('data-departamento');

            document.getElementById('input_Departamento').value = departamento || '';
        }

        // Autocompletar campos de Quien Entrega (create)
        function fillEntrega(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');
            const turno = selectedOption.getAttribute('data-turno');

            document.getElementById('input_CveEmplEnt').value = numero || '';
            document.getElementById('input_TurnoEntrega').value = turno || '';
        }

        // Autocompletar campos de Quien Recibe en modal de editar
        function fillEditRecibe(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');
            const turno = selectedOption.getAttribute('data-turno');

            document.getElementById('edit_input_CveEmplRec').value = numero || '';
            document.getElementById('edit_input_TurnoRecibe').value = turno || '';
        }

        // Autocompletar campos de Quien Autoriza
        function fillAutoriza(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');

            document.getElementById('input_CveEmplAutoriza').value = numero || '';
        }

        // Select all checkbox
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Select row
        function selectRow(row, id) {
            // Deselect all rows
            document.querySelectorAll('#bpmTable tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });
            // Select current row
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');
            selectedRowId = id;
            selectedFolio = row.dataset.folio;

            // Habilitar botones
            enableButtons();
        }

        // Habilitar botones cuando hay una fila seleccionada
        function enableButtons() {
            // Habilitar botón de checklist
            const btnChecklist = document.getElementById('btn-checklist');
            if (btnChecklist) {
                btnChecklist.disabled = false;
                btnChecklist.classList.remove('opacity-50', 'cursor-not-allowed');
                btnChecklist.classList.add('hover:bg-green-700');
            }

            // Habilitar botón de editar
            const btnEdit = document.getElementById('btn-edit');
            if (btnEdit) {
                btnEdit.disabled = false;
                btnEdit.classList.remove('opacity-50', 'cursor-not-allowed');
                btnEdit.classList.add('hover:bg-yellow-100');
            }

            // Habilitar botón de eliminar
            const btnDelete = document.getElementById('btn-delete');
            if (btnDelete) {
                btnDelete.disabled = false;
                btnDelete.classList.remove('opacity-50', 'cursor-not-allowed');
                btnDelete.classList.add('hover:bg-red-100');
            }
        }

        // Deshabilitar botones
        function disableButtons() {
            const btnChecklist = document.getElementById('btn-checklist');
            if (btnChecklist) {
                btnChecklist.disabled = true;
                btnChecklist.classList.add('opacity-50', 'cursor-not-allowed');
                btnChecklist.classList.remove('hover:bg-green-700');
            }

            const btnEdit = document.getElementById('btn-edit');
            if (btnEdit) {
                btnEdit.disabled = true;
                btnEdit.classList.add('opacity-50', 'cursor-not-allowed');
                btnEdit.classList.remove('hover:bg-yellow-100');
            }

            const btnDelete = document.getElementById('btn-delete');
            if (btnDelete) {
                btnDelete.disabled = true;
                btnDelete.classList.add('opacity-50', 'cursor-not-allowed');
                btnDelete.classList.remove('hover:bg-red-100');
            }
        }

        // Modal functions
        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function openChecklist() {
            if (!selectedFolio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    text: 'Por favor seleccione un registro para abrir el checklist',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Redirigir a la vista de checklist
            window.location.href = `/urd-bpm-line/${selectedFolio}`;
        }

         function openEditModal() {
            if (!selectedRowId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    text: 'Por favor seleccione un registro para editar',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const row = document.querySelector(`tr[data-id="${selectedRowId}"]`);
            if (!row) return;

            // Fill form with data
            document.getElementById('edit_Folio').value = row.dataset.folio || '';
            document.getElementById('edit_FolioDisplay').textContent = row.dataset.folio || '';
            document.getElementById('edit_Status').value = row.dataset.status || '';
            document.getElementById('edit_Fecha').value = row.dataset.fecha || '';

            // Quien Recibe (editable con select)
            document.getElementById('edit_select_NombreEmplRec').value = row.dataset.nombreemplrec || '';
            document.getElementById('edit_input_CveEmplRec').value = row.dataset.cveemplrec || '';
            document.getElementById('edit_input_TurnoRecibe').value = row.dataset.turnorecibe || '';

            // Quien Entrega (solo lectura)
            document.getElementById('edit_NombreEmplEnt').value = row.dataset.nombreemplent || '';
            document.getElementById('edit_CveEmplEnt').value = row.dataset.cveemplent || '';
            document.getElementById('edit_TurnoEntrega').value = row.dataset.turnoentrega || '';

            // Set form action
            document.getElementById('editForm').action = `/urd-bpm/${selectedRowId}`;

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openDeleteModal() {
            if (!selectedRowId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    text: 'Por favor seleccione un registro para eliminar',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const row = document.querySelector(`tr[data-id="${selectedRowId}"]`);
            const folio = row?.dataset.folio || 'este registro';

            Swal.fire({
                title: '¿Está seguro?',
                html: `¿Desea eliminar el folio <strong>${folio}</strong>?<br><small class="text-gray-500">Esta acción no se puede deshacer.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Crear y enviar el formulario
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/urd-bpm/${selectedRowId}`;

                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = '{{ csrf_token() }}';

                    const methodField = document.createElement('input');
                    methodField.type = 'hidden';
                    methodField.name = '_method';
                    methodField.value = 'DELETE';

                    form.appendChild(csrfToken);
                    form.appendChild(methodField);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateModal();
                closeEditModal();
                closeDeleteModal();
            }
        });
    </script>

@endsection
