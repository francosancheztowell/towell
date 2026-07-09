@php
    $porTelar = $resumenCrudo['porTelar'] ?? [];
@endphp

<article class="prod-resumen-crudo" data-prod-summary-card>
    <div class="prod-resumen-crudo__head">
        <h4 class="prod-resumen-crudo__title">Resumen</h4>
        @if (! empty($porTelar))
            <button type="button" class="prod-resumen-crudo__toggle" data-abrir-modal-telares title="Ver detalle por telar">
                <span>Telares</span>
                <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    <div class="prod-resumen-crudo__stats">
        <div class="prod-resumen-crudo__stat">
            <span>Programado</span>
            <strong>{{ number_format((float) ($resumenCrudo['totalProgramado'] ?? 0)) }}</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>Producido</span>
            <strong>{{ number_format((float) ($resumenCrudo['totalProducido'] ?? 0)) }}</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>KG</span>
            <strong>{{ number_format((float) ($resumenCrudo['totalKg'] ?? 0)) }}</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>Avance</span>
            <strong>{{ number_format((float) ($resumenCrudo['avanceGlobal'] ?? 0)) }}%</strong>
        </div>
        <div class="prod-resumen-crudo__stat prod-resumen-crudo__stat--wide">
            <span>Telares activos</span>
            <strong>{{ (int) ($resumenCrudo['telaresActivos'] ?? 0) }} / {{ (int) ($resumenCrudo['telaresTotal'] ?? 0) }}</strong>
        </div>
    </div>
</article>

@if (! empty($porTelar))
    {{-- Modal detalle por telar (se mueve a body vía JS) --}}
    <div id="modal-resumen-telares" class="hidden fixed inset-0 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-resumen-telares-titulo">
        <div class="modal-resumen-telares__backdrop absolute inset-0" data-modal-resumen-telares-close></div>
        <div class="modal-resumen-telares__panel relative bg-white rounded-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 shrink-0">
                <h4 id="modal-resumen-telares-titulo" class="text-lg font-bold text-slate-800">Detalle por telar</h4>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg transition-colors" data-modal-resumen-telares-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="overflow-auto flex-1 p-4">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <th class="px-3 py-2 border-b border-slate-200">Telar</th>
                            <th class="px-3 py-2 border-b border-slate-200 text-right">Programado</th>
                            <th class="px-3 py-2 border-b border-slate-200 text-right">Producido</th>
                            <th class="px-3 py-2 border-b border-slate-200 text-right">KG</th>
                            <th class="px-3 py-2 border-b border-slate-200 text-right">Avance</th>
                            <th class="px-3 py-2 border-b border-slate-200">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-700">
                        @foreach ($porTelar as $t)
                            <tr class="border-b border-slate-100 hover:bg-slate-50/80">
                                <td class="px-3 py-2 font-semibold">{{ $t['telar'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t['programado']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t['producido']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t['kg']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t['avance']) }}%</td>
                                <td class="px-3 py-2">
                                    <span class="prod-resumen-crudo__telar-badge prod-resumen-crudo__telar-badge--{{ $t['activo'] ? 'activo' : 'inactivo' }}">
                                        {{ $t['activo'] ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
