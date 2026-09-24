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
            <select wire:model.live="abiertas" aria-label="Filtrar sesiones abiertas" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[12rem] sm:flex-none">
                <option value="">Abiertas y cerradas</option>
                <option value="abiertas">Solo abiertas</option>
                <option value="cerradas">Solo cerradas</option>
            </select>
            @include('livewire.admin.partials.fechas')
            @if ($dispositivo !== '')
                <button type="button" wire:click="$set('dispositivo', '')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-bold text-slate-600 hover:bg-red-50 hover:text-red-600">
                    <i class="fa-solid fa-xmark"></i> Dispositivo #{{ $dispositivo }}
                </button>
            @endif
        </x-slot:filtros>
    </x-tabla>
</div>
