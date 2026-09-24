{{-- Filtro desde/hasta del trait App\Livewire\Admin\Concerns\FiltroFechas. --}}
<label class="flex items-center gap-1 text-xs font-semibold text-slate-500">
    Desde
    <input type="date" wire:model.live="desde" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
</label>
<label class="flex items-center gap-1 text-xs font-semibold text-slate-500">
    Hasta
    <input type="date" wire:model.live="hasta" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
</label>
