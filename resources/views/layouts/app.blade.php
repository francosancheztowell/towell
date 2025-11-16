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

  <!-- ====== Modal Notificar Montado de Julio ====== -->
  <div id="modalTelaresNotificar" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
      <!-- Header del Modal -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">Telares Asignados</h2>
        <button type="button" onclick="cerrarModalTelares()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body del Modal -->
      <div class="p-6">
        <!-- Filtros de Tipo -->
        <div class="mb-6 flex gap-4">
          <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" id="checkRizoModal" class="form-checkbox h-5 w-5 text-blue-600 rounded">
            <span class="ml-2 text-gray-700 font-medium">Rizo</span>
          </label>
          
          <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" id="checkPieModal" class="form-checkbox h-5 w-5 text-blue-600 rounded">
            <span class="ml-2 text-gray-700 font-medium">Pie</span>
          </label>
        </div>

        <!-- Tabla de Telares -->
        <div class="overflow-x-auto max-h-96">
          <table class="min-w-full bg-white border border-gray-300 rounded-lg">
            <thead class="bg-gray-100 sticky top-0">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-b">Telar</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-b">Tipo</th>
              </tr>
            </thead>
            <tbody id="tablaTelaresBody" class="divide-y divide-gray-200">
              <!-- Se llenará dinámicamente -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer del Modal -->
      <div class="flex justify-end p-6 border-t border-gray-200">
        <button type="button" onclick="cerrarModalTelares()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">Cerrar</button>
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

    // Funciones para modal de telares
    let telaresData = [];
    let tipoFiltroActual = null;

    async function abrirModalTelares() {
      try {
        const response = await fetch('{{ route('notificar.montado.julios') }}', {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        telaresData = data.telares;
        renderTelares();
        document.getElementById('modalTelaresNotificar').style.display = 'flex';
      } catch (error) {
        console.error('Error al cargar telares:', error);
      }
    }

    function cerrarModalTelares() {
      document.getElementById('modalTelaresNotificar').style.display = 'none';
      tipoFiltroActual = null;
      document.getElementById('checkRizoModal').checked = false;
      document.getElementById('checkPieModal').checked = false;
    }

    function renderTelares() {
      const tbody = document.getElementById('tablaTelaresBody');
      let telaresFiltrados = telaresData;

      if (tipoFiltroActual) {
        telaresFiltrados = telaresData.filter(t => t.tipo === tipoFiltroActual);
      }

      if (telaresFiltrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="px-6 py-4 text-center text-sm text-gray-500">No hay telares asignados o no coinciden con el filtro seleccionado</td></tr>';
        return;
      }

      tbody.innerHTML = telaresFiltrados.map(telar => `
        <tr class="hover:bg-gray-50">
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${telar.no_telar}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${telar.tipo === 'rizo' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
              ${telar.tipo.charAt(0).toUpperCase() + telar.tipo.slice(1)}
            </span>
          </td>
        </tr>
      `).join('');
    }

    // Event listeners para checkboxes
    document.addEventListener('DOMContentLoaded', function() {
      const checkRizo = document.getElementById('checkRizoModal');
      const checkPie = document.getElementById('checkPieModal');

      if (checkRizo) {
        checkRizo.addEventListener('change', function() {
          if (this.checked) {
            checkPie.checked = false;
            tipoFiltroActual = 'rizo';
          } else {
            tipoFiltroActual = null;
          }
          renderTelares();
        });
      }

      if (checkPie) {
        checkPie.addEventListener('change', function() {
          if (this.checked) {
            checkRizo.checked = false;
            tipoFiltroActual = 'pie';
          } else {
            tipoFiltroActual = null;
          }
          renderTelares();
        });
      }

      // Cerrar modal al hacer clic fuera
      document.getElementById('modalTelaresNotificar')?.addEventListener('click', function(event) {
        if (event.target === this) {
          cerrarModalTelares();
        }
      });
    });
  </script>
    </body>
    </html>
