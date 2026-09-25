@php
    use App\Services\ModuloService;

    // Variables de estado de rutas
    $isProduccionIndex = Route::currentRouteName() === 'produccion.index';
    $isMuestras = request()->routeIs('muestras.index') || request()->is('planeacion/muestras');
    $isProgramaTejido = request()->routeIs('catalogos.req-programa-tejido') || request()->is('planeacion/programa-tejido') || $isMuestras;
    $programaTejidoModuleLabel = $isMuestras ? 'Muestras' : 'Programa';
    $programaTejidoModulePermission = $isMuestras ? 'Muestras' : 'Programa Tejido';
    $liberarOrdenesBase = $isMuestras ? '/planeacion/muestras' : '/planeacion/programa-tejido';
    // Información del usuario
    $usuario = Auth::user();
    $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
    $usuarioInicial = strtoupper(substr($usuario->nombre, 0, 1));

    // Acceso al módulo Configuración (la lista viene del caché de módulos del usuario).
    // El engranaje solo se ofrece desde el inicio.
    $tieneConfiguracion = $isProduccionIndex
        && app(ModuloService::class)
            ->getModulosPrincipalesPorUsuario(Auth::id())
            ->contains('nombre', 'Configuración');

    // Ocultar Paro solo en las secciones que no deben mostrar esta acción.
    $showParoButton = !request()->routeIs('catalogos.req-programa-tejido')
        && !request()->routeIs('programa-tejido.liberar-ordenes')
        && !$isMuestras
        && !request()->routeIs('programa.urd.eng.*')
        && !request()->routeIs('codificacion-modelos')
        && !request()->routeIs('planeacion.alineacion.index')
        && !request()->routeIs('trazabilidad.*')
        && !request()->routeIs('crudo.*')
        && !request()->is('simulacion*')
        && !request()->routeIs('ventas.*')
        && !request()->is('ventas*');

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
                    {{-- UX-03: un solo <h1>. x-layout.page-title ya trae el suyo; texto plano no. --}}
                    @php($tituloNavbar = $__env->yieldContent('page-title'))
                    @php($etiquetaTitulo = stripos($tituloNavbar, '<h1') === false ? 'h1' : 'div')
                    <{{ $etiquetaTitulo }} class="text-lg md:text-xl lg:text-2xl font-bold text-blue-600 animate-fade-in">
                        {!! $tituloNavbar !!}
                    </{{ $etiquetaTitulo }}>
                @endif
                @yield('menu-planeacion')
            </div>

            <!-- Sección Derecha: Botones y controles -->
            <div class="flex items-center gap-4 flex-shrink-0">
                <!-- Botón Configuración -->
                @if($tieneConfiguracion)
                    <a href="{{ route('configuracion.index') }}"
                       class="w-10 h-10 bg-blue-100 hover:bg-blue-200 rounded-full flex items-center justify-center text-blue-800 hover:text-blue-900 transition-all duration-200 shadow-sm hover:shadow-md"
                       title="Configuración"
                       aria-label="Configuración">
                        <i class="fas fa-cog" aria-hidden="true"></i>
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
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        Paro
                    </a>
                @endif

                <!-- Botón Salir -->
                @if($isProduccionIndex)
                    <button id="logout-btn"
                            class="flex items-center gap-1 px-4 py-3 text-md font-bold text-white bg-red-700 hover:bg-red-800 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
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

{{-- Días para liberar órdenes (Programa Tejido / Muestras): el modal vive en
     resources/js/componentes/dias-liberar.ts (HANDOFF PT B2); aquí solo sus datos. --}}
@if($isProgramaTejido)
    <div id="navbar-dias-liberar" hidden
         data-dias="{{ $diasLiberarOrdenes }}"
         data-base="{{ $liberarOrdenesBase }}"></div>
@endif
