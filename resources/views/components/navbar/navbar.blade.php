@php
    use App\Services\ModuloService;

    // Variables de estado de rutas
    $isProduccionIndex = Route::currentRouteName() === 'produccion.index';
    $isMuestras = request()->routeIs('muestras.index') || request()->is('planeacion/muestras');
    $isProgramaTejido = request()->routeIs('catalogos.req-programa-tejido') || request()->is('planeacion/programa-tejido') || $isMuestras;
    $programaTejidoModuleLabel = $isMuestras ? 'Muestras' : 'Programa';
    $programaTejidoModulePermission = 'Programa Tejido';
    $liberarOrdenesBase = $isMuestras ? '/planeacion/muestras' : '/planeacion/programa-tejido';
    $showParoButton = !request()->routeIs('catalogos.req-programa-tejido')
        && !$isMuestras
        && !request()->routeIs('programa.urd.eng.reservar.programar')
        && !request()->routeIs('codificacion-modelos')
        && !request()->routeIs('planeacion.alineacion.index')
        && !request()->is('simulacion*');

    // Información del usuario
    $usuario = Auth::user();
    $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
    $usuarioInicial = strtoupper(substr($usuario->nombre, 0, 1));

    // Verificar acceso al módulo Configuración
    $tieneConfiguracion = (bool) ($tieneConfiguracion ?? false);
    if (!$tieneConfiguracion && Auth::check() && $isProduccionIndex) {
        $moduloService = app(ModuloService::class);
        $modulos = $moduloService->getModulosPrincipalesPorUsuario(Auth::id());
        $tieneConfiguracion = $modulos->contains('nombre', 'Configuración');
    }

    // Días para liberar órdenes
    $diasLiberarOrdenes = session('liberar_ordenes_dias', 10.999);
@endphp

<!-- NAVBAR -->
<nav class="bg-white fixed top-0 left-0 right-0 z-50">
    <div class="w-full mx-auto px-0 md:px-2 py-2">
        <div class="flex items-center gap-2">
            <!-- Sección Izquierda: Botón atrás + Logo -->
            @include('components.navbar.sections.left')

            <!-- Sección Centro: Título + Menú planeación -->
            <div class="flex-1 flex items-center justify-center gap-4 min-w-0">
                @hasSection('page-title')
                    <h1 class="text-lg md:text-xl lg:text-2xl font-bold text-blue-600 animate-fade-in">
                        @yield('page-title')
                    </h1>
                @endif
                @yield('menu-planeacion')
            </div>

            <!-- Sección Derecha: Botones y controles -->
            <div class="flex items-center gap-4 flex-shrink-0">
                @section('navbar-right')

                <!-- Botón Configuración -->
                @if($tieneConfiguracion)
                    <a href="{{ route('configuracion.index') }}"
                       class="w-10 h-10 bg-blue-100 hover:bg-blue-200 rounded-full flex items-center justify-center text-blue-800 hover:text-blue-900 transition-all duration-200 shadow-sm hover:shadow-md"
                       title="Configuración">
                        <i class="fas fa-cog"></i>
                    </a>
                @endif

                <!-- Controles Programa Tejido -->
                @if($isProgramaTejido)
                    @include('components.navbar.sections.programa-tejido', [
                        'moduleLabel' => $programaTejidoModuleLabel,
                        'modulePermission' => $programaTejidoModulePermission
                    ])
                @endif

                @yield('navbar-right')

                <!-- Botón Paro -->
                @if($showParoButton)
                    <a href="{{ url('mantenimiento/nuevo-paro') }}"
                       class="bg-yellow-500 hover:bg-yellow-600 flex items-center gap-2 px-4 py-3 text-md font-bold rounded-lg transition-colors">
                        <i class="fas fa-exclamation-triangle"></i>
                        Paro
                    </a>
                @endif

                <!-- Botón Salir -->
                @if($isProduccionIndex)
                    <button id="logout-btn"
                            class="flex items-center gap-1 px-4 py-3 text-md font-bold text-white bg-red-500 hover:bg-red-700 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt"></i>
                        Salir
                    </button>
                @endif

                <!-- Avatar Usuario -->
                @include('components.navbar.sections.user-avatar')
            </div>
        </div>
    </div>
</nav>

<!-- Modal Usuario -->
@include('components.navbar.sections.user-modal')

<!-- Scripts -->
@push('scripts')
    <script>
        function mostrarModalDiasLiberar() {
            const diasActual = {{ $diasLiberarOrdenes }};

            Swal.fire({
                title: 'Rango de días a considerar',
                html: `
                    <div class="text-left">
                        <label for="rangoDias" class="block text-sm font-medium text-gray-700 mb-2">
                            Ingrese el número de días (decimales permitidos, máx. 3)
                        </label>
                        <input
                            type="number"
                            id="rangoDias"
                            step="0.001"
                            min="0"
                            max="999.999"
                            value="${diasActual}"
                            class="swal2-input w-full"
                            placeholder="10.999"
                            style="margin: 0; width: 100%;"
                        >
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#6b7280',
                focusConfirm: false,
                didOpen: () => {
                    const input = document.getElementById('rangoDias');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                },
                preConfirm: () => {
                    const dias = document.getElementById('rangoDias')?.value;

                    if (!dias || isNaN(dias) || dias < 0) {
                        Swal.showValidationMessage('Por favor ingrese un número válido');
                        return false;
                    }

                    const partes = dias.toString().split('.');
                    if (partes.length > 1 && partes[1].length > 3) {
                        Swal.showValidationMessage('Máximo 3 decimales permitidos');
                        return false;
                    }

                    return dias;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{{ $liberarOrdenesBase }}' + '/liberar-ordenes?dias=' + result.value;
                }
            });
        }
    </script>
@endpush
