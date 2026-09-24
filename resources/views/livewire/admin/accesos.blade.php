{{-- Panel /admin · Accesos (MON-27) y acciones de admin (MON-28). --}}
<div>
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             vacio="No hay accesos en ese periodo"
             vacio-icono="fa-door-open"
             buscar-placeholder="Buscar número, usuario, IP o motivo…">
        <x-slot:filtros>
            <select wire:model.live="tipo" aria-label="Filtrar por tipo" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[12rem] sm:flex-none">
                <option value="">Tipo: todos</option>
                @foreach ($tipos as $t)
                    <option value="{{ $t }}">{{ str_replace('_', ' ', $t) }}</option>
                @endforeach
            </select>
            @include('livewire.admin.partials.fechas')
        </x-slot:filtros>
    </x-tabla>
</div>
