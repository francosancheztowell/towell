@extends('layouts.app', ['ocultarBotones' => true])

@section('title', 'Editar Módulo')

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

    <!-- Formulario Simplificado -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <form action="{{ route('configuracion.utileria.modulos.update', $modulo->idrol) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- Campos principales -->
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Editar Módulo: {{ $modulo->modulo }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Orden -->
                    <div>
                        <label for="orden" class="block text-sm font-medium text-gray-700 mb-1">Orden <span class="text-red-500">*</span></label>
                        <input type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('orden') border-red-500 @enderror"
                               id="orden"
                               name="orden"
                               value="{{ old('orden', $modulo->orden) }}"
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
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('modulo') border-red-500 @enderror"
                               id="modulo"
                               name="modulo"
                               value="{{ old('modulo', $modulo->modulo) }}"
                               required
                               placeholder="Ej: Tejido, Urdido, Configuración">
                        @error('modulo')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nivel -->
                    <div>
                        <label for="Nivel" class="block text-sm font-medium text-gray-700 mb-1">Nivel <span class="text-red-500">*</span></label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('Nivel') border-red-500 @enderror"
                                id="Nivel"
                                name="Nivel"
                                required>
                            <option value="">Seleccionar nivel</option>
                            <option value="1" {{ old('Nivel', $modulo->Nivel) == '1' ? 'selected' : '' }}>Nivel 1 (Principal)</option>
                            <option value="2" {{ old('Nivel', $modulo->Nivel) == '2' ? 'selected' : '' }}>Nivel 2 (Submódulo)</option>
                            <option value="3" {{ old('Nivel', $modulo->Nivel) == '3' ? 'selected' : '' }}>Nivel 3 (Submódulo)</option>
                        </select>
                        @error('Nivel')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Dependencia -->
                    <div>
                        <label for="Dependencia" class="block text-sm font-medium text-gray-700 mb-1">Dependencia</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('Dependencia') border-red-500 @enderror"
                                id="Dependencia"
                                name="Dependencia">
                            <option value="">Sin dependencia</option>
                            @foreach($modulosPrincipales as $moduloPrincipal)
                                <option value="{{ $moduloPrincipal->orden }}"
                                        {{ old('Dependencia', $modulo->Dependencia) == $moduloPrincipal->orden ? 'selected' : '' }}>
                                    {{ $moduloPrincipal->modulo }} ({{ $moduloPrincipal->orden }})
                                </option>
                            @endforeach
                        </select>
                        @error('Dependencia')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
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
                               {{ old('acceso', $modulo->acceso) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Acceso</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="crear"
                               name="crear"
                               value="1"
                               {{ old('crear', $modulo->crear) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Crear</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="modificar"
                               name="modificar"
                               value="1"
                               {{ old('modificar', $modulo->modificar) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Modificar</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="eliminar"
                               name="eliminar"
                               value="1"
                               {{ old('eliminar', $modulo->eliminar) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Eliminar</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               id="reigstrar"
                               name="reigstrar"
                               value="1"
                               {{ old('reigstrar', $modulo->reigstrar) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-medium text-gray-700">Registrar</span>
                    </label>
                </div>
            </div>

            <!-- Imagen del módulo -->
            <div class="p-6 border-b border-gray-200">
                <label for="imagen_archivo" class="block text-sm font-medium text-gray-700 mb-3">Imagen del Módulo</label>

                @if($modulo->imagen)
                    <div class="mb-4">
                        <div class="flex items-center space-x-4">
                            <img src="{{ asset('images/fotos_modulos/' . $modulo->imagen) }}"
                                 alt="Imagen actual"
                                 class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Imagen actual</p>
                                <p class="text-xs text-gray-500">{{ $modulo->imagen }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <input type="file"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('imagen_archivo') border-red-500 @enderror"
                       id="imagen_archivo"
                       name="imagen_archivo"
                       accept="image/*">
                @error('imagen_archivo')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF. Máximo: 2MB</p>
            </div>

            <!-- Botones -->
            <div class="p-6 bg-gray-50 flex justify-end space-x-3">
                <a href="{{ route('configuracion.utileria.modulos') }}"
                   class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    Actualizar Módulo
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
