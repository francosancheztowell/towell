{{-- Panel /admin · Errores agrupados por huella (MON-25). Doble clic / Enter abre el detalle. --}}
<div>
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="verDetalle"
             vacio="Sin errores con esos filtros"
             vacio-icono="fa-bug-slash"
             buscar-placeholder="Buscar clase, mensaje, ruta o archivo…">
        <x-slot:acciones>
            <x-navbar.button-edit wire:click="verDetalle" icon="fa-magnifying-glass" text="Ver detalle"
                                  :disabled="$seleccionado === null"
                                  title="{{ $seleccionado === null ? 'Selecciona un error' : 'Ver detalle y cambiar estado' }}" />
        </x-slot:acciones>
        <x-slot:filtros>
            <select wire:model.live="estado" aria-label="Filtrar por estado" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[12rem] sm:flex-none">
                <option value="abiertos">Abiertos (nuevo y visto)</option>
                <option value="todos">Todos</option>
                @foreach ($estados as $e)
                    <option value="{{ $e }}">{{ ucfirst($e) }}</option>
                @endforeach
            </select>
            <select wire:model.live="origen" aria-label="Filtrar por origen" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[12rem] sm:flex-none">
                <option value="">Origen: todos</option>
                @foreach ($origenes as $o)
                    <option value="{{ $o }}">{{ $o }}</option>
                @endforeach
            </select>
        </x-slot:filtros>
    </x-tabla>
</div>
