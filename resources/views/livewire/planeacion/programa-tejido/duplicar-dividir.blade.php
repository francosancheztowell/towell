<div>
    {{-- Duplicar / Dividir. <dialog> nativo y no flux:modal: el layout no carga Alpine por su cuenta.
         wire:ignore.self evita que un refresco le quite `open`. No se cierra con clic fuera para no
         perder lo capturado: Esc o Cancelar. --}}
    @if ($registroId !== null)
        @php
            $dividir = $modo === 'dividir';
            $llave = $dividir ? 'dividir' : 'duplicar';
            $filasModo = $filas[$llave];
            $numero = fn ($v) => number_format((float) $v); // saldo y cuadre sin decimales
        @endphp
        <dialog wire:key="pt-duplicar-{{ $registroId }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" aria-labelledby="pt-duplicar-titulo"
                class="ui-dialogo ui-dialogo--formulario w-[min(98vw,120rem)]! max-w-none!">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading id="pt-duplicar-titulo" size="xl">
                        {{ ['duplicar' => 'Duplicar registro', 'vincular' => 'Duplicar y vincular', 'dividir' => 'Dividir saldo'][$modo] }}
                    </flux:heading>

                    <flux:radio.group wire:model.live="modo" variant="segmented" aria-label="Modo">
                        <flux:radio value="duplicar" label="Duplicar" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
                        @if ($puedeVincular)
                            <flux:radio value="vincular" label="Vincular" class="min-h-touch data-checked:bg-purple-600! data-checked:text-white!" />
                        @endif
                        <flux:radio value="dividir" label="Dividir" class="min-h-touch data-checked:bg-green-600! data-checked:text-white!" :disabled="! $this->puedeDividir()" />
                    </flux:radio.group>
                </div>

                @if (! $dividir && ! $this->puedeDividir())
                    <flux:text class="text-caption">Dividir no está disponible: {{ $this->porQueNoDivide() }}</flux:text>
                @endif

                {{-- Sin overflow propio: el scroll lo hace el <dialog>, así las sugerencias (absolute z-20) no se recortan. --}}
                <div>
                    <table class="w-full min-w-[78rem] border-separate border-spacing-y-1.5 text-sm">
                        <thead class="text-center text-sm font-semibold text-zinc-600 dark:text-zinc-300 [&_th]:px-2">
                            <tr>
                                <th scope="col" class="w-32">Modelo</th>
                                <th scope="col" class="w-[18%]">Producto</th>
                                <th scope="col" class="w-60">Flog</th>
                                <th scope="col" class="w-[28%]">Descripción</th>
                                <th scope="col" class="w-24">Aplicación</th>
                                <th scope="col" class="w-28">Telar</th>
                                <th scope="col" class="w-28">Pedido</th>
                                <th scope="col" class="w-20">% 2as</th>
                                <th scope="col" class="w-20">Saldo</th>
                                <th scope="col" class="w-12"><span class="sr-only">Quitar</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filasModo as $i => $fila)
                                @php
                                    $base = "filas.$llave.$i";
                                    $fija = $fila['existente'];
                                @endphp
                                {{-- Filas alternadas: una blanca, una gris. --}}
                                <tr wire:key="pt-dup-{{ $llave }}-{{ $i }}-{{ $fila['registroId'] ?? 'n' }}"
                                    class="align-top [&>td]:px-2 [&>td]:py-1.5 [&>td:first-child]:rounded-l-lg [&>td:last-child]:rounded-r-lg odd:[&>td]:bg-white even:[&>td]:bg-zinc-100 dark:odd:[&>td]:bg-zinc-900 dark:even:[&>td]:bg-zinc-800">
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['clave'] }}</span>
                                        @else
                                            {{-- El div relative ancla la lista justo debajo del input (en un <td> no es fiable). --}}
                                            <div class="relative" x-data="{ foco: false }" x-on:focusin="foco = true" x-on:focusout="foco = false">
                                                {{-- wire:change y no wire:blur: pasar por el campo sin cambiar la clave no vuelve a
                                                     consultar Modelos/AX ni pisa el Flog y la Descripción capturados. --}}
                                                {{-- Como Producto, Flog y Descripción: un renglón que crece a dos si no cabe; sin Enter. --}}
                                                <flux:textarea wire:model.live.debounce.250ms="{{ $base }}.clave" wire:change="confirmar('clave', {{ $i }})"
                                                               rows="auto" resize="none" x-on:keydown.enter.prevent
                                                               maxlength="100" autocomplete="off" aria-label="Clave modelo fila {{ $i + 1 }}" />
                                                @include('livewire.planeacion.programa-tejido.sugerencias', ['campo' => 'clave'])
                                            </div>
                                            @error("$base.clave") <span class="mt-1 block text-caption text-red-600">{{ $message }}</span> @enderror
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['producto'] }}</span>
                                        @else
                                            {{-- Producto, Flog y Descripción: textarea de un renglón que crece a dos si el texto no
                                                 cabe (rows="auto" = field-sizing: content). Enter no mete saltos de línea. --}}
                                            <flux:textarea wire:model.blur="{{ $base }}.producto" rows="auto" resize="none" x-on:keydown.enter.prevent
                                                           maxlength="255" autocomplete="off" aria-label="Producto fila {{ $i + 1 }}" />
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['flog'] }}</span>
                                        @else
                                            {{-- Al enfocar sale la lista de flogs vigentes, primero los del artículo de la fila. --}}
                                            <div class="relative" x-data="{ foco: false }" x-on:focusin="foco = true" x-on:focusout="foco = false">
                                                <flux:textarea wire:model.live.debounce.250ms="{{ $base }}.flog" wire:change="confirmar('flog', {{ $i }})"
                                                               wire:focus="abrirSugerencias({{ $i }})" rows="auto" resize="none" x-on:keydown.enter.prevent
                                                               maxlength="100" autocomplete="off" aria-label="Flog fila {{ $i + 1 }}" />
                                                @include('livewire.planeacion.programa-tejido.sugerencias', ['campo' => 'flog'])
                                            </div>
                                            @error("$base.flog") <span class="mt-1 block text-caption text-red-600">{{ $message }}</span> @enderror
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['descripcion'] }}</span>
                                        @else
                                            <flux:textarea wire:model.blur="{{ $base }}.descripcion" rows="auto" resize="none" x-on:keydown.enter.prevent
                                                           maxlength="500" autocomplete="off" aria-label="Descripción fila {{ $i + 1 }}" />
                                        @endif
                                    </td>
                                    <td>
                                        @if ($dividir)
                                            <span class="block py-2">{{ $fila['aplicacion'] }}</span>
                                        @else
                                            <flux:select wire:model="{{ $base }}.aplicacion" aria-label="Aplicación fila {{ $i + 1 }}">
                                                <option value="">—</option>
                                                @foreach ($aplicaciones as $aplicacion)
                                                    <option value="{{ $aplicacion }}">{{ $aplicacion }}</option>
                                                @endforeach
                                            </flux:select>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2 font-semibold tabular-nums">{{ $fila['telar'] }}</span>
                                        @else
                                            {{-- Telares de los salones donde existe la clave, 201 … 402; elegir fija el salón. --}}
                                            <flux:select wire:change="elegirTelar({{ $i }}, $event.target.value)" aria-label="Telar fila {{ $i + 1 }}">
                                                <option value="">Telar</option>
                                                @foreach ($telaresDe($fila['clave']) as $op)
                                                    <option value="{{ $op['valor'] }}" title="{{ $op['salon'] }}" @selected($op['salon'] === $fila['salon'] && $op['telar'] === $fila['telar'])>{{ $op['telar'] }}</option>
                                                @endforeach
                                            </flux:select>
                                            @error("$base.telar") <span class="mt-1 block text-caption text-red-600">{{ $message }}</span> @enderror
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Se captura el pedido; el saldo es texto (pedido × (1 + %2as) − producción). wire:model sin
                                             modificador: el valor vive en el cliente al teclear y ptDuplicar (duplicar.ts) muestra
                                             saldo y cuadre sin ir al servidor; en Dividir el original toma la diferencia. --}}
                                        <flux:input wire:model="{{ $base }}.pedido" x-on:input="ptDuplicar.absorber($wire, {{ $i }}, 'pedido', $el.value)"
                                                    inputmode="decimal" data-solo="decimal" data-decimales="2"
                                                    class:input="text-right tabular-nums" aria-label="Pedido fila {{ $i + 1 }}" />
                                        @if ($dividir && $fila['aviso'])
                                            <span class="mt-1 block text-caption text-amber-700 dark:text-amber-400">{{ $fila['aviso'] }}</span>
                                        @endif
                                        @error("$base.pedido") <span class="mt-1 block text-caption text-red-600">{{ $message }}</span> @enderror
                                    </td>
                                    <td>
                                        <flux:input wire:model="{{ $base }}.porcSeg" x-on:input="ptDuplicar.absorber($wire, {{ $i }}, 'porcSeg', $el.value)"
                                                    inputmode="decimal" data-solo="decimal" data-decimales="2"
                                                    class:input="text-right tabular-nums" aria-label="Porcentaje de segundas fila {{ $i + 1 }}" />
                                    </td>
                                    <td class="text-right">
                                        {{-- El servidor vuelve a calcular el saldo desde el pedido al guardar. --}}
                                        <span class="block py-2 font-semibold tabular-nums" title="pedido × (1 + %2as) − producción"
                                              x-text="ptDuplicar.saldo($wire.$get('{{ $base }}'))">{{ $numero(\App\Livewire\Planeacion\ProgramaTejido\FilasDestino::saldo($fila)) }}</span>
                                    </td>
                                    <td>
                                        @unless ($fija || count($filasModo) <= 1)
                                            <flux:button size="sm" variant="ghost" icon="trash" class="size-touch transition-transform hover:text-red-600 active:scale-90"
                                                         wire:click="quitarFila({{ $i }})" aria-label="Quitar fila {{ $i + 1 }}" />
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:button icon="plus" class="min-h-touch transition-transform active:scale-95" wire:click="agregarFila"
                                 wire:loading.attr="disabled" wire:target="agregarFila">Agregar telar</flux:button>

                    @if ($dividir)
                        {{-- Se anuncia al cambiar: el planeador ve cuánto falta sin buscar el botón. El texto del
                             servidor es el inicial; cuadreVivo (duplicar.ts) lo recalcula al teclear cada pedido. --}}
                        <p aria-live="polite" class="text-sm tabular-nums" x-data="ptDuplicar.cuadreVivo(@js($cuadre['disponible']))">
                            Disponible <strong>{{ $numero($cuadre['disponible']) }}</strong>
                            · Asignado <strong x-text="c.asignado">{{ $numero($cuadre['asignado']) }}</strong>
                            · <span class="font-semibold" x-bind:class="c.cuadra ? 'text-green-700' : 'text-red-600'" x-text="c.estado">{{ $cuadre['cuadra'] ? 'Cuadra' : ($cuadre['diferencia'] < 0 ? 'Faltan '.$numero(-$cuadre['diferencia']) : 'Sobran '.$numero($cuadre['diferencia'])) }}</span>
                        </p>
                    @endif
                </div>

                @php
                    // Los errores de telar, clave y flog (filas.*) salen bajo su campo; aquí solo los demás.
                    $otrosErrores = array_filter($errors->getMessages(), fn ($campo) => ! str_starts_with($campo, 'filas.'), ARRAY_FILTER_USE_KEY);
                @endphp
                @if ($aviso !== null || $otrosErrores !== [])
                    <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
                        <flux:callout.text>
                            @if ($aviso !== null) <span class="block">{{ $aviso }}</span> @endif
                            @foreach ($otrosErrores as $mensajes)
                                <span class="block">{{ $mensajes[0] }}</span>
                            @endforeach
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @if ($pendiente)
                    <p role="status" class="text-sm font-medium text-amber-700 dark:text-amber-400">{{ $pendiente }}</p>
                @endif
                <div class="ui-dialogo__botones">
                    <button type="button" class="ui-dialogo__boton ui-dialogo__boton--secundario"
                            x-on:click="$el.closest('dialog').close()">Cancelar</button>
                    {{-- Bloqueado mientras falte telar o haya clave/flog marcados: todo eso cambia en el servidor,
                         así que el render no va atrasado. El cuadre de Dividir no bloquea (se calcula en el cliente):
                         guardar() lo revisa y deja el aviso. --}}
                    <button type="submit" @class(['ui-dialogo__boton ui-dialogo__boton--primario disabled:cursor-not-allowed disabled:opacity-50', 'bg-purple-600! hover:bg-purple-700!' => $modo === 'vincular', 'bg-green-600! hover:bg-green-700!' => $modo === 'dividir'])
                            @disabled($pendiente !== null) wire:loading.attr="data-ocupado" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">{{ ['duplicar' => 'Duplicar', 'vincular' => 'Duplicar y vincular', 'dividir' => 'Dividir'][$modo] }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</div>
