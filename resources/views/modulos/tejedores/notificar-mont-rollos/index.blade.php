@extends('layouts.app')

@section('page-title', 'Cortado de Rollo')

@section('content')
<div class="w-full p-3 sm:p-5 flex flex-col gap-5">

  {{-- Header --}}
  <div class="bg-white rounded-xl shadow border border-gray-200 flex flex-col sm:flex-row items-start sm:items-center gap-3 px-5 py-4">
    <h1 class="text-xl sm:text-2xl font-bold text-gray-800 whitespace-nowrap flex-shrink-0">Cortado de Rollo</h1>
    <div class="flex items-center gap-3 w-full sm:w-auto sm:flex-1 sm:justify-end">
      <label for="selectTelarCortado" class="text-base font-semibold text-gray-700 whitespace-nowrap">Telar:</label>
      <select id="selectTelarCortado" class="flex-1 sm:w-64 border border-gray-300 rounded-lg px-4 py-2.5 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
        <option value="">-- Seleccione --</option>
        @foreach($telaresUsuario as $telar)
          <option value="{{ $telar->NoTelarId }}" {{ $telarSeleccionado == $telar->NoTelarId ? 'selected' : '' }}>
            Telar {{ $telar->NoTelarId }}
          </option>
        @endforeach
      </select>
    </div>
  </div>

  {{-- Tabla Nivel 2: Órdenes del Telar --}}
  <div id="tablaProduccionesCortadoContainer" style="display: none;">
    <div class="bg-white rounded-xl shadow border border-blue-200 overflow-hidden">
      <div class="bg-blue-50 px-5 py-3 border-b border-blue-200">
        <h2 class="text-base sm:text-lg font-semibold text-blue-800">Órdenes del Telar</h2>
      </div>
      <div class="tabla-ordenes-cortado overflow-x-auto overflow-y-auto" style="max-height: 16rem; -webkit-overflow-scrolling: touch;">
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
          <tbody id="tablaProduccionesCortadoBody" class="bg-white divide-y divide-gray-100"></tbody>
        </table>
      </div>
      <div id="noDataProduccionesCortado" class="hidden text-center py-4 text-gray-500 text-base"></div>
    </div>
  </div>

  {{-- Tabla Nivel 3: Marbetes --}}
  <div id="tablaProduccionCortadoContainer" style="display: none;">
    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
      <div class="bg-gray-50 px-5 py-3 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-semibold text-gray-700">Seleccionar Marbete a Liberar</h2>
      </div>
      <div class="tabla-marbetes-cortado overflow-x-auto overflow-y-auto" style="max-height: 16rem; -webkit-overflow-scrolling: touch;">
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
          <tbody id="tablaProduccionCortadoBody" class="bg-white divide-y divide-gray-100"></tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Mensaje de estado --}}
  <div id="mensajeEstadoCortado" class="text-center text-base sm:text-lg text-gray-600 py-5 bg-white rounded-xl shadow border border-gray-200" style="display: none;"></div>

  {{-- Botón Notificar --}}
  <div class="flex justify-end" id="btnNotificarCortadoWrapper" style="display: none !important;">
    <button type="button" id="btnNotificarCortado"
      class="px-6 py-3 text-base bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium shadow-sm">
      Notificar
    </button>
  </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const selectTelarCortado       = document.getElementById('selectTelarCortado');
  const tablaProduccionesCortadoContainer = document.getElementById('tablaProduccionesCortadoContainer');
  const tablaProduccionesCortadoBody      = document.getElementById('tablaProduccionesCortadoBody');
  const noDataProduccionesCortado         = document.getElementById('noDataProduccionesCortado');
  const tablaCortadoContainer    = document.getElementById('tablaProduccionCortadoContainer');
  const tablaCortadoBody         = document.getElementById('tablaProduccionCortadoBody');
  const mensajeCortado           = document.getElementById('mensajeEstadoCortado');
  const btnNotificarWrapper      = document.getElementById('btnNotificarCortadoWrapper');
  const btnNotificarCortado      = document.getElementById('btnNotificarCortado');

  let produccionSeleccionada = null;

  // ── Helpers de mensaje ────────────────────────────────────────────────────
  function mostrarMensajeCortado(mensaje, tipo) {
    mensajeCortado.textContent = mensaje;
    mensajeCortado.className = `text-center text-base sm:text-lg py-5 bg-white rounded-xl shadow border border-gray-200
      ${tipo === 'error' ? 'text-red-600' : tipo === 'info' ? 'text-blue-600' : 'text-gray-500'}`;
    mensajeCortado.style.display = 'block';
    tablaCortadoContainer.style.display = 'none';
    btnNotificarWrapper.style.setProperty('display', 'none', 'important');
  }

  function ocultarMensajeCortado() {
    mensajeCortado.style.display = 'none';
  }

  // ── Nivel 1 → 2: Órdenes del telar ───────────────────────────────────────
  function cargarOrdenesEnProceso(telarId) {
    produccionSeleccionada = null;
    tablaCortadoContainer.style.display = 'none';
    btnNotificarWrapper.style.setProperty('display', 'none', 'important');
    ocultarMensajeCortado();

    tablaProduccionesCortadoBody.innerHTML = `
      <tr>
        <td colspan="6" class="px-4 py-6 text-center text-gray-500 text-base">
          <svg class="animate-spin h-6 w-6 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="mt-2">Cargando órdenes del telar...</p>
        </td>
      </tr>`;
    tablaProduccionesCortadoContainer.style.display = 'block';
    noDataProduccionesCortado.classList.add('hidden');

    fetch(`/tejedores/cortadoderollo/telar/${telarId}/ordenes-en-proceso`)
      .then(r => r.json())
      .then(data => {
        tablaProduccionesCortadoBody.innerHTML = '';

        if (data.success && data.ordenes && data.ordenes.length > 0) {
          data.ordenes.forEach(orden => {
            const row = document.createElement('tr');
            const enProceso = orden.EnProceso == 1;
            row.className = 'hover:bg-gray-50 transition-colors cursor-pointer orden-row';

            const badge = enProceso
              ? '<span class="ml-1 inline-flex px-1.5 py-0.5 bg-green-500 text-white text-[10px] rounded-full font-medium leading-none align-middle">EN PROCESO</span>'
              : '';

            row.innerHTML = `
              <td class="w-[10%] px-4 py-3 text-sm text-gray-700 truncate">${orden.SalonTejidoId ?? 'N/A'}</td>
              <td class="w-[25%] px-4 py-3 text-sm text-gray-900 font-semibold truncate">${orden.NoProduccion}${badge}</td>
              <td class="w-[15%] px-4 py-3 text-sm text-gray-600 truncate">${orden.FechaInicio ? new Date(orden.FechaInicio).toLocaleDateString('es-ES', {day:'2-digit',month:'2-digit',year:'numeric'}) : 'N/A'}</td>
              <td class="w-[12%] px-4 py-3 text-sm text-gray-600 truncate">${orden.TamanoClave ?? 'N/A'}</td>
              <td class="w-[30%] px-4 py-3 text-sm text-gray-600 truncate" title="${orden.NombreProducto || 'N/A'}">${orden.NombreProducto || 'N/A'}</td>
              <td class="w-[8%] px-4 py-3 text-center">
                <input type="checkbox"
                       class="checkbox-produccion-cortado w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
                       data-telar="${telarId}"
                       data-salon="${orden.SalonTejidoId ?? ''}"
                       data-produccion="${orden.NoProduccion}"
                       onchange="seleccionarProduccionCortado(this)">
              </td>`;
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
          </tr>`;
      });
  }

  // ── Nivel 2 → 3: Seleccionar orden y cargar marbetes ─────────────────────
  window.seleccionarProduccionCortado = function (checkbox) {
    document.querySelectorAll('.checkbox-produccion-cortado').forEach(cb => {
      if (cb !== checkbox) cb.checked = false;
    });

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
      btnNotificarWrapper.style.setProperty('display', 'none', 'important');
      ocultarMensajeCortado();
      return;
    }

    const selectedRow = checkbox.closest('tr');
    selectedRow.classList.add('bg-blue-600', 'selected-orden');
    selectedRow.querySelectorAll('td').forEach(td => {
      td.classList.remove('text-gray-900', 'text-gray-600', 'text-gray-700');
      td.classList.add('text-white');
    });

    const noTelar      = checkbox.dataset.telar;
    const salon        = checkbox.dataset.salon;
    const noProduccion = checkbox.dataset.produccion;

    produccionSeleccionada = { NoProduccion: noProduccion, NoTelarId: noTelar, SalonTejidoId: salon };

    cargarMarbetesCortado(noProduccion, noTelar, salon);
  };

  // ── Cargar marbetes ───────────────────────────────────────────────────────
  function cargarMarbetesCortado(noProduccion, noTelar, salon) {
    mostrarMensajeCortado('Cargando marbetes de la producción seleccionada...', 'info');

    fetch(`{{ route('notificar.cortado.rollo.datos.produccion') }}?no_produccion=${encodeURIComponent(noProduccion)}&no_telar=${encodeURIComponent(noTelar)}&salon=${encodeURIComponent(salon)}`)
      .then(r => r.json())
      .then(data => {
        if (!data.success || data.datos.length === 0) {
          let msg = data.error || 'No se encontraron marbetes disponibles';
          if (data.mensaje) msg += '\n' + data.mensaje;
          mostrarMensajeCortado(msg, 'error');
          return;
        }

        renderizarTablaCortado(data.datos);
        ocultarMensajeCortado();
        tablaCortadoContainer.style.display = 'block';
        btnNotificarWrapper.style.removeProperty('display');
        btnNotificarWrapper.style.display = 'flex';
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
        <td class="w-[14%] px-4 py-3 text-sm text-gray-700 text-center truncate">${dato.Salon || 'N/A'}</td>`;

      row.addEventListener('click', function () {
        document.querySelectorAll('#tablaProduccionCortadoBody tr').forEach(r => {
          r.classList.remove('bg-blue-500', 'selected');
          r.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
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

  // ── Select de telar ───────────────────────────────────────────────────────
  selectTelarCortado?.addEventListener('change', function () {
    const noTelar = this.value;
    if (!noTelar) {
      tablaProduccionesCortadoContainer.style.display = 'none';
      tablaCortadoContainer.style.display = 'none';
      btnNotificarWrapper.style.setProperty('display', 'none', 'important');
      ocultarMensajeCortado();
      return;
    }
    cargarOrdenesEnProceso(noTelar);
  });

  // Si ya hay un telar pre-seleccionado por PHP, cargar sus órdenes
  if (selectTelarCortado?.value) {
    cargarOrdenesEnProceso(selectTelarCortado.value);
  }

  // ── Botón Notificar ───────────────────────────────────────────────────────
  btnNotificarCortado?.addEventListener('click', async function () {
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

    if (!confirmacion.isConfirmed) return;

    try {
      Swal.fire({
        title: 'Procesando...',
        text: 'Liberando marbete',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => { Swal.showLoading(); }
      });

      const response = await fetch('{{ route('notificar.cortado.rollo.insertar') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ marbetes: [marbete] })
      });

      const data = await response.json();

      if (!data.success) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.error || 'No se pudieron insertar los marbetes',
          confirmButtonColor: '#ef4444'
        });
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

      // Recargar órdenes del telar actual tras liberar
      const telarActual = selectTelarCortado?.value;
      tablaCortadoContainer.style.display = 'none';
      btnNotificarWrapper.style.setProperty('display', 'none', 'important');
      if (telarActual) cargarOrdenesEnProceso(telarActual);

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
@endpush
