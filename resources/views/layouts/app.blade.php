<!DOCTYPE html>
<html lang="es">
<head>
    <x-layout-head />
    <x-layout-styles />
    <x-layout-scripts />
    <style>
        /* Animación de spin para iconos */
        .fa-spin {
            animation: fa-spin 1s linear infinite;
        }
        @keyframes fa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Fondo compatible con iPad/Safari: rellena viewport y gradiente con prefijo WebKit */
        html {
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }
        body {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            background: #93c5fd;
            background: -webkit-linear-gradient(to bottom, #60a5fa, #93c5fd);
            background: linear-gradient(to bottom, #60a5fa, #93c5fd);
        }
        /* Mismo fondo en main para que en iPad el scroll muestre el gradiente */
        main.app-main {
            background: #93c5fd;
            background: -webkit-linear-gradient(to bottom, #60a5fa, #93c5fd);
            background: linear-gradient(to bottom, #60a5fa, #93c5fd);
        }

    </style>
</head>

<body class="min-h-screen flex flex-col overflow-hidden h-screen bg-gradient-to-b from-blue-400 to-blue-200 relative" style="touch-action: manipulation; -webkit-touch-callout: none; -webkit-user-select: none; user-select: none;">
    <x-layout.global-loader />

    <x-navbar.navbar />

  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">@csrf</form>

        <main class="app-main overflow-x-hidden overflow-y-auto max-w-full flex-1" style="padding-top: 64px; height: 100vh; max-height: 100vh; min-height: -webkit-fill-available;">
            @yield('content')
        </main>



  <!-- ====== Scripts ====== -->
    {{-- app-filters.js se desconecto a proposito: @vite emite <script type="module">, que es
         diferido, asi que siempre ganaba sobre los <script> inline de las vistas y reasignaba
         window.applyFilters / removeFilter / resetFilters / openFilterModal. Encima su HTML
         (#filtersModal, #f_list, #f_col_select) no existe en ninguna vista del repo, asi que
         las funciones ganadoras eran no-ops silenciosos y rompian los filtros de 5 paginas. --}}
    @vite(['resources/js/app-core.js'])

  @stack('scripts')

  <!-- Scripts específicos -->
  @if(request()->routeIs('catalogos.req-programa-tejido') || request()->is('planeacion/programa-tejido') || request()->routeIs('muestras.index') || request()->is('planeacion/muestras'))
    <script src="{{ asset('js/programa-tejido-menu.js') }}"></script>
  @endif

  @if(config('app.pwa_enabled', true) && !config('app.service_worker_cleanup', false))
    {{-- data-navigate-once: wire:navigate reinyecta scripts del layout; app-pwa.js no debe reejecutarse. --}}
    <script src="{{ asset('js/app-pwa.js') }}" data-navigate-once></script>
  @endif

    </body>
    </html>
