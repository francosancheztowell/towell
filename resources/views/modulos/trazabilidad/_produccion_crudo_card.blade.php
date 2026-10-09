@php
    $esMultiTelar = (bool) ($o['esMultiTelar'] ?? false);
    $estado = $o['estado'] ?? 'terminado';
    $activa = $estado === 'activo';
    $meses = implode(', ', $o['meses'] ?? []);
    $pzasDia = $o['pzasDia'] ?? ($o['programa']['stdDia'] ?? null);
    $kgDia = $o['prodKgDia'] ?? ($o['programa']['prodKgDia'] ?? null);
    $ritmoReal = (bool) ($o['ritmoReal'] ?? false);
    $avance = (float) ($o['avance'] ?? 0);
    $avanceBarra = min(100, max(0, $avance));
    $entero = static fn ($valor): string => $valor === null ? '—' : number_format((int) round((float) $valor));
    // El servicio ya formatea d/m/y sin hora. Programa Tejido primero; si la orden ya salió de ahí, CatCodificados.
    $inicio = $o['programa']['fechaInicio'] ?? $o['codificados']['fechaInicio'] ?? null;
    $final = $o['programa']['fechaFinal'] ?? $o['codificados']['fechaFinal'] ?? null;
    $telares = $o['telares'] ?? [];
@endphp

<flux:card class="prod-crudo-card flex flex-col overflow-hidden !p-0" data-estado="{{ $estado }}">
    <header class="flex items-start justify-between gap-3 px-4 pt-4">
        <div class="min-w-0">
            <flux:heading size="lg" level="4" class="tabular-nums">Orden {{ $o['orden'] }}</flux:heading>
            @if ($meses !== '')
                <flux:text class="text-xs">{{ $meses }}</flux:text>
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap justify-end gap-1">
            <flux:badge size="sm" :color="$activa ? 'emerald' : 'zinc'">{{ $activa ? 'Activa' : 'Terminada' }}</flux:badge>
            @if ($esMultiTelar)
                <flux:badge size="sm" color="blue">{{ $o['cantidadTelares'] }} telares</flux:badge>
            @endif
        </div>
    </header>

    {{-- Cada cifra con su etiqueta; Producidas va más grande porque es lo que se revisa primero. --}}
    <dl class="mx-4 mt-3 grid grid-cols-3 divide-x divide-zinc-200">
        <div class="pr-3">
            <dt class="truncate text-xs text-zinc-500" title="Piezas producidas">Producidas</dt>
            <dd class="text-2xl font-semibold tabular-nums text-zinc-900">{{ $entero($o['producidasTotal'] ?? 0) }}</dd>
        </div>
        <div class="px-3">
            <dt class="truncate text-xs text-zinc-500" title="Piezas programadas">Programadas</dt>
            <dd class="pt-1.5 text-lg font-medium tabular-nums text-zinc-700">{{ $entero($o['programadas'] ?? 0) }}</dd>
        </div>
        <div class="pl-3">
            <dt class="truncate text-xs text-zinc-500">Kg total</dt>
            <dd class="pt-1.5 text-lg font-medium tabular-nums text-zinc-700">{{ $entero($o['pesoTotal'] ?? 0) }}</dd>
        </div>
    </dl>

    <dl class="mx-4 mt-3 grid grid-cols-2 divide-x divide-zinc-200 rounded-lg bg-zinc-50 py-2.5">
        <div class="min-w-0 px-3">
            <dt class="flex items-center gap-1 text-xs text-zinc-500">
                <flux:icon.calendar-days variant="micro" class="text-zinc-400" />Inicio → Final
            </dt>
            <dd class="truncate text-sm font-medium tabular-nums text-zinc-800">{{ $inicio === null && $final === null ? '—' : ($inicio ?? '—').' → '.($final ?? '—') }}</dd>
        </div>
        <div class="min-w-0 px-3">
            <dt class="flex flex-wrap items-center gap-1 text-xs text-zinc-500">
                <flux:icon.bolt variant="micro" class="text-zinc-400" />Pzas/día · Kg/día
            </dt>
            <dd class="truncate text-sm font-medium tabular-nums text-zinc-800">{{ $pzasDia === null && $kgDia === null ? '—' : $entero($pzasDia).' · '.$entero($kgDia) }}
                @if ($ritmoReal)
                    <span class="text-xs font-normal text-zinc-400" title="Promedio real entre arranque y finalización: la orden ya no está en Programa Tejido.">prom.</span>
                @endif
            </dd>
        </div>
    </dl>

    @if ($telares !== [])
        {{-- Telares en columnas, Pzas y Kg en renglones (así lo prefiere planta). --}}
        <div class="traza-scroll mx-4 mt-3 overflow-x-auto">
            <table class="prod-crudo-card__loom-matrix w-full text-sm tabular-nums">
                <thead>
                    <tr>
                        <th scope="col"><span class="sr-only">Medida</span></th>
                        @foreach ($telares as $telar)
                            <th scope="col" data-loom-column title="Telar {{ $telar['telarNumero'] }}">T {{ $telar['telarNumero'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">Pzas</th>
                        @foreach ($telares as $telar)
                            <td>{{ number_format((int) $telar['producidas']) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Kg</th>
                        @foreach ($telares as $telar)
                            <td>{{ number_format((int) round((float) $telar['kg'])) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="prod-crudo-card__progress mt-auto border-t border-zinc-100 px-4 py-3">
        <div class="mb-1.5 flex items-baseline justify-between">
            <span class="text-xs text-zinc-500">Avance</span>
            <strong class="text-lg font-semibold tabular-nums text-zinc-900">{{ number_format($avance, 1) }}%</strong>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100" role="progressbar"
             aria-label="Avance de la orden {{ $o['orden'] }}" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="{{ $avanceBarra }}">
            <div @class(['prod-crudo-card__progress-bar h-full rounded-full', 'bg-emerald-500' => $activa, 'bg-blue-500' => ! $activa])
                 style="width: {{ $avanceBarra }}%"></div>
        </div>
    </div>
</flux:card>
