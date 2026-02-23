@extends('layouts.app')
@section('navbar-right')
<div class="flex items-center gap-2">
    <button
        onclick="abrirModalFiltros()"
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors"
        title="Filtrar usuarios">
        <i class="fas fa-filter mr-2"></i>
        Filtrar
    </button>
    <x-navbar.button-create
    onclick="abrirModalCrearUsuario()"
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
    @endphp

    <div class="">
        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: @json(session('success')),
                            confirmButtonColor: '#2563eb'
                        });
                    }
                });
            </script>
        @endif

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
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
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

                                <div class="flex items-center gap-2 ml-4 flex-shrink-0">
                                    <a href="{{ route('configuracion.usuarios.qr', $u->idusuario) }}"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-purple-700 bg-purple-100 hover:bg-purple-200 rounded-lg transition-colors"
                                       title="Ver código QR">
                                        <i class="fas fa-qrcode mr-1"></i>
                                        QR
                                    </a>
                                    <a href="{{ route('configuracion.usuarios.edit', $u->idusuario) }}"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors">
                                        <i class="fas fa-edit mr-1"></i>
                                        Editar
                                    </a>
                                    <form action="{{ route('configuracion.usuarios.destroy', $u->idusuario) }}" method="POST"
                                          onsubmit="return confirmarEliminacion(event)" class="inline">
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
                        <x-navbar.button-create onclick="abrirModalCrearUsuario()" title="Crear primer usuario"/>
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
    <div id="modalCrearUsuario" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-lg bg-white">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-user-plus text-blue-600 mr-2"></i>
                    Crear Nuevo Usuario
                </h3>
                <button onclick="cerrarModalCrearUsuario()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="formCrearUsuario" class="p-6">
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
                    <button type="button" onclick="cerrarModalCrearUsuario()"
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

    {{-- Confirmación de eliminación (SweetAlert si está disponible; fallback a confirm) --}}
    <script>
        function confirmarEliminacion(e) {
            if (window.Swal) {
                e.preventDefault();
                const form = e.target;
                Swal.fire({
                    title: '¿Eliminar usuario?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#2563eb',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((r) => {
                    if (r.isConfirmed) form.submit();
                });
                return false;
            } else {
                return confirm('¿Eliminar usuario? ');
            }
        }

        // Modal crear usuario
        function abrirModalCrearUsuario() {
            cargarDepartamentos();
            document.getElementById('modalCrearUsuario').classList.remove('hidden');
        }

        function cerrarModalCrearUsuario() {
            document.getElementById('modalCrearUsuario').classList.add('hidden');
            document.getElementById('formCrearUsuario').reset();
        }

        // Cargar departamentos desde el servidor
        function cargarDepartamentos() {
            fetch('{{ route("configuracion.usuarios.create") }}')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const selectOriginal = doc.querySelector('select[name="area"]');
                    const selectModal = document.getElementById('area_modal');

                    if (selectOriginal && selectModal) {
                        selectModal.innerHTML = selectOriginal.innerHTML;
                    }
                })
                .catch(error => console.error('Error cargando departamentos:', error));
        }

        // Submit del formulario
        document.getElementById('formCrearUsuario').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('{{ route("configuracion.usuarios.store") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Usuario creado correctamente',
                        confirmButtonColor: '#2563eb'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo crear el usuario',
                    confirmButtonColor: '#2563eb'
                });
            });
        });

        // Variables globales para filtros
        let filtrosActivos = {
            numero_empleado: '',
            area: '',
            turno: ''
        };

        // Obtener áreas únicas de los usuarios cargados
        function obtenerAreasDisponibles() {
            const areas = new Set();
            document.querySelectorAll('.usuario-item').forEach(item => {
                const area = item.getAttribute('data-area');
                if (area && area.trim() !== '') {
                    areas.add(area);
                }
            });
            return Array.from(areas).sort();
        }

        // Función para aplicar filtros en el frontend
        function aplicarFiltros(filtros) {
            filtrosActivos = filtros;
            const usuarios = document.querySelectorAll('.usuario-item');
            let usuariosVisibles = 0;

            usuarios.forEach(usuario => {
                let mostrar = true;

                // Filtrar por número de empleado
                if (filtros.numero_empleado && filtros.numero_empleado.trim() !== '') {
                    const numeroEmpleado = usuario.getAttribute('data-numero-empleado') || '';
                    if (!numeroEmpleado.includes(filtros.numero_empleado.trim())) {
                        mostrar = false;
                    }
                }

                // Filtrar por área
                if (filtros.area && filtros.area !== '') {
                    const area = usuario.getAttribute('data-area') || '';
                    if (area !== filtros.area) {
                        mostrar = false;
                    }
                }

                // Filtrar por turno
                if (filtros.turno && filtros.turno !== '') {
                    const turno = usuario.getAttribute('data-turno') || '';
                    if (turno !== filtros.turno) {
                        mostrar = false;
                    }
                }

                // Mostrar u ocultar usuario
                if (mostrar) {
                    usuario.style.display = '';
                    usuariosVisibles++;
                } else {
                    usuario.style.display = 'none';
                }
            });

            // Actualizar badge del botón de filtros
            actualizarBadgeFiltros();

            // Mostrar mensaje si no hay resultados
            const container = document.getElementById('usuarios-container');
            let mensajeNoResultados = container.querySelector('.mensaje-no-resultados');
            
            if (usuariosVisibles === 0) {
                if (!mensajeNoResultados) {
                    mensajeNoResultados = document.createElement('div');
                    mensajeNoResultados.className = 'mensaje-no-resultados px-4 py-12 text-center';
                    mensajeNoResultados.innerHTML = `
                        <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron usuarios</h3>
                        <p class="mt-1 text-sm text-gray-500">Intenta con otros filtros.</p>
                    `;
                    container.appendChild(mensajeNoResultados);
                }
            } else {
                if (mensajeNoResultados) {
                    mensajeNoResultados.remove();
                }
            }
        }

        // Actualizar badge del botón de filtros
        function actualizarBadgeFiltros() {
            const botonFiltros = document.querySelector('button[onclick="abrirModalFiltros()"]');
            if (!botonFiltros) return;

            const tieneFiltros = filtrosActivos.numero_empleado || filtrosActivos.area || filtrosActivos.turno;
            let badge = botonFiltros.querySelector('.badge-filtros');

            if (tieneFiltros) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge-filtros ml-2 px-2 py-0.5 text-xs font-bold bg-red-500 rounded-full text-white';
                    badge.textContent = '!';
                    botonFiltros.appendChild(badge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        }

        // Función para abrir modal de filtros
        async function abrirModalFiltros() {
            try {
                // Obtener áreas disponibles de los usuarios cargados
                const areas = obtenerAreasDisponibles();
                let opcionesAreas = '<option value="">Todas las áreas</option>';
                areas.forEach(area => {
                    opcionesAreas += `<option value="${area}" ${filtrosActivos.area === area ? 'selected' : ''}>${area}</option>`;
                });

                Swal.fire({
                    title: 'Filtrar Usuarios',
                    html: `
                        <div class="text-left">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Empleado</label>
                                <input 
                                    type="text" 
                                    id="filtro_numero_empleado" 
                                    class="swal2-input" 
                                    placeholder="Ej: 2045"
                                    value="${filtrosActivos.numero_empleado}"
                                    pattern="[0-9]*"
                                >
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                                <select id="filtro_area" class="swal2-input" style="display: block; width: 100%; padding: 0.5rem;">
                                    ${opcionesAreas}
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                                <select id="filtro_turno" class="swal2-input" style="display: block; width: 100%; padding: 0.5rem;">
                                    <option value="">Todos los turnos</option>
                                    <option value="1" ${filtrosActivos.turno === '1' ? 'selected' : ''}>Turno 1</option>
                                    <option value="2" ${filtrosActivos.turno === '2' ? 'selected' : ''}>Turno 2</option>
                                    <option value="3" ${filtrosActivos.turno === '3' ? 'selected' : ''}>Turno 3</option>
                                </select>
                            </div>
                        </div>
                    `,
                    width: '500px',
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar Filtros',
                    cancelButtonText: 'Limpiar',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                    didOpen: () => {
                        // Asegurar que los inputs tengan el estilo correcto
                        const inputs = document.querySelectorAll('.swal2-input');
                        inputs.forEach(input => {
                            if (input.tagName === 'INPUT') {
                                input.style.width = '100%';
                                input.style.margin = '0';
                            }
                        });
                    },
                    preConfirm: () => {
                        return {
                            numero_empleado: document.getElementById('filtro_numero_empleado').value.trim(),
                            area: document.getElementById('filtro_area').value,
                            turno: document.getElementById('filtro_turno').value
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        aplicarFiltros(result.value);
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // Limpiar filtros
                        aplicarFiltros({ numero_empleado: '', area: '', turno: '' });
                    }
                });
            } catch (error) {
                console.error('Error al abrir modal de filtros:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar el modal de filtros',
                    confirmButtonColor: '#2563eb'
                });
            }
        }

        // Inicializar badge al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            actualizarBadgeFiltros();
        });
    </script>
@endsection
