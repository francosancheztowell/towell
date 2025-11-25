@extends('layouts.app')

@section('page-title', 'Captura de Fórmulas - Engomado')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="p-2 rounded-lg transition hover:bg-green-100" title="Crear Nueva Fórmula">
            <i class="fa-solid fa-plus text-green-600 text-lg"></i>
        </button>
        <button onclick="openEditModal()" id="btn-edit" disabled class="p-2 rounded-lg transition hover:bg-yellow-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Editar">
            <i class="fa-solid fa-edit text-yellow-600 text-lg"></i>
        </button>
        <button onclick="confirmDelete()" id="btn-delete" disabled class="p-2 rounded-lg transition hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Eliminar">
            <i class="fa-solid fa-trash text-red-600 text-lg"></i>
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
        <table id="formulaTable" class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Folio</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Hora</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Máquina</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Empleado</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Olla</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Fórmula</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Kilos</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Litros</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Calibre</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tipo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr class="border-b hover:bg-purple-50 cursor-pointer transition-colors" onclick="selectRow(this, '{{ $item->Folio }}')">
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $item->Folio }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold
                                @if($item->Status === 'Creado') bg-yellow-100 text-yellow-800
                                @elseif($item->Status === 'En Proceso') bg-blue-100 text-blue-800
                                @elseif($item->Status === 'Terminado') bg-green-100 text-green-800
                                @endif">
                                {{ $item->Status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Hora }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->MaquinaId }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NomEmpl }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Olla }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Formula }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Kilos ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Litros ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Calibre ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Tipo }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500">No hay fórmulas disponibles</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Crear -->
    <div id="createModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Nueva Formulación de Engomado</h3>
                <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form action="{{ route('eng-formulacion.store') }}" method="POST" class="p-4">
                @csrf
                
                <div class="grid grid-cols-4 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Hora</label>
                        <input type="time" name="Hora" value="{{ date('H:i') }}" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Máquina <span class="text-red-600">*</span></label>
                        <select name="MaquinaId" required class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                            <option value="">Seleccione...</option>
                            @foreach($maquinas as $maquina)
                                <option value="{{ $maquina->MaquinaId }}">{{ $maquina->Nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta</label>
                        <input type="text" name="Cuenta" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Calibre</label>
                        <input type="number" step="0.01" name="Calibre" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
                        <input type="text" name="Tipo" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Empleado <span class="text-red-600">*</span></label>
                        <select name="NomEmpl" id="create_empleado" onchange="fillEmpleado(this)" required class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                            <option value="">Seleccione...</option>
                            @foreach($usuarios as $usuario)
                                <option value="{{ $usuario->nombre }}" data-numero="{{ $usuario->numero_empleado }}">
                                    {{ $usuario->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Clave Empleado</label>
                        <input type="text" name="CveEmpl" id="create_cve_empl" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
                        <input type="text" name="Olla" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fórmula</label>
                        <input type="text" name="Formula" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Producto ID</label>
                        <input type="text" name="ProdId" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div class="grid grid-cols-5 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Kilos</label>
                        <input type="number" step="0.01" name="Kilos" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Litros</label>
                        <input type="number" step="0.01" name="Litros" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tiempo Cocinado</label>
                        <input type="number" step="0.01" name="TiempoCocinado" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Sólidos</label>
                        <input type="number" step="0.01" name="Solidos" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Viscosidad</label>
                        <input type="number" step="0.01" name="Viscocidad" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex gap-2 justify-end pt-3 border-t">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fa-solid fa-save mr-1"></i>
                        Crear Formulación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold">Editar Formulación</h3>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="editForm" method="POST" class="p-4">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-4 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Hora</label>
                        <input type="time" name="Hora" id="edit_hora" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Máquina</label>
                        <select name="MaquinaId" id="edit_maquina" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                            <option value="">Seleccione...</option>
                            @foreach($maquinas as $maquina)
                                <option value="{{ $maquina->MaquinaId }}">{{ $maquina->Nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta</label>
                        <input type="text" name="Cuenta" id="edit_cuenta" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Calibre</label>
                        <input type="number" step="0.01" name="Calibre" id="edit_calibre" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
                        <input type="text" name="Tipo" id="edit_tipo" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Empleado</label>
                        <select name="NomEmpl" id="edit_empleado" onchange="fillEmpleadoEdit(this)" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                            <option value="">Seleccione...</option>
                            @foreach($usuarios as $usuario)
                                <option value="{{ $usuario->nombre }}" data-numero="{{ $usuario->numero_empleado }}">
                                    {{ $usuario->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Clave Empleado</label>
                        <input type="text" name="CveEmpl" id="edit_cve_empl" readonly class="w-full px-2 py-1.5 text-sm border rounded bg-gray-50">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
                        <input type="text" name="Olla" id="edit_olla" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fórmula</label>
                        <input type="text" name="Formula" id="edit_formula" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Producto ID</label>
                        <input type="text" name="ProdId" id="edit_prod_id" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                </div>

                <div class="grid grid-cols-5 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Kilos</label>
                        <input type="number" step="0.01" name="Kilos" id="edit_kilos" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Litros</label>
                        <input type="number" step="0.01" name="Litros" id="edit_litros" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tiempo Cocinado</label>
                        <input type="number" step="0.01" name="TiempoCocinado" id="edit_tiempo" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Sólidos</label>
                        <input type="number" step="0.01" name="Solidos" id="edit_solidos" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Viscosidad</label>
                        <input type="number" step="0.01" name="Viscocidad" id="edit_viscocidad" class="w-full px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-yellow-500">
                    </div>
                </div>

                <div class="flex gap-2 justify-end pt-3 border-t">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                        <i class="fa-solid fa-check mr-1"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedRow = null;
        let selectedFolio = null;

        function selectRow(row, folio) {
            if (selectedRow) {
                selectedRow.classList.remove('bg-purple-100');
            }
            
            selectedRow = row;
            selectedFolio = folio;
            row.classList.add('bg-purple-100');
            
            enableButtons();
        }

        function enableButtons() {
            ['btn-edit', 'btn-delete'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        }

        function openEditModal() {
            if (!selectedRow) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }

            const cells = selectedRow.cells;
            
            // Folio: cells[0], Status: cells[1], Hora: cells[2], Máquina: cells[3], 
            // Empleado: cells[4], Olla: cells[5], Fórmula: cells[6], Kilos: cells[7],
            // Litros: cells[8], Calibre: cells[9], Tipo: cells[10]
            
            document.getElementById('edit_hora').value = cells[2].textContent.trim();
            document.getElementById('edit_maquina').value = cells[3].textContent.trim();
            document.getElementById('edit_empleado').value = cells[4].textContent.trim();
            document.getElementById('edit_olla').value = cells[5].textContent.trim();
            document.getElementById('edit_formula').value = cells[6].textContent.trim();
            document.getElementById('edit_kilos').value = cells[7].textContent.trim().replace(/,/g, '');
            document.getElementById('edit_litros').value = cells[8].textContent.trim().replace(/,/g, '');
            document.getElementById('edit_calibre').value = cells[9].textContent.trim().replace(/,/g, '');
            document.getElementById('edit_tipo').value = cells[10].textContent.trim();
            
            document.getElementById('editForm').action = `/eng-formulacion/${selectedFolio}`;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function confirmDelete() {
            if (!selectedFolio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }

            Swal.fire({
                title: '¿Estás seguro?',
                html: `Se eliminará la formulación con folio <strong>${selectedFolio}</strong> y todas sus líneas asociadas`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteForm');
                    form.action = `/eng-formulacion/${selectedFolio}`;
                    form.submit();
                }
            });
        }

        function fillEmpleado(select) {
            const selectedOption = select.options[select.selectedIndex];
            const numero = selectedOption.getAttribute('data-numero');
            document.getElementById('create_cve_empl').value = numero || '';
        }

        function fillEmpleadoEdit(select) {
            const option = select.options[select.selectedIndex];
            document.getElementById('edit_cve_empl').value = option.getAttribute('data-numero') || '';
        }

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>

@endsection
