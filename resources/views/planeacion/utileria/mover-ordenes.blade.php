{{--
    @file mover-ordenes.blade.php
    @description Modal para mover órdenes entre telares con interfaz drag-and-drop y ordenamiento.
    @relatedFiles index.blade.php, MoverOrdenesController.php

    ! REPORTE DE FUNCIONALIDAD - Modal Mover Órdenes
    * -----------------------------------------------
    * 1. Al abrir el modal, se cargan todos los telares disponibles vía AJAX.
    * 2. El usuario selecciona TELAR ORIGEN y/o TELAR DESTINO y se cargan sus registros.
    * 3. Las órdenes en proceso ahora también se pueden mover y reordenar.
    * 4. Interfaz drag-and-drop permite reordenar registros dentro del mismo telar o moverlos a otro.
    * 5. Al confirmar, se envían todos los IDs en el nuevo orden al backend.
    * 6. El backend actualiza NoTelarId, SalonTejidoId y Posicion para cada registro.
    * -----------------------------------------------
--}}

<div id="modalMover" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] max-h-[90vh] flex flex-col overflow-hidden" style="min-width: 1200px;">

        {{-- ? Header --}}
        <div class="flex items-center justify-between px-6 py-4 ">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold text-gray-800">Mover Órdenes</h2>
            </div>
            <button type="button" data-accion-modal="cerrar" aria-label="Cerrar" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                <svg aria-hidden="true" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- ? Body: Paneles --}}
        <div class="flex-1 overflow-hidden px-6 py-5 bg-gray-50/30">
            <div class="flex gap-6 h-full" style="min-height: 450px;">

                {{-- Panel ORIGEN --}}
                <div class="flex-1 flex flex-col border border-gray-100 rounded-xl overflow-hidden bg-white transition-all duration-200"
                     id="panelOrigenContainer">
                    <div class="px-4 py-3 bg-amber-100 border-b border-gray-200">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <label class="text-sm font-semibold text-amber-800">Telar Origen</label>
                            <span id="moverOrigenTipo" class="text-xs font-bold text-amber-800 bg-amber-300 px-2 py-0.5 rounded hidden shadow-sm"></span>
                        </div>
                        <select id="moverSelectOrigen" class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-400 bg-white">
                            <option value="">Seleccione telar origen</option>
                        </select>
                    </div>
                    <div id="moverOrigenList" class="flex-1 overflow-y-auto relative">
                        <div id="moverOrigenEmpty" class="py-12 text-center text-gray-400 text-sm pointer-events-none">
                            <i class="fas fa-inbox text-3xl mb-2 text-amber-200"></i><p>Seleccione un telar origen</p>
                        </div>
                        <div id="moverOrigenItems" class="hidden h-full">
                            <table class="w-full text-sm border-collapse">
                                <thead class="bg-blue-500 text-white sticky top-0 z-10 shadow-sm rounded-t-lg">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold w-8 rounded-tl-lg"></th>
                                        <th class="px-3 py-2 text-left font-semibold">Orden</th>
                                        <th class="px-3 py-2 text-left font-semibold">Tamaño</th>
                                        <th class="px-3 py-2 text-left font-semibold">Modelo</th>
                                        <th class="px-3 py-2 text-right font-semibold rounded-tr-lg">Producción</th>
                                    </tr>
                                </thead>
                                <tbody id="moverOrigenTbody" class="pb-12"></tbody>
                            </table>
                            <div id="moverOrigenDropZone" class="hidden absolute inset-0 bg-amber-300/80 backdrop-blur-[1px] flex items-center justify-center border-2 border-dashed border-amber-400 rounded-b-lg pointer-events-none z-20">
                                <div class="bg-white px-4 py-2 rounded-lg shadow-sm text-amber-600 font-semibold flex items-center gap-2">
                                    <i class="fas fa-download"></i><span>Mover aquí</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Panel DESTINO --}}
                <div class="flex-1 flex flex-col border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm transition-all duration-200"
                     id="panelDestinoContainer">
                    <div class="px-4 py-3 bg-blue-100 border-b border-gray-200">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <label class="text-sm font-semibold text-blue-800">Telar Destino</label>
                            <span id="moverDestinoTipo" class="text-xs font-bold text-blue-800 bg-blue-200 px-2 py-0.5 rounded hidden shadow-sm"></span>
                        </div>
                        <select id="moverSelectDestino" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 bg-white">
                            <option value="">Seleccione telar destino</option>
                        </select>
                    </div>
                    <div id="moverDestinoList" class="flex-1 overflow-y-auto relative">
                        <div id="moverDestinoEmpty" class="py-12 text-center text-gray-400 text-sm pointer-events-none">
                            <i class="fas fa-inbox text-3xl mb-2 text-blue-200"></i><p>Seleccione un telar destino</p>
                        </div>
                        <div id="moverDestinoItems" class="hidden h-full">
                            <table class="w-full text-sm border-collapse">
                                <thead class="bg-blue-500 text-white sticky top-0 z-10 shadow-sm rounded-t-lg">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold w-8 rounded-tl-lg"></th>
                                        <th class="px-3 py-2 text-left font-semibold">Orden</th>
                                        <th class="px-3 py-2 text-left font-semibold">Tamaño</th>
                                        <th class="px-3 py-2 text-left font-semibold">Modelo</th>
                                        <th class="px-3 py-2 text-right font-semibold rounded-tr-lg">Producción</th>
                                    </tr>
                                </thead>
                                <tbody id="moverDestinoTbody" class="pb-12"></tbody>
                            </table>
                            <div id="moverDestinoDropZone" class="hidden absolute inset-0 bg-blue-50/80 backdrop-blur-[1px] flex items-center justify-center border-2 border-dashed border-blue-400 rounded-b-lg pointer-events-none z-20">
                                <div class="bg-white px-4 py-2 rounded-lg shadow-sm text-blue-600 font-semibold flex items-center gap-2">
                                    <i class="fas fa-download"></i><span>Mover aquí</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ? Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-white">
            <div id="moverResumen" class="text-sm font-medium"></div>
            <div class="flex gap-3">
                <button type="button" id="btnMoverRevertir" class="hidden px-5 py-2.5 text-md font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors shadow-sm">
                    <i class="fas fa-undo mr-1" aria-hidden="true"></i> Revertir
                </button>
                <button type="button" id="btnMoverConfirm" disabled class="px-5 py-2.5 text-md font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm flex items-center gap-2">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- La lógica vive en resources/js/modulos/programa-tejido/utileria/mover.ts (la carga index.blade.php). --}}
