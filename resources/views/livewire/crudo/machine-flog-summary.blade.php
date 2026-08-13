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
        </dl>

        <div class="crudo-flog-simulations">
            <p>Simulación</p>
            <div class="crudo-flog-simulation-grid">
                @foreach ([
                    ['label' => 'Ventas', 'url' => $summary['simulationSalesUrl'] ?? null],
                    ['label' => 'Diseño', 'url' => $summary['simulationDesignUrl'] ?? null],
                ] as $simulation)
                    @if ($simulation['url'])
                        @php($simulationId = 'crudo-sim-'.$summary['flog'].'-'.str($simulation['label'])->slug())

                        {{--
                            El popover nativo amplía la imagen sin salir del modal: se
                            cierra con Esc o clic fuera, sin JS ni librería.
                        --}}
                        <button
                            type="button"
                            class="crudo-flog-simulation"
                            popovertarget="{{ $simulationId }}"
                            title="Ampliar simulación de {{ strtolower($simulation['label']) }}"
                        >
                            {{--
                                Si el archivo no está en el UNC la ruta responde 204 y la
                                imagen falla: se oculta el botón completo en vez de dejar
                                un icono roto.
                            --}}
                            <img
                                src="{{ $simulation['url'] }}"
                                alt="Simulación de {{ strtolower($simulation['label']) }} del Flog {{ $summary['flog'] }}"
                                loading="lazy"
                                decoding="async"
                                fetchpriority="low"
                                onerror="this.closest('.crudo-flog-simulation').hidden = true"
                            >
                            <span>{{ $simulation['label'] }}</span>
                        </button>

                        <div id="{{ $simulationId }}" popover class="crudo-flog-lightbox">
                            <img
                                src="{{ $simulation['url'] }}"
                                alt="Simulación de {{ strtolower($simulation['label']) }} ampliada"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
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
                El Flog existe, pero no hay una línea inequívoca relacionada con este rollo.
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
