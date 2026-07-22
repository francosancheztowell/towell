@php
    $pedido = $resumen['pedido'] ?? null;
    $facturado = $resumen['facturado'] ?? null;
    $pendiente = $resumen['pendienteFacturacion'] ?? null;
    $totalFacturacion = max(0, (float) ($facturado ?? 0) + (float) ($pendiente ?? 0));
    $porcentajeFacturado = $totalFacturacion > 0 ? min(100, max(0, (float) $facturado / $totalFacturacion * 100)) : 0;
    $porcentajePendiente = $totalFacturacion > 0 ? 100 - $porcentajeFacturado : 0;
    $campos = [
        ['icono' => 'fa-hashtag', 'etiqueta' => 'No. Flog', 'valor' => data_get($resumen, 'flogs.texto', '—')],
        ['icono' => 'fa-box', 'etiqueta' => 'Artículo', 'valor' => data_get($resumen, 'articulos.texto', '—')],
        ['icono' => 'fa-ruler-combined', 'etiqueta' => 'Tamaño', 'valor' => data_get($resumen, 'tamanos.texto', '—')],
    ];
@endphp

<article class="min-h-[290px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <i class="fa-solid fa-file-lines"></i>
        </span>
        <div>
            <h3 class="font-bold text-slate-800">Flog</h3>
        </div>
    </header>

    <div class="grid flex-1 grid-cols-1 sm:grid-cols-3">
        @foreach ($campos as $campo)
            <div class="flex min-h-20 gap-2.5 border-b border-slate-100 px-4 py-2.5 {{ $loop->iteration % 3 !== 0 ? 'sm:border-r' : '' }}">
                <i class="fa-solid {{ $campo['icono'] }} mt-1 text-sm text-slate-400"></i>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $campo['etiqueta'] }}</p>
                    <p class="mt-1 break-words text-sm font-semibold text-slate-700" title="{{ $campo['valor'] }}">
                        {{ $campo['valor'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="border-b border-slate-100 px-4 py-2.5">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach ([
                ['Pedido', $pedido, 'text-blue-700', 'bg-blue-50'],
                ['Facturado', $facturado, 'text-emerald-700', 'bg-emerald-50'],
                ['Pendiente', $pendiente, 'text-amber-700', 'bg-amber-50'],
            ] as [$etiqueta, $valor, $texto, $fondo])
                <div class="rounded-lg {{ $fondo }} px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $etiqueta }}</p>
                    <p class="mt-0.5 text-base font-extrabold {{ $texto }} tabular-nums">
                        {{ is_null($valor) ? '—' : number_format($valor, 0) }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100" aria-label="Distribución de facturación">
            <div class="h-full bg-emerald-500" style="width: {{ is_null($facturado) ? 0 : $porcentajeFacturado }}%"></div>
            <div class="h-full bg-amber-400" style="width: {{ is_null($pendiente) ? 0 : $porcentajePendiente }}%"></div>
        </div>
        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-[10px] font-semibold text-slate-500">
            <span><i class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></i>Facturado</span>
            <span><i class="mr-1 inline-block h-2 w-2 rounded-full bg-amber-400"></i>Pendiente</span>
        </div>
    </div>

    <div class="grid grid-cols-2 border-b border-slate-100">
        <div class="border-r border-slate-100 px-4 py-2.5">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Fecha inicio</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-700">{{ $resumen['fechaInicio'] ?? '—' }}</p>
        </div>
        <div class="px-4 py-2.5">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Fecha fin</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-700">{{ $resumen['fechaFin'] ?? '—' }}</p>
        </div>
    </div>
    <footer class="border-t border-slate-100 px-4 py-2 text-right">
        <button type="button" data-resumen-detalle="flogs"
                class="btn-ver-detalles inline-flex items-center gap-2 text-sm font-bold text-blue-600 hover:text-blue-800">
            Ver detalles <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </footer>
</article>
