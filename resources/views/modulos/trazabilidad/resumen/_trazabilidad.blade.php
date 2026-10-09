@php $areasTrazabilidad = collect($resumen['trazabilidadAreas'] ?? []); @endphp

<x-trazabilidad.tarjeta titulo="Trazabilidad" detalle="trazabilidad">
    <flux:table class="traza-tabla" container:class="traza-tabla-limitada">
        <flux:table.columns sticky>
            <flux:table.column>Área</flux:table.column>
            <flux:table.column align="end">Piezas</flux:table.column>
            <flux:table.column align="end">Kilos</flux:table.column>
            <flux:table.column align="center">Inicio</flux:table.column>
            <flux:table.column align="center">Fin</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($areasTrazabilidad as $area)
                <flux:table.row>
                    <flux:table.cell>
                        <span class="inline-flex items-center gap-2 font-medium text-zinc-800">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $area['dot'] }}" aria-hidden="true"></span>
                            {{ $area['area'] }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-semibold tabular-nums text-zinc-900">
                        {{ is_null($area['piezas']) ? '—' : number_format($area['piezas'], 0) }}
                    </flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">
                        {{ is_null($area['kilos']) ? '—' : number_format($area['kilos'], 1) }}
                    </flux:table.cell>
                    <flux:table.cell align="center" class="tabular-nums">{{ $area['fechaInicio'] }}</flux:table.cell>
                    <flux:table.cell align="center" class="tabular-nums">{{ $area['fechaFin'] }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</x-trazabilidad.tarjeta>
