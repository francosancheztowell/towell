@extends('layouts.simple')

@section('title', 'Gestión de Módulos')


@section('menu-planeacion')
<!-- Botones específicos para Gestión de Módulos -->
<div class="flex items-center gap-2">
    <a href="{{ route('modulos.sin.auth.create') }}"
       class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Crear Módulo
    </a>

    <button type="button" onclick="editarModuloSeleccionado()"
            class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            id="btnEditarSeleccionado" disabled>
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Editar
        <span id="countEditar" class="ml-2 bg-yellow-500 text-white text-xs rounded-full px-2 py-0.5 hidden">1</span>
    </button>

    <button type="button" onclick="eliminarModulosSeleccionados()"
            class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            id="btnEliminarSeleccionado" disabled>
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Eliminar
        <span id="countEliminar" class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-0.5 hidden">0</span>
    </button>
</div>
@endsection

@section('content')
<div class="container mx-auto px-2 py-8 max-w-full -mt-6">

<!-- Estilos personalizados -->
<style>
    #tablaModulos tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    #tablaModulos tbody tr:hover {
        background-color: #f3f4f6 !important;
    }

    #tablaModulos tbody tr.selected {
        background-color: #dbeafe !important;
        border-left: 4px solid #3b82f6;
    }

    .modulo-checkbox:checked + * {
        background-color: #dbeafe;
    }

    /* Sticky header para la tabla */
    #tablaModulos thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #3b82f6 !important;
        border-bottom: 2px solid #1d4ed8;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Asegurar que el contenedor tenga altura máxima para el scroll */
    .table-container {
        max-height: 83vh;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }

    /* Mejorar la apariencia del scroll */
    .table-container::-webkit-scrollbar {
        width: 8px;
    }

    .table-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>


    <!-- Tabla de módulos -->
    <div class=" rounded-lg shadow overflow-hidden">
        @if($modulos->count() > 0)
            <div class="table-container overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="tablaModulos">
                    <thead class="bg-blue-500">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-12">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-16">Imagen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Orden</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Módulo</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Acceso</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Crear</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Modificar</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Eliminar</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Registrar</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php
                            // Organizar módulos jerárquicamente: 100 -> 101,102 -> 102_1,102_2
                            $modulosOrganizados = [];
                            $modulosPorDependencia = [];

                            // Agrupar por dependencia
                            foreach($modulos as $modulo) {
                                $dependencia = $modulo->Dependencia ?? 'sin_dependencia';
                                $modulosPorDependencia[$dependencia][] = $modulo;
                            }

                            // Función recursiva para organizar jerárquicamente
                            function organizarModulos($dependencia, $modulosPorDependencia, &$resultado) {
                                if(isset($modulosPorDependencia[$dependencia])) {
                                    foreach($modulosPorDependencia[$dependencia] as $modulo) {
                                        $resultado[] = $modulo;
                                        // Llamar recursivamente para los hijos
                                        organizarModulos($modulo->orden, $modulosPorDependencia, $resultado);
                                    }
                                }
                            }

                            // Empezar con módulos sin dependencia (nivel 1)
                            organizarModulos('sin_dependencia', $modulosPorDependencia, $modulosOrganizados);
                        @endphp

                        @foreach($modulosOrganizados as $modulo)
                            @php
                                // Calcular indentación basada en el nivel
                                $indentacion = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $modulo->Nivel - 1);
                                $claseFila = '';
                                $colorBadge = '';

                                switch($modulo->Nivel) {
                                    case 1:
                                        $claseFila = 'bg-blue-50';
                                        $colorBadge = 'blue';
                                        break;
                                    case 2:
                                        $claseFila = 'bg-green-50';
                                        $colorBadge = 'green';
                                        break;
                                    case 3:
                                        $claseFila = 'bg-yellow-50';
                                        $colorBadge = 'yellow';
                                        break;
                                }
                            @endphp
                            <tr class=" hover:bg-gray-50" data-nivel="{{ $modulo->Nivel }}" data-acceso="{{ $modulo->acceso }}" data-nombre="{{ strtolower($modulo->modulo) }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 modulo-checkbox" value="{{ $modulo->idrol }}" data-id="{{ $modulo->idrol }}">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($modulo->imagen)
                                        <img src="{{ asset('images/fotos_modulos/' . $modulo->imagen) }}"
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $colorBadge }}-100 text-{{ $colorBadge }}-800">
                                        {{ $modulo->orden }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        {!! $indentacion !!}
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ $modulo->modulo }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" class="toggle-acceso rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-id="{{ $modulo->idrol }}" {{ $modulo->acceso ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-id="{{ $modulo->idrol }}" data-campo="crear" {{ $modulo->crear ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-id="{{ $modulo->idrol }}" data-campo="modificar" {{ $modulo->modificar ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-id="{{ $modulo->idrol }}" data-campo="eliminar" {{ $modulo->eliminar ? 'checked' : '' }}>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" class="toggle-permiso rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-id="{{ $modulo->idrol }}" data-campo="reigstrar" {{ $modulo->reigstrar ? 'checked' : '' }}>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay módulos registrados</h3>
                <p class="mt-1 text-sm text-gray-500">Comienza creando tu primer módulo</p>
                <div class="mt-6">
                    <a href="{{ route('modulos.sin.auth.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Crear Primer Módulo
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Mostrar SweetAlert de éxito si viene de crear/editar módulo
    @if(session('success') && session('show_sweetalert'))
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    @endif

    // Toggle acceso
    $('.toggle-acceso').on('change', function() {
        const id = $(this).data('id');
        const acceso = $(this).is(':checked');

        $.ajax({
            url: `/configuracion/utileria/modulos/${id}/toggle-acceso`,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                    // Revertir el toggle
                    $(`.toggle-acceso[data-id="${id}"]`).prop('checked', !acceso);
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al cambiar el estado de acceso'
                });
                // Revertir el toggle
                $(`.toggle-acceso[data-id="${id}"]`).prop('checked', !acceso);
            }
        });
    });

    // Toggle permisos (crear, modificar, eliminar, registrar)
    $(document).on('change', '.toggle-permiso', function() {
        const id = $(this).data('id');
        const campo = $(this).data('campo');
        const valor = $(this).is(':checked');

        $.ajax({
            url: `/configuracion/utileria/modulos/${id}/toggle-permiso`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                campo: campo,
                valor: valor ? 1 : 0
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                    // Revertir el toggle
                    $(`.toggle-permiso[data-id="${id}"][data-campo="${campo}"]`).prop('checked', !valor);
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al cambiar el permiso: ' + (xhr.responseJSON?.message || error)
                });
                // Revertir el toggle
                $(`.toggle-permiso[data-id="${id}"][data-campo="${campo}"]`).prop('checked', !valor);
            }
        });
    });

    // Selección múltiple de módulos
    $('#selectAll').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.modulo-checkbox').prop('checked', isChecked);
        actualizarBotonesAccion();
    });

    // Selección individual de módulos
    $(document).on('change', '.modulo-checkbox', function() {
        const totalCheckboxes = $('.modulo-checkbox').length;
        const checkedCheckboxes = $('.modulo-checkbox:checked').length;

        // Actualizar checkbox "Seleccionar todo"
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        $('#selectAll').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);

        actualizarBotonesAccion();
    });

    // Filtros de búsqueda
    $('#filtroNivel, #filtroAcceso, #buscarModulo').on('change keyup', function() {
        filtrarModulos();
    });

    // Click en fila para seleccionar/deseleccionar
    $(document).on('click', '#tablaModulos tbody tr', function(e) {
        // No activar si se hace click en un checkbox o input
        if (e.target.type === 'checkbox' || e.target.tagName === 'INPUT') {
            return;
        }

        const checkbox = $(this).find('.modulo-checkbox');
        checkbox.prop('checked', !checkbox.prop('checked'));

        // Actualizar estado del checkbox "Seleccionar todo"
        const totalCheckboxes = $('.modulo-checkbox').length;
        const checkedCheckboxes = $('.modulo-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        $('#selectAll').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);

        actualizarBotonesAccion();
    });
});

// Función para actualizar el estado de los botones
function actualizarBotonesAccion() {
    const checkedBoxes = $('.modulo-checkbox:checked');
    const count = checkedBoxes.length;

    // Actualizar botones
    const btnEditar = document.getElementById('btnEditarSeleccionado');
    const btnEliminar = document.getElementById('btnEliminarSeleccionado');

    if (count === 0) {
        btnEditar.disabled = true;
        btnEliminar.disabled = true;
        btnEditar.classList.add('opacity-50', 'cursor-not-allowed');
        btnEliminar.classList.add('opacity-50', 'cursor-not-allowed');
        btnEditar.classList.remove('hover:bg-yellow-700');
        btnEliminar.classList.remove('hover:bg-red-700');
    } else if (count === 1) {
        btnEditar.disabled = false;
        btnEliminar.disabled = false;
        btnEditar.classList.remove('opacity-50', 'cursor-not-allowed');
        btnEliminar.classList.remove('opacity-50', 'cursor-not-allowed');
        btnEditar.classList.add('hover:bg-yellow-700');
        btnEliminar.classList.add('hover:bg-red-700');
    } else {
        btnEditar.disabled = true; // Solo permitir editar uno a la vez
        btnEliminar.disabled = false;
        btnEditar.classList.add('opacity-50', 'cursor-not-allowed');
        btnEliminar.classList.remove('opacity-50', 'cursor-not-allowed');
        btnEditar.classList.remove('hover:bg-yellow-700');
        btnEliminar.classList.add('hover:bg-red-700');
    }

    // Actualizar badges de conteo en los botones
    const countEditar = document.getElementById('countEditar');
    const countEliminar = document.getElementById('countEliminar');

    if (count === 0) {
        countEditar.classList.add('hidden');
        countEliminar.classList.add('hidden');
    } else {
        countEliminar.classList.remove('hidden');
        countEliminar.textContent = count;

        if (count === 1) {
            countEditar.classList.remove('hidden');
            countEditar.textContent = '1';
        } else {
            countEditar.classList.add('hidden');
        }
    }

    // Actualizar clases CSS de las filas seleccionadas
    $('#tablaModulos tbody tr').each(function() {
        const checkbox = $(this).find('.modulo-checkbox');
        if (checkbox.is(':checked')) {
            $(this).addClass('selected');
        } else {
            $(this).removeClass('selected');
        }
    });

    // Actualizar contador en el título de la tabla
    const totalModulos = $('#tablaModulos tbody tr').length;
    if (count > 0) {
        $('#totalModulos').text(`${count}/${totalModulos} seleccionado(s)`);
    } else {
        $('#totalModulos').text(`${totalModulos} módulos`);
    }
}

// Función para editar módulo seleccionado
function editarModuloSeleccionado() {
    const checkedBoxes = $('.modulo-checkbox:checked');
    if (checkedBoxes.length === 1) {
        const id = checkedBoxes.first().data('id');
        window.location.href = `/modulos-sin-auth/${id}/edit`;
    }
}

// Función para eliminar módulos seleccionados con SweetAlert
function eliminarModulosSeleccionados() {
    const checkedBoxes = $('.modulo-checkbox:checked');
    const ids = checkedBoxes.map(function() { return $(this).data('id'); }).get();
    const count = ids.length;

    if (count === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: 'Selecciona al menos un módulo para eliminar'
        });
        return;
    }

    const mensaje = count === 1
        ? '¿Estás seguro de eliminar este módulo?'
        : `¿Estás seguro de eliminar ${count} módulos?`;

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
            eliminarModulos(ids);
        }
    });
}

// Función para eliminar múltiples módulos
function eliminarModulos(ids) {
    let eliminados = 0;
    let errores = 0;

    // Mostrar indicador de carga
    Swal.fire({
        title: 'Eliminando...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Eliminar cada módulo
    ids.forEach(function(id, index) {
        $.ajax({
            url: `/modulos-sin-auth/${id}`,
            method: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    eliminados++;
                } else {
                    errores++;
                }

                if (eliminados + errores === ids.length) {
                    Swal.close();
                    if (errores === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: `${eliminados} módulo(s) eliminado(s) correctamente`,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => window.location.href = '/produccionProceso');
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Parcialmente completado',
                            text: `${eliminados} módulo(s) eliminado(s), ${errores} error(es)`
                        }).then(() => window.location.href = '/produccionProceso');
                    }
                }
            },
            error: function(xhr, status, error) {
                errores++;
                if (eliminados + errores === ids.length) {
                    Swal.close();
                    if (eliminados > 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Parcialmente completado',
                            text: `${eliminados} módulo(s) eliminado(s), ${errores} error(es)`
                        }).then(() => window.location.href = '/produccionProceso');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al eliminar los módulos'
                        });
                    }
                }
            }
        });
    });
}

// Función para limpiar filtros
function limpiarFiltros() {
    $('#filtroNivel').val('');
    $('#filtroAcceso').val('');
    $('#buscarModulo').val('');
    $('#selectAll').prop('checked', false);
    $('.modulo-checkbox').prop('checked', false);
    actualizarBotonesAccion();

    // Mostrar todas las filas
    $('#tablaModulos tbody tr').show();
    const totalModulos = $('#tablaModulos tbody tr').length;
    $('#totalModulos').text(`${totalModulos} módulos`);

    Swal.fire({
        icon: 'success',
        title: 'Filtros limpiados',
        timer: 1500,
        showConfirmButton: false
    });
}

// Función para exportar módulos
function exportarModulos() {
    Swal.fire({
        icon: 'info',
        title: 'Exportar Módulos',
        text: 'Esta funcionalidad estará disponible próximamente',
        timer: 2000,
        showConfirmButton: false
    });
}

// Función de filtrado
function filtrarModulos() {
    const nivel = $('#filtroNivel').val();
    const acceso = $('#filtroAcceso').val();
    const buscar = $('#buscarModulo').val().toLowerCase();

    $('#tablaModulos tbody tr').each(function() {
        const fila = $(this);
        const filaNivel = fila.data('nivel');
        const filaAcceso = fila.data('acceso');
        const filaNombre = fila.data('nombre');

        let mostrar = true;

        // Filtro por nivel
        if (nivel && filaNivel != nivel) {
            mostrar = false;
        }

        // Filtro por acceso
        if (acceso !== '' && filaAcceso != acceso) {
            mostrar = false;
        }

        // Filtro por búsqueda
        if (buscar && !filaNombre.includes(buscar)) {
            mostrar = false;
        }

        if (mostrar) {
            fila.show();
        } else {
            fila.hide();
        }
    });

    // Actualizar contador
    const filasVisibles = $('#tablaModulos tbody tr:visible').length;
    const totalModulos = $('#tablaModulos tbody tr').length;
    $('#totalModulos').text(`${filasVisibles}/${totalModulos} módulos`);
}
</script>
@endsection
