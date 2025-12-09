@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores')

@section('navbar-right')
    <x-navbar.button-create/>
@endsection

@section('content')
    <div class="flex w-screen h-full overflow-hidden flex-col px-4 py-4 md:px-6 lg:px-6 bg-none-500">
        <div class="bg-white flex flex-col flex-1 rounded-md overflow-hidden max-w-full p-6">
            
            <!-- Select de Telares -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Seleccionar Telar</label>
                <select name="telar_operador" id="telarOperador" class="w-60 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="" disabled selected>Selecciona un Telar</option>
                    @foreach ($telares ?? [] as $telar)
                        <option value="{{ $telar->NoTelarId }}">
                            {{ $telar->NoTelarId }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tabla de Producciones -->
            <div id="tablaProducciones" class="hidden mt-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Producciones Disponibles</h3>
                    <p class="text-sm text-gray-600">Selecciona una producción para continuar</p>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    No. Orden
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha Cambio
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Modelo
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Seleccionar
                                </th>
                            </tr>
                        </thead>
                        <tbody id="bodyProducciones" class="bg-white divide-y divide-gray-200">
                            <!-- Las filas se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <!-- Mensaje cuando no hay datos -->
                <div id="noDataMessage" class="hidden text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="mt-2 text-sm">No se encontraron producciones para este telar</p>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectTelar = document.getElementById('telarOperador');
        const tablaProducciones = document.getElementById('tablaProducciones');
        const bodyProducciones = document.getElementById('bodyProducciones');
        const noDataMessage = document.getElementById('noDataMessage');

        // Evento al seleccionar un telar - Cargar producciones en tabla
        selectTelar.addEventListener('change', function() {
            const telarSeleccionado = this.value;
            if (telarSeleccionado) {
                cargarProducciones(telarSeleccionado);
            }
        });

        function cargarProducciones(telarId) {
            // Mostrar loading
            bodyProducciones.innerHTML = `
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                        <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2">Cargando producciones...</p>
                    </td>
                </tr>
            `;
            tablaProducciones.classList.remove('hidden');
            noDataMessage.classList.add('hidden');

            // Petición AJAX
            fetch(`/desarrolladores/telar/${telarId}/producciones`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.producciones.length > 0) {
                        bodyProducciones.innerHTML = '';
                        data.producciones.forEach(produccion => {
                            const row = document.createElement('tr');
                            row.className = 'hover:bg-gray-50 transition-colors';
                            row.innerHTML = `
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    ${produccion.NoProduccion}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    ${produccion.FechaInicio || 'N/A'}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    ${produccion.NombreProducto || 'N/A'}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <input type="checkbox" 
                                           class="checkbox-produccion w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer"
                                           data-telar="${telarId}"
                                           data-produccion="${produccion.NoProduccion}"
                                           onchange="seleccionarProduccion(this)">
                                </td>
                            `;
                            bodyProducciones.appendChild(row);
                        });
                    } else {
                        bodyProducciones.innerHTML = '';
                        noDataMessage.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    bodyProducciones.innerHTML = `
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-red-500">
                                Error al cargar las producciones
                            </td>
                        </tr>
                    `;
                });
        }

        // Función global para manejar la selección
        window.seleccionarProduccion = function(checkbox) {
            if (checkbox.checked) {
                // Desmarcar otros checkboxes
                document.querySelectorAll('.checkbox-produccion').forEach(cb => {
                    if (cb !== checkbox) {
                        cb.checked = false;
                    }
                });

                const telarId = checkbox.dataset.telar;
                const noProduccion = checkbox.dataset.produccion;
                
                // Redirigir al formulario
                window.location.href = `/desarrolladores/telar/${telarId}/produccion/${noProduccion}`;
            }
        };
    });
</script>
@endpush
