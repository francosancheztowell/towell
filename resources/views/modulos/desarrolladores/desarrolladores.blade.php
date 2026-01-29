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
                        <div class="overflow-y-auto max-h-96 rounded-lg border border-gray-200">
                            <table class="w-full table-fixed divide-y divide-red-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salon de Tejido</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Orden</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Cambio</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño Clave</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Seleccionar</th>
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
                            <select id="NumeroJulioRizo" name="NumeroJulioRizo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="" disabled selected>Selecciona un Julio</option>
                                @foreach ($juliosRizo ?? [] as $julio)
                                    @if($julio)
                                        <option value="{{ data_get($julio, 'NoJulio') ?? '' }}">{{ data_get($julio, 'NoJulio') ?? '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="NumeroJulioPie" class="block text-sm font-medium text-gray-700 mb-1">Número de Julio Pie <span class="text-red-500">*</span></label>
                            <select id="NumeroJulioPie" name="NumeroJulioPie" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="" disabled selected>Selecciona un Julio</option>
                                @foreach ($juliosPie ?? [] as $julio)
                                    @if($julio)
                                        <option value="{{ data_get($julio, 'NoJulio') ?? '' }}">{{ data_get($julio, 'NoJulio') ?? '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="TotalPasadasDibujo" class="block text-sm font-medium text-gray-700 mb-1">Total Pasadas del Dibujo <span class="text-red-500">*</span></label>
                            <input type="number" id="TotalPasadasDibujo" name="TotalPasadasDibujo" min="1" step="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Ingrese total de pasadas">
                        </div>

                        <div>
                            <label for="EficienciaInicio" class="block text-sm font-medium text-gray-700 mb-1">Eficiencia de Inicio <span class="text-red-500">*</span></label>
                            <div class="relative" data-number-selector data-min="0" data-max="100" data-step="1" data-suggested="80">
                                <input type="number" id="EficienciaInicio" name="EficienciaInicio" min="0" step="1" required class="hidden">
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
                            <label for="HoraInicio" class="block text-sm font-medium text-gray-700 mb-1">Hora Inicio <span class="text-red-500">*</span></label>
                            <input type="time" id="HoraInicio" name="HoraInicio" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>

                        <div>
                            <label for="HoraFinal" class="block text-sm font-medium text-gray-700 mb-1">Hora Final <span class="text-red-500">*</span></label>
                            <input type="time" id="HoraFinal" name="HoraFinal" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>

                        <div>
                            <label for="EficienciaFinal" class="block text-sm font-medium text-gray-700 mb-1">Eficiencia Final <span class="text-red-500">*</span></label>
                            <div class="relative" data-number-selector data-min="0" data-max="100" data-step="1" data-suggested="80">
                                <input type="number" id="EficienciaFinal" name="EficienciaFinal" min="0" step="1" required class="hidden">
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
                            <label for="Desarrollador" class="block text-sm font-medium text-gray-700 mb-1">Desarrollador <span class="text-red-500">*</span></label>
                            <select id="Desarrollador" name="Desarrollador" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="" disabled {{ !old('Desarrollador', $desarrolladorActual ?? '') ? 'selected' : '' }}>Selecciona un Desarrollador</option>
                                @foreach ($desarrolladores ?? [] as $desarrollador)
                                    @if($desarrollador)
                                        @php $nombre = data_get($desarrollador, 'nombre') ?? ''; @endphp
                                        <option value="{{ $nombre }}" {{ old('Desarrollador', $desarrolladorActual ?? '') === $nombre ? 'selected' : '' }}>
                                            {{ $nombre }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="TramaAnchoPeine" class="block text-sm font-medium text-gray-700 mb-1">Trama Ancho de Peine</label>
                            <input type="number" id="TramaAnchoPeine" name="TramaAnchoPeine" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
                        </div>

                        <div>
                            <label for="DesperdicioTrama" class="block text-sm font-medium text-gray-700 mb-1">Desperdicio Trama</label>
                            <input type="number" id="DesperdicioTrama" name="DesperdicioTrama" step="0.01" min="0" min="0" value="11" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
                        </div>

                        <div>
                            <label for="LongitudLuchaTot" class="block text-sm font-medium text-gray-700 mb-1">Long. De Lucha Tot.</label>
                            <input type="number" id="LongitudLuchaTot" name="LongitudLuchaTot" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Codificación Modelo - Sección separada con auto-avance -->
                    <div class="mt-6 pt-6 border-t">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Codificación Modelo</label>
                        <div class="overflow-x-auto pb-2">
                            <div class="flex justify-start items-center gap-2 min-w-max px-2">
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="0" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="1" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="2" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="3" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="4" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="5" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="6" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="7" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="8" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="9" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="10" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="11" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="12" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="13" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="14" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="15" required>
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="16">
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="17">
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="18">
                                <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="19">
                                <span id="codificacionSuffix" class="text-lg font-bold text-gray-600">.JCS</span>
                            </div>
                        </div>
                        <input type="hidden" id="CodificacionModelo" name="CodificacionModelo" required>
                        {{-- <p id="codificacionNoData" class="mt-2 text-sm text-red-500 hidden">No se obtuvieron datos.</p>  --}}
                    </div>

                    <!-- Tabla de Detalles de la Orden -->
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Detalles de la Orden</h3>
                            <button type="button" id="btnAgregarFilaDetalle" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Agregar Fila
                            </button>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calibre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hilo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fibra</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cod Color</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre Color</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pasadas<span class="text-red-500">*</span></th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyDetallesOrden" class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">
                                            Selecciona una producción para ver los detalles
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectTelar = document.getElementById('telarOperador');
        const tablaProducciones = document.getElementById('tablaProducciones');
        const bodyProducciones = document.getElementById('bodyProducciones');
        const bodyDetallesOrden = document.getElementById('bodyDetallesOrden');
        const noDataMessage = document.getElementById('noDataMessage');
        const formContainer = document.getElementById('formContainer');
        const inputTelarId = document.getElementById('inputTelarId');
        const inputNoProduccion = document.getElementById('inputNoProduccion');
        const formTelarId = document.getElementById('formTelarId');
        const formNoProduccion = document.getElementById('formNoProduccion');
        const formNombreProducto = document.getElementById('formNombreProducto');
        const btnCancelarFormulario = document.getElementById('btnCancelarFormulario');
        const form = document.getElementById('formDesarrollador');
        const resumenFields = {
            JulioRizo: document.getElementById('resumenJulioRizo'),
            JulioPie: document.getElementById('resumenJulioPie'),
            EfiInicial: document.getElementById('resumenEfiInicial'),
            EfiFinal: document.getElementById('resumenEfiFinal'),
            DesperdicioTrama: document.getElementById('resumenDesperdicioTrama'),
        };
        const selectNumeroJulioRizo = document.getElementById('NumeroJulioRizo');
        const selectNumeroJulioPie = document.getElementById('NumeroJulioPie');
        const inputDesperdicioTrama = document.getElementById('DesperdicioTrama');
        const inputTramaAnchoPeine = document.getElementById('TramaAnchoPeine');
        const inputLongitudLuchaTot = document.getElementById('LongitudLuchaTot');
        const modalPasadas = document.getElementById('modalPasadas');
        const modalPasadasAceptar = document.getElementById('modalPasadasAceptar');
        const modalPasadasCancelar = document.getElementById('modalPasadasCancelar');
        const numberSelectors = [];
        let numberSelectorDocumentListenerAttached = false;
        const codificacionInputs = document.querySelectorAll('.codificacion-char');
        const codificacionHidden = document.getElementById('CodificacionModelo');
        const codificacionNoData = document.getElementById('codificacionNoData');
        let codificacionFetchAttempted = false;
        const codificacionSuffixSpan = document.getElementById('codificacionSuffix');
        let sumaPasadasDetalle = 0;
        let omitirConfirmacionPasadas = false;

        function formatResumenValue(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }
            return String(value);
        }

        function actualizarResumenCatCodificados(data) {
            Object.entries(resumenFields).forEach(([key, element]) => {
                if (!element) {
                    return;
                }
                element.textContent = formatResumenValue(data ? data[key] : null);
            });
        }

        function mostrarModalPasadas() {
            modalPasadas?.classList.remove('hidden');
        }

        function ocultarModalPasadas() {
            modalPasadas?.classList.add('hidden');
        }

        // Auto-focus de Total Pasadas del Dibujo a Eficiencia Inicio
        const totalPasadasDibujo = document.getElementById('TotalPasadasDibujo');
        if (totalPasadasDibujo) {
            totalPasadasDibujo.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // Buscar el botón del selector de Eficiencia Inicio
                    const eficienciaInicioWrapper = document.querySelector('[data-number-selector] #EficienciaInicio');
                    if (eficienciaInicioWrapper) {
                        const triggerBtn = eficienciaInicioWrapper.closest('[data-number-selector]').querySelector('.number-selector-btn');
                        if (triggerBtn) {
                            triggerBtn.focus();
                            triggerBtn.click();
                        }
                    }
                }
            });
        }

        // Auto-avance en inputs de codificación
        codificacionInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                const value = this.value.toUpperCase();
                this.value = value;

                // Auto-avanzar al siguiente input si se ingresó un caracter
                if (value.length === 1 && index < codificacionInputs.length - 1) {
                    codificacionInputs[index + 1].focus();
                }

                // Actualizar el campo hidden con el valor completo
                updateCodificacionModelo();
            });

            // Manejar backspace para retroceder
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    codificacionInputs[index - 1].focus();
                }
            });

            // Manejar pegado
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text').toUpperCase();
                const chars = pastedText.split('');

                chars.forEach((char, i) => {
                    const targetIndex = index + i;
                    if (targetIndex < codificacionInputs.length) {
                        codificacionInputs[targetIndex].value = char;
                    }
                });

                // Mover foco al último caracter pegado o al final
                const lastIndex = Math.min(index + chars.length, codificacionInputs.length - 1);
                codificacionInputs[lastIndex].focus();

                updateCodificacionModelo();
            });
        });

        function getCodificacionSuffix(telarId) {
            const numericTelar = Number.parseInt(telarId, 10);
            if (Number.isFinite(numericTelar) && numericTelar >= 200 && numericTelar <= 299) {
                return 'JC5';
            }
            if (Number.isFinite(numericTelar) && numericTelar >= 300) {
                return '';
            }
            return 'JCS';
        }

        function updateCodificacionSuffix(telarId) {
            const suffix = getCodificacionSuffix(telarId);
            if (codificacionSuffixSpan) {
                codificacionSuffixSpan.textContent = suffix ? `.${suffix}` : '';
                codificacionSuffixSpan.classList.toggle('hidden', !suffix);
            }
            return suffix;
        }

        function updateCodificacionModelo() {
            const fullCode = Array.from(codificacionInputs).map(input => input.value).join('');
            const suffix = updateCodificacionSuffix(inputTelarId?.value || selectTelar?.value);
            if (!fullCode) {
                codificacionHidden.value = '';
            } else {
                codificacionHidden.value = suffix ? `${fullCode}.${suffix}` : fullCode;
            }
            updateCodificacionNoDataMessage();
        }

        function updateCodificacionNoDataMessage() {
            if (!codificacionNoData) return;
            const shouldShow = codificacionFetchAttempted && !codificacionHidden.value;
            codificacionNoData.classList.toggle('hidden', !shouldShow);
        }

        function setCodificacionFromCodigoDibujo(codigoDibujo) {
            const normalized = String(codigoDibujo ?? '')
                .toUpperCase()
                .trim()
                .replace(/\.JC5$/i, '')
                .replace(/\.JCS$/i, '')
                .replace(/\s+/g, '');

            codificacionInputs.forEach((input, index) => {
                input.value = normalized[index] ?? '';
            });
            updateCodificacionModelo();
        }

        // Evento al seleccionar un telar - Cargar producciones en tabla
        selectTelar.addEventListener('change', function() {
            const telarSeleccionado = this.value;
            if (telarSeleccionado) {
                updateCodificacionSuffix(telarSeleccionado);
                updateCodificacionModelo();
                cargarProducciones(telarSeleccionado);
            }
        });

        function cargarProducciones(telarId) {
            // Mostrar loading
            bodyProducciones.innerHTML = `
                <tr>
                    <td colspan="6" class="px-3 py-3 text-center text-gray-500">
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
                                <td class="px-3 py-3 whitespace-nowrap text-xs font-medium text-gray-900">
                                    ${produccion.SalonTejidoId ?? 'N/A'}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs font-medium text-gray-900">
                                    ${produccion.NoProduccion}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">
                                    ${produccion.FechaInicio ? new Date(produccion.FechaInicio).toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'}) : 'N/A'}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">
                                    ${produccion.TamanoClave ?? 'N/A'}
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-600 break-words">
                                    ${produccion.NombreProducto || 'N/A'}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-center">
                                    <input type="checkbox"
                                           class="checkbox-produccion w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer"
                                           data-telar="${telarId}"
                                           data-salon="${produccion.SalonTejidoId ?? ''}"
                                           data-tamano="${produccion.TamanoClave ?? ''}"
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
                            <td colspan="6" class="px-3 py-3 text-center text-red-500">
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
                const salonTejidoId = checkbox.dataset.salon || '';
                const tamanoClave = checkbox.dataset.tamano || '';

                // Mostrar formulario inline y setear datos
                inputTelarId.value = telarId;
                inputNoProduccion.value = noProduccion;
                formTelarId.textContent = telarId;
                formNoProduccion.textContent = noProduccion;
                formNombreProducto.textContent = modelo || '-';
                updateCodificacionSuffix(telarId);

                // Obtener CodigoDibujo por SalonTejidoId + TamanoClave y auto-llenar codificación
                codificacionFetchAttempted = true;
                updateCodificacionNoDataMessage();

                if (salonTejidoId && tamanoClave) {
                    fetch(`/desarrolladores/modelo-codificado/${encodeURIComponent(salonTejidoId)}/${encodeURIComponent(tamanoClave)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.codigoDibujo) {
                                setCodificacionFromCodigoDibujo(data.codigoDibujo);
                            } else {
                                setCodificacionFromCodigoDibujo('');
                            }
                        })
                        .catch(error => {
                            setCodificacionFromCodigoDibujo('');
                        });
                } else {
                    setCodificacionFromCodigoDibujo('');
                }

                // Resetear selectores numéricos para nuevo registro
                resetNumberSelectors();
                actualizarResumenCatCodificados(null);
                prefillFormularioDesdeCatCodificados(null);
                resetDetallePasadas();

                // Cargar detalles de la orden
                cargarDetallesOrden(noProduccion);
                cargarResumenCatCodificados(telarId, noProduccion);

                formContainer.classList.remove('hidden');
            }
        };

        // Función para cargar detalles de la orden
        function cargarDetallesOrden(noProduccion) {

            // Mostrar loading
            bodyDetallesOrden.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2">Cargando detalles...</p>
                    </td>
                </tr>
            `;

            // Petición AJAX
            fetch(`/desarrolladores/orden/${noProduccion}/detalles`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.detalles.length > 0) {
                        bodyDetallesOrden.innerHTML = '';
                        data.detalles.forEach((detalle, index) => {
                            const calibre = detalle.Calibre ?? detalle.calibre ?? '';
                            const hilo = detalle.Hilo ?? detalle.hilo ?? '';
                            const fibra = detalle.Fibra ?? detalle.fibra ?? '';
                            const codColor = detalle.CodColor ?? detalle.codColor ?? '';
                            const nombreColor = detalle.NombreColor ?? detalle.nombreColor ?? '';
                            const pasadasValue = detalle.Pasadas ?? detalle.pasadas ?? '';
                            const pasadasKey = detalle.pasadasField ?? detalle.pasadas_key ?? index;

                            const row = crearFilaDetalle(index, calibre, hilo, fibra, codColor, nombreColor, pasadasValue, pasadasKey, false);
                            bodyDetallesOrden.appendChild(row);
                        });
                        adjuntarListenersDetallePasadas();
                    } else {
                        bodyDetallesOrden.innerHTML = `
                            <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">
                                No se encontraron detalles para esta orden
                            </td>
                            </tr>
                        `;
                        resetDetallePasadas();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    bodyDetallesOrden.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-red-500">
                                Error al cargar los detalles
                            </td>
                        </tr>
                    `;
                    resetDetallePasadas();
                });
        }

        // Cancelar formulario vuelve a ocultarlo y limpia selección
        btnCancelarFormulario.addEventListener('click', function() {
            form.reset();
            resetNumberSelectors();
            codificacionInputs.forEach(input => input.value = '');
            codificacionHidden.value = '';
            codificacionFetchAttempted = false;
            updateCodificacionNoDataMessage();
            formContainer.classList.add('hidden');
            document.querySelectorAll('.checkbox-produccion').forEach(cb => cb.checked = false);
            // Limpiar tabla de detalles
            document.getElementById('bodyDetallesOrden').innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">
                        Selecciona una producción para ver los detalles
                    </td>
                </tr>
            `;
            resetDetallePasadas();
            ocultarModalPasadas();
            actualizarResumenCatCodificados(null);
            prefillFormularioDesdeCatCodificados(null);
        });

        function setSelectValue(select, value) {
            if (!select) {
                return;
            }

            if (value === null || value === undefined || value === '') {
                const placeholder = select.querySelector('option[disabled]');
                if (placeholder) {
                    placeholder.selected = true;
                } else {
                    select.value = '';
                }
                return;
            }

            const optionValue = String(value);
            let option = Array.from(select.options).find(opt => opt.value === optionValue);
            if (!option) {
                option = new Option(optionValue, optionValue);
                select.add(option);
            }

            option.selected = true;
        }

        function setNumberSelectorValueById(inputId, value) {
            const selector = numberSelectors.find(item => item.input && item.input.id === inputId);
            const normalizedValue = value === null || value === undefined || value === ''
                ? ''
                : String(value);

            if (!selector) {
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = normalizedValue;
                }
                return;
            }

            if (normalizedValue === '') {
                selector.reset();
            } else {
                selector.setValue(normalizedValue);
            }
        }

        function prefillFormularioDesdeCatCodificados(data) {
            setSelectValue(selectNumeroJulioRizo, data ? data.JulioRizo : '');
            setSelectValue(selectNumeroJulioPie, data ? data.JulioPie : '');
            setNumberSelectorValueById('EficienciaInicio', data ? data.EfiInicial : '');
            setNumberSelectorValueById('EficienciaFinal', data ? data.EfiFinal : '');
            if (inputDesperdicioTrama) {
                inputDesperdicioTrama.value = data && data.DesperdicioTrama !== null ? data.DesperdicioTrama : 11;
            }
        }

        function cargarResumenCatCodificados(telarId, noProduccion) {
            actualizarResumenCatCodificados(null);
            prefillFormularioDesdeCatCodificados(null);

            if (!telarId || !noProduccion) {
                return;
            }

            fetch(`/desarrolladores/catcodificados/${encodeURIComponent(telarId)}/${encodeURIComponent(noProduccion)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.registro) {
                        actualizarResumenCatCodificados(data.registro);
                        prefillFormularioDesdeCatCodificados(data.registro);
                    }
                })
                .catch(error => {
                    console.error('Error al obtener datos registrados:', error);
                });
        }

        function obtenerInputsPasadasDetalle() {
            if (!bodyDetallesOrden) {
                return [];
            }
            return Array.from(bodyDetallesOrden.querySelectorAll('input[name^="pasadas"]'));
        }

        function calcularSumaPasadasDetalle() {
            return obtenerInputsPasadasDetalle().reduce((total, input) => {
                const valor = parseInt(input.value, 10);
                return total + (Number.isFinite(valor) ? valor : 0);
            }, 0);
        }

        function sincronizarTotalPasadasConDetalle() {
            sumaPasadasDetalle = calcularSumaPasadasDetalle();
            if (!totalPasadasDibujo) {
                return;
            }
            const inputs = obtenerInputsPasadasDetalle();
            if (inputs.length === 0) {
                totalPasadasDibujo.value = '';
                return;
            }
            totalPasadasDibujo.value = String(sumaPasadasDetalle);
        }

        function adjuntarListenersDetallePasadas() {
            const inputs = obtenerInputsPasadasDetalle();
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    sincronizarTotalPasadasConDetalle();
                });
            });
            sincronizarTotalPasadasConDetalle();
        }

        // Contador para generar índices únicos de filas nuevas
        let contadorFilasNuevas = 1000;

        const detalleMaterialRoutes = {
            calibres: "{{ route('tejido.produccion.reenconado.calibres') }}",
            fibras: "{{ route('tejido.produccion.reenconado.fibras') }}",
            colores: "{{ route('tejido.produccion.reenconado.colores') }}"
        };

        const detalleMaterialCache = {
            calibres: null,
            fibras: new Map(),
            colores: new Map()
        };

        const setDetalleSelectOptions = (select, options, placeholder, selectedValue = '') => {
            if (!select) return;
            select.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            select.appendChild(placeholderOption);

            options.forEach((opt) => {
                const option = document.createElement('option');
                if (typeof opt === 'string') {
                    option.value = opt;
                    option.textContent = opt;
                } else {
                    option.value = opt.value;
                    option.textContent = opt.label;
                    if (opt.name) option.dataset.name = opt.name;
                }
                select.appendChild(option);
            });

            select.value = selectedValue || '';
            select.disabled = options.length === 0;
        };

        const ensureDetalleOption = (select, value, label, name = '') => {
            if (!select || !value) return;
            const exists = Array.from(select.options).some(opt => opt.value === value);
            if (!exists) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label || value;
                if (name) option.dataset.name = name;
                select.appendChild(option);
            }
        };

        const fetchDetalleJson = async (url, params = {}) => {
            const query = new URLSearchParams(params);
            const fullUrl = query.toString() ? `${url}?${query}` : url;
            const response = await fetch(fullUrl);
            if (!response.ok) {
                throw new Error(`Request failed: ${response.status}`);
            }
            return response.json();
        };

        const getDetalleCalibres = async () => {
            if (detalleMaterialCache.calibres) return detalleMaterialCache.calibres;
            try {
                const data = await fetchDetalleJson(detalleMaterialRoutes.calibres);
                const items = (data?.data || []).map(i => i.ItemId).filter(Boolean);
                detalleMaterialCache.calibres = items;
                return items;
            } catch (e) {
                console.error('No se pudieron cargar calibres', e);
                return [];
            }
        };

        const getDetalleFibras = async (itemId) => {
            if (detalleMaterialCache.fibras.has(itemId)) return detalleMaterialCache.fibras.get(itemId);
            try {
                const data = await fetchDetalleJson(detalleMaterialRoutes.fibras, { itemId });
                const items = (data?.data || []).map(i => i.ConfigId).filter(Boolean);
                detalleMaterialCache.fibras.set(itemId, items);
                return items;
            } catch (e) {
                console.error('No se pudieron cargar fibras', e);
                return [];
            }
        };

        const getDetalleColores = async (itemId) => {
            if (detalleMaterialCache.colores.has(itemId)) return detalleMaterialCache.colores.get(itemId);
            try {
                const data = await fetchDetalleJson(detalleMaterialRoutes.colores, { itemId });
                const items = (data?.data || []).map(c => ({
                    value: c.InventColorId,
                    label: `${c.InventColorId} - ${c.Name}`,
                    name: c.Name
                })).filter(c => c.value);
                detalleMaterialCache.colores.set(itemId, items);
                return items;
            } catch (e) {
                console.error('No se pudieron cargar colores', e);
                return [];
            }
        };

        const getDetalleRowEls = (row) => ({
            calibreEl: row.querySelector('.detalle-calibre'),
            fibraEl: row.querySelector('.detalle-fibra'),
            codColorEl: row.querySelector('.detalle-codcolor'),
            colorEl: row.querySelector('.detalle-color')
        });

        const resetDependentsForRow = (row) => {
            const { fibraEl, codColorEl, colorEl } = getDetalleRowEls(row);
            setDetalleSelectOptions(fibraEl, [], 'Selecciona calibre');
            setDetalleSelectOptions(codColorEl, [], 'Selecciona calibre');
            if (colorEl) colorEl.value = '';
        };

        const updateColorFromCod = (row, fallback = '') => {
            const { codColorEl, colorEl } = getDetalleRowEls(row);
            const selected = codColorEl?.selectedOptions?.[0];
            if (colorEl) {
                colorEl.value = selected?.dataset?.name || fallback || '';
            }
        };

        const loadDependentsForRow = async (row, itemId, selections = {}) => {
            const { fibraEl, codColorEl } = getDetalleRowEls(row);
            if (!itemId) {
                resetDependentsForRow(row);
                return;
            }

            setDetalleSelectOptions(fibraEl, [], 'Cargando...');
            setDetalleSelectOptions(codColorEl, [], 'Cargando...');

            const [fibras, colores] = await Promise.all([
                getDetalleFibras(itemId),
                getDetalleColores(itemId)
            ]);

            setDetalleSelectOptions(fibraEl, fibras, 'Selecciona fibra', selections.fibra || '');
            setDetalleSelectOptions(codColorEl, colores, 'Selecciona color', selections.codColor || '');

            if (selections.fibra) {
                ensureDetalleOption(fibraEl, selections.fibra, selections.fibra);
                fibraEl.value = selections.fibra;
            }

            if (selections.codColor) {
                ensureDetalleOption(codColorEl, selections.codColor, selections.codColor, selections.colorName);
                codColorEl.value = selections.codColor;
            }

            updateColorFromCod(row, selections.colorName || '');
        };

        const initMaterialSelectorsForRow = async (row, selections = {}) => {
            const { calibreEl, codColorEl } = getDetalleRowEls(row);
            if (!calibreEl) return;

            setDetalleSelectOptions(calibreEl, [], 'Cargando...');
            const calibres = await getDetalleCalibres();
            setDetalleSelectOptions(calibreEl, calibres, 'Selecciona calibre', selections.calibre || '');
            if (selections.calibre) {
                ensureDetalleOption(calibreEl, selections.calibre, selections.calibre);
                calibreEl.value = selections.calibre;
            }

            await loadDependentsForRow(row, selections.calibre || '', selections);

            calibreEl.addEventListener('change', async (e) => {
                await loadDependentsForRow(row, e.target.value, {});
            });

            codColorEl?.addEventListener('change', () => {
                updateColorFromCod(row, '');
            });
        };

        // Función para crear una fila de detalle editable
        function crearFilaDetalle(index, calibre = '', hilo = '', fibra = '', codColor = '', nombreColor = '', pasadas = '', pasadasKey = null, usarSelects = false) {
            const key = pasadasKey ?? `nuevo_${contadorFilasNuevas++}`;
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors fila-detalle';
            row.dataset.index = index;
            
            row.innerHTML = `
                <td class="px-4 py-2">
                    ${
                        usarSelects
                            ? `<select name="detalle_calibre[]"
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm detalle-calibre">
                                       <option value="">Cargando...</option>
                                   </select>`
                            : `<input type="text" 
                                       name="detalle_calibre[]" 
                                       value="${calibre}" 
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       placeholder="Calibre">`
                    }
                </td>
                <td class="px-4 py-2">
                    <input type="text" 
                           name="detalle_hilo[]" 
                           value="${hilo}" 
                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="Hilo">
                </td>
                <td class="px-4 py-2">
                    ${
                        usarSelects
                            ? `<select name="detalle_fibra[]"
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm detalle-fibra"
                                       disabled>
                                       <option value="">Selecciona calibre</option>
                                   </select>`
                            : `<input type="text" 
                                       name="detalle_fibra[]" 
                                       value="${fibra}" 
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       placeholder="Fibra">`
                    }
                </td>
                <td class="px-4 py-2">
                    ${
                        usarSelects
                            ? `<select name="detalle_codcolor[]"
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm detalle-codcolor"
                                       disabled>
                                       <option value="">Selecciona calibre</option>
                                   </select>`
                            : `<input type="text" 
                                       name="detalle_codcolor[]" 
                                       value="${codColor}" 
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       placeholder="Cod Color">`
                    }
                </td>
                <td class="px-4 py-2">
                    <input type="text" 
                           name="detalle_nombrecolor[]" 
                           value="${nombreColor}" 
                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm ${usarSelects ? 'bg-gray-50 detalle-color' : ''}"
                           placeholder="Nombre Color"
                           ${usarSelects ? 'readonly' : ''}>
                </td>
                <td class="px-4 py-2">
                    <input type="number" 
                           name="pasadas[${key}]" 
                           value="${pasadas}" 
                           min="1" 
                           step="1" 
                           required
                           class="w-20 px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="0">
                </td>
                <td class="px-4 py-2 text-center">
                    <button type="button" 
                            onclick="eliminarFilaDetalle(this)" 
                            class="p-1.5 text-red-600 hover:bg-red-100 rounded-md transition-colors" 
                            title="Eliminar fila">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </td>
            `;

            if (usarSelects) {
                void initMaterialSelectorsForRow(row, {
                    calibre,
                    fibra,
                    codColor,
                    colorName: nombreColor
                });
            }
            
            return row;
        }

        // Función para agregar una nueva fila vacía
        function agregarFilaDetalle() {
            const filas = bodyDetallesOrden.querySelectorAll('.fila-detalle');
            const nuevoIndex = filas.length;
            
            // Si hay mensaje de "no hay datos", limpiarlo
            const mensajeVacio = bodyDetallesOrden.querySelector('td[colspan]');
            if (mensajeVacio) {
                bodyDetallesOrden.innerHTML = '';
            }
            
            const nuevaFila = crearFilaDetalle(nuevoIndex, '', '', '', '', '', '', null, true);
            bodyDetallesOrden.appendChild(nuevaFila);
            
            // Adjuntar listener al nuevo input de pasadas
            const inputPasadas = nuevaFila.querySelector('input[name^="pasadas"]');
            if (inputPasadas) {
                inputPasadas.addEventListener('input', () => {
                    sincronizarTotalPasadasConDetalle();
                });
            }
            
            // Enfocar el primer input de la nueva fila
            const primerInput = nuevaFila.querySelector('input');
            if (primerInput) {
                primerInput.focus();
            }
            
            sincronizarTotalPasadasConDetalle();
        }

        // Función global para eliminar una fila
        window.eliminarFilaDetalle = function(boton) {
            const fila = boton.closest('tr');
            if (fila) {
                fila.remove();
                sincronizarTotalPasadasConDetalle();
                
                // Si no quedan filas, mostrar mensaje
                const filasRestantes = bodyDetallesOrden.querySelectorAll('.fila-detalle');
                if (filasRestantes.length === 0) {
                    bodyDetallesOrden.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">
                                No hay detalles. Usa el botón "Agregar Fila" para añadir.
                            </td>
                        </tr>
                    `;
                }
            }
        };

        // Event listener para el botón de agregar fila
        const btnAgregarFilaDetalle = document.getElementById('btnAgregarFilaDetalle');
        if (btnAgregarFilaDetalle) {
            btnAgregarFilaDetalle.addEventListener('click', agregarFilaDetalle);
        }

        function resetDetallePasadas() {
            sumaPasadasDetalle = 0;
            if (totalPasadasDibujo) {
                totalPasadasDibujo.value = '';
            }
        }

        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Siempre prevenir el submit normal

            if (omitirConfirmacionPasadas) {
                omitirConfirmacionPasadas = false;
            } else {
                const sumaDetalle = calcularSumaPasadasDetalle();
                const totalInput = parseInt(totalPasadasDibujo?.value ?? '0', 10);
                const coincideTotal = Number.isFinite(totalInput) && totalInput === sumaDetalle;

                if (sumaDetalle > 0 && !coincideTotal) {
                    mostrarModalPasadas();
                    return;
                }
            }

            // Mostrar loading
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Preparar datos del formulario
            const formData = new FormData(form);

            // Agregar pasadas del detalle (ya están en el FormData con name="pasadas[...]")
            // El backend espera un array, así que las pasadas ya se envían correctamente
            // Solo necesitamos asegurarnos de que se envíen como array
            const inputsPasadas = obtenerInputsPasadasDetalle();
            inputsPasadas.forEach(input => {
                if (input.value) {
                    // Los inputs ya tienen name="pasadas[key]", así que FormData los maneja automáticamente
                    // Solo verificamos que tengan valor
                }
            });

            // Enviar por AJAX
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado exitosamente!',
                        text: data.message || 'Los datos se han guardado correctamente',
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        // Limpiar formulario y ocultarlo
                        form.reset();
                        resetNumberSelectors();
                        codificacionInputs.forEach(input => input.value = '');
                        codificacionHidden.value = '';
                        codificacionFetchAttempted = false;
                        updateCodificacionNoDataMessage();
                        formContainer.classList.add('hidden');
                        document.querySelectorAll('.checkbox-produccion').forEach(cb => cb.checked = false);
                        document.getElementById('bodyDetallesOrden').innerHTML = `
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Selecciona una producción para ver los detalles
                                </td>
                            </tr>
                        `;
                        resetDetallePasadas();
                        actualizarResumenCatCodificados(null);
                        prefillFormularioDesdeCatCodificados(null);
                    });
                } else {
                    throw new Error(data.message || 'Error al guardar los datos');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: error.message || 'Ocurrió un error al guardar los datos. Por favor intenta nuevamente.',
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Aceptar'
                });
            });
        });

        modalPasadasCancelar?.addEventListener('click', () => {
            ocultarModalPasadas();
        });

        modalPasadasAceptar?.addEventListener('click', () => {
            ocultarModalPasadas();
            omitirConfirmacionPasadas = true;
            // Disparar el evento submit que ahora maneja AJAX
            form.dispatchEvent(new Event('submit'));
        });

        // Lógica de selectores numéricos (tomada del formulario original)
        function initNumberSelectors() {
            document.querySelectorAll('[data-number-selector]').forEach(selector => {
                if (selector.dataset.selectorInitialized === 'true') {
                    return;
                }

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

                        // Si hay un valor sugerido, hacer scroll a ese elemento
                        const suggestedValue = selector.dataset.suggested;
                        if (suggestedValue && hiddenInput.value === '') {
                            setTimeout(() => {
                                const suggestedOption = track.querySelector(`[data-value="${suggestedValue}"]`);
                                if (suggestedOption) {
                                    suggestedOption.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                                    // Resaltar visualmente el valor sugerido
                                    suggestedOption.classList.add('ring-2', 'ring-yellow-400');
                                    setTimeout(() => {
                                        suggestedOption.classList.remove('ring-2', 'ring-yellow-400');
                                    }, 1500);
                                }
                            }, 100);
                        }
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

                selector.dataset.selectorInitialized = 'true';
                numberSelectors.push({ optionsWrapper, reset: resetSelector, setValue: setNumberSelectorValue, input: hiddenInput });
            });

            if (!numberSelectorDocumentListenerAttached) {
                document.addEventListener('click', (event) => {
                    if (!event.target.closest('[data-number-selector]')) {
                        closeAllNumberSelectors();
                    }
                });
                numberSelectorDocumentListenerAttached = true;
            }
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

        initNumberSelectors();

        // Función para calcular Longitud Lucha Total = Trama Ancho Peine + Desperdicio Trama
        function calcularLongitudLuchaTot() {
            const tramaAnchoPeine = parseFloat(inputTramaAnchoPeine?.value) || 0;
            const desperdicioTrama = parseFloat(inputDesperdicioTrama?.value) || 0;
            const total = tramaAnchoPeine + desperdicioTrama;
            
            if (inputLongitudLuchaTot) {
                inputLongitudLuchaTot.value = total > 0 ? total.toFixed(2) : '';
            }
        }

        // Listeners para recalcular cuando cambien los valores
        if (inputTramaAnchoPeine) {
            inputTramaAnchoPeine.addEventListener('input', calcularLongitudLuchaTot);
        }
        if (inputDesperdicioTrama) {
            inputDesperdicioTrama.addEventListener('input', calcularLongitudLuchaTot);
        }
    });
</script>
@endpush
