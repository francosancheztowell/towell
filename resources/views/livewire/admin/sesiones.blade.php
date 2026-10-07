{{-- Panel /admin · Sesiones (MON-23). --}}
<div>
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             vacio="No hay sesiones en ese periodo"
             vacio-icono="fa-clock-rotate-left"
             buscar-placeholder="Buscar usuario, número, dispositivo o IP…">
        <x-slot:filtros>
            <flux:select wire:model.live="abiertas" size="sm" aria-label="Filtrar sesiones abiertas" class="sm:w-52">
                <flux:select.option value="">Abiertas y cerradas</flux:select.option>
                <flux:select.option value="abiertas">Solo abiertas</flux:select.option>
                <flux:select.option value="cerradas">Solo cerradas</flux:select.option>
            </flux:select>
            @include('livewire.admin.partials.fechas')
            @if ($dispositivo !== '')
                <flux:button size="sm" variant="ghost" icon:trailing="x-mark" wire:click="$set('dispositivo', '')"
                             aria-label="Quitar el filtro del dispositivo #{{ $dispositivo }}">Dispositivo #{{ $dispositivo }}</flux:button>
            @endif
        </x-slot:filtros>
    </x-tabla>
</div>
