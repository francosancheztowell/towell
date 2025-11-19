@extends('layouts.app')

@section('title', 'Gestión de Módulos')

@section('navbar-right')
    <x-navbar.button-create onclick="abrirModalCrearModulo()" title="Crear Módulo" />
    <x-navbar.button-edit id="btnEditarSeleccionado" onclick="editarModuloSeleccionado()" title="Editar" />
    <x-navbar.button-delete id="btnEliminarSeleccionado" onclick="eliminarModulosSeleccionados()" title="Eliminar" />
@endsection

@section('content')
<div class="w-full h-full flex flex-col overflow-hidden">
    @php
        function organizarModulos($modulos) {
            $modulosPorDependencia = [];
            foreach($modulos as $modulo) {
                $dependencia = $modulo->Dependencia ?? 'sin_dependencia';
                $modulosPorDependencia[$dependencia][] = $modulo;
            }
            $resultado = [];
            function organizarRecursivo($dependencia, $modulosPorDependencia, &$resultado) {
                if(isset($modulosPorDependencia[$dependencia])) {
                    foreach($modulosPorDependencia[$dependencia] as $modulo) {
                        $resultado[] = $modulo;
                        organizarRecursivo($modulo->orden, $modulosPorDependencia, $resultado);
                    }
                }
            }
            organizarRecursivo('sin_dependencia', $modulosPorDependencia, $resultado);
            return $resultado;
        }

        $modulosOrganizados = organizarModulos($modulos);

        function obtenerClaseFila($nivel) {
            return match($nivel) {
                1 => 'bg-blue-50',
                2 => 'bg-green-50',
                3 => 'bg-yellow-50',
                default => ''
            };
        }

        function obtenerBadgeClasses($nivel) {
            return match($nivel) {
                1 => 'bg-blue-100 text-blue-800',
                2 => 'bg-green-100 text-green-800',
                3 => 'bg-yellow-100 text-yellow-800',
                default => 'bg-gray-100 text-gray-800'
            };
        }
    @endphp

    @if($modulos->count() > 0)
        <div class="flex-1 flex flex-col overflow-hidden bg-white">
            <div class="flex-1 overflow-y-auto overflow-x-auto">
                <table class="w-full divide-y divide-gray-200" id="tablaModulos">
                    <thead class="bg-blue-500">
                        <tr>
                            <th class="sticky top-0 z-10 px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-12 bg-blue-500 border-b-2 border-blue-700 shadow-sm">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-16 bg-blue-500 border-b-2 border-blue-700 shadow-sm">Imagen</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">ID</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Orden</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Módulo</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Acceso</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Crear</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Modificar</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Eliminar</th>
                            <th class="sticky top-0 z-10 px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider bg-blue-500 border-b-2 border-blue-700 shadow-sm">Registrar</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($modulosOrganizados as $modulo)
                            @php
                                $indentacion = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $modulo->Nivel - 1);
                                $claseFila = obtenerClaseFila($modulo->Nivel);
                                $badgeClasses = obtenerBadgeClasses($modulo->Nivel);
                            @endphp
                            <tr class="cursor-pointer transition-colors duration-200 hover:bg-gray-50 {{ $claseFila }}"
                                data-nivel="{{ $modulo->Nivel }}"
                                data-acceso="{{ $modulo->acceso }}"
                                data-nombre="{{ strtolower($modulo->modulo) }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 modulo-checkbox"
                                           value="{{ $modulo->idrol }}"
                                           data-id="{{ $modulo->idrol }}">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($modulo->imagen)
                                        <img src="{{ asset('images/fotos_modulos/' . $modulo->imagen) . '?v=' . time() }}"
                                             alt="{{ $modulo->modulo }}"
                                             class="w-8 h-8 object-cover rounded-lg border border-gray-200"
                                             title="{{ $modulo->modulo }}">
                                    @else
                                        <div class="w-8 h-8 bg-gray-200 rounded-lg border border-gray-200 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $modulo->idrol }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                        {{ $modulo->orden }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        {!! $indentacion !!}
                                        <span class="text-sm font-medium text-gray-900">{{ $modulo->modulo }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                               class="toggle-acceso rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               data-id="{{ $modulo->idrol }}"
                                               {{ $modulo->acceso ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                               class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               data-id="{{ $modulo->idrol }}"
                                               data-campo="crear"
                                               {{ $modulo->crear ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                               class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               data-id="{{ $modulo->idrol }}"
                                               data-campo="modificar"
                                               {{ $modulo->modificar ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                               class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               data-id="{{ $modulo->idrol }}"
                                               data-campo="eliminar"
                                               {{ $modulo->eliminar ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                               class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               data-id="{{ $modulo->idrol }}"
                                               data-campo="reigstrar"
                                               {{ $modulo->reigstrar ? 'checked' : '' }}>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Estado vacío --}}
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay módulos registrados</h3>
                <p class="mt-1 text-sm text-gray-500">Comienza creando tu primer módulo</p>
                <div class="mt-6">
                    <a href="{{ route('modulos.sin.auth.create') }}"
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Crear Primer Módulo
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Modal Crear Módulo --}}
<div id="modalCrearModulo" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
            <h3 class="text-lg font-semibold">Crear Nuevo Módulo</h3>
            <button onclick="cerrarModalCrearModulo()" class="text-white hover:text-gray-200 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Form -->
        <form id="formCrearModulo" action="{{ route('modulos.sin.auth.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf

            <!-- Campos principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Orden -->
                <div>
                    <label for="modal_orden" class="block text-sm font-medium text-gray-700 mb-1">
                        Orden <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           id="modal_orden"
                           name="orden"
                           required
                           placeholder="Ej: 600, 601">
                    <p class="text-xs text-gray-500 mt-1">Debe ser único</p>
                </div>

                <!-- Nombre del Módulo -->
                <div>
                    <label for="modal_modulo" class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre del Módulo <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           id="modal_modulo"
                           name="modulo"
                           required
                           placeholder="Ej: Nuevo Módulo">
                </div>
            </div>

            <!-- Sistema de Selección Jerárquica -->
            <div class="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Ubicación en la Jerarquía</h4>
                
                <!-- Nivel 1: Selección de tipo de módulo -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Select Nivel -->
                    <div>
                        <label for="modal_nivel_select" class="block text-sm font-medium text-gray-700 mb-1">
                            1. Tipo de Módulo <span class="text-red-500">*</span>
                        </label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                id="modal_nivel_select"
                                required
                                onchange="cambiarNivelModulo()">
                            <option value="">Seleccionar tipo</option>
                            <option value="1">Módulo Principal (Nivel 1)</option>
                            <option value="2">Submódulo (Nivel 2)</option>
                            <option value="3">Sub-submódulo (Nivel 3)</option>
                        </select>
                    </div>

                    <!-- Select Módulo Nivel 1 (Solo visible para niveles 2 y 3) -->
                    <div id="container_modulo_nivel1" class="hidden">
                        <label for="modal_modulo_nivel1" class="block text-sm font-medium text-gray-700 mb-1">
                            2. Módulo Principal <span class="text-red-500">*</span>
                        </label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                id="modal_modulo_nivel1"
                                onchange="cargarModulosNivel2()">
                            <option value="">Seleccionar módulo principal</option>
                            @foreach($modulos->where('Nivel', 1)->whereNull('Dependencia')->sortBy('orden') as $moduloPrincipal)
                                <option value="{{ $moduloPrincipal->orden }}" data-nombre="{{ $moduloPrincipal->modulo }}">
                                    {{ $moduloPrincipal->modulo }} ({{ $moduloPrincipal->orden }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Select Módulo Nivel 2 (Solo visible para nivel 3) -->
                    <div id="container_modulo_nivel2" class="hidden">
                        <label for="modal_modulo_nivel2" class="block text-sm font-medium text-gray-700 mb-1">
                            3. Submódulo <span class="text-red-500">*</span>
                        </label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                id="modal_modulo_nivel2"
                                onchange="establecerDependenciaFinal()">
                            <option value="">Seleccionar submódulo</option>
                        </select>
                    </div>
                </div>

                <!-- Información de ubicación -->
                <div id="info_ubicacion" class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-sm text-blue-700 hidden">
                    <strong>Ubicación:</strong> <span id="texto_ubicacion"></span>
                </div>
            </div>

            <!-- Campos ocultos para el formulario -->
            <input type="hidden" id="modal_Nivel" name="Nivel">
            <input type="hidden" id="modal_Dependencia" name="Dependencia">

            <!-- Permisos -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Permisos del Módulo</label>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               name="acceso"
                               value="1"
                               checked>
                        <span class="text-sm text-gray-700">Acceso</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               name="crear"
                               value="1">
                        <span class="text-sm text-gray-700">Crear</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               name="modificar"
                               value="1">
                        <span class="text-sm text-gray-700">Modificar</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               name="eliminar"
                               value="1">
                        <span class="text-sm text-gray-700">Eliminar</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               name="reigstrar"
                               value="1">
                        <span class="text-sm text-gray-700">Registrar</span>
                    </label>
                </div>
            </div>

            <!-- Imagen -->
            <div class="mb-4">
                <label for="modal_imagen_archivo" class="block text-sm font-medium text-gray-700 mb-1">
                    Imagen del Módulo (Opcional)
                </label>
                
                <!-- Preview -->
                <div id="modal_imagen_preview" class="hidden mb-3">
                    <div class="flex items-center space-x-3">
                        <img id="modal_preview_img"
                             alt="Vista previa"
                             class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Vista previa</p>
                            <p id="modal_preview_filename" class="text-xs text-gray-500"></p>
                        </div>
                    </div>
                </div>

                <input type="file"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       id="modal_imagen_archivo"
                       name="imagen_archivo"
                       accept="image/*"
                       onchange="previsualizarImagen(this)">
                <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF. Máximo: 2MB</p>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button"
                        onclick="cerrarModalCrearModulo()"
                        class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <button type="button"
                        onclick="confirmarCreacionModal()"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    Crear Módulo
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Scripts --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function() {
    'use strict';

    // ==================== CONSTANTES ====================
    const SELECTORS = {
        selectAll: '#selectAll',
        moduloCheckbox: '.modulo-checkbox',
        toggleAcceso: '.toggle-acceso',
        togglePermiso: '.toggle-permiso',
        tablaModulos: '#tablaModulos',
        btnEditar: '#btnEditarSeleccionado',
        btnEliminar: '#btnEliminarSeleccionado'
    };

    const RUTAS = {
        toggleAcceso: (id) => `/configuracion/utileria/modulos/${id}/toggle-acceso`,
        togglePermiso: (id) => `/configuracion/utileria/modulos/${id}/toggle-permiso`,
        eliminar: (id) => `/modulos-sin-auth/${id}`,
        editar: (id) => `/modulos-sin-auth/${id}/edit`
    };

    // ==================== UTILIDADES ====================
    const Utils = {
        getCSRFToken() {
            return $('meta[name="csrf-token"]').attr('content');
        },

        mostrarAlerta(icono, titulo, texto, opciones = {}) {
            return Swal.fire({
                icon: icono,
                title: titulo,
                text: texto,
                timer: opciones.timer || null,
                showConfirmButton: opciones.showConfirmButton !== false,
                toast: opciones.toast || false,
                position: opciones.position || 'center',
                ...opciones
            });
        },

        revertirToggle(selector, valor) {
            $(selector).prop('checked', !valor);
        }
    };

    // ==================== GESTIÓN DE TOGGLES ====================
    const ToggleManager = {
        async cambiarAcceso(id, acceso) {
            try {
                const response = await $.ajax({
                    url: RUTAS.toggleAcceso(id),
                    method: 'POST',
                    data: { _token: Utils.getCSRFToken() }
                });

                if (response.success) {
                    Utils.mostrarAlerta('success', 'Éxito', response.message, { timer: 2000, showConfirmButton: false });
                } else {
                    Utils.mostrarAlerta('error', 'Error', response.message);
                    Utils.revertirToggle(`${SELECTORS.toggleAcceso}[data-id="${id}"]`, acceso);
                }
            } catch (error) {
                Utils.mostrarAlerta('error', 'Error', 'Error al cambiar el estado de acceso');
                Utils.revertirToggle(`${SELECTORS.toggleAcceso}[data-id="${id}"]`, acceso);
            }
        },

        async cambiarPermiso(id, campo, valor) {
            try {
                const response = await $.ajax({
                    url: RUTAS.togglePermiso(id),
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': Utils.getCSRFToken(),
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        _token: Utils.getCSRFToken(),
                        campo: campo,
                        valor: valor ? 1 : 0
                    })
                });

                if (response.success) {
                    Utils.mostrarAlerta('success', 'Éxito', response.message, { timer: 2000, showConfirmButton: false });
                } else {
                    Utils.mostrarAlerta('error', 'Error', response.message);
                    Utils.revertirToggle(`${SELECTORS.togglePermiso}[data-id="${id}"][data-campo="${campo}"]`, valor);
                }
            } catch (xhr) {
                const mensaje = xhr.responseJSON?.message || 'Error al cambiar el permiso';
                Utils.mostrarAlerta('error', 'Error', `Error al cambiar el permiso: ${mensaje}`);
                Utils.revertirToggle(`${SELECTORS.togglePermiso}[data-id="${id}"][data-campo="${campo}"]`, valor);
            }
        }
    };

    // ==================== GESTIÓN DE SELECCIÓN ====================
    const SelectionManager = {
        actualizarSelectAll() {
            const total = $(SELECTORS.moduloCheckbox).length;
            const checked = $(SELECTORS.moduloCheckbox + ':checked').length;
            const selectAll = $(SELECTORS.selectAll);

            selectAll.prop('checked', total === checked);
            selectAll.prop('indeterminate', checked > 0 && checked < total);
        },

        actualizarFilasSeleccionadas() {
            $(SELECTORS.tablaModulos + ' tbody tr').each(function() {
                const checkbox = $(this).find(SELECTORS.moduloCheckbox);
                const fila = $(this);

                if (checkbox.is(':checked')) {
                    fila.addClass('bg-blue-100 border-l-4 border-blue-500');
                } else {
                    fila.removeClass('bg-blue-100 border-l-4 border-blue-500');
                }
            });
        },

        toggleFila(fila) {
            const checkbox = fila.find(SELECTORS.moduloCheckbox);
            checkbox.prop('checked', !checkbox.prop('checked'));
            this.actualizarSelectAll();
            ButtonManager.actualizarEstado();
            this.actualizarFilasSeleccionadas();
        }
    };

    // ==================== GESTIÓN DE BOTONES ====================
    const ButtonManager = {
        actualizarEstado() {
            const checkedBoxes = $(SELECTORS.moduloCheckbox + ':checked');
            const count = checkedBoxes.length;
            const btnEditar = document.querySelector(SELECTORS.btnEditar);
            const btnEliminar = document.querySelector(SELECTORS.btnEliminar);

            if (!btnEditar || !btnEliminar) return;

            if (count === 0) {
                btnEditar.disabled = true;
                btnEliminar.disabled = true;
            } else if (count === 1) {
                btnEditar.disabled = false;
                btnEliminar.disabled = false;
            } else {
                btnEditar.disabled = true;
                btnEliminar.disabled = false;
            }
        }
    };

    // ==================== GESTIÓN DE ELIMINACIÓN ====================
    const DeleteManager = {
        async eliminarModulos(ids) {
            let eliminados = 0;
            let errores = 0;

            Utils.mostrarAlerta('info', 'Eliminando...', 'Por favor espera', {
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const promesas = ids.map(id => this.eliminarModulo(id));

            try {
                const resultados = await Promise.allSettled(promesas);

                resultados.forEach(resultado => {
                    if (resultado.status === 'fulfilled' && resultado.value) {
                        eliminados++;
                    } else {
                        errores++;
                    }
                });

                Swal.close();
                this.mostrarResultado(eliminados, errores);
            } catch (error) {
                Swal.close();
                Utils.mostrarAlerta('error', 'Error', 'Error al eliminar los módulos');
            }
        },

        eliminarModulo(id) {
            return $.ajax({
                url: RUTAS.eliminar(id),
                method: 'DELETE',
                data: { _token: Utils.getCSRFToken() }
            }).then(response => response.success);
        },

        mostrarResultado(eliminados, errores) {
            if (errores === 0) {
                Utils.mostrarAlerta('success', 'Éxito', `${eliminados} módulo(s) eliminado(s) correctamente`, {
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => window.location.href = '/produccionProceso');
            } else if (eliminados > 0) {
                Utils.mostrarAlerta('warning', 'Parcialmente completado', `${eliminados} módulo(s) eliminado(s), ${errores} error(es)`)
                    .then(() => window.location.href = '/produccionProceso');
            } else {
                Utils.mostrarAlerta('error', 'Error', 'Error al eliminar los módulos');
            }
        }
    };

    // ==================== FUNCIONES GLOBALES ====================
    
    // Función para abrir el modal de crear módulo
    window.abrirModalCrearModulo = function() {
        document.getElementById('modalCrearModulo').classList.remove('hidden');
        document.getElementById('formCrearModulo').reset();
        document.getElementById('modal_imagen_preview').classList.add('hidden');
        
        // Resetear sistema jerárquico
        resetearSistemaJerarquico();
    };

    // Función para cerrar el modal
    window.cerrarModalCrearModulo = function() {
        document.getElementById('modalCrearModulo').classList.add('hidden');
        document.getElementById('formCrearModulo').reset();
        document.getElementById('modal_imagen_preview').classList.add('hidden');
        resetearSistemaJerarquico();
    };

    // Resetear sistema jerárquico
    function resetearSistemaJerarquico() {
        // Ocultar contenedores
        document.getElementById('container_modulo_nivel1').classList.add('hidden');
        document.getElementById('container_modulo_nivel2').classList.add('hidden');
        document.getElementById('info_ubicacion').classList.add('hidden');
        
        // Resetear selects
        document.getElementById('modal_nivel_select').value = '';
        document.getElementById('modal_modulo_nivel1').value = '';
        document.getElementById('modal_modulo_nivel2').value = '';
        
        // Limpiar campos ocultos
        document.getElementById('modal_Nivel').value = '';
        document.getElementById('modal_Dependencia').value = '';
        
        // Limpiar opciones dinámicas
        const nivel2Select = document.getElementById('modal_modulo_nivel2');
        nivel2Select.innerHTML = '<option value="">Seleccionar submódulo</option>';
    }

    // Cambio de nivel principal
    window.cambiarNivelModulo = function() {
        const nivel = document.getElementById('modal_nivel_select').value;
        
        // Resetear estado
        resetearSistemaJerarquico();
        
        // Establecer nivel en campo oculto
        document.getElementById('modal_Nivel').value = nivel;
        
        if (nivel === '1') {
            // Nivel 1 - No necesita dependencia
            document.getElementById('modal_Dependencia').value = '';
            mostrarUbicacion('Módulo Principal (sin dependencia)');
            
        } else if (nivel === '2') {
            // Nivel 2 - Mostrar selector de módulos nivel 1
            document.getElementById('container_modulo_nivel1').classList.remove('hidden');
            document.getElementById('info_ubicacion').classList.add('hidden');
            
        } else if (nivel === '3') {
            // Nivel 3 - Mostrar selectores de nivel 1 y 2
            document.getElementById('container_modulo_nivel1').classList.remove('hidden');
            document.getElementById('info_ubicacion').classList.add('hidden');
        }
    };

    // Cargar módulos de nivel 2 cuando se selecciona un módulo de nivel 1
    window.cargarModulosNivel2 = function() {
        const nivelSeleccionado = document.getElementById('modal_nivel_select').value;
        const moduloNivel1 = document.getElementById('modal_modulo_nivel1');
        const moduloNivel1Valor = moduloNivel1.value;
        const moduloNivel1Nombre = moduloNivel1.options[moduloNivel1.selectedIndex]?.getAttribute('data-nombre') || '';
        
        if (nivelSeleccionado === '2') {
            // Para nivel 2, establecer dependencia directamente
            document.getElementById('modal_Dependencia').value = moduloNivel1Valor;
            
            if (moduloNivel1Valor) {
                mostrarUbicacion(`${moduloNivel1Nombre} > [Nuevo Submódulo]`);
            }
            
        } else if (nivelSeleccionado === '3') {
            // Para nivel 3, cargar submódulos disponibles
            if (moduloNivel1Valor) {
                cargarSubmodolosDisponibles(moduloNivel1Valor);
                document.getElementById('container_modulo_nivel2').classList.remove('hidden');
            } else {
                document.getElementById('container_modulo_nivel2').classList.add('hidden');
                document.getElementById('modal_modulo_nivel2').innerHTML = '<option value="">Seleccionar submódulo</option>';
            }
        }
    };

    // Cargar submódulos disponibles para el nivel 3
    function cargarSubmodolosDisponibles(ordenPadre) {
        const nivel2Select = document.getElementById('modal_modulo_nivel2');
        nivel2Select.innerHTML = '<option value="">Seleccionar submódulo</option>';
        
        // Buscar módulos de nivel 2 que dependan del módulo de nivel 1 seleccionado
        @php
            $modulosNivel2JSON = $modulos->where('Nivel', 2)->values()->toJson();
        @endphp
        
        const modulosNivel2 = @json($modulos->where('Nivel', 2)->values());
        
        modulosNivel2.forEach(modulo => {
            if (modulo.Dependencia == ordenPadre) {
                const option = document.createElement('option');
                option.value = modulo.orden;
                option.setAttribute('data-nombre', modulo.modulo);
                option.textContent = `${modulo.modulo} (${modulo.orden})`;
                nivel2Select.appendChild(option);
            }
        });
        
        if (nivel2Select.children.length === 1) {
            // No hay submódulos disponibles
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No hay submódulos disponibles';
            option.disabled = true;
            nivel2Select.appendChild(option);
        }
    }

    // Establecer dependencia final para nivel 3
    window.establecerDependenciaFinal = function() {
        const moduloNivel1 = document.getElementById('modal_modulo_nivel1');
        const moduloNivel2 = document.getElementById('modal_modulo_nivel2');
        
        const moduloNivel1Nombre = moduloNivel1.options[moduloNivel1.selectedIndex]?.getAttribute('data-nombre') || '';
        const moduloNivel2Nombre = moduloNivel2.options[moduloNivel2.selectedIndex]?.getAttribute('data-nombre') || '';
        const moduloNivel2Valor = moduloNivel2.value;
        
        if (moduloNivel2Valor) {
            document.getElementById('modal_Dependencia').value = moduloNivel2Valor;
            mostrarUbicacion(`${moduloNivel1Nombre} > ${moduloNivel2Nombre} > [Nuevo Sub-submódulo]`);
        } else {
            document.getElementById('modal_Dependencia').value = '';
            document.getElementById('info_ubicacion').classList.add('hidden');
        }
    };

    // Mostrar información de ubicación
    function mostrarUbicacion(texto) {
        document.getElementById('texto_ubicacion').textContent = texto;
        document.getElementById('info_ubicacion').classList.remove('hidden');
    }

    // Actualizar dependencias según nivel (función legacy - mantenida por compatibilidad)
    window.actualizarDependencias = function() {
        // Esta función se mantiene por compatibilidad pero ya no se usa
        // El nuevo sistema jerárquico maneja esto automáticamente
    };

    // Previsualizar imagen
    window.previsualizarImagen = function(input) {
        const file = input.files[0];
        if (file) {
            // Validar tamaño (2MB)
            if (file.size > 2 * 1024 * 1024) {
                Utils.mostrarAlerta('error', 'Error', 'La imagen no debe superar los 2MB');
                input.value = '';
                return;
            }

            // Validar tipo
            const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            if (!tiposPermitidos.includes(file.type)) {
                Utils.mostrarAlerta('error', 'Error', 'Solo se permiten imágenes JPG, PNG o GIF');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('modal_preview_img').src = e.target.result;
                document.getElementById('modal_preview_filename').textContent = file.name;
                document.getElementById('modal_imagen_preview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('modal_imagen_preview').classList.add('hidden');
        }
    };

    // Confirmar creación con validación
    window.confirmarCreacionModal = function() {
        const orden = document.getElementById('modal_orden').value.trim();
        const modulo = document.getElementById('modal_modulo').value.trim();
        const nivelSeleccionado = document.getElementById('modal_nivel_select').value;
        const nivel = document.getElementById('modal_Nivel').value;
        const dependencia = document.getElementById('modal_Dependencia').value;

        // Validar campos requeridos básicos
        if (!orden || !modulo || !nivelSeleccionado) {
            Utils.mostrarAlerta('warning', 'Campos requeridos', 'Por favor completa todos los campos obligatorios (Orden, Nombre del Módulo y Tipo de Módulo).');
            return;
        }

        // Validaciones específicas por nivel
        if (nivelSeleccionado === '2') {
            const moduloNivel1 = document.getElementById('modal_modulo_nivel1').value;
            if (!moduloNivel1) {
                Utils.mostrarAlerta('warning', 'Módulo Principal requerido', 'Debes seleccionar un módulo principal para el Nivel 2.');
                return;
            }
        }

        if (nivelSeleccionado === '3') {
            const moduloNivel1 = document.getElementById('modal_modulo_nivel1').value;
            const moduloNivel2 = document.getElementById('modal_modulo_nivel2').value;
            
            if (!moduloNivel1) {
                Utils.mostrarAlerta('warning', 'Módulo Principal requerido', 'Debes seleccionar un módulo principal para el Nivel 3.');
                return;
            }
            
            if (!moduloNivel2) {
                Utils.mostrarAlerta('warning', 'Submódulo requerido', 'Debes seleccionar un submódulo para el Nivel 3.');
                return;
            }
        }

        // Construir información de ubicación para confirmación
        let ubicacionTexto = '';
        if (nivelSeleccionado === '1') {
            ubicacionTexto = 'Módulo Principal (sin dependencia)';
        } else {
            const textoUbicacion = document.getElementById('texto_ubicacion').textContent;
            ubicacionTexto = textoUbicacion || 'Ubicación no definida';
        }

        // Confirmar con SweetAlert
        Swal.fire({
            title: '¿Confirmar creación?',
            html: `
                <div class="text-left">
                    <p class="mb-2"><strong>Orden:</strong> ${orden}</p>
                    <p class="mb-2"><strong>Módulo:</strong> ${modulo}</p>
                    <p class="mb-2"><strong>Nivel:</strong> ${nivel}</p>
                    <p class="mb-2"><strong>Ubicación:</strong> ${ubicacionTexto}</p>
                    ${dependencia ? `<p class="mb-2"><strong>Dependencia:</strong> ${dependencia}</p>` : ''}
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, crear',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loader
                Swal.fire({
                    title: 'Creando módulo...',
                    text: 'Por favor espera',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Enviar formulario
                document.getElementById('formCrearModulo').submit();
            }
        });
    };

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('modalCrearModulo');
            if (modal && !modal.classList.contains('hidden')) {
                window.cerrarModalCrearModulo();
            }
        }
    });

    window.editarModuloSeleccionado = function() {
        const checkedBoxes = $(SELECTORS.moduloCheckbox + ':checked');
        if (checkedBoxes.length === 1) {
            const id = checkedBoxes.first().data('id');
            window.location.href = RUTAS.editar(id);
        }
    };

    window.eliminarModulosSeleccionados = function() {
        const checkedBoxes = $(SELECTORS.moduloCheckbox + ':checked');
        const ids = checkedBoxes.map(function() {
            return $(this).data('id');
        }).get();

        if (ids.length === 0) {
            Utils.mostrarAlerta('warning', 'Advertencia', 'Selecciona al menos un módulo para eliminar');
            return;
        }

        const mensaje = ids.length === 1
            ? '¿Estás seguro de eliminar este módulo?'
            : `¿Estás seguro de eliminar ${ids.length} módulos?`;

        Swal.fire({
            title: '¿Estás seguro?',
            text: mensaje,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                DeleteManager.eliminarModulos(ids);
            }
        });
    };

    window.actualizarBotonesAccion = function() {
        ButtonManager.actualizarEstado();
        SelectionManager.actualizarFilasSeleccionadas();
    };

    // ==================== INICIALIZACIÓN ====================
    $(document).ready(function() {
        @if(session('success') && session('show_sweetalert'))
            Utils.mostrarAlerta('success', '¡Éxito!', '{{ session('success') }}', {
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        @endif

        $(document).on('change', SELECTORS.toggleAcceso, function() {
            const id = $(this).data('id');
            const acceso = $(this).is(':checked');
            ToggleManager.cambiarAcceso(id, acceso);
        });

        $(document).on('change', SELECTORS.togglePermiso, function() {
            const id = $(this).data('id');
            const campo = $(this).data('campo');
            const valor = $(this).is(':checked');
            ToggleManager.cambiarPermiso(id, campo, valor);
        });

        $(SELECTORS.selectAll).on('change', function() {
            const isChecked = $(this).is(':checked');
            $(SELECTORS.moduloCheckbox).prop('checked', isChecked);
            window.actualizarBotonesAccion();
        });

        $(document).on('change', SELECTORS.moduloCheckbox, function() {
            SelectionManager.actualizarSelectAll();
            window.actualizarBotonesAccion();
        });

        $(document).on('click', SELECTORS.tablaModulos + ' tbody tr', function(e) {
            if (e.target.type === 'checkbox' || e.target.tagName === 'INPUT') {
                return;
            }
            SelectionManager.toggleFila($(this));
        });

        window.actualizarBotonesAccion();
    });
})();
</script>
@endsection
