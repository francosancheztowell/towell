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
            <span>Programadas</span>
            <strong>{{ number_format((float) ($o['programadas'] ?? 0)) }}</strong>
        </div>
        <div class="prod-crudo-card__stat">
            <span>Producidas</span>
            <strong>{{ number_format((float) ($o['producidasTotal'] ?? 0)) }} <small>pzas</small></strong>
        </div>
        <div class="prod-crudo-card__stat">
            <span>Peso total</span>
            <strong>{{ number_format((float) ($o['pesoTotal'] ?? 0), 2) }} <small>kg</small></strong>
        </div>
    </div>

    <div class="prod-crudo-card__daily">
        <div>
            <span>Pzas/día</span>
            <strong>{{ $pzasDia !== null ? number_format((float) $pzasDia) : '—' }}</strong>
        </div>
        <div>
            <span>Kg/día</span>
            <strong>{{ $kgDia !== null ? number_format((float) $kgDia, 2) : '—' }}</strong>
        </div>
    </div>

    @if ($esMultiTelar)
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
                        <th scope="row">Piezas</th>
                        @foreach ($o['telares'] as $telar)
                            <td>{{ number_format((float) $telar['producidas']) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Peso</th>
                        @foreach ($o['telares'] as $telar)
                            <td>{{ number_format((float) $telar['kg'], 2) }} <small>kg</small></td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        <div class="prod-crudo-card__single-loom">
            Telar {{ $o['telares'][0]['telarNumero'] ?? '—' }}
        </div>
    @endif

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
