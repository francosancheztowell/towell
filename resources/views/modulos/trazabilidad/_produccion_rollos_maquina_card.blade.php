{{-- Tarjeta por máquina (Rollos Teñido): abre el modal con el detalle por orden. --}}
<button type="button"
        class="prod-rollos-maquina-card group flex min-h-touch w-full flex-col gap-3 rounded-xl border border-zinc-900/10 bg-white p-4 text-left shadow-xs transition hover:border-blue-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
        data-maquina="{{ $m['maquina'] }}"
        data-filas='@json($m['filas'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)'>
    <span class="flex items-start justify-between gap-2">
        <span class="min-w-0">
            <span class="block text-2xl font-semibold leading-none tracking-tight text-zinc-900">{{ $m['maquina'] }}</span>
            <span class="mt-1.5 block text-sm text-zinc-500">{{ $m['ordenes'] }} {{ $m['ordenes'] === 1 ? 'orden' : 'órdenes' }}</span>
        </span>
        <flux:icon.arrows-pointing-out variant="micro" class="shrink-0 text-zinc-400 group-hover:text-blue-600" />
    </span>
    <span class="grid grid-cols-2 gap-2 rounded-lg bg-zinc-50 px-3 py-2">
        <span>
            <span class="block text-xs text-zinc-500">Piezas</span>
            <span class="block text-base font-semibold tabular-nums text-zinc-900">{{ number_format($m['cantidad']) }}</span>
        </span>
        <span>
            <span class="block text-xs text-zinc-500">Kilos</span>
            <span class="block text-base font-semibold tabular-nums text-zinc-900">{{ number_format($m['peso'], 2) }}</span>
        </span>
    </span>
</button>
