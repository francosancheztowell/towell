@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto">
        <div class="mt-14">
            <x-back-button text="Volver a Inventario Trama" />
                    </div>

        <!-- Navbar de telares -->
        <div class="fixed top-16 left-0 right-0 bg-white/95 backdrop-blur-sm border-b border-gray-200 shadow-lg z-40 transition-all duration-300">
            <div class="container mx-auto px-4 py-3">
                <div class="flex flex-wrap justify-center gap-4 max-w-6xl mx-auto">
                    <button onclick="scrollToTelar(207)" class="telar-nav-btn px-3 py-1.5 rounded text-sm font-medium transition-all duration-200 border border-gray-300 bg-blue-600 text-white hover:bg-blue-700 hover:border-blue-600" data-telar="207">
                        207
                    </button>
                    </div>
                    </div>
                    </div>

        <!-- Lista de Requerimientos en Proceso -->
        <div class="space-y-6">
            <!-- Requerimiento 1 -->
            <div id="telar-207" class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                <!-- Header amarillo -->
                <div class="bg-blue-500 px-4 py-4 border-t-4 border-orange-400">
                    <h2 class="text-xl font-bold text-white text-center">
                        PRODUCCIÓN EN PROCESO TELAR JAQ
                        <span class="inline-block bg-red-600 text-white px-3 py-1 rounded-lg ml-2 font-bold text-xl">207</span>
                    </h2>
                    </div>

                <!-- Información del requerimiento -->
                <div class="p-6">
                    <!-- Primera fila de información -->
                    <div class="grid grid-cols-3 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Columna izquierda -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Folio:</span>
                                <span class="text-sm font-semibold text-gray-900">REQ-001</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Fecha:</span>
                                <span class="text-sm font-semibold text-gray-900">15/10/2025</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Turno:</span>
                                <span class="text-sm font-semibold text-gray-900">1</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Orden (NoProduccion):</span>
                                <span class="text-sm font-semibold text-gray-900">ORD-2025-001</span>
                    </div>
                </div>

                        <!-- Columna derecha -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">No Flog (FlogsId):</span>
                                <span class="text-sm font-semibold text-gray-900">FLG-001</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Cliente (CustName):</span>
                                <span class="text-sm font-semibold text-gray-900">Cliente A</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Tamaño (InventSizeId):</span>
                                <span class="text-sm font-semibold text-gray-900">38x48</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Artículo (ItemId + NombreProducto):</span>
                                <span class="text-sm font-semibold text-gray-900">ART-001 Producto A</span>
                            </div>
                        </div>

                        <!-- Columna adicional -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Pedido (TotalPedido):</span>
                                <span class="text-sm font-semibold text-gray-900">1000</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Producción:</span>
                                <span class="text-sm font-semibold text-gray-900">500</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Inicio (FechaInicio):</span>
                                <span class="text-sm font-semibold text-gray-900">15/10/2025</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Fin (FechaFinal):</span>
                                <span class="text-sm font-semibold text-gray-900">20/10/2025</span>
                        </div>
                        </div>
                        </div>

                    <!-- Botón de nuevo requerimiento -->
                    <div class="flex justify-end mb-4">
                        <button onclick="agregarNuevoRequerimiento()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Nuevo Requerimiento
                        </button>
                    </div>

                    <!-- Tabla de detalles -->
                    <div class=" rounded-lg overflow-hidden ">
                        <table class="w-full mt-2.5 ">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Artículo</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad (Conos)</th>
                                </tr>
                            </thead>
                            <tbody class="">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Calibre Trama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Trama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorTrama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">ColorTrama</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-between relative">
                                            <span class="quantity-display text-sm text-gray-900">1</span>
                                            <button class="edit-quantity-btn ml-2 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleQuantityEdit(this)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 1; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb12</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC1</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-between relative">
                                            <span class="quantity-display text-sm text-gray-900">1</span>
                                            <button class="edit-quantity-btn ml-2 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleQuantityEdit(this)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 1; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb22</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb2</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb2</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC2</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-between">
                                            <span class="quantity-display text-sm text-gray-900">1</span>
                                            <button class="edit-quantity-btn ml-2 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleQuantityEdit(this)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <div class="quantity-edit-container hidden relative w-20">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 1; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                        </div>
                    </div>
                </div>
                                </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb32</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC3</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-between">
                                            <span class="quantity-display text-sm text-gray-900">1</span>
                                            <button class="edit-quantity-btn ml-2 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleQuantityEdit(this)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <div class="quantity-edit-container hidden relative w-20">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 1; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                                </div>
                                </div>
                                </div>
                            </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb42</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC4</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-between relative">
                                            <span class="quantity-display text-sm text-gray-900">1</span>
                                            <button class="edit-quantity-btn ml-2 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleQuantityEdit(this)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 1; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                        @endfor
                    </div>
                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb52</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC5</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-between relative">
                                            <span class="quantity-display text-sm text-gray-900">1</span>
                                            <button class="edit-quantity-btn ml-2 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleQuantityEdit(this)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 1; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                                                    </div>
                        </div>
                        </div>
                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


                </div>

        <!-- Mensaje cuando no hay requerimientos -->
        <div id="no-requerimientos" class="hidden">
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                    <p class="text-gray-500">No se encontraron requerimientos que coincidan con los filtros seleccionados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
        <!-- Los toasts se agregarán aquí dinámicamente -->
    </div>

    <script>
        // Funcionalidad del navbar de telares
        function scrollToTelar(telarNumber) {
            const element = document.getElementById(`telar-${telarNumber}`);
            if (!element) return;

            // Calcular posición exacta
            const elementRect = element.getBoundingClientRect();
            const absoluteElementTop = elementRect.top + window.pageYOffset;
            const navbarHeight = 56;
            const offsetTop = absoluteElementTop - navbarHeight - 60; // 60px de margen

            window.scrollTo({
                top: Math.max(0, offsetTop),
                behavior: 'smooth'
            });

            // Actualizar botón activo después del scroll
            setTimeout(() => {
                updateActiveButton(telarNumber);
            }, 500);
        }

        function updateActiveButton(activeTelar) {
            document.querySelectorAll('.telar-nav-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'border-blue-600', 'text-white');
                btn.classList.add('bg-gray-100', 'border-gray-300', 'text-gray-700');
            });

            // Agregar clase activa al botón del telar actual
            const activeBtn = document.querySelector(`[data-telar="${activeTelar}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-100', 'border-gray-300', 'text-gray-700');
                activeBtn.classList.add('bg-blue-600', 'border-blue-600', 'text-white');
            }
        }

        // Funcionalidad del scroll horizontal de números
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar editores al hacer clic fuera de ellos
            document.addEventListener('click', function(event) {
                const isInsideEditor = event.target.closest('.quantity-edit-container');
                const isEditButton = event.target.closest('.edit-quantity-btn');

                if (!isInsideEditor && !isEditButton) {
                    closeAllQuantityEditors();
                }
            });
            document.querySelectorAll('.number-option').forEach(option => {
                option.addEventListener('click', function() {
                    const container = this.closest('.number-scroll-container');
                    const allOptions = container.querySelectorAll('.number-option');
                    const row = this.closest('tr');
                    const quantityDisplay = row.querySelector('.quantity-display');
                    const selectedValue = this.getAttribute('data-value');

                    // Remover selección anterior
                    allOptions.forEach(opt => {
                        opt.classList.remove('bg-blue-500', 'text-white');
                        opt.classList.add('bg-gray-100', 'text-gray-700');
                    });

                    // Seleccionar opción actual
                    this.classList.remove('bg-gray-100', 'text-gray-700');
                    this.classList.add('bg-blue-500', 'text-white');

                    // Actualizar el texto mostrado
                    quantityDisplay.textContent = selectedValue;

                    // Mostrar toast
                    showToast(`Cantidad actualizada a ${selectedValue} conos`);

                    // Centrar el número seleccionado
                    const containerWidth = container.offsetWidth;
                    const optionLeft = this.offsetLeft;
                    const optionWidth = this.offsetWidth;
                    const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                    container.scrollTo({
                        left: scrollLeft,
                        behavior: 'smooth'
                    });

                    // Ocultar el editor después de seleccionar
                    setTimeout(() => {
                        const editContainer = row.querySelector('.quantity-edit-container');
                        const editBtn = row.querySelector('.edit-quantity-btn');
                        const display = row.querySelector('.quantity-display');

                        editContainer.classList.add('hidden');
                        editBtn.classList.remove('hidden');
                        display.classList.remove('hidden');
                    }, 500);
                });
            });
        });

        // Función para mostrar/ocultar el editor de cantidad
        function toggleQuantityEdit(button) {
            const row = button.closest('tr');
            const editContainer = row.querySelector('.quantity-edit-container');
            const quantityDisplay = row.querySelector('.quantity-display');

            if (editContainer.classList.contains('hidden')) {
                // Cerrar todos los editores abiertos primero
                closeAllQuantityEditors();

                // Mostrar editor actual
                editContainer.classList.remove('hidden');
                button.classList.add('hidden');
                quantityDisplay.classList.add('hidden');
            } else {
                // Ocultar editor
                editContainer.classList.add('hidden');
                button.classList.remove('hidden');
                quantityDisplay.classList.remove('hidden');
            }
        }

        // Función para cerrar todos los editores de cantidad
        function closeAllQuantityEditors() {
            document.querySelectorAll('.quantity-edit-container').forEach(container => {
                if (!container.classList.contains('hidden')) {
                    const row = container.closest('tr');
                    const editBtn = row.querySelector('.edit-quantity-btn');
                    const display = row.querySelector('.quantity-display');

                    container.classList.add('hidden');
                    editBtn.classList.remove('hidden');
                    display.classList.remove('hidden');
                }
            });
        }

        // Función para agregar nuevo requerimiento
        function agregarNuevoRequerimiento() {
            showToast('Agregando nuevo requerimiento...');

            // Aquí se implementaría la lógica para agregar un nuevo requerimiento
            // Por ejemplo: redirigir a un formulario, mostrar un modal, etc.
            console.log('Agregando nuevo requerimiento');

            // Ejemplo: mostrar un modal o redirigir
            // window.location.href = '/nuevo-requerimiento-formulario';
            // o mostrar un modal con formulario
        }

        // Función para mostrar toast
        function showToast(message) {
            const toastContainer = document.getElementById('toast-container');
            const toastId = 'toast-' + Date.now();

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = 'bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 ease-in-out';
            toast.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="text-sm font-medium">${message}</span>
                </div>
            `;

            toastContainer.appendChild(toast);

            // Animar entrada
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);

            // Auto-remover después de 3 segundos
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
    </script>

    <style>
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
    </style>
@endsection

