@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('page-title', 'Editar Usuario')

@section('content')
    <div class="">
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
        <!-- Formulario -->
        <form action="{{ $isEdit ? route('configuracion.usuarios.update', $usuario) : route('configuracion.usuarios.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="bg-white rounded-b-xl shadow-sm">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <!-- ddDatos Generales -->
                <div>
                    <div class="px-4 sm:px-6 py-2 sm:py-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                            <!-- Área -->
                            <div class="space-y-1">
                                <label for="area" class="block text-sm font-medium text-gray-700">Área</label>
                                <select id="area" name="area" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                    <option value="" disabled {{ old('area', $usuario->area ?? '') ? '' : 'selected' }}>Selecciona el área</option>
                                    @foreach ($departamentos as $depto)
                                        <option value="{{ $depto->Depto }}" {{ old('area', $usuario->area ?? '') === $depto->Depto ? 'selected' : '' }}>
                                            {{ $depto->Depto }}
                                        </option>
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



                            <!-- Foto de Perfil -->
                            <div class="space-y-1 sm:col-span-2">
                                <label for="foto" class="block text-sm font-medium text-gray-700">Foto de Perfil (Opcional)</label>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                    <!-- Upload -->
                                    <div class="flex-1">
                                        <input id="foto" name="foto" type="file" accept="image/*"
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            onchange="previewImage(event)">
                                    </div>

                                    <!-- Preview -->
                                    <div class="flex-shrink-0">
                                        @php
                                            $fotoUrl = null;
                                            if ($isEdit && !empty($usuario->foto)) {
                                                if (!Str::startsWith($usuario->foto, ['http://', 'https://', '/'])) {
                                                    $relativeStoragePath = 'storage/usuarios/' . $usuario->foto;
                                                    $storagePath = public_path($relativeStoragePath);
                                                    $fotoUrl = asset($relativeStoragePath) . (file_exists($storagePath) ? ('?v=' . filemtime($storagePath)) : '');
                                                } else {
                                                    if (Str::startsWith($usuario->foto, ['http://', 'https://'])) {
                                                        $fotoUrl = $usuario->foto;
                                                    } else {
                                                        $relativeStoragePath = 'storage/' . ltrim($usuario->foto, '/');
                                                        $storagePath = public_path($relativeStoragePath);
                                                        $fotoUrl = asset($relativeStoragePath) . (file_exists($storagePath) ? ('?v=' . filemtime($storagePath)) : '');
                                                    }
                                                }
                                            }
                                        @endphp


                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permisos por Módulos -->
                <div class="">
                    <div class="px-4 sm:px-6 py-2 sm:py-3">

                        <!-- Acciones rápidas de permisos -->
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 mb-2">
                            <button type="button"
                                class="px-3 py-2 text-sm font-medium rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200"
                                onclick="seleccionarTodos()">
                                Seleccionar todos
                            </button>
                            <button type="button"
                                class="px-3 py-2 text-sm font-medium rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200"
                                onclick="deseleccionarTodos()">
                                Deseleccionar todos
                            </button>
                        </div>

                        <!-- Versión móvil: cards con header sticky -->
                        <div class="block sm:hidden ">
                            <div class="border border-gray-200 rounded shadow-sm overflow-hidden flex flex-col" style="height: 200px;">
                                <!-- Header sticky -->
                                <div class="bg-blue-600 rounded-t border-b border-blue-700 sticky top-0 z-20 flex-shrink-0">
                                    <div class="grid grid-cols-2 gap-1 px-1 py-0.5">
                                        <div class="text-[8px] font-semibold text-white uppercase leading-tight">Módulo</div>
                                        <div class="flex flex-col gap-0.5">
                                            <div class="text-[7px] font-semibold text-white uppercase text-center leading-tight">Acceso</div>
                                            <div class="text-[7px] font-semibold text-white uppercase text-center leading-tight">Crear</div>
                                            <div class="text-[7px] font-semibold text-white uppercase text-center leading-tight">Modificar</div>
                                            <div class="text-[7px] font-semibold text-white uppercase text-center leading-tight">Eliminar</div>
                                            <div class="text-[7px] font-semibold text-white uppercase text-center leading-tight">Registrar</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contenido con scroll -->
                                <div class="bg-white space-y-0 p-0.5 overflow-y-auto overflow-x-auto flex-1">
                                    @foreach ($modulos as $modulo)
                                        @php
                                            $permisos = isset($permisosUsuario) ? ($permisosUsuario[$modulo->idrol] ?? null) : null;
                                            $nivelMobile = (string)$modulo->Nivel;
                                            $moduloMobileClass = $nivelMobile === '1'
                                                ? 'font-semibold text-blue-900 border-b-2 border-sky-500'
                                                : 'font-medium text-gray-900';
                                        @endphp
                                        <div class="bg-white border-b border-gray-100 p-0.5">
                                            <div class="grid grid-cols-2 gap-1 items-start">
                                                <div class="text-[9px] leading-tight inline-block {{ $moduloMobileClass }}">{{ $modulo->modulo }}</div>
                                                <div class="flex flex-col gap-0.5">
                                                    <label class="flex items-center justify-center">
                                                        <input type="checkbox" name="modulo_{{ $modulo->idrol }}_acceso" value="1"
                                                            data-idrol="{{ $modulo->idrol }}" data-permiso="acceso" data-nivel="{{ (int)$modulo->Nivel }}"
                                                            class="h-2 w-2 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-0.5 checkbox-acceso"
                                                            {{ $isEdit ? (old("modulo_{$modulo->idrol}_acceso", $permisos->acceso ?? false) ? 'checked' : '') : 'checked' }}>
                                                        <span class="text-[8px] text-gray-700">Acceso</span>
                                                    </label>
                                                    <label class="flex items-center justify-center">
                                                        <input type="checkbox" name="modulo_{{ $modulo->idrol }}_crear" value="1"
                                                            data-idrol="{{ $modulo->idrol }}" data-permiso="crear" data-nivel="{{ (int)$modulo->Nivel }}"
                                                            class="h-2 w-2 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-0.5 checkbox-crear"
                                                            {{ $isEdit ? (old("modulo_{$modulo->idrol}_crear", $permisos->crear ?? false) ? 'checked' : '') : 'checked' }}>
                                                        <span class="text-[8px] text-gray-700">Crear</span>
                                                    </label>
                                                    <label class="flex items-center justify-center">
                                                        <input type="checkbox" name="modulo_{{ $modulo->idrol }}_modificar" value="1"
                                                            data-idrol="{{ $modulo->idrol }}" data-permiso="modificar" data-nivel="{{ (int)$modulo->Nivel }}"
                                                            class="h-2 w-2 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-0.5 checkbox-modificar"
                                                            {{ $isEdit ? (old("modulo_{$modulo->idrol}_modificar", $permisos->modificar ?? false) ? 'checked' : '') : 'checked' }}>
                                                        <span class="text-[8px] text-gray-700">Modificar</span>
                                                    </label>
                                                    <label class="flex items-center justify-center">
                                                        <input type="checkbox" name="modulo_{{ $modulo->idrol }}_eliminar" value="1"
                                                            data-idrol="{{ $modulo->idrol }}" data-permiso="eliminar" data-nivel="{{ (int)$modulo->Nivel }}"
                                                            class="h-2 w-2 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-0.5 checkbox-eliminar"
                                                            {{ $isEdit ? (old("modulo_{$modulo->idrol}_eliminar", $permisos->eliminar ?? false) ? 'checked' : '') : 'checked' }}>
                                                        <span class="text-[8px] text-gray-700">Eliminar</span>
                                                    </label>
                                                    <label class="flex items-center justify-center">
                                                        <input type="checkbox" name="modulo_{{ $modulo->idrol }}_registrar" value="1"
                                                            data-idrol="{{ $modulo->idrol }}" data-permiso="registrar" data-nivel="{{ (int)$modulo->Nivel }}"
                                                            class="h-2 w-2 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-0.5 checkbox-registrar"
                                                            {{ $isEdit ? (old("modulo_{$modulo->idrol}_registrar", $permisos->registrar ?? false) ? 'checked' : '') : 'checked' }}>
                                                        <span class="text-[8px] text-gray-700">Registrar</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Versión desktop: tabla con contenedor -->
                        <div class="hidden sm:block">
                            <div class="border border-gray-200 rounded-lg shadow-sm overflow-hidden flex flex-col" style="height: 350px;">
                                <!-- Header de la tabla sticky -->
                                <div class="sticky top-0 z-10 flex-shrink-0">
                                    <div class="grid grid-cols-6 gap-1 px-3 py-1.5 bg-blue-600 rounded-t-lg border-b border-blue-700 shadow-lg" style="grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;">
                                        <div class="text-xs font-semibold text-white uppercase tracking-wider">Módulo</div>

                                        <!-- Acceso -->
                                        <div class="text-sm font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Acceso</span>
                                                <input type="checkbox" id="selectAllAcceso"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllAcceso(this)">
                                            </div>
                                        </div>

                                        <!-- Crear -->
                                        <div class="text-sm font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Crear</span>
                                                <input type="checkbox" id="selectAllCrear"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllCrear(this)">
                                            </div>
                                        </div>

                                        <!-- Modificar -->
                                        <div class="text-sm font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Modificar</span>
                                                <input type="checkbox" id="selectAllModificar"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllModificar(this)">
                                            </div>
                                        </div>

                                        <!-- Eliminar -->
                                        <div class="text-sm font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Eliminar</span>
                                                <input type="checkbox" id="selectAllEliminar"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllEliminar(this)">
                                            </div>
                                        </div>
                                        <!-- Registrar -->
                                        <div class="text-sm font-semibold text-white uppercase tracking-wider text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Registrar</span>
                                                <input type="checkbox" id="selectAllRegistrar"
                                                    class="h-3 w-3 text-white bg-white border-white rounded focus:ring-blue-300 cursor-pointer"
                                                    onchange="toggleAllRegistrar(this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cuerpo de la tabla con scroll -->
                                <div class="bg-white overflow-y-auto overflow-x-auto flex-1">
                                @foreach ($modulos as $modulo)
                                    @php
                                        $permisos = isset($permisosUsuario) ? ($permisosUsuario[$modulo->idrol] ?? null) : null;
                                        // Determinar el nivel de indentación (convertir a string para comparación segura)
                                        $nivel = (string)$modulo->Nivel;
                                        $indent = '';
                                        $bgClass = 'bg-white';
                                        $fontClass = 'font-medium';
                                        $underlineClass = '';

                                        if ($nivel == '1') {
                                            $indent = '';
                                            $bgClass = 'bg-sky-100/80';
                                            $fontClass = 'font-bold text-blue-950';
                                            $underlineClass = 'border-b-2 border-sky-600 pb-0.5';
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
                                    <div class="grid grid-cols-6 gap-1 px-3 py-1 border-b {{ $nivel == '1' ? 'border-sky-300' : 'border-gray-100' }} hover:bg-gray-50 transition-colors {{ $bgClass }} {{ $loop->last ? 'rounded-b-lg' : '' }}" style="grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;">
                                        <!-- Módulo -->
                                        <div class="flex items-center text-sm {{ $fontClass }} {{ $indent }}">
                                            @if($nivel != '1')
                                                <span class="text-gray-400 mr-1">└─</span>
                                            @endif
                                            <span class="{{ $underlineClass }}">{{ $modulo->modulo }}</span>
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
                                                data-idrol="{{ $modulo->idrol }}" data-permiso="acceso" data-nivel="{{ (int)$modulo->Nivel }}"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-acceso"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_acceso", $permisos->acceso ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Crear -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_crear" value="1"
                                                data-idrol="{{ $modulo->idrol }}" data-permiso="crear" data-nivel="{{ (int)$modulo->Nivel }}"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-crear"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_crear", $permisos->crear ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Modificar -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_modificar" value="1"
                                                data-idrol="{{ $modulo->idrol }}" data-permiso="modificar" data-nivel="{{ (int)$modulo->Nivel }}"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-modificar"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_modificar", $permisos->modificar ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Eliminar -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_eliminar" value="1"
                                                data-idrol="{{ $modulo->idrol }}" data-permiso="eliminar" data-nivel="{{ (int)$modulo->Nivel }}"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-eliminar"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_eliminar", $permisos->eliminar ?? false) ? 'checked' : '') : 'checked' }}>
                                        </div>

                                        <!-- Registrar -->
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="modulo_{{ $modulo->idrol }}_registrar" value="1"
                                                data-idrol="{{ $modulo->idrol }}" data-permiso="registrar" data-nivel="{{ (int)$modulo->Nivel }}"
                                                class="h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer checkbox-registrar"
                                                {{ $isEdit ? (old("modulo_{$modulo->idrol}_registrar", $permisos->registrar ?? false) ? 'checked' : '') : 'checked' }}>
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
                    <div class="px-4 sm:px-6 py-2 sm:py-3">
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 justify-end max-w-7xl mx-auto">
                            <a href="{{ route('configuracion.usuarios.select') }}"
                               class="px-4 sm:px-6 py-2 sm:py-3 bg-red-500 border border-gray-300 rounded-lg text-sm font-medium text-white focus:outline-none focus:ring-2 transition-colors text-center">
                                Cancelar
                            </a>

                            <x-navbar.button-edit
                            type="submit"
                            class="px-6 sm:px-8 py-2 sm:py-3 bg-blue-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors flex items-center justify-center"
                            title="Actualizar Usuario"
                            text="Actualizar Usuario"
                            module="Usuarios"
                            checkPermission="true"
                            icon="fa-save"
                            iconColor="text-white"
                            hoverBg="hover:bg-blue-700"
                            bg="bg-blue-600"
                            :disabled="false"
                            />
                        </div>
                    </div>
                </div>


        </form>
    </div>

    <!-- Scripts -->
    <script>
        // Jerarquía: para cada idrol de Nivel 1, lista de idrol de descendientes (Nivel 2 y 3)
        window.MODULOS_DESCENDIENTES = @json($modulosDescendientes ?? []);

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

        const PERMISOS_CONFIG = [
            { permiso: 'acceso', selector: '.checkbox-acceso', headerId: 'selectAllAcceso' },
            { permiso: 'crear', selector: '.checkbox-crear', headerId: 'selectAllCrear' },
            { permiso: 'modificar', selector: '.checkbox-modificar', headerId: 'selectAllModificar' },
            { permiso: 'eliminar', selector: '.checkbox-eliminar', headerId: 'selectAllEliminar' },
            { permiso: 'registrar', selector: '.checkbox-registrar', headerId: 'selectAllRegistrar' }
        ];

        // Función para actualizar el estado del checkbox del header basado en los checkboxes individuales
        function updateHeaderCheckbox(headerId, selector) {
            const headerCheckbox = document.getElementById(headerId);
            if (!headerCheckbox) return;

            const checkboxes = document.querySelectorAll(selector);
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            const totalCount = checkboxes.length;

            // Actualizar estado del header: checked si todos están marcados, indeterminate si algunos, unchecked si ninguno
            if (checkedCount === 0) {
                headerCheckbox.checked = false;
                headerCheckbox.indeterminate = false;
            } else if (checkedCount === totalCount) {
                headerCheckbox.checked = true;
                headerCheckbox.indeterminate = false;
            } else {
                headerCheckbox.checked = false;
                headerCheckbox.indeterminate = true;
            }
        }

        function updateAllHeaders() {
            PERMISOS_CONFIG.forEach(({ headerId, selector }) => updateHeaderCheckbox(headerId, selector));
        }

        function setTodosLosPermisos(checked) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="modulo_"]');
            const checkboxesPorName = {};
            checkboxes.forEach(checkbox => {
                if (!checkboxesPorName[checkbox.name]) {
                    checkboxesPorName[checkbox.name] = [];
                }
                checkboxesPorName[checkbox.name].push(checkbox);
            });

            Object.keys(checkboxesPorName).forEach(name => {
                checkboxesPorName[name].forEach(cb => cb.checked = checked);
            });
        }

        // Funciones para selección rápida de permisos
        function seleccionarTodos() {
            setTodosLosPermisos(true);
            updateAllHeaders();
        }

        function deseleccionarTodos() {
            setTodosLosPermisos(false);
            updateAllHeaders();
        }

        function toggleAllPermiso(checkbox, permiso) {
            const shouldCheck = checkbox.checked || checkbox.indeterminate;
            const idrolesAfectados = new Set();
            document.querySelectorAll(`.checkbox-${permiso}`).forEach(cb => {
                cb.checked = shouldCheck;
                const idrol = cb.getAttribute('data-idrol');
                if (idrol) idrolesAfectados.add(idrol);
            });

            idrolesAfectados.forEach((idrol) => {
                aplicarReglaAcceso(idrol, permiso, shouldCheck);
            });
            updateAllHeaders();
        }

        // Funciones para toggle de todas las columnas
        function toggleAllAcceso(checkbox) {
            toggleAllPermiso(checkbox, 'acceso');
        }

        function toggleAllCrear(checkbox) {
            toggleAllPermiso(checkbox, 'crear');
        }

        function toggleAllModificar(checkbox) {
            toggleAllPermiso(checkbox, 'modificar');
        }

        function toggleAllEliminar(checkbox) {
            toggleAllPermiso(checkbox, 'eliminar');
        }

        function toggleAllRegistrar(checkbox) {
            toggleAllPermiso(checkbox, 'registrar');
        }

        // Variable para evitar bucles infinitos durante la sincronización/cascada
        let sincronizando = false;

        // Sincronizar por data-idrol y data-permiso (más rápido que buscar por name)
        function sincronizarCheckboxPorIdrolPermiso(idrol, permiso, checked) {
            if (sincronizando) return;
            sincronizando = true;
            const selector = `input[type="checkbox"][data-idrol="${idrol}"][data-permiso="${permiso}"]`;
            document.querySelectorAll(selector).forEach(cb => {
                if (cb.checked !== checked) cb.checked = checked;
            });
            sincronizando = false;
        }

        function moduloTienePermisoMarcado(idrol, permiso) {
            const selector = `input[type="checkbox"][data-idrol="${idrol}"][data-permiso="${permiso}"]`;
            return Array.from(document.querySelectorAll(selector)).some(cb => cb.checked);
        }

        function limpiarPermisosSinAcceso(idrol) {
            ['crear', 'modificar', 'eliminar', 'registrar'].forEach((permiso) => {
                sincronizarCheckboxPorIdrolPermiso(idrol, permiso, false);
            });
        }

        function aplicarReglaAcceso(idrol, permisoCambiado, checked) {
            if (permisoCambiado === 'acceso' && !checked) {
                limpiarPermisosSinAcceso(idrol);
                return;
            }

            if (permisoCambiado !== 'acceso' && checked) {
                sincronizarCheckboxPorIdrolPermiso(idrol, 'acceso', true);
            }

            if (!moduloTienePermisoMarcado(idrol, 'acceso')) {
                limpiarPermisosSinAcceso(idrol);
            }
        }

        function aplicarCascadaNivel1(idrolPadre, permiso, checked) {
            if (!window.MODULOS_DESCENDIENTES || !window.MODULOS_DESCENDIENTES[idrolPadre]) return [];

            const hijos = window.MODULOS_DESCENDIENTES[idrolPadre];
            hijos.forEach((idrolHijo) => {
                sincronizarCheckboxPorIdrolPermiso(idrolHijo, permiso, checked);
            });
            return hijos;
        }

        function normalizarPermisosIniciales() {
            const idroles = new Set();
            document.querySelectorAll('input[type="checkbox"][name*="modulo_"]').forEach(cb => {
                const idrol = cb.getAttribute('data-idrol');
                if (idrol) idroles.add(idrol);
            });

            idroles.forEach((idrol) => {
                if (!moduloTienePermisoMarcado(idrol, 'acceso')) {
                    limpiarPermisosSinAcceso(idrol);
                }
            });
        }

        // Sincronizar el checkbox duplicado (otro bloque) y opcionalmente cascada si es Nivel 1
        function sincronizarCheckboxDuplicado(checkboxCambiado) {
            const idrol = checkboxCambiado.getAttribute('data-idrol');
            const permiso = checkboxCambiado.getAttribute('data-permiso');
            const nivel = parseInt(checkboxCambiado.getAttribute('data-nivel'), 10);
            const checked = checkboxCambiado.checked;
            if (!idrol || !permiso) return;

            sincronizarCheckboxPorIdrolPermiso(idrol, permiso, checked);
            aplicarReglaAcceso(idrol, permiso, checked);

            // Cascada: si es módulo Nivel 1, aplicar mismo valor a todos los descendientes (Nivel 2 y 3)
            if (nivel === 1) {
                const hijosAfectados = aplicarCascadaNivel1(idrol, permiso, checked);
                hijosAfectados.forEach((idrolHijo) => {
                    aplicarReglaAcceso(idrolHijo, permiso, checked);
                });
            }
        }

        // Función para deshabilitar/habilitar checkboxes según el bloque visible
        function gestionarBloquesVisibles() {
            // Buscar los bloques usando selectores más específicos
            const mobileBlock = document.querySelector('.block.sm\\:hidden');
            const desktopBlock = document.querySelector('.hidden.sm\\:block');

            if (!mobileBlock || !desktopBlock) return;

            // Verificar qué bloque está visible usando window.getComputedStyle
            const mobileStyle = window.getComputedStyle(mobileBlock);
            const desktopStyle = window.getComputedStyle(desktopBlock);

            const mobileVisible = mobileStyle.display !== 'none' && mobileStyle.visibility !== 'hidden';
            const desktopVisible = desktopStyle.display !== 'none' && desktopStyle.visibility !== 'hidden';

            if (mobileVisible) {
                // Si el bloque móvil está visible, deshabilitar los del desktop
                desktopBlock.querySelectorAll('input[type="checkbox"][name*="modulo_"]').forEach(cb => {
                    cb.disabled = true;
                });
                // Habilitar los del móvil
                mobileBlock.querySelectorAll('input[type="checkbox"][name*="modulo_"]').forEach(cb => {
                    cb.disabled = false;
                });
            } else if (desktopVisible) {
                // Si el bloque desktop está visible, deshabilitar los del móvil
                mobileBlock.querySelectorAll('input[type="checkbox"][name*="modulo_"]').forEach(cb => {
                    cb.disabled = true;
                });
                // Habilitar los del desktop
                desktopBlock.querySelectorAll('input[type="checkbox"][name*="modulo_"]').forEach(cb => {
                    cb.disabled = false;
                });
            }
        }

        // Sincronizar headers cuando se cambian checkboxes individuales
        document.addEventListener('DOMContentLoaded', function() {
            // Gestionar bloques visibles al cargar
            gestionarBloquesVisibles();
            normalizarPermisosIniciales();

            // Función helper para agregar listeners con sincronización
            function agregarListenerConSincronizacion(selector) {
                document.querySelectorAll(selector).forEach(cb => {
                    cb.addEventListener('change', function() {
                        sincronizarCheckboxDuplicado(this);
                        updateAllHeaders();
                    });
                });
            }

            // Listeners para actualizar headers y sincronizar checkboxes duplicados
            agregarListenerConSincronizacion('.checkbox-acceso');
            agregarListenerConSincronizacion('.checkbox-crear');
            agregarListenerConSincronizacion('.checkbox-modificar');
            agregarListenerConSincronizacion('.checkbox-eliminar');
            agregarListenerConSincronizacion('.checkbox-registrar');

            // Inicializar estado de los headers al cargar la página
            updateAllHeaders();

            // Gestionar bloques cuando cambia el tamaño de la ventana
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    gestionarBloquesVisibles();
                }, 100);
            });
        });

        // Los permisos se guardan al enviar el formulario (Actualizar Usuario)

    </script>
@endsection
