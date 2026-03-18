<div class="md:col-span-2">
    {{-- Orden En Proceso + Reprogramar (se llena via JS) --}}
    <div id="ordenEnProcesoBanner" class="hidden mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                </span>
                <span class="text-sm font-semibold text-amber-800">Orden en Proceso:</span>
                <span id="ordenEnProcesoNum" class="text-sm font-bold text-amber-900">-</span>
                <span class="text-xs text-amber-500">|</span>
                <span id="ordenEnProcesoFecha" class="text-sm text-amber-700">-</span>
                <span class="text-xs text-amber-500">|</span>
                <span id="ordenEnProcesoNombre" class="text-sm text-amber-700">-</span>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" id="btnFinalizarOrden" class="px-3 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors">
                    Finalizar
                </button>
                <button type="button" id="btnRepSiguiente" class="px-3 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors">
                    Reprogramar siguiente
                </button>
                <button type="button" id="btnRepFinal" class="px-3 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors">
                    Reprogramar final
                </button>
            </div>
        </div>
    </div>

    <div id="tablaProducciones" class="hidden">
        <div class="rounded-lg border border-gray-200">
            <table class="w-full divide-y divide-red-200">
                <thead class="bg-blue-500">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Salón</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Orden</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Fecha Cambio</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Clave</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Modelo</th>
                        <th scope="col" class="px-3 py-2 text-center text-sm font-medium text-white">Seleccionar</th>
                    </tr>
                </thead>
                <tbody id="bodyProducciones" class="bg-white divide-y divide-gray-200">
                    <!-- Las filas se cargarán dinámicamente -->
                </tbody>
            </table>
        </div>

        <!-- Mensaje cuando no hay datos -->
        <div id="noDataMessage" class="hidden bg-white rounded-b-lg border-x border-b border-gray-200 py-10 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-600">No se encontraron producciones para este telar</p>
        </div>
    </div>
</div>
