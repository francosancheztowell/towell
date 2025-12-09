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
        </div>
    </div>

    <!-- Modal para Desarrollador -->
    <div id="modalDesarrollador" class="fixed inset-0 hidden z-50 bg-gray-900 bg-opacity-60 backdrop-blur-sm items-center justify-center p-4">
        <div class="relative w-full max-w-4xl p-6 border shadow-2xl rounded-lg bg-white">
            <div class="flex items-center justify-between mb-4 pb-3 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Datos del Desarrollador</h3>
                <button type="button" onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="formDesarrollador">
                <input type="hidden" name="NoTelarId" id="modalTelarId">

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Número de Julio Rizo -->
                    <div>
                        <label for="NumeroJulioRizo" class="block text-sm font-medium text-gray-700 mb-1">
                            Número de Julio Rizo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="NumeroJulioRizo" name="NumeroJulioRizo" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Ingrese número de julio rizo">
                    </div>

                    <!-- Número de Julio Pie -->
                    <div>
                        <label for="NumeroJulioPie" class="block text-sm font-medium text-gray-700 mb-1">
                            Número de Julio Pie <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="NumeroJulioPie" name="NumeroJulioPie" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Ingrese número de julio pie">
                    </div>

                    <!-- Total Pasadas del Dibujo -->
                    <div>
                        <label for="TotalPasadasDibujo" class="block text-sm font-medium text-gray-700 mb-1">
                            Total Pasadas del Dibujo <span class="text-red-500">*</span>
                        </label>
                        <div class="relative" data-number-selector data-min="0" data-max="500" data-step="1">
                            <input type="number" id="TotalPasadasDibujo" name="TotalPasadasDibujo" min="0" step="1" required class="hidden">
                            <button type="button"
                                    class="number-selector-btn w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm flex items-center justify-between bg-white">
                                <span class="number-selector-value text-gray-400 font-semibold">Selecciona</span>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div class="number-selector-options hidden absolute left-0 right-0 mt-2 z-20">
                                <div class="number-selector-track flex gap-2 px-2 py-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-x-auto"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Eficiencia de Inicio -->
                    <div>
                        <label for="EficienciaInicio" class="block text-sm font-medium text-gray-700 mb-1">
                            Eficiencia de Inicio <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="EficienciaInicio" name="EficienciaInicio" required step="0.01" min="0" max="100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="0.00 %">
                    </div>
                    
                    <!-- Hora Inicio -->
                    <div>
                        <label for="HoraInicio" class="block text-sm font-medium text-gray-700 mb-1">
                            Hora Inicio <span class="text-red-500">*</span>
                        </label>
                        <input type="time" id="HoraInicio" name="HoraInicio" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>

                    <!-- Hora Final -->
                    <div>
                        <label for="HoraFinal" class="block text-sm font-medium text-gray-700 mb-1">
                            Hora Final <span class="text-red-500">*</span>
                        </label>
                        <input type="time" id="HoraFinal" name="HoraFinal" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>

                    <!-- Eficiencia Final -->
                    <div>
                        <label for="EficienciaFinal" class="block text-sm font-medium text-gray-700 mb-1">
                            Eficiencia Final <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="EficienciaFinal" name="EficienciaFinal" required step="0.01" min="0" max="100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="0.00 %">
                    </div>

                    <!-- Desarrollador -->
                    <div>
                        <label for="Desarrollador" class="block text-sm font-medium text-gray-700 mb-1">
                            Desarrollador <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="Desarrollador" name="Desarrollador" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Nombre del desarrollador">
                    </div>

                    <!-- Trama Ancho de Peine -->
                    <div>
                        <label for="TramaAnchoPeine" class="block text-sm font-medium text-gray-700 mb-1">
                            Trama Ancho de Peine <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="TramaAnchoPeine" name="TramaAnchoPeine" required step="0.01" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="0.00">
                    </div>

                    <!-- Desperdicio Trama -->
                    <div>
                        <label for="DesperdicioTrama" class="block text-sm font-medium text-gray-700 mb-1">
                            Desperdicio Trama <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="DesperdicioTrama" name="DesperdicioTrama" required step="0.01" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="0.00">
                    </div>

                    <!-- Longitud De Lucha Total -->
                    <div>
                        <label for="LongitudLuchaTot" class="block text-sm font-medium text-gray-700 mb-1">
                            Long. De Lucha Tot. <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="LongitudLuchaTot" name="LongitudLuchaTot" required step="0.01" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="0.00">
                    </div>

                    <!-- Codificación Modelo -->
                    <div>
                        <label for="CodificacionModelo" class="block text-sm font-medium text-gray-700 mb-1">
                            Codificación Modelo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="CodificacionModelo" name="CodificacionModelo" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Ingrese codificación">
                    </div>
                </div>

                <!-- Botones -->
                <div class="mt-6 flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="cerrarModal()"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectTelar = document.getElementById('telarOperador');
        const modal = document.getElementById('modalDesarrollador');
        const modalTelarId = document.getElementById('modalTelarId');
        const form = document.getElementById('formDesarrollador');
        const numberSelectors = [];

        initNumberSelectors();

        // Evento al seleccionar un telar
        selectTelar.addEventListener('change', function() {
            const telarSeleccionado = this.value;
            if (telarSeleccionado) {
                abrirModal(telarSeleccionado);
            }
        });

        // Función para abrir el modal
        function abrirModal(telarId) {
            modalTelarId.value = telarId;
            modal.classList.add('flex');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        // Función para cerrar el modal
        window.cerrarModal = function() {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            form.reset();
            resetNumberSelectors();
            selectTelar.value = '';
        };

        // Cerrar modal al hacer clic fuera de él
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                cerrarModal();
            }
        });

        // Manejar envío del formulario (solo frontend por ahora)
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar formulario
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Mostrar datos en consola para verificar
            const formData = new FormData(form);
            console.log('Datos del formulario:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }

            // Mostrar mensaje de éxito (simulado)
            Swal.fire({
                icon: 'success',
                title: 'Vista previa',
                text: 'Formulario validado correctamente (sin conexión al backend)',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Cerrar modal después de un breve delay
            setTimeout(() => {
                cerrarModal();
            }, 2000);
        });

        form.addEventListener('reset', resetNumberSelectors);

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

                numberSelectors.push({
                    optionsWrapper,
                    reset: resetSelector
                });
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
