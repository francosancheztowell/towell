@php
    $pedido = $resumen['pedido'] ?? null;
    $facturado = $resumen['facturado'] ?? null;
    $pendiente = $resumen['pendienteFacturacion'] ?? null;
    $cancelado = $resumen['cancelado'] ?? null;
    $lineasCanceladas = (int) ($resumen['lineasCanceladas'] ?? 0);
    $hayCancelado = $cancelado !== null && $cancelado > 0;
    // La barra reparte todo lo que se pidió alguna vez: facturado + pendiente + cancelado.
    $totalBarra = max(0, (float) ($facturado ?? 0) + (float) ($pendiente ?? 0) + (float) ($cancelado ?? 0));
    $porcentaje = static fn (?float $valor): float => $totalBarra > 0 ? min(100, max(0, (float) $valor / $totalBarra * 100)) : 0;
    $porcentajeFacturado = $porcentaje($facturado);
    $porcentajePendiente = $porcentaje($pendiente);
    $porcentajeCancelado = $porcentaje($cancelado);
    $campos = [
        ['etiqueta' => 'No. Flog', 'valor' => data_get($resumen, 'flogs.texto', '—'), 'resumen' => $resumen['flogs'] ?? []],
        ['etiqueta' => 'Artículo', 'valor' => data_get($resumen, 'articulos.texto', '—'), 'resumen' => $resumen['articulos'] ?? []],
        ['etiqueta' => 'Tamaño', 'valor' => data_get($resumen, 'tamanos.texto', '—'), 'resumen' => $resumen['tamanos'] ?? []],
    ];
    $cifras = [
        ['Pedido', $pedido, 'bg-blue-500'],
        ['Facturado', $facturado, 'bg-emerald-500'],
        ['Pendiente', $pendiente, 'bg-amber-400'],
    ];
@endphp

<x-trazabilidad.tarjeta titulo="Flog" detalle="flogs">
    <dl class="grid grid-cols-1 border-b border-zinc-100 sm:grid-cols-3">
        @foreach ($campos as $campo)
            <div @class(['min-w-0 px-4 py-3', 'sm:border-r sm:border-zinc-100' => ! $loop->last])>
                <dt class="text-xs font-medium text-zinc-500">{{ $campo['etiqueta'] }}</dt>
                @php $todos = $campo['resumen']['todos'] ?? []; @endphp
                @if (count($todos) > 3)
                    {{-- <details> nativo: el "+N más" se abre con dedo o teclado y muestra la lista completa. --}}
                    <dd class="mt-1 text-sm font-semibold text-zinc-800">
                        <details class="group">
                            <summary class="cursor-pointer list-none break-words [&::-webkit-details-marker]:hidden">
                                {{ $campo['resumen']['visibles'] }}
                                <span class="ms-1 inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-xs font-semibold text-blue-700 group-open:hidden">+{{ count($todos) - 3 }} más</span>
                                <span class="ms-1 hidden items-center rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-semibold text-zinc-600 group-open:inline-flex">Ocultar</span>
                            </summary>
                            <ul class="traza-scroll mt-2 flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                                @foreach ($todos as $valor)
                                    <li class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700">{{ $valor }}</li>
                                @endforeach
                            </ul>
                        </details>
                    </dd>
                @else
                    <dd class="mt-1 break-words text-sm font-semibold text-zinc-800">{{ $campo['valor'] }}</dd>
                @endif
            </div>
        @endforeach
    </dl>

    <div class="flex-1 space-y-3 px-4 py-4">
        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($cifras as [$etiqueta, $valor, $punto])
                <div>
                    <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                        <span class="size-2 rounded-full {{ $punto }}" aria-hidden="true"></span>{{ $etiqueta }}
                    </dt>
                    <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 md:text-2xl">
                        {{ is_null($valor) ? '—' : number_format($valor, 0) }}
                    </dd>
                </div>
            @endforeach

            {{-- Cancelado: lo cancelado sin facturar. No entra al pedido; en rojo cuando hay. --}}
            <div @class(['-m-1.5 rounded-lg p-1.5', 'bg-red-50 ring-1 ring-inset ring-red-200' => $hayCancelado])>
                <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                    <span class="size-2 rounded-full bg-red-500" aria-hidden="true"></span>Cancelado
                </dt>
                <dd @class(['mt-0.5 text-xl font-semibold tabular-nums md:text-2xl', 'text-red-700' => $hayCancelado, 'text-zinc-400' => ! $hayCancelado])>
                    {{ is_null($cancelado) ? '—' : number_format($cancelado, 0) }}
                </dd>
                @if ($lineasCanceladas > 0)
                    <dd class="text-xs font-medium text-red-700">
                        {{ $lineasCanceladas }} {{ $lineasCanceladas === 1 ? 'línea' : 'líneas' }}
                    </dd>
                @endif
            </div>
        </dl>

        @php
            $tramos = [
                ['Facturado', $porcentajeFacturado, 'bg-emerald-500', 'text-white'],
                ['Pendiente', $porcentajePendiente, 'bg-amber-400', 'text-amber-950'],
                ['Cancelado', $porcentajeCancelado, 'bg-red-500', 'text-white'],
            ];
            $pct = static fn (float $valor): string => ($valor > 0 && $valor < 1 ? '<1' : number_format($valor, 0)).' %';
        @endphp
        <div>
            <div class="flex h-6 overflow-hidden rounded-md bg-zinc-100" role="img"
                 aria-label="Facturado {{ $pct($porcentajeFacturado) }}, pendiente {{ $pct($porcentajePendiente) }}, cancelado {{ $pct($porcentajeCancelado) }}">
                @foreach ($tramos as [$etiqueta, $valor, $fondo, $texto])
                    @if ($valor > 0)
                        <div class="flex h-full items-center justify-center overflow-hidden {{ $fondo }}" style="width: {{ $valor }}%">
                            {{-- Dentro del tramo solo si cabe; la leyenda de abajo siempre los trae todos. --}}
                            @if ($valor >= 12)
                                <span class="text-xs font-semibold tabular-nums {{ $texto }}" aria-hidden="true">{{ $pct($valor) }}</span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
            <ul class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-600" aria-hidden="true">
                @foreach ($tramos as [$etiqueta, $valor, $fondo])
                    <li class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full {{ $fondo }}"></span>{{ $etiqueta }}
                        <span class="font-semibold tabular-nums text-zinc-900">{{ $pct($valor) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <dl class="grid grid-cols-2 border-t border-zinc-100">
        <div class="border-r border-zinc-100 px-4 py-3">
            <dt class="text-xs font-medium text-zinc-500">Fecha inicio</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-zinc-800">{{ $resumen['fechaInicio'] ?? '—' }}</dd>
        </div>
        <div class="px-4 py-3">
            <dt class="text-xs font-medium text-zinc-500">Fecha fin</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-zinc-800">{{ $resumen['fechaFin'] ?? '—' }}</dd>
        </div>
    </dl>
</x-trazabilidad.tarjeta>
