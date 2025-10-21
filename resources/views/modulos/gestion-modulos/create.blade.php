@extends('layouts.simple')

@section('title', 'Crear Nuevo Módulo')

@section('content')
<div class="container mx-auto px-4 py-4 max-w-full -mt-6">

    <!-- Botón de regresar -->
    <x-back-button text="Volver a Gestión de Módulos" />

    <!-- Mensajes de éxito/error -->
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Formulario -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <form action="{{ route('modulos.sin.auth.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- Campos principales -->
            <div class="p-6 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Orden -->
                    <div>
                        <label for="orden" class="block text-sm font-medium text-gray-700 mb-1">Orden <span class="text-red-500">*</span></label>
                        <input type="text"
                               class="w-full px-3 py-2 border  rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('orden') border-red-500 @enderror"
                               id="orden"
                               name="orden"
                               value="{{ old('orden') }}"
                               required
                               placeholder="Ej: 100, 200, 201">
                        @error('orden')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nombre del Módulo -->
                    <div>
                        <label for="modulo" class="block text-sm font-medium text-gray-700 mb-1">Nombre del Módulo <span class="text-red-500">*</span></label>
                        <input type="text"
                               class="w-full px-3 py-2 border  rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('modulo') border-red-500 @enderror"
                               id="modulo"
                               name="modulo"
                               value="{{ old('modulo') }}"
                               required
                               placeholder="Ej: Tejido, Urdido, Configuración">
                        @error('modulo')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nivel -->
                    <div>
                        <label for="Nivel" class="block text-sm font-medium text-gray-700 mb-1">Nivel <span class="text-red-500">*</span></label>
                        <select class="w-full px-3 py-2 border  rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('Nivel') border-red-500 @enderror"
                                id="Nivel"
                                name="Nivel"
                                required>
                            <option value="">Seleccionar nivel</option>
                            <option value="1" {{ old('Nivel') == '1' ? 'selected' : '' }}>Nivel 1 (Principal)</option>
                            <option value="2" {{ old('Nivel') == '2' ? 'selected' : '' }}>Nivel 2 (Submódulo)</option>
                            <option value="3" {{ old('Nivel') == '3' ? 'selected' : '' }}>Nivel 3 (Submódulo)</option>
                        </select>
                        @error('Nivel')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Dependencia -->
                <div class="mt-4">
                    <label for="Dependencia" class="block text-sm font-medium text-gray-700 mb-1">Dependencia</label>
                    <select class="w-full md:w-1/3 px-3 py-2 border  rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('Dependencia') border-red-500 @enderror"
                            id="Dependencia"
                            name="Dependencia">
                        <option value="">Sin dependencia</option>
                        @foreach($modulosPrincipales as $moduloPrincipal)
                            <option value="{{ $moduloPrincipal->orden }}" {{ old('Dependencia') == $moduloPrincipal->orden ? 'selected' : '' }}>
                                {{ $moduloPrincipal->modulo }} ({{ $moduloPrincipal->orden }})
                            </option>
                        @endforeach
                    </select>
                    @error('Dependencia')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Permisos -->
            <div class="p-6 border-b border-gray-200">
                <label class="block text-sm font-medium text-gray-700 mb-3">Permisos del Módulo</label>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="acceso"
                               name="acceso"
                               value="1"
                               {{ old('acceso', true) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Acceso</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="crear"
                               name="crear"
                               value="1"
                               {{ old('crear') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Crear</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="modificar"
                               name="modificar"
                               value="1"
                               {{ old('modificar') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Modificar</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="eliminar"
                               name="eliminar"
                               value="1"
                               {{ old('eliminar') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Eliminar</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="reigstrar"
                               name="reigstrar"
                               value="1"
                               {{ old('reigstrar') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Registrar</span>
                    </label>
                </div>
            </div>

            <!-- Imagen del módulo -->
            <div class="p-6 border-b border-gray-200">
                <label for="imagen_archivo" class="block text-sm font-medium text-gray-700 mb-3">Imagen del Módulo</label>

                <!-- Preview de imagen -->
                <div id="imagen-preview" class="mb-4 hidden">
                    <div class="flex items-center space-x-4">
                        <img id="preview-img"
                             alt="Vista previa"
                             class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Vista previa</p>
                            <p id="preview-filename" class="text-xs text-gray-500"></p>
                        </div>
                    </div>
                </div>

                <input type="file"
                       class="w-full px-3 py-2 border  rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('imagen_archivo') border-red-500 @enderror"
                       id="imagen_archivo"
                       name="imagen_archivo"
                       accept="image/*">
                @error('imagen_archivo')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF. Máximo: 2MB (Opcional)</p>
            </div>

            <!-- Botones -->
            <div class="p-6 bg-gray-50 flex justify-end space-x-3">
                <a href="{{ route('modulos.sin.auth.index') }}"
                   class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    Cancelar
                </a>
                <button type="button" onclick="confirmarCreacion()"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    Crear Módulo
                </button>
            </div>
        </form>
    </div>

    <!-- Panel de información compacto -->

</div>

<script>
$(document).ready(function() {
    // Mostrar SweetAlert de error si hay errores de validación
    @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Error de validación',
            text: 'Por favor revisa los campos marcados en rojo',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    @endif

    // Auto-actualizar dependencias cuando cambie el nivel
    $('#Nivel').on('change', function() {
        const nivel = $(this).val();
        const dependenciaSelect = $('#Dependencia');

        if (nivel == '1') {
            // Nivel 1 no tiene dependencias
            dependenciaSelect.val('').prop('disabled', true);
        } else {
            // Niveles 2 y 3 pueden tener dependencias
            dependenciaSelect.prop('disabled', false);
        }

        actualizarPreview();
    });

    // Actualizar preview cuando cambien los campos
    $('#orden, #modulo, #Nivel, #Dependencia').on('input change', actualizarPreview);

    // Actualizar preview cuando cambien los checkboxes
    $('input[type="checkbox"]').on('change', actualizarPreview);

    // Preview de imagen en tiempo real
    $('#imagen_archivo').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#preview-img').attr('src', e.target.result);
                $('#preview-filename').text(file.name);
                $('#imagen-preview').removeClass('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            $('#imagen-preview').addClass('hidden');
        }
    });

    // Trigger inicial
    $('#Nivel').trigger('change');
});

function actualizarPreview() {
    // Actualizar orden
    const orden = $('#orden').val() || '-';
    $('#previewOrden').text(orden);

    // Actualizar nivel
    const nivel = $('#Nivel').val();
    const nivelText = nivel ? `Nivel ${nivel}` : '-';
    const nivelColors = {
        '1': 'bg-blue-100 text-blue-800',
        '2': 'bg-green-100 text-green-800',
        '3': 'bg-yellow-100 text-yellow-800'
    };

    const previewNivel = $('#previewNivel');
    previewNivel.text(nivelText);
    previewNivel.removeClass('bg-blue-100 text-blue-800 bg-green-100 text-green-800 bg-yellow-100 text-yellow-800');
    if (nivel && nivelColors[nivel]) {
        previewNivel.addClass(nivelColors[nivel]);
    } else {
        previewNivel.addClass('bg-gray-100 text-gray-800');
    }

    // Actualizar dependencia
    const dependencia = $('#Dependencia option:selected').text();
    const dependenciaText = dependencia && dependencia !== 'Sin dependencia' ? dependencia : 'Sin dependencia';
    $('#previewDependencia').text(dependenciaText);

    // Actualizar permisos
    const permisos = ['acceso', 'crear', 'modificar', 'eliminar', 'reigstrar'];
    const previewContainer = $('#previewPermisos');
    previewContainer.empty();

    permisos.forEach(permiso => {
        const isChecked = $(`#${permiso}`).is(':checked');
        const colorClass = isChecked ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
        const label = permiso.charAt(0).toUpperCase() + permiso.slice(1);

        previewContainer.append(`
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}">
                ${label}
            </span>
        `);
    });
}

function confirmarCreacion() {
    const moduloNombre = document.getElementById('modulo').value;
    const orden = document.getElementById('orden').value;
    const nivel = document.getElementById('Nivel').value;

    if (!moduloNombre || !orden || !nivel) {
        Swal.fire({
            title: 'Campos requeridos',
            text: 'Por favor completa todos los campos obligatorios (Orden, Nombre del Módulo y Nivel).',
            icon: 'warning',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    Swal.fire({
        title: '¿Confirmar creación?',
        text: `¿Estás seguro de que deseas crear el módulo "${moduloNombre}" con orden "${orden}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, crear',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loader
            Swal.fire({
                title: 'Creando...',
                text: 'Por favor espera mientras se procesa la información.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Enviar el formulario después de un pequeño delay
            setTimeout(() => {
                // Agregar un campo oculto para indicar que viene de SweetAlert
                const form = document.querySelector('form');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'from_sweetalert';
                hiddenInput.value = 'true';
                form.appendChild(hiddenInput);

                form.submit();
            }, 100);
        }
    });
}
</script>
@endsection
