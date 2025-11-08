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

    <div class="flex items-center justify-between mb-3">
        <button onclick="openModal('createModal')" class="px-3 py-2 rounded bg-green-600 text-white">Nuevo Operador</button>
    </div>

    <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-3 py-2 text-left">Número</th>
                    <th class="px-3 py-2 text-left">Nombre</th>
                    <th class="px-3 py-2 text-left">No. Telar</th>
                    <th class="px-3 py-2 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="odd:bg-white even:bg-gray-50">
                        <td class="px-3 py-2">{{ $it->numero_empleado }}</td>
                        <td class="px-3 py-2">{{ $it->nombreEmpl }}</td>
                        <td class="px-3 py-2">{{ $it->NoTelarId }}</td>
                        <td class="px-3 py-2 text-right">
                            <button
                                class="px-2 py-1 rounded bg-amber-500 text-white"
                                data-key="{{ $it->getRouteKey() }}"
                                data-numero="{{ $it->numero_empleado }}"
                                data-nombre="{{ $it->nombreEmpl }}"
                                data-telar="{{ $it->NoTelarId }}"
                                onclick="openEditModalFromBtn(this)">
                                Editar
                            </button>
                            <form id="deleteForm-{{ $it->getRouteKey() }}" action="{{ route('tel-telares-operador.destroy', $it) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="deleteOperator('{{ $it->getRouteKey() }}')" class="px-2 py-1 rounded bg-red-600 text-white">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-3 text-center text-gray-500">Sin registros</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>

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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const updateUrl = '{{ route("tel-telares-operador.update", ["telTelaresOperador" => "PLACEHOLDER"]) }}';
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
</script>
@endsection
