@php
    $esMultiTelar = (bool) ($o['esMultiTelar'] ?? false);
    $estado = $o['estado'] ?? 'terminado';
    $estadoLabel = $estado === 'activo' ? 'Activa' : 'Finalizada';
    $meses = implode(', ', $o['meses'] ?? []);
    $meta = collect([$meses, $estadoLabel])->filter()->implode(' · ');
    $pzasDia = $o['pzasDia'] ?? ($o['programa']['stdDia'] ?? null);
    $kgDia = $o['prodKgDia'] ?? ($o['programa']['prodKgDia'] ?? null);
    $avance = (float) ($o['avance'] ?? 0);
    $avanceBarra = min(100, max(0, $avance));
@endphp

<article class="prod-crudo-card {{ $esMultiTelar ? 'prod-crudo-card--multi' : '' }}"
         data-estado="{{ $estado }}">
    <div class="prod-crudo-card__head">
        <div class="min-w-0">
            <h4 class="prod-crudo-card__title">Orden {{ $o['orden'] }}</h4>
            <p class="prod-crudo-card__meta">{{ $meta }}</p>
        </div>

        @if ($esMultiTelar)
            <span class="prod-crudo-card__loom-count">
                {{ $o['cantidadTelares'] }} telares
            </span>
        @else
            <span class="prod-crudo-card__status prod-crudo-card__status--{{ $estado }}">
                {{ $estadoLabel }}
            </span>
        @endif
    </div>

    <div class="prod-crudo-card__stats">
        <div class="prod-crudo-card__stat">
            <span>Pzas programadas</span>
            <strong>{{ number_format((int) round((int) ($o['programadas'] ?? 0))) }}</strong>
        </div>
        <div class="prod-crudo-card__stat">
            <span>Pzas producidas</span>
            <strong>{{ number_format((int) round((int) ($o['producidasTotal'] ?? 0))) }} </strong>
        </div>
        <div class="prod-crudo-card__stat">
            <span>Kg total</span>
            <strong>{{ number_format((int) round((int) ($o['pesoTotal'] ?? 0))) }} </strong>
        </div>
    </div>

    <div class="prod-crudo-card__daily">
        <div>
            <span>Pzas/día</span>
            <strong>{{ $pzasDia !== null ? number_format((int) round((int) $pzasDia)) : '—' }}</strong>
        </div>
        <div>
            <span>Kg/día</span>
            <strong>{{ $kgDia !== null ? number_format((int) round((int) $kgDia)) : '—' }}</strong>
        </div>
    </div>

    <div class="prod-crudo-card__loom-matrix-wrap">
        <table class="prod-crudo-card__loom-matrix">
            <thead>
                <tr>
                    <th scope="col"></th>
                    @foreach ($o['telares'] as $telar)
                        <th scope="col" data-loom-column>Telar {{ $telar['telarNumero'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Pzas</th>
                    @foreach ($o['telares'] as $telar)
                        <td>{{ number_format((int) $telar['producidas']) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <th scope="row">Kg</th>
                    @foreach ($o['telares'] as $telar)
                        <td>{{ number_format((int) round((float) $telar['kg'])) }} </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div class="prod-crudo-card__progress">
        <div class="prod-crudo-card__progress-meta">
            <span>Avance</span>
            <strong>{{ number_format($avance, 1) }}%</strong>
        </div>
        <div class="prod-crudo-card__progress-track"
             role="progressbar"
             aria-label="Avance de la orden {{ $o['orden'] }}"
             aria-valuemin="0"
             aria-valuemax="100"
             aria-valuenow="{{ $avanceBarra }}">
            <div class="prod-crudo-card__progress-bar" style="width: {{ $avanceBarra }}%"></div>
        </div>
    </div>
</article>
