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
    </style>
</head>

<body class="min-h-screen flex flex-col overflow-hidden h-screen bg-gradient-to-b from-blue-400 to-blue-200 relative" style="touch-action: manipulation; -webkit-touch-callout: none; -webkit-user-select: none; user-select: none;">
    <x-layout.global-loader />

    <x-navbar.navbar />

  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">@csrf</form>

        <main class="overflow-x-hidden overflow-y-auto max-w-full flex-1" style="padding-top: 64px; height: 100vh; max-height: 100vh;">
            @yield('content')
        </main>



  <!-- ====== Modal Atado de Julio ====== -->
  <div id="modalTelaresNotificar" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4">
      <!-- Header del Modal -->
      <div class="flex items-center justify-between p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">Atado de Julio</h2>
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

  <!-- ====== Modal Cortado de Rollo ====== -->
  <div id="modalCortadoRollos" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-6xl w-[96vw] mx-2 my-4 h-[85vh] flex flex-col">
      <!-- Header del Modal -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">Cortado de Rollo</h2>
        <button type="button" id="closeModalCortado" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body del Modal -->
      <div class="p-4 flex-1 overflow-hidden flex flex-col">
        <!-- Select de Telar del Usuario -->
        <div class="mb-3 flex-shrink-0">
          <label for="selectTelarCortado" class="block text-sm font-medium text-gray-700 mb-2">
            Seleccionar Telar
          </label>
          <select id="selectTelarCortado" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">-- Seleccione un telar --</option>
          </select>
        </div>

        <!-- Tabla de Datos de Producción -->
        <div id="tablaProduccionCortadoContainer" class="flex-shrink-0" style="display: none;">
          <h3 class="text-base font-semibold text-gray-800 mb-2">Seleccionar Marbete a Liberar</h3>
          <div class="border border-gray-300 rounded-lg overflow-hidden">
            <table class="w-full bg-white">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Cuantas</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Marbete</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Artículo</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Tamaño</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Orden</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Telar</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Piezas</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Salón</th>
                </tr>
              </thead>
            </table>
            <div class="overflow-x-auto overflow-y-auto" style="max-height: 250px;">
              <table class="w-full bg-white">
                <tbody id="tablaProduccionCortadoBody" class="divide-y divide-gray-200">
                  <!-- Los datos se cargarán dinámicamente -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Mensaje de carga o error -->
        <div id="mensajeEstadoCortado" class="text-center text-gray-600 py-4 flex-shrink-0" style="display: none;"></div>
      </div>

      <!-- Footer del Modal -->
      <div class="flex justify-end gap-2 p-4 border-t border-gray-200 flex-shrink-0">
        <button type="button" id="closeModalCortadoBtn" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
          Cerrar
        </button>
        <button type="button" id="btnNotificarCortado" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors" style="display: none;">
          Notificar
        </button>
      </div>
    </div>
  </div>

  <!-- ====== Polyfill CSS.escape (debe estar ANTES de los scripts) ====== -->
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
    <script src="{{ asset('js/app-core.js') }}"></script>
    <script src="{{ asset('js/app-filters.js') }}"></script>

  @stack('scripts')

  <!-- Scripts específicos -->
  @if(request()->routeIs('catalogos.req-programa-tejido') || request()->is('planeacion/programa-tejido'))
    <script src="{{ asset('js/programa-tejido-menu.js') }}"></script>
  @endif

  <script src="{{ asset('js/simple-click-sounds.js') }}"></script>
  <script src="{{ asset('js/app-pwa.js') }}"></script>

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
      const tablaCortadoContainer = document.getElementById('tablaProduccionCortadoContainer');
      const tablaCortadoBody = document.getElementById('tablaProduccionCortadoBody');
      const mensajeCortado = document.getElementById('mensajeEstadoCortado');
      const btnNotificarCortado = document.getElementById('btnNotificarCortado');

      let ordenCortadoActual = null;
      let datosCortado = [];

      function mostrarMensajeCortado(mensaje, tipo) {
        mensajeCortado.textContent = mensaje;
        mensajeCortado.className = `text-center mb-4 ${tipo === 'error' ? 'text-red-600' : tipo === 'info' ? 'text-blue-600' : 'text-gray-500'}`;
        mensajeCortado.style.display = 'block';
        tablaCortadoContainer.style.display = 'none';
        btnNotificarCortado.style.display = 'none';
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
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.CUANTAS || 'N/A'}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.PurchBarCode || 'N/A'}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.ItemId || 'N/A'}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.InventSizeId || 'N/A'}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.InventBatchId || 'N/A'}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.WMSLocationId || 'N/A'}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap text-right">${formatearPiezas(dato.QtySched)}</td>
            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">${dato.Salon || 'N/A'}</td>
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

      selectTelarCortado?.addEventListener('change', async function() {
        const noTelar = this.value;

        if (!noTelar) {
          tablaCortadoContainer.style.display = 'none';
          btnNotificarCortado.style.display = 'none';
          return;
        }

        mostrarMensajeCortado('Buscando orden de producción...', 'info');

        try {
          const responseOrden = await fetch(`{{ route('notificar.cortado.rollo.orden.produccion') }}?no_telar=${encodeURIComponent(noTelar)}`, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            }
          });

          const dataOrden = await responseOrden.json();

          if (!dataOrden.success) {
            mostrarMensajeCortado(dataOrden.error || 'No se encontró orden activa', 'error');
            console.log('Debug orden:', dataOrden.debug);
            return;
          }

          ordenCortadoActual = dataOrden.orden;
          mostrarMensajeCortado('Cargando datos de producción desde TOW_PRO...', 'info');

          const responseDatos = await fetch(`{{ route('notificar.cortado.rollo.datos.produccion') }}?no_produccion=${encodeURIComponent(ordenCortadoActual.NoProduccion)}&no_telar=${encodeURIComponent(noTelar)}&salon=${encodeURIComponent(ordenCortadoActual.SalonTejidoId || '')}`, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            }
          });

          const dataDatos = await responseDatos.json();

          if (!dataDatos.success || dataDatos.datos.length === 0) {
            let mensajeError = dataDatos.error || 'No se encontraron datos de producción';
            if (dataDatos.mensaje) {
              mensajeError += '\n' + dataDatos.mensaje;
            }
            mostrarMensajeCortado(mensajeError, 'error');
            console.log('Debug validación:', dataDatos.debug);
            return;
          }

          datosCortado = dataDatos.datos;
          renderizarTablaCortado(datosCortado);

          mensajeCortado.style.display = 'none';
          tablaCortadoContainer.style.display = 'block';
          btnNotificarCortado.style.display = 'inline-block';
        } catch (error) {
          console.error('Error:', error);
          mostrarMensajeCortado('Error al cargar los datos: ' + error.message, 'error');
        }
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
