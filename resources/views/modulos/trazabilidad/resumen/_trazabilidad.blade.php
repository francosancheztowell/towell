@php $areasTrazabilidad = collect($resumen['trazabilidadAreas'] ?? []); @endphp

<article class="min-h-[290px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700">
            <i class="fa-solid fa-route"></i>
        </span>
        <div>
            <h3 class="font-bold text-slate-800">Trazabilidad</h3>
        </div>
    </header>

    <div class="max-h-[240px] flex-1 overflow-auto">
        <table class="w-full text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 text-left">Área</th>
                    <th class="px-3 py-2.5 text-right">Pzas</th>
                    <th class="px-3 py-2.5 text-right">Kilos</th>
                    <th class="px-3 py-2.5 text-center">Inicio</th>
                    <th class="px-4 py-2.5 text-center">Fin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($areasTrazabilidad as $area)
                    <tr>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-bold"
                                  style="background-color: {{ $area['tint'] }}; color: {{ $area['text'] }};">
                                <span class="h-2 w-2 rounded-full" style="background-color: {{ $area['dot'] }};"></span>
                                {{ $area['area'] }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums" style="color: {{ $area['text'] }};">
                            {{ is_null($area['piezas']) ? '—' : number_format($area['piezas'], 0) }}
                        </td>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums" style="color: {{ $area['text'] }};">
                            {{ is_null($area['kilos']) ? '—' : number_format($area['kilos'], 1) }}
                        </td>
                        <td class="px-3 py-2 text-center text-xs font-medium text-slate-600">{{ $area['fechaInicio'] }}</td>
                        <td class="px-4 py-2 text-center text-xs font-medium text-slate-600">{{ $area['fechaFin'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <footer class="border-t border-slate-100 px-4 py-2 text-right">
        <button type="button" data-resumen-detalle="trazabilidad"
                class="btn-ver-detalles inline-flex items-center gap-2 text-sm font-bold text-cyan-700 hover:text-cyan-900">
            Ver detalles <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </footer>
</article>
