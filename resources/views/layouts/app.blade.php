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
        @media (min-width: 768px) and (max-width: 1024px) {
            #modalCortadoRollos {
                align-items: flex-start;
                padding: 0.5rem;
            }
            #modalCortadoRollos > div {
                max-width: 98vw;
                margin-top: 0.5rem;
                margin-bottom: 0.5rem;
                height: calc(100dvh - 1rem) !important;
                max-height: calc(100dvh - 1rem) !important;
            }
            #modalCortadoRollos .tabla-ordenes-cortado {
                max-height: 13rem !important;
            }
            #modalCortadoRollos .tabla-marbetes-cortado {
                max-height: 12rem !important;
            }
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
  <div id="modalCortadoRollos" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full z-50 overflow-y-auto flex items-start justify-center p-2 sm:p-4" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-[96vw] lg:max-w-6xl xl:max-w-7xl 2xl:max-w-[88rem] mx-auto my-2 sm:my-4 flex flex-col"
         style="height: calc(100vh - 1rem); height: calc(100dvh - 1rem); max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem);">

      <!-- Header del Modal -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-5 p-4 sm:p-6 border-b border-gray-200 flex-shrink-0 mb-14">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 whitespace-nowrap">Cortado de Rollo</h2>
        <div class="flex-1 w-full sm:w-auto flex items-center gap-2 sm:gap-4">
          <label for="selectTelarCortado" class="text-base sm:text-lg font-semibold text-gray-700 whitespace-nowrap">Telar:</label>
          <select id="selectTelarCortado" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-base sm:text-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">-- Seleccione --</option>
          </select>
        </div>
        <button type="button" id="closeModalCortado" class="absolute top-4 right-4 sm:relative sm:top-auto sm:right-auto text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
          <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body del Modal (scrollable) -->
      <div class="p-4 sm:p-6 flex-1 min-h-0 overflow-y-auto flex flex-col gap-5">

        <!-- Nivel 2: Tabla de Órdenes del Telar -->
        <div id="tablaProduccionesCortadoContainer" style="display: none;">
          <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-3">Órdenes del Telar</h3>
          <div class="border border-blue-200 rounded-lg overflow-hidden">
            <div class="tabla-ordenes-cortado overflow-x-auto overflow-y-auto" style="max-height: 15rem; max-height: min(26dvh, 15rem); -webkit-overflow-scrolling: touch;">
              <table class="w-full text-sm sm:text-base table-fixed">
                <thead class="bg-blue-50 sticky top-0 z-[1]">
                  <tr>
                    <th class="w-[10%] px-4 py-3 text-left text-xs sm:text-sm font-semibold text-blue-800 uppercase">Salón</th>
                    <th class="w-[25%] px-4 py-3 text-left text-xs sm:text-sm font-semibold text-blue-800 uppercase">No. Producción</th>
                    <th class="w-[15%] px-4 py-3 text-left text-xs sm:text-sm font-semibold text-blue-800 uppercase">Fecha</th>
                    <th class="w-[12%] px-4 py-3 text-left text-xs sm:text-sm font-semibold text-blue-800 uppercase">Tamaño</th>
                    <th class="w-[30%] px-4 py-3 text-left text-xs sm:text-sm font-semibold text-blue-800 uppercase">Producto</th>
                    <th class="w-[8%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase"></th>
                  </tr>
                </thead>
                <tbody id="tablaProduccionesCortadoBody" class="bg-white divide-y divide-gray-100">
                </tbody>
              </table>
            </div>
            <div id="noDataProduccionesCortado" class="hidden text-center py-4 text-gray-500 text-base"></div>
          </div>
        </div>

        <!-- Nivel 3: Tabla de Marbetes -->
        <div id="tablaProduccionCortadoContainer" style="display: none;">
          <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-3">Seleccionar Marbete a Liberar</h3>
          <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="tabla-marbetes-cortado overflow-x-auto overflow-y-auto" style="max-height: 13rem; max-height: min(30dvh, 13rem); -webkit-overflow-scrolling: touch;">
              <table class="w-full text-sm sm:text-base table-fixed">
                <thead class="bg-gray-50 sticky top-0 z-[1]">
                  <tr>
                    <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Cuantas</th>
                    <th class="w-[16%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Marbete</th>
                    <th class="w-[14%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Artículo</th>
                    <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Tamaño</th>
                    <th class="w-[16%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Orden</th>
                    <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Telar</th>
                    <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Piezas</th>
                    <th class="w-[14%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-gray-600 uppercase">Salón</th>
                  </tr>
                </thead>
                <tbody id="tablaProduccionCortadoBody" class="bg-white divide-y divide-gray-100">
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Mensaje de carga o error -->
        <div id="mensajeEstadoCortado" class="text-center text-base sm:text-lg text-gray-600 py-5" style="display: none;"></div>
      </div>

      <!-- Footer del Modal -->
      <div class="flex justify-end gap-3 px-4 sm:px-6 py-4 border-t border-gray-200 flex-shrink-0">
        <button type="button" id="closeModalCortadoBtn" class="px-5 py-2.5 text-base bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors font-medium border border-gray-300">
          Cerrar
        </button>
        <button type="button" id="btnNotificarCortado" class="px-5 py-2.5 text-base bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium shadow-sm" style="display: none;">
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
        mensajeCortado.className = `text-center text-base sm:text-lg mb-4 ${tipo === 'error' ? 'text-red-600' : tipo === 'info' ? 'text-blue-600' : 'text-gray-500'}`;
        mensajeCortado.style.display = 'block';
        tablaCortadoContainer.style.display = 'none';
        btnNotificarCortado.style.display = 'none';
      }

      function ocultarMensajeCortado() {
        mensajeCortado.style.display = 'none';
      }

      // Nivel 1 → 2: Cargar TODAS las órdenes del telar
      function cargarOrdenesEnProceso(telarId) {
        telarActualCortado = telarId;
        produccionSeleccionada = null;
        tablaCortadoContainer.style.display = 'none';
        btnNotificarCortado.style.display = 'none';
        ocultarMensajeCortado();

        // Mostrar loading
        tablaProduccionesCortadoBody.innerHTML = `
          <tr>
            <td colspan="6" class="px-4 py-4 text-center text-gray-500 text-base">
              <svg class="animate-spin h-6 w-6 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <p class="mt-2">Cargando órdenes del telar...</p>
            </td>
          </tr>
        `;
        tablaProduccionesCortadoContainer.style.display = 'block';
        noDataProduccionesCortado.classList.add('hidden');

        // Petición AJAX para TODAS las órdenes del telar
        fetch(`/tejedores/cortadoderollo/telar/${telarId}/ordenes-en-proceso`)
          .then(response => response.json())
          .then(data => {
            tablaProduccionesCortadoBody.innerHTML = '';
            
            if (data.success && data.ordenes && data.ordenes.length > 0) {
              data.ordenes.forEach(orden => {
                const row = document.createElement('tr');
                const enProceso = orden.EnProceso == 1;
                row.className = 'hover:bg-gray-50 transition-colors cursor-pointer orden-row';
                
                const badgeEnProceso = enProceso 
                  ? '<span class="ml-1 inline-flex px-1.5 py-0.5 bg-green-500 text-white text-[10px] rounded-full font-medium leading-none align-middle">EN PROCESO</span>' 
                  : '';
                
                row.innerHTML = `
                  <td class="w-[10%] px-4 py-3 text-sm text-gray-700 truncate">${orden.SalonTejidoId ?? 'N/A'}</td>
                  <td class="w-[25%] px-4 py-3 text-sm text-gray-900 font-semibold truncate">${orden.NoProduccion}${badgeEnProceso}</td>
                  <td class="w-[15%] px-4 py-3 text-sm text-gray-600 truncate">${orden.FechaInicio ? new Date(orden.FechaInicio).toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'}) : 'N/A'}</td>
                  <td class="w-[12%] px-4 py-3 text-sm text-gray-600 truncate">${orden.TamanoClave ?? 'N/A'}</td>
                  <td class="w-[30%] px-4 py-3 text-sm text-gray-600 truncate" title="${orden.NombreProducto || 'N/A'}">${orden.NombreProducto || 'N/A'}</td>
                  <td class="w-[8%] px-4 py-3 text-center">
                    <input type="checkbox"
                           class="checkbox-produccion-cortado w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
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
              noDataProduccionesCortado.innerHTML = '<p class="text-base">No hay órdenes disponibles para este telar</p>';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            tablaProduccionesCortadoBody.innerHTML = `
              <tr>
                <td colspan="6" class="px-4 py-4 text-center text-red-500 text-base">
                  Error al cargar las órdenes del telar
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
          row.classList.remove('bg-blue-600', 'selected-orden');
          row.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
            if (!td.classList.contains('text-gray-700') && !td.classList.contains('text-gray-900') && !td.classList.contains('text-gray-600')) {
              td.classList.add('text-gray-700');
            }
          });
        });

        if (!checkbox.checked) {
          produccionSeleccionada = null;
          tablaCortadoContainer.style.display = 'none';
          btnNotificarCortado.style.display = 'none';
          ocultarMensajeCortado();
          return;
        }

        // Marcar la fila seleccionada con fondo azul y texto blanco
        const selectedRow = checkbox.closest('tr');
        selectedRow.classList.add('bg-blue-600', 'selected-orden');
        selectedRow.querySelectorAll('td').forEach(td => {
          td.classList.remove('text-gray-900', 'text-gray-600', 'text-gray-700');
          td.classList.add('text-white');
        });

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
          row.className = 'hover:bg-blue-50 cursor-pointer transition-colors marbete-row';
          row.dataset.marbete = JSON.stringify(dato);
          row.dataset.index = index;

          row.innerHTML = `
            <td class="w-[10%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.CUANTAS || 'N/A'}</td>
            <td class="w-[16%] px-4 py-3 text-sm text-gray-900 text-center font-medium truncate">${dato.PurchBarCode || 'N/A'}</td>
            <td class="w-[14%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.ItemId || 'N/A'}</td>
            <td class="w-[10%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.InventSizeId || 'N/A'}</td>
            <td class="w-[16%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.InventBatchId || 'N/A'}</td>
            <td class="w-[10%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.WMSLocationId || 'N/A'}</td>
            <td class="w-[10%] px-4 py-3 text-sm text-gray-700 text-center truncate">${formatearPiezas(dato.QtySched)}</td>
            <td class="w-[14%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.Salon || 'N/A'}</td>
          `;

          row.addEventListener('click', function() {
            document.querySelectorAll('#tablaProduccionCortadoBody tr').forEach(r => {
              r.classList.remove('bg-blue-500', 'selected');
              r.querySelectorAll('td').forEach(td => {
                td.className = td.className.replace('text-white', 'text-gray-700').replace('text-gray-900 text-white', 'text-gray-900');
                if (!td.classList.contains('text-gray-700') && !td.classList.contains('text-gray-900')) {
                  td.classList.add('text-gray-700');
                }
              });
            });

            this.classList.add('bg-blue-500', 'selected');
            this.querySelectorAll('td').forEach(td => {
              td.classList.remove('text-gray-700', 'text-gray-900');
              td.classList.add('text-white');
            });
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

        // Cargar las órdenes del telar (Nivel 1 → 2)
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
