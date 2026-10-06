{{-- Editor del nombre del dispositivo (MON-19, monitoreo/dispositivo.ts). Va FUERA del flux:dropdown:
     ui-dropdown toma su último hijo como panel [popover]; un div después lo dejaba sin menú. --}}
@if(\App\Services\Monitoreo\Monitoreo::activo())
<!-- Input oculto para editar nombre -->
<div id="device-name-editor" class="hidden fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-4 w-72 mx-4">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Nombre del dispositivo</h3>
        <input type="text"
               id="device-name-input"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Ej: Tablet Producción 1"
               maxlength="80">
        <p class="text-xs text-gray-500 mt-1">Este nombre identifica a este equipo en el monitoreo</p>
        <div class="flex gap-2 mt-3">
            <button id="cancel-device-name"
                    class="flex-1 px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                Cancelar
            </button>
            <button id="save-device-name"
                    class="flex-1 px-3 py-2 text-sm text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition-colors">
                Guardar
            </button>
        </div>
    </div>
</div>
@endif
