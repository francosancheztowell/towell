@extends('layouts.app')

@section('title', 'Cat · Desarrolladores')
@section('page-title')
Catálogo de Desarrolladores
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create id="btn-create"  title="Nuevo Desarrollador" module="Catalogo Desarrolladores" />
        <x-navbar.button-edit id="btn-top-edit"  title="Editar Desarrollador" module="Catalogo Desarrolladores" />
        <x-navbar.button-delete id="btn-top-delete"  title="Eliminar Desarrollador" module="Catalogo Desarrolladores" />
    </div>
@endsection

@section('content')
<div class="container">
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
                text: '{{ session('success') }}',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif

    <div class="bg-white rounded shadow">
        <div class="overflow-x-auto">
            <div class="overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left">Clave Empleado</th>
                            <th class="px-3 py-2 text-left">Nombre</th>
                            <th class="px-3 py-2 text-left">Turno</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $it)
                            <tr class="odd:bg-white even:bg-gray-50 cursor-pointer transition-colors duration-150 hover:bg-blue-50 row-selectable"
                                data-key="{{ $it->id }}"
                                data-clave="{{ e($it->clave_empleado) }}"
                                data-nombre="{{ e($it->nombre) }}"
                                data-turno="{{ e($it->Turno) }}"
                                aria-selected="false">
                                <td class="px-3 py-2 align-middle">{{ $it->clave_empleado }}</td>
                                <td class="px-3 py-2 align-middle">{{ $it->nombre }}</td>
                                <td class="px-3 py-2 align-middle">{{ $it->Turno }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-3 text-center text-gray-500">Sin registros</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Formulario global oculto para eliminar -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <!-- Modal para Nuevo Desarrollador -->
    <div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Nuevo Desarrollador</h2>
                <form id="createForm" action="{{ route('cat-desarrolladores.store') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-sm font-medium">Clave Empleado</label>
                            <input type="text" name="clave_empleado" class="w-full px-3 py-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Nombre</label>
                            <input type="text" name="nombre" class="w-full px-3 py-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Turno</label>
                            <select name="Turno" class="w-full px-3 py-2 border rounded" required>
                                <option value="" disabled selected>Selecciona turno</option>
                                <option value="1">Turno 1</option>
                                <option value="2">Turno 2</option>
                                <option value="3">Turno 3</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="button" data-close-modal="createModal" class="w-full px-4 py-2  bg-gray-500 text-white rounded mr-2">Cancelar</button>
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Editar -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Editar Desarrollador</h2>
                <form id="editForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-sm font-medium">Clave Empleado</label>
                            <input type="text" id="editClave" name="clave_empleado" class="w-full px-3 py-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Nombre</label>
                            <input type="text" id="editNombre" name="nombre" class="w-full px-3 py-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Turno</label>
                            <select id="editTurno" name="Turno" class="w-full px-3 py-2 border rounded" required>
                                <option value="1">Turno 1</option>
                                <option value="2">Turno 2</option>
                                <option value="3">Turno 3</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="button" data-close-modal="editModal" class="px-4 py-2 bg-gray-500 w-full text-white rounded mr-2">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-yellow-600 w-full text-white rounded">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<style>
    tbody tr { transition: background-color .15s ease, box-shadow .15s ease; }
    tbody tr:hover { background-color: #eff6ff; }
    tbody tr[aria-selected="true"] {
        background-color: #dbeafe;
        box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.35);
    }
    tbody tr[aria-selected="true"] td:first-child {
        border-left: 4px solid #3b82f6;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const updateUrl = '{{ route("cat-desarrolladores.update", ["cat_desarrolladore" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("cat-desarrolladores.destroy", ["cat_desarrolladore" => "PLACEHOLDER"]) }}';

    let selectedRow = null;
    let selectedKey = null;

    function updateTopButtonsState() {
        const btnEdit = document.getElementById('btn-top-edit');
        const btnDelete = document.getElementById('btn-top-delete');
        const hasSelection = !!selectedKey;
        [btnEdit, btnDelete].forEach(btn => {
            if (!btn) return;
            if (hasSelection) {
                btn.removeAttribute('disabled');
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.setAttribute('disabled', 'disabled');
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    function clearSelection() {
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-50', 'ring', 'ring-blue-300');
            selectedRow.setAttribute('aria-selected', 'false');
        }
        selectedRow = null;
        selectedKey = null;
        updateTopButtonsState();
    }

    function selectRow(row) {
        if (selectedRow === row) {
            clearSelection();
            return;
        }
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-50', 'ring', 'ring-blue-300');
            selectedRow.setAttribute('aria-selected', 'false');
        }
        selectedRow = row;
        selectedKey = row.dataset.key || null;
        row.classList.add('bg-blue-50', 'ring', 'ring-blue-300');
        row.setAttribute('aria-selected', 'true');
        updateTopButtonsState();
    }

    function handleTopEdit() {
        if (!selectedRow || !selectedKey) return;
        const clave = selectedRow.dataset.clave || '';
        const nombre = selectedRow.dataset.nombre || '';
        const turno = selectedRow.dataset.turno || '';
        openEditModal(selectedKey, clave, nombre, turno);
    }

    function handleTopDelete() {
        if (!selectedKey) return;
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Quieres eliminar este desarrollador?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('globalDeleteForm');
                form.action = destroyUrl.replace('PLACEHOLDER', encodeURIComponent(selectedKey));
                form.submit();
            }
        });
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        if (modalId === 'createModal') {
            const form = modal.querySelector('form');
            if (form) form.reset();
        }
        modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function openEditModal(key, clave, nombre, turno) {
        document.getElementById('editClave').value = clave;
        document.getElementById('editNombre').value = nombre;
        document.getElementById('editTurno').value = turno;
        document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        openModal('editModal');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('bg-gray-600')) {
            event.target.classList.add('hidden');
        }
    }

    updateTopButtonsState();

    document.getElementById('btn-create')?.addEventListener('click', function() {
        openModal('createModal');
    });

    document.getElementById('btn-top-edit')?.addEventListener('click', handleTopEdit);
    document.getElementById('btn-top-delete')?.addEventListener('click', handleTopDelete);

    document.querySelectorAll('.row-selectable').forEach(row => {
        row.addEventListener('click', function() {
            selectRow(this);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-close-modal');
            closeModal(modalId);
        });
    });
});
</script>
@endsection
