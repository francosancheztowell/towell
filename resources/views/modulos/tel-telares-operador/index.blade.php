@extends('layouts.app')

@section('title', 'Telares por Operador')
@section('page-title')
Telares por Operador
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-report id="btn-open-filters" title="Filtros" icon="fa-filter" text="Filtrar" bg="bg-green-600" module="Telares x Operador" iconColor="text-white" class="text-white" />
        <x-navbar.button-create id="btn-create" title="Nuevo Operador" module="Telares x Operador"/>
        <x-navbar.button-edit id="btn-top-edit" title="Editar Operador" module="Telares x Operador" />
        <x-navbar.button-delete id="btn-top-delete" title="Eliminar Operador" module="Telares x Operador" />
    </div>
@endsection

@section('content')
<div class="w-full px-4 py-6">
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

    @php
        $salonesUnique = $items->pluck('SalonTejidoId')->unique()->filter()->sort()->values();
    @endphp

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div id="table-scroll-wrapper" class="overflow-auto max-h-[75vh]">
            <table class="w-full text-base border-collapse">
                <thead class="bg-blue-500 text-white shadow-[0_2px_4px_rgba(0,0,0,0.08)]">
                    <tr>
                        <th class="sticky top-0 z-20 bg-blue-500 px-6 py-4 text-left font-semibold text-base whitespace-nowrap border-b-2 border-blue-600">Número</th>
                        <th class="sticky top-0 z-20 bg-blue-500 px-6 py-4 text-left font-semibold text-base whitespace-nowrap border-b-2 border-blue-600">Nombre</th>
                        <th class="sticky top-0 z-20 bg-blue-500 px-6 py-4 text-left font-semibold text-base whitespace-nowrap border-b-2 border-blue-600">Telar</th>
                        <th class="sticky top-0 z-20 bg-blue-500 px-6 py-4 text-left font-semibold text-base whitespace-nowrap border-b-2 border-blue-600">Turno</th>
                        <th class="sticky top-0 z-20 bg-blue-500 px-6 py-4 text-left font-semibold text-base whitespace-nowrap border-b-2 border-blue-600">Salón</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $it)
                    <tr class="odd:bg-gray-100 even:bg-white cursor-pointer transition-colors duration-150 hover:bg-blue-50 row-selectable"
                        data-key="{{ $it->getRouteKey() }}"
                        data-numero="{{ e($it->numero_empleado) }}"
                        data-nombre="{{ e($it->nombreEmpl) }}"
                        data-telar="{{ e($it->NoTelarId) }}"
                        data-turno="{{ e($it->Turno) }}"
                        data-salon="{{ e($it->SalonTejidoId) }}"
                        aria-selected="false">
                        <td class="px-6 py-4 align-middle font-medium text-gray-700">{{ $it->numero_empleado }}</td>
                        <td class="px-6 py-4 align-middle text-gray-800">{{ $it->nombreEmpl }}</td>
                        <td class="px-6 py-4 align-middle text-gray-800">{{ $it->NoTelarId }}</td>
                        <td class="px-6 py-4 align-middle text-gray-800">{{ $it->Turno }}</td>
                        <td class="px-6 py-4 align-middle text-gray-800">{{ $it->SalonTejidoId }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-solid fa-inbox text-5xl mb-3 text-gray-300 block"></i>
                            <p class="text-lg font-medium">Sin registros</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>



    <!-- Formulario global oculto para eliminar -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <!-- Modal para Nuevo Operador (Múltiples Registros) -->
    <div id="createModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4" onclick="if(event.target === this) closeModal('createModal')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto transform transition-all animate-modalFadeIn" onclick="event.stopPropagation()">
            <div class="bg-gradient-to-r from-green-600 via-green-500 to-green-600 text-white px-8 py-5 rounded-t-xl sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fa-solid fa-user-plus text-2xl"></i>
                        Nuevo Operador - Múltiples Telares
                    </h2>
                    <button type="button" data-close-modal="createModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-2 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-8">
                @if($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border-2 border-red-200 rounded-lg text-red-700 text-sm">
                        <i class="fa-solid fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
                    </div>
                @endif
                <form id="createForm" action="{{ route('tel-telares-operador.store') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-id-card text-green-600"></i>
                                Número Empleado <span class="text-red-500">*</span>
                            </label>
                            <select id="createEmpleado" name="numero_empleado" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all shadow-sm hover:shadow-md" required autofocus>
                                <option value="" disabled selected>Selecciona empleado</option>
                                @foreach(($usuarios ?? []) as $u)
                                    <option value="{{ $u->numero_empleado }}" data-nombre="{{ $u->nombre }}" data-turno="{{ $u->turno }}">{{ $u->numero_empleado }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-user text-green-600"></i>
                                Nombre
                            </label>
                            <input type="text" id="createNombre" name="nombreEmpl" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg bg-gray-50 shadow-sm" readonly>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-clock text-green-600"></i>
                                Turno
                            </label>
                            <input type="text" id="createTurno" name="Turno" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg bg-gray-50 shadow-sm" readonly>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-door-open text-green-600"></i>
                                Salón Tejido <span class="text-red-500">*</span>
                            </label>
                            <select id="createSalon" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all shadow-sm hover:shadow-md" required>
                                <option value="" disabled selected>Selecciona salón primero</option>
                                @php
                                    $salonesDisponibles = $telares->pluck('SalonTejidoId')->unique()->filter()->sort()->values();
                                @endphp
                                @foreach($salonesDisponibles as $salon)
                                    <option value="{{ $salon }}">{{ $salon }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div id="telaresContainer" class="mb-6 hidden">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-4">
                            <i class="fa-solid fa-list-check text-green-600"></i>
                            Selecciona Telares <span class="text-red-500">*</span>
                        </label>
                        <div id="telaresList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 max-h-[350px] overflow-y-auto p-4 border-2 border-gray-200 rounded-lg bg-gray-50 shadow-inner">
                            <!-- Los telares se cargarán dinámicamente aquí -->
                        </div>
                        <p class="mt-3 text-xs text-gray-500 flex items-center gap-2">
                            <i class="fa-solid fa-info-circle"></i>
                            Se crearán registros individuales para cada telar seleccionado
                        </p>
                    </div>

                    <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                        <button type="button" data-close-modal="createModal" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                            <i class="fa-solid fa-times"></i> Cancelar
                        </button>
                        <button type="submit" id="btnGuardar" class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-700 hover:to-green-600 text-white rounded-lg transition-all font-semibold shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2" disabled>
                            <i class="fa-solid fa-save"></i> <span>Guardar Registros</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Editar -->
    <div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4" onclick="if(event.target === this) closeModal('editModal')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl transform transition-all animate-modalFadeIn" onclick="event.stopPropagation()">
            <div class="bg-gradient-to-r from-yellow-600 via-yellow-500 to-yellow-600 text-white px-8 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fa-solid fa-edit text-2xl"></i>
                        Editar Operador
                    </h2>
                    <button type="button" data-close-modal="editModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-2 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-8">
                @if($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border-2 border-red-200 rounded-lg text-red-700 text-sm">
                        <i class="fa-solid fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
                    </div>
                @endif
                <form id="editForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-id-card text-yellow-600"></i>
                                Número Empleado <span class="text-red-500">*</span>
                            </label>
                            <select id="editEmpleado" name="numero_empleado" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all shadow-sm hover:shadow-md" required autofocus>
                                @foreach(($usuarios ?? []) as $u)
                                    <option value="{{ $u->numero_empleado }}" data-nombre="{{ $u->nombre }}" data-turno="{{ $u->turno }}">{{ $u->numero_empleado }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-user text-yellow-600"></i>
                                Nombre
                            </label>
                            <input type="text" id="editNombre" name="nombreEmpl" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg bg-gray-50 shadow-sm" readonly>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-gear text-yellow-600"></i>
                                No. Telar <span class="text-red-500">*</span>
                            </label>
                            <select id="editTelar" name="NoTelarId" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all shadow-sm hover:shadow-md" required>
                                @foreach(($telares ?? []) as $tel)
                                    <option value="{{ $tel->NoTelarId }}" data-salon="{{ $tel->SalonTejidoId }}">
                                        {{ $tel->NoTelarId }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-clock text-yellow-600"></i>
                                Turno
                            </label>
                            <input type="text" id="editTurno" name="Turno" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg bg-gray-50 shadow-sm" readonly>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-door-open text-yellow-600"></i>
                                Salón Tejido <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="editSalon" name="SalonTejidoId" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all shadow-sm hover:shadow-md" required>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                        <button type="button" data-close-modal="editModal" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                            <i class="fa-solid fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-yellow-600 to-yellow-500 hover:from-yellow-700 hover:to-yellow-600 text-white rounded-lg transition-all font-semibold shadow-md hover:shadow-lg flex items-center gap-2">
                            <i class="fa-solid fa-save"></i> Actualizar Operador
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Filtros (estilo BPM) --}}
    <div id="modal-filters" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-filter text-purple-600 mr-2"></i>Filtros
                </h2>
                <button type="button" data-close="#modal-filters" class="text-slate-500 hover:text-slate-700 text-3xl leading-none">&times;</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                    <label class="block text-xs text-gray-600 mb-2 text-center">
                        <i class="fa-solid fa-id-card mr-1"></i>No. Empleado
                    </label>
                    <select id="filter-empleado" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="">Todos</option>
                        @php
                            $empleadosUnique = $items->pluck('numero_empleado')->unique()->filter()->sort()->values();
                        @endphp
                        @foreach($empleadosUnique as $emp)
                            <option value="{{ $emp }}">{{ $emp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                    <label class="block text-xs text-gray-600 mb-2 text-center">
                        <i class="fa-solid fa-clock mr-1"></i>Turno
                    </label>
                    <select id="filter-turno" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="">Todos</option>
                        <option value="1">Turno 1</option>
                        <option value="2">Turno 2</option>
                        <option value="3">Turno 3</option>
                    </select>
                </div>
                <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                    <label class="block text-xs text-gray-600 mb-2 text-center">
                        <i class="fa-solid fa-door-open mr-1"></i>Salón
                    </label>
                    <select id="filter-salon" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="">Todos</option>
                        @foreach($salonesUnique as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" id="btn-clear-filters" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white transition text-sm">
                    <i class="fa-solid fa-eraser mr-1"></i>Limpiar
                </button>
            </div>
        </div>
    </div>

    </div>
    </div>
</div>

<style>
    /* Franjas: impar gris, par blanco (odd:bg-gray-100 even:bg-white en filas) */
    tbody tr.row-selectable { transition: background-color .15s ease, box-shadow .15s ease; }

    /* Hover más visible (fallback adicional al hover de clases) */
    tbody tr.row-selectable:hover { background-color: #eff6ff !important; }

    /* Estado seleccionado con borde lateral e indicador */
    tbody tr[aria-selected="true"] {
        background-color: #dbeafe !important; /* azul suave */
        box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.35);
    }
    tbody tr[aria-selected="true"] td:first-child {
        border-left: 4px solid #3b82f6; /* blue-600 */
    }

    /* Encabezado fijo al hacer scroll */
    #table-scroll-wrapper { overscroll-behavior: contain; }
    #table-scroll-wrapper thead th {
        position: sticky;
        top: 0;
        z-index: 20;
        background-color: #3b82f6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }

    /* Animación para modales */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .animate-modalFadeIn {
        animation: modalFadeIn 0.3s ease-out;
    }

    /* Mejorar scrollbar de la tabla */
    .overflow-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .overflow-auto::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .overflow-auto::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {
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
        const turno = selectedRow.dataset.turno || '';
        const salon = selectedRow.dataset.salon || '';
        openEditModal(selectedKey, numero, nombre, telar, turno, salon);
    }

    async function handleTopDelete() {
        if (!selectedKey) return;
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Quieres eliminar este operador?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        
        if (result.isConfirmed) {
            try {
                const response = await fetch(destroyUrl.replace('PLACEHOLDER', encodeURIComponent(selectedKey)), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Eliminar la fila de la tabla
                    if (selectedRow) {
                        selectedRow.remove();
                    }
                    clearSelection();
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message || 'Operador eliminado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al eliminar el operador'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo eliminar el operador. Intenta de nuevo.'
                });
            }
        }
    }
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error('Modal no encontrado:', modalId);
            return;
        }

        // Reset form if it's the create modal
        if (modalId === 'createModal') {
            const form = modal.querySelector('form');
            if (form) form.reset();
            // Clear readonly fields manually
            const nombre = document.getElementById('createNombre');
            const turno = document.getElementById('createTurno');
            const salon = document.getElementById('createSalon');
            if (nombre) nombre.value = '';
            if (turno) turno.value = '';
            if (salon) salon.value = '';
            // Reset telares container
            if (telaresContainer) {
                telaresContainer.classList.add('hidden');
                telaresList.innerHTML = '';
            }
            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save"></i> <span>Guardar Registros</span>';
            }
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        // Focus en el primer input si existe
        setTimeout(() => {
            const firstInput = modal.querySelector('input[type="text"], input[type="number"], textarea, select');
            if (firstInput) firstInput.focus();
        }, 100);
    }
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
            // Reset form si es createModal
            if (modalId === 'createModal') {
                const form = modal.querySelector('form');
                if (form) form.reset();
            }
        }
    }
    function openEditModal(key, numero, nombre, telar, turno = '', salon = '') {
        const editEmpSel = document.getElementById('editEmpleado');
        if (editEmpSel) {
            editEmpSel.value = String(numero || '');
        }
        document.getElementById('editNombre').value = nombre;
        const editTelarSel = document.getElementById('editTelar');
        if (editTelarSel) {
            editTelarSel.value = String(telar || '');
        }
        const turnoInput = document.getElementById('editTurno');
        if (turnoInput) turnoInput.value = String(turno || '');
        const salonInput = document.getElementById('editSalon');
        if (salonInput) salonInput.value = salon || '';
        document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        openModal('editModal');
    }
    function openEditModalFromBtn(btn) {
        const key = btn.dataset.key;
        const numero = btn.dataset.numero || '';
        const nombre = btn.dataset.nombre || '';
        const telar = btn.dataset.telar || '';
        const turno = btn.dataset.turno || '';
        const salon = btn.dataset.salon || '';
        openEditModal(key, numero, nombre, telar, turno, salon);
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

    // --- Modal Filtros (estilo BPM) ---
    const modalFilters = document.getElementById('modal-filters');
    const btnOpenFilters = document.getElementById('btn-open-filters');
    const filterEmpleado = document.getElementById('filter-empleado');
    const filterTurno = document.getElementById('filter-turno');
    const filterSalon = document.getElementById('filter-salon');
    const btnClearFilters = document.getElementById('btn-clear-filters');

    function openFiltersModal() {
        if (!modalFilters) return;
        modalFilters.classList.remove('hidden');
        modalFilters.classList.add('flex');
    }

    function closeFiltersModal() {
        if (!modalFilters) return;
        modalFilters.classList.add('hidden');
        modalFilters.classList.remove('flex');
    }

    function applyFilters() {
        const empleado = (filterEmpleado?.value || '').trim();
        const turno = (filterTurno?.value || '').trim();
        const salon = (filterSalon?.value || '').trim();
        const rows = document.querySelectorAll('.row-selectable');
        let visibleCount = 0;
        
        rows.forEach(tr => {
            const e = (tr.dataset.numero || '').toString().trim();
            const t = (tr.dataset.turno || '').toString().trim();
            const s = (tr.dataset.salon || '').toString().trim();
            const matchEmpleado = !empleado || e === empleado;
            const matchTurno = !turno || t === turno;
            const matchSalon = !salon || s === salon;
            const show = matchEmpleado && matchTurno && matchSalon;
            tr.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        
        // Actualizar mensaje "Sin registros"
        updateEmptyMessage();
    }

    btnOpenFilters?.addEventListener('click', openFiltersModal);
    btnClearFilters?.addEventListener('click', function() {
        if (filterEmpleado) filterEmpleado.value = '';
        if (filterTurno) filterTurno.value = '';
        if (filterSalon) filterSalon.value = '';
        applyFilters();
        closeFiltersModal();
    });
    filterEmpleado?.addEventListener('change', applyFilters);
    filterTurno?.addEventListener('change', applyFilters);
    filterSalon?.addEventListener('change', applyFilters);

    modalFilters?.addEventListener('click', function(e) {
        if (e.target === modalFilters) closeFiltersModal();
    });
    document.querySelectorAll('[data-close="#modal-filters"]').forEach(btn => {
        btn.addEventListener('click', closeFiltersModal);
    });

    applyFilters();

    // Event listeners para botones de navbar
    document.getElementById('btn-create')?.addEventListener('click', function() {
        openModal('createModal');
    });

    document.getElementById('btn-top-edit')?.addEventListener('click', handleTopEdit);
    document.getElementById('btn-top-delete')?.addEventListener('click', handleTopDelete);

    // Event listeners para filas de la tabla
    document.querySelectorAll('.row-selectable').forEach(row => {
        row.addEventListener('click', function() {
            selectRow(this);
        });
    });

    // Event listeners para botones de cerrar modal
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-close-modal');
            closeModal(modalId);
        });
    });

    // Cargar telares por salón (modal crear)
    const createSalon = document.getElementById('createSalon');
    const telaresContainer = document.getElementById('telaresContainer');
    const telaresList = document.getElementById('telaresList');
    const btnGuardar = document.getElementById('btnGuardar');
    
    const telaresData = @json($telares ?? []);
    
    function cargarTelaresPorSalon(salon) {
        if (!salon || salon === '') {
            telaresContainer.classList.add('hidden');
            btnGuardar.disabled = true;
            telaresList.innerHTML = '';
            return;
        }
        
        const telaresDelSalon = telaresData.filter(t => (t.SalonTejidoId || '').toString().trim() === salon.toString().trim());
        
        if (telaresDelSalon.length === 0) {
            telaresList.innerHTML = '<p class="col-span-full text-center text-gray-500 py-4">No hay telares disponibles para este salón</p>';
            telaresContainer.classList.remove('hidden');
            btnGuardar.disabled = true;
            return;
        }
        
        telaresList.innerHTML = telaresDelSalon.map(telar => {
            const telarId = (telar.NoTelarId || '').toString();
            return `
                <label class="flex items-center p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer transition-colors">
                    <input type="checkbox" name="telares[]" value="${telarId}" data-salon="${salon}" class="telar-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm font-medium text-gray-700">${telarId}</span>
                </label>
            `;
        }).join('');
        
        telaresContainer.classList.remove('hidden');
        actualizarEstadoGuardar();
        
        // Event listeners para checkboxes
        document.querySelectorAll('.telar-checkbox').forEach(cb => {
            cb.addEventListener('change', actualizarEstadoGuardar);
        });
    }
    
    function actualizarEstadoGuardar() {
        const checked = document.querySelectorAll('.telar-checkbox:checked').length;
        btnGuardar.disabled = checked === 0;
        if (checked > 0) {
            btnGuardar.innerHTML = `<i class="fa-solid fa-save"></i> <span>Guardar ${checked} Registro${checked > 1 ? 's' : ''}</span>`;
        } else {
            btnGuardar.innerHTML = `<i class="fa-solid fa-save"></i> <span>Guardar Registros</span>`;
        }
    }
    
    createSalon?.addEventListener('change', function() {
        cargarTelaresPorSalon(this.value);
    });
    
    // Vincular select de telar con salón (modal editar - mantener funcionalidad existente)
    function wireTelarSalon(selectId, salonInputId) {
        const sel = document.getElementById(selectId);
        const salon = document.getElementById(salonInputId);
        if (!sel || !salon) return;
        sel.addEventListener('change', () => {
            const opt = sel.options[sel.selectedIndex];
            const salonVal = opt ? (opt.getAttribute('data-salon') || '') : '';
            salon.value = salonVal;
        });
    }
    wireTelarSalon('editTelar', 'editSalon');

    // Vincular empleados -> nombre y turno (modales)
    function wireEmpleado(selectId, nombreId, turnoId) {
        const sel = document.getElementById(selectId);
        const nombre = document.getElementById(nombreId);
        const turno = document.getElementById(turnoId);
        if (!sel || !nombre || !turno) return;
        const sync = () => {
            const op = sel.options[sel.selectedIndex];
            if (!op || !op.value || op.disabled) {
                nombre.value = '';
                turno.value = '';
                return;
            }
            nombre.value = op.getAttribute('data-nombre') || '';
            turno.value = op.getAttribute('data-turno') || '';
        };
        sel.addEventListener('change', sync);
        // Solo sincronizar al inicio si ya hay un valor válido seleccionado
        if (sel.value) sync();
    }
    wireEmpleado('createEmpleado','createNombre','createTurno');
    wireEmpleado('editEmpleado','editNombre','editTurno');

    // Función para crear una fila de tabla
    function createTableRow(item) {
        const tr = document.createElement('tr');
        tr.className = 'odd:bg-gray-100 even:bg-white cursor-pointer transition-colors duration-150 hover:bg-blue-50 row-selectable';
        tr.setAttribute('data-key', item.Id);
        tr.setAttribute('data-numero', item.numero_empleado || '');
        tr.setAttribute('data-nombre', item.nombreEmpl || '');
        tr.setAttribute('data-telar', item.NoTelarId || '');
        tr.setAttribute('data-turno', item.Turno || '');
        tr.setAttribute('data-salon', item.SalonTejidoId || '');
        tr.setAttribute('aria-selected', 'false');
        
        tr.innerHTML = `
            <td class="px-6 py-4 align-middle font-medium text-gray-700">${escapeHtml(item.numero_empleado || '')}</td>
            <td class="px-6 py-4 align-middle text-gray-800">${escapeHtml(item.nombreEmpl || '')}</td>
            <td class="px-6 py-4 align-middle text-gray-800">${escapeHtml(item.NoTelarId || '')}</td>
            <td class="px-6 py-4 align-middle text-gray-800">${escapeHtml(item.Turno || '')}</td>
            <td class="px-6 py-4 align-middle text-gray-800">${escapeHtml(item.SalonTejidoId || '')}</td>
        `;
        
        // Agregar event listener para selección
        tr.addEventListener('click', function() {
            selectRow(this);
        });
        
        return tr;
    }

    // Función helper para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Función para verificar y actualizar el mensaje "Sin registros"
    function updateEmptyMessage() {
        const tbody = document.querySelector('tbody');
        const visibleRows = Array.from(tbody.querySelectorAll('.row-selectable')).filter(row => row.style.display !== 'none');
        const emptyRow = tbody.querySelector('tr:not(.row-selectable)');
        
        if (visibleRows.length === 0 && !emptyRow) {
            const emptyTr = document.createElement('tr');
            emptyTr.innerHTML = `
                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                    <i class="fa-solid fa-inbox text-5xl mb-3 text-gray-300 block"></i>
                    <p class="text-lg font-medium">Sin registros</p>
                </td>
            `;
            tbody.appendChild(emptyTr);
        } else if (visibleRows.length > 0 && emptyRow) {
            emptyRow.remove();
        }
    }

    // Función para actualizar una fila existente
    function updateTableRow(row, item) {
        row.setAttribute('data-key', item.Id);
        row.setAttribute('data-numero', item.numero_empleado || '');
        row.setAttribute('data-nombre', item.nombreEmpl || '');
        row.setAttribute('data-telar', item.NoTelarId || '');
        row.setAttribute('data-turno', item.Turno || '');
        row.setAttribute('data-salon', item.SalonTejidoId || '');
        
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            cells[0].textContent = item.numero_empleado || '';
            cells[1].textContent = item.nombreEmpl || '';
            cells[2].textContent = item.NoTelarId || '';
            cells[3].textContent = item.Turno || '';
            cells[4].textContent = item.SalonTejidoId || '';
        }
    }

    // Validar y enviar formulario de creación (múltiples registros)
    const createForm = document.getElementById('createForm');
    if (createForm) {
        createForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const empSel = document.getElementById('createEmpleado');
            const nombre = document.getElementById('createNombre');
            const turno = document.getElementById('createTurno');
            const salon = document.getElementById('createSalon');
            const telaresCheckboxes = document.querySelectorAll('.telar-checkbox:checked');
            
            if (!empSel.value || !nombre.value || !turno.value || !salon.value) {
                Swal.fire({
                    icon: 'error',
                    title: 'Campos incompletos',
                    text: 'Por favor completa todos los campos requeridos'
                });
                return false;
            }
            
            if (telaresCheckboxes.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Selecciona telares',
                    text: 'Debes seleccionar al menos un telar'
                });
                return false;
            }
            
            // Validar duplicados antes de enviar
            const numeroEmpleado = empSel.value.trim();
            const telaresSeleccionados = Array.from(telaresCheckboxes).map(cb => cb.value.trim());
            const duplicados = [];
            
            document.querySelectorAll('.row-selectable').forEach(row => {
                const num = (row.dataset.numero || '').trim();
                const tel = (row.dataset.telar || '').trim();
                if (num === numeroEmpleado && telaresSeleccionados.includes(tel)) {
                    duplicados.push(tel);
                }
            });
            
            if (duplicados.length > 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Asignaciones duplicadas',
                    text: `Este operador ya tiene asignados los telares: ${duplicados.join(', ')}`
                });
                return false;
            }
            
            // Crear múltiples registros
            const formData = new FormData();
            formData.append('numero_empleado', numeroEmpleado);
            formData.append('nombreEmpl', nombre.value);
            formData.append('Turno', turno.value);
            formData.append('SalonTejidoId', salon.value);
            telaresSeleccionados.forEach((telar, index) => {
                formData.append(`telares[${index}]`, telar);
            });
            formData.append('_token', '{{ csrf_token() }}');
            
            try {
                const response = await fetch('{{ route("tel-telares-operador.store") }}', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Agregar las nuevas filas a la tabla
                    if (result.data && result.data.length > 0) {
                        const tbody = document.querySelector('tbody');
                        // Eliminar mensaje "Sin registros" si existe
                        const emptyRow = tbody.querySelector('tr:not(.row-selectable)');
                        if (emptyRow) emptyRow.remove();
                        
                        result.data.forEach(item => {
                            const newRow = createTableRow(item);
                            tbody.insertBefore(newRow, tbody.firstChild);
                        });
                        
                        // Aplicar filtros activos a las nuevas filas
                        applyFilters();
                    }
                    
                    // Cerrar modal y limpiar formulario
                    closeModal('createModal');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: result.message || `Se crearon ${telaresSeleccionados.length} registro(s) correctamente`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'Error al crear los registros'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudieron crear los registros. Intenta de nuevo.'
                });
            }
        });
    }

    // Handler para formulario de edición
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(editForm);
            formData.append('_method', 'PUT');
            formData.append('_token', '{{ csrf_token() }}');
            
            const actionUrl = editForm.getAttribute('action');
            if (!actionUrl) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'URL de acción no válida'
                });
                return;
            }
            
            try {
                const response = await fetch(actionUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Actualizar la fila en la tabla
                    if (selectedRow && result.data) {
                        updateTableRow(selectedRow, result.data);
                        clearSelection();
                    }
                    
                    // Cerrar modal
                    closeModal('editModal');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: result.message || 'Operador actualizado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'Error al actualizar el operador'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo actualizar el operador. Intenta de nuevo.'
                });
            }
        });
    }
});
</script>
@endsection
