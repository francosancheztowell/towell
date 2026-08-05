<section class="crudo-detail-panel crudo-flog-panel">
    <div class="crudo-detail-panel-heading">
        <div>
            <h3>Datos del Flog</h3>
        </div>
        @if (($summary['status'] ?? null) === 'ok')
            <span class="crudo-detail-count">Vinculado</span>
        @endif
    </div>

    @if (! $loaded)
        <div
            class="crudo-flog-state"
            wire:init="load"
            wire:loading.class="is-loading"
            wire:target="load"
        >
            <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            <p>Consultando el Flog relacionado…</p>
        </div>
    @elseif (($summary['status'] ?? null) === 'ok')
        <dl class="crudo-flog-fields">
            <div>
                <dt>Flog</dt>
                <dd>{{ $summary['flog'] ?: '—' }}</dd>
            </div>
            <div>
                <dt>Cliente</dt>
                <dd title="{{ $summary['client'] }}">
                    {{ $summary['client'] ?: '—' }}
                </dd>
            </div>
            <div>
                <dt>Artículo</dt>
                <dd>{{ $summary['itemId'] ?: '—' }}</dd>
            </div>
            <div>
                <dt>Tamaño</dt>
                <dd>{{ $summary['inventSizeId'] ?: '—' }}</dd>
            </div>
        </dl>

        <div class="crudo-flog-simulations">
            <p>Simulación</p>
            <div class="crudo-flog-simulation-grid">
                @foreach ([
                    ['label' => 'Ventas', 'url' => $summary['simulationSalesUrl'] ?? null],
                    ['label' => 'Diseño', 'url' => $summary['simulationDesignUrl'] ?? null],
                ] as $simulation)
                    @if ($simulation['url'])
                        <a
                            href="{{ $simulation['url'] }}"
                            class="crudo-flog-simulation"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Abrir simulación de {{ strtolower($simulation['label']) }}"
                        >
                            <img
                                src="{{ $simulation['url'] }}"
                                alt="Simulación de {{ strtolower($simulation['label']) }} del Flog {{ $summary['flog'] }}"
                                loading="lazy"
                                decoding="async"
                                fetchpriority="low"
                            >
                            <span>{{ $simulation['label'] }}</span>
                        </a>
                    @endif
                @endforeach

                @if (empty($summary['simulationSalesUrl']) && empty($summary['simulationDesignUrl']))
                    <div class="crudo-flog-empty-simulation">
                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                        <span>Sin simulación</span>
                    </div>
                @endif
            </div>
        </div>

        @if (! ($summary['lineMatched'] ?? false))
            <p class="crudo-flog-line-warning">
                El Flog existe, pero no hay una línea inequívoca para este artículo, tamaño o rollo.
            </p>
        @endif
    @elseif (($summary['status'] ?? null) === 'error')
        <div class="crudo-flog-state is-error">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <p>No fue posible consultar TI_PRO en este momento.</p>
        </div>
    @elseif (($summary['status'] ?? null) === 'not_found')
        <div class="crudo-flog-state">
            <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
            <p>No se encontró un Flog para los datos de esta producción.</p>
        </div>
    @else
        <div class="crudo-flog-state">
            <i class="fa-solid fa-link" aria-hidden="true"></i>
            <p>Sin datos suficientes para relacionar el Flog.</p>
        </div>
    @endif
</section>
