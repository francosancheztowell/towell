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
                        <input type="number" id="TotalPasadasDibujo" name="TotalPasadasDibujo" required step="1" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Ingrese total pasadas">
                    </div>

                    <!-- Hora Inicio -->
                    <div>
                        <label for="HoraInicio" class="block text-sm font-medium text-gray-700 mb-1">
                            Hora Inicio <span class="text-red-500">*</span>
                        </label>
                        <input type="time" id="HoraInicio" name="HoraInicio" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
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
    });
</script>
@endpush
