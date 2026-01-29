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
                text: '{{ session('success') }}',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif

    @php
        $salonesUnique = $items->pluck('SalonTejidoId')->unique()->filter()->sort()->values();
    @endphp

    <div class="bg-white rounded shadow">
        <div class="overflow-auto max-h-[70vh]">
            <table class="min-w-full text-md">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="sticky top-0 z-10 bg-blue-500 px-3 py-2 text-left">Número</th>
                        <th class="sticky top-0 z-10 bg-blue-500 px-3 py-2 text-left">Nombre</th>
                        <th class="sticky top-0 z-10 bg-blue-500 px-3 py-2 text-left">Telar</th>
                        <th class="sticky top-0 z-10 bg-blue-500 px-3 py-2 text-left">Turno</th>
                        <th class="sticky top-0 z-10 bg-blue-500 px-3 py-2 text-left">Salón</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $it)
                    <tr class="odd:bg-white even:bg-gray-50 cursor-pointer transition-colors duration-150 hover:bg-blue-50 row-selectable"
                        data-key="{{ $it->getRouteKey() }}"
                        data-numero="{{ e($it->numero_empleado) }}"
                        data-nombre="{{ e($it->nombreEmpl) }}"
                        data-telar="{{ e($it->NoTelarId) }}"
                        data-turno="{{ e($it->Turno) }}"
                        data-salon="{{ e($it->SalonTejidoId) }}"
                        aria-selected="false">
                        <td class="px-3 py-2 align-middle">{{ $it->numero_empleado }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->nombreEmpl }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->NoTelarId }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->Turno }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->SalonTejidoId }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-3 text-center text-gray-500">Sin registros</td></tr>
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-md font-medium">Número Empleado</label>
                            <select id="createEmpleado" name="numero_empleado" class="w-full px-3 py-2 border rounded" required>
                                <option value="" disabled selected>Selecciona empleado</option>
                                @foreach(($usuarios ?? []) as $u)
                                    <option value="{{ $u->numero_empleado }}" data-nombre="{{ $u->nombre }}" data-turno="{{ $u->turno }}">{{ $u->numero_empleado }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-md font-medium">Nombre</label>
                            <input type="text" id="createNombre" name="nombreEmpl" class="w-full px-3 py-2 border rounded bg-gray-50" readonly required>
                        </div>
                        <div>
                            <label class="block text-md font-medium">Telar</label>
                            <select id="createTelar" name="NoTelarId" class="w-full px-3 py-2 border rounded" required>
                                <option value="" disabled selected>Selecciona telar</option>
                                @foreach(($telares ?? []) as $tel)
                                    <option value="{{ $tel->NoTelarId }}" data-salon="{{ $tel->SalonTejidoId }}">
                                        {{ $tel->NoTelarId }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-md font-medium">Turno</label>
                            <input type="text" id="createTurno" name="Turno" class="w-full px-3 py-2 border rounded bg-gray-50" readonly required>
                        </div>
                        <div>
                            <label class="block text-md font-medium">Salón Tejido Id</label>
                            <input type="text" id="createSalon" name="SalonTejidoId" class="w-full px-3 py-2 border rounded" required>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" data-close-modal="createModal" class="px-4 py-2 bg-gray-500 text-white rounded mr-2 mt-3">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded mt-3">Guardar</button>
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-md font-medium">Número Empleado</label>
                            <select id="editEmpleado" name="numero_empleado" class="w-full px-3 py-2 border rounded" required>
                                @foreach(($usuarios ?? []) as $u)
                                    <option value="{{ $u->numero_empleado }}" data-nombre="{{ $u->nombre }}" data-turno="{{ $u->turno }}">{{ $u->numero_empleado }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-md font-medium">Nombre</label>
                            <input type="text" id="editNombre" name="nombreEmpl" class="w-full px-3 py-2 border rounded bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-md font-medium">No. Telar</label>
                            <select id="editTelar" name="NoTelarId" class="w-full px-3 py-2 border rounded" required>
                                @foreach(($telares ?? []) as $tel)
                                    <option value="{{ $tel->NoTelarId }}" data-salon="{{ $tel->SalonTejidoId }}">
                                        {{ $tel->NoTelarId }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-md font-medium">Turno</label>
                            <input type="text" id="editTurno" name="Turno" class="w-full px-3 py-2 border rounded bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-md font-medium">Salón Tejido</label>
                            <input type="text" id="editSalon" name="SalonTejidoId" class="w-full px-3 py-2 border rounded" required>
                        </div>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button type="button" data-close-modal="editModal" class="px-4 py-2 bg-gray-500 text-white rounded mr-2 w-full">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded w-full">Actualizar</button>
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
            <div class="grid grid-cols-2 gap-3 mb-4">
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
        }

        modal.classList.remove('hidden');
    }
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
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
        const turno = (filterTurno?.value || '').trim();
        const salon = (filterSalon?.value || '').trim();
        const rows = document.querySelectorAll('.row-selectable');
        rows.forEach(tr => {
            const t = (tr.dataset.turno || '').toString().trim();
            const s = (tr.dataset.salon || '').toString().trim();
            const matchTurno = !turno || t === turno;
            const matchSalon = !salon || s === salon;
            const show = matchTurno && matchSalon;
            tr.style.display = show ? '' : 'none';
        });
    }

    btnOpenFilters?.addEventListener('click', openFiltersModal);
    btnClearFilters?.addEventListener('click', function() {
        if (filterTurno) filterTurno.value = '';
        if (filterSalon) filterSalon.value = '';
        applyFilters();
        closeFiltersModal();
    });
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

    // Vincular selects de telar con su salón
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
    wireTelarSalon('createTelar', 'createSalon');
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

    // Validar formulario de creación antes de submit
    const createForm = document.querySelector('#createModal form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const empSel = document.getElementById('createEmpleado');
            const telarSel = document.getElementById('createTelar');
            const nombre = document.getElementById('createNombre');
            const turno = document.getElementById('createTurno');
            const salon = document.getElementById('createSalon');

            if (!empSel.value || !telarSel.value || !nombre.value || !turno.value || !salon.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Campos incompletos',
                    text: 'Por favor completa todos los campos requeridos'
                });
                return false;
            }

            // Validación: no permitir asignar el mismo telar al mismo usuario más de una vez
            const numeroEmpleado = empSel.value.trim();
            const noTelar = telarSel.value.trim();
            let existeDuplicado = false;
            document.querySelectorAll('.row-selectable').forEach(row => {
                const num = (row.dataset.numero || '').trim();
                const tel = (row.dataset.telar || '').trim();
                if (num === numeroEmpleado && tel === noTelar) {
                    existeDuplicado = true;
                }
            });
            if (existeDuplicado) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Asignación duplicada',
                    text: 'Este operador ya tiene asignado el telar seleccionado.'
                });
                return false;
            }
        });
    }
});
</script>
@endsection
