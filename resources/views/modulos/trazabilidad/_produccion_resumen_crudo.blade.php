@php
    $porTelar = $resumenCrudo['porTelar'] ?? [];
@endphp

<div class="prod-resumen-crudo mb-6">
    <div class="prod-resumen-crudo__stats">
        <div class="prod-resumen-crudo__stat">
            <span>Total Programado</span>
            <strong>{{ number_format((float) ($resumenCrudo['totalProgramado'] ?? 0)) }}</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>Total Producido</span>
            <strong>{{ number_format((float) ($resumenCrudo['totalProducido'] ?? 0)) }}</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>KG Producidos</span>
            <strong>{{ number_format((float) ($resumenCrudo['totalKg'] ?? 0), 2) }}</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>Avance Global</span>
            <strong>{{ number_format((float) ($resumenCrudo['avanceGlobal'] ?? 0), 2) }}%</strong>
        </div>
        <div class="prod-resumen-crudo__stat">
            <span>Telares Activos</span>
            <strong>{{ (int) ($resumenCrudo['telaresActivos'] ?? 0) }} / {{ (int) ($resumenCrudo['telaresTotal'] ?? 0) }}</strong>
        </div>
    </div>

    @if (! empty($porTelar))
        <div class="prod-resumen-crudo__telares-wrap">
            <table class="prod-resumen-crudo__telares">
                <thead>
                    <tr>
                        <th scope="col">Telar</th>
                        <th scope="col">Programado</th>
                        <th scope="col">Producido</th>
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
                            <td>{{ number_format((float) $t['kg'], 2) }}</td>
                            <td>{{ number_format((float) $t['avance'], 2) }}%</td>
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
</div>
