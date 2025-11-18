@extends('layouts.app')

@section('title', 'Gestión de Módulos')

@section('navbar-right')
    <x-navbar.button-create href="{{ route('modulos.sin.auth.create') }}" title="Crear Módulo" onclick="crearModulo()"/>
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

    function crearModulo(){
        
    }

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
