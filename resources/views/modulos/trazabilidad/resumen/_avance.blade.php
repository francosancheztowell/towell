<article class="min-h-[290px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
            <i class="fa-solid fa-chart-line"></i>
        </span>
        <div>
            <h3 class="font-bold text-slate-800">Avance del pedido</h3>
        </div>
    </header>

    <div class="max-h-[240px] flex-1 overflow-y-auto overflow-x-hidden">
        <table class="w-full table-auto border-separate border-spacing-0 text-xs" data-tabla-avance-pedido>
            <thead class="text-[11px] uppercase tracking-wide text-white">
                <tr>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-2 py-2.5 text-left">Flog</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-2 py-2.5 text-left">Orden</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-1.5 py-2.5 text-left">Tam.</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-1.5 py-2.5 text-left">Telar</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-1.5 py-2.5 text-right">Progr.</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-1.5 py-2.5 text-right">Prod.</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-1.5 py-2.5 text-right">Pedido</th>
                    <th class="sticky top-0 z-10 border-r border-blue-500 bg-blue-600 px-1 py-2.5 text-center">Ini.</th>
                    <th class="sticky top-0 z-10 bg-blue-600 px-1 py-2.5 text-center">Fin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tablaAvancePedido ?? [] as $fila)
                    <tr class="odd:bg-white even:bg-blue-50/40 hover:bg-blue-100/60">
                        <td class="border-b border-r border-blue-100 px-2 py-2 font-semibold text-slate-700">
                            <span class="whitespace-nowrap" title="{{ $fila['flog'] ?: '—' }}">{{ $fila['flog'] ?: '—' }}</span>
                        </td>
                        <td class="border-b border-r border-blue-100 px-1.5 py-2 font-mono font-semibold text-slate-700">
                            <span @class([
                                'inline-flex max-w-full rounded-md px-1.5 py-0.5',
                                'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200' => $fila['enProceso'],
                            ]) title="{{ $fila['enProceso'] ? 'Orden en proceso' : 'Orden fuera de proceso' }}">
                                {{ $fila['orden'] }}
                            </span>
                        </td>
                        <td class="truncate border-b border-r border-blue-100 px-1.5 py-2 text-slate-600" title="{{ $fila['tamano'] ?: '—' }}">{{ $fila['tamano'] ?: '—' }}</td>
                        <td class="border-b border-r border-blue-100 px-1.5 py-2 font-semibold text-slate-600">{{ $fila['telar'] ?: '—' }}</td>
                        <td class="border-b border-r border-blue-100 px-1.5 py-2 text-right tabular-nums text-slate-600">{{ number_format($fila['programado'], 0) }}</td>
                        <td class="border-b border-r border-blue-100 px-1.5 py-2 text-right tabular-nums text-slate-600">{{ number_format($fila['produccion'], 0) }}</td>
                        <td class="border-b border-r border-blue-100 px-1.5 py-2 text-right tabular-nums text-slate-600">{{ number_format($fila['pedido'], 0) }}</td>
                        <td class="border-b border-r border-blue-100 px-1 py-2 text-center text-[11px] font-medium text-slate-600">{{ $fila['inicio'] ?: '—' }}</td>
                        <td class="border-b border-blue-100 px-1 py-2 text-center text-[11px] font-medium text-slate-600">{{ $fila['fin'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-14 text-center text-slate-400">
                            Sin órdenes para los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <footer class="border-t border-slate-100 px-4 py-2 text-right">
        <button type="button" data-resumen-detalle="produccion"
                class="btn-ver-detalles inline-flex items-center gap-2 text-sm font-bold text-violet-600 hover:text-violet-800">
            Ver detalles <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </footer>
</article>
