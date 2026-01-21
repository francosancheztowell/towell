@extends('layouts.app')

@section('page-title', 'BPM Engomado')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="p-2 rounded-lg transition hover:bg-green-100" title="Crear Nuevo">
            <i class="fa-solid fa-plus text-green-600 text-lg"></i>
        </button>
        <button onclick="openChecklist()" id="btn-checklist" disabled class="p-2 rounded-lg transition hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Abrir Checklist">
            <i class="fa-solid fa-clipboard-list text-blue-600 text-lg"></i>
        </button>

    </div>
@endsection

@section('content')
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
            <tbody>
                @forelse($items as $item)
                    <tr class="border-b hover:bg-blue-50 cursor-pointer transition-colors" onclick="selectRow(this, '{{ $item->Folio }}', '{{ $item->Id }}')">
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $item->Folio }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold
                                @if($item->Status === 'Creado') bg-yellow-100 text-yellow-800
                                @elseif($item->Status === 'Terminado') bg-blue-100 text-blue-800
                                @elseif($item->Status === 'Autorizado') bg-green-100 text-green-800
                                @endif">
                                {{ $item->Status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Fecha ? $item->Fecha->format('d/m/Y H:i') : '' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplRec }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplRec }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">{{ $item->TurnoRecibe }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplEnt }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NombreEmplEnt }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">{{ $item->TurnoEntrega }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->CveEmplAutoriza }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NomEmplAutoriza }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500">No hay registros disponibles</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Crear -->
    <div id="createModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Crear Nuevo Folio BPM Engomado</h3>
                <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form action="{{ route('eng-bpm.store') }}" method="POST" class="p-4">
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

                <!-- Botones -->
                <div class="flex gap-2 justify-end pt-3 border-t">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Editar Folio BPM</h3>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="editForm" method="POST" class="p-4">
                @csrf
                @method('PUT')

                <input type="hidden" name="Folio" id="edit_folio">
                <input type="hidden" name="Status" id="edit_status">
                <input type="hidden" name="NombreEmplEnt" id="edit_nombre_ent">
                <input type="hidden" name="CveEmplEnt" id="edit_cve_ent">
                <input type="hidden" name="TurnoEntrega" id="edit_turno_ent">

                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="datetime-local" name="Fecha" id="edit_fecha" required
                           class="w-full px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-3">
                    <h4 class="text-sm font-semibold text-green-700 mb-2">Quien Recibe</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                            <select name="NombreEmplRec" id="edit_nombre_rec" required onchange="fillRecibeEdit(this)"
                                    class="w-full px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                            <input type="text" name="CveEmplRec" id="edit_cve_rec" readonly
                                   class="w-full px-2 py-1.5 text-sm border rounded-lg bg-gray-100">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Turno</label>
                            <input type="text" name="TurnoRecibe" id="edit_turno_rec" readonly
                                   class="w-full px-2 py-1.5 text-sm border rounded-lg bg-gray-100">
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 justify-end pt-3 border-t">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form para eliminar -->
    <form id="deleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <script>
        let selectedRow = null;
        let selectedFolio = null;
        let selectedId = null;

        function selectRow(row, folio, id) {
            if (selectedRow) {
                selectedRow.classList.remove('bg-blue-100');
            }

            selectedRow = row;
            selectedFolio = folio;
            selectedId = id;
            row.classList.add('bg-blue-100');

            enableButtons();
        }

        function enableButtons() {
            ['btn-checklist', 'btn-edit', 'btn-delete'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        }

        function openChecklist() {
            if (!selectedFolio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    text: 'Por favor selecciona un registro primero',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            window.location.href = `/eng-bpm-line/${selectedFolio}`;
        }

        function openEditModal() {
            if (!selectedRow) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const cells = selectedRow.cells;

            document.getElementById('edit_folio').value = cells[0].textContent;
            document.getElementById('edit_status').value = cells[1].textContent.trim();
            document.getElementById('edit_fecha').value = cells[2].textContent.trim();
            document.getElementById('edit_nombre_ent').value = cells[4].textContent;
            document.getElementById('edit_cve_ent').value = cells[3].textContent;
            document.getElementById('edit_turno_ent').value = cells[5].textContent;
            document.getElementById('edit_nombre_rec').value = cells[7].textContent;
            document.getElementById('edit_cve_rec').value = cells[6].textContent;
            document.getElementById('edit_turno_rec').value = cells[8].textContent;

            document.getElementById('editForm').action = `/eng-bpm/${selectedId}`;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function confirmDelete() {
            if (!selectedFolio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            Swal.fire({
                title: '¿Estás seguro?',
                html: `Se eliminará el folio <strong>${selectedFolio}</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteForm');
                    form.action = `/eng-bpm/${selectedId}`;
                    form.submit();
                }
            });
        }

        function fillRecibe(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');
            const turno = selectedOption.getAttribute('data-turno');

            document.getElementById('input_CveEmplRec').value = numero || '';
            document.getElementById('input_TurnoRecibe').value = turno || '';
        }

        function fillMaquina(select) {
            const selectedOption = select.options[select.selectedIndex];
            const departamento = selectedOption.getAttribute('data-departamento');

            document.getElementById('input_Departamento').value = departamento || '';
        }

        function fillEntrega(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');
            const turno = selectedOption.getAttribute('data-turno');

            document.getElementById('input_CveEmplEnt').value = numero || '';
            document.getElementById('input_TurnoEntrega').value = turno || '';
        }

        function fillRecibeEdit(select) {
            const option = select.options[select.selectedIndex];
            document.getElementById('edit_cve_rec').value = option.getAttribute('data-numero') || '';
            document.getElementById('edit_turno_rec').value = option.getAttribute('data-turno') || '';
        }

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>

@endsection
