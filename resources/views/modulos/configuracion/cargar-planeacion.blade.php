@extends('layouts.app')

@section('page-title', 'Cargar Planeación - Programa Tejido')

@section('content')
<div class="w-full">
    <!-- Panel principal blanco -->
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
        <div class="p-4">
            <div class="text-center mb-4">
                <h1 class="text-xl font-bold text-gray-800 mb-1">
                    <i class="fas fa-file-excel text-green-600 mr-2"></i>
                    Cargar Planeación - Programa Tejido
                </h1>
                <p class="text-sm text-gray-600">Importar datos de programa de tejido desde archivo Excel</p>
            </div>

            <!-- Formulario de carga -->
            <form id="excelForm" enctype="multipart/form-data" class="max-w-2xl mx-auto">
                @csrf

                <!-- Zona de carga de archivo -->
                <div class="mb-4">
                    <label for="excel_file" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-upload mr-1"></i>
                        Seleccionar archivo Excel
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition-colors">
                        <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="mt-2">
                            <label for="excel_file" class="cursor-pointer">
                                <span class="block text-sm font-medium text-gray-900">
                                    Arrastra y suelta tu archivo Excel aquí
                                </span>
                                <span class="block text-xs text-gray-500">
                                    o haz click para seleccionar
                                </span>
                                <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" class="sr-only" required>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Formatos soportados: .xlsx, .xls (Máximo 10MB)
                        </p>
                    </div>
                </div>

                <!-- Información del archivo seleccionado -->
                <div id="file-info" class="hidden mb-4 p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-excel text-green-600 mr-2"></i>
                        <div>
                            <p class="text-sm font-medium text-gray-900" id="file-name"></p>
                            <p class="text-xs text-gray-500" id="file-size"></p>
                        </div>
                    </div>
                </div>

                <!-- Instrucciones compactas -->
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Instrucciones:</h3>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <ul class="space-y-1 text-xs text-gray-700">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-1 mt-0.5 text-xs"></i>
                                <span>Archivo debe contener columnas: Salón, Telar, Nombre, Clave Mod., etc.</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-1 mt-0.5 text-xs"></i>
                                <span>Primera fila debe contener encabezados de columna</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-1 mt-0.5 text-xs"></i>
                                <span>Datos se importarán a la tabla ReqProgramaTejido</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-center space-x-3">
                    <button type="button" onclick="window.history.back()"
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Volver
                    </button>
                    <button type="submit" id="uploadBtn"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-upload mr-1"></i>
                        Procesar Excel
                    </button>
                </div>
            </form>

            <!-- Barra de progreso (oculta por defecto) -->
            <div id="progress-section" class="hidden mt-4">
                <div class="bg-gray-200 rounded-full h-2">
                    <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="progress-text" class="text-xs text-gray-600 mt-1 text-center">Procesando archivo...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('excel_file');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const uploadBtn = document.getElementById('uploadBtn');
    const form = document.getElementById('excelForm');
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    // Manejar selección de archivo
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            fileName.textContent = file.name;
            fileSize.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
            fileInfo.classList.remove('hidden');
            uploadBtn.disabled = false;
        } else {
            fileInfo.classList.add('hidden');
            uploadBtn.disabled = true;
        }
    });

    // Manejar envío del formulario
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const file = fileInput.files[0];
        if (!file) {
            Swal.fire({
                title: 'Archivo requerido',
                text: 'Por favor selecciona un archivo Excel',
                icon: 'warning',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Validar tipo de archivo
        const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                title: 'Tipo de archivo inválido',
                text: 'Por favor selecciona un archivo Excel válido (.xlsx o .xls)',
                icon: 'error',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        // Validar tamaño (10MB)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            Swal.fire({
                title: 'Archivo demasiado grande',
                text: 'El archivo es demasiado grande. Máximo 10MB permitido.',
                icon: 'error',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        // Mostrar progreso
        progressSection.classList.remove('hidden');
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';

        // Crear FormData
        const formData = new FormData();
        formData.append('excel_file', file);

        // Obtener token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            formData.append('_token', csrfToken.getAttribute('content'));
        }

        // Simular progreso
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            progressBar.style.width = progress + '%';
        }, 200);

        fetch('/configuracion/cargar-planeacion/upload', {
            method: 'POST',
            body: formData
        })
        .then(async (response) => {
            const rawText = await response.text();
            let parsed = null;
            try { parsed = rawText ? JSON.parse(rawText) : null; } catch (_) { /* texto plano */ }

            if (!response.ok) {
                const serverMsg = parsed && (parsed.message || parsed.error) ? (parsed.message || parsed.error) : rawText;
                throw new Error(`HTTP ${response.status} - ${serverMsg || response.statusText}`);
            }

            if (!parsed) {
                throw new Error('Respuesta no es JSON válido: ' + rawText.substring(0, 200));
                }
            return parsed;
        })
        .then(data => {
            clearInterval(progressInterval);
            progressBar.style.width = '100%';

            if (data.success) {
                progressText.textContent = '¡Archivo procesado exitosamente!';
                progressBar.classList.remove('bg-blue-600');
                progressBar.classList.add('bg-green-600');

                setTimeout(() => {
                    Swal.fire({
                        title: '¡Archivo procesado exitosamente!',
                        html: `
                            <div class="text-left">
                                <p><strong>Registros eliminados:</strong> ${data.deleted || 0}</p>
                                <p><strong>Registros procesados:</strong> ${data.processed || 0}</p>
                                <p><strong>Registros creados:</strong> ${data.created || 0}</p>
                                <p><strong>Registros omitidos:</strong> ${data.skipped || 0}</p>
                                <hr class="my-3">
                                <p><strong>Total antes:</strong> ${data.total_before || 0}</p>
                                <p><strong>Total después:</strong> ${data.total_after || 0}</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'Continuar',
                        confirmButtonColor: '#3b82f6'
                    }).then(() => {
                        window.location.href = '/planeacion/programa-tejido';
                    });
                }, 1000);
            } else {
                throw new Error(data.message || 'Error al procesar el archivo');
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            progressText.textContent = 'Error al procesar el archivo';
            progressBar.classList.remove('bg-blue-600');
            progressBar.classList.add('bg-red-600');

            Swal.fire({
                title: 'Error al procesar el archivo',
                text: (error && error.message) ? error.message : 'Error desconocido',
                icon: 'error',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#ef4444'
            });

            // Resetear botón
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload mr-1"></i>Procesar Excel';
        });
    });
});
</script>
@endsection
