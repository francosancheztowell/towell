@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores')

@section('navbar-right')
    <x-navbar.button-create/>
@endsection

@section('content')
    <div class="flex w-full flex-col px-4 py-4 md:px-6 lg:px-6">
        <div class="bg-white flex flex-col rounded-md max-w-full p-6">
            <!-- Layout en columnas: Select a la izquierda, tabla a la derecha -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Columna: Select de Telares -->
                <div>
                    <label class="block text-sm font-medium mb-2">Seleccionar Telar</label>
                    <select name="telar_operador" id="telarOperador" class="w-full md:w-60 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="" disabled selected>Selecciona un Telar</option>
                        @foreach ($telares ?? [] as $telar)
                            <option value="{{ $telar->NoTelarId }}">
                                {{ $telar->NoTelarId }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Columna: Tabla de Producciones -->
                <div class="md:col-span-2">
                    <div id="tablaProducciones" class="hidden">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Producciones Disponibles</h3>
                            <p class="text-sm text-gray-600">Selecciona una producción para continuar</p>
                        </div>

                        <!-- Contenedor con scroll: horizontal para columnas y vertical limitado -->
                        <div class="overflow-x-auto overflow-y-auto max-h-96 rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-red-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Orden</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Cambio</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Seleccionar</th>
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

            <!-- Formulario inline debajo -->
            <div id="formContainer" class="hidden mt-8 border-t pt-6">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Datos del Desarrollador</h3>
                    <div class="flex flex-wrap gap-4 mt-2 text-sm text-gray-700">
                        <span>Telar: <strong id="formTelarId" class="text-blue-600">-</strong></span>
                        <span>No. Orden: <strong id="formNoProduccion" class="text-blue-600">-</strong></span>
                        <span>Modelo: <strong id="formNombreProducto">-</strong></span>
                    </div>
                </div>

                <form id="formDesarrollador" method="POST" action="{{ route('desarrolladores.store') }}">
                    @csrf
                    <input type="hidden" name="NoTelarId" id="inputTelarId" value="">
                    <input type="hidden" name="NoProduccion" id="inputNoProduccion" value="">

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="NumeroJulioRizo" class="block text-sm font-medium text-gray-700 mb-1">Número de Julio Rizo <span class="text-red-500">*</span></label>
                            <input type="text" id="NumeroJulioRizo" name="NumeroJulioRizo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Ingrese número de julio rizo">
                        </div>

                        <div>
                            <label for="NumeroJulioPie" class="block text-sm font-medium text-gray-700 mb-1">Número de Julio Pie <span class="text-red-500">*</span></label>
                            <input type="text" id="NumeroJulioPie" name="NumeroJulioPie" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Ingrese número de julio pie">
                        </div>

                        <div>
                            <label for="TotalPasadasDibujo" class="block text-sm font-medium text-gray-700 mb-1">Total Pasadas del Dibujo <span class="text-red-500">*</span></label>
                            <div class="relative" data-number-selector data-min="0" data-max="500" data-step="1">
                                <input type="number" id="TotalPasadasDibujo" name="TotalPasadasDibujo" min="0" step="1" required class="hidden">
                                <button type="button" class="number-selector-btn w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm flex items-center justify-between bg-white">
                                    <span class="number-selector-value text-gray-400 font-semibold">Selecciona</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="number-selector-options hidden absolute left-0 right-0 mt-2 z-20">
                                    <div class="number-selector-track flex gap-2 px-2 py-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-x-auto"></div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="EficienciaInicio" class="block text-sm font-medium text-gray-700 mb-1">Eficiencia de Inicio <span class="text-red-500">*</span></label>
                            <input type="number" id="EficienciaInicio" name="EficienciaInicio" required step="0.01" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00 %">
                        </div>

                        <div>
                            <label for="HoraInicio" class="block text-sm font-medium text-gray-700 mb-1">Hora Inicio <span class="text-red-500">*</span></label>
                            <input type="time" id="HoraInicio" name="HoraInicio" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>

                        <div>
                            <label for="HoraFinal" class="block text-sm font-medium text-gray-700 mb-1">Hora Final <span class="text-red-500">*</span></label>
                            <input type="time" id="HoraFinal" name="HoraFinal" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>

                        <div>
                            <label for="EficienciaFinal" class="block text-sm font-medium text-gray-700 mb-1">Eficiencia Final <span class="text-red-500">*</span></label>
                            <input type="number" id="EficienciaFinal" name="EficienciaFinal" required step="0.01" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00 %">
                        </div>

                        <div>
                            <label for="Desarrollador" class="block text-sm font-medium text-gray-700 mb-1">Desarrollador <span class="text-red-500">*</span></label>
                            <input type="text" id="Desarrollador" name="Desarrollador" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Nombre del desarrollador">
                        </div>

                        <div>
                            <label for="TramaAnchoPeine" class="block text-sm font-medium text-gray-700 mb-1">Trama Ancho de Peine <span class="text-red-500">*</span></label>
                            <input type="number" id="TramaAnchoPeine" name="TramaAnchoPeine" required step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
                        </div>

                        <div>
                            <label for="DesperdicioTrama" class="block text-sm font-medium text-gray-700 mb-1">Desperdicio Trama <span class="text-red-500">*</span></label>
                            <input type="number" id="DesperdicioTrama" name="DesperdicioTrama" required step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
                        </div>

                        <div>
                            <label for="LongitudLuchaTot" class="block text-sm font-medium text-gray-700 mb-1">Long. De Lucha Tot. <span class="text-red-500">*</span></label>
                            <input type="number" id="LongitudLuchaTot" name="LongitudLuchaTot" required step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
                        </div>

                        <div>
                            <label for="CodificacionModelo" class="block text-sm font-medium text-gray-700 mb-1">Codificación Modelo <span class="text-red-500">*</span></label>
                            <input type="text" id="CodificacionModelo" name="CodificacionModelo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Ingrese codificación">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t">
                        <button type="button" id="btnCancelarFormulario" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium">Cancelar</button>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">Guardar</button>
                    </div>
                </form>
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
        const formContainer = document.getElementById('formContainer');
        const inputTelarId = document.getElementById('inputTelarId');
        const inputNoProduccion = document.getElementById('inputNoProduccion');
        const formTelarId = document.getElementById('formTelarId');
        const formNoProduccion = document.getElementById('formNoProduccion');
        const formNombreProducto = document.getElementById('formNombreProducto');
        const btnCancelarFormulario = document.getElementById('btnCancelarFormulario');
        const form = document.getElementById('formDesarrollador');
        const numberSelectors = [];

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
                                           data-modelo="${produccion.NombreProducto || ''}"
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

        // Función global para manejar la selección: mostrar formulario debajo
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
                const modelo = checkbox.dataset.modelo || '';
                
                // Mostrar formulario inline y setear datos
                inputTelarId.value = telarId;
                inputNoProduccion.value = noProduccion;
                formTelarId.textContent = telarId;
                formNoProduccion.textContent = noProduccion;
                formNombreProducto.textContent = modelo || '-';

                // Resetear y preparar selectores numéricos
                resetNumberSelectors();
                initNumberSelectors();

                formContainer.classList.remove('hidden');
            }
        };

        // Cancelar formulario vuelve a ocultarlo y limpia selección
        btnCancelarFormulario.addEventListener('click', function() {
            form.reset();
            resetNumberSelectors();
            formContainer.classList.add('hidden');
            document.querySelectorAll('.checkbox-produccion').forEach(cb => cb.checked = false);
        });

        // Lógica de selectores numéricos (tomada del formulario original)
        function initNumberSelectors() {
            document.querySelectorAll('[data-number-selector]').forEach(selector => {
                const hiddenInput = selector.querySelector('input[type="number"]');
                const triggerBtn = selector.querySelector('.number-selector-btn');
                const valueSpan = selector.querySelector('.number-selector-value');
                const optionsWrapper = selector.querySelector('.number-selector-options');
                const track = selector.querySelector('.number-selector-track');

                if (!hiddenInput || !triggerBtn || !valueSpan || !optionsWrapper || !track) {
                    return;
                }

                const min = parseInt(selector.dataset.min ?? hiddenInput.min ?? '0', 10);
                const max = parseInt(selector.dataset.max ?? hiddenInput.max ?? '100', 10);
                const step = parseInt(selector.dataset.step ?? hiddenInput.step ?? '1', 10);

                buildSelectorOptions(track, min, max, step);

                triggerBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    const shouldOpen = optionsWrapper.classList.contains('hidden');
                    closeAllNumberSelectors();
                    if (shouldOpen) {
                        optionsWrapper.classList.remove('hidden');
                    }
                });

                track.addEventListener('click', (event) => {
                    const option = event.target.closest('.number-option');
                    if (!option) return;
                    event.preventDefault();
                    setNumberSelectorValue(option.dataset.value);
                });

                const setNumberSelectorValue = (value) => {
                    hiddenInput.value = value;
                    valueSpan.textContent = value;
                    valueSpan.classList.remove('text-gray-400');
                    valueSpan.classList.add('text-blue-600');
                    track.querySelectorAll('.number-option').forEach(opt => {
                        const isActive = opt.dataset.value === String(value);
                        opt.classList.toggle('bg-blue-600', isActive);
                        opt.classList.toggle('text-white', isActive);
                        opt.classList.toggle('border-blue-600', isActive);
                    });
                    optionsWrapper.classList.add('hidden');
                };

                const resetSelector = () => {
                    hiddenInput.value = '';
                    valueSpan.textContent = 'Selecciona';
                    valueSpan.classList.remove('text-blue-600');
                    valueSpan.classList.add('text-gray-400');
                    track.querySelectorAll('.number-option').forEach(opt => {
                        opt.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
                    });
                    optionsWrapper.classList.add('hidden');
                };

                if (hiddenInput.value !== '') {
                    setNumberSelectorValue(hiddenInput.value);
                } else {
                    resetSelector();
                }

                numberSelectors.push({ optionsWrapper, reset: resetSelector });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-number-selector]')) {
                    closeAllNumberSelectors();
                }
            });
        }

        function buildSelectorOptions(track, min, max, step) {
            track.innerHTML = '';
            for (let value = min; value <= max; value += step) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.value = String(value);
                button.textContent = String(value);
                button.className = 'number-option shrink-0 px-3 py-2 text-sm font-semibold border border-gray-300 rounded-md bg-white hover:bg-blue-50 focus:outline-none focus:ring-1 focus:ring-blue-500';
                track.appendChild(button);
            }
        }

        function closeAllNumberSelectors() {
            numberSelectors.forEach(selector => {
                selector.optionsWrapper.classList.add('hidden');
            });
        }

        function resetNumberSelectors() {
            numberSelectors.forEach(selector => selector.reset());
        }
    });
</script>
@endpush
