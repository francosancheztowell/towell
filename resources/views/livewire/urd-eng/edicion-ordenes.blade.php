<div class="edicion-ordenes">
    @teleport('#tabla-navbar-acciones')
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="editar" wire:loading.attr="disabled" @disabled(! $ordenSeleccionada)
                class="edicion-action bg-blue-600 text-white" title="Editar orden seleccionada">
                <i class="fa-solid fa-pen-to-square"></i><span>Editar</span>
            </button>
            @if ($module === 'engomado')
            <button type="button" wire:click="calificar" wire:loading.attr="disabled"
                @disabled(! $ordenSeleccionada || $ordenSeleccionada->Status !== 'Finalizado')
                class="edicion-action bg-purple-600 text-white" title="Calificar julios: requiere una orden finalizada">
                <i class="fa-solid fa-clipboard-check"></i><span>Calificar julios</span>
            </button>
            @endif
            @if ($ordenSeleccionada && $ordenSeleccionada->Status === 'Finalizado')
                @if ($module === 'urdido')
                    <a wire:loading.class="pointer-events-none opacity-50" class="edicion-action bg-slate-700 text-white" target="_blank" rel="noopener"
                        href="{{ route('urdido.reimpresion.urdido.ventana.imprimir', ['orden_id' => $ordenSeleccionada->Id]) }}" title="Imprimir PDF de Urdido">
                        <i class="fa-solid fa-file-pdf"></i><span>PDF</span>
                    </a>
                @else
                @foreach ([false => ['PDF', 'fa-file-pdf'], true => ['Excel simplificado', 'fa-file-excel']] as $simplificado => [$titulo, $icono])
                    <button type="button" wire:loading.attr="disabled" class="edicion-action bg-slate-700 text-white" title="{{ $titulo }}"
                        data-engomado-pdf="{{ route('engomado.modulo.produccion.engomado.pdf', ['orden_id' => $ordenSeleccionada->Id, 'tipo' => 'engomado', 'reimpresion' => 1, 'simplificado' => $simplificado]) }}">
                        <i class="fa-solid {{ $icono }}"></i><span>{{ $titulo }}</span>
                    </button>
                @endforeach
                @endif
            @endif

        </div>
    @endteleport
    <section class="edicion-filters" aria-label="Filtros de órdenes">
        <label class="edicion-filter"><span>Folio</span><input wire:model.live.debounce.300ms="folio" maxlength="80" placeholder="Todos"></label>
        <label class="edicion-filter"><span>Tipo</span><select wire:model.live="tipo"><option value="">Todos</option>
            @foreach ($tipos as $opcion)<option value="{{ $opcion }}">{{ $opcion }}</option>@endforeach
        </select></label>
        <label class="edicion-filter"><span>Estado</span><select wire:model.live="status"><option value="">Todos</option>
            @foreach (['Programado', 'En Proceso', 'Parcial', 'Finalizado', 'Cancelado'] as $opcion)<option value="{{ $opcion }}">{{ $opcion }}</option>@endforeach
        </select></label>
        <button type="button" wire:click="limpiarFiltros" class="edicion-action border border-slate-300" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i>Limpiar</button>
    </section>
    <div class="edicion-summary" role="status">
        <span><strong>{{ number_format($total) }}</strong> órdenes · <strong>{{ count($boards) }}</strong> máquinas</span>
        @if ($ordenSeleccionada)
            <span class="edicion-selected">Seleccionada: <strong>{{ $ordenSeleccionada->Folio }}</strong> · {{ $ordenSeleccionada->{\App\Support\Programas\ProgramaModulo::resolve($module)->machineColumn()} }}</span>
        @else
            <span>Selecciona una orden; doble clic para editar.</span>
        @endif
        <span wire:loading.delay class="text-blue-600">Actualizando…</span>
    </div>
    @error('accion')<p role="alert" class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
    <div class="edicion-machine-grid {{ count($boards) === 1 ? 'is-single' : '' }}">
        @forelse ($boards as $board)
            <section class="edicion-machine" wire:key="machine-{{ $module }}-{{ $board['key'] }}" aria-label="{{ $board['label'] }}">
                <header class="edicion-machine-header">
                    <h2><i class="fa-solid fa-industry" aria-hidden="true"></i>{{ $board['label'] }}</h2>
                    <span>{{ number_format($board['filas']->total()) }} órdenes</span>
                </header>
                <x-tabla :columnas="$this->columnas()" :filas="$board['filas']" :seleccionado="$seleccionado"
                    :orden-por="$ordenPor" :orden-dir="$ordenDir" al-editar="editar" :seleccion-inmediata="true"
                    :mostrar-filtros="false" :mostrar-tamano-pagina="false"
                    objetivos-extra="folio,tipo,status,limpiarFiltros" vacio="Sin órdenes con estos filtros" />
            </section>
        @empty
            <p class="edicion-no-machines">No hay órdenes disponibles. Cambia los filtros o pulsa Limpiar.</p>
        @endforelse
    </div>
</div>
