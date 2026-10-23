{{-- Lista de sugerencias de Clave/Flog bajo el campo ($campo, $i); cada opción es ['valor', 'detalle']
     (la clave trae su talla y producto). mousedown.prevent en la lista: usar su scroll o elegir no le
     quita el foco al campo (antes perdía el foco, disparaba el change y la lista se cerraba).
     x-show="foco" la oculta al salir del campo sin ir al servidor. --}}
@if (($sugerencias['campo'] ?? null) === $campo && $sugerencias['i'] === $i)
    <ul role="listbox" aria-label="Sugerencias" x-show="foco" x-on:mousedown.prevent
        class="absolute left-0 top-full z-20 mt-1 w-max min-w-full max-w-md max-h-60 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
        @foreach ($sugerencias['items'] as $opcion)
            <li wire:key="sug-{{ $campo }}-{{ $i }}-{{ $loop->index }}">
                <button type="button" role="option" tabindex="-1" wire:click="elegir('{{ $campo }}', {{ $i }}, @js($opcion['valor']))"
                        class="w-full rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-blue-50 focus:bg-blue-50 focus:outline-none dark:hover:bg-zinc-700">
                    <span class="font-medium">{{ $opcion['valor'] }}</span>
                    @if ($opcion['detalle'] !== '')
                        <span class="block text-caption text-zinc-500 dark:text-zinc-400">{{ $opcion['detalle'] }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
@endif
