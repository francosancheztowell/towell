@php
    $esMuestras = $modo === 'muestras';
    // Clases repetidas seis veces en el archivo, una por campo.
    $claseCampo = 'w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white';
@endphp

<div class="flex w-full flex-col px-4 py-4 md:px-6 lg:px-6">
    {{-- Barra de progreso SIN wire:target: se enciende con cualquier peticion del
         componente, asi ninguna interaccion se queda sin senal. El .delay evita que
         parpadee en las respuestas rapidas. --}}
    <div wire:loading.delay.flex class="fixed inset-x-0 top-0 z-50 hidden h-1 overflow-hidden bg-blue-100" role="status" aria-live="polite">
        <span class="sr-only">Procesando…</span>
        <span class="h-full w-1/3 animate-[barra_1.1s_ease-in-out_infinite] bg-blue-600"></span>
    </div>

    <div class="bg-white flex flex-col rounded-md max-w-full p-6 {{ $esMuestras ? 'border-t-4 border-purple-600' : '' }}">

        @if ($esMuestras)
            {{-- Las dos capturas eran identicas salvo el titulo del navbar, y esta borra
                 la muestra al guardar. La diferencia tiene que verse dentro de la tarjeta. --}}
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-purple-50 border border-purple-200 px-4 py-3">
                <span class="rounded bg-purple-700 px-2 py-1 text-sm font-bold tracking-wide text-white">MUESTRA</span>
                <span class="text-base text-purple-900">Al guardar, la muestra se elimina del programa.</span>
            </div>
        @endif

        {{-- Selector de telar + tabla de producciones --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="telarOperador" class="block text-sm font-medium mb-2">Seleccionar Telar</label>
                <div class="flex items-center gap-2">
                    <select wire:model.live="telarId" id="telarOperador"
                            class="w-full md:w-60 px-3 py-2.5 border border-gray-300 rounded-md text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecciona un Telar</option>
                        @foreach ($this->telares as $telar)
                            <option value="{{ $telar->NoTelarId }}">{{ $telar->NoTelarId }}</option>
                        @endforeach
                    </select>
                    <span wire:loading wire:target="telarId" class="shrink-0">
                        <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="md:col-span-2">
                {{-- Banner de la orden en curso --}}
                @if ($this->ordenEnProceso)
                    @php($enProceso = $this->ordenEnProceso)
                    <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span wire:loading wire:target="telarId,telarDestino">
                                    <svg class="animate-spin h-4 w-4 text-amber-700" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </span>
                                <span class="flex items-center gap-2 flex-wrap">
                                    {{-- relative: sin el, el ping absolute se escapaba del contenedor --}}
                                    <span class="relative flex h-3 w-3">
                                        <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-amber-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                    </span>
                                    <span class="text-sm font-semibold text-amber-900">
                                        {{-- El banner mira al telar destino cuando hay cambio: hay que decirlo,
                                             o se lee como que la orden ya se movio. --}}
                                        {{ $this->hayCambioTelar ? 'En proceso en el telar destino:' : 'En Proceso:' }}
                                    </span>
                                    <span class="text-sm font-bold text-amber-900">{{ $enProceso['noProduccion'] }}</span>
                                    <span class="text-xs text-amber-700" aria-hidden="true">|</span>
                                    <span class="text-sm text-amber-900">{{ $enProceso['fecha'] }}</span>
                                    <span class="text-xs text-amber-700" aria-hidden="true">|</span>
                                    <span class="text-sm text-amber-900">{{ $enProceso['nombre'] }}</span>
                                    <span class="text-xs text-amber-700" aria-hidden="true">|</span>
                                    <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-800 text-xs font-bold rounded-full border border-green-300">{{ $enProceso['telar'] }}</span>
                                </span>
                            </div>

                            <div>
                                <label for="accionGuardar" class="block text-xs font-medium text-gray-700 mb-1">Al guardar</label>
                                <select wire:model.live="accion" id="accionGuardar"
                                        wire:loading.attr="disabled" wire:target="accion,guardar,seleccionar"
                                        @disabled(! $this->filaSeleccionada)
                                        class="px-3 py-2 text-base rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-400
                                               {{ $this->filaSeleccionada ? 'bg-white text-gray-800 cursor-pointer' : 'bg-gray-100 text-gray-600 cursor-not-allowed' }}">
                                    <option value="finalizar">Finalizar la orden</option>
                                    <option value="reprogramar_siguiente">Reprogramar al siguiente</option>
                                    <option value="reprogramar_final">Reprogramar al final</option>
                                </select>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($telarId !== '')
                    <div class="rounded-lg border border-gray-200 overflow-x-auto"
                         wire:loading.class="opacity-50" wire:target="telarId,telarDestino">
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-blue-600">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Orden</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Fecha Cambio</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Clave</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Modelo</th>
                                    <th scope="col" class="px-3 py-2 text-left text-sm font-medium text-white">Telar Destino</th>
                                    <th scope="col" class="px-3 py-2 text-center text-sm font-medium text-white">Seleccionar</th>
                                </tr>
                            </thead>
                            {{-- Cebra por renglon. Antes alternaba por celda, que es el eje que
                                 no se lee: la fila se sigue de izquierda a derecha. --}}
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($this->producciones as $p)
                                    @php($id = (int) ($p->Id ?? 0))
                                    @php($seleccionada = $produccionSeleccionada === $id)
                                    <tr wire:key="prod-{{ $id }}"
                                        class="transition-colors {{ $seleccionada ? 'bg-blue-100 ring-2 ring-inset ring-blue-500' : ($loop->even ? 'bg-gray-50 hover:bg-gray-100' : 'hover:bg-gray-100') }}">
                                        <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $p->NoProduccion }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700">
                                            {{ $p->FechaInicio ? \Carbon\Carbon::parse($p->FechaInicio)->format('d/m/Y') : 'N/A' }}
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700">{{ $p->TamanoClave ?? 'N/A' }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-700 break-words">{{ $p->NombreProducto ?? 'N/A' }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            {{-- Antes cada fila pintaba el catalogo completo de telares: con N filas
                                                 y M telares eran N*M nodos. Ahora solo lo pinta la fila elegida. --}}
                                            @if ($seleccionada)
                                                <label for="telarDestino" class="sr-only">Telar destino</label>
                                                <select wire:model.live="telarDestino" id="telarDestino"
                                                        wire:loading.attr="disabled" wire:target="telarDestino,seleccionar"
                                                        class="w-full px-3 py-2 border rounded text-base focus:ring-2 focus:ring-blue-500 cursor-pointer disabled:opacity-60
                                                               {{ $this->hayCambioTelar ? 'border-amber-500 bg-amber-50 font-semibold text-amber-900' : 'border-gray-300 bg-white' }}">
                                                    <option value="{{ ($p->SalonTejidoId ?? '') . '|' . $telarId }}">{{ $telarId }} (sin cambio)</option>
                                                    @foreach ($this->telaresDestino as $t)
                                                        @php($partes = explode('|', $t['value'] ?? '', 2))
                                                        @if (trim($partes[1] ?? '') !== (string) $telarId)
                                                            <option wire:key="dst-{{ $t['value'] }}" value="{{ $t['value'] }}">{{ $t['label'] }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                @error('telarDestino') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                            @else
                                                <span class="text-sm text-gray-600" aria-hidden="true">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-center">
                                            <label class="inline-flex min-h-11 min-w-11 items-center justify-center cursor-pointer">
                                                <span class="sr-only">Seleccionar la orden {{ $p->NoProduccion }}</span>
                                                {{-- La llave lleva el estado a proposito. Al pulsar un checkbox real
                                                     el navegador lo marca como "tocado", y desde ese momento quitarle
                                                     el atributo checked en el morph ya no lo desmarca: el tick viejo
                                                     se quedaba pegado y se veian dos filas seleccionadas. Con la
                                                     llave cambiando, Livewire reemplaza el nodo en vez de parchearlo
                                                     y el nodo nuevo nace limpio. --}}
                                                <input type="checkbox" wire:key="sel-{{ $id }}-{{ $seleccionada ? 'on' : 'off' }}"
                                                       wire:click="seleccionar({{ $id }})"
                                                       wire:loading.attr="disabled" wire:target="seleccionar"
                                                       @checked($seleccionada)
                                                       class="w-6 h-6 text-blue-600 bg-gray-100 border-gray-400 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer">
                                            </label>
                                        </td>
                                    </tr>
                                @empty
                                    {{-- Dentro del tbody: antes se pintaba una tabla vacia con encabezados
                                         y ademas un bloque de estado vacio debajo. --}}
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center">
                                            <svg class="mx-auto h-10 w-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <p class="mt-2 text-sm text-gray-700">No se encontraron producciones para este telar</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex h-full min-h-32 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center">
                        <p class="text-base text-gray-700">Selecciona un telar para ver sus órdenes.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Formulario --}}
        @if ($this->filaSeleccionada)
            @php($fila = $this->filaSeleccionada)
            <div class="mt-8 border-t pt-6 {{ $this->hayCambioTelar ? 'border-l-4 border-l-amber-500 pl-4' : '' }}">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Datos del Desarrollador</h3>
                    <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-800">
                        <span>Telar: <strong class="text-blue-700">{{ $telarId }}</strong></span>
                        <span>No. Orden: <strong class="text-blue-700">{{ $fila['NoProduccion'] ?: '-' }}</strong></span>
                        <span>Modelo: <strong>{{ $fila['NombreProducto'] ?: '-' }}</strong></span>
                        <span class="inline-flex items-center gap-1 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                                {{ $form['NumeroJulioRizo'] ? 'Julio Rizo: '.$form['NumeroJulioRizo'] : 'Sin Julio Rizo' }}
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                {{ $form['NumeroJulioPie'] ? 'Julio Pie: '.$form['NumeroJulioPie'] : 'Sin Julio Pie' }}
                            </span>
                        </span>
                    </div>
                </div>

                @if ($this->hayCambioTelar)
                    {{-- Mover una orden de telar era indistinguible de no moverla: la unica
                         senal era un chip text-xs entre otros cuatro. --}}
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border-2 border-amber-500 bg-amber-50 px-4 py-3">
                        <p class="text-base font-semibold text-amber-900">
                            Esta orden se moverá del telar {{ $telarId }} al telar {{ $this->telarDestinoNombre }}.
                        </p>
                        <button type="button" wire:click="$set('telarDestino', '{{ ($fila['SalonTejidoId'] ?? '') . '|' . $telarId }}')"
                                wire:loading.attr="disabled" wire:target="telarDestino"
                                class="min-h-11 rounded-lg border border-amber-600 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 disabled:opacity-60 disabled:cursor-not-allowed">
                            Cancelar cambio
                        </button>
                    </div>
                @endif

                <form wire:submit="guardar">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {{-- Los asteriscos siguen al servidor: Julio Rizo, eficiencias, Altura de Rizo
                             y Codificacion son required; horas y desarrollador son nullable. --}}
                        <div>
                            <label for="NumeroJulioRizo" class="block text-sm font-medium text-gray-800 mb-1">
                                Julio Rizo <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                            </label>
                            <select wire:model.live="form.NumeroJulioRizo" id="NumeroJulioRizo"
                                    wire:loading.attr="disabled" wire:target="form.NumeroJulioRizo"
                                    class="{{ $claseCampo }} disabled:opacity-60">
                                <option value="">Selecciona un Julio</option>
                                @foreach ($this->juliosRizo as $julio)
                                    <option wire:key="jr-{{ data_get($julio, 'NoJulio') }}" value="{{ data_get($julio, 'NoJulio') }}">{{ data_get($julio, 'NoJulio') }}</option>
                                @endforeach
                            </select>
                            @error('form.NumeroJulioRizo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="NumeroJulioPie" class="block text-sm font-medium text-gray-800 mb-1">Julio Pie</label>
                            <select wire:model.live="form.NumeroJulioPie" id="NumeroJulioPie"
                                    wire:loading.attr="disabled" wire:target="form.NumeroJulioPie"
                                    class="{{ $claseCampo }} disabled:opacity-60">
                                <option value="">Selecciona un Julio</option>
                                @foreach ($this->juliosPie as $julio)
                                    <option wire:key="jp-{{ data_get($julio, 'NoJulio') }}" value="{{ data_get($julio, 'NoJulio') }}">{{ data_get($julio, 'NoJulio') }}</option>
                                @endforeach
                            </select>
                            @error('form.NumeroJulioPie') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="TotalPasadas" class="block text-sm font-medium text-gray-800 mb-1">Total Pasadas</label>
                            <input type="number" id="TotalPasadas" value="{{ $this->totalPasadas }}" readonly tabindex="-1"
                                   aria-describedby="TotalPasadasAyuda"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-gray-100 text-base text-gray-800">
                            <p id="TotalPasadasAyuda" class="mt-1 text-xs text-gray-700">
                                <span wire:loading.remove wire:target="detalles">Suma de las pasadas del detalle.</span>
                                <span wire:loading wire:target="detalles" class="font-medium text-blue-700">Recalculando…</span>
                            </p>
                            @error('form.TotalPasadasDibujo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
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
                                                    wire:loading.attr="disabled" wire:target="form.{{ $campo }}"
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
                            <label for="Desarrollador" class="block text-sm font-medium text-gray-800 mb-1">Desarrollador</label>
                            <select wire:model="form.Desarrollador" id="Desarrollador" class="{{ $claseCampo }}">
                                <option value="">Selecciona un Desarrollador</option>
                                @foreach ($this->desarrolladores as $d)
                                    <option wire:key="dev-{{ data_get($d, 'idusuario') }}" value="{{ data_get($d, 'nombre') }}">{{ data_get($d, 'nombre') }}</option>
                                @endforeach
                            </select>
                            @error('form.Desarrollador') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                        <div>
                            <label for="HoraInicio" class="block text-sm font-medium text-gray-800 mb-1">Hora Inicio</label>
                            <input type="time" wire:model="form.HoraInicio" id="HoraInicio" class="{{ $claseCampo }}">
                            @error('form.HoraInicio') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="HoraFinal" class="block text-sm font-medium text-gray-800 mb-1">Hora Final</label>
                            <input type="time" wire:model="form.HoraFinal" id="HoraFinal" class="{{ $claseCampo }}">
                            @error('form.HoraFinal') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="DesperdicioTrama" class="block text-sm font-medium text-gray-800 mb-1">Desperdicio de Trama</label>
                            <input type="number" wire:model="form.DesperdicioTrama" id="DesperdicioTrama"
                                   step="0.01" min="0" inputmode="decimal" autocomplete="off" class="{{ $claseCampo }}">
                            @error('form.DesperdicioTrama') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="AlturaRizo" class="block text-sm font-medium text-gray-800 mb-1">
                                Altura de Rizo <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                            </label>
                            <input type="number" wire:model="form.AlturaRizo" id="AlturaRizo"
                                   step="0.1" min="0" max="10" inputmode="decimal" autocomplete="off"
                                   aria-describedby="AlturaRizoAyuda" class="{{ $claseCampo }}">
                            <p id="AlturaRizoAyuda" class="mt-1 text-xs text-gray-700">De 0 a 10, un decimal.</p>
                            @error('form.AlturaRizo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Codificacion: el auto-avance entre casillas es puro manejo de foco,
                         se queda en Alpine para no pagar un viaje al servidor por tecla. --}}
                    <div class="mt-6 pt-6 border-t" x-data="codificacionBoxes()">
                        <label id="codificacionEtiqueta" class="block text-sm font-medium text-gray-800 mb-1">
                            Codificación Modelo <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                        </label>
                        <p class="mb-3 text-xs text-gray-700">Entre 10 y 20 caracteres. El sufijo .JC5 se añade solo.</p>
                        <div class="overflow-x-auto pb-2">
                            <div class="flex justify-start items-center gap-2 min-w-max px-2" role="group" aria-labelledby="codificacionEtiqueta">
                                @for ($i = 0; $i < 20; $i++)
                                    <input type="text" maxlength="1" wire:key="cod-{{ $produccionSeleccionada }}-{{ $i }}"
                                           wire:model.blur="codificacion.{{ $i }}"
                                           id="codificacion-{{ $i }}" aria-label="Carácter {{ $i + 1 }} de 20"
                                           autocomplete="off" autocapitalize="characters" spellcheck="false"
                                           x-on:input="avanzar($event)" x-on:keydown.backspace="retroceder($event)" x-on:paste="pegar($event)"
                                           data-indice="{{ $i }}"
                                           class="codificacion-char w-11 h-11 text-center text-lg font-bold border-2 border-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase">
                                @endfor
                                <span class="text-lg font-bold text-gray-800">.JC5</span>
                            </div>
                        </div>
                        @error('form.CodificacionModelo') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    {{-- Detalles --}}
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Detalles de la Orden</h3>
                            @php($tope = collect($detalles)->reject(fn ($d) => str_contains((string) ($d['slot'] ?? ''), 'Trama'))->count() >= 5)
                            <button type="button" wire:click="agregarFila"
                                    wire:loading.attr="disabled" wire:target="agregarFila"
                                    @disabled($tope)
                                    title="{{ $tope ? 'Solo se pueden capturar 5 combinaciones' : '' }}"
                                    class="min-h-11 px-4 py-2 text-base font-medium rounded-lg transition-colors flex items-center gap-2
                                           {{ $tope ? 'bg-gray-200 text-gray-600 cursor-not-allowed' : 'bg-green-700 text-white hover:bg-green-800' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Agregar Fila
                            </button>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-800 uppercase tracking-wider">Calibre</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-800 uppercase tracking-wider">Hilo <span class="normal-case font-normal text-gray-600">(del catálogo)</span></th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-800 uppercase tracking-wider">Fibra</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-800 uppercase tracking-wider">Cod Color</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-800 uppercase tracking-wider">Nombre Color</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-800 uppercase tracking-wider">Pasadas</th>
                                        <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-800 uppercase tracking-wider w-20">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse ($detalles as $i => $detalle)
                                        @php($esTrama = str_contains((string) ($detalle['slot'] ?? ''), 'Trama'))
                                        <tr wire:key="det-{{ $produccionSeleccionada }}-{{ $i }}" class="hover:bg-gray-50 transition-colors">
                                            @php($fueraDeCatalogo = ! empty($detalle['noVigente']))
                                            {{-- Calibre e Hilo salen del mismo renglon del catalogo. Un solo
                                                 select llena los dos, que es lo que impide que se desparejen:
                                                 capturados por separado, el codigo 10.1 llego a convivir con
                                                 diez divisores distintos y la formula de L.Mat calculaba mal. --}}
                                            <td class="px-4 py-2 min-w-64">
                                                <select wire:key="det-{{ $i }}-CalibreId"
                                                        wire:change="elegirCalibre({{ $i }}, $event.target.value)"
                                                        wire:loading.attr="disabled" wire:target="elegirCalibre"
                                                        aria-label="Calibre, fila {{ $i + 1 }}"
                                                        class="w-full px-2 py-2 border rounded-md text-base focus:ring-2 disabled:opacity-60
                                                               {{ $fueraDeCatalogo ? 'border-red-500 bg-red-50 text-red-900 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500' }}">
                                                    @if ($fueraDeCatalogo)
                                                        <option value="" selected disabled>{{ $detalle['Calibre'] }} — fuera de catálogo</option>
                                                    @elseif (empty($detalle['CalibreId']))
                                                        <option value="" selected disabled>Selecciona un hilo</option>
                                                    @endif
                                                    @foreach ($this->calibres as $hilo)
                                                        <option value="{{ $hilo->Id }}" @selected((int) ($detalle['CalibreId'] ?? 0) === (int) $hilo->Id)>{{ $hilo->etiqueta }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($fueraDeCatalogo)
                                                    <p class="mt-1 text-xs font-medium text-red-700">
                                                        El calibre <strong>{{ $detalle['Calibre'] }}</strong> ya no está vigente. Elige uno de la lista para poder guardar.
                                                    </p>
                                                @endif
                                                @error('detalles.'.$i.'.CalibreId') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                            </td>
                                            {{-- Hilo es el divisor del calibre elegido: mientras el catalogo tenga
                                                 uno solo no hay nada que decidir y se muestra, no se captura. Con dos
                                                 o mas registrados para ese calibre deja de haber respuesta unica y
                                                 se vuelve select. --}}
                                            @php($hilos = $this->hilosDelCalibre((string) ($detalle['Calibre'] ?? '')))
                                            <td class="px-4 py-2">
                                                @if (count($hilos) > 1)
                                                    <select wire:key="det-{{ $i }}-Hilo"
                                                            wire:change="elegirHilo({{ $i }}, $event.target.value)"
                                                            wire:loading.attr="disabled" wire:target="elegirHilo"
                                                            aria-label="Hilo, fila {{ $i + 1 }}"
                                                            class="w-40 px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base disabled:opacity-60">
                                                        @foreach ($hilos as $opcion)
                                                            <option value="{{ $opcion['Divisor'] }}" @selected((string) $detalle['Hilo'] === $opcion['Divisor'])>{{ $opcion['etiqueta'] }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" readonly tabindex="-1"
                                                           value="{{ $detalle['Hilo'] }}"
                                                           aria-label="Hilo, fila {{ $i + 1 }}"
                                                           class="w-24 px-2 py-2 border border-gray-300 rounded-md bg-gray-100 text-base text-gray-800">
                                                @endif
                                                @error('detalles.'.$i.'.Hilo') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                            </td>
                                            {{-- Fibra y color salen de AX colgando del articulo del hilo, igual
                                                 que en L.Mat: fibra es el ConfigId, el color es InventColor. Eran
                                                 texto libre y produccion acumulo 272 variantes de fibra (TERMO,
                                                 TERMO., TERMOFIJADO) y ni un solo codigo de color capturado. --}}
                                            @php($opciones = $this->opcionesFila($detalle))
                                            <td class="px-4 py-2">
                                                <select wire:key="det-{{ $i }}-Fibra" wire:model="detalles.{{ $i }}.Fibra"
                                                        aria-label="Fibra, fila {{ $i + 1 }}"
                                                        @disabled(empty($detalle['CalibreId']))
                                                        class="w-full px-2 py-2 border rounded-md text-base focus:ring-2 disabled:bg-gray-100
                                                               {{ $opciones['fibraFuera'] ? 'border-amber-500 bg-amber-50 text-amber-900 focus:ring-amber-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500' }}">
                                                    <option value="">{{ empty($detalle['CalibreId']) ? 'Elige primero el hilo' : 'Sin fibra' }}</option>
                                                    @foreach ($opciones['fibras'] as $fibra)
                                                        <option value="{{ $fibra }}">{{ $fibra }}{{ $opciones['fibraFuera'] && $loop->first ? ' — fuera de AX' : '' }}</option>
                                                    @endforeach
                                                </select>
                                                @error('detalles.'.$i.'.Fibra') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-4 py-2">
                                                <select wire:key="det-{{ $i }}-CodColor"
                                                        wire:change="elegirColor({{ $i }}, $event.target.value)"
                                                        wire:loading.attr="disabled" wire:target="elegirColor"
                                                        aria-label="Cod Color, fila {{ $i + 1 }}"
                                                        @disabled(empty($detalle['CalibreId']))
                                                        class="w-full px-2 py-2 border rounded-md text-base focus:ring-2 disabled:bg-gray-100 disabled:opacity-60
                                                               {{ $opciones['colorFuera'] ? 'border-amber-500 bg-amber-50 text-amber-900 focus:ring-amber-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500' }}">
                                                    <option value="">{{ empty($detalle['CalibreId']) ? 'Elige primero el hilo' : 'Sin color' }}</option>
                                                    @foreach ($opciones['colores'] as $color)
                                                        <option value="{{ $color['InventColorId'] }}" @selected((string) ($detalle['CodColor'] ?? '') === (string) $color['InventColorId'])>{{ $color['InventColorId'] }}{{ $opciones['colorFuera'] && $loop->first ? ' — fuera de AX' : '' }}</option>
                                                    @endforeach
                                                </select>
                                                @error('detalles.'.$i.'.CodColor') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                            </td>
                                            {{-- El nombre viene con el codigo, del mismo renglon de AX: se muestra. --}}
                                            <td class="px-4 py-2">
                                                <input type="text" readonly tabindex="-1"
                                                       value="{{ $detalle['NombreColor'] }}"
                                                       aria-label="Nombre Color, fila {{ $i + 1 }}"
                                                       class="w-full px-2 py-2 border border-gray-300 rounded-md bg-gray-100 text-base text-gray-800">
                                                @error('detalles.'.$i.'.NombreColor') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" min="1" step="1" inputmode="numeric" autocomplete="off"
                                                       wire:model.live.debounce.400ms="detalles.{{ $i }}.Pasadas"
                                                       aria-label="Pasadas, fila {{ $i + 1 }}"
                                                       class="w-24 px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base">
                                                @error('detalles.'.$i.'.Pasadas') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if ($esTrama)
                                                    <span class="text-xs font-medium text-gray-700">Trama</span>
                                                @else
                                                    <button type="button" wire:click="eliminarFila({{ $i }})"
                                                            wire:confirm="¿Quitar esta combinación de la captura?"
                                                            wire:loading.attr="disabled" wire:target="eliminarFila"
                                                            class="inline-flex min-h-11 min-w-11 items-center justify-center text-red-700 hover:bg-red-100 rounded-md transition-colors">
                                                        <span class="sr-only">Eliminar la combinación de la fila {{ $i + 1 }}</span>
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-700 text-sm">
                                                Esta orden no trae detalles. Usa "Agregar Fila" para capturarlos.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Nada puede rechazarse en silencio: lo que no tiene campo propio en
                         pantalla se lista aqui, arriba de los botones. --}}
                    @error('guardar')
                        <div role="alert" class="mt-6 rounded-lg border-2 border-red-300 bg-red-50 px-4 py-3">
                            <p class="text-base font-semibold text-red-900">No se pudo guardar</p>
                            <p class="mt-1 text-sm text-red-800">{{ $message }}</p>
                        </div>
                    @enderror

                    {{-- Lo que falta para poder guardar. Es la misma lista que deshabilita
                         el boton, asi que nunca hay un boton apagado sin explicacion. --}}
                    @if ($this->problemas !== [])
                        <div role="status" class="mt-6 rounded-lg border-2 border-amber-300 bg-amber-50 px-4 py-3">
                            <p class="text-base font-semibold text-amber-900">Falta esto para poder guardar:</p>
                            <ul class="mt-1 list-disc pl-6 text-sm text-amber-900">
                                @foreach ($this->problemas as $problema)
                                    <li>{{ $problema }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($this->requiereConfirmacion)
                        <div class="mt-6 rounded-lg border-2 border-amber-500 bg-amber-50 px-4 py-4">
                            <p class="text-base font-semibold text-amber-900">Al guardar:</p>
                            <ul class="mt-2 list-disc pl-6 text-base text-amber-900">
                                @foreach ($this->resumenGuardado as $punto)
                                    <li>{{ $punto }}</li>
                                @endforeach
                            </ul>
                            <label class="mt-3 flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model.live="confirmado"
                                       wire:loading.attr="disabled" wire:target="confirmado,guardar"
                                       class="w-6 h-6 rounded border-amber-600 text-amber-700 focus:ring-2 focus:ring-amber-500 disabled:opacity-60">
                                <span class="text-base font-medium text-amber-900">Confirmo</span>
                            </label>
                            @error('confirmado') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="mt-6 flex flex-col-reverse gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                        {{-- Cancelar descarta la captura entera: deja de tener el mismo peso
                             visual que Guardar, y pregunta antes. --}}
                        <button type="button" wire:click="cancelar"
                                wire:confirm="Se perderá lo capturado en esta orden. ¿Cancelar?"
                                wire:loading.attr="disabled" wire:target="cancelar,guardar"
                                class="min-h-11 px-6 py-2 border border-gray-300 rounded-lg text-gray-800 hover:bg-gray-100 transition-colors font-medium sm:w-auto disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="cancelar">Cancelar</span>
                            <span wire:loading wire:target="cancelar">Cancelando…</span>
                        </button>
                        {{-- wire:loading deshabilita el boton mientras viaja: el doble envio ya no es posible --}}
                        <button type="submit" wire:loading.attr="disabled" wire:target="guardar,cancelar,seleccionar,agregarFila,eliminarFila"
                                @disabled($this->problemas !== [])
                                title="{{ $this->problemas !== [] ? 'Falta: '.$this->problemas[0] : '' }}"
                                class="min-h-11 px-8 py-2 rounded-lg text-white transition-colors font-semibold disabled:opacity-60 disabled:cursor-not-allowed sm:w-auto
                                       {{ $esMuestras ? 'bg-purple-700 hover:bg-purple-800' : 'bg-blue-600 hover:bg-blue-700' }}">
                            <span wire:loading.remove wire:target="guardar">{{ $esMuestras ? 'Guardar y consumir muestra' : 'Guardar' }}</span>
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
