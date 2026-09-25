{{-- Modal Actualizar Calendarios --}}
<x-ui.modal-base id="modalActCalendarios" title="Actualizar Calendarios" size="lg" onclose="cerrarModalActCalendarios()">

  {{-- Select de Calendario --}}
  <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
    <label for="selectCalendario" class="text-xs font-semibold text-gray-600">
      Calendario
    </label>
    <select id="selectCalendario" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm hover:border-blue-400">
      <option value="">Seleccione un calendario...</option>
    </select>
    <label class="text-xs text-gray-600 flex items-center gap-2">
      <input type="checkbox" id="selectAllRegistros" class="w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer">
      Seleccionar todo
    </label>
  </div>

  {{-- Tabla tipo Excel con registros de ProgramaTejido --}}
  <div class="border border-gray-300 rounded-lg overflow-y-auto overflow-x-hidden" style="max-height: 50vh;">
    <table id="tablaRegistros" class="min-w-full bg-white text-sm">
      <tbody id="tbodyRegistros" class="divide-y divide-gray-200">
        {{-- Las filas se cargarán dinámicamente con JavaScript --}}
      </tbody>
    </table>
  </div>

  {{-- Botones de acción --}}
  <div class="flex justify-end gap-3 mt-3 pt-3 border-t border-gray-200">
    <button type="button" onclick="cerrarModalActCalendarios()" class="modal-btn-secondary">
      Cancelar
    </button>
    <button type="button" id="btnGuardarCalendarios" onclick="guardarCalendariosSeleccionados()" class="modal-btn-primary">
      Guardar
    </button>
  </div>

</x-ui.modal-base>

{{-- El JS del modal vive en resources/js/programa-tejido/modales/act-calendarios.js (bundle de la grilla). --}}

<style>
  #tablaRegistros {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
  }

  #tablaRegistros td {
    border-bottom: 1px solid #e5e7eb;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  #tablaRegistros tbody tr:last-child td {
    border-bottom: 0;
  }

  #tablaRegistros tbody tr:hover {
    background-color: #f3f4f6;
  }
</style>
