@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto">
        <!-- Lista de Requerimientos en Proceso -->
        <div>
            <div class="bg-white overflow-hidden">

                <!-- Header azul -->
                <div class="bg-blue-500 px-3 py-3 border-t-4 border-orange-400">
                    <div class="flex items-center">
                        <button onclick="history.back()" class="flex items-center justify-center w-10 h-10 text-white hover:bg-blue-600 rounded-lg transition-colors mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <h2 class="text-2xl font-bold text-white flex-1 text-center">
                            Consultar Requerimiento
                        </h2>
                    </div>
                </div>

                <!-- Información del requerimiento -->
                <div class="p-4 ">
                    <!-- Información del requerimiento en formato tabla -->
                    <div class="mb-6 flex flex-col lg:flex-row gap-4">
                        <!-- Tabla de datos del requerimiento -->
                        <div class="flex-1 border border-gray-300 rounded-lg overflow-hidden min-w-0">
                            <div class="overflow-y-auto h-[190px]">
                                <table class="w-full">
                                <thead class="sticky top-0 z-10">
                                    <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Folio</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Status</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Turno</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Operador</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Folio:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">REQ-001</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Fecha:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">15/10/2025</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Status:</td>
                                        <td class="px-4 py-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                En Proceso
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Folio:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">REQ-002</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Fecha:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">16/10/2025</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Status:</td>
                                        <td class="px-4 py-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Solicitado
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Folio:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">REQ-003</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Fecha:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">17/10/2025</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Status:</td>
                                        <td class="px-4 py-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Surtido
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Folio:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">REQ-004</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Fecha:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">18/10/2025</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Status:</td>
                                        <td class="px-4 py-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Cancelado
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Folio:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">REQ-005</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Fecha:</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">19/10/2025</td>
                                        <td class="px-4 py-1 text-sm font-semibold text-gray-600 border-r border-gray-200">Status:</td>
                                        <td class="px-4 py-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Creado
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        <!-- Botones de acciones -->
                        <div class="flex flex-col space-y-2 lg:min-w-[180px]">
                            <button class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-list mr-2"></i>Solicitar consumo
                            </button>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-edit mr-2"></i>Editar
                            </button>
                            <button class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                <i class="fas fa-trash mr-2"></i>Cancelar
                            </button>
                            <button class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition-colors">
                                <i class="fas fa-eye mr-2"></i>Resumen de articulo
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de detalles -->
                    <div class="border border-gray-300 rounded-lg overflow-hidden">
                        <div class="overflow-y-auto h-[300px]">
                        <table class="w-full">
                            <thead class="sticky top-0 z-10">
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Folio</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Telar</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Articulo</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Calibre Trama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Trama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorTrama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">ColorTrama</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb12</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb22</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb2</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb2</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC2</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb32</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb42</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb52</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb32</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC3</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb42</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC4</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CalibreComb52</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">Fibra Comb5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">CodColorComb5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">NombreCC5</td>
                                    <td class="px-4 py-1 text-sm text-gray-900">1</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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

        // Funcionalidad de filtros
        document.addEventListener('DOMContentLoaded', function() {
            const searchBtn = document.querySelector('button[class*="bg-blue-600"]');
            const clearBtn = document.querySelector('button[class*="bg-gray-500"]');
            const exportBtn = document.querySelector('button[class*="bg-green-600"]');

            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    // Lógica de búsqueda
                    console.log('Buscando requerimientos...');
                    // Aquí se implementaría la lógica de filtrado
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    // Limpiar filtros
                    document.querySelectorAll('select, input[type="date"]').forEach(element => {
                        element.value = '';
                    });
                });
            }

            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    // Exportar a Excel
                    console.log('Exportando a Excel...');
                    // Aquí se implementaría la lógica de exportación
                });
            }
        });
    </script>
@endsection

