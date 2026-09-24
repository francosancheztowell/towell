{{-- Panel /admin · Rendimiento por ruta (MON-26): p50/p95, últimos 7 días vs los 7 previos. --}}
@php
    $celda = function (array $metrica, int $umbral): array {
        $actual = $metrica['actual'] ?? null;
        $previa = $metrica['previa'] ?? null;
        $delta = $actual && $previa && $previa['p95'] > 0 ? (int) round(100 * ($actual['p95'] - $previa['p95']) / $previa['p95']) : null;

        return [$actual, $previa, $delta, $actual && $actual['p95'] > $umbral];
    };
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <label class="relative min-w-0 flex-1 sm:max-w-xs">
            <span class="sr-only">Buscar ruta</span>
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
            <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar ruta…"
                   class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model.live="soloLentas" class="rounded border-slate-300 text-blue-600 focus:ring-blue-200">
            Solo lentas (servidor &gt; {{ $umbrales['ServidorMs'] }} ms o carga &gt; {{ $umbrales['CargaMs'] }} ms, p95)
        </label>
        <span class="ms-auto text-xs text-slate-400">Calculado {{ $calculadoEn }}</span>
        <button type="button" wire:click="recalcular" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
            <i class="fa-solid fa-rotate" wire:loading.class="fa-spin" wire:target="recalcular"></i> Recalcular
        </button>
        @if ($pulse)
            <a href="{{ url(config('pulse.path')) }}" class="rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-blue-700">
                <i class="fa-solid fa-heart-pulse"></i> Pulse (queries lentas)
            </a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-blue-600 text-white">
                <tr>
                    <th scope="col" rowspan="2" class="px-4 py-2 text-left font-semibold">Ruta</th>
                    <th scope="colgroup" colspan="3" class="border-l border-blue-500 px-4 py-1.5 text-center font-semibold">Servidor (ms)</th>
                    <th scope="colgroup" colspan="3" class="border-l border-blue-500 px-4 py-1.5 text-center font-semibold">Carga en navegador (ms)</th>
                </tr>
                <tr class="text-xs">
                    @foreach (['Servidor', 'Carga'] as $grupo)
                        <th scope="col" class="border-l border-blue-500 px-3 py-1 text-right font-semibold">n</th>
                        <th scope="col" class="px-3 py-1 text-right font-semibold">p50</th>
                        <th scope="col" class="px-3 py-1 text-right font-semibold">p95 · vs sem. previa</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr wire:key="ruta-{{ md5($fila['ruta']) }}" @class(['border-b border-slate-100', 'bg-red-50' => $fila['lenta'], 'odd:bg-white even:bg-slate-50/60' => ! $fila['lenta']])>
                        <td class="px-4 py-2 font-medium text-slate-700">
                            @if ($fila['lenta']) <i class="fa-solid fa-triangle-exclamation text-red-500" title="Lenta"></i> @endif
                            {{ $fila['ruta'] }}
                        </td>
                        @foreach (['ServidorMs', 'CargaMs'] as $metrica)
                            @php [$actual, $previa, $delta, $excede] = $celda($fila[$metrica], $umbrales[$metrica]); @endphp
                            <td class="border-l border-slate-100 px-3 py-2 text-right text-slate-500">{{ $actual['n'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-slate-700">{{ $actual ? number_format($actual['p50']) : '—' }}</td>
                            <td @class(['whitespace-nowrap px-3 py-2 text-right', 'font-bold text-red-600' => $excede, 'text-slate-700' => ! $excede])>
                                {{ $actual ? number_format($actual['p95']) : '—' }}
                                @if ($delta !== null)
                                    <span @class(['ms-1 text-xs', 'text-red-500' => $delta > 10, 'text-emerald-600' => $delta < -10, 'text-slate-400' => abs($delta) <= 10])>
                                        {{ $delta > 0 ? '+' : '' }}{{ $delta }}%
                                    </span>
                                @elseif ($actual)
                                    <span class="ms-1 text-xs text-slate-400">nuevo</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center">
                            <i class="fa-solid fa-gauge-high text-3xl text-slate-300"></i>
                            <p class="mt-3 font-semibold text-slate-600">Sin vistas medidas en los últimos 7 días.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-400">Percentil de rango más cercano sobre SYSMonVista. «n» = vistas medidas de la semana. Rojo: p95 por encima del umbral.</p>
</div>
