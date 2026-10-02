<div>
    {{-- Duplicar / Dividir. <dialog> nativo y no flux:modal: el layout no carga Alpine por su cuenta.
         wire:ignore.self evita que un refresco le quite `open`. No se cierra con clic fuera para no
         perder lo capturado: Esc o Cancelar. --}}
    @if ($registroId !== null)
        @php
            $dividir = $modo === 'dividir';
            $llave = $dividir ? 'dividir' : 'duplicar';
            $filasModo = $filas[$llave];
            $numero = fn ($v) => number_format((float) $v, 2);
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
                    <flux:text class="text-caption">Dividir no está disponible: el registro no tiene No. de producción. Libéralo primero.</flux:text>
                @endif

                {{-- Sin overflow propio: el scroll lo hace el <dialog>, así las sugerencias (absolute z-20) no se recortan. --}}
                <div>
                    <table class="w-full min-w-[88rem] border-separate border-spacing-y-1.5 text-sm">
                        <thead class="text-left text-sm font-semibold text-zinc-600 dark:text-zinc-300 [&_th]:px-2">
                            <tr>
                                <th scope="col" class="w-36">Clave modelo</th>
                                <th scope="col">Producto</th>
                                <th scope="col" class="w-64">Flog</th>
                                <th scope="col">Descripción</th>
                                <th scope="col" class="w-28">Aplicación</th>
                                <th scope="col" class="w-24">Telar</th>
                                <th scope="col" class="w-28 text-right">Pedido</th>
                                <th scope="col" class="w-20 text-right">% 2as</th>
                                <th scope="col" class="w-28 text-right">Saldo</th>
                                <th scope="col" class="w-40">Observaciones</th>
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
                                            <div class="relative">
                                                <flux:input wire:model.live.debounce.250ms="{{ $base }}.clave" wire:blur="confirmar('clave', {{ $i }})"
                                                            maxlength="100" autocomplete="off" aria-label="Clave modelo fila {{ $i + 1 }}" />
                                                @if (($sugerencias['campo'] ?? null) === 'clave' && $sugerencias['i'] === $i)
                                                    {{-- mousedown.prevent: elegir sin disparar el blur del input. --}}
                                                    <ul role="listbox" aria-label="Sugerencias" class="absolute left-0 top-full z-20 mt-1 w-max min-w-full max-w-md max-h-60 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                                        @foreach ($sugerencias['items'] as $opcion)
                                                            <li wire:key="sug-clave-{{ $i }}-{{ $loop->index }}">
                                                                <button type="button" role="option" wire:mousedown.prevent="elegir('clave', {{ $i }}, @js($opcion))"
                                                                        class="w-full rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-blue-50 focus:bg-blue-50 focus:outline-none dark:hover:bg-zinc-700">{{ $opcion }}</button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>
                                            @error("$base.clave") <span class="mt-1 block text-caption text-red-600">{{ $message }}</span> @enderror
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['producto'] }}</span>
                                        @else
                                            <flux:input wire:model.blur="{{ $base }}.producto" maxlength="255" autocomplete="off" aria-label="Producto fila {{ $i + 1 }}" />
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['flog'] }}</span>
                                        @else
                                            {{-- El div relative ancla la lista justo debajo del input (en un <td> no es fiable). --}}
                                            <div class="relative">
                                                <flux:input wire:model.live.debounce.250ms="{{ $base }}.flog" wire:blur="confirmar('flog', {{ $i }})"
                                                            maxlength="100" autocomplete="off" aria-label="Flog fila {{ $i + 1 }}" />
                                                @if (($sugerencias['campo'] ?? null) === 'flog' && $sugerencias['i'] === $i)
                                                    {{-- mousedown.prevent: elegir sin disparar el blur del input. --}}
                                                    <ul role="listbox" aria-label="Sugerencias" class="absolute left-0 top-full z-20 mt-1 w-max min-w-full max-w-md max-h-60 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                                        @foreach ($sugerencias['items'] as $opcion)
                                                            <li wire:key="sug-flog-{{ $i }}-{{ $loop->index }}">
                                                                <button type="button" role="option" wire:mousedown.prevent="elegir('flog', {{ $i }}, @js($opcion))"
                                                                        class="w-full rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-blue-50 focus:bg-blue-50 focus:outline-none dark:hover:bg-zinc-700">{{ $opcion }}</button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fija)
                                            <span class="block py-2">{{ $fila['descripcion'] }}</span>
                                        @else
                                            <flux:input wire:model.blur="{{ $base }}.descripcion" maxlength="500" autocomplete="off" aria-label="Descripción fila {{ $i + 1 }}" />
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
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($dividir)
                                            <span class="block py-2 tabular-nums" title="(saldo + producción) / (1 + %2as)">{{ $numero(\App\Livewire\Planeacion\ProgramaTejido\FilasDestino::pedidoDerivado($fila)) }}</span>
                                        @else
                                            <flux:input wire:model.blur="{{ $base }}.pedido" inputmode="decimal" data-solo="decimal" data-decimales="2"
                                                        class:input="text-right tabular-nums" aria-label="Pedido fila {{ $i + 1 }}" />
                                        @endif
                                    </td>
                                    <td>
                                        <flux:input wire:model.blur="{{ $base }}.porcSeg" inputmode="decimal" data-solo="decimal" data-decimales="2"
                                                    class:input="text-right tabular-nums" aria-label="Porcentaje de segundas fila {{ $i + 1 }}" />
                                    </td>
                                    <td>
                                        <flux:input wire:model.blur="{{ $base }}.saldo" inputmode="decimal" data-solo="decimal" data-decimales="2"
                                                    class:input="text-right tabular-nums font-semibold" aria-label="Saldo fila {{ $i + 1 }}" />
                                    </td>
                                    <td>
                                        <flux:input wire:model.blur="{{ $base }}.observaciones" maxlength="500" autocomplete="off" aria-label="Observaciones fila {{ $i + 1 }}" />
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
                        {{-- Se anuncia al cambiar: el planeador ve cuánto falta sin buscar el botón. --}}
                        <p aria-live="polite" class="text-sm tabular-nums">
                            Disponible <strong>{{ $numero($cuadre['disponible']) }}</strong>
                            · Asignado <strong>{{ $numero($cuadre['asignado']) }}</strong>
                            @if ($cuadre['cuadra'])
                                · <span class="font-semibold text-green-700">Cuadra</span>
                            @elseif ($cuadre['diferencia'] < 0)
                                · <span class="font-semibold text-red-600">Faltan {{ $numero(-$cuadre['diferencia']) }}</span>
                            @else
                                · <span class="font-semibold text-red-600">Sobran {{ $numero($cuadre['diferencia']) }}</span>
                            @endif
                        </p>
                    @endif
                </div>

                @if ($aviso !== null || $errors->any())
                    <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
                        <flux:callout.text>
                            @if ($aviso !== null) <span class="block">{{ $aviso }}</span> @endif
                            @foreach ($errors->getMessages() as $campo => $mensajes)
                                @unless (str_ends_with($campo, '.clave'))
                                    <span class="block">{{ $mensajes[0] }}</span>
                                @endunless
                            @endforeach
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="ui-dialogo__botones">
                    <button type="button" class="ui-dialogo__boton ui-dialogo__boton--secundario"
                            x-on:click="$el.closest('dialog').close()">Cancelar</button>
                    {{-- Sin :disabled por cuadre: el render va un paso atrás del último blur y se comía el
                         primer clic. guardar() revisa el cuadre y deja el aviso en el diálogo. --}}
                    <button type="submit" @class(['ui-dialogo__boton ui-dialogo__boton--primario', 'bg-purple-600! hover:bg-purple-700!' => $modo === 'vincular', 'bg-green-600! hover:bg-green-700!' => $modo === 'dividir'])
                            wire:loading.attr="data-ocupado" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">{{ ['duplicar' => 'Duplicar', 'vincular' => 'Duplicar y vincular', 'dividir' => 'Dividir'][$modo] }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</div>
