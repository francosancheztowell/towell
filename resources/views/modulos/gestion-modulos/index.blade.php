@extends('layouts.app')

@section('title', 'Gestión de Módulos')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <flux:button variant="primary" icon="plus" class="min-h-touch" onclick="openModuloModal('createModal')">Nuevo</flux:button>
        <flux:button id="btn-top-edit" icon="pencil-square" class="min-h-touch" onclick="handleTopEdit('editModal')" disabled>Editar</flux:button>
        <flux:button id="btn-top-sync" icon="arrow-path" class="min-h-touch" onclick="handleSyncPermisos()" disabled>Sincronizar permisos</flux:button>
        <flux:button id="btn-top-delete" variant="danger" icon="trash" class="min-h-touch" onclick="handleTopDelete()" disabled>Eliminar</flux:button>
    </div>
@endsection

@section('content')
        <div class="container mx-auto px-4 py-6 te">
            {{-- Éxito, error y validación los pinta x-ui.flash (layout). Un <script> inline aquí
                 corría antes que app.js (módulo diferido): notify no existía y, como el texto ya
                 estaba en la vista, el flash tampoco lo repetía, así que no salía ningún aviso. --}}

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
                                    data-ruta="{{ e($m->Ruta) }}"
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

            {{-- <dialog> nativo con el aspecto de los demás diálogos (.ui-dialogo, utils/dialogo.ts).
                 Esc, foco y fondo los da el navegador; Cancelar cierra con formmethod="dialog". --}}
            <dialog id="createModal" data-dialog-nativo aria-labelledby="createModal-titulo"
                    class="ui-dialogo ui-dialogo--xl ui-dialogo--formulario">
                <form action="{{ route('configuracion.utileria.modulos.store') }}" method="POST" enctype="multipart/form-data" class="ui-dialogo__cuerpo">
                    <h2 id="createModal-titulo" class="ui-dialogo__titulo">Nuevo módulo</h2>
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1">Orden <span class="text-red-500">*</span></label>
                                <input type="text" id="createOrden" name="orden" required readonly class="bg-zinc-100" placeholder="Se calculará automáticamente">
                            </div>
                            <div>
                                <label class="block mb-1">Nombre del Módulo <span class="text-red-500">*</span></label>
                                <input type="text" name="modulo" required autofocus placeholder="Ej: Configuración, Tejido">
                            </div>
                            <div>
                                <label class="block mb-1">Nivel <span class="text-red-500">*</span></label>
                                <select name="Nivel" id="createNivel" required>
                                    <option value="">Seleccionar</option>
                                    <option value="1">Nivel 1 (Principal)</option>
                                    <option value="2">Nivel 2 (Submódulo)</option>
                                    <option value="3">Nivel 3 (Submódulo)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1">Dependencia</label>
                                <select name="Dependencia" id="createDependencia">
                                    <option value="">Seleccionar dependencia</option>
                                </select>
                                <p id="createDependenciaHelp" class="text-xs text-gray-500 mt-1">Selecciona primero el nivel</p>
                            </div>
                            {{-- Sin Ruta, moduleNameForRoute() no puede resolver el modulo y los
                                 permisos quedan obligados a buscarse por nombre, que esta repetido
                                 en SYSRoles. Asi nacio el bug de Utileria (188 sin Ruta vs 67). --}}
                            <div class="md:col-span-2">
                                <label class="block mb-1">Ruta</label>
                                <input type="text" name="Ruta" id="createRuta" placeholder="Ej: /tejido/invtelas">
                                <p class="text-xs text-gray-500 mt-1">URL de la pantalla, empezando con <code>/</code>. Sin ella el módulo no se puede resolver por ruta y los permisos dependen del nombre.</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4">
                            @php($permLabels = ['acceso' => 'Acceso','crear' => 'Crear','modificar' => 'Modificar','eliminar' => 'Eliminar','reigstrar' => 'Registrar'])
                            @foreach($permLabels as $name => $label)
                                <label class="flex items-center min-h-touch">
                                    <input type="checkbox" name="{{ $name }}" value="1" {{ $name==='acceso' ? 'checked' : '' }}>
                                    <span class="ml-2">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label class="block mb-1">Imagen (opcional)</label>
                            <input type="file" name="imagen_archivo" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:min-h-touch file:rounded-lg file:border file:border-zinc-200 file:bg-white file:px-4 file:font-medium file:text-zinc-800 hover:file:bg-zinc-50">
                            <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF. Máximo: 2MB</p>
                        </div>

                        <div class="ui-dialogo__botones">
                            <button type="submit" formmethod="dialog" formnovalidate class="ui-dialogo__boton ui-dialogo__boton--secundario">Cancelar</button>
                            <button type="submit" class="ui-dialogo__boton ui-dialogo__boton--primario">Guardar</button>
                        </div>
                </form>
            </dialog>

            {{-- <dialog> nativo con el aspecto de los demás diálogos (.ui-dialogo, utils/dialogo.ts).
                 Esc, foco y fondo los da el navegador; Cancelar cierra con formmethod="dialog". --}}
            <dialog id="editModal" data-dialog-nativo aria-labelledby="editModal-titulo"
                    class="ui-dialogo ui-dialogo--xl ui-dialogo--formulario">
                <form id="editForm" action="#" method="POST" enctype="multipart/form-data" class="ui-dialogo__cuerpo">
                    <h2 id="editModal-titulo" class="ui-dialogo__titulo">Editar módulo</h2>
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1">Orden <span class="text-red-500">*</span></label>
                                <input type="text" id="editOrden" name="orden" required readonly class="bg-zinc-100">
                            </div>
                            <div>
                                <label class="block mb-1">Nombre del Módulo <span class="text-red-500">*</span></label>
                                <input type="text" id="editModulo" name="modulo" required autofocus>
                            </div>
                            <div>
                                <label class="block mb-1">Nivel <span class="text-red-500">*</span></label>
                                <select id="editNivel" name="Nivel" required>
                                    <option value="1">Nivel 1 (Principal)</option>
                                    <option value="2">Nivel 2 (Submódulo)</option>
                                    <option value="3">Nivel 3 (Submódulo)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1">Dependencia</label>
                                <select id="editDependencia" name="Dependencia">
                                    <option value="">Seleccionar dependencia</option>
                                </select>
                                <p id="editDependenciaHelp" class="text-xs text-gray-500 mt-1">Selecciona primero el nivel</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block mb-1">Ruta</label>
                                <input type="text" id="editRuta" name="Ruta" placeholder="Ej: /tejido/invtelas">
                                <p class="text-xs text-gray-500 mt-1">URL de la pantalla, empezando con <code>/</code>. Los módulos sin ruta no se pueden resolver con <code>moduleNameForRoute()</code>.</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4">
                            @php($permLabels = ['acceso' => 'Acceso','crear' => 'Crear','modificar' => 'Modificar','eliminar' => 'Eliminar','reigstrar' => 'Registrar'])
                            @foreach($permLabels as $name => $label)
                                <label class="flex items-center min-h-touch">
                                    <input type="checkbox" id="edit_{{ $name }}" name="{{ $name }}" value="1">
                                    <span class="ml-2">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label class="block mb-1">Reemplazar Imagen (opcional)</label>
                            <input type="file" name="imagen_archivo" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:min-h-touch file:rounded-lg file:border file:border-zinc-200 file:bg-white file:px-4 file:font-medium file:text-zinc-800 hover:file:bg-zinc-50">
                        </div>

                        <div class="ui-dialogo__botones">
                            <button type="submit" formmethod="dialog" formnovalidate class="ui-dialogo__boton ui-dialogo__boton--secundario">Cancelar</button>
                            <button type="submit" class="ui-dialogo__boton ui-dialogo__boton--primario">Actualizar</button>
                        </div>
                </form>
            </dialog>

        <style>
            tbody tr { transition: all 0.15s ease; }
            tbody tr:hover { background-color: #eff6ff !important; }
            tbody tr[aria-selected="true"] { background-color: #dbeafe !important; box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.5); }
            tbody tr[aria-selected="true"] td:first-child { border-left: 4px solid #3b82f6; }
        </style>

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

            function openModuloModal(modalId) { document.getElementById(modalId).showModal(); }

            function handleTopEdit() {
                if (!selectedRow || !selectedKey) {
                    notify.alert('Debes seleccionar un módulo de la tabla para editarlo', 'Selecciona un módulo', 'warning');
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
                document.getElementById('editRuta').value = selectedRow.dataset.ruta || '';

                document.getElementById('edit_acceso').checked = (selectedRow.dataset.acceso === '1');
                document.getElementById('edit_crear').checked = (selectedRow.dataset.crear === '1');
                document.getElementById('edit_modificar').checked = (selectedRow.dataset.modificar === '1');
                document.getElementById('edit_eliminar').checked = (selectedRow.dataset.eliminar === '1');
                document.getElementById('edit_reigstrar').checked = (selectedRow.dataset.reigstrar === '1');
                openModuloModal('editModal');
            }

            function handleTopDelete() {
                if (!selectedKey) {
                    notify.alert('Debes seleccionar un módulo de la tabla para eliminarlo', 'Selecciona un módulo', 'warning');
                    return;
                }
                notify.confirm({
                    title: '¿Eliminar módulo?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    confirmColor: '#dc2626',
                    confirmText: 'Sí, eliminar', cancelText: 'Cancelar'
                }).then((ok) => {
                    if (ok) {
                        const form = document.getElementById('globalDeleteForm');
                        form.action = destroyUrl.replace('PLACEHOLDER', encodeURIComponent(selectedKey));
                        form.submit();
                    }
                });
            }

            function handleSyncPermisos() {
                if (!selectedKey) {
                    notify.alert('Debes seleccionar un módulo de la tabla para sincronizar sus permisos', 'Selecciona un módulo', 'warning');
                    return;
                }

                notify.confirm({
                    title: '¿Sincronizar permisos?',
                    text: 'Se actualizarán los permisos de todos los usuarios para este módulo',
                    icon: 'question',
                    confirmColor: '#8b5cf6',
                    confirmText: 'Sí, sincronizar',
                    cancelText: 'Cancelar'
                }).then((ok) => {
                    if (ok) {
                        // Mostrar loading
                        notify.loading('Sincronizando...');

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
                            notify.close();
                            if (data.success) {
                                notify.success(data.message);
                            } else {
                                notify.alert(data.message || 'Error al sincronizar permisos', 'Error', 'error');
                            }
                        })
                        .catch(error => {
                            notify.close();
                            notify.alert('Error de conexión al sincronizar permisos', 'Error', 'error');
                            console.error('Error:', error);
                        });
                    }
                });
            }

            updateTopButtonsState();
        </script>
@endsection
