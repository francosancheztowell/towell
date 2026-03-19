<div id="formContainer" class="hidden mt-8 border-t pt-6 scroll-mt-4 transition-opacity duration-300">
    <div class="mb-4">
        <h3 class="text-xl font-bold text-gray-800">Datos del Desarrollador</h3>
        <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-700">
            <span>Telar: <strong id="formTelarId" class="text-blue-600">-</strong></span>
            <span>No. Orden: <strong id="formNoProduccion" class="text-blue-600">-</strong></span>
            <span>Modelo: <strong id="formNombreProducto">-</strong></span>
            <span class="inline-flex items-center gap-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-full">
                    <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                    <span id="formJulioRizoInfo">No se ha seleccionado Julio Rizo</span>
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                    <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                    <span id="formJulioPieInfo">No se ha seleccionado Julio Pie</span>
                </span>
            </span>
        </div>
    </div>
    <form id="formDesarrollador" method="POST" action="{{ route('desarrolladores.store') }}">
        @csrf
        <input type="hidden" name="NoTelarId" id="inputTelarId" value="">
        <input type="hidden" name="NoProduccion" id="inputNoProduccion" value="">
        <input type="hidden" name="accion" id="inputAccion" value="finalizar">
        <input type="hidden" name="registroId" id="inputRegistroId" value="">
        <input type="hidden" name="CambioTelarActivo" id="inputCambioTelarActivo" value="false">
        <input type="hidden" name="TelarDestino" id="inputTelarDestino" value="">

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- FILA 1: Julios y Pasadas -->
            <div>
                <label for="NumeroJulioRizo" class="block text-xs font-medium text-gray-700 mb-1">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        Julio Rizo
                    </span>
                </label>
                <select id="NumeroJulioRizo" name="NumeroJulioRizo" required class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                    <option value="" disabled selected>Selecciona un Julio</option>
                    @foreach ($juliosRizo ?? [] as $julio)
                        @if($julio)
                            @php
                                $noJulio = data_get($julio, 'NoJulio') ?? '';
                                $invSize = data_get($julio, 'InventSizeId') ?? '';
                                $cfgId = data_get($julio, 'ConfigId') ?? '';
                            @endphp
                            <option value="{{ $noJulio }}" data-inventsizeid="{{ $invSize }}" data-configid="{{ $cfgId }}">{{ $noJulio }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div>
                <label for="NumeroJulioPie" class="block text-xs font-medium text-gray-700 mb-1">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        Julio Pie
                    </span>
                </label>
                <select id="NumeroJulioPie" name="NumeroJulioPie" required class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm bg-white">
                    <option value="" disabled selected>Selecciona un Julio</option>
                    @foreach ($juliosPie ?? [] as $julio)
                        @if($julio)
                            @php
                                $noJulio = data_get($julio, 'NoJulio') ?? '';
                                $invSize = data_get($julio, 'InventSizeId') ?? '';
                                $cfgId = data_get($julio, 'ConfigId') ?? '';
                            @endphp
                            <option value="{{ $noJulio }}" data-inventsizeid="{{ $invSize }}" data-configid="{{ $cfgId }}">{{ $noJulio }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div>
                <label for="TotalPasadasDibujo" class="block text-xs font-medium text-gray-700 mb-1">Total Pasadas <span class="text-red-500">*</span></label>
                <input type="number" id="TotalPasadasDibujo" name="TotalPasadasDibujo" min="1" step="1" required class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0">
            </div>

            <!-- FILA 2: Eficiencias y Horas -->
            <div>
                <label for="EficienciaInicio" class="block text-xs font-medium text-gray-700 mb-1">Eficiencia de Inicio <span class="text-red-500">*</span></label>
                <div class="relative" data-number-selector data-min="0" data-max="100" data-step="1" data-suggested="80">
                    <input type="number" id="EficienciaInicio" name="EficienciaInicio" min="0" step="1" required class="hidden">
                    <button type="button" class="number-selector-btn w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm flex items-center justify-between bg-white">
                        <span class="number-selector-value text-gray-400 font-semibold text-sm">Selecciona</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="number-selector-options hidden absolute left-0 right-0 mt-2 z-20">
                        <div class="number-selector-track flex gap-2 px-2 py-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-x-auto"></div>
                    </div>
                </div>
            </div>

            <div>
                <label for="EficienciaFinal" class="block text-xs font-medium text-gray-700 mb-1">Eficiencia Final <span class="text-red-500">*</span></label>
                <div class="relative" data-number-selector data-min="0" data-max="100" data-step="1" data-suggested="80">
                    <input type="number" id="EficienciaFinal" name="EficienciaFinal" min="0" step="1" required class="hidden">
                    <button type="button" class="number-selector-btn w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm flex items-center justify-between bg-white">
                        <span class="number-selector-value text-gray-400 font-semibold text-sm">Selecciona</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="number-selector-options hidden absolute left-0 right-0 mt-2 z-20">
                        <div class="number-selector-track flex gap-2 px-2 py-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-x-auto"></div>
                    </div>
                </div>
            </div>

            <div>
                <label for="Desarrollador" class="block text-xs font-medium text-gray-700 mb-1">Desarrollador <span class="text-red-500">*</span></label>
                <select id="Desarrollador" name="Desarrollador" required class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
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
        </div>

        <!-- FILA 3: separate, 3 columnas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div>
                <label for="HoraInicio" class="block text-xs font-medium text-gray-700 mb-1">Hora Inicio <span class="text-red-500">*</span></label>
                <input type="time" id="HoraInicio" name="HoraInicio" required class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>

            <div>
                <label for="HoraFinal" class="block text-xs font-medium text-gray-700 mb-1">Hora Final <span class="text-red-500">*</span></label>
                <input type="time" id="HoraFinal" name="HoraFinal" required class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>

            <div>
                <label for="DesperdicioTrama" class="block text-xs font-medium text-gray-700 mb-1">Desp. Trama</label>
                <input type="number" id="DesperdicioTrama" name="DesperdicioTrama" step="0.01" min="0" value="11" class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="0.00">
            </div>
        </div>

        <!-- Codificación Modelo - Sección separada con auto-avance -->
        <div class="mt-6 pt-6 border-t">
            <label class="block text-sm font-medium text-gray-700 mb-3">Codificación Modelo</label>
            <div class="overflow-x-auto pb-2">
                <div class="flex justify-start items-center gap-2 min-w-max px-2">
                    @for ($i = 0; $i < 20; $i++)
                        <input type="text" class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase" maxlength="1" data-index="{{ $i }}" {{ $i < 16 ? 'required' : '' }}>
                    @endfor
                    <span id="codificacionSuffix" class="text-lg font-bold text-gray-600">.JC5</span>
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
            <button type="button" id="btnCancelarFormulario" class="w-full px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium">Cancelar</button>
            <button type="submit" class="px-6 py-2 w-full bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">Guardar</button>
        </div>
    </form>
</div>
