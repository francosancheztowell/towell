@extends('layouts.app')
@section('navbar-right')
<div class="flex items-center gap-2">
    <button
        type="button" data-usuarios-filtrar
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors"
        title="Filtrar usuarios">
        <i class="fas fa-filter mr-2"></i>
        Filtrar
    </button>
    <x-navbar.button-create
    data-usuarios-crear
    title="Nuevo Usuario"
    icon="fa-plus"
    iconColor="text-white"
    hoverBg="hover:bg-blue-700"
    bg="bg-blue-600"
    text="Nuevo Usuario"
    module="Usuarios"
    checkPermission="true"
    />
</div>
@endsection
@section('page-title', 'Lista de Usuarios')

@section('content')
    @php
        // function_exists: declarada en la vista compilada, un segundo render en el mismo proceso
        // (tests, workers) sería un "Cannot redeclare".
        if (! function_exists('iniciales')) {
            function iniciales($nombre)
            {
                $partes = preg_split('/\s+/', trim($nombre));
                $ini = '';
                foreach ($partes as $p) {
                    if ($p !== '') {
                        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
                    }
                    if (mb_strlen($ini) >= 2) break;
                }
                return mb_substr($ini, 0, 2);
            }
        }
        $puedeDuplicar = userCan('crear', 'Usuarios');
    @endphp

    <div class="">

        <div class="bg-white " id="usuarios-container">
            @forelse ($usuarios as $u)
                <div class="usuario-item px-4 py-4 border-b border-gray-100 hover:bg-gray-50 transition-colors {{ $loop->last ? 'rounded-b-lg' : '' }}"
                     data-numero-empleado="{{ $u->numero_empleado ?? '' }}"
                     data-area="{{ $u->area ?? '' }}"
                     data-turno="{{ $u->turno ?? '' }}">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            @php
                                $fotoUrl = getFotoUsuarioUrl($u->foto ?? null);
                            @endphp
                            @if ($fotoUrl)
                                <img src="{{ $fotoUrl }}" alt="Foto de {{ $u->nombre }}"
                                    width="48"
                                    height="48"
                                    decoding="async"
                                    class="h-12 w-12 rounded-full object-cover border-2 border-gray-200">
                            @else
                                <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center border-2 border-gray-200">
                                    <span class="text-white font-semibold text-sm">{{ iniciales($u->nombre) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1 basis-64">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h3 class="text-base font-semibold text-gray-900">{{ $u->nombre }}</h3>
                                        <span class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full">
                                            #{{ $u->numero_empleado }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-building text-gray-400"></i>
                                            <span class="font-medium">Área:</span>
                                            <span>{{ $u->area ?? 'No asignada' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-gray-400"></i>
                                            <span class="font-medium">Turno:</span>
                                            <span>{{ $u->turno ?? 'No asignado' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-phone text-gray-400"></i>
                                            <span class="font-medium">Teléfono:</span>
                                            <span>{{ $u->telefono ?? 'No registrado' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-comment text-gray-400"></i>
                                            <span class="font-medium">Mensajes:</span>
                                            @if ($u->enviarMensaje)
                                                <span class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">Activo</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full">Inactivo</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('configuracion.usuarios.edit', $u->idusuario) }}"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors">
                                        <i class="fas fa-edit mr-1"></i>
                                        Editar
                                    </a>
                                    @if ($puedeDuplicar)
                                        <button type="button"
                                                data-duplicar-usuario="{{ $u->idusuario }}"
                                                data-nombre="{{ $u->nombre }}"
                                                data-numero-empleado="{{ $u->numero_empleado }}"
                                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-emerald-700 bg-emerald-100 hover:bg-emerald-200 rounded-lg transition-colors"
                                                title="Crear un usuario nuevo con los mismos permisos">
                                            <i class="fas fa-copy mr-1" aria-hidden="true"></i>
                                            Duplicar
                                        </button>
                                    @endif
                                    <form action="{{ route('configuracion.usuarios.destroy', $u->idusuario) }}" method="POST"
                                          data-confirmar-eliminar class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg transition-colors">
                                            <i class="fas fa-trash mr-1"></i>
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay usuarios</h3>
                    <p class="mt-1 text-sm text-gray-500">Comienza creando un nuevo usuario.</p>
                    <div class="mt-6">
                        <x-navbar.button-create data-usuarios-crear title="Crear primer usuario"/>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="mt-4 px-4 py-3 bg-gray-100 rounded-lg border border-gray-200 text-center text-sm font-medium text-gray-700">
            <i class="fas fa-users mr-2 text-blue-600"></i>
            Total: {{ $usuarios->count() }} {{ $usuarios->count() === 1 ? 'usuario' : 'usuarios' }}
        </div>
    </div>

    <!-- Modal Crear Usuario -->
    <div id="modalCrearUsuario" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-lg bg-white">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-user-plus text-blue-600 mr-2"></i>
                    Crear Nuevo Usuario
                </h3>
                <button type="button" data-usuarios-cerrar-crear aria-label="Cerrar" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="formCrearUsuario" class="p-6" method="POST" action="{{ route('configuracion.usuarios.store') }}"
                  data-url-areas="{{ route('configuracion.usuarios.create') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Número de Empleado -->
                    <div>
                        <label for="numero_empleado_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Número de Empleado <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="numero_empleado_modal" name="numero_empleado" required
                            pattern="[0-9]*" inputmode="numeric"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Ej: 2045">
                    </div>

                    <!-- Nombre -->
                    <div>
                        <label for="nombre_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre Completo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nombre_modal" name="nombre" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Nombre completo">
                    </div>

                    <!-- Área -->
                    <div>
                        <label for="area_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Área <span class="text-red-500">*</span>
                        </label>
                        <select id="area_modal" name="area" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                            <option value="">Selecciona el área</option>
                        </select>
                    </div>

                    <!-- Turno -->
                    <div>
                        <label for="turno_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Turno <span class="text-red-500">*</span>
                        </label>
                        <select id="turno_modal" name="turno" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                            <option value="">Selecciona el turno</option>
                            <option value="1">Turno 1</option>
                            <option value="2">Turno 2</option>
                            <option value="3">Turno 3</option>
                            <option value="4">Turno 4 (Cubre descansos)</option>
                        </select>
                    </div>

                    <!-- Puesto -->
                    <div>
                        <label for="puesto_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Puesto
                        </label>
                        <input type="text" id="puesto_modal" name="puesto"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Ej: Supervisor">
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label for="telefono_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Teléfono
                        </label>
                        <input type="tel" id="telefono_modal" name="telefono"
                            inputmode="numeric" pattern="^\d{10}$" minlength="10" maxlength="10"
                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="10 dígitos">
                    </div>

                    <!-- Correo -->
                    <div class="md:col-span-2">
                        <label for="correo_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Correo Electrónico
                        </label>
                        <input type="email" id="correo_modal" name="correo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="usuario@towell.com">
                    </div>

                    <!-- Contraseña -->
                    <div class="md:col-span-2">
                        <label for="contrasenia_modal" class="block text-sm font-medium text-gray-700 mb-1">
                            Contraseña <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="contrasenia_modal" name="contrasenia" required
                            minlength="8"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Mínimo 8 caracteres">
                    </div>

                    <!-- Enviar Mensaje -->
                    <div class="md:col-span-2">
                        <label class="flex items-center">
                            <input type="checkbox" id="enviarMensaje_modal" name="enviarMensaje" value="1"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">¿Enviar mensajes al usuario?</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <button type="button" data-usuarios-cerrar-crear
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($puedeDuplicar)
        {{-- Duplicar: usuario nuevo con los permisos del elegido (resources/js/modulos/usuarios/duplicar) --}}
        <x-ui.modal-base id="modalDuplicarUsuario" title="Duplicar usuario" size="md"
                         data-url="{{ route('configuracion.usuarios.duplicar', ['id' => '__ID__']) }}">
            <form id="formDuplicarUsuario" class="space-y-4" novalidate>
                <p class="text-sm text-gray-600">
                    El usuario nuevo tendrá los mismos permisos, área y puesto que
                    <strong data-duplicar-origen></strong>.
                </p>

                <div>
                    <label for="duplicar_numero_empleado" class="block text-sm font-medium text-gray-700 mb-1">
                        Número de Empleado <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="duplicar_numero_empleado" name="numero_empleado" required
                           maxlength="50" pattern="[0-9]*" inputmode="numeric" autocomplete="off"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="Ej: 2045">
                </div>

                <div>
                    <label for="duplicar_nombre" class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre Completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="duplicar_nombre" name="nombre" required maxlength="255" autocomplete="off"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="Nombre completo">
                </div>

                <div>
                    <label for="duplicar_turno" class="block text-sm font-medium text-gray-700 mb-1">
                        Turno <span class="text-red-500">*</span>
                    </label>
                    <select id="duplicar_turno" name="turno" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                        <option value="">Selecciona el turno</option>
                        <option value="1">Turno 1</option>
                        <option value="2">Turno 2</option>
                        <option value="3">Turno 3</option>
                        <option value="4">Turno 4 (Cubre descansos)</option>
                    </select>
                </div>

                <div>
                    <label for="duplicar_contrasenia" class="block text-sm font-medium text-gray-700 mb-1">
                        Contraseña <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="duplicar_contrasenia" name="contrasenia" required minlength="4"
                           autocomplete="new-password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="Mínimo 4 caracteres">
                </div>
            </form>

            <x-slot:footer>
                <button type="button" data-ui-modal-close-target="modalDuplicarUsuario"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancelar
                </button>
                <button type="submit" form="formDuplicarUsuario" data-duplicar-guardar
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors inline-flex items-center disabled:opacity-60">
                    <i class="fas fa-copy mr-2" aria-hidden="true"></i>
                    Crear usuario
                </button>
            </x-slot:footer>
        </x-ui.modal-base>

        @vite('resources/js/modulos/usuarios/duplicar/index.ts')
    @endif

    @vite('resources/js/modulos/usuarios/lista/index.ts')
@endsection
