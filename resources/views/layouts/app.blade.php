<!DOCTYPE html>
<html lang="es">
<head>
    <x-layout-head />
    <x-layout-styles />
    <x-layout-scripts />
</head>

<body class="min-h-screen flex flex-col overflow-hidden h-screen bg-gradient-to-b from-blue-400 to-blue-200 relative" style="touch-action: manipulation; -webkit-touch-callout: none; -webkit-user-select: none; user-select: none;">
    <x-layout.global-loader />

    <x-navbar.navbar />

  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">@csrf</form>

        <main class="overflow-x-hidden overflow-y-auto max-w-full flex-1" style="height: calc(100vh - 64px); max-height: calc(100vh - 64px);">
            @yield('content')
        </main>

  <!-- ====== Modal de Filtros (simple) ====== -->
  <div id="filtersModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white w-full max-w-3xl rounded-xl shadow-lg overflow-hidden animate-fade-in">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">Filtros por columnas</h3>
        <button class="p-2 rounded-md hover:bg-gray-100" onclick="closeFilterModal()" aria-label="Cerrar">
          <i class="fas fa-times text-gray-600"></i>
        </button>
      </div>

      <div class="p-4 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
          <div>
            <label class="block text-xs text-gray-600 mb-1" for="f_col_select">Columna</label>
            <select id="f_col_select" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs text-gray-600 mb-1" for="f_col_value">Valor</label>
            <input id="f_col_value" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Escribe el valor a filtrar">
          </div>
        </div>
        <div class="flex items-center justify-between">
          <button id="f_add_btn" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-blue-600 hover:bg-blue-700 text-white" type="button" aria-label="Agregar filtro">
            <i class="fa-solid fa-plus"></i>
            Agregar
          </button>
          <small class="text-gray-500">Puedes agregar varios filtros</small>
        </div>
        <div id="f_list" class="flex flex-wrap gap-2"></div>
      </div>

      <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <button id="btnResetFilters"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 transition-all"
                onclick="resetFiltersSpin()" title="Restablecer">
          <i id="iconReset" class="fas fa-redo w-4 h-4"></i>
          Restablecer
        </button>

        <div class="flex items-center gap-2">
          <button class="px-3 py-2 text-sm font-medium rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700"
                  onclick="closeFilterModal()">Cancelar</button>
          <button class="px-3 py-2 text-sm font-medium rounded-md bg-blue-600 hover:bg-blue-700 text-white"
                  onclick="confirmFilters()">Aplicar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ====== Scripts ====== -->
    <script src="{{ asset('js/app-core.js') }}"></script>
    <script src="{{ asset('js/app-filters.js') }}"></script>

  @stack('scripts')

  <!-- Scripts específicos -->
  @if(request()->routeIs('catalogos.req-programa-tejido') || (request()->is('planeacion/programa-tejido') && !request()->is('*programa-tejido/*/editar') && !request()->is('*programa-tejido/nuevo*')))
    <script src="{{ asset('js/programa-tejido-menu.js') }}"></script>
  @endif

  <script src="{{ asset('js/simple-click-sounds.js') }}"></script>
  <script src="{{ asset('js/app-pwa.js') }}"></script>

  <script>
    // Exponer función resetColumnsSpin globalmente
    window.resetColumnsSpin = resetColumnsSpin;
  </script>
    </body>
    </html>
