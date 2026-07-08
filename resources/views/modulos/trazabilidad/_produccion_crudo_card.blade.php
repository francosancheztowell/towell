@php
    $panelId = 'prod-telares-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $o['orden']);
    $esMultiTelar = (bool) ($o['esMultiTelar'] ?? false);
    $estado = $o['estado'] ?? 'terminado';
    $estadoLabel = $estado === 'activo' ? 'Activa' : 'Finalizada';
    $meses = implode(', ', $o['meses'] ?? []);
    $meta = collect([$meses, $estadoLabel])->filter()->implode(' · ');
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

    @if ($esMultiTelar)
        <div id="{{ $panelId }}" class="prod-crudo-card__detail hidden">
            <div class="prod-crudo-card__loom-row prod-crudo-card__loom-row--head">
                <span>Telar</span>
                <span>Origen</span>
                <span>Piezas</span>
                <span>Peso</span>
            </div>
            @foreach ($o['telares'] as $telar)
                <div class="prod-crudo-card__loom-row" data-loom-row>
                    <strong class="{{ $telar['origen'] === 'trazabilidad' ? 'text-amber-700' : '' }}">
                        {{ $telar['telarNumero'] }}
                    </strong>
                    <span>{{ $telar['origen'] === 'trazabilidad' ? 'Trazabilidad' : 'Programa' }}</span>
                    <span>{{ number_format((float) $telar['producidas']) }}</span>
                    <span>{{ number_format((float) $telar['kg'], 2) }} kg</span>
                </div>
            @endforeach
        </div>

        <div class="prod-crudo-card__footer">
            <button type="button"
                    class="prod-crudo-toggle"
                    aria-expanded="false"
                    aria-controls="{{ $panelId }}">
                <span class="prod-crudo-toggle__label">Ver telares</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <span class="prod-crudo-card__loom-summary" title="{{ $o['telaresResumen'] }}">
                {{ $o['telaresResumen'] }}
            </span>
        </div>
    @else
        <div class="prod-crudo-card__single-loom">
            Telar {{ $o['telares'][0]['telarNumero'] ?? '—' }}
        </div>
    @endif
</article>
