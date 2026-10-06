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
        && !request()->is('ventas*')
        && !request()->routeIs('costos.*');

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
                    {{-- <dialog> nativo y no flux:modal: este layout no carga Alpine (ver app.blade.php)
                         y flux:modal lo necesita. Lo abre componentes/dialog-nativo.ts. --}}
                    <flux:button data-dialog-abrir="confirmar-salir" variant="danger" icon="arrow-right-start-on-rectangle"
                                 class="min-h-touch font-bold">Salir</flux:button>

                    <dialog id="confirmar-salir" data-dialog-nativo aria-labelledby="confirmar-salir-titulo"
                            class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl ring ring-black/5 backdrop:bg-black/50">
                        <form method="POST" action="{{ route('logout') }}" class="flex flex-col items-center gap-6 p-8 text-center">
                            @csrf
                            <div class="flex size-20 items-center justify-center rounded-full bg-red-100 text-red-600">
                                <flux:icon.arrow-right-start-on-rectangle class="size-10" />
                            </div>
                            <div>
                                <flux:heading id="confirmar-salir-titulo" size="xl" class="text-2xl!">¿Cerrar sesión?</flux:heading>
                                <flux:text class="mt-2 text-base!">Tendrás que volver a entrar con tu número de empleado.</flux:text>
                            </div>
                            <div class="grid w-full grid-cols-2 gap-3">
                                <flux:button type="submit" formmethod="dialog" formnovalidate class="h-14! text-lg! font-semibold">Cancelar</flux:button>
                                <flux:button type="submit" variant="danger" icon="arrow-right-start-on-rectangle" class="h-14! text-lg! font-semibold">Sí, salir</flux:button>
                            </div>
                        </form>
                    </dialog>
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
