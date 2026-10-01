@extends('layouts.app')

@section('title', 'Telares por Operador')
@section('page-title')
Telares por Operador
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Filtros por columna (componentes/tabla-columnas.ts): reemplazan el modal de empleado/turno/salón. --}}
        <flux:button data-alternar-filtros="#telaresOperadorTable" aria-pressed="false" icon="funnel"
                     class="min-h-touch min-w-touch" title="Filtrar por columna" aria-label="Filtrar por columna" />
        <x-navbar.button-create id="btn-create" title="Nuevo Operador" module="Telares x Operador"/>
        <x-navbar.button-edit id="btn-top-edit" title="Editar Operador" module="Telares x Operador" />
        <x-navbar.button-delete id="btn-top-delete" title="Eliminar Operador" module="Telares x Operador" />
    </div>
@endsection

@section('content')
<div class="pantalla-completa p-2">
    {{-- Errores de validación y éxito: los pinta x-ui.flash del layout. --}}

    @php
        $items = $items instanceof \Illuminate\Support\Collection ? $items : collect($items);
        $itemsAgrupados = $items->groupBy('numero_empleado');
    @endphp

    {{-- flux:table + .tabla-cebra / .tabla-seleccionable / data-filtros-columna (app.css, tabla-columnas.ts).
         Selección: el script de abajo solo pone aria-selected. --}}
    <div class="tabla-pantalla bg-white rounded-lg shadow-lg overflow-hidden">
        <flux:table id="telaresOperadorTable" data-filtros-columna class="tabla-cebra tabla-seleccionable">
            <flux:table.columns sticky class="bg-white">
                <flux:table.column>Número</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Telar</flux:table.column>
                <flux:table.column>Turno</flux:table.column>
                <flux:table.column>Salón</flux:table.column>
                <flux:table.column align="center" data-sin-filtro>Supervisor</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($itemsAgrupados as $numero => $registros)
                    @php
                        $primer = $registros->first();
                        $telares = $registros->pluck('NoTelarId')->filter()->unique()->sort()->implode(', ');
                        $salones = $registros->pluck('SalonTejidoId')->filter()->unique()->sort()->implode(', ');
                    @endphp
                    <flux:table.row class="row-selectable"
                        data-key="{{ $primer->getRouteKey() }}"
                        data-numero="{{ $numero }}"
                        data-nombre="{{ $primer->nombreEmpl }}"
                        data-telar="{{ $telares }}"
                        data-turno="{{ $primer->Turno }}"
                        data-salon="{{ $salones }}"
                        data-supervisor="{{ $primer->Supervisor ? '1' : '0' }}"
                        aria-selected="false">
                        <flux:table.cell variant="strong">{{ $numero }}</flux:table.cell>
                        <flux:table.cell>{{ $primer->nombreEmpl }}</flux:table.cell>
                        <flux:table.cell class="font-semibold text-blue-700">{{ $telares }}</flux:table.cell>
                        <flux:table.cell>{{ $primer->Turno }}</flux:table.cell>
                        <flux:table.cell>{{ $salones }}</flux:table.cell>
                        <flux:table.cell align="center">
                            @if($primer->Supervisor)
                                <flux:badge size="sm" color="green" inset="top bottom">Sí</flux:badge>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center">
                            <i class="fa-solid fa-inbox text-5xl mb-3 text-gray-300 block" aria-hidden="true"></i>
                            <p class="text-lg font-medium">Sin registros</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
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
                            </select>
                        </div>
                        <div class="md:col-span-3 flex items-center gap-3">
                            <input type="checkbox" id="createSupervisor" name="Supervisor" value="1" class="w-5 h-5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <label for="createSupervisor" class="text-sm font-semibold text-gray-700 cursor-pointer">
                                <i class="fa-solid fa-user-tie text-green-600 mr-1"></i>Supervisor
                            </label>
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
                                <i class="fa-solid fa-clock text-yellow-600"></i>
                                Turno
                            </label>
                            <input type="text" id="editTurno" name="Turno" class="w-full px-4 py-3 text-base border-2 border-gray-300 rounded-lg bg-gray-50 shadow-sm" readonly>
                        </div>
                        <div class="md:col-span-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                                <i class="fa-solid fa-list-check text-yellow-600"></i>
                                Telares asignados
                            </label>
                            <div id="editTelaresList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 max-h-[350px] overflow-y-auto p-4 border-2 border-gray-200 rounded-lg bg-gray-50 shadow-inner"></div>
                            <p class="mt-3 text-xs text-gray-500 flex items-center gap-2">
                                <i class="fa-solid fa-info-circle"></i>
                                Los telares marcados quedan asignados; al desmarcarlos se eliminan de este operador.
                            </p>
                        </div>
                        <div class="md:col-span-3 flex items-center gap-3">
                            <input type="checkbox" id="editSupervisor" name="Supervisor" value="1" class="w-5 h-5 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                            <label for="editSupervisor" class="text-sm font-semibold text-gray-700 cursor-pointer">
                                <i class="fa-solid fa-user-tie text-yellow-600 mr-1"></i>Supervisor
                            </label>
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
</div>

<style>
    /* Animación de los modales Crear/Editar. Cebra, selección y encabezado fijo: flux:table + app.css. */
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.9) translateY(-20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .animate-modalFadeIn { animation: modalFadeIn 0.3s ease-out; }
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
            selectedRow.setAttribute('aria-selected', 'false');
        }
        // Seleccionar actual
        selectedRow = row;
        selectedKey = row.dataset.key || null;
        row.setAttribute('aria-selected', 'true');
        updateTopButtonsState();
    }

    /** Una fila por empleado (agrupa sus telares y salones) con las clases de flux:table. Crear y editar. */
    function filasDeOperadores(data) {
        const groupedByEmpleado = data.reduce((acc, item) => {
            const num = item.numero_empleado || '';
            if (!acc[num]) acc[num] = [];
            acc[num].push(item);
            return acc;
        }, {});
        
        return Object.values(groupedByEmpleado).map(registros => {
            const primero = registros[0];
            const telares = registros.map(r => r.NoTelarId).filter(Boolean).sort().join(', ');
            const salones = [...new Set(registros.map(r => r.SalonTejidoId).filter(Boolean))].sort().join(', ');
            
            const tr = document.createElement('tr');
            tr.className = 'row-selectable'; // cebra/hover/selección: .tabla-cebra/.tabla-seleccionable
            tr.setAttribute('data-key', primero.Id);
            tr.setAttribute('data-numero', primero.numero_empleado || '');
            tr.setAttribute('data-nombre', primero.nombreEmpl || '');
            tr.setAttribute('data-telar', telares);
            tr.setAttribute('data-turno', primero.Turno || '');
            tr.setAttribute('data-salon', salones);
            tr.setAttribute('data-supervisor', primero.Supervisor ? '1' : '0');
            tr.setAttribute('aria-selected', 'false');
            
            const supervisorCell = primero.Supervisor 
                ? '<i class="fa-solid fa-check text-green-600" title="Sí"></i>' 
                : '<span class="text-gray-400">—</span>';
            
            tr.innerHTML = `
                <td class="py-3 px-3 text-sm font-medium text-zinc-800 border-t border-zinc-800/10">${escapeHtml(primero.numero_empleado || '')}</td>
                <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(primero.nombreEmpl || '')}</td>
                <td class="py-3 px-3 text-sm font-semibold text-blue-700 border-t border-zinc-800/10">${escapeHtml(telares)}</td>
                <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(primero.Turno || '')}</td>
                <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(salones)}</td>
                <td class="py-3 px-3 text-sm text-center border-t border-zinc-800/10">${supervisorCell}</td>
            `;
            
            tr.addEventListener('click', function() { selectRow(this); });
            return tr;
        });
    }

    function handleTopEdit() {
        if (!selectedRow || !selectedKey) return;
        const numero = selectedRow.dataset.numero || '';
        const nombre = selectedRow.dataset.nombre || '';
        const telar = selectedRow.dataset.telar || '';
        const turno = selectedRow.dataset.turno || '';
        const salon = selectedRow.dataset.salon || '';
        const supervisor = selectedRow.dataset.supervisor || '0';
        openEditModal(selectedKey, numero, nombre, telar, turno, salon, supervisor);
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
                    const numeroEmpleado = data.numero_empleado || (selectedRow ? selectedRow.dataset.numero : null);
                    const tbody = document.querySelector('tbody');
                    
                    if (numeroEmpleado && tbody) {
                        const rowsToRemove = Array.from(tbody.querySelectorAll('.row-selectable'))
                            .filter(row => String(row.dataset.numero || '').trim() === String(numeroEmpleado).trim());
                        rowsToRemove.forEach(row => row.remove());
                    } else if (selectedRow) {
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
            if (salon) salon.selectedIndex = 0;
            // Reset telares container
            if (telaresContainer) {
                telaresContainer.classList.add('hidden');
                telaresList.innerHTML = '';
            }
            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save"></i> <span>Guardar Registros</span>';
            }
            // Cargar catálogos desde API
            cargarCatalogos();
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
    function openEditModal(key, numero, nombre, telar, turno = '', salon = '', supervisor = '0') {
        const editEmpSel = document.getElementById('editEmpleado');
        if (editEmpSel) {
            editEmpSel.value = String(numero || '');
        }
        document.getElementById('editNombre').value = nombre;
        const turnoInput = document.getElementById('editTurno');
        if (turnoInput) turnoInput.value = String(turno || '');
        const editSupervisor = document.getElementById('editSupervisor');
        if (editSupervisor) editSupervisor.checked = (supervisor === '1' || supervisor === 'true');
        renderEditTelares(numero);
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
        const supervisor = btn.dataset.supervisor || '0';
        openEditModal(key, numero, nombre, telar, turno, salon, supervisor);
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
    
    let telaresData = Array.isArray(@json($telares ?? [])) ? @json($telares ?? []) : [];
    let salonesData = [];
    let catalogosCargados = false;

    async function cargarCatalogos() {
        if (catalogosCargados) return;
        try {
            const response = await fetch('{{ route("tel-telares-operador.api.salones-y-telares") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (response.ok) {
                const data = await response.json();
                salonesData = Array.isArray(data.salones) ? data.salones : [];
                telaresData = Array.isArray(data.telares) ? data.telares : [];
                populateSalonesSelect();
                catalogosCargados = true;
            }
        } catch (e) {
            console.error('Error cargando catálogos:', e);
        }
    }

    function populateSalonesSelect() {
        if (!createSalon) return;
        const currentValue = createSalon.value;
        createSalon.innerHTML = '<option value="" disabled selected>Selecciona salón</option>';
        salonesData.forEach(salon => {
            const option = document.createElement('option');
            option.value = salon;
            option.textContent = salon;
            if (salon === currentValue) option.selected = true;
            createSalon.appendChild(option);
        });
    }

    function getTelaresAsignadosPorEmpleado(numeroEmpleado) {
        const numero = String(numeroEmpleado || '').trim();
        const asignados = new Set();
        Array.from(document.querySelectorAll('.row-selectable'))
            .filter(row => String(row.dataset.numero || '').trim() === numero)
            .forEach(row => {
                const telares = String(row.dataset.telar || '').trim();
                if (telares) {
                    telares.split(',').map(t => t.trim()).filter(Boolean).forEach(t => asignados.add(t));
                }
            });
        return asignados;
    }

    function renderEditTelares(numeroEmpleado) {
        const list = document.getElementById('editTelaresList');
        if (!list) return;

        if (telaresData.length === 0) {
            cargarCatalogos().then(() => {
                renderEditTelaresInternal(numeroEmpleado, list);
            });
        } else {
            renderEditTelaresInternal(numeroEmpleado, list);
        }
    }

    function renderEditTelaresInternal(numeroEmpleado, list) {
        const asignados = getTelaresAsignadosPorEmpleado(numeroEmpleado);
        list.innerHTML = telaresData.map(telar => {
            const telarId = String(telar.NoTelarId || '').trim();
            const salon = String(telar.SalonTejidoId || '').trim();
            const checked = asignados.has(telarId) ? 'checked' : '';

            return `
                <label class="flex items-center justify-between gap-2 p-3 border border-gray-300 rounded-lg hover:bg-yellow-50 cursor-pointer transition-colors">
                    <span class="flex items-center min-w-0">
                        <input type="checkbox" name="telares[]" value="${escapeHtml(telarId)}" ${checked} class="edit-telar-checkbox h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded">
                        <span class="ml-2 text-sm font-medium text-gray-700">${escapeHtml(telarId)}</span>
                    </span>
                    <span class="text-[11px] text-gray-500 truncate">${escapeHtml(salon)}</span>
                </label>
            `;
        }).join('');
    }

    function cargarTelaresPorSalon(salon) {
        if (!salon || salon === '') {
            telaresContainer.classList.add('hidden');
            btnGuardar.disabled = true;
            telaresList.innerHTML = '';
            return;
        }

        if (telaresData.length === 0) {
            cargarCatalogos().then(() => {
                cargarTelaresPorSalon(salon);
            });
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
            const escapedId = escapeHtml(telarId);
            const escapedSalon = escapeHtml(salon);
            return `
                <label class="flex items-center p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer transition-colors">
                    <input type="checkbox" name="telares[]" value="${escapedId}" data-salon="${escapedSalon}" class="telar-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm font-medium text-gray-700">${escapedId}</span>
                </label>
            `;
        }).join('');

        telaresContainer.classList.remove('hidden');
        actualizarEstadoGuardar();

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
            if (selectId === 'editEmpleado') {
                renderEditTelares(sel.value);
            }
        };
        sel.addEventListener('change', sync);
    }
    wireEmpleado('createEmpleado','createNombre','createTurno');
    wireEmpleado('editEmpleado','editNombre','editTurno');

    // Función para crear una fila de tabla
    function createTableRow(item) {
        const tr = document.createElement('tr');
        tr.className = 'row-selectable'; // cebra/hover/selección: .tabla-cebra/.tabla-seleccionable
        tr.setAttribute('data-key', item.Id);
        tr.setAttribute('data-numero', item.numero_empleado || '');
        tr.setAttribute('data-nombre', item.nombreEmpl || '');
        tr.setAttribute('data-telar', item.NoTelarId || '');
        tr.setAttribute('data-turno', item.Turno || '');
        tr.setAttribute('data-salon', item.SalonTejidoId || '');
        tr.setAttribute('data-supervisor', item.Supervisor ? '1' : '0');
        tr.setAttribute('aria-selected', 'false');
        
        const supervisorCell = item.Supervisor ? '<i class="fa-solid fa-check text-green-600" title="Sí"></i>' : '<span class="text-gray-400">—</span>';
        tr.innerHTML = `
            <td class="py-3 px-3 text-sm font-medium text-zinc-800 border-t border-zinc-800/10">${escapeHtml(item.numero_empleado || '')}</td>
            <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(item.nombreEmpl || '')}</td>
            <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(item.NoTelarId || '')}</td>
            <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(item.Turno || '')}</td>
            <td class="py-3 px-3 text-sm text-zinc-500 border-t border-zinc-800/10">${escapeHtml(item.SalonTejidoId || '')}</td>
            <td class="py-3 px-3 text-sm text-center border-t border-zinc-800/10">${supervisorCell}</td>
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
                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
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
        row.setAttribute('data-supervisor', item.Supervisor ? '1' : '0');
        
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            cells[0].textContent = item.numero_empleado || '';
            cells[1].textContent = item.nombreEmpl || '';
            cells[2].textContent = item.NoTelarId || '';
            cells[3].textContent = item.Turno || '';
            cells[4].textContent = item.SalonTejidoId || '';
            cells[5].innerHTML = item.Supervisor ? '<i class="fa-solid fa-check text-green-600" title="Sí"></i>' : '<span class="text-gray-400">—</span>';
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

            const numeroEmpleado = empSel.value.trim();
            const telaresSeleccionados = Array.from(telaresCheckboxes).map(cb => cb.value.trim());

            // Crear múltiples registros (el backend omite duplicados y lo indica en el mensaje)
            const formData = new FormData();
            formData.append('numero_empleado', numeroEmpleado);
            formData.append('nombreEmpl', nombre.value);
            formData.append('Turno', turno.value);
            formData.append('SalonTejidoId', salon.value);
            const createSupervisor = document.getElementById('createSupervisor');
            formData.append('Supervisor', (createSupervisor && createSupervisor.checked) ? '1' : '0');
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
                    if (result.data && result.data.length > 0) {
                        const tbody = document.querySelector('tbody');
                        const emptyRow = tbody.querySelector('tr:not(.row-selectable)');
                        if (emptyRow) emptyRow.remove();
                        
                        filasDeOperadores(result.data).forEach(tr => tbody.insertBefore(tr, tbody.firstChild));
                        
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
            const editSupervisor = document.getElementById('editSupervisor');
            formData.set('Supervisor', (editSupervisor && editSupervisor.checked) ? '1' : '0');
            
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
                    const numeroActual = String(document.getElementById('editEmpleado')?.value || '').trim();
                    const tbody = document.querySelector('tbody');

                    Array.from(tbody.querySelectorAll('.row-selectable'))
                        .filter(row => String(row.dataset.numero || '').trim() === numeroActual)
                        .forEach(row => row.remove());

                    if (Array.isArray(result.data) && result.data.length > 0) {
                        const emptyRow = tbody.querySelector('tr:not(.row-selectable)');
                        if (emptyRow) emptyRow.remove();
                        
                        filasDeOperadores(result.data).forEach(tr => tbody.appendChild(tr));
                    }

                    clearSelection();
                    
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
