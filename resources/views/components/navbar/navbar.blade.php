@php
    use App\Services\ModuloService;

    // Variables de estado de rutas
    $isProduccionIndex = Route::currentRouteName() === 'produccion.index';
    $isMuestras = request()->routeIs('muestras.index') || request()->is('planeacion/muestras');
    $isProgramaTejido = request()->routeIs('catalogos.req-programa-tejido') || request()->is('planeacion/programa-tejido') || $isMuestras;
    $programaTejidoModuleLabel = $isMuestras ? 'Muestras' : 'Programa';
    $programaTejidoModulePermission = $isMuestras ? 'Muestras' : 'Programa Tejido';
    $liberarOrdenesBase = $isMuestras ? '/planeacion/muestras' : '/planeacion/programa-tejido';

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
    {{-- Alto fijo = --pt-navbar-height (app.css): main y los overlays se alinean a él. --}}
    <div class="w-full mx-auto px-0 md:px-2">
        <div class="h-14 flex items-center gap-2">
            <!-- Sección Izquierda: Botón atrás + Logo -->
            @include('components.navbar.sections.left')

            <!-- Sección Centro: Título + Menú planeación -->
            <div class="flex-1 flex items-center justify-center gap-4 min-w-0">
                @hasSection('page-title')
                    {{-- UX-03: un solo <h1>. x-layout.page-title ya trae el suyo; texto plano no. --}}
                    @php($tituloNavbar = $__env->yieldContent('page-title'))
                    @php($etiquetaTitulo = stripos($tituloNavbar, '<h1') === false ? 'h1' : 'div')
                    <{{ $etiquetaTitulo }} class="text-base md:text-lg lg:text-xl font-bold text-blue-600 animate-fade-in">
                        {!! $tituloNavbar !!}
                    </{{ $etiquetaTitulo }}>
                @endif
                @yield('menu-planeacion')
            </div>

            <!-- Sección Derecha: Botones y controles -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <!-- Botón Configuración -->
                @if($tieneConfiguracion)
                    <flux:button :href="route('configuracion.index')" variant="filled" color="blue" icon="cog-6-tooth"
                                 class="min-h-touch min-w-touch" title="Configuración" aria-label="Configuración" />
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
                    <flux:button :href="url('mantenimiento/nuevo-paro')" variant="primary" color="yellow"
                                 icon="exclamation-triangle" class="min-h-touch font-bold">Paro</flux:button>
                @endif

                <!-- Botón Salir -->
                @if($isProduccionIndex)
                    <flux:modal.trigger name="confirmar-salir">
                        <flux:button variant="danger" icon="arrow-right-start-on-rectangle"
                                     class="min-h-touch font-bold">Salir</flux:button>
                    </flux:modal.trigger>

                    <flux:modal name="confirmar-salir" class="w-full max-w-sm">
                        <form method="POST" action="{{ route('logout') }}" class="space-y-6">
                            @csrf
                            <div class="flex items-start gap-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                                    <flux:icon.arrow-right-start-on-rectangle />
                                </div>
                                <div>
                                    <flux:heading size="lg">¿Cerrar sesión?</flux:heading>
                                    <flux:text class="mt-1">Tendrás que volver a entrar con tu número de empleado.</flux:text>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost" class="min-h-touch">Cancelar</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" variant="danger" class="min-h-touch">Sí, salir</flux:button>
                            </div>
                        </form>
                    </flux:modal>
                @endif

                <!-- Avatar + menú de usuario: ui-dropdown de Flux abre/cierra, Esc, clic fuera y foco. -->
                <flux:dropdown position="bottom" align="end">
                    @include('components.navbar.sections.user-avatar')
                    @include('components.navbar.sections.user-modal')
                </flux:dropdown>
            </div>
        </div>
    </div>
</nav>

{{-- Días para liberar órdenes (Programa Tejido / Muestras): el modal vive en
     resources/js/componentes/dias-liberar.ts (HANDOFF PT B2); aquí solo sus datos. --}}
@if($isProgramaTejido)
    <div id="navbar-dias-liberar" hidden
         data-dias="{{ $diasLiberarOrdenes }}"
         data-base="{{ $liberarOrdenesBase }}"></div>
@endif
