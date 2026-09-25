{{-- Modal Editar Marbetes --}}
<x-ui.modal-base id="modalMarbetes" title="Editar Marbetes" size="md" onclose="cerrarModalMarbetes()">
  <p id="marbetes-info" class="text-xs text-gray-600 mb-3"></p>

  <div class="grid grid-cols-2 gap-3">
    @foreach ([
      'pesoRollo' => 'Peso rollo (kg)',
      'repeticiones' => 'Repeticiones',
      'mtsRollo' => 'Mts x rollo',
      'pzasRollo' => 'Pzas x rollo',
      'noMarbete' => 'No. marbetes',
      'totalRollos' => 'Total rollos',
      'totalPzas' => 'Total pzas',
    ] as $campo => $label)
      <label class="text-sm text-gray-700">
        <span class="block mb-1">{{ $label }}</span>
        <input type="number" step="any" min="0" id="marbetes-{{ $campo }}" data-campo="{{ $campo }}"
               class="marbetes-input w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
      </label>
    @endforeach
  </div>

  <div class="flex justify-center mt-4">
    <button type="button" id="btnGuardarMarbetes" onclick="guardarMarbetesEnviar()" class="modal-btn-primary">
      Guardar
    </button>
  </div>
</x-ui.modal-base>

{{-- El JS del modal vive en resources/js/programa-tejido/modales/marbetes.js (bundle de la grilla). --}}
