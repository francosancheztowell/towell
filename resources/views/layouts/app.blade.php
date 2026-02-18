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



  <!-- ====== Modal Atado de Julio ====== -->
  <div id="modalTelaresNotificar" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-2">
      <!-- Header del Modal -->
      <div class="flex items-center justify-between p-5 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800">Atado de Julio</h2>
        <button type="button" onclick="cerrarModalTelares()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body del Modal -->
      <div class="p-6">
        <!-- Formulario de Selección -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
          <!-- Select de Telar -->
          <div>
            <label for="selectTelar" class="block text-base font-semibold text-gray-700 mb-2">
              Seleccionar Telar
            </label>
            <select id="selectTelar" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">-- Seleccione un telar --</option>
            </select>
          </div>

          <!-- Checkboxes de Tipo -->
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-2">Tipo</label>
            <div class="flex gap-6 items-center h-12">
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="tipoTelar" id="radioRizo" value="rizo" class="form-radio h-6 w-6 text-blue-600">
                <span class="ml-2 text-lg text-gray-700 font-medium">Rizo</span>
              </label>

              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="tipoTelar" id="radioPie" value="pie" class="form-radio h-6 w-6 text-blue-600">
                <span class="ml-2 text-lg text-gray-700 font-medium">Pie</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Detalles del Telar -->
        <div id="detallesTelar" class="hidden bg-gradient-to-br from-blue-50 to-gray-50 rounded-lg p-6 border-2 border-blue-200">
          <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
            Información del Telar
          </h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">No. Telar</label>
              <input type="text" id="detalle_no_telar" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">Cuenta</label>
              <input type="text" id="detalle_cuenta" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">Calibre</label>
              <input type="text" id="detalle_calibre" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">Tipo</label>
              <input type="text" id="detalle_tipo" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">Tipo Atado</label>
              <input type="text" id="detalle_tipo_atado" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">No. Orden</label>
              <input type="text" id="detalle_no_orden" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">No. Julio</label>
              <input type="text" id="detalle_no_julio" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">Metros</label>
              <input type="text" id="detalle_metros" readonly class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-600 mb-1">Hora Paro</label>
            </div>
              <input type="text" id="detalle_hora_paro" readonly class="w-full px-4 py-3 bg-green-50 border border-green-300 rounded-md text-3xl font-bold text-green-700 text-center">
          </div>
        </div>

        <!-- Mensaje cuando no hay datos -->
        <div id="mensajeNoData" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-triangle text-yellow-400 text-lg"></i>
            </div>
            <div class="ml-3">
              <p class="text-base text-yellow-700">No se encontraron datos para el telar y tipo seleccionados.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer del Modal -->
      <div class="flex justify-end gap-3 p-4 border-t border-gray-200">
        <button type="button" onclick="notificarTelares()" class="px-5 py-2.5 text-base bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors font-medium">
          Notificar
        </button>
        <button type="button" onclick="cerrarModalTelares()" class="px-5 py-2.5 text-base bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors font-medium">
          Cerrar
        </button>
      </div>
    </div>
  </div>

  <!-- ====== Modal Cortado de Rollo ====== -->
  <div id="modalCortadoRollos" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl w-[96vw] sm:w-[90vw] lg:w-[94vw] max-w-none mx-1 my-2 h-[80vh] max-h-[80vh] flex flex-col">
      <!-- Header del Modal -->
      <div class="flex items-center justify-between gap-2 p-4 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 whitespace-nowrap">Cortado de Rollo</h2>
        <div class="flex-1 flex items-center gap-3">
          <label for="selectTelarCortado" class="text-lg font-semibold text-gray-700 whitespace-nowrap">
            Telar:
          </label>
          <select id="selectTelarCortado" class="flex-1 border-2 border-gray-300 rounded-lg px-4 py-2.5 text-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">-- Seleccione un telar --</option>
          </select>
        </div>
        <button type="button" id="closeModalCortado" class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body del Modal -->
      <div class="p-6 flex-1 min-h-0 overflow-hidden flex flex-col">

        <!-- Nivel 2: Tabla de Órdenes en Proceso -->
        <div id="tablaProduccionesCortadoContainer" class="mb-4" style="display: none;">
          <h3 class="text-lg font-semibold text-gray-800 mb-2">Órdenes en Proceso</h3>
          <div class="border-2 border-gray-300 rounded-lg overflow-hidden">
            <table class="w-full bg-white">
              <thead class="bg-green-100">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Salón</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">No. Producción</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Fecha Inicio</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Tamaño</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Producto</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase">Seleccionar</th>
                </tr>
              </thead>
            </table>
            <div class="overflow-x-auto overflow-y-auto max-h-[150px]">
              <table class="w-full bg-white">
                <tbody id="tablaProduccionesCortadoBody" class="divide-y divide-gray-200">
                </tbody>
              </table>
            </div>
            <div id="noDataProduccionesCortado" class="hidden text-center py-3 text-gray-500"></div>
          </div>
        </div>

        <!-- Nivel 3: Tabla de Marbetes -->
        <div id="tablaProduccionCortadoContainer" class="flex-1 min-h-0" style="display: none;">
          <h3 class="text-xl font-semibold text-gray-800 mb-4">Seleccionar Marbete a Liberar</h3>
          <div class="border-2 border-gray-300 rounded-lg overflow-hidden">
            <table class="w-full bg-white">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Cuantas</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Marbete</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Artículo</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Tamaño</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Orden</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Telar</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Piezas</th>
                  <th class="px-5 py-4 text-left text-base font-semibold text-gray-700 uppercase whitespace-nowrap">Salón</th>
                </tr>
              </thead>
            </table>
            <div class="overflow-x-auto overflow-y-auto max-h-[300px]">
              <table class="w-full bg-white">
                <tbody id="tablaProduccionCortadoBody" class="divide-y divide-gray-200">
                  <!-- Los datos se cargarán dinámicamente -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Mensaje de carga o error -->
        <div id="mensajeEstadoCortado" class="text-center text-lg text-gray-600 py-6 flex-shrink-0" style="display: none;"></div>
      </div>

      <!-- Footer del Modal -->
      <div class="flex justify-end gap-4 p-6 border-t border-gray-200 flex-shrink-0">
        <button type="button" id="closeModalCortadoBtn" class="px-4 py-2 text-base bg-gray-500 hover:bg-gray-600 text-white rounded-md transition-colors font-medium">
          Cerrar
        </button>
        <button type="button" id="btnNotificarCortado" class="px-4 py-2 text-base bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors font-medium" style="display: none;">
          Notificar
        </button>
      </div>
    </div>
  </div>


  <script>
    (function () {
      if (typeof window.CSS === 'undefined') {
        window.CSS = {};
      }
      if (typeof window.CSS.escape !== 'function') {
        window.CSS.escape = function (value) {
          if (arguments.length === 0) {
            throw new TypeError('CSS.escape requires an argument.');
          }
          var string = String(value);
          var length = string.length;
          var index = -1;
          var codeUnit;
          var result = '';
          var firstCodeUnit = string.charCodeAt(0);
          while (++index < length) {
            codeUnit = string.charCodeAt(index);
            if (codeUnit === 0x0000) {
              result += '\uFFFD';
              continue;
            }
            if (
              (codeUnit >= 0x0001 && codeUnit <= 0x001F) ||
              codeUnit === 0x007F ||
              (index === 0 && codeUnit >= 0x0030 && codeUnit <= 0x0039) ||
              (index === 1 &&
                codeUnit >= 0x0030 &&
                codeUnit <= 0x0039 &&
                firstCodeUnit === 0x002D)
            ) {
              result += '\\' + codeUnit.toString(16).toUpperCase() + ' ';
              continue;
            }
            if (
              codeUnit >= 0x0080 ||
              codeUnit === 0x002D ||
              codeUnit === 0x005F ||
              (codeUnit >= 0x0030 && codeUnit <= 0x0039) ||
              (codeUnit >= 0x0041 && codeUnit <= 0x005A) ||
              (codeUnit >= 0x0061 && codeUnit <= 0x007A)
            ) {
              result += string.charAt(index);
              continue;
            }
            result += '\\' + string.charAt(index);
          }
          return result;
        };
      }
    })();
  </script>

  <!-- ====== Scripts ====== -->
    <script src="{{ asset('js/app-core.js') }}?v={{ @filemtime(public_path('js/app-core.js')) ?: time() }}"></script>
    <script src="{{ asset('js/app-filters.js') }}"></script>

  @stack('scripts')

  <!-- Scripts específicos -->
  @if(request()->routeIs('catalogos.req-programa-tejido') || request()->is('planeacion/programa-tejido') || request()->routeIs('muestras.index') || request()->is('planeacion/muestras'))
    <script src="{{ asset('js/programa-tejido-menu.js') }}"></script>
  @endif

  <script src="{{ asset('js/simple-click-sounds.js') }}"></script>
  @if(config('app.pwa_enabled', true) && !config('app.service_worker_cleanup', false))
    <script src="{{ asset('js/app-pwa.js') }}"></script>
  @endif

  <script>
    // La función resetColumnsSpin ya está expuesta en app-filters.js

    // Funciones para modal de telares
    let telaresUsuario = [];
    let registroActualId = null;

    async function abrirModalTelares() {
      try {
        const response = await fetch('{{ route('notificar.atado.julio') }}?listado=1', {
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
        const response = await fetch(`{{ route('notificar.atado.julio') }}?no_telar=${telar}&tipo=${tipo}`, {
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
        const response = await fetch('{{ route('notificar.atado.julio.notificar') }}', {
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
          }).then(() => {
            cerrarModalTelares();
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

    // Funciones para modal de cortado de rollo
    async function abrirModalCortadoRollos() {
      await cargarTelaresCortadoRollos();
      document.getElementById('modalCortadoRollos').style.display = 'flex';
    }

    function cerrarModalCortadoRollos() {
      document.getElementById('modalCortadoRollos').style.display = 'none';
      const select = document.getElementById('selectTelarCortado');
      if (select) select.value = '';
      const tabla = document.getElementById('tablaProduccionCortadoBody');
      if (tabla) tabla.innerHTML = '';
      const container = document.getElementById('tablaProduccionCortadoContainer');
      if (container) container.style.display = 'none';
      const mensaje = document.getElementById('mensajeEstadoCortado');
      if (mensaje) mensaje.style.display = 'none';
      const btn = document.getElementById('btnNotificarCortado');
      if (btn) btn.style.display = 'none';
    }

    async function cargarTelaresCortadoRollos() {
      const select = document.getElementById('selectTelarCortado');
      if (!select) return;

      select.innerHTML = '<option value="">-- Seleccione un telar --</option>';

      try {
        const response = await fetch('{{ route('notificar.cortado.rollo') }}?listado=1', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (!data.telares || data.telares.length === 0) {
          return;
        }

        data.telares.forEach(telar => {
          const option = document.createElement('option');
          option.value = telar;
          option.textContent = `Telar ${telar}`;
          select.appendChild(option);
        });
      } catch (error) {
        console.error('Error al cargar telares de rollo:', error);
      }
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

      // Modal cortado de rollo
      const modalCortado = document.getElementById('modalCortadoRollos');
      const closeModalCortado = document.getElementById('closeModalCortado');
      const closeModalCortadoBtn = document.getElementById('closeModalCortadoBtn');
      const selectTelarCortado = document.getElementById('selectTelarCortado');
      const tablaProduccionesCortadoContainer = document.getElementById('tablaProduccionesCortadoContainer');
      const tablaProduccionesCortadoBody = document.getElementById('tablaProduccionesCortadoBody');
      const noDataProduccionesCortado = document.getElementById('noDataProduccionesCortado');
      const tablaCortadoContainer = document.getElementById('tablaProduccionCortadoContainer');
      const tablaCortadoBody = document.getElementById('tablaProduccionCortadoBody');
      const mensajeCortado = document.getElementById('mensajeEstadoCortado');
      const btnNotificarCortado = document.getElementById('btnNotificarCortado');

      let ordenCortadoActual = null;
      let produccionSeleccionada = null;
      let datosCortado = [];
      let telarActualCortado = null;

      function mostrarMensajeCortado(mensaje, tipo) {
        mensajeCortado.textContent = mensaje;
        mensajeCortado.className = `text-center mb-4 ${tipo === 'error' ? 'text-red-600' : tipo === 'info' ? 'text-blue-600' : 'text-gray-500'}`;
        mensajeCortado.style.display = 'block';
        tablaCortadoContainer.style.display = 'none';
        btnNotificarCortado.style.display = 'none';
      }

      function ocultarMensajeCortado() {
        mensajeCortado.style.display = 'none';
      }

      // Nivel 1 → 2: Cargar TODAS las órdenes en proceso del telar
      function cargarOrdenesEnProceso(telarId) {
        telarActualCortado = telarId;
        produccionSeleccionada = null;
        tablaCortadoContainer.style.display = 'none';
        btnNotificarCortado.style.display = 'none';
        ocultarMensajeCortado();

        // Mostrar loading
        tablaProduccionesCortadoBody.innerHTML = `
          <tr>
            <td colspan="6" class="px-3 py-3 text-center text-gray-500">
              <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <p class="mt-2">Cargando órdenes en proceso...</p>
            </td>
          </tr>
        `;
        tablaProduccionesCortadoContainer.style.display = 'block';
        noDataProduccionesCortado.classList.add('hidden');

        // Petición AJAX para TODAS las órdenes en proceso
        fetch(`/tejedores/cortadoderollo/telar/${telarId}/ordenes-en-proceso`)
          .then(response => response.json())
          .then(data => {
            tablaProduccionesCortadoBody.innerHTML = '';
            
            if (data.success && data.ordenes && data.ordenes.length > 0) {
              data.ordenes.forEach(orden => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-green-50 transition-colors cursor-pointer';
                row.innerHTML = `
                  <td class="px-3 py-3 whitespace-nowrap text-xs font-medium text-gray-900">
                    ${orden.SalonTejidoId ?? 'N/A'}
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap text-xs font-bold text-green-700">
                    ${orden.NoProduccion}
                    <span class="ml-2 px-2 py-0.5 bg-green-500 text-white text-xs rounded-full">EN PROCESO</span>
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">
                    ${orden.FechaInicio ? new Date(orden.FechaInicio).toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'}) : 'N/A'}
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">
                    ${orden.TamanoClave ?? 'N/A'}
                  </td>
                  <td class="px-3 py-3 text-xs text-gray-600 break-words">
                    ${orden.NombreProducto || 'N/A'}
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap text-center">
                    <input type="checkbox"
                           class="checkbox-produccion-cortado w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2 cursor-pointer"
                           data-telar="${telarId}"
                           data-salon="${orden.SalonTejidoId ?? ''}"
                           data-produccion="${orden.NoProduccion}"
                           onchange="seleccionarProduccionCortado(this)">
                  </td>
                `;
                tablaProduccionesCortadoBody.appendChild(row);
              });
            } else {
              noDataProduccionesCortado.classList.remove('hidden');
              noDataProduccionesCortado.innerHTML = '<p class="text-sm">No hay órdenes en proceso para este telar</p>';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            tablaProduccionesCortadoBody.innerHTML = `
              <tr>
                <td colspan="6" class="px-3 py-3 text-center text-red-500">
                  Error al cargar las órdenes en proceso
                </td>
              </tr>
            `;
          });
      }

      // Nivel 2 → 3: Función global para seleccionar una orden y cargar sus marbetes
      window.seleccionarProduccionCortado = function(checkbox) {
        // Desmarcar otros checkboxes
        document.querySelectorAll('.checkbox-produccion-cortado').forEach(cb => {
          if (cb !== checkbox) {
            cb.checked = false;
          }
        });

        // Quitar selección visual de todas las filas
        document.querySelectorAll('#tablaProduccionesCortadoBody tr').forEach(row => {
          row.classList.remove('bg-green-200');
        });

        if (!checkbox.checked) {
          produccionSeleccionada = null;
          tablaCortadoContainer.style.display = 'none';
          btnNotificarCortado.style.display = 'none';
          ocultarMensajeCortado();
          return;
        }

        // Marcar la fila seleccionada
        checkbox.closest('tr').classList.add('bg-green-200');

        // Guardar producción seleccionada
        const noTelar = checkbox.dataset.telar;
        const salon = checkbox.dataset.salon;
        const noProduccion = checkbox.dataset.produccion;

        produccionSeleccionada = {
          NoProduccion: noProduccion,
          NoTelarId: noTelar,
          SalonTejidoId: salon
        };

        // Cargar marbetes de la orden seleccionada
        cargarMarbetesCortado(noProduccion, noTelar, salon);
      };

      // Cargar marbetes de una producción específica
      function cargarMarbetesCortado(noProduccion, noTelar, salon) {
        mostrarMensajeCortado('Cargando marbetes de la producción seleccionada...', 'info');

        fetch(`{{ route('notificar.cortado.rollo.datos.produccion') }}?no_produccion=${encodeURIComponent(noProduccion)}&no_telar=${encodeURIComponent(noTelar)}&salon=${encodeURIComponent(salon)}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (!data.success || data.datos.length === 0) {
            let mensajeError = data.error || 'No se encontraron marbetes disponibles';
            if (data.mensaje) {
              mensajeError += '\n' + data.mensaje;
            }
            mostrarMensajeCortado(mensajeError, 'error');
            console.log('Debug validación:', data.debug);
            return;
          }

          datosCortado = data.datos;
          renderizarTablaCortado(datosCortado);

          ocultarMensajeCortado();
          tablaCortadoContainer.style.display = 'block';
          btnNotificarCortado.style.display = 'inline-block';
        })
        .catch(error => {
          console.error('Error:', error);
          mostrarMensajeCortado('Error al cargar marbetes: ' + error.message, 'error');
        });
      }

      function renderizarTablaCortado(datos) {
        tablaCortadoBody.innerHTML = '';

        function formatearPiezas(valor) {
          const n = Number(valor);
          return Number.isFinite(n) ? Math.trunc(n) : 'N/A';
        }

        datos.forEach((dato, index) => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-blue-50 cursor-pointer transition-colors';
          row.dataset.marbete = JSON.stringify(dato);
          row.dataset.index = index;

          row.innerHTML = `
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.CUANTAS || 'N/A'}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.PurchBarCode || 'N/A'}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.ItemId || 'N/A'}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.InventSizeId || 'N/A'}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.InventBatchId || 'N/A'}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.WMSLocationId || 'N/A'}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap text-right">${formatearPiezas(dato.QtySched)}</td>
            <td class="px-5 py-4 text-lg text-gray-900 whitespace-nowrap">${dato.Salon || 'N/A'}</td>
          `;

          row.addEventListener('click', function() {
            document.querySelectorAll('#tablaProduccionCortadoBody tr').forEach(r => {
              r.classList.remove('bg-blue-200', 'selected');
            });

            this.classList.add('bg-blue-200', 'selected');
          });

          tablaCortadoBody.appendChild(row);
        });
      }

      closeModalCortado?.addEventListener('click', cerrarModalCortadoRollos);
      closeModalCortadoBtn?.addEventListener('click', cerrarModalCortadoRollos);

      modalCortado?.addEventListener('click', function(event) {
        if (event.target === this) {
          cerrarModalCortadoRollos();
        }
      });

      selectTelarCortado?.addEventListener('change', function() {
        const noTelar = this.value;

        if (!noTelar) {
          tablaProduccionesCortadoContainer.style.display = 'none';
          tablaCortadoContainer.style.display = 'none';
          btnNotificarCortado.style.display = 'none';
          ocultarMensajeCortado();
          return;
        }

        // Cargar las órdenes en proceso del telar (Nivel 1 → 2)
        cargarOrdenesEnProceso(noTelar);
      });

      btnNotificarCortado?.addEventListener('click', async function() {
        const filaSeleccionada = document.querySelector('#tablaProduccionCortadoBody tr.selected');

        if (!filaSeleccionada) {
          Swal.fire({
            icon: 'warning',
            title: 'Selección requerida',
            text: 'Debe seleccionar un marbete de la tabla',
            confirmButtonColor: '#3b82f6'
          });
          return;
        }

        const marbete = JSON.parse(filaSeleccionada.dataset.marbete);
        const marbetesSeleccionados = [marbete];

        const confirmacion = await Swal.fire({
          icon: 'question',
          title: '¿Confirmar liberación?',
          text: `¿Está seguro de liberar el marbete ${marbete.PurchBarCode}?`,
          showCancelButton: true,
          confirmButtonColor: '#3b82f6',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Sí, liberar',
          cancelButtonText: 'Cancelar'
        });

        if (!confirmacion.isConfirmed) {
          return;
        }

        try {
          mostrarMensajeCortado('Insertando marbetes en TelMarbeteLiberado...', 'info');

          Swal.fire({
            title: 'Procesando...',
            text: 'Liberando marbete',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          const response = await fetch('{{ route('notificar.cortado.rollo.insertar') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
              marbetes: marbetesSeleccionados
            })
          });

          const data = await response.json();

          if (!data.success) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: data.error || 'No se pudieron insertar los marbetes',
              confirmButtonColor: '#ef4444'
            });
            mostrarMensajeCortado('Error al insertar: ' + (data.error || 'Error desconocido'), 'error');
            return;
          }

          await Swal.fire({
            icon: 'success',
            title: '¡Marbete liberado!',
            text: data.mensaje,
            confirmButtonColor: '#22c55e',
            timer: 2000,
            timerProgressBar: true
          });

          cerrarModalCortadoRollos();
        } catch (error) {
          console.error('Error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'Error al insertar marbetes: ' + error.message,
            confirmButtonColor: '#ef4444'
          });
        }
      });
    });
  </script>
    </body>
    </html>
