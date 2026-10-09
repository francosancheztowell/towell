{{--
    Detalle de Ventas (TwHistoricosVentas por IDFLOG), pensado para dirección: cuánto se facturó,
    a qué ritmo, cuánto vale lo que falta y de quién viene el dinero.
    @var array|null $ventas  TrazabilidadVentasService::detalle(); null = sin conexión con ReportesTowel
    @var float|null $pedido  pedido de AX (en la unidad de la venta) para el valor por facturar
--}}
@php
    $num = static fn (float $valor): string => number_format($valor, 0);
    $fecha = static fn (?string $ymd): string => $ymd ? \Carbon\Carbon::parse($ymd)->format('d/m/Y') : '—';
    // $18.9 M / $950 K: las cifras grandes se leen de un vistazo.
    $corto = static fn (float $v): string => '$'.(abs($v) >= 1e6 ? number_format($v / 1e6, 1).' M' : (abs($v) >= 1e3 ? number_format($v / 1e3, 0).' K' : number_format($v, 0)));
@endphp

@if ($ventas === null)
    <flux:card class="flex flex-col items-center gap-2 px-6 py-14 text-center">
        <flux:icon.exclamation-triangle class="size-8 text-zinc-300" />
        <flux:text>No se pudieron leer las ventas. Intenta de nuevo en unos minutos.</flux:text>
    </flux:card>
@elseif ($ventas['facturas'] === [])
    <flux:card class="flex flex-col items-center gap-2 px-6 py-14 text-center">
        <flux:icon.receipt-percent class="size-8 text-zinc-300" />
        <flux:text>Todavía no hay facturas de este pedido.</flux:text>
    </flux:card>
@else
    @php
        $t = $ventas['totales'];
        $unidad = $ventas['unidad'];
        $moneda = $ventas['moneda'];
        $precio = $ventas['precio'];
        $importe = $t['importes'][$moneda] ?? ['bruto' => 0, 'descuento' => 0, 'neto' => 0];
        $producido = (float) $t['producido'];

        // --- Valor del pedido: facturado + lo que falta valuado al precio neto promedio ---
        $pedido = $pedido !== null && $pedido > 0 && $unidad === 'pza' ? (float) $pedido : null;
        $avance = $pedido ? $t['vendido'] / $pedido * 100 : null;
        $pendientePza = $pedido ? max(0, $pedido - $t['vendido']) : 0;
        $pendienteValor = $precio ? $pendientePza * $precio : 0;
        $valorTotal = max(1, $importe['neto'] + $pendienteValor);
        $pctFacturado = min(100, max(0, $importe['neto'] / $valorTotal * 100));

        // --- Facturación por mes (SVG) ---
        $meses = $ventas['meses'];
        $n = count($meses);
        [$W, $H, $L, $R, $T, $B] = [640, 250, 12, 12, 26, 44];
        $maxMes = max(1, ...array_map(static fn (array $m): float => $m['neto'], $meses));
        $slot = ($W - $L - $R) / $n;
        $barra = min(56, $slot * 0.7);
        $alto = $H - $T - $B;
        $cadaEtiqueta = (int) ceil($n / 12);
        $conValores = $n <= 12;

        $clientes = $ventas['clientes'];
        $topClientes = array_slice($clientes, 0, 5);
        $otros = array_sum(array_column(array_slice($clientes, 5), 'neto'));
        $netoClientes = max(1, array_sum(array_column($clientes, 'neto')));

        $tarjetas = [
            ['Vendido', $num($t['vendido']), $unidad, $producido > 0 ? 'de '.$num($producido).' producidas' : null, 'cube', 'bg-emerald-50 ring-emerald-200', 'bg-emerald-600'],
            ['Importe neto', $corto($importe['neto']), $moneda, $importe['descuento'] > 0 && $importe['bruto'] > 0 ? 'descuento '.number_format($importe['descuento'] / $importe['bruto'] * 100, 1).' %' : null, 'banknotes', 'bg-blue-50 ring-blue-200', 'bg-blue-600'],
            ['Facturas', (string) $t['facturas'], '', $t['notasCredito'] > 0 ? $t['notasCredito'].' '.($t['notasCredito'] === 1 ? 'nota' : 'notas').' de crédito · '.$num($t['devuelto']).' '.$unidad : null, 'document-text', 'bg-violet-50 ring-violet-200', 'bg-violet-600'],
            ['Precio neto prom.', $precio ? '$'.number_format($precio, 2) : '—', $precio ? 'por '.$unidad : '', null, 'tag', 'bg-amber-50 ring-amber-200', 'bg-amber-500'],
            ['Periodo', $fecha($t['primera']), '', $t['primera'] ? 'al '.$fecha($t['ultima']).' · '.\Carbon\Carbon::parse($t['primera'])->diffInDays(\Carbon\Carbon::parse($t['ultima'])).' días' : null, 'calendar-days', 'bg-sky-50 ring-sky-200', 'bg-sky-600'],
        ];
    @endphp

    <div class="space-y-4">
        {{-- Cifras clave: cada tarjeta con su tono; el texto queda en tinta para leerse bien. --}}
        <dl class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($tarjetas as [$etiqueta, $valor, $sufijo, $nota, $icono, $fondo, $chip])
                <div class="flex items-start gap-3 rounded-xl p-4 ring-1 ring-inset {{ $fondo }}">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg text-white {{ $chip }}" aria-hidden="true">
                        <flux:icon :name="$icono" variant="mini" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-zinc-600">{{ $etiqueta }}</dt>
                        <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 md:text-2xl">{{ $valor }} @if ($sufijo)<span class="text-sm font-medium text-zinc-500">{{ $sufijo }}</span>@endif</dd>
                        @if ($nota)
                            <dd @class(['text-xs', 'font-medium text-red-700' => str_contains($nota, 'crédito'), 'text-zinc-600' => ! str_contains($nota, 'crédito')])>{{ $nota }}</dd>
                        @endif
                    </div>
                </div>
            @endforeach
        </dl>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
            {{-- ¿Cuánto entra por mes? --}}
            <flux:card class="!p-4 xl:col-span-3">
                <flux:heading size="lg" level="3">Facturación por mes</flux:heading>

                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="mt-3 h-auto w-full" role="img"
                     aria-label="Facturación por mes: {{ collect($meses)->map(fn ($m) => \Carbon\Carbon::parse($m['mes'].'-01')->locale('es')->translatedFormat('F Y').' '.$corto($m['neto']))->implode(', ') }}.">
                    <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $T + $alto }}" y2="{{ $T + $alto }}" stroke="#d4d4d8" stroke-width="1" />
                    @foreach ($meses as $i => $m)
                        @php
                            $h = max(0, $m['neto']) / $maxMes * $alto;
                            $cx = $L + $slot * ($i + 0.5);
                            $etiquetaMes = \Carbon\Carbon::parse($m['mes'].'-01')->locale('es')->translatedFormat('M y');
                        @endphp
                        <g>
                            <title>{{ ucfirst(\Carbon\Carbon::parse($m['mes'].'-01')->locale('es')->translatedFormat('F Y')) }}: ${{ $num($m['neto']) }} {{ $moneda }} · {{ $num($m['cantidad']) }} {{ $unidad }}</title>
                            <rect x="{{ round($cx - $slot / 2, 1) }}" y="{{ $T }}" width="{{ round($slot, 1) }}" height="{{ $alto + $B }}" fill="transparent" />
                            @if ($h > 0)
                                <path d="M{{ round($cx - $barra / 2, 1) }},{{ round($T + $alto, 1) }} V{{ round($T + $alto - $h + 4, 1) }} q0,-4 4,-4 H{{ round($cx + $barra / 2 - 4, 1) }} q4,0 4,4 V{{ round($T + $alto, 1) }} Z" fill="#059669" />
                            @endif
                            @if ($conValores)
                                <text x="{{ round($cx, 1) }}" y="{{ round($T + $alto - $h - 6, 1) }}" text-anchor="middle" class="fill-zinc-800 text-[11px] font-semibold tabular-nums">{{ $corto($m['neto']) }}</text>
                            @endif
                            @if ($i % $cadaEtiqueta === 0)
                                <text x="{{ round($cx, 1) }}" y="{{ $T + $alto + 16 }}" text-anchor="middle" class="fill-zinc-600 text-[11px]">{{ $etiquetaMes }}</text>
                                @if ($conValores)
                                    <text x="{{ round($cx, 1) }}" y="{{ $T + $alto + 31 }}" text-anchor="middle" class="fill-zinc-400 text-[10px] tabular-nums">{{ $num($m['cantidad']) }} {{ $unidad }}</text>
                                @endif
                            @endif
                        </g>
                    @endforeach
                </svg>
            </flux:card>

            {{-- ¿Cuánto vale el pedido, cuánto ya se cobró y cuánto falta? --}}
            <flux:card class="flex flex-col !p-4 xl:col-span-2">
                <flux:heading size="lg" level="3">Valor del pedido</flux:heading>

                @if ($pedido)
                    <div class="mt-3 flex items-end gap-3">
                        <span class="text-4xl font-semibold tabular-nums text-zinc-900">{{ number_format($avance, 0) }} %</span>
                        <span class="pb-1 text-sm text-zinc-600">del pedido facturado<br><span class="tabular-nums">{{ $num($t['vendido']) }} de {{ $num($pedido) }} {{ $unidad }}</span></span>
                    </div>

                    <div class="mt-4 flex h-7 gap-0.5 overflow-hidden rounded-md bg-zinc-100" role="img"
                         aria-label="Facturado {{ $corto($importe['neto']) }}, por facturar {{ $corto($pendienteValor) }} estimado">
                        <div class="h-full rounded-s-md bg-emerald-600" style="width: {{ $pctFacturado }}%"></div>
                        @if ($pendienteValor > 0)
                            <div class="h-full flex-1 rounded-e-md bg-amber-400"></div>
                        @endif
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-600"><span class="size-2 rounded-full bg-emerald-600"></span>Facturado</dt>
                            <dd class="text-lg font-semibold tabular-nums text-zinc-900">${{ $num($importe['neto']) }}</dd>
                        </div>
                        <div>
                            <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-600"><span class="size-2 rounded-full bg-amber-400"></span>Por facturar (estimado)</dt>
                            <dd class="text-lg font-semibold tabular-nums text-zinc-900">${{ $num($pendienteValor) }}</dd>
                            <dd class="text-xs text-zinc-500 tabular-nums">{{ $num($pendientePza) }} {{ $unidad }} × ${{ number_format((float) $precio, 2) }}</dd>
                        </div>
                    </dl>
                    @if ($avance > 100)
                        <flux:text class="mt-2 text-xs">Se facturó {{ $num($t['vendido'] - $pedido) }} {{ $unidad }} más que el pedido de AX.</flux:text>
                    @endif
                @else
                    <div class="mt-3">
                        <span class="text-3xl font-semibold tabular-nums text-zinc-900">${{ $num($importe['neto']) }}</span>
                        <span class="text-sm text-zinc-500">{{ $moneda }} facturado</span>
                        <flux:text class="mt-1 text-xs">Sin pedido de AX para calcular lo que falta.</flux:text>
                    </div>
                @endif

                {{-- ¿De quién viene el dinero? Solo tiene sentido con más de un cliente. --}}
                <div class="mt-5 border-t border-zinc-100 pt-4">
                    @if (count($clientes) > 1)
                        <h4 class="text-sm font-semibold text-zinc-800">Reparto por cliente</h4>
                        <ul class="mt-2 space-y-2">
                            @foreach ([...$topClientes, ...($otros > 0 ? [['cliente' => 'Otros '.(count($clientes) - 5), 'neto' => $otros]] : [])] as $c)
                                @php $pct = max(0, $c['neto'] / $netoClientes * 100); @endphp
                                <li>
                                    <div class="flex items-baseline justify-between gap-3 text-xs">
                                        <span class="min-w-0 truncate text-zinc-700" title="{{ $c['cliente'] }}">{{ $c['cliente'] }}</span>
                                        <span class="shrink-0 tabular-nums text-zinc-600"><span class="font-semibold text-zinc-900">{{ $pct > 0 && $pct < 1 ? "<1" : number_format($pct, 0) }} %</span> · {{ $corto($c['neto']) }}</span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-zinc-100"><div class="h-2 rounded-full bg-blue-600" style="width: {{ min(100, $pct) }}%"></div></div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <dt class="text-xs font-medium text-zinc-600">Cliente único</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-zinc-900">{{ $clientes[0]['cliente'] ?? '—' }}</dd>
                    @endif
                </div>
            </flux:card>
        </div>

        {{-- Facturas (también es la vista en tabla de la gráfica mensual) --}}
        <flux:card class="!p-4">
            <div class="flex items-baseline justify-between gap-3">
                <flux:heading size="lg" level="3">Facturas</flux:heading>
                <flux:text class="text-xs">{{ count($ventas['facturas']) }} documentos, la más reciente primero</flux:text>
            </div>
            {{-- La altura va en el contenedor de flux:table: así su propia área de scroll es la que
                 se desplaza y el encabezado sticky se queda arriba (con un div externo no se fija). --}}
            <div class="mt-2">
                <flux:table container:class="traza-scroll max-h-[28rem]">
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>Fecha</flux:table.column>
                        <flux:table.column>Folio</flux:table.column>
                        <flux:table.column>OC cliente</flux:table.column>
                        <flux:table.column>Cliente</flux:table.column>
                        <flux:table.column align="end">{{ ucfirst($unidad) }}</flux:table.column>
                        <flux:table.column align="end">Importe neto</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($ventas['facturas'] as $f)
                            <flux:table.row @class(['bg-red-50' => $f['notaCredito']])>
                                <flux:table.cell class="tabular-nums">{{ $fecha($f['fecha']) }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">
                                    {{ $f['folio'] }}
                                    @if ($f['notaCredito'])
                                        <flux:badge size="sm" color="red" class="ms-1">Nota de crédito</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $f['oc'] !== '' ? $f['oc'] : '—' }}</flux:table.cell>
                                <flux:table.cell class="max-w-56 truncate" title="{{ $f['cliente'] }}">{{ $f['cliente'] }}</flux:table.cell>
                                <flux:table.cell align="end" @class(['tabular-nums', 'text-red-700' => $f['cantidad'] < 0])>{{ $num($f['cantidad']) }}</flux:table.cell>
                                <flux:table.cell align="end" @class(['tabular-nums', 'text-red-700' => $f['neto'] < 0])>${{ $num($f['neto']) }} {{ $f['moneda'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </div>
@endif
