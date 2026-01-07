{{--
    Componente: Catalog Actions (Acciones de Catálogo)

    Descripción:
        Botones de acción para catálogos (crear, editar, eliminar, subir Excel, filtrar).
        Incluye verificación de permisos basada en el módulo.

    Props:
        @param string $route - Ruta del catálogo (ej: 'telares', 'eficiencia', 'calendarios')
        @param bool $showFilters - Si debe mostrar botones de filtros (default: false)

    Uso:
        <x-buttons.catalog-actions route="telares" :showFilters="true" />
--}}

@props(['route' => null, 'showFilters' => false])

@php
    // Mapeo de rutas a nombres de módulos en la tabla SYSRoles
    $rutaToNombreModulo = [
        'telares' => 'Telares',
        'eficiencia' => 'Eficiencias STD',
        'velocidad' => 'Velocidad STD',
        'calendarios' => 'Calendarios',
        'aplicaciones' => 'Aplicaciones (Cat.)',
        'codificacion' => 'Codificación Modelos',
        'matriz-hilos' => 'Matriz Hilos',
        'pesos-rollos' => 'Pesos por Rollos',
    ];

    // Obtener permisos del usuario usando helper reutilizable
    $nombreModulo = isset($rutaToNombreModulo[$route]) ? $rutaToNombreModulo[$route] : null;
    // Nombre seguro para JS (reemplazar no alfanumérico por guion bajo)
    $routeJs = preg_replace('/[^A-Za-z0-9_]/', '_', ucfirst($route ?? ''));

    $puedeCrear = $nombreModulo ? userCan('crear', $nombreModulo) : false;
    $puedeEditar = $nombreModulo ? userCan('modificar', $nombreModulo) : false;
    $puedeEliminar = $nombreModulo ? userCan('eliminar', $nombreModulo) : false;
    $tieneAcceso = $nombreModulo ? userCan('acceso', $nombreModulo) : false;
@endphp

<div class="flex items-center gap-1">
    @if($tieneAcceso)
        {{-- Mostrar botones de Excel solo si tiene permiso de crear --}}
            {{-- Botón especial de Recalcular para calendarios --}}
    @if($route === 'calendarios')
    <button id="btn-recalcular" onclick="recalcularProgramasCalendarioNavbar()"
       class="p-2 text-white bg-blue-600 hover:bg-blue-800 rounded-md transition-colors"
       title="Recalcular programas" aria-label="Recalcular programas">
       <span class="mr-2">
        Recalcular
       </span>
        <i class="fas fa-calculator text-lg" aria-hidden="true"></i>
    </button>
@endif

        @if($puedeCrear)
            {{-- Para calendarios, mostrar dos botones de Excel separados --}}
            @if($route === 'calendarios')
                <button id="btn-subir-excel-calendarios" onclick="subirExcelCalendariosMaestro()"
                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                title="Subir Calendarios" aria-label="Subir Calendarios">
                    <i class="fas fa-file-excel text-lg" aria-hidden="true"></i>
                </button>

                <button id="btn-subir-excel-lineas" onclick="subirExcelLineas()"
                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                title="Subir Líneas" aria-label="Subir Líneas">
                    <i class="fas fa-file-excel text-lg" aria-hidden="true"></i>
                </button>
            @else
                {{-- Para otros módulos, botón único --}}
                <button id="btn-subir-excel" onclick="subirExcel{{ $routeJs }}()"
                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                title="Subir Excel" aria-label="Subir Excel">
                    <i class="fas fa-file-excel text-lg" aria-hidden="true"></i>
                </button>
            @endif

            {{-- Botón Añadir/Crear solo si tiene permiso de crear --}}
            <button id="btn-agregar" onclick="agregar{{ $routeJs }}()"
               class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
               title="Añadir" aria-label="Añadir">
                <i class="fas fa-plus text-lg" aria-hidden="true"></i>
            </button>
        @endif

        {{-- Botón Editar solo si tiene permiso de editar --}}
        @if($puedeEditar)
            <button id="btn-editar" onclick="editar{{ $routeJs }}()"
               class="p-2 text-yellow-400 hover:text-yellow-600 rounded-md transition-colors cursor-not-allowed"
               title="Editar" aria-label="Editar">
                <i class="fas fa-edit text-lg" aria-hidden="true"></i>
            </button>
        @endif

        {{-- Botón Eliminar solo si tiene permiso de eliminar --}}
        @if($puedeEliminar)
            <button id="btn-eliminar" onclick="eliminar{{ $routeJs }}()" disabled
               class="p-2 text-red-400 hover:text-red-600 rounded-md transition-colors cursor-not-allowed"
               title="Eliminar" aria-label="Eliminar">
                <i class="fas fa-trash text-lg" aria-hidden="true"></i>
            </button>

            {{-- Botón Eliminar por Rango solo para calendarios --}}
            @if($route === 'calendarios')
                <button id="btn-eliminar-rango" onclick="eliminarCalendariosPorRango()"
                   class="p-2 bg-red-600 text-white hover:bg-red-800 rounded-md transition-colors"
                   title="Eliminar por Rango" aria-label="Eliminar por Rango">
                    <i class="fas fa-calendar-times text-lg" aria-hidden="true"></i>
                    <span class="ml-1 text-xs">Rango</span>
                </button>
            @endif
        @endif
    @endif


    @if($showFilters)
    <button id="btn-filtrar" onclick="filtrar{{ $routeJs }}()"
       class="relative p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
       title="Filtrar" aria-label="Filtrar">
        <i class="fas fa-filter text-lg" aria-hidden="true"></i>
        <span id="filter-count" class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs font-bold hidden">0</span>
    </button>

    <button id="btn-restablecer-{{ $route }}" onclick="animarRestablecer{{ $routeJs }}(); limpiarFiltros{{ $routeJs }}()"
       class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
       title="Restablecer" aria-label="Restablecer">
        <i id="icon-restablecer-{{ $route }}" class="fas fa-redo text-lg" aria-hidden="true"></i>
    </button>
    @endif
</div>

<script>
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Función global para subir Excel con SweetAlert2
        window.subirExcel{{ $routeJs }} = function() {
        console.log('subirExcel{{ ucfirst($route) }} llamada');
        // Crear el HTML del formulario
        const htmlContent = `
            <div class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <div class="flex justify-center mb-4">
                        <i class="fas fa-file-excel text-gray-400 fa-5x"></i>
                    </div>
                    <div class="mt-4">
                        <label for="swal-file-excel" class="cursor-pointer">
                            <span class="mt-2 block text-sm font-medium text-gray-900">
                                Arrastra y suelta tu archivo Excel aquí
                            </span>
                            <span class="mt-1 block text-sm text-gray-500">
                                o haz click para seleccionar
                            </span>
                            <input type="file" id="swal-file-excel" name="file-excel" accept=".xlsx,.xls" class="sr-only">
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Formatos soportados: .xlsx, .xls (Máximo 10MB)
                    </p>

                    <div class="mt-4">
                    </div>
                </div>

                <div id="swal-file-info" class="hidden p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2 fa-lg"></i>
                        <div>
                            <p class="text-sm font-medium text-gray-900" id="swal-file-name"></p>
                            <p class="text-xs text-gray-500" id="swal-file-size"></p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Subir Archivo Excel',
            html: htmlContent,
            width: '500px',
            showCancelButton: true,
            confirmButtonText: 'Procesar Excel',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            showLoaderOnConfirm: true,
            allowOutsideClick: false,
            preConfirm: () => {
                const fileInput = document.getElementById('swal-file-excel');
                const file = fileInput.files[0];

                if (!file) {
                    Swal.showValidationMessage('Por favor selecciona un archivo Excel');
                    return false;
                }

                // Validar tipo de archivo
                const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.showValidationMessage('Por favor selecciona un archivo Excel válido (.xlsx o .xls)');
                    return false;
                }

                // Validar tamaño (10MB)
                const maxSize = 10 * 1024 * 1024; // 10MB en bytes
                if (file.size > maxSize) {
                    Swal.showValidationMessage('El archivo es demasiado grande. Máximo 10MB permitido.');
                    return false;
                }

                // Realizar petición real al servidor
                const formData = new FormData();
                formData.append('archivo_excel', file);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                return fetch('/planeacion/catalogos/{{ $route }}-modelos/excel', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Verificar si la respuesta HTTP es exitosa
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`Error HTTP ${response.status}: ${text || response.statusText}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        return {
                            success: true,
                            fileName: file.name,
                            fileSize: formatFileSize(file.size),
                            message: data.message,
                            data: data.data
                        };
                    } else {
                        throw new Error(data.message || 'Error al procesar el archivo');
                    }
                })
                .catch(error => {
                    throw new Error(error.message || 'Error al procesar el archivo');
                });
        },
            didOpen: () => {
                // Hacer que el input de archivo sea visible y funcional
                const fileInput = document.getElementById('swal-file-excel');
                if (fileInput) {
                    fileInput.addEventListener('change', function(event) {
                        const file = event.target.files[0];
                        if (file) {
                            // Mostrar información del archivo
                            document.getElementById('swal-file-name').textContent = file.name;
                            document.getElementById('swal-file-size').textContent = formatFileSize(file.size);
                            document.getElementById('swal-file-info').classList.remove('hidden');
                        }
                    });
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                // Mostrar mensaje detallado de éxito
                const data = result.value.data;
                let mensajeDetallado = `Archivo ${result.value.fileName} procesado exitosamente\n\n`;
                mensajeDetallado += `• Registros procesados: ${data.registros_procesados}\n`;
                mensajeDetallado += `• Nuevos registros: ${data.registros_creados}\n`;
                mensajeDetallado += `• Registros actualizados: ${data.registros_actualizados}`;

                if (data.errores && (data.errores.total_errores > 0 || data.errores.length > 0)) {
                    const totalErrores = data.errores.total_errores || data.errores.length;
                    mensajeDetallado += `\n• Errores encontrados: ${totalErrores}`;

                    // Mostrar errores en un modal separado si hay muchos
                    if (totalErrores > 10) {
                        mensajeDetallado += `\n\n Hay ${totalErrores} errores. Revisa el archivo Excel.`;
                    }
                }

                Swal.fire({
                    title: 'Procesamiento Completado',
                    text: mensajeDetallado,
                    icon: (data.errores && (data.errores.total_errores > 0 || data.errores.length > 0)) ? 'warning' : 'success',
                    confirmButtonText: 'Entendido',
                    showCancelButton: true,
                    cancelButtonText: 'Recargar página',
                    cancelButtonColor: '#3085d6'
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        location.reload();
                    }
                    // Si hay errores, mostrar detalles
                    if (data.errores && (data.errores.total_errores > 0 || data.errores.length > 0)) {
                        console.log('Estructura de errores:', data.errores);
                        const totalErrores = data.errores.total_errores || data.errores.length;
                        let erroresTexto = '';

                        // Manejar diferentes formatos de errores
                        if (data.errores.errores && Array.isArray(data.errores.errores)) {
                            // Nuevo formato: {total_errores: X, errores: [...]}
                            data.errores.errores.forEach(error => {
                                erroresTexto += `Fila ${error.fila}: ${error.error}\n`;
                            });
                        } else if (Array.isArray(data.errores)) {
                            // Formato antiguo: array directo
                            data.errores.forEach(error => {
                                if (typeof error === 'string') {
                                    erroresTexto += error + '\n';
                                } else if (error.fila && error.error) {
                                    erroresTexto += `Fila ${error.fila}: ${error.error}\n`;
                                } else {
                                    erroresTexto += JSON.stringify(error) + '\n';
                                }
                            });
                        } else {
                            // Formato desconocido - mostrar como JSON
                            erroresTexto = JSON.stringify(data.errores, null, 2);
                        }

                        if (totalErrores > 10) {
                            erroresTexto += `\n\n... y ${totalErrores - 10} errores más`;
                        }

                        Swal.fire({
                            title: 'Detalles de Errores',
                            text: erroresTexto,
                            icon: 'info',
                            confirmButtonText: 'Entendido',
                            showCancelButton: true,
                            cancelButtonText: 'Recargar página',
                            cancelButtonColor: '#3085d6'
                        }).then((result) => {
                            if (result.dismiss === Swal.DismissReason.cancel) {
                                location.reload();
                            }
                        });
                    }

                    // No recargar automáticamente para poder ver los logs
                    // location.reload();
                });
            }
        }).catch((error) => {
            Swal.fire({
                title: 'Error',
                text: error.message || 'Error al procesar el archivo Excel',
                icon: 'error',
                confirmButtonText: 'Entendido'
            });
        });
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }


    // Función para manejar la selección de archivos
    window.handleFileSelect = function(event) {
        const file = event.target.files[0];
        if (file) {
            // Mostrar información del archivo
            const fileName = document.getElementById('swal-file-name');
            const fileSize = document.getElementById('swal-file-size');
            const fileInfo = document.getElementById('swal-file-info');

            if (fileName && fileSize && fileInfo) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.remove('hidden');
            }
        }
    };

    // Función para animar el icono de restablecer
    window.animarRestablecer{{ $routeJs }} = function() {
        const icon = document.getElementById('icon-restablecer-{{ $route }}');
        if (icon) {
            icon.classList.add('fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spin');
            }, 1000);
        }
    };

    // Función para actualizar el contador de filtros
    window.actualizarContadorFiltros{{ $routeJs }} = function(count) {
        const filterCount = document.getElementById('filter-count');
        if (filterCount) {
            if (count > 0) {
                filterCount.textContent = count;
                filterCount.classList.remove('hidden');
            } else {
                filterCount.classList.add('hidden');
            }
        }
    };

    // Función para habilitar/deshabilitar botones de editar y eliminar
    window.actualizarBotonesAccion{{ $routeJs }} = function(habilitar) {
        const btnEditar = document.getElementById('btn-editar');
        const btnEliminar = document.getElementById('btn-eliminar');

        if (btnEditar) {
            if (habilitar) {
                btnEditar.disabled = false;
                btnEditar.classList.remove('text-gray-400', 'cursor-not-allowed');
                btnEditar.classList.add('text-blue-600', 'hover:text-blue-800');
            } else {
                btnEditar.disabled = true;
                btnEditar.classList.add('text-gray-400', 'cursor-not-allowed');
                btnEditar.classList.remove('text-blue-600', 'hover:text-blue-800');
            }
        }

        if (btnEliminar) {
            if (habilitar) {
                btnEliminar.disabled = false;
                btnEliminar.classList.remove('text-red-400', 'cursor-not-allowed');
                btnEliminar.classList.add('text-red-600', 'hover:text-red-800');
            } else {
                btnEliminar.disabled = true;
                btnEliminar.classList.add('text-red-400', 'cursor-not-allowed');
                btnEliminar.classList.remove('text-red-600', 'hover:text-red-800');
            }
        }
    };
    });
</script>

