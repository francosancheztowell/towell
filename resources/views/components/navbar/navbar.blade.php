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
        && !request()->routeIs('urdido.reportes.urdido.oee')
        && !request()->routeIs('crudo.*')
        && !request()->is('simulacion*')
        && !request()->routeIs('ventas.*')
        && !request()->is('ventas*')
        && !request()->routeIs('costos.*');

    // Días para liberar órdenes
    $diasLiberarOrdenes = session('liberar_ordenes_dias', 10.999);
@endphp

<!-- NAVBAR -->
{{-- flux:header fijo. Fila 1 (h-14): atrás + logo, título truncado, Paro/Salir/avatar.
     Las acciones de la vista (navbar-right, Programa Tejido) van en un flux:navbar con scroll
     horizontal: en línea desde md, en una segunda fila (h-12) debajo de md. El alto total sale
     de --pt-navbar-height (app.css, regla [data-con-acciones]); main y los overlays se alinean a él. --}}
@php($conAcciones = $isProgramaTejido || $__env->hasSection('navbar-right'))
<flux:header data-navbar :data-con-acciones="$conAcciones ?: null"
             class="fixed! inset-x-0 top-0 z-50! max-md:flex-wrap gap-x-2 bg-white px-1! md:px-2!">
    <!-- Atrás + Logo -->
    @include('components.navbar.sections.left')

    {{-- Título: un solo <h1>. x-layout.page-title ya trae el suyo; texto plano no. --}}
    <div class="flex h-14 min-w-0 flex-1 items-center justify-center">
        @hasSection('page-title')
            @php($tituloNavbar = $__env->yieldContent('page-title'))
            @if(stripos($tituloNavbar, '<h1') === false)
                <flux:heading level="1" class="truncate text-base! md:text-lg! lg:text-xl! font-bold! text-blue-600! animate-fade-in">{!! $tituloNavbar !!}</flux:heading>
            @else
                <flux:heading class="min-w-0 truncate text-base! md:text-lg! lg:text-xl! font-bold! text-blue-600! animate-fade-in">{!! $tituloNavbar !!}</flux:heading>
            @endif
        @endif
    </div>

    <!-- Acciones de la vista -->
    @if($conAcciones)
        <flux:navbar scrollable aria-label="Acciones de la página"
                     class="max-md:order-last h-12 max-md:basis-full md:h-14 min-w-0 md:max-w-[60%] gap-2! py-0! px-1 md:justify-end">
            @if($isProgramaTejido)
                @include('components.navbar.sections.programa-tejido', [
                    'moduleLabel' => $programaTejidoModuleLabel,
                    'modulePermission' => $programaTejidoModulePermission
                ])
            @endif
            @yield('navbar-right')
        </flux:navbar>
    @endif

    <!-- Controles fijos: nunca se recortan -->
    <div class="flex h-14 shrink-0 items-center gap-1 sm:gap-2">
        @if($tieneConfiguracion)
            <flux:button :href="route('configuracion.index')" variant="filled" color="blue" icon="cog-6-tooth"
                         class="min-h-touch min-w-touch" title="Configuración" aria-label="Configuración" />
        @endif

        @if($showParoButton)
            <flux:button :href="url('mantenimiento/nuevo-paro')" variant="primary" color="yellow"
                         icon="exclamation-triangle" class="min-h-touch min-w-touch font-bold" aria-label="Paro">
                <span class="max-sm:sr-only">Paro</span>
            </flux:button>
        @endif

        @if($isProduccionIndex)
            {{-- <dialog> nativo y no flux:modal: este layout no carga Alpine (ver app.blade.php)
                 y flux:modal lo necesita. Lo abre componentes/dialog-nativo.ts. --}}
            <flux:button data-dialog-abrir="confirmar-salir" variant="danger" icon="arrow-right-start-on-rectangle"
                         class="min-h-touch min-w-touch font-bold" aria-label="Salir">
                <span class="max-sm:sr-only">Salir</span>
            </flux:button>

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
</flux:header>

{{-- Días para liberar órdenes (Programa Tejido / Muestras): el modal vive en
     resources/js/componentes/dias-liberar.ts (HANDOFF PT B2); aquí solo sus datos. --}}
@if($isProgramaTejido)
    <div id="navbar-dias-liberar" hidden
         data-dias="{{ $diasLiberarOrdenes }}"
         data-base="{{ $liberarOrdenesBase }}"></div>
@endif
