<div id="trazabilidad-livewire" data-trazabilidad-livewire>
    <form wire:submit.prevent
          class="bg-white border border-slate-200 rounded-2xl shadow-sm p-2.5 md:p-3 mb-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-3">
            <div>
                <label for="filtro-flog" class="block text-xs font-semibold text-slate-500 mb-0.5">Flog</label>
                <select id="filtro-flog"
                        data-livewire-filter="flog"
                        class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach ($opcionesFlog as $option)
                        <option value="{{ $option }}" @selected($flog === (string) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtro-articulo" class="block text-xs font-semibold text-slate-500 mb-0.5">Artículo</label>
                <select id="filtro-articulo"
                        data-livewire-filter="articulo"
                        class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach ($opcionesArticulo as $option)
                        <option value="{{ $option['codigo'] }}" @selected($articulo === (string) $option['codigo'])>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtro-tamano" class="block text-xs font-semibold text-slate-500 mb-0.5">Tamaño</label>
                <select id="filtro-tamano"
                        data-livewire-filter="tamano"
                        class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach ($opcionesTamano as $option)
                        <option value="{{ $option }}" @selected($tamano === (string) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <input type="hidden" id="filtro-color" value="{{ $color }}">
        <input type="hidden" id="filtro-mes" value="{{ $mes }}">
        <input type="hidden" id="filtro-metrica" value="{{ $metrica }}">
    </form>

    <div class="relative">
        <div wire:loading.flex
             wire:target="actualizarFiltro,restablecer"
             class="absolute inset-0 z-20 items-start justify-center rounded-2xl bg-white/65 pt-8 backdrop-blur-[1px]">
            <span class="inline-flex items-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-lg">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
                Actualizando trazabilidad
            </span>
        </div>

        <div id="resultado-resumen-livewire" wire:loading.class="opacity-60">
            @include('modulos.trazabilidad._resultado')
        </div>
    </div>

</div>
