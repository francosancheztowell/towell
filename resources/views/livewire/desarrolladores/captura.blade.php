@php
    $esMuestras = $modo === 'muestras';
    $esKm = $this->esKarlMayer;

    // Clases repetidas, una por campo. Altura fija para que todos los controles
    // de una fila midan lo mismo aunque unos sean select y otros input.
    $claseCampo = 'w-full h-11 px-3 rounded-lg border border-slate-300 bg-white text-slate-900 text-base shadow-xs transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none disabled:bg-slate-100 disabled:text-slate-500';
    $claseEtiqueta = 'block text-sm font-medium text-slate-700 mb-1.5';

    // Detalle: tabla en escritorio, ficha por renglon en movil. Un solo marcado;
    // el rotulo de cada celda sale de data-label con un pseudo-elemento.
    $trDetalle = 'block border-b border-slate-200 last:border-0 md:table-row md:border-0 md:hover:bg-slate-50';
    $tdDetalle = 'flex items-start gap-3 px-4 py-1.5 before:w-24 before:shrink-0 before:pt-2.5 before:text-xs before:font-semibold before:uppercase before:tracking-wide before:text-slate-500 before:content-[attr(data-label)] md:table-cell md:py-2 md:before:hidden';
@endphp

{{-- Sin fondo propio: el degradado azul del layout se ve a traves --}}
<div class="min-h-full w-full p-2 sm:p-4 lg:p-6">
    {{-- Barra de progreso SIN wire:target: se enciende con cualquier peticion del
         componente, asi ninguna interaccion se queda sin senal. El .delay evita que
         parpadee en las respuestas rapidas. --}}
    <div wire:loading.delay.flex class="fixed inset-x-0 top-0 z-50 hidden h-1 overflow-hidden bg-blue-100" role="status" aria-live="polite">
        <span class="sr-only">Procesando…</span>
        <span class="h-full w-1/3 animate-[barra_1.1s_ease-in-out_infinite] bg-blue-600"></span>
    </div>

    <div class="flex w-full flex-col rounded-2xl border border-slate-200 bg-white shadow-sm">
        {{-- Sin titulo: el modulo ya se nombra en la barra de navegacion. Lo unico que
             la pantalla tiene que decir de si misma es lo que no se ve venir, y eso solo
             pasa en muestras: el guardado borra el registro. --}}
        @if ($esMuestras)
            <div class="flex items-center gap-2 rounded-t-2xl border-b border-violet-200 bg-violet-50 px-4 py-2.5 sm:px-6">
                <span class="rounded bg-violet-700 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white">Muestra</span>
                <span class="text-sm text-violet-900">Al guardar, la muestra se elimina del programa.</span>
            </div>
        @endif

        <div class="flex flex-col gap-6 p-4 sm:p-6">

            {{-- Selector de telar + tabla de producciones.
                 En pantallas anchas el selector es una columna estrecha fija: la tabla
                 se queda con todo el resto en vez de con dos tercios. --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(230px,300px)_1fr]">
                <div>
                    <label for="telarOperador" class="{{ $claseEtiqueta }}">Seleccionar Telar</label>
                    {{-- La leyenda va arriba del select: dentro de la lista desplegada no
                         cabe explicar nada, y el color solo no dice que significa. --}}
                    <p class="mb-1.5 flex items-center gap-1.5 text-xs text-slate-600">
                        <span class="inline-block h-3 w-3 shrink-0 rounded-sm bg-amber-200 ring-1 ring-amber-400"></span>
                        Los amarillos tienen órdenes siguientes
                    </p>
                    <div class="relative">
                        <select wire:model.live="telarId" id="telarOperador" class="{{ $claseCampo }} pr-10">
                            <option value="">Selecciona un telar</option>
                            @foreach ($this->telares as $telar)
                                @php($conSiguientes = (bool) data_get($telar, 'tieneSiguientes'))
                                <option wire:key="tel-{{ data_get($telar, 'NoTelarId') }}"
                                        value="{{ data_get($telar, 'NoTelarId') }}"
                                        class="{{ $conSiguientes ? 'bg-amber-200 font-semibold text-amber-950' : 'bg-white text-slate-500' }}">
                                    {{ data_get($telar, 'NoTelarId') }}
                                </option>
                            @endforeach
                        </select>
                        <span wire:loading wire:target="telarId" class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="h-5 w-5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                    </div>

                    {{-- Banner de la orden en curso. Debajo del selector y no sobre la tabla:
                         es informacion del telar, no de la lista. --}}
                    @if ($this->ordenEnProceso)
                        @php($enProceso = $this->ordenEnProceso)
                        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3">
                            <div class="flex items-center gap-2">
                                {{-- relative: sin el, el ping absolute se escapaba del contenedor --}}
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-2.5 w-2.5 animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                </span>
                                <span class="text-xs font-bold uppercase tracking-wide text-amber-900">
                                    {{-- El banner mira al telar destino cuando hay cambio: hay que decirlo,
                                         o se lee como que la orden ya se movio. --}}
                                    {{ $this->hayCambioTelar ? 'En proceso en el destino' : 'En proceso' }}
                                </span>
                                <span wire:loading wire:target="telarId,telarDestino" class="ml-auto">
                                    <svg class="h-4 w-4 animate-spin text-amber-700" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </span>
                            </div>
                            <p class="mt-2 text-base font-bold text-amber-900">{{ $enProceso['noProduccion'] }}</p>
                            <p class="text-sm text-amber-800">{{ $enProceso['nombre'] }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-amber-800">
                                <span class="rounded-full bg-white px-2 py-0.5 font-semibold ring-1 ring-amber-300">Telar {{ $enProceso['telar'] }}</span>
                                <span>{{ $enProceso['fecha'] }}</span>
                            </div>

                            <div class="mt-3">
                                <label for="accionGuardar" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-amber-900">Al guardar</label>
                                <select wire:model.live="accion" id="accionGuardar"
                                        wire:loading.attr="disabled" wire:target="accion,guardar,seleccionar"
                                        @disabled(! $this->filaSeleccionada)
                                        class="h-11 w-full rounded-lg border border-amber-300 bg-white px-3 text-base text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none disabled:bg-amber-100/60 disabled:text-amber-700">
                                    <option value="finalizar">Finalizar la orden</option>
                                    <option value="reprogramar_siguiente">Reprogramar al siguiente</option>
                                    <option value="reprogramar_final">Reprogramar al final</option>
                                </select>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="min-w-0">
                    @if ($telarId !== '')
                        @php($hayOrdenes = $this->producciones->isNotEmpty())
                        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="text-base font-bold text-slate-900 sm:text-lg">Siguientes órdenes en el programa</h3>
                            @if ($hayOrdenes)
                                <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ $this->producciones->count() }} {{ $this->producciones->count() === 1 ? 'orden' : 'órdenes' }}
                                </span>
                            @endif
                        </div>

                        {{-- Sin ordenes no se pinta una tabla vacia: el telar no tiene nada
                             que capturar y eso se tiene que leer de un vistazo desde lejos. --}}
                        @if (! $hayOrdenes)
                            <div class="flex min-h-40 flex-col items-center justify-center rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 px-6 py-10 text-center"
                                 wire:loading.class="opacity-50" wire:target="telarId,telarDestino">
                                <svg class="h-12 w-12 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="mt-3 text-xl font-bold text-amber-900 sm:text-2xl">No hay órdenes programadas en este telar</p>
                                <p class="mt-1 text-base text-amber-800">Telar {{ $telarId }}</p>
                            </div>
                        @else
                        <div class="overflow-hidden rounded-xl border border-slate-200"
                             wire:loading.class="opacity-50" wire:target="telarId,telarDestino">
                            <div class="max-h-[46vh] overflow-auto">
                                <table class="w-full">
                                    <thead class="sticky top-0 z-10 bg-slate-700">
                                        <tr>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-white">Orden</th>
                                            <th scope="col" class="hidden px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-white sm:table-cell">Fecha cambio</th>
                                            <th scope="col" class="hidden px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-white md:table-cell">Clave</th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-white">Modelo</th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-white">Telar destino</th>
                                            <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-white">Sel.</th>
                                        </tr>
                                    </thead>
                                    {{-- Cebra por renglon. Antes alternaba por celda, que es el eje que
                                         no se lee: la fila se sigue de izquierda a derecha. --}}
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        @foreach ($this->producciones as $p)
                                            @php($id = (int) ($p->Id ?? 0))
                                            @php($seleccionada = $produccionSeleccionada === $id)
                                            <tr wire:key="prod-{{ $id }}"
                                                class="transition-colors {{ $seleccionada ? 'bg-blue-50 ring-2 ring-inset ring-blue-500' : ($loop->even ? 'bg-slate-50/70 hover:bg-slate-100' : 'hover:bg-slate-100') }}">
                                                <td class="px-3 py-3 text-sm font-semibold whitespace-nowrap text-slate-900">
                                                    {{ $p->NoProduccion }}
                                                    <span class="block text-xs font-normal text-slate-500 sm:hidden">
                                                        {{ $p->FechaInicio ? \Carbon\Carbon::parse($p->FechaInicio)->format('d/m/Y') : 'N/A' }}
                                                    </span>
                                                </td>
                                                <td class="hidden px-3 py-3 text-sm whitespace-nowrap text-slate-600 sm:table-cell">
                                                    {{ $p->FechaInicio ? \Carbon\Carbon::parse($p->FechaInicio)->format('d/m/Y') : 'N/A' }}
                                                </td>
                                                <td class="hidden px-3 py-3 text-sm whitespace-nowrap text-slate-600 md:table-cell">{{ $p->TamanoClave ?? 'N/A' }}</td>
                                                <td class="px-3 py-3 text-sm break-words text-slate-700">{{ $p->NombreProducto ?? 'N/A' }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    {{-- Antes cada fila pintaba el catalogo completo de telares: con N filas
                                                         y M telares eran N*M nodos. Ahora solo lo pinta la fila elegida. --}}
                                                    @if ($seleccionada)
                                                        <label for="telarDestino" class="sr-only">Telar destino</label>
                                                        <select wire:model.live="telarDestino" id="telarDestino"
                                                                wire:loading.attr="disabled" wire:target="telarDestino,seleccionar"
                                                                class="h-10 w-full min-w-40 rounded-lg border px-2 text-sm focus:outline-none focus:ring-2 disabled:opacity-60
                                                                       {{ $this->hayCambioTelar ? 'border-amber-500 bg-amber-50 font-semibold text-amber-900 focus:ring-amber-500/30' : 'border-slate-300 bg-white focus:ring-blue-500/30' }}">
                                                            <option value="{{ ($p->SalonTejidoId ?? '') . '|' . $telarId }}">{{ $telarId }} (sin cambio)</option>
                                                            @foreach ($this->telaresDestino as $t)
                                                                @php($partes = explode('|', $t['value'] ?? '', 2))
                                                                @if (trim($partes[1] ?? '') !== (string) $telarId)
                                                                    <option wire:key="dst-{{ $t['value'] }}" value="{{ $t['value'] }}">{{ $t['label'] }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        @error('telarDestino') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                                    @else
                                                        <span class="text-sm text-slate-400" aria-hidden="true">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                                    <label class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center">
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
                                                               class="h-6 w-6 cursor-pointer rounded border-slate-400 text-blue-600 focus:ring-2 focus:ring-blue-500">
                                                    </label>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    @else
                        <div class="flex h-full min-h-40 items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                            <p class="text-base text-slate-600">Selecciona un telar para ver sus órdenes.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Formulario --}}
            @if ($this->filaSeleccionada)
                @php($fila = $this->filaSeleccionada)
                {{-- El formulario nace fuera de pantalla: sin el deslizamiento y el scroll,
                     elegir una orden no se notaba y el operador se quedaba mirando la tabla.
                     La llave lleva el Id para que cambiar de orden vuelva a dispararlo. --}}
                <div wire:key="form-{{ $produccionSeleccionada }}"
                     x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                     {{-- Sin overflow-hidden a proposito: en un ancestro anula el position:sticky
                          de la barra de Guardar/Cancelar, que se quedaba al final del scroll. --}}
                     class="animate-[deslizar_.28s_ease-out] rounded-xl border border-slate-200 shadow-sm {{ $this->hayCambioTelar ? 'border-l-4 border-l-amber-500' : '' }}">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-t-xl border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-6">
                        <h3 class="text-xl font-bold text-slate-900 sm:text-2xl">Datos del Desarrollador</h3>
                        <span class="text-sm text-slate-600">Telar <strong class="text-slate-900">{{ $telarId }}</strong></span>
                        <span class="text-sm text-slate-600">Orden <strong class="text-slate-900">{{ $fila['NoProduccion'] ?: '-' }}</strong></span>
                        <span class="text-sm text-slate-600">{{ $fila['NombreProducto'] ?: '-' }}</span>
                        {{-- Karl Mayer no monta julios: "Sin julio rizo" ahi no es un aviso,
                             es una columna de otro salon puesta como si faltara algo. --}}
                        @unless ($esKm)
                            <span class="ml-auto flex flex-wrap gap-1.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $form['NumeroJulioRizo'] ? 'bg-sky-50 text-sky-800 ring-sky-300' : 'bg-slate-100 text-slate-500 ring-slate-300' }}">
                                    {{ $form['NumeroJulioRizo'] ? 'Rizo '.$form['NumeroJulioRizo'] : 'Sin julio rizo' }}
                                </span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $form['NumeroJulioPie'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-300' : 'bg-slate-100 text-slate-500 ring-slate-300' }}">
                                    {{ $form['NumeroJulioPie'] ? 'Pie '.$form['NumeroJulioPie'] : 'Sin julio pie' }}
                                </span>
                            </span>
                        @endunless
                    </div>

                    <div class="flex flex-col gap-6 p-4 sm:p-6">
                        @if ($this->hayCambioTelar)
                            {{-- Mover una orden de telar era indistinguible de no moverla: la unica
                                 senal era un chip text-xs entre otros cuatro. --}}
                            <div role="alert" class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-3 ring-1 ring-amber-200">
                                <p class="flex items-center gap-2 text-base font-semibold text-amber-900">
                                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/></svg>
                                    Esta orden se moverá del telar {{ $telarId }} al telar {{ $this->telarDestinoNombre }}.
                                </p>
                                <button type="button" wire:click="$set('telarDestino', '{{ ($fila['SalonTejidoId'] ?? '') . '|' . $telarId }}')"
                                        wire:loading.attr="disabled" wire:target="telarDestino"
                                        class="min-h-11 rounded-lg border border-amber-600 bg-white px-4 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60">
                                    Cancelar cambio
                                </button>
                            </div>
                        @endif

                        <form wire:submit="guardar">
                            {{-- Los asteriscos siguen al servidor: Julio Rizo, eficiencias, Altura de Rizo
                                 y Codificacion son required; horas y desarrollador son nullable. --}}
                            <section class="overflow-hidden rounded-xl border border-slate-300">
                            <h4 class="border-l-4 border-blue-600 bg-slate-100 px-4 py-2.5 text-sm font-bold uppercase tracking-wide text-slate-700">Captura del turno</h4>
                            <div class="grid grid-cols-1 gap-4 bg-white p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-3 xl:grid-cols-4">
                                @unless ($esKm)
                                <div>
                                    <label for="NumeroJulioRizo" class="{{ $claseEtiqueta }}">
                                        Julio rizo <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                    </label>
                                    <select wire:model.live="form.NumeroJulioRizo" id="NumeroJulioRizo"
                                            wire:loading.attr="disabled" wire:target="form.NumeroJulioRizo"
                                            class="{{ $claseCampo }}">
                                        <option value="">Selecciona un julio</option>
                                        @foreach ($this->juliosRizo as $julio)
                                            <option wire:key="jr-{{ data_get($julio, 'NoJulio') }}" value="{{ data_get($julio, 'NoJulio') }}">{{ data_get($julio, 'NoJulio') }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.NumeroJulioRizo') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="NumeroJulioPie" class="{{ $claseEtiqueta }}">Julio pie</label>
                                    <select wire:model.live="form.NumeroJulioPie" id="NumeroJulioPie"
                                            wire:loading.attr="disabled" wire:target="form.NumeroJulioPie"
                                            class="{{ $claseCampo }}">
                                        <option value="">Selecciona un julio</option>
                                        @foreach ($this->juliosPie as $julio)
                                            <option wire:key="jp-{{ data_get($julio, 'NoJulio') }}" value="{{ data_get($julio, 'NoJulio') }}">{{ data_get($julio, 'NoJulio') }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.NumeroJulioPie') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>
                                @endunless

                                <div>
                                    <label for="TotalPasadas" class="{{ $claseEtiqueta }}">Total pasadas</label>
                                    <input type="number" id="TotalPasadas" value="{{ $this->totalPasadas }}" readonly tabindex="-1"
                                           aria-describedby="TotalPasadasAyuda"
                                           class="h-11 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 text-base font-semibold text-slate-900">
                                    <p id="TotalPasadasAyuda" class="mt-1 text-xs text-slate-500">
                                        <span wire:loading.remove wire:target="detalles">Suma de las pasadas del detalle.</span>
                                        <span wire:loading wire:target="detalles" class="font-medium text-blue-700">Recalculando…</span>
                                    </p>
                                    @error('form.TotalPasadasDibujo') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="Desarrollador" class="{{ $claseEtiqueta }}">Desarrollador</label>
                                    <select wire:model="form.Desarrollador" id="Desarrollador" class="{{ $claseCampo }}">
                                        <option value="">Selecciona un desarrollador</option>
                                        @foreach ($this->desarrolladores as $d)
                                            <option wire:key="dev-{{ data_get($d, 'idusuario') }}" value="{{ data_get($d, 'nombre') }}">{{ data_get($d, 'nombre') }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.Desarrollador') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>

                                {{-- Eficiencias: el selector se arma al abrirlo, no 101 botones por adelantado.
                                     La rejilla envuelve en vez de desplazarse de lado: el 63 estaba a ocho
                                     gestos de scroll y ahora esta a la vista. --}}
                                @foreach (['EficienciaInicio' => 'Eficiencia de inicio', 'EficienciaFinal' => 'Eficiencia final'] as $campo => $etiqueta)
                                    @php($vacio = $form[$campo] === null || $form[$campo] === '')
                                    <div wire:key="efi-{{ $campo }}" x-data="{ abierto: false }" class="relative">
                                        <label class="{{ $claseEtiqueta }}">{{ $etiqueta }} <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span></label>
                                        <button type="button" @click="abierto = !abierto" :aria-expanded="abierto"
                                                class="flex h-11 w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 text-left shadow-xs transition hover:border-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none">
                                            <span class="text-base font-semibold {{ $vacio ? 'text-slate-400' : 'text-slate-900' }}">{{ $vacio ? 'Selecciona' : $form[$campo].'%' }}</span>
                                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="abierto" x-cloak @click.outside="abierto = false" x-transition.opacity class="absolute inset-x-0 z-30 mt-2">
                                            <div class="grid grid-cols-10 gap-1 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                                                @for ($v = 0; $v <= 100; $v++)
                                                    <button type="button" wire:click="$set('form.{{ $campo }}', {{ $v }})" @click="abierto = false"
                                                            wire:loading.attr="disabled" wire:target="form.{{ $campo }}"
                                                            class="h-9 rounded-md border text-sm font-semibold transition
                                                                   {{ (string) $form[$campo] === (string) $v ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50' }}
                                                                   {{ $v === 80 && (string) $form[$campo] !== (string) $v ? 'ring-2 ring-blue-300' : '' }}">
                                                        {{ $v }}
                                                    </button>
                                                @endfor
                                            </div>
                                        </div>
                                        @error('form.'.$campo) <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach

                                <div>
                                    <label for="HoraInicio" class="{{ $claseEtiqueta }}">Hora inicio</label>
                                    <input type="time" wire:model="form.HoraInicio" id="HoraInicio" class="{{ $claseCampo }}">
                                    @error('form.HoraInicio') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="HoraFinal" class="{{ $claseEtiqueta }}">Hora final</label>
                                    <input type="time" wire:model="form.HoraFinal" id="HoraFinal" class="{{ $claseCampo }}">
                                    @error('form.HoraFinal') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>
                                @unless ($esKm)
                                <div>
                                    <label for="DesperdicioTrama" class="{{ $claseEtiqueta }}">Desperdicio de trama</label>
                                    <input type="number" wire:model="form.DesperdicioTrama" id="DesperdicioTrama"
                                           step="0.01" min="0" inputmode="decimal" autocomplete="off" class="{{ $claseCampo }}">
                                    @error('form.DesperdicioTrama') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="AlturaRizo" class="{{ $claseEtiqueta }}">
                                        Altura de rizo <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                    </label>
                                    <input type="number" wire:model="form.AlturaRizo" id="AlturaRizo"
                                           step="0.1" min="0" max="10" inputmode="decimal" autocomplete="off"
                                           aria-describedby="AlturaRizoAyuda" class="{{ $claseCampo }}">
                                    <p id="AlturaRizoAyuda" class="mt-1 text-xs text-slate-500">De 0 a 10, un decimal.</p>
                                    @error('form.AlturaRizo') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>
                                @endunless
                            </div>

                            {{-- Codificacion: el auto-avance entre casillas es puro manejo de foco,
                                 se queda en Alpine para no pagar un viaje al servidor por tecla.
                                 Las casillas envuelven: 20 x 44px no caben en un movil y antes
                                 se capturaba con scroll horizontal. --}}
                            </section>

                            <section class="overflow-hidden rounded-xl border border-slate-300" x-data="codificacionBoxes()">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-l-4 border-blue-600 bg-slate-100 px-4 py-2.5">
                                    <label id="codificacionEtiqueta" class="text-sm font-bold uppercase tracking-wide text-slate-700">
                                        Codificación Modelo <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                    </label>
                                    @php($largo = mb_strlen($this->codificacionModelo))
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $largo >= 10 && $largo <= 20 ? 'bg-emerald-50 text-emerald-800 ring-emerald-300' : 'bg-amber-50 text-amber-800 ring-amber-300' }}">
                                        {{ $largo }}/20 · mínimo 10
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 bg-white p-4 sm:p-5" role="group" aria-labelledby="codificacionEtiqueta">
                                    @for ($i = 0; $i < 20; $i++)
                                        <input type="text" maxlength="1" wire:key="cod-{{ $produccionSeleccionada }}-{{ $i }}"
                                               wire:model.blur="codificacion.{{ $i }}"
                                               id="codificacion-{{ $i }}" aria-label="Carácter {{ $i + 1 }} de 20"
                                               autocomplete="off" autocapitalize="characters" spellcheck="false"
                                               x-on:input="avanzar($event)" x-on:keydown.backspace="retroceder($event)" x-on:paste="pegar($event)"
                                               data-indice="{{ $i }}"
                                               class="codificacion-char h-12 w-10 rounded-lg border-2 border-slate-300 text-center text-lg font-bold uppercase text-slate-900 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none sm:w-11">
                                    @endfor
                                    {{-- Karl Mayer no lleva sufijo, ni aqui ni en el servidor --}}
                                    @unless ($esKm)
                                        <span class="px-1 text-lg font-bold text-slate-500">.JC5</span>
                                    @endunless
                                </div>
                                @error('form.CodificacionModelo') <p class="bg-white px-4 pb-4 text-sm font-medium text-rose-700 sm:px-5">{{ $message }}</p> @enderror
                            </section>

                            {{-- Detalles --}}
                            <section class="overflow-hidden rounded-xl border border-slate-300">
                                <div class="flex items-center justify-between gap-3 border-l-4 border-blue-600 bg-slate-100 px-4 py-2">
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ $esKm ? 'Barras del telar' : 'Detalles de la orden' }}</h3>
                                    {{-- Las cuatro barras son posiciones fisicas de la maquina: ni se agregan ni se quitan --}}
                                    @unless ($esKm)
                                    @php($tope = collect($detalles)->reject(fn ($d) => str_contains((string) ($d['slot'] ?? ''), 'Trama'))->count() >= 5)
                                    <button type="button" wire:click="agregarFila"
                                            wire:loading.attr="disabled" wire:target="agregarFila"
                                            @disabled($tope)
                                            title="{{ $tope ? 'Solo se pueden capturar 5 combinaciones' : '' }}"
                                            class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 text-sm font-semibold transition
                                                   {{ $tope ? 'cursor-not-allowed bg-slate-200 text-slate-500' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                        Agregar fila
                                    </button>
                                    @endunless
                                </div>
                                <div class="bg-white md:overflow-x-auto">
                                    <table class="w-full md:min-w-[56rem]">
                                        <thead class="hidden bg-slate-100 md:table-header-group">
                                            <tr>
                                                <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Calibre</th>
                                                <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $esKm ? 'Cuenta' : 'Hilo' }}</th>
                                                <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Fibra</th>
                                                <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Cod color</th>
                                                <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Nombre color</th>
                                                <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Pasadas</th>
                                                <th scope="col" class="w-20 px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 bg-white md:divide-y">
                                            @forelse ($detalles as $i => $detalle)
                                                @php($esTrama = str_contains((string) ($detalle['slot'] ?? ''), 'Trama'))
                                                @php($fueraDeCatalogo = ! empty($detalle['noVigente']))
                                                @php($opciones = $this->opcionesFila($detalle))
                                                @php($hilos = $this->hilosDelCalibre((string) ($detalle['Calibre'] ?? '')))
                                                <tr wire:key="det-{{ $produccionSeleccionada }}-{{ $i }}" class="{{ $trDetalle }}">
                                                    {{-- Cabecera del renglon: solo en movil, donde la tabla deja de serlo --}}
                                                    <td class="flex items-center justify-between bg-slate-50 px-4 py-2 md:hidden">
                                                        <span class="text-xs font-bold uppercase tracking-wide text-slate-600">
                                                            {{ $esKm ? 'Barra '.($i + 1) : ($esTrama ? 'Trama' : 'Combinación '.$i) }}
                                                        </span>
                                                        @unless ($esTrama || $esKm)
                                                            <button type="button" wire:click="eliminarFila({{ $i }})"
                                                                    wire:confirm="¿Quitar esta combinación de la captura?"
                                                                    wire:loading.attr="disabled" wire:target="eliminarFila"
                                                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-rose-700 transition hover:bg-rose-50">
                                                                <span class="sr-only">Eliminar la combinación de la fila {{ $i + 1 }}</span>
                                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        @endunless
                                                    </td>
                                                    {{-- Calibre e Hilo salen del mismo renglon del catalogo. Un solo
                                                         select llena los dos, que es lo que impide que se desparejen:
                                                         capturados por separado, el codigo 10.1 llego a convivir con
                                                         diez divisores distintos y la formula de L.Mat calculaba mal. --}}
                                                    <td data-label="Calibre" class="{{ $tdDetalle }} md:min-w-64">
                                                        <div class="w-full">
                                                            <select wire:key="det-{{ $i }}-CalibreId"
                                                                    wire:change="elegirCalibre({{ $i }}, $event.target.value)"
                                                                    wire:loading.attr="disabled" wire:target="elegirCalibre"
                                                                    aria-label="Calibre, fila {{ $i + 1 }}"
                                                                    class="h-10 w-full rounded-lg border px-2 text-sm focus:outline-none focus:ring-2 disabled:opacity-60
                                                                           {{ $fueraDeCatalogo ? 'border-rose-500 bg-rose-50 text-rose-900 focus:ring-rose-500/30' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500/30' }}">
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
                                                                <p class="mt-1 text-xs font-medium text-rose-700">
                                                                    El calibre <strong>{{ $detalle['Calibre'] }}</strong> ya no está vigente. Elige uno de la lista para poder guardar.
                                                                </p>
                                                            @endif
                                                            @error('detalles.'.$i.'.CalibreId') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                        </div>
                                                    </td>
                                                    {{-- Hilo es el divisor del calibre elegido: mientras el catalogo tenga
                                                         uno solo no hay nada que decidir y se muestra, no se captura. Con dos
                                                         o mas registrados para ese calibre deja de haber respuesta unica y
                                                         se vuelve select. --}}
                                                    <td data-label="{{ $esKm ? 'Cuenta' : 'Hilo' }}" class="{{ $tdDetalle }}">
                                                        <div class="w-full">
                                                            @if ($esKm)
                                                                {{-- Karl Mayer no guarda divisor: lo que acompana al calibre es la cuenta --}}
                                                                <input type="text" wire:model="detalles.{{ $i }}.Cuenta"
                                                                       aria-label="Cuenta, barra {{ $i + 1 }}" autocomplete="off"
                                                                       class="h-10 w-full rounded-lg border border-slate-300 px-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 md:w-28">
                                                                @error('detalles.'.$i.'.Cuenta') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                            @elseif (count($hilos) > 1)
                                                                <select wire:key="det-{{ $i }}-Hilo"
                                                                        wire:change="elegirHilo({{ $i }}, $event.target.value)"
                                                                        wire:loading.attr="disabled" wire:target="elegirHilo"
                                                                        aria-label="Hilo, fila {{ $i + 1 }}"
                                                                        class="h-10 w-full rounded-lg border border-slate-300 px-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-60 md:w-40">
                                                                    @foreach ($hilos as $opcion)
                                                                        <option value="{{ $opcion['Divisor'] }}" @selected((string) $detalle['Hilo'] === $opcion['Divisor'])>{{ $opcion['etiqueta'] }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <input type="text" readonly tabindex="-1"
                                                                       value="{{ $detalle['Hilo'] }}"
                                                                       aria-label="Hilo, fila {{ $i + 1 }}"
                                                                       class="h-10 w-full rounded-lg border border-slate-300 bg-slate-100 px-2 text-sm text-slate-700 md:w-24">
                                                            @endif
                                                            @error('detalles.'.$i.'.Hilo') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                        </div>
                                                    </td>
                                                    {{-- Fibra y color salen de AX colgando del articulo del hilo, igual
                                                         que en L.Mat: fibra es el ConfigId, el color es InventColor. Eran
                                                         texto libre y produccion acumulo 272 variantes de fibra (TERMO,
                                                         TERMO., TERMOFIJADO) y ni un solo codigo de color capturado. --}}
                                                    <td data-label="Fibra" class="{{ $tdDetalle }}">
                                                        <div class="w-full">
                                                            <select wire:key="det-{{ $i }}-Fibra" wire:model="detalles.{{ $i }}.Fibra"
                                                                    aria-label="Fibra, fila {{ $i + 1 }}"
                                                                    @disabled(empty($detalle['CalibreId']))
                                                                    class="h-10 w-full rounded-lg border px-2 text-sm focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500
                                                                           {{ $opciones['fibraFuera'] ? 'border-amber-500 bg-amber-50 text-amber-900 focus:ring-amber-500/30' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500/30' }}">
                                                                <option value="">{{ empty($detalle['CalibreId']) ? 'Elige primero el hilo' : 'Sin fibra' }}</option>
                                                                @foreach ($opciones['fibras'] as $fibra)
                                                                    <option value="{{ $fibra }}">{{ $fibra }}{{ $opciones['fibraFuera'] && $loop->first ? ' — fuera de AX' : '' }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('detalles.'.$i.'.Fibra') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                        </div>
                                                    </td>
                                                    <td data-label="Color" class="{{ $tdDetalle }}">
                                                        <div class="w-full">
                                                            <select wire:key="det-{{ $i }}-CodColor"
                                                                    wire:change="elegirColor({{ $i }}, $event.target.value)"
                                                                    wire:loading.attr="disabled" wire:target="elegirColor"
                                                                    aria-label="Cod Color, fila {{ $i + 1 }}"
                                                                    @disabled(empty($detalle['CalibreId']))
                                                                    class="h-10 w-full rounded-lg border px-2 text-sm focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500
                                                                           {{ $opciones['colorFuera'] ? 'border-amber-500 bg-amber-50 text-amber-900 focus:ring-amber-500/30' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500/30' }}">
                                                                <option value="">{{ empty($detalle['CalibreId']) ? 'Elige primero el hilo' : 'Sin color' }}</option>
                                                                @foreach ($opciones['colores'] as $color)
                                                                    <option value="{{ $color['InventColorId'] }}" @selected((string) ($detalle['CodColor'] ?? '') === (string) $color['InventColorId'])>{{ $color['InventColorId'] }}{{ $opciones['colorFuera'] && $loop->first ? ' — fuera de AX' : '' }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('detalles.'.$i.'.CodColor') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                        </div>
                                                    </td>
                                                    {{-- El nombre viene con el codigo, del mismo renglon de AX: se muestra. --}}
                                                    <td data-label="Nombre" class="{{ $tdDetalle }}">
                                                        <div class="w-full">
                                                            <input type="text" readonly tabindex="-1"
                                                                   value="{{ $detalle['NombreColor'] }}"
                                                                   aria-label="Nombre Color, fila {{ $i + 1 }}"
                                                                   class="h-10 w-full rounded-lg border border-slate-300 bg-slate-100 px-2 text-sm text-slate-700">
                                                            @error('detalles.'.$i.'.NombreColor') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                        </div>
                                                    </td>
                                                    <td data-label="Pasadas" class="{{ $tdDetalle }}">
                                                        <div class="w-full">
                                                            <input type="number" min="1" step="1" inputmode="numeric" autocomplete="off"
                                                                   wire:model.live.debounce.400ms="detalles.{{ $i }}.Pasadas"
                                                                   aria-label="Pasadas, fila {{ $i + 1 }}"
                                                                   class="h-10 w-full rounded-lg border border-slate-300 px-2 text-sm font-semibold focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 md:w-24">
                                                            @error('detalles.'.$i.'.Pasadas') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                                                        </div>
                                                    </td>
                                                    {{-- La accion ya se pinto arriba en movil; aqui es solo la columna de escritorio --}}
                                                    <td class="hidden px-4 py-2 text-center md:table-cell">
                                                        @if ($esKm)
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Barra {{ $i + 1 }}</span>
                                                        @elseif ($esTrama)
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Trama</span>
                                                        @else
                                                            <button type="button" wire:click="eliminarFila({{ $i }})"
                                                                    wire:confirm="¿Quitar esta combinación de la captura?"
                                                                    wire:loading.attr="disabled" wire:target="eliminarFila"
                                                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-rose-700 transition hover:bg-rose-50">
                                                                <span class="sr-only">Eliminar la combinación de la fila {{ $i + 1 }}</span>
                                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr class="block md:table-row">
                                                    <td colspan="7" class="block px-6 py-6 text-center text-sm text-slate-600 md:table-cell">
                                                        Esta orden no trae detalles. Usa "Agregar fila" para capturarlos.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            {{-- Nada puede rechazarse en silencio: lo que no tiene campo propio en
                                 pantalla se lista aqui, arriba de los botones. --}}
                            @error('guardar')
                                <div role="alert" class="mt-6 flex gap-3 rounded-xl border-l-4 border-rose-600 bg-rose-50 px-4 py-3 ring-1 ring-rose-200">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                    <div>
                                        <p class="text-base font-bold text-rose-900">No se pudo guardar</p>
                                        <p class="mt-0.5 text-sm text-rose-800">{{ $message }}</p>
                                    </div>
                                </div>
                            @enderror

                            {{-- Lo que falta para poder guardar. Es la misma lista que deshabilita
                                 el boton, asi que nunca hay un boton apagado sin explicacion. --}}
                            @if ($this->problemas !== [])
                                <div role="status" class="mt-6 flex gap-3 rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-3 ring-1 ring-amber-200">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <div>
                                        <p class="text-base font-bold text-amber-900">Falta esto para poder guardar</p>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-amber-900">
                                            @foreach ($this->problemas as $problema)
                                                <li>{{ $problema }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            @if ($this->requiereConfirmacion)
                                <div class="mt-6 rounded-xl border-l-4 border-amber-500 bg-white px-4 py-4 ring-1 ring-amber-300">
                                    <p class="flex items-center gap-2 text-base font-bold text-amber-900">
                                        <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                        Al guardar:
                                    </p>
                                    <ul class="mt-2 list-disc space-y-1 pl-6 text-base text-slate-800">
                                        @foreach ($this->resumenGuardado as $punto)
                                            <li>{{ $punto }}</li>
                                        @endforeach
                                    </ul>
                                    <label class="mt-3 flex cursor-pointer items-center gap-3 rounded-lg bg-amber-50 px-3 py-2.5 transition hover:bg-amber-100">
                                        <input type="checkbox" wire:model.live="confirmado"
                                               wire:loading.attr="disabled" wire:target="confirmado,guardar"
                                               class="h-6 w-6 rounded border-amber-500 text-amber-600 focus:ring-2 focus:ring-amber-500 disabled:opacity-60">
                                        <span class="text-base font-semibold text-amber-900">Confirmo</span>
                                    </label>
                                    @error('confirmado') <p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            {{-- Barra de acciones pegada al borde inferior: con el formulario largo,
                                 Guardar quedaba a una pantalla de scroll del ultimo campo. --}}
                            <div class="sticky bottom-0 z-20 -mx-4 -mb-4 flex flex-col-reverse gap-3 rounded-b-xl border-t-2 border-slate-300 bg-white/95 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,.25)] backdrop-blur sm:-mx-6 sm:-mb-6 sm:flex-row sm:justify-end sm:px-6">
                                {{-- Cancelar descarta la captura entera: deja de tener el mismo peso
                                     visual que Guardar, y pregunta antes. --}}
                                <button type="button" wire:click="cancelar"
                                        wire:confirm="Se perderá lo capturado en esta orden. ¿Cancelar?"
                                        wire:loading.attr="disabled" wire:target="cancelar,guardar"
                                        class="min-h-13 w-full rounded-lg border-2 border-slate-300 px-8 text-base font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60 sm:w-56">
                                    <span wire:loading.remove wire:target="cancelar">Cancelar</span>
                                    <span wire:loading wire:target="cancelar" class="inline-flex items-center justify-center gap-2">
                                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Cancelando…
                                    </span>
                                </button>
                                {{-- wire:loading deshabilita el boton mientras viaja: el doble envio ya no es posible --}}
                                <button type="submit" wire:loading.attr="disabled" wire:target="guardar,cancelar,seleccionar,agregarFila,eliminarFila"
                                        @disabled($this->problemas !== [])
                                        title="{{ $this->problemas !== [] ? 'Falta: '.$this->problemas[0] : '' }}"
                                        class="min-h-13 w-full rounded-lg px-10 text-base font-bold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60 sm:w-72
                                               {{ $esMuestras ? 'bg-violet-600 hover:bg-violet-700' : 'bg-blue-600 hover:bg-blue-700' }}">
                                    <span wire:loading.remove wire:target="guardar">{{ $esMuestras ? 'Guardar y consumir muestra' : 'Guardar' }}</span>
                                    <span wire:loading wire:target="guardar" class="inline-flex items-center justify-center gap-2">
                                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Guardando…
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
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
