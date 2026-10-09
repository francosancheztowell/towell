{{-- Ventas: facturas de TwHistoricosVentas (ReportesTowel) por IDFLOG; notas de crédito ya restadas. --}}
@php
    $ventas ??= null;
    $monto = static fn (float $valor): string => number_format($valor, 0);
@endphp

<x-trazabilidad.tarjeta titulo="Ventas">
    @if ($ventas === null)
        <div class="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-10 text-center">
            <flux:icon.exclamation-triangle class="size-7 text-zinc-300" />
            <flux:text>Sin datos de ventas por ahora.</flux:text>
        </div>
    @elseif ($ventas['clientes'] === [])
        <div class="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-10 text-center">
            <flux:icon.receipt-percent class="size-7 text-zinc-300" />
            <flux:text>Todavía no hay facturas de este pedido.</flux:text>
        </div>
    @else
        <dl class="grid grid-cols-1 border-b border-zinc-100 sm:grid-cols-3">
            <div class="px-4 py-3 sm:border-r sm:border-zinc-100">
                <dt class="text-xs font-medium text-zinc-500">Vendido</dt>
                @foreach ($ventas['cantidades'] as $unidad => $cantidad)
                    <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900">
                        {{ $monto($cantidad) }} <span class="text-sm font-medium text-zinc-500">{{ $unidad }}</span>
                    </dd>
                @endforeach
            </div>
            <div class="px-4 py-3 sm:border-r sm:border-zinc-100">
                <dt class="text-xs font-medium text-zinc-500">Importe neto</dt>
                @foreach ($ventas['importes'] as $moneda => $importe)
                    <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900">
                        ${{ $monto($importe) }} <span class="text-sm font-medium text-zinc-500">{{ $moneda }}</span>
                    </dd>
                @endforeach
            </div>
            <div class="px-4 py-3">
                <dt class="text-xs font-medium text-zinc-500">Última factura</dt>
                <dd class="mt-0.5 text-sm font-semibold tabular-nums text-zinc-800">{{ $ventas['ultima'] ?? '—' }}</dd>
                <dd class="text-xs text-zinc-500">{{ $ventas['totalClientes'] }} {{ $ventas['totalClientes'] === 1 ? 'cliente' : 'clientes' }}</dd>
            </div>
        </dl>

        <div class="flex-1 overflow-x-auto px-4 py-3">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Cliente</flux:table.column>
                    <flux:table.column align="end">Cantidad</flux:table.column>
                    <flux:table.column align="end">Importe</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($ventas['clientes'] as $cliente)
                        <flux:table.row>
                            <flux:table.cell class="max-w-48 truncate" title="{{ $cliente['cliente'] }}">{{ $cliente['cliente'] }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $monto($cliente['cantidad']) }} {{ $cliente['unidad'] }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">${{ $monto($cliente['importe']) }} {{ $cliente['moneda'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</x-trazabilidad.tarjeta>
