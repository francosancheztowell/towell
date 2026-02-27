<div id="modalPasadas" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-30 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-2">Validación de Pasadas</h4>
        <p class="text-sm text-gray-600 mb-6">Total de pasadas no cuadra con el detalle de la orden.</p>
        <div class="flex justify-end gap-3">
            <button type="button" id="modalPasadasCancelar" class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
            <button type="button" id="modalPasadasAceptar" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Aceptar</button>
        </div>
    </div>
</div>

{{-- Modal Reporte por Fecha --}}
<div id="modalReporteFecha" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white max-w-md w-full rounded-xl shadow-xl m-4">
        <div class="px-4 py-3 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fa-solid fa-file-excel text-green-600 mr-2"></i>
                Exportar Reporte a Excel
            </h3>
            <button type="button" onclick="cerrarModalReporte()" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4">
            <label for="input-fecha-reporte" class="block text-sm font-medium text-gray-700 mb-1">Selecciona la fecha</label>
            <input
                type="date"
                id="input-fecha-reporte"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                value="{{ now()->format('Y-m-d') }}"
            />
            <p class="text-xs text-gray-500 mt-2">Se exportarán los registros de desarrolladores correspondientes a la fecha seleccionada.</p>
        </div>
        <div class="px-4 py-3 border-t flex justify-end gap-2">
            <button type="button" onclick="cerrarModalReporte()" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" onclick="exportarReporteExcel()" class="px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700 flex items-center gap-2">
                <i class="fa-solid fa-download"></i>
                Descargar Excel
            </button>
        </div>
    </div>
</div>