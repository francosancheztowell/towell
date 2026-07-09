@php
    $porTelar = $resumenCrudo['porTelar'] ?? [];
@endphp

<article class="prod-resumen-crudo" data-prod-summary-card>
    <div class="prod-resumen-crudo__head">
        <h4 class="prod-resumen-crudo__title">Resumen</h4>
        @if (! empty($porTelar))
            <button type="button"
                    class="prod-resumen-crudo__toggle"
                    aria-expanded="false"
                    aria-controls="prod-resumen-telares-detalle"
                    title="Ver detalle por telar">
                <span>Telares</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
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

    @if (! empty($porTelar))
        <div id="prod-resumen-telares-detalle" class="prod-resumen-crudo__popover" role="region" aria-label="Detalle por telar">
            <table class="prod-resumen-crudo__telares">
                <thead>
                    <tr>
                        <th scope="col">Telar</th>
                        <th scope="col">Prog.</th>
                        <th scope="col">Prod.</th>
                        <th scope="col">KG</th>
                        <th scope="col">Avance</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porTelar as $t)
                        <tr>
                            <td>{{ $t['telar'] }}</td>
                            <td>{{ number_format((float) $t['programado']) }}</td>
                            <td>{{ number_format((float) $t['producido']) }}</td>
                            <td>{{ number_format((float) $t['kg']) }}</td>
                            <td>{{ number_format((float) $t['avance']) }}%</td>
                            <td>
                                <span class="prod-resumen-crudo__telar-badge prod-resumen-crudo__telar-badge--{{ $t['activo'] ? 'activo' : 'inactivo' }}">
                                    {{ $t['activo'] ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</article>
