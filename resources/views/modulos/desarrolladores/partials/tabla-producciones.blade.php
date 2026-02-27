<div class="md:col-span-2">
    <div id="tablaProducciones" class="hidden">
        <div class="flex justify-center">
            <h3 class="text-lg font-semibold text-gray-800">Producciones Disponibles</h3>
        </div>

        <!-- Contenedor con scroll: horizontal para columnas y vertical limitado -->
        <div class="overflow-y-auto max-h-96 rounded-lg border border-gray-200">
            <table class="w-full table-fixed divide-y divide-red-200">
                <thead class="bg-blue-500 ">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white uppercase tracking-wider">Salon de Tejido</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white uppercase tracking-wider">No. Orden</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white uppercase tracking-wider">Fecha Cambio</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white uppercase tracking-wider">Tamaño Clave</th>
                        <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white uppercase tracking-wider">Modelo</th>
                        <th scope="col" class="px-3 py-2 text-center text-sm font-medium text-white uppercase tracking-wider">Seleccionar</th>
                    </tr>
                </thead>
                <tbody id="bodyProducciones" class="bg-white divide-y divide-gray-200">
                    <!-- Las filas se cargarán dinámicamente -->
                </tbody>
            </table>
        </div>

        <!-- Mensaje cuando no hay datos -->
        <div id="noDataMessage" class="hidden text-center py-8 text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="mt-2 text-sm">No se encontraron producciones para este telar</p>
        </div>
    </div>
</div>