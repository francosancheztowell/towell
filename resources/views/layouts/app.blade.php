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
    <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4">
      <!-- Header del Modal -->
      <div class="flex items-center justify-between p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">Notificar Montado de Julio</h2>
        <button type="button" onclick="cerrarModalTelares()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body del Modal -->
      <div class="p-6">
        <!-- Formulario de Selección -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <!-- Select de Telar -->
          <div>
            <label for="selectTelar" class="block text-sm font-medium text-gray-700 mb-2">
              Seleccionar Telar
            </label>
            <select id="selectTelar" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">-- Seleccione un telar --</option>
            </select>
          </div>

          <!-- Checkboxes de Tipo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <div class="flex gap-4 items-center h-10">
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="tipoTelar" id="radioRizo" value="rizo" class="form-radio h-5 w-5 text-blue-600">
                <span class="ml-2 text-gray-700 font-medium">Rizo</span>
              </label>
              
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="tipoTelar" id="radioPie" value="pie" class="form-radio h-5 w-5 text-blue-600">
                <span class="ml-2 text-gray-700 font-medium">Pie</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Detalles del Telar -->
        <div id="detallesTelar" class="hidden bg-gradient-to-br from-blue-50 to-gray-50 rounded-lg p-6 border-2 border-blue-200">
          <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
            Información del Telar
          </h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">No. Telar</label>
              <input type="text" id="detalle_no_telar" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Cuenta</label>
              <input type="text" id="detalle_cuenta" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Calibre</label>
              <input type="text" id="detalle_calibre" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo</label>
              <input type="text" id="detalle_tipo" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo Atado</label>
              <input type="text" id="detalle_tipo_atado" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">No. Orden</label>
              <input type="text" id="detalle_no_orden" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">No. Julio</label>
              <input type="text" id="detalle_no_julio" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Metros</label>
              <input type="text" id="detalle_metros" readonly class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora Paro</label>
            </div>
              <input type="text" id="detalle_hora_paro" readonly class="w-full px-3 py-2 bg-green-50 border border-green-300 rounded-md text-2xl font-bold text-green-700 text-center">
          </div>
        </div>

        <!-- Mensaje cuando no hay datos -->
        <div id="mensajeNoData" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm text-yellow-700">No se encontraron datos para el telar y tipo seleccionados.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer del Modal -->
      <div class="flex justify-end gap-3 p-2 border-t border-gray-200">
        <button type="button" onclick="notificarTelares()" class="px-4 py-2 bg-blue-400 hover:bg-blue-600 text-white rounded-lg transition-colors">
          Notificar
        </button>
        <button type="button" onclick="cerrarModalTelares()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
          Cerrar
        </button>
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
  @if(request()->is('simulacion') && !request()->is('*simulacion/*/editar') && !request()->is('*simulacion/nuevo*'))
    <script src="{{ asset('js/programa-tejido-menu.js') }}"></script>
  @endif

  <script src="{{ asset('js/simple-click-sounds.js') }}"></script>
  <script src="{{ asset('js/app-pwa.js') }}"></script>

  <script>
    // Exponer función resetColumnsSpin globalmente
    window.resetColumnsSpin = resetColumnsSpin;

    // Funciones para modal de telares
    let telaresUsuario = [];
    let registroActualId = null;

    async function abrirModalTelares() {
      try {
        const response = await fetch('{{ route('notificar.montado.julios') }}?listado=1', {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        telaresUsuario = data.telares;
        
        // Llenar el select con los telares
        const select = document.getElementById('selectTelar');
        select.innerHTML = '<option value="">-- Seleccione un telar --</option>';
        
        telaresUsuario.forEach(telar => {
          const option = document.createElement('option');
          option.value = telar;
          option.textContent = telar;
          select.appendChild(option);
        });
        
        document.getElementById('modalTelaresNotificar').style.display = 'flex';
      } catch (error) {
        console.error('Error al cargar telares:', error);
      }
    }

    function cerrarModalTelares() {
      document.getElementById('modalTelaresNotificar').style.display = 'none';
      document.getElementById('selectTelar').value = '';
      document.getElementById('radioRizo').checked = false;
      document.getElementById('radioPie').checked = false;
      document.getElementById('detallesTelar').classList.add('hidden');
      document.getElementById('mensajeNoData').classList.add('hidden');
      registroActualId = null;
    }

    async function buscarDetallesTelar() {
      const telar = document.getElementById('selectTelar').value;
      const tipo = document.querySelector('input[name="tipoTelar"]:checked')?.value;

      // Validar que ambos campos estén llenos
      if (!telar || !tipo) {
        return; // No hacer nada si falta algún campo
      }

      try {
        const response = await fetch(`{{ route('notificar.montado.julios') }}?no_telar=${telar}&tipo=${tipo}`, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();

        // Ocultar mensaje de error
        document.getElementById('mensajeNoData').classList.add('hidden');
        
        if (data.detalles) {
          registroActualId = data.detalles.id;
          mostrarDetallesTelar(data.detalles);
        } else {
          document.getElementById('detallesTelar').classList.add('hidden');
          document.getElementById('mensajeNoData').classList.remove('hidden');
          registroActualId = null;
        }
      } catch (error) {
        console.error('Error al cargar detalles del telar:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Ocurrió un error al buscar los detalles del telar',
          confirmButtonColor: '#3b82f6'
        });
      }
    }

    async function notificarTelares(){
      if (!registroActualId) {
        Swal.fire({
          icon: 'warning',
          title: 'Sin registro',
          text: 'Por favor seleccione un telar y tipo primero',
          confirmButtonColor: '#3b82f6'
        });
        return;
      }

      // Obtener la hora actual del input
      const horaParo = document.getElementById('detalle_hora_paro').value;
      
      if (!horaParo) {
        Swal.fire({
          icon: 'warning',
          title: 'Sin hora',
          text: 'No hay hora registrada para notificar',
          confirmButtonColor: '#3b82f6'
        });
        return;
      }

      try {
        const response = await fetch('{{ route('notificar.montado.julios.notificar') }}', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
          },
          body: JSON.stringify({
            id: registroActualId,
            horaParo: horaParo
          })
        });

        const data = await response.json();

        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Notificado',
            text: data.message,
            confirmButtonColor: '#3b82f6',
            timer: 2000
          });
        } else {
          throw new Error(data.error || 'Error al notificar');
        }
      } catch (error) {
        console.error('Error al notificar:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Ocurrió un error al notificar el telar',
          confirmButtonColor: '#3b82f6'
        });
      }
    }

    function mostrarDetallesTelar(detalles) {
      document.getElementById('detalle_no_telar').value = detalles.no_telar || '';
      document.getElementById('detalle_cuenta').value = detalles.cuenta || '';
      document.getElementById('detalle_calibre').value = detalles.calibre || '';
      document.getElementById('detalle_tipo').value = detalles.tipo || '';
      document.getElementById('detalle_tipo_atado').value = detalles.tipo_atado || '';
      document.getElementById('detalle_no_orden').value = detalles.no_orden || '';
      document.getElementById('detalle_no_julio').value = detalles.no_julio || '';
      document.getElementById('detalle_metros').value = detalles.metros || '';
      
      // Siempre insertar la hora actual
      const horaParo = new Date().toLocaleTimeString('es-MX', { hour12: false });
      document.getElementById('detalle_hora_paro').value = horaParo;
      
      document.getElementById('detallesTelar').classList.remove('hidden');
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Cerrar modal al hacer clic fuera
      document.getElementById('modalTelaresNotificar')?.addEventListener('click', function(event) {
        if (event.target === this) {
          cerrarModalTelares();
        }
      });

      // Buscar automáticamente cuando cambie el select de telar
      document.getElementById('selectTelar')?.addEventListener('change', function() {
        buscarDetallesTelar();
      });

      // Buscar automáticamente cuando cambie el tipo (rizo/pie)
      document.querySelectorAll('input[name="tipoTelar"]').forEach(radio => {
        radio.addEventListener('change', function() {
          buscarDetallesTelar();
        });
      });
    });
  </script>
    </body>
    </html>
