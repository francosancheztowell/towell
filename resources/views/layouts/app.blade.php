<!DOCTYPE html>
<html lang="es">
<head>
    <x-layout-head />
    <x-layout-styles />
    <x-layout-scripts />
    <style>
        /* .fa-spin ya no se redefine aquí: Font Awesome 7 (app.js) trae el suyo con sus
           variables --fa-animation-*; la copia local las ignoraba. */
        /* Fondo compatible con iPad/Safari: rellena viewport y color sólido */
        html {
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }
        body {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            background: #60a5fa;
        }
        /* Mismo fondo en main para que en iPad el scroll muestre el fondo */
        main.app-main {
            background: #60a5fa;
        }

    </style>
</head>

<body class="min-h-screen flex flex-col overflow-hidden h-screen bg-blue-400 relative" style="touch-action: manipulation; -webkit-touch-callout: none;">
    {{-- UX-05: sin user-select:none global (impedía copiar folios); solo el chrome lo lleva
         (app.css). touch-callout se queda: los long-press existentes dependen de él y no
         impide seleccionar. touch-action: manipulation quita el doble toque, no el pinch. --}}
    <x-layout.global-loader />

    <x-navbar.navbar />

    {{-- DS-09: flash de sesión (antes "No tienes acceso…" se perdía). No repite lo que la vista ya pinta;
         el HTML de la vista solo se junta si de verdad hay algo que mostrar. --}}
    @if (session()->hasAny(['error', 'warning', 'success', 'info', 'status']) || (isset($errors) && $errors->any()))
        <x-ui.flash :contenido="$__env->yieldContent('content').$__env->yieldPushContent('scripts')" />
    @endif


        <main class="app-main overflow-x-hidden overflow-y-auto max-w-full flex-1" style="padding-top: var(--pt-navbar-height); height: 100vh; max-height: 100vh; min-height: -webkit-fill-available;">
            @yield('content')
        </main>



  <!-- ====== Scripts ====== -->
    {{-- app-filters.js se desconecto a proposito: @vite emite <script type="module">, que es
         diferido, asi que siempre ganaba sobre los <script> inline de las vistas y reasignaba
         window.applyFilters / removeFilter / resetFilters / openFilterModal. Encima su HTML
         (#filtersModal, #f_list, #f_col_select) no existe en ninguna vista del repo, asi que
         las funciones ganadoras eran no-ops silenciosos y rompian los filtros de 5 paginas. --}}
    {{-- Solo flux.js: @fluxScripts además fuerza Livewire en todas las páginas (la grilla legacy
         de PT no debe cargarlo, ProgramaTejidoShellV2Test). Los ui-* de Flux son custom elements
         y no lo necesitan; donde ya hay Livewire, Flux se engancha a Alpine solo. --}}
    {!! app('flux')->scripts() !!}
    @vite(['resources/js/app-core.js'])

  @stack('scripts')

  @if(config('app.pwa_enabled', true) && !config('app.service_worker_cleanup', false))
    {{-- data-navigate-once: wire:navigate reinyecta scripts del layout; app-pwa.js no debe reejecutarse. --}}
    <script src="{{ asset('js/app-pwa.js') }}" data-navigate-once></script>
  @endif

    </body>
    </html>
