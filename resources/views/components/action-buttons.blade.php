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
    ];

    // Obtener permisos del usuario usando helper reutilizable
    $nombreModulo = isset($rutaToNombreModulo[$route]) ? $rutaToNombreModulo[$route] : null;

    $puedeCrear = $nombreModulo ? userCan('crear', $nombreModulo) : false;
    $puedeEditar = $nombreModulo ? userCan('modificar', $nombreModulo) : false;
    $puedeEliminar = $nombreModulo ? userCan('eliminar', $nombreModulo) : false;
    $tieneAcceso = $nombreModulo ? userCan('acceso', $nombreModulo) : false;
@endphp

<div class="flex items-center gap-1">
    @if($tieneAcceso)
        {{-- Mostrar botones de Excel solo si tiene permiso de crear --}}
        @if($puedeCrear)
            {{-- Para calendarios, mostrar dos botones de Excel separados --}}
            @if($route === 'calendarios')
                <button id="btn-subir-excel-calendarios" onclick="subirExcelCalendariosMaestro()"
                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                title="Subir Calendarios" aria-label="Subir Calendarios">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M14 3v5h5" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M9 11l6 6M15 11l-6 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                </button>

                <button id="btn-subir-excel-lineas" onclick="subirExcelLineas()"
                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                title="Subir Líneas" aria-label="Subir Líneas">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M14 3v5h5" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M9 11l6 6M15 11l-6 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                </button>
            @else
                {{-- Para otros módulos, botón único --}}
                <button id="btn-subir-excel" onclick="console.log('Botón clickeado'); subirExcel{{ ucfirst($route) }}()"
                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                title="Subir Excel" aria-label="Subir Excel">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M14 3v5h5" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M9 11l6 6M15 11l-6 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                </button>
            @endif

            {{-- Botón Añadir/Crear solo si tiene permiso de crear --}}
            <button id="btn-agregar" onclick="agregar{{ ucfirst($route) }}()"
               class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
               title="Añadir" aria-label="Añadir">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        @endif

        {{-- Botón Editar solo si tiene permiso de editar --}}
        @if($puedeEditar)
            <button id="btn-editar" onclick="editar{{ ucfirst($route) }}()" disabled
               class="p-2 text-gray-400 hover:text-gray-600 rounded-md transition-colors cursor-not-allowed"
               title="Editar" aria-label="Editar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
        @endif

        {{-- Botón Eliminar solo si tiene permiso de eliminar --}}
        @if($puedeEliminar)
            <button id="btn-eliminar" onclick="eliminar{{ ucfirst($route) }}()" disabled
               class="p-2 text-red-400 hover:text-red-600 rounded-md transition-colors cursor-not-allowed"
               title="Eliminar" aria-label="Eliminar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        @endif
    @endif

    @if($showFilters)
    <button id="btn-filtrar" onclick="filtrar{{ ucfirst($route) }}()"
       class="relative p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
       title="Filtrar" aria-label="Filtrar">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z" />
        </svg>
        <span id="filter-count" class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs font-bold hidden">0</span>
    </button>

    <button id="btn-restablecer-{{ $route }}" onclick="animarRestablecer{{ ucfirst($route) }}(); limpiarFiltros{{ ucfirst($route) }}()"
       class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
       title="Restablecer" aria-label="Restablecer">
        <svg id="icon-restablecer-{{ $route }}" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
    </button>
    @endif
</div>

<script>
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Función global para subir Excel con SweetAlert2
        window.subirExcel{{ ucfirst($route) }} = function() {
        console.log('subirExcel{{ ucfirst($route) }} llamada');
        // Crear el HTML del formulario
        const htmlContent = `
            <div class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
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
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
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
    window.animarRestablecer{{ ucfirst($route) }} = function() {
        const icon = document.getElementById('icon-restablecer-{{ $route }}');
        if (icon) {
            icon.classList.add('animate-spin');
            setTimeout(() => {
                icon.classList.remove('animate-spin');
            }, 1000);
        }
    };

    // Función para actualizar el contador de filtros
    window.actualizarContadorFiltros{{ ucfirst($route) }} = function(count) {
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
    window.actualizarBotonesAccion{{ ucfirst($route) }} = function(habilitar) {
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
