<div class="flex w-full flex-col px-4 py-4 md:px-6 lg:px-6">
    <div class="bg-white flex flex-col rounded-md max-w-full p-6">

        {{-- Selector de telar + tabla de producciones --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="telarOperador" class="block text-sm font-medium mb-2">Seleccionar Telar</label>
                <select wire:model.live="telarId" id="telarOperador"
                        class="w-full md:w-60 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selecciona un Telar</option>
                    @foreach ($this->telares as $telar)
                        <option value="{{ $telar->NoTelarId }}">{{ $telar->NoTelarId }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                {{-- Banner de la orden en curso --}}
                @if ($this->ordenEnProceso)
                    @php($enProceso = $this->ordenEnProceso)
                    <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span wire:loading wire:target="telarId,telarDestino">
                                    <svg class="animate-spin h-4 w-4 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="flex h-3 w-3">
                                        <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-amber-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                    </span>
                                    <span class="text-sm font-semibold text-amber-800">En Proceso:</span>
                                    <span class="text-sm font-bold text-amber-900">{{ $enProceso['noProduccion'] }}</span>
                                    <span class="text-xs text-amber-500">|</span>
                                    <span class="text-sm text-amber-700">{{ $enProceso['fecha'] }}</span>
                                    <span class="text-xs text-amber-500">|</span>
                                    <span class="text-sm text-amber-700">{{ $enProceso['nombre'] }}</span>
                                    <span class="text-xs text-amber-500">|</span>
                                    <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 text-xs font-bold rounded-full border border-green-200">{{ $enProceso['telar'] }}</span>
                                </span>
                            </div>

                            {{-- El select se deshabilita solo: ya no hace falta el boton invisible que
                                 antes tapaba el control para poder mostrar una alerta. --}}
                            <select wire:model.live="accion"
                                    @disabled(! $this->filaSeleccionada)
                                    title="{{ $this->filaSeleccionada ? 'Accion al guardar' : 'Selecciona primero un registro' }}"
                                    class="px-2 py-1 text-xs rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-400
                                           {{ $this->filaSeleccionada ? 'bg-white text-gray-700 cursor-pointer' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                                <option value="finalizar">Finalizar</option>
                                <option value="reprogramar_siguiente">Reprogramar siguiente</option>
                                <option value="reprogramar_final">Reprogramar final</option>
                            </select>
                        </div>
                    </div>
                @endif

                @if ($telarId !== '')
                    <div class="rounded-lg border border-gray-200 overflow-x-auto" wire:loading.class="opacity-50" wire:target="telarId">
                        <table class="w-full divide-y divide-red-200">
                            <thead class="bg-blue-500">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Orden</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Fecha Cambio</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Clave</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Modelo</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Telar Destino</th>
                                    <th scope="col" class="px-3 py-2 text-center text-sm font-medium text-white">Seleccionar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($this->producciones as $p)
                                    @php($id = (int) ($p->Id ?? 0))
                                    @php($seleccionada = $produccionSeleccionada === $id)
                                    <tr wire:key="prod-{{ $id }}" class="hover:bg-gray-100 transition-colors">
                                        <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 bg-white">{{ $p->NoProduccion }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-blue-50">
                                            {{ $p->FechaInicio ? \Carbon\Carbon::parse($p->FechaInicio)->format('d/m/Y') : 'N/A' }}
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600 bg-white">{{ $p->TamanoClave ?? 'N/A' }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-600 break-words bg-blue-50">{{ $p->NombreProducto ?? 'N/A' }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap bg-white">
                                            {{-- Antes cada fila pintaba el catalogo completo de telares: con N filas
                                                 y M telares eran N*M nodos. Ahora solo lo pinta la fila elegida. --}}
                                            @if ($seleccionada)
                                                <select wire:model.live="telarDestino"
                                                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-green-50 cursor-pointer">
                                                    <option value="{{ ($p->SalonTejidoId ?? '') . '|' . $telarId }}">{{ $telarId }}</option>
                                                    @foreach ($this->telaresDestino as $t)
                                                        @php($partes = explode('|', $t['value'] ?? '', 2))
                                                        @if (trim($partes[1] ?? '') !== (string) $telarId)
                                                            <option value="{{ $t['value'] }}">{{ $t['label'] }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-sm text-gray-400">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-center bg-blue-50">
                                            <input type="checkbox" wire:click="seleccionar({{ $id }})" @checked($seleccionada)
                                                   class="checkbox-produccion w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($this->producciones->isEmpty())
                        <div class="bg-white rounded-b-lg border-x border-b border-gray-200 py-10 text-center">
                            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">No se encontraron producciones para este telar</p>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Formulario --}}
        @if ($this->filaSeleccionada)
            @php($fila = $this->filaSeleccionada)
            <div class="mt-8 border-t pt-6">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Datos del Desarrollador</h3>
                    <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-700">
                        <span>Telar: <strong class="text-blue-600">{{ $telarId }}</strong></span>
                        <span>No. Orden: <strong class="text-blue-600">{{ $fila['NoProduccion'] ?: '-' }}</strong></span>
                        <span>Modelo: <strong>{{ $fila['NombreProducto'] ?: '-' }}</strong></span>
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-full">
                                <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                                {{ $form['NumeroJulioRizo'] ? 'Julio Rizo: '.$form['NumeroJulioRizo'] : 'No se ha seleccionado Julio Rizo' }}
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                                {{ $form['NumeroJulioPie'] ? 'Julio Pie: '.$form['NumeroJulioPie'] : 'No se ha seleccionado Julio Pie' }}
                            </span>
                        </span>
                        @if ($this->hayCambioTelar)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-800 text-xs font-semibold rounded-full">
                                Cambio de telar activo
                            </span>
                        @endif
                    </div>
                </div>

                <form wire:submit="guardar">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="NumeroJulioRizo" class="block text-xs font-medium text-gray-700 mb-1">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                                    Julio Rizo
                                </span>
                            </label>
                            <select wire:model.live="form.NumeroJulioRizo" id="NumeroJulioRizo"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                <option value="">Selecciona un Julio</option>
                                @foreach ($this->juliosRizo as $julio)
                                    <option value="{{ data_get($julio, 'NoJulio') }}">{{ data_get($julio, 'NoJulio') }}</option>
                                @endforeach
                            </select>
                            @error('form.NumeroJulioRizo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="NumeroJulioPie" class="block text-xs font-medium text-gray-700 mb-1">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                                    Julio Pie
                                </span>
                            </label>
                            <select wire:model.live="form.NumeroJulioPie" id="NumeroJulioPie"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm bg-white">
                                <option value="">Selecciona un Julio</option>
                                @foreach ($this->juliosPie as $julio)
                                    <option value="{{ data_get($julio, 'NoJulio') }}">{{ data_get($julio, 'NoJulio') }}</option>
                                @endforeach
                            </select>
                            @error('form.NumeroJulioPie') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Total Pasadas <span class="text-red-500">*</span></label>
                            <input type="number" value="{{ $this->totalPasadas }}" readonly
                                   class="w-full px-2 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm">
                            @error('form.TotalPasadasDibujo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Eficiencias: el selector se arma al abrirlo, no 101 botones por adelantado --}}
                        @foreach (['EficienciaInicio' => 'Eficiencia de Inicio', 'EficienciaFinal' => 'Eficiencia Final'] as $campo => $etiqueta)
                            <div wire:key="efi-{{ $campo }}" x-data="{ abierto: false }" class="relative">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ $etiqueta }} <span class="text-red-500">*</span></label>
                                <button type="button" @click="abierto = !abierto"
                                        class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm flex items-center justify-between bg-white">
                                    <span class="{{ $form[$campo] === null || $form[$campo] === '' ? 'text-gray-400' : 'text-gray-800' }} font-semibold text-sm">
                                        {{ $form[$campo] === null || $form[$campo] === '' ? 'Selecciona' : $form[$campo].'%' }}
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="abierto" x-cloak @click.outside="abierto = false" class="absolute left-0 right-0 mt-2 z-20">
                                    <div class="flex gap-2 px-2 py-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-x-auto">
                                        @for ($v = 0; $v <= 100; $v++)
                                            <button type="button" wire:click="$set('form.{{ $campo }}', {{ $v }})" @click="abierto = false"
                                                    class="shrink-0 w-10 h-9 rounded-md border text-sm font-semibold
                                                           {{ (string) $form[$campo] === (string) $v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-blue-50' }}
                                                           {{ $v === 80 && (string) $form[$campo] !== (string) $v ? 'ring-2 ring-blue-300' : '' }}">
                                                {{ $v }}
                                            </button>
                                        @endfor
                                    </div>
                                </div>
                                @error('form.'.$campo) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach

                        <div>
                            <label for="Desarrollador" class="block text-xs font-medium text-gray-700 mb-1">Desarrollador <span class="text-red-500">*</span></label>
                            <select wire:model="form.Desarrollador" id="Desarrollador"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                <option value="">Selecciona un Desarrollador</option>
                                @foreach ($this->desarrolladores as $d)
                                    <option value="{{ data_get($d, 'nombre') }}">{{ data_get($d, 'nombre') }}</option>
                                @endforeach
                            </select>
                            @error('form.Desarrollador') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label for="HoraInicio" class="block text-xs font-medium text-gray-700 mb-1">Hora Inicio <span class="text-red-500">*</span></label>
                            <input type="time" wire:model="form.HoraInicio" id="HoraInicio"
                                   class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            @error('form.HoraInicio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="HoraFinal" class="block text-xs font-medium text-gray-700 mb-1">Hora Final <span class="text-red-500">*</span></label>
                            <input type="time" wire:model="form.HoraFinal" id="HoraFinal"
                                   class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            @error('form.HoraFinal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="DesperdicioTrama" class="block text-xs font-medium text-gray-700 mb-1">Desp. Trama</label>
                            <input type="number" wire:model="form.DesperdicioTrama" id="DesperdicioTrama" step="0.01" min="0"
                                   class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            @error('form.DesperdicioTrama') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Codificacion: el auto-avance entre casillas es puro manejo de foco,
                         se queda en Alpine para no pagar un viaje al servidor por tecla. --}}
                    <div class="mt-6 pt-6 border-t" x-data="codificacionBoxes()">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Codificación Modelo</label>
                        <div class="overflow-x-auto pb-2">
                            <div class="flex justify-start items-center gap-2 min-w-max px-2">
                                @for ($i = 0; $i < 20; $i++)
                                    <input type="text" maxlength="1" wire:model.blur="codificacion.{{ $i }}"
                                           x-on:input="avanzar($event)" x-on:keydown.backspace="retroceder($event)" x-on:paste="pegar($event)"
                                           data-indice="{{ $i }}"
                                           class="codificacion-char w-10 h-10 text-center text-lg font-bold border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase">
                                @endfor
                                <span class="text-lg font-bold text-gray-600">.JC5</span>
                            </div>
                        </div>
                        @error('form.CodificacionModelo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Detalles --}}
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Detalles de la Orden</h3>
                            <button type="button" wire:click="agregarFila"
                                    class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
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
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse ($detalles as $i => $detalle)
                                        @php($esTrama = str_contains((string) ($detalle['slot'] ?? ''), 'Trama'))
                                        <tr wire:key="det-{{ $i }}" class="hover:bg-gray-50 transition-colors">
                                            @foreach (['Calibre', 'Hilo', 'Fibra', 'CodColor', 'NombreColor'] as $campo)
                                                <td class="px-4 py-2">
                                                    <input type="text" wire:model="detalles.{{ $i }}.{{ $campo }}"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                                </td>
                                            @endforeach
                                            <td class="px-4 py-2">
                                                <input type="number" min="0" step="1" wire:model.live="detalles.{{ $i }}.Pasadas"
                                                       class="w-20 px-2 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if ($esTrama)
                                                    <span class="text-xs text-gray-400">Trama</span>
                                                @else
                                                    <button type="button" wire:click="eliminarFila({{ $i }})" title="Eliminar combinación"
                                                            class="p-1.5 text-red-600 hover:bg-red-100 rounded-md transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">
                                                Esta orden no trae detalles. Usa "Agregar Fila" para capturarlos.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t">
                        <button type="button" wire:click="cancelar"
                                class="w-full px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium">
                            Cancelar
                        </button>
                        {{-- wire:loading deshabilita el boton mientras viaja: el doble envio ya no es posible --}}
                        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
                                class="px-6 py-2 w-full bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="guardar">Guardar</span>
                            <span wire:loading wire:target="guardar">Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
        function codificacionBoxes() {
            const casillas = (el) => Array.from(el.closest('[x-data]').querySelectorAll('.codificacion-char'));
            return {
                avanzar(e) {
                    e.target.value = e.target.value.toUpperCase();
                    if (!e.target.value) return;
                    const lista = casillas(e.target);
                    const i = lista.indexOf(e.target);
                    if (i > -1 && i < lista.length - 1) lista[i + 1].focus();
                },
                retroceder(e) {
                    if (e.target.value) return;
                    const lista = casillas(e.target);
                    const i = lista.indexOf(e.target);
                    if (i > 0) { e.preventDefault(); lista[i - 1].focus(); }
                },
                pegar(e) {
                    e.preventDefault();
                    const texto = (e.clipboardData.getData('text') || '').toUpperCase().replace(/\s+/g, '');
                    const lista = casillas(e.target);
                    let i = lista.indexOf(e.target);
                    for (const ch of texto) {
                        if (i >= lista.length) break;
                        lista[i].value = ch;
                        lista[i].dispatchEvent(new Event('input', { bubbles: true }));
                        lista[i].dispatchEvent(new Event('blur', { bubbles: true }));
                        i++;
                    }
                    if (i < lista.length) lista[i].focus();
                },
            };
        }
    </script>
</div>
