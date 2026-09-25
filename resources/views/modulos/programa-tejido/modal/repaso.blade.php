{{-- Modal Crear Repaso --}}
<x-ui.modal-base id="modalRepaso" title="Crear Repaso" size="md" onclose="cerrarModalRepaso()">
  <table class="min-w-full border border-gray-300">
    <thead>
      <tr class="bg-gray-50">
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Telar</th>
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Ancho</th>
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Hilo</th>
        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 border-b border-gray-300">Calibre</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="px-3 py-2 border-r border-gray-300">
          <select id="repaso-telar" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">Seleccione telar...</option>
          </select>
        </td>
        <td class="px-3 py-2 border-r border-gray-300">
          <input type="number" id="repaso-ancho" step="any" min="0" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="">
        </td>
        <td class="px-3 py-2 border-r border-gray-300">
          <select id="repaso-hilo" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">Seleccione hilo...</option>
          </select>
        </td>
        <td class="px-3 py-2">
          <input type="number" id="repaso-calibre" step="any" min="0" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="">
        </td>
      </tr>
    </tbody>
  </table>

  <div class="flex justify-center mt-4">
    <button type="button" id="btnCrearRepaso" onclick="crearRepasoEnviar()" class="modal-btn-primary">
      Crear
    </button>
  </div>
</x-ui.modal-base>

{{-- El JS del modal vive en resources/js/programa-tejido/modales/repaso.js (bundle de la grilla). --}}
