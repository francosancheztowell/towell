@extends('layouts.app')

@section('title', 'Tel · Actividades BPM')
@section('page-title')
Actividades BPM
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openModal('createModal')" title="Nueva Actividad" module="Actividades BPM" />
        <x-navbar.button-edit onclick="editSelected()" id="btn-edit" title="Editar Actividad" module="Actividades BPM" />
        <x-navbar.button-delete onclick="deleteSelected()" id="btn-delete" title="Eliminar Actividad" module="Actividades BPM" />
    </div>
@endsection

@section('content')
<div class="container ">
    @if($errors->any())
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: '<ul class="text-left list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif
    @if(session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: @json(session('success')),
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif

    <!-- Card -->
    <div class="bg-white  overflow-hidden">
        <!-- Table wrapper: sticky header + hover rows -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-3 py-2 text-left w-28 sticky top-0">Orden</th>
                        <th class="px-3 py-2 text-left sticky top-0">Actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $it)
                        <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50 transition-colors cursor-pointer row-select"
                            data-id="{{ $it->Orden }}"
                            data-actividad="{{ $it->Actividad }}"
                            onclick="selectRow(this)">
                            <td class="px-3 py-2 align-middle font-mono text-gray-700">{{ $it->Orden }}</td>
                            <td class="px-3 py-2 align-middle">{{ $it->Actividad }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-gray-500">Sin registros</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para Nueva Actividad -->
    <div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Nueva Actividad</h2>
                <form action="{{ route('tel-actividades-bpm.store') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Actividad</label>
                        <input type="text" name="Actividad" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Actividad -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Editar Actividad</h2>
                <form id="editForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Actividad</label>
                        <input type="text" id="editActividad" name="Actividad" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let selectedRow = null;
    let selectedId = null;

    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function selectRow(row) {
        // Remover selección anterior
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-500', 'text-white');
            // Restaurar colores originales
            const cells = selectedRow.querySelectorAll('td');
            cells.forEach(cell => {
                cell.classList.remove('text-white');
            });
        }

        // Seleccionar nueva fila
        selectedRow = row;
        selectedId = row.dataset.id;
        row.classList.add('bg-blue-500', 'text-white');
        // Aplicar texto blanco a todas las celdas
        const cells = row.querySelectorAll('td');
        cells.forEach(cell => {
            cell.classList.add('text-white');
        });

        // Habilitar botones
        document.getElementById('btn-edit').disabled = false;
        document.getElementById('btn-delete').disabled = false;
    }

    function editSelected() {
        if (!selectedRow || !selectedId) return;

        const actividad = selectedRow.dataset.actividad;
        document.getElementById('editActividad').value = actividad;
        document.getElementById('editForm').action = '{{ route("tel-actividades-bpm.update", ":id") }}'.replace(':id', selectedId);
        openModal('editModal');
    }

    function deleteSelected() {
        if (!selectedRow || !selectedId) return;

        Swal.fire({
            title: '¿Eliminar actividad?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
        }).then((result) => {
            if (result.isConfirmed) {
                // Crear formulario dinámico para eliminar
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("tel-actividades-bpm.destroy", ":id") }}'.replace(':id', selectedId);

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

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target.classList.contains('bg-gray-600')) {
            event.target.classList.add('hidden');
        }
    }
</script>
@endpush
