@extends('layouts.app')

@section('title', 'Gestión de Módulos')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button onclick="openModal('createModal')" class="p-2 rounded-lg transition hover:bg-green-100" title="Nuevo Módulo">
            <i class="fa-solid fa-plus text-green-600 text-lg"></i>
        </button>
        <button id="btn-top-edit" type="button"
            class="p-2 rounded-lg transition hover:bg-yellow-100 disabled:opacity-50 disabled:cursor-not-allowed"
            onclick="handleTopEdit()" disabled title="Editar Módulo">
            <i class="fa-solid fa-pen-to-square text-yellow-500 text-lg"></i>
        </button>
        <button id="btn-top-delete" type="button"
            class="p-2 rounded-lg transition hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed"
            onclick="handleTopDelete()" disabled title="Eliminar Módulo">
            <i class="fa-solid fa-trash text-red-600 text-lg"></i>
        </button>
    </div>
@endsection

@section('content')
        <div class="container mx-auto px-4 py-6">
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
                        confirmButtonText: 'Aceptar',
                        timer: 3000
                    });
                </script>
            @endif

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-blue-600 text-white sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold w-28">Orden</th>
                                <th class="px-4 py-3 text-left font-semibold">Módulo</th>
                                <th class="px-4 py-3 text-left font-semibold w-28">Nivel</th>
                                <th class="px-4 py-3 text-left font-semibold w-40">Dependencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($modulos as $m)
                                <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                                    data-key="{{ $m->idrol }}"
                                    data-orden="{{ e($m->orden) }}"
                                    data-modulo="{{ e($m->modulo) }}"
                                    data-nivel="{{ e($m->Nivel) }}"
                                    data-dependencia="{{ e($m->Dependencia) }}"
                                    data-acceso="{{ (int) $m->acceso }}"
                                    data-crear="{{ (int) $m->crear }}"
                                    data-modificar="{{ (int) $m->modificar }}"
                                    data-eliminar="{{ (int) $m->eliminar }}"
                                    data-reigstrar="{{ (int) $m->reigstrar }}"
                                    onclick="selectRow(this)"
                                    aria-selected="false">
                                    <td class="px-4 py-3 align-middle font-medium text-gray-700">{{ $m->orden }}</td>
                                    <td class="px-4 py-3 align-middle text-gray-800">{{ $m->modulo }}</td>
                                    <td class="px-4 py-3 align-middle text-gray-700">
                                        <span class="px-2 py-0.5 rounded-full text-xs {{ (string)$m->Nivel==='1' ? 'bg-blue-100 text-blue-800' : ((string)$m->Nivel==='2' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            Nivel {{ $m->Nivel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 align-middle text-gray-700">{{ $m->Dependencia ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fa-solid fa-inbox text-4xl mb-2 text-gray-300"></i>
                                        <p class="text-lg">No hay módulos registrados</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <form id="globalDeleteForm" action="#" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>

            <!-- Modal Crear -->
                    <!-- Modal Crear -->
        <div id="createModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 items-center justify-center">
                <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl mx-4 transform transition-all">
                    <div class="bg-gradient-to-r from-green-600 to-green-500 text-white px-6 py-4 rounded-t-lg">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus-circle"></i>
                            Nuevo Módulo
                        </h2>
                    </div>

                    <form action="{{ route('configuracion.utileria.modulos.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Orden <span class="text-red-500">*</span></label>
                                <input type="text" name="orden" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Ej: 100, 200, 201, 401-1">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre del Módulo <span class="text-red-500">*</span></label>
                                <input type="text" name="modulo" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Ej: Configuración, Tejido">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nivel <span class="text-red-500">*</span></label>
                                <select name="Nivel" id="createNivel" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <option value="">Seleccionar</option>
                                    <option value="1">Nivel 1 (Principal)</option>
                                    <option value="2">Nivel 2 (Submódulo)</option>
                                    <option value="3">Nivel 3 (Submódulo)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Dependencia</label>
                                <select name="Dependencia" id="createDependencia" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <option value="">Sin dependencia</option>
                                    @foreach(($modulosPrincipales ?? []) as $mp)
                                        <option value="{{ $mp->orden }}">{{ $mp->modulo }} ({{ $mp->orden }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4">
                            @php($permLabels = ['acceso' => 'Acceso','crear' => 'Crear','modificar' => 'Modificar','eliminar' => 'Eliminar','reigstrar' => 'Registrar'])
                            @foreach($permLabels as $name => $label)
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500" name="{{ $name }}" value="1" {{ $name==='acceso' ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm font-medium text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Imagen (opcional)</label>
                            <input type="file" name="imagen_archivo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF. Máximo: 2MB</p>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" onclick="closeModal('createModal')" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                                <i class="fa-solid fa-times mr-1"></i> Cancelar
                            </button>
                            <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                <i class="fa-solid fa-check mr-1"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Editar -->
                    <!-- Modal Editar -->
                <!-- Modal Editar -->
        <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 items-center justify-center">
                <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl mx-4 transform transition-all">
                    <div class="bg-gradient-to-r from-yellow-600 to-yellow-500 text-white px-6 py-4 rounded-t-lg">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i class="fa-solid fa-edit"></i>
                            Editar Módulo
                        </h2>
                    </div>

                    <form id="editForm" action="#" method="POST" enctype="multipart/form-data" class="p-6">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Orden <span class="text-red-500">*</span></label>
                                <input type="text" id="editOrden" name="orden" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre del Módulo <span class="text-red-500">*</span></label>
                                <input type="text" id="editModulo" name="modulo" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nivel <span class="text-red-500">*</span></label>
                                <select id="editNivel" name="Nivel" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    <option value="1">Nivel 1 (Principal)</option>
                                    <option value="2">Nivel 2 (Submódulo)</option>
                                    <option value="3">Nivel 3 (Submódulo)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Dependencia</label>
                                <select id="editDependencia" name="Dependencia" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    <option value="">Sin dependencia</option>
                                    @foreach(($modulosPrincipales ?? []) as $mp)
                                        <option value="{{ $mp->orden }}">{{ $mp->modulo }} ({{ $mp->orden }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4">
                            @php($permLabels = ['acceso' => 'Acceso','crear' => 'Crear','modificar' => 'Modificar','eliminar' => 'Eliminar','reigstrar' => 'Registrar'])
                            @foreach($permLabels as $name => $label)
                                <label class="flex items-center">
                                    <input type="checkbox" id="edit_{{ $name }}" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" name="{{ $name }}" value="1">
                                    <span class="ml-2 text-sm font-medium text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Reemplazar Imagen (opcional)</label>
                            <input type="file" name="imagen_archivo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" onclick="closeModal('editModal')" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                                <i class="fa-solid fa-times mr-1"></i> Cancelar
                            </button>
                            <button type="submit" class="px-5 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-medium">
                                <i class="fa-solid fa-save mr-1"></i> Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
            tbody tr { transition: all 0.15s ease; }
            tbody tr:hover { background-color: #eff6ff !important; }
            tbody tr[aria-selected="true"] { background-color: #dbeafe !important; box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.5); }
            tbody tr[aria-selected="true"] td:first-child { border-left: 4px solid #3b82f6; }
            @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95);} to { opacity: 1; transform: scale(1);} }
            #createModal > div, #editModal > div { animation: modalFadeIn 0.2s ease-out; }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            const updateUrl = @json(route('configuracion.utileria.modulos.update', 'PLACEHOLDER'));
            const destroyUrl = @json(route('configuracion.utileria.modulos.destroy', 'PLACEHOLDER'));

            let selectedRow = null;
            let selectedKey = null;

            function updateTopButtonsState() {
                const btnEdit = document.getElementById('btn-top-edit');
                const btnDelete = document.getElementById('btn-top-delete');
                const hasSelection = !!selectedKey;
                [btnEdit, btnDelete].forEach(btn => {
                    if (!btn) return;
                    if (hasSelection) { btn.removeAttribute('disabled'); btn.classList.remove('opacity-50','cursor-not-allowed'); }
                    else { btn.setAttribute('disabled','disabled'); btn.classList.add('opacity-50','cursor-not-allowed'); }
                });
            }

            function clearSelection() {
                if (selectedRow) { selectedRow.setAttribute('aria-selected','false'); }
                selectedRow = null; selectedKey = null; updateTopButtonsState();
            }

            function selectRow(row) {
                if (selectedRow === row) { clearSelection(); return; }
                if (selectedRow) { selectedRow.setAttribute('aria-selected','false'); }
                selectedRow = row; selectedKey = row.dataset.key || null; row.setAttribute('aria-selected','true'); updateTopButtonsState();
            }

            function openModal(modalId) { const el=document.getElementById(modalId); el.classList.remove('hidden'); el.classList.add('flex'); document.body.style.overflow='hidden'; }
            function closeModal(modalId) { const el=document.getElementById(modalId); el.classList.add('hidden'); el.classList.remove('flex'); document.body.style.overflow='auto'; }

            function handleTopEdit() {
                if (!selectedRow || !selectedKey) return;
                document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(selectedKey));
                document.getElementById('editOrden').value = selectedRow.dataset.orden || '';
                document.getElementById('editModulo').value = selectedRow.dataset.modulo || '';
                document.getElementById('editNivel').value = selectedRow.dataset.nivel || '1';
                document.getElementById('editDependencia').value = selectedRow.dataset.dependencia || '';
                document.getElementById('edit_acceso').checked = (selectedRow.dataset.acceso === '1');
                document.getElementById('edit_crear').checked = (selectedRow.dataset.crear === '1');
                document.getElementById('edit_modificar').checked = (selectedRow.dataset.modificar === '1');
                document.getElementById('edit_eliminar').checked = (selectedRow.dataset.eliminar === '1');
                document.getElementById('edit_reigstrar').checked = (selectedRow.dataset.reigstrar === '1');
                openModal('editModal');
            }

            function handleTopDelete() {
                if (!selectedKey) return;
                Swal.fire({
                    title: '¿Eliminar módulo?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.getElementById('globalDeleteForm');
                        form.action = destroyUrl.replace('PLACEHOLDER', encodeURIComponent(selectedKey));
                        form.submit();
                    }
                });
            }

            window.onclick = function(event) {
                if (event.target.id === 'createModal' || event.target.id === 'editModal') { closeModal(event.target.id); }
            }
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') { closeModal('createModal'); closeModal('editModal'); }
            });

            updateTopButtonsState();
        </script>
@endsection