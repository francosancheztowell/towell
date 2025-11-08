@extends('layouts.app')

@section('title', 'Tel · Telares por Operador')
@section('page-title')
Telares por Operador
@endsection

@section('content')
<div class="container mx-auto px-3 md:px-6 py-4">
    @if($errors->any())
        <div class="rounded bg-red-100 text-red-800 px-3 py-2 mb-3">
            <ul class="mb-0 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
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

    <div class="flex items-center justify-end mb-3">
        <div class="flex items-center gap-2 md:gap-3">
            <button onclick="openModal('createModal')" class="px-3 py-2 rounded bg-green-600 text-white">
                <i class="fa-solid fa-plus mr-1.5"></i> Nuevo Operador
            </button>
            <button id="btn-top-edit" type="button"
                class="px-3 py-2 rounded bg-amber-600 text-white opacity-50 cursor-not-allowed"
                onclick="handleTopEdit()" disabled>
                <i class="fa-solid fa-pen-to-square mr-1.5"></i> Editar Operador
            </button>
            <button id="btn-top-delete" type="button"
                class="px-3 py-2 rounded bg-red-600 text-white opacity-50 cursor-not-allowed"
                onclick="handleTopDelete()" disabled>
                <i class="fa-solid fa-trash mr-1.5"></i> Eliminar Operador
            </button>
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-3 py-2 text-left">Número</th>
                    <th class="px-3 py-2 text-left">Nombre</th>
                    <th class="px-3 py-2 text-left">No. Telar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="odd:bg-white even:bg-gray-50 cursor-pointer transition-colors duration-150 hover:bg-blue-50"
                        data-key="{{ $it->getRouteKey() }}"
                        data-numero="{{ e($it->numero_empleado) }}"
                        data-nombre="{{ e($it->nombreEmpl) }}"
                        data-telar="{{ e($it->NoTelarId) }}"
                        onclick="selectRow(this)"
                        aria-selected="false">
                        <td class="px-3 py-2 align-middle">{{ $it->numero_empleado }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->nombreEmpl }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->NoTelarId }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-3 text-center text-gray-500">Sin registros</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>

    <!-- Formulario global oculto para eliminar -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <!-- Modal para Nuevo Operador -->
    <div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Nuevo Operador</h2>
                @if($errors->any())
                    <div class="mb-4 text-red-600">{{ $errors->first() }}</div>
                @endif
                <form action="{{ route('tel-telares-operador.store') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Número Empleado</label>
                        <input type="text" name="numero_empleado" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Nombre</label>
                        <input type="text" name="nombreEmpl" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">No. Telar</label>
                        <input type="text" name="NoTelarId" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Editar -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Editar Operador</h2>
                @if($errors->any())
                    <div class="mb-4 text-red-600">{{ $errors->first() }}</div>
                @endif
                <form id="editForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Número Empleado</label>
                        <input type="text" id="editNumero" name="numero_empleado" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Nombre</label>
                        <input type="text" id="editNombre" name="nombreEmpl" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">No. Telar</label>
                        <input type="text" id="editTelar" name="NoTelarId" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    </div>
    </div>
</div>

<style>
    /* Suaviza transiciones de listas */
    tbody tr { transition: background-color .15s ease, box-shadow .15s ease; }

    /* Hover más visible (fallback adicional al hover de clases) */
    tbody tr:hover { background-color: #eff6ff; }

    /* Estado seleccionado con borde lateral e indicador */
    tbody tr[aria-selected="true"] {
        background-color: #dbeafe; /* azul suave */
        box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.35);
    }
    tbody tr[aria-selected="true"] td:first-child {
        border-left: 4px solid #3b82f6; /* blue-600 */
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const updateUrl = '{{ route("tel-telares-operador.update", ["telTelaresOperador" => "PLACEHOLDER"]) }}';
    const destroyUrl = '{{ route("tel-telares-operador.destroy", ["telTelaresOperador" => "PLACEHOLDER"]) }}';

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
            // Toggle deselección
            clearSelection();
            return;
        }
        // Quitar selección previa
        if (selectedRow) {
            selectedRow.classList.remove('bg-blue-50', 'ring', 'ring-blue-300');
            selectedRow.setAttribute('aria-selected', 'false');
        }
        // Seleccionar actual
        selectedRow = row;
        selectedKey = row.dataset.key || null;
        row.classList.add('bg-blue-50', 'ring', 'ring-blue-300');
        row.setAttribute('aria-selected', 'true');
        updateTopButtonsState();
    }

    function handleTopEdit() {
        if (!selectedRow || !selectedKey) return;
        const numero = selectedRow.dataset.numero || '';
        const nombre = selectedRow.dataset.nombre || '';
        const telar = selectedRow.dataset.telar || '';
        openEditModal(selectedKey, numero, nombre, telar);
    }

    function handleTopDelete() {
        if (!selectedKey) return;
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Quieres eliminar este operador?',
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
        document.getElementById(modalId).classList.remove('hidden');
    }
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
    function openEditModal(key, numero, nombre, telar) {
        document.getElementById('editNumero').value = numero;
        document.getElementById('editNombre').value = nombre;
        document.getElementById('editTelar').value = telar;
        document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        openModal('editModal');
    }
    function openEditModalFromBtn(btn) {
        const key = btn.dataset.key;
        const numero = btn.dataset.numero || '';
        const nombre = btn.dataset.nombre || '';
        const telar = btn.dataset.telar || '';
        openEditModal(key, numero, nombre, telar);
    }
    function deleteOperator(key) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Quieres eliminar este operador?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm-' + key).submit();
            }
        });
    }
    // Cierra modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target.classList.contains('bg-gray-600')) {
            event.target.classList.add('hidden');
        }
    }

    // Estado inicial
    updateTopButtonsState();
</script>
@endsection
