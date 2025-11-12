@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@php
    $soloAtras = true;
@endphp

@section('content')
    <div class=" py-2 px-2 sm:py-4 sm:px-4 lg:px-8">
        {{-- SweetAlert de éxito --}}
        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: '¡ÉXITO!',
                        text: @json(session('success')),
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        window.location.href = "{{ route('configuracion.usuarios.select') }}";
                    });
                });
            </script>
        @endif

        @if (session('error'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ocurrió un problema',
                        text: @json(session('error')),
                        confirmButtonColor: '#2563eb'
                    });
                });
            </script>
        @endif

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const errs = @json($errors->all());
                    const list = '<ul style="text-align:left;margin-left:0.5rem;">' +
                        errs.map(e => `<li>• ${e}</li>`).join('') +
                        '</ul>';
                    Swal.fire({
                        icon: 'error',
                        title: 'Revisa los campos',
                        html: list,
                        confirmButtonColor: '#2563eb'
                    });
                });
            </script>
        @endif



        <!-- Header con componente reutilizable -->
        <x-layout.page-header
            :title="$isEdit ? 'Editar Usuario #' . ($usuario->numero_empleado ?? '') : 'Registrar Nuevo Usuario'"
            :subtitle="$isEdit ? 'Actualiza los datos del usuario ' . ($usuario->nombre ?? '') : 'Completa los datos del usuario para crear una nueva cuenta'"
            containerClass="max-w-7xl mx-auto"
        />

        <!-- Formulario -->
        <form action="{{ $isEdit ? route('configuracion.usuarios.update', $usuario) : route('configuracion.usuarios.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="bg-white rounded-b-xl shadow-sm">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <!-- Datos Generales -->
                <div>
                    <div class="px-4 sm:px-6 py-3 sm:py-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                            <!-- Área -->
                            <div class="space-y-1">
                                <label for="area" class="block text-sm font-medium text-gray-700">Área</label>
                                <select id="area" name="area" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                    <option value="" disabled {{ old('area', $usuario->area ?? '') ? '' : 'selected' }}>Selecciona el área</option>
                                    @foreach ([
                                        'Almacén' => 'Almacen',
                                        'Urdido' => 'Urdido',
                                        'Engomado' => 'Engomado',
                                        'Tejido' => 'Tejido',
                                        'Atadores' => 'Atadores',
                                        'Tejedores' => 'Tejedores',
                                        'Mantenimiento' => 'Mantenimiento',
                                    ] as $label => $val)
                                        <option value="{{ $val }}" {{ old('area', $usuario->area ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Número de Empleado -->
                            <div class="space-y-1">
                                <label for="numero_empleado" class="block text-sm font-medium text-gray-700">Número de Empleado</label>
                                <input id="numero_empleado" name="numero_empleado" type="text" inputmode="numeric"
                                    pattern="[0-9]*" value="{{ old('numero_empleado', $usuario->numero_empleado ?? '') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm {{ $isEdit ? 'bg-gray-100' : '' }}"
                                    placeholder="Ej: 2045" {{ $isEdit ? 'readonly' : 'required' }}>
                            </div>

                            <!-- Nombre -->
                            <div class="space-y-1">
                                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre Completo</label>
                                <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $usuario->nombre ?? '') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    placeholder="Nombre completo" required>
                            </div>

                            <!-- Puesto -->
                            <div class="space-y-1">
                                <label for="puesto" class="block text-sm font-medium text-gray-700">Puesto</label>
                                <input id="puesto" name="puesto" type="text" value="{{ old('puesto', $usuario->puesto ?? '') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    placeholder="Ej: Supervisor">
                            </div>

                            <!-- Turno -->
                            <div class="space-y-1">
                                <label for="turno" class="block text-sm font-medium text-gray-700">Turno</label>
                                <select id="turno" name="turno" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                    <option value="" disabled {{ old('turno', $usuario->turno ?? '') ? '' : 'selected' }}>Selecciona el turno</option>
                                    <option value="1" {{ old('turno', $usuario->turno ?? '') == '1' ? 'selected' : '' }}>Turno 1</option>
                                    <option value="2" {{ old('turno', $usuario->turno ?? '') == '2' ? 'selected' : '' }}>Turno 2</option>
                                    <option value="3" {{ old('turno', $usuario->turno ?? '') == '3' ? 'selected' : '' }}>Turno 3</option>
                                </select>
                            </div>

                            <!-- Teléfono -->
                            <div class="space-y-1">
                                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                <input id="telefono" name="telefono" type="tel" inputmode="numeric"
                                    autocomplete="tel" value="{{ old('telefono', $usuario->telefono ?? '') }}" placeholder="10 dígitos"
                                    minlength="10" maxlength="10" pattern="^\d{10}$"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                            </div>

                            <!-- Correo -->
                            <div class="space-y-1">
                                <label for="correo" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                                <input id="correo" name="correo" type="email" value="{{ old('correo', $usuario->correo ?? '') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    placeholder="usuario@towell.com">
                            </div>

                            <!-- Contraseña -->
                            <div class="space-y-1 sm:col-span-2">
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    Contraseña @if($isEdit)<span class="text-gray-500">(dejar vacío para mantener actual)</span>@endif
                                </label>
                                <div class="relative">
                                    <input id="password" name="contrasenia" type="password" autocomplete="new-password"
                                        class="w-full px-3 py-2 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                        placeholder="Mínimo 8 caracteres" {{ $isEdit ? '' : 'required' }}>
                                    <button type="button" id="togglePassword"
                                        class="absolute inset-y-0 right-0 px-3 py-2 text-blue-600 hover:text-blue-800 focus:outline-none transition-colors">
                                        <svg id="eyeClosed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                        </svg>
                                        <svg id="eyeOpen" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Enviar mensaje -->
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700">¿Enviar mensaje?</label>
                                <div class="flex items-center pt-1">
                                    <input id="enviarMensaje" name="enviarMensaje" type="checkbox" value="1"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        {{ old('enviarMensaje', $usuario->enviarMensaje ?? false) ? 'checked' : '' }}>
                                    <label for="enviarMensaje" class="ml-2 block text-sm text-gray-700">Sí, enviar mensajes</label>
                                </div>
                            </div>

                            <!-- Foto de Perfil -->
                            <div class="space-y-1 sm:col-span-2">
                                <label for="foto" class="block text-sm font-medium text-gray-700">Foto de Perfil (Opcional)</label>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                    <!-- Upload -->
                                    <div class="flex-1">
                                        <input id="foto" name="foto" type="file" accept="image/*"
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            onchange="previewImage(event)">
                                        <p class="mt-1 text-xs text-gray-500">Formatos: JPG, PNG. Tamaño sugerido 512×512px</p>
                                    </div>

                                    <!-- Preview -->
                                    <div class="flex-shrink-0">
                                        @php
                                            $fotoUrl = null;
                                            if ($isEdit && !empty($usuario->foto)) {
                                                // Si la DB almacena solo el nombre (imagen.jpg), construir ruta /storage/usuarios/imagen.jpg
                                                if (!Str::startsWith($usuario->foto, ['http://', 'https://', '/'])) {
                                                    $fotoUrl = asset('storage/usuarios/' . $usuario->foto) . '?v=' . time();
                                                } else {
                                                    $fotoUrl = asset('storage/' . ltrim($usuario->foto, '/')) . '?v=' . time();
                                                }
                                            }
                                        @endphp
                                        @if($fotoUrl)
                                            <div id="photo-preview">
                                                <img id="photo-image" src="{{ $fotoUrl }}" class="w-16 h-16 object-cover rounded-full border-2 border-gray-200 shadow-sm" alt="Vista previa">
                                            </div>
                                        @else
                                            <div id="photo-preview" class="hidden">
                                                <img id="photo-image" class="w-16 h-16 object-cover rounded-full border-2 border-gray-200 shadow-sm" alt="Vista previa">
                                            </div>
                                            <div id="photo-placeholder" class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center border-2 border-dashed border-gray-300">
                                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permisos por Módulos -->
                <div>
                    <div class="px-4 sm:px-6 py-3 sm:py-4">
                        <!-- Header con título y botones -->
                        <div class="mb-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <h3 class="text-lg font-semibold text-gray-900">Permisos por Módulos</h3>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="seleccionarTodos()"
                                        class="px-3 py-1 text-sm font-medium text-blue-700 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors">
                                        Seleccionar Todo
                                    </button>
                                    <button type="button" onclick="deseleccionarTodos()"
                                        class="px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors">
                                        Deseleccionar Todo
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Versión móvil: cards -->
                        <div class="block sm:hidden space-y-2">
                            @foreach ($modulos as $modulo)
                                @php
                                    $slugModulo = strtolower(str_replace(' ', '_', $modulo->modulo));
                                    $permisos = isset($permisosUsuario) ? ($permisosUsuario[$modulo->idrol] ?? null) : null;
                                @endphp
                                <div class="bg-white border border-gray-200 rounded-lg p-2.5">
                                    <h3 class="font-medium text-gray-900 mb-1.5 text-sm">{{ $modulo->modulo }}</h3>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_acceso" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-1.5"
                                                {{ old("modulo_{$modulo->idrol}_acceso", $permisos->acceso ?? false) ? 'checked' : '' }}>
                                            <span class="text-xs text-gray-700">Acceso</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_crear" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-1.5"
                                                {{ old("modulo_{$modulo->idrol}_crear", $permisos->crear ?? false) ? 'checked' : '' }}>
                                            <span class="text-xs text-gray-700">Crear</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_modificar" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-1.5"
                                                {{ old("modulo_{$modulo->idrol}_modificar", $permisos->modificar ?? false) ? 'checked' : '' }}>
                                            <span class="text-xs text-gray-700">Modificar</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_eliminar" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-1.5"
                                                {{ old("modulo_{$modulo->idrol}_eliminar", $permisos->eliminar ?? false) ? 'checked' : '' }}>
                                            <span class="text-xs text-gray-700">Eliminar</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Versión desktop: tabla con contenedor -->
                        <div class="hidden sm:block">
                            <div class="overflow-x-auto max-h-[600px] overflow-y-auto border border-gray-200 rounded-lg shadow-sm">
                                <!-- Header de la tabla sticky -->
                                <div class="sticky top-0 z-10">
                                    <div class="grid grid-cols-5 gap-1 px-3 py-2 bg-blue-600 rounded-t-lg border-b border-blue-700 shadow-lg">
                                        <div class="text-xs font-semibold text-white uppercase tracking-wider">Módulo</div>

                                        <!-- Acceso -->
                                        <div class="text-xs font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Acceso</span>
                                                <input type="checkbox" id="selectAllAcceso"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllAcceso(this)">
                                            </div>
                                        </div>

                                        <!-- Crear -->
                                        <div class="text-xs font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Crear</span>
                                                <input type="checkbox" id="selectAllCrear"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllCrear(this)">
                                            </div>
                                        </div>

                                        <!-- Modificar -->
                                        <div class="text-xs font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Modificar</span>
                                                <input type="checkbox" id="selectAllModificar"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllModificar(this)">
                                            </div>
                                        </div>

                                        <!-- Eliminar -->
                                        <div class="text-xs font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Eliminar</span>
                                                <input type="checkbox" id="selectAllEliminar"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllEliminar(this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cuerpo de la tabla -->
                                <div class="bg-white">
                                @foreach ($modulos as $modulo)
                                    @php
                                        $permisos = isset($permisosUsuario) ? ($permisosUsuario[$modulo->idrol] ?? null) : null;
                                        // Determinar el nivel de indentación (convertir a string para comparación segura)
                                        $nivel = (string)$modulo->Nivel;
                                        $indent = '';
                                        $bgClass = 'bg-white';
                                        $fontClass = 'font-medium';

                                        if ($nivel == '1') {
                                            $indent = '';
                                            $bgClass = 'bg-blue-50';
                                            $fontClass = 'font-bold text-blue-900';
                                        } elseif ($nivel == '2') {
                                            $indent = 'pl-6';
                                            $fontClass = 'font-semibold text-gray-800';
                                        } elseif ($nivel == '3') {
                                            $indent = 'pl-12';
                                            $fontClass = 'font-normal text-gray-700';
                                        } else {
                                            $indent = 'pl-16';
                                            $fontClass = 'font-normal text-gray-600';
                                        }
                                    @endphp
                                    <div class="grid grid-cols-5 gap-1 px-3 py-1.5 border-b border-gray-100 hover:bg-gray-50 transition-colors {{ $bgClass }} {{ $loop->last ? 'rounded-b-lg' : '' }}">
                                        <!-- Módulo -->
                                        <div class="flex items-center text-sm {{ $fontClass }} {{ $indent }}">
                                            @if($nivel != '1')
                                                <span class="text-gray-400 mr-1">└─</span>
                                            @endif
                                            {{ $modulo->modulo }}
                                            @if($nivel == '1')
                                                <span class="ml-1 px-1.5 py-0.5 bg-blue-200 text-blue-800 text-xs rounded-full">Principal</span>
                                            @elseif($nivel == '2')
                                                <span class="ml-1 px-1 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Submódulo</span>
                                            @elseif($nivel == '3')
                                                <span class="ml-1 px-1 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full">Nivel 3</span>
                                            @endif
                                        </div>

                                        <!-- Acceso -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_acceso" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-acceso"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_acceso", $permisos->acceso ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Crear -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_crear" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-crear"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_crear", $permisos->crear ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Modificar -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_modificar" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-modificar"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_modificar", $permisos->modificar ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Eliminar -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_eliminar" value="1"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-eliminar"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_eliminar", $permisos->eliminar ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Botones sticky fijos en la parte inferior -->
                <div class="sticky bottom-0 left-0 right-0 z-20">
                    <div class="px-4 sm:px-6 py-3 sm:py-4">
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 justify-end max-w-7xl mx-auto">
                            <a href="{{ route('configuracion.usuarios.select') }}"
                               class="px-4 sm:px-6 py-2 sm:py-3 bg-red-500 border border-gray-300 rounded-lg text-sm font-medium text-white focus:outline-none focus:ring-2 transition-colors text-center">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="px-6 sm:px-8 py-2 sm:py-3 bg-blue-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($isEdit)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    @endif
                                </svg>
                                {{ $isEdit ? 'Actualizar Usuario' : 'Registrar Usuario' }}
                            </button>
                        </div>
                    </div>
                </div>


        </form>
    </div>

    <!-- Scripts -->
    <script>
        // Habilitar uso de Str en blade (@php ya maneja el helper arriba)
        document.addEventListener('DOMContentLoaded', function() {
        // Mostrar / ocultar contraseña
            const togglePassword = () => {
            const btn = document.getElementById('togglePassword');
            const input = document.getElementById('password');
            const eyeClosed = document.getElementById('eyeClosed');
            const eyeOpen = document.getElementById('eyeOpen');

            if (btn && input && eyeClosed && eyeOpen) {
                btn.addEventListener('click', () => {
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';

                    if (isHidden) {
                        eyeClosed.classList.add('hidden');
                        eyeOpen.classList.remove('hidden');
                    } else {
                        eyeOpen.classList.add('hidden');
                        eyeClosed.classList.remove('hidden');
                    }

                    input.focus();
                });
            }
            };

            // Ejecutar función de contraseña
            togglePassword();
        });

        // Previsualización de imagen
        function previewImage(evt) {
            const file = evt.target.files?.[0];
            const preview = document.getElementById('photo-preview');
            const placeholder = document.getElementById('photo-placeholder');
            const img = document.getElementById('photo-image');

            if (!file) {
                preview?.classList.add('hidden');
                placeholder?.classList.remove('hidden');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                if (img) img.src = e.target.result;
                preview?.classList.remove('hidden');
                placeholder?.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        // Funciones para selección rápida de permisos
        function seleccionarTodos() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="modulo_"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }

        function deseleccionarTodos() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="modulo_"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }

        // Funciones para toggle de todas las columnas
        function toggleAllAcceso(checkbox) {
            const accesoCheckboxes = document.querySelectorAll('.checkbox-acceso');
            accesoCheckboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function toggleAllCrear(checkbox) {
            const crearCheckboxes = document.querySelectorAll('.checkbox-crear');
            crearCheckboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function toggleAllModificar(checkbox) {
            const modificarCheckboxes = document.querySelectorAll('.checkbox-modificar');
            modificarCheckboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function toggleAllEliminar(checkbox) {
            const eliminarCheckboxes = document.querySelectorAll('.checkbox-eliminar');
            eliminarCheckboxes.forEach(cb => cb.checked = checkbox.checked);
        }



    </script>
@endsection

