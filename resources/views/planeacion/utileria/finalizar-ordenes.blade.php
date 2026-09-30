{{--
    @file finalizar-ordenes.blade.php
    @description Modal para finalizar órdenes de producción en telares.
    @relatedFiles index.blade.php, FinalizarOrdenesController.php

    ! REPORTE DE FUNCIONALIDAD - Modal Finalizar Órdenes
    * -----------------------------------------------
    * 1. Al abrir el modal, se cargan los telares con órdenes en proceso vía AJAX
    * 2. El usuario selecciona un telar del dropdown
    * 3. Se cargan las órdenes en proceso del telar seleccionado en una tabla
    * 4. Columnas de la tabla: No. Orden, Fecha Cambio, Tamaño Clave, Modelo, Seleccionar (check)
    * 5. El usuario selecciona las órdenes a finalizar mediante checkboxes
    * 6. Al confirmar, se envían los IDs al backend para actualizar EnProceso=0 y FechaFinaliza=now()
    * 7. Se muestra confirmación con SweetAlert2 y se refresca la tabla
    * -----------------------------------------------
--}}

{{-- * Modal Finalizar Órdenes --}}
<div id="modalFinalizar" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">

        {{-- ? Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-white">
            <div class="flex items-center gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">
                        Finalizar Órdenes
                        <span id="finalizarContador" class="text-sm font-semibold text-blue-600"></span>
                    </h2>
                </div>
            </div>
            <button type="button" data-accion-modal="cerrar" aria-label="Cerrar" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                <svg aria-hidden="true" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        {{-- ? Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5">

            {{-- Select de Telar --}}
            <div class="mb-5">
                <label for="finalizarSelectTelar" class="block text-sm font-semibold text-gray-700 mb-2">
                    Seleccionar Telar
                </label>
                <select
                    id="finalizarSelectTelar"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition-colors bg-white"
                >
                    <option value="">Seleccione un telar</option>
                </select>
            </div>

            {{-- Loader --}}
            <div id="finalizarLoader" class="hidden py-12 text-center">
                <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                <p class="text-sm text-gray-500 mt-2">Cargando órdenes...</p>
            </div>

            {{-- Tabla de órdenes --}}
            <div id="finalizarTablaContainer" class="hidden">


                <div class="border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full min-w-full text-sm">
                        <thead class="bg-blue-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-white w-10"></th>
                                <th class="px-3 py-2 text-left font-semibold text-white whitespace-nowrap min-w-[100px]">No. Orden</th>
                                <th class="px-3 py-2 text-left font-semibold text-white whitespace-nowrap">Fecha Cambio</th>
                                <th class="px-3 py-2 text-left font-semibold text-white whitespace-nowrap">Clave</th>
                                <th class="px-3 py-2 text-left font-semibold text-white">Modelo</th>
                                <th class="px-3 py-2 text-right font-semibold text-white whitespace-nowrap">Pedido</th>
                                <th class="px-3 py-2 text-right font-semibold text-white whitespace-nowrap">Producción</th>
                                <th class="px-3 py-2 text-right font-semibold text-white whitespace-nowrap">Saldos</th>
                            </tr>
                        </thead>
                        <tbody id="finalizarTbody" class="divide-y divide-gray-100">
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Estado vacío --}}
            <div id="finalizarEmpty" class="hidden py-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
                <p class="text-sm text-gray-500">No hay órdenes para este telar</p>
            </div>
        </div>

        {{-- ? Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50">
            <span id="finalizarSeleccionados" class="text-sm text-gray-500"></span>
            <div class="flex gap-3">
                <button type="button" id="btnFinalizarConfirm" disabled class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Finalizar Ordenes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- La lógica vive en resources/js/modulos/programa-tejido/utileria/finalizar.ts (la carga index.blade.php). --}}
