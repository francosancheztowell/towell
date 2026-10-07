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
            <flux:select wire:model.live="tipo" size="sm" aria-label="Filtrar por tipo" class="sm:w-52">
                <flux:select.option value="">Tipo: todos</flux:select.option>
                @foreach ($tipos as $t)
                    <flux:select.option :value="$t">{{ str_replace('_', ' ', $t) }}</flux:select.option>
                @endforeach
            </flux:select>
            @include('livewire.admin.partials.fechas')
        </x-slot:filtros>
    </x-tabla>
</div>
