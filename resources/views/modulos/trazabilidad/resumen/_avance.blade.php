<x-trazabilidad.tarjeta titulo="Programa tejido" detalle="produccion">
    @if (empty($tablaAvancePedido))
        <div class="flex flex-1 items-center justify-center px-6 py-10 text-center">
            <flux:text>Sin órdenes de tejido para estos filtros.</flux:text>
        </div>
    @else
        <flux:table class="traza-tabla" container:class="traza-tabla-limitada" data-tabla-avance-pedido>
            <flux:table.columns sticky>
                <flux:table.column>Flog</flux:table.column>
                <flux:table.column>Orden</flux:table.column>
                <flux:table.column>Tam.</flux:table.column>
                <flux:table.column>Telar</flux:table.column>
                <flux:table.column align="end">Progr.</flux:table.column>
                <flux:table.column align="end">Saldo</flux:table.column>
                <flux:table.column align="center">Inicio</flux:table.column>
                <flux:table.column align="center">Fin</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tablaAvancePedido as $fila)
                    <flux:table.row>
                        <flux:table.cell class="font-medium text-zinc-800">{{ $fila['flog'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($fila['enProceso'])
                                <flux:badge size="sm" color="emerald" class="font-mono" title="Orden en proceso">{{ $fila['orden'] }}</flux:badge>
                            @else
                                <span class="font-mono text-zinc-700">{{ $fila['orden'] }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $fila['tamano'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $fila['telar'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format($fila['programado'], 0) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums text-zinc-900">{{ number_format($fila['restante'], 0) }}</flux:table.cell>
                        <flux:table.cell align="center" class="tabular-nums">{{ $fila['inicio'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="center" class="tabular-nums">{{ $fila['fin'] ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</x-trazabilidad.tarjeta>
