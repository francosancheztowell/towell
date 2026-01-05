@extends('layouts.app')

@section('title', 'Gestión de Módulos')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openModuloModal('createModal')" hoverBg="hover:bg-green-200" />
        <x-navbar.button-edit id="btn-top-edit" onclick="handleTopEdit('editModal')" :disabled="true" iconColor="text-blue-400" hoverBg="hover:bg-blue-200"/>
        <button id="btn-top-sync" type="button" onclick="handleSyncPermisos()" disabled
            class="p-2 rounded-full transition hover:bg-purple-100 disabled:opacity-50 disabled:cursor-not-allowed w-9 h-9 flex items-center justify-center"
            title="Sincronizar Permisos">
            <i class="fa-solid fa-sync text-purple-600 text-lg"></i>
        </button>
        <x-navbar.button-delete id="btn-top-delete" onclick="handleTopDelete()" :disabled="true" iconColor="text-red-500" hoverBg="hover:bg-red-200"/>
    </div>
@endsection

@section('content')
        <div class="container mx-auto px-4 py-6 te">
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
                <div class="overflow-x-auto ">
                    <table class="min-w-full text-sm">
                        <thead class="bg-blue-600 text-white sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold w-28">Orden</th>
                                <th class="px-4 py-3 text-left font-semibold">Módulo</th>
                                <th class="px-4 py-3 text-left font-semibold w-28">Nivel</th>
                                <th class="px-4 py-3 text-center font-semibold w-40">Dependencia</th>
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
                                    <td class="px-4 py-3 align-middle text-center text-gray-700">{{ $m->Dependencia ?? '—' }}</td>
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
        <div id="createModal" class="fixed inset-0 bg-gray-400 bg-opacity-10 hidden z-50 items-center justify-center">
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
                                <input type="text" id="createOrden" name="orden" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Se calculará automáticamente">
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
                                    <option value="">Seleccionar dependencia</option>
                                </select>
                                <p id="createDependenciaHelp" class="text-xs text-gray-500 mt-1">Selecciona primero el nivel</p>
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
                            <button type="button" onclick="closeModuloModal('createModal')" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
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
                                <input type="text" id="editOrden" name="orden" required readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none focus:ring-2 focus:ring-yellow-500">
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
                                    <option value="">Seleccionar dependencia</option>
                                </select>
                                <p id="editDependenciaHelp" class="text-xs text-gray-500 mt-1">Selecciona primero el nivel</p>
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
                            <button type="button" onclick="closeModuloModal('editModal')" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
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
            const modulosData = @json($modulos);

            let selectedRow = null;
            let selectedKey = null;

            // Función para poblar el select de dependencia según el nivel
            function poblarDependencias(selectId, nivel, helpId) {
                const select = document.getElementById(selectId);
                const help = document.getElementById(helpId);
                select.innerHTML = '<option value="">Seleccionar dependencia</option>';
                
                if (nivel === '1') {
                    help.textContent = 'Los módulos de Nivel 1 no tienen dependencia';
                    select.disabled = true;
                    return;
                }
                
                if (nivel === '2') {
                    // Para Nivel 2: mostrar solo módulos de Nivel 1
                    const nivel1 = modulosData.filter(m => String(m.Nivel) === '1');
                    if (nivel1.length === 0) {
                        help.textContent = 'No hay módulos de Nivel 1 disponibles';
                        select.disabled = true;
                        return;
                    }
                    nivel1.forEach(m => {
                        const option = document.createElement('option');
                        option.value = m.orden;
                        option.textContent = `${m.modulo} (${m.orden})`;
                        select.appendChild(option);
                    });
                    help.textContent = 'Selecciona el módulo principal (Nivel 1)';
                    select.disabled = false;
                    return;
                }
                
                if (nivel === '3') {
                    // Para Nivel 3: mostrar solo módulos de Nivel 2
                    const nivel2 = modulosData.filter(m => String(m.Nivel) === '2');
                    if (nivel2.length === 0) {
                        help.textContent = 'No hay submódulos de Nivel 2 disponibles';
                        select.disabled = true;
                        return;
                    }
                    nivel2.forEach(m => {
                        const option = document.createElement('option');
                        option.value = m.orden;
                        // Buscar el módulo padre para mostrarlo
                        const padre = modulosData.find(p => String(p.orden) === String(m.Dependencia));
                        const padreNombre = padre ? ` [${padre.modulo}]` : '';
                        option.textContent = `${m.modulo} (${m.orden})${padreNombre}`;
                        select.appendChild(option);
                    });
                    help.textContent = 'Selecciona el submódulo (Nivel 2) donde agregar este elemento';
                    select.disabled = false;
                    return;
                }
            }

            // Función para calcular el orden automáticamente
            function calcularOrden(nivel, dependencia) {
                if (!nivel) return '';
                
                nivel = String(nivel);
                
                // Nivel 1: Buscar el siguiente múltiplo de 100 disponible
                if (nivel === '1') {
                    let maxOrden = 0;
                    modulosData.forEach(m => {
                        if (String(m.Nivel) === '1') {
                            const orden = parseInt(m.orden);
                            if (!isNaN(orden) && orden > maxOrden) {
                                maxOrden = orden;
                            }
                        }
                    });
                    return maxOrden === 0 ? 100 : maxOrden + 100;
                }
                
                // Nivel 2 o 3: Requiere dependencia
                if (!dependencia) return '';
                
                // Nivel 2: orden = dependencia + 1
                if (nivel === '2') {
                    const depInt = parseInt(dependencia);
                    if (isNaN(depInt)) return '';
                    
                    // Buscar el siguiente orden disponible basado en la dependencia
                    let maxOrden = depInt;
                    modulosData.forEach(m => {
                        if (String(m.Dependencia) === String(dependencia) && String(m.Nivel) === '2') {
                            const orden = parseInt(m.orden);
                            if (!isNaN(orden) && orden > maxOrden) {
                                maxOrden = orden;
                            }
                        }
                    });
                    return maxOrden === depInt ? depInt + 1 : maxOrden + 1;
                }
                
                // Nivel 3: orden = dependencia-N (donde N es secuencial)
                if (nivel === '3') {
                    let maxSubOrden = 0;
                    const depStr = String(dependencia);
                    modulosData.forEach(m => {
                        if (String(m.Dependencia) === depStr && String(m.Nivel) === '3') {
                            const ordenStr = String(m.orden);
                            // Extraer el número después del guion (ej: "101-1" -> 1)
                            const match = ordenStr.match(/-([0-9]+)$/);
                            if (match) {
                                const subOrden = parseInt(match[1]);
                                if (subOrden > maxSubOrden) {
                                    maxSubOrden = subOrden;
                                }
                            }
                        }
                    });
                    return `${depStr}-${maxSubOrden + 1}`;
                }
                
                return '';
            }

            // Listeners para el modal de crear
            document.getElementById('createNivel').addEventListener('change', function() {
                const nivel = this.value;
                const dependenciaSelect = document.getElementById('createDependencia');
                const ordenInput = document.getElementById('createOrden');
                
                // Poblar el select de dependencia según el nivel
                poblarDependencias('createDependencia', nivel, 'createDependenciaHelp');
                
                if (nivel === '1') {
                    ordenInput.value = calcularOrden(nivel, null);
                } else {
                    ordenInput.value = '';
                }
            });

            document.getElementById('createDependencia').addEventListener('change', function() {
                const nivel = document.getElementById('createNivel').value;
                const dependencia = this.value;
                const ordenInput = document.getElementById('createOrden');
                ordenInput.value = calcularOrden(nivel, dependencia);
            });

            // Listeners para el modal de editar
            document.getElementById('editNivel').addEventListener('change', function() {
                const nivel = this.value;
                const dependenciaSelect = document.getElementById('editDependencia');
                const ordenInput = document.getElementById('editOrden');
                
                // Poblar el select de dependencia según el nivel
                poblarDependencias('editDependencia', nivel, 'editDependenciaHelp');
                
                if (nivel === '1') {
                    ordenInput.value = calcularOrden(nivel, null);
                } else {
                    ordenInput.value = '';
                }
            });

            document.getElementById('editDependencia').addEventListener('change', function() {
                const nivel = document.getElementById('editNivel').value;
                const dependencia = this.value;
                const ordenInput = document.getElementById('editOrden');
                ordenInput.value = calcularOrden(nivel, dependencia);
            });

            function updateTopButtonsState() {
                const btnEdit = document.getElementById('btn-top-edit');
                const btnDelete = document.getElementById('btn-top-delete');
                const btnSync = document.getElementById('btn-top-sync');
                const hasSelection = !!selectedKey;
                [btnEdit, btnDelete, btnSync].forEach(btn => {
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

            function openModuloModal(modalId) { const el=document.getElementById(modalId); el.classList.remove('hidden'); el.classList.add('flex'); document.body.style.overflow='hidden'; }
            function closeModuloModal(modalId) { const el=document.getElementById(modalId); el.classList.add('hidden'); el.classList.remove('flex'); document.body.style.overflow='auto'; }

            function handleTopEdit() {
                if (!selectedRow || !selectedKey) {
                    Swal.fire({icon: 'warning', title: 'Selecciona un módulo', text: 'Debes seleccionar un módulo de la tabla para editarlo', confirmButtonText: 'Entendido'});
                    return;
                }
                const nivel = selectedRow.dataset.nivel || '1';
                const dependencia = selectedRow.dataset.dependencia || '';
                
                document.getElementById('editForm').action = updateUrl.replace('PLACEHOLDER', encodeURIComponent(selectedKey));
                document.getElementById('editOrden').value = selectedRow.dataset.orden || '';
                document.getElementById('editModulo').value = selectedRow.dataset.modulo || '';
                document.getElementById('editNivel').value = nivel;
                
                // Poblar dependencias según el nivel y luego establecer el valor
                poblarDependencias('editDependencia', nivel, 'editDependenciaHelp');
                document.getElementById('editDependencia').value = dependencia;
                
                document.getElementById('edit_acceso').checked = (selectedRow.dataset.acceso === '1');
                document.getElementById('edit_crear').checked = (selectedRow.dataset.crear === '1');
                document.getElementById('edit_modificar').checked = (selectedRow.dataset.modificar === '1');
                document.getElementById('edit_eliminar').checked = (selectedRow.dataset.eliminar === '1');
                document.getElementById('edit_reigstrar').checked = (selectedRow.dataset.reigstrar === '1');
                openModuloModal('editModal');
            }

            function handleTopDelete() {
                if (!selectedKey) {
                    Swal.fire({icon: 'warning', title: 'Selecciona un módulo', text: 'Debes seleccionar un módulo de la tabla para eliminarlo', confirmButtonText: 'Entendido'});
                    return;
                }
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

            function handleSyncPermisos() {
                if (!selectedKey) {
                    Swal.fire({icon: 'warning', title: 'Selecciona un módulo', text: 'Debes seleccionar un módulo de la tabla para sincronizar sus permisos', confirmButtonText: 'Entendido'});
                    return;
                }
                
                Swal.fire({
                    title: '¿Sincronizar permisos?',
                    text: 'Se actualizarán los permisos de todos los usuarios para este módulo',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#8b5cf6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, sincronizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar loading
                        Swal.fire({
                            title: 'Sincronizando...',
                            text: 'Por favor espera',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        // Hacer petición AJAX
                        fetch(`{{ url('configuracion/utileria/modulos') }}/${selectedKey}/sincronizar-permisos`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Éxito',
                                    text: data.message,
                                    confirmButtonText: 'Aceptar',
                                    timer: 3000
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Error al sincronizar permisos',
                                    confirmButtonText: 'Aceptar'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error de conexión al sincronizar permisos',
                                confirmButtonText: 'Aceptar'
                            });
                            console.error('Error:', error);
                        });
                    }
                });
            }

            window.onclick = function(event) {
                if (event.target.id === 'createModal' || event.target.id === 'editModal') { closeModuloModal(event.target.id); }
            }
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') { closeModuloModal('createModal'); closeModuloModal('editModal'); }
            });

            updateTopButtonsState();
        </script>
@endsection