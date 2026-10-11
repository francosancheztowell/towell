@extends('layouts.app')

@section('page-title', 'Cortado de Rollo')

@section('navbar-right')
  <div class="flex items-center gap-2">

    {{-- Botón Notificar (deshabilitado por defecto) --}}
    <button
      type="button"
      id="btnNotificarCortado"
      disabled
      class="flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors
             bg-gray-200 text-gray-400 cursor-not-allowed"
    >
      <i class="fas fa-bell text-sm"></i>
      <span>Notificar</span>
    </button>

    {{-- Botón dropdown telares --}}
    @if(count($telaresUsuario) > 0)
    <div class="relative">
      <button
        type="button"
        id="btnDropdownTelaresCortado"
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        <span class="font-medium" id="labelTelarSeleccionado">
          {{ $telarSeleccionado ? 'Telar ' . $telarSeleccionado : 'Telares' }}
        </span>
        <i class="fas fa-chevron-down text-sm transition-transform duration-200 ease-out rotate-0" id="iconDropdownCortado"></i>
      </button>

      {{-- Menú dropdown --}}
      <div
        id="menuDropdownTelaresCortado"
        class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg border border-gray-200 max-h-96 overflow-y-auto z-50"
      >
        <div class="py-2">
          <button
            type="button"
            data-telar=""
            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
          >
            <span class="font-medium">Todos los telares</span>
          </button>
          <div class="border-t border-gray-200 my-1"></div>
          @foreach($telaresUsuario as $telar)
            <button
              type="button"
              data-telar="{{ $telar->NoTelarId }}"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
            >
              Telar <span class="font-semibold">{{ $telar->NoTelarId }}</span>
            </button>
          @endforeach
        </div>
      </div>
    </div>
    @endif

  </div>
@endsection

@section('content')
{{-- Select oculto: mantiene la lógica JS existente --}}
<select id="selectTelarCortado" class="hidden">
  <option value="">Selecciona un telar</option>
  @foreach($telaresUsuario as $telar)
    <option value="{{ $telar->NoTelarId }}">
      Telar {{ $telar->NoTelarId }}
    </option>
  @endforeach
</select>

<div class="w-full p-3 sm:p-5 flex flex-col gap-5" id="cortadoRollo"
     data-ruta-ordenes="{{ route('notificar.cortado.rollo.ordenes.enproceso', ['telarId' => ':telar']) }}"
     data-ruta-marbetes="{{ route('notificar.cortado.rollo.datos.produccion') }}"
     data-ruta-insertar="{{ route('notificar.cortado.rollo.insertar') }}">

  {{-- Mensaje inicial: selecciona un telar --}}
  <div id="mensajeSeleccionaTelar" class="flex flex-col items-center justify-center py-16 text-white">
    <i class="fas fa-hand-point-up text-5xl mb-4"></i>
    <p class="text-lg font-medium">Selecciona un telar en la parte superior</p>
  </div>

  {{-- Tabla Nivel 2: Órdenes del Telar --}}
  <div id="tablaProduccionesCortadoContainer" style="display: none;">
    <div class="bg-white rounded-xl shadow border border-blue-200 overflow-hidden">
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
    <div class="bg-white rounded-xl shadow border border-blue-200 overflow-hidden">
      <div class="tabla-marbetes-cortado overflow-x-auto overflow-y-auto" style="max-height: 16rem; -webkit-overflow-scrolling: touch;">
        <table class="w-full text-sm sm:text-base table-fixed">
          <thead class="bg-blue-50 sticky top-0 z-[1]">
            <tr>
              <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Cuantas</th>
              <th class="w-[16%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Marbete</th>
              <th class="w-[14%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Artículo</th>
              <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Tamaño</th>
              <th class="w-[16%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Orden</th>
              <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Telar</th>
              <th class="w-[10%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Piezas</th>
              <th class="w-[14%] px-4 py-3 text-center text-xs sm:text-sm font-semibold text-blue-800 uppercase">Salón</th>
            </tr>
          </thead>
          <tbody id="tablaProduccionCortadoBody" class="bg-white divide-y divide-gray-100"></tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Mensaje de estado --}}
  <div id="mensajeEstadoCortado" class="text-center text-base sm:text-lg text-gray-600 py-5 bg-white rounded-xl shadow border border-gray-200" style="display: none;"></div>


</div>
@endsection


@push('scripts')
    @vite('resources/js/modulos/tejedores/notificar-mont-rollos/index.ts')
@endpush
