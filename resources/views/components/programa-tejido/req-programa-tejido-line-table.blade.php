<div id="reqpt-line-wrapper" class="mt-4 hidden">
    <div class="shadow rounded-md overflow-hidden">
        <!-- Tabla con altura máxima fija y scroll interno -->
        <div class="overflow-x-auto max-h-48" style="max-height: 250px; overflow-y: auto;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-500 text-white sticky top-0">
                    <tr>
                        <th class="px-2 py-1 text-left text-xs font-semibold">Fecha</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Total Piezas</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Total Kilos</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Aplicación</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Trama</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 1</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 2</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 3</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 4</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 5</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Rizo</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Pie</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Mts/Pie</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Mts/Rizo</th>

                    </tr>
                </thead>
                <tbody id="reqpt-line-body" class=" divide-y divide-gray-100 bg-white">
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
	/* Scrollbar delgado para el modal */
	#swal2-html-container .overflow-x-auto::-webkit-scrollbar,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar {
		width: 6px;
		height: 6px;
	}

	#swal2-html-container .overflow-x-auto::-webkit-scrollbar-track,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar-track {
		background: #f1f1f1;
		border-radius: 3px;
	}

	#swal2-html-container .overflow-x-auto::-webkit-scrollbar-thumb,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar-thumb {
		background: #cbd5e1;
		border-radius: 3px;
	}

	#swal2-html-container .overflow-x-auto::-webkit-scrollbar-thumb:hover,
	#swal2-html-container [style*="overflow-y: auto"]::-webkit-scrollbar-thumb:hover {
		background: #989b9e;
	}

	/* Para Firefox */
	#swal2-html-container .overflow-x-auto,
	#swal2-html-container [style*="overflow-y: auto"] {
		scrollbar-width: thin;
		scrollbar-color: #cbd5e1 #f1f1f1;
	}
</style>

{{-- El JS de la tabla y del modal de líneas vive en resources/js/programa-tejido/lineas.js (bundle de la grilla). --}}









