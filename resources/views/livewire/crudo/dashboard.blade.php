@php
    $areaSalonLabels = ['Karl Mayer' => 'KM', 'Jacquard' => 'JAC', 'Smith' => 'SMI'];
    $statusCards = [
        ['key' => 'paro', 'label' => 'Paro', 'description' => 'Máquina detenida', 'icon' => 'fa-triangle-exclamation'],
        ['key' => 'bad_quality', 'label' => 'Mala calidad', 'description' => 'Máquinas en alerta', 'icon' => 'fa-circle-xmark'],
        ['key' => 'low_kilos', 'label' => 'Bajos kg', 'description' => 'Debajo de la meta', 'icon' => 'fa-arrow-down'],
        ['key' => 'operating', 'label' => 'En operación', 'description' => 'Con captura y sin paro', 'icon' => 'fa-circle-check'],
        ['key' => 'no_data', 'label' => 'Sin datos', 'description' => 'Sin captura', 'icon' => 'fa-minus'],
    ];
@endphp

<div
    class="crudo-dashboard"
    data-crudo-dashboard
    @if ($shouldPoll) wire:poll.visible.{{ $pollSeconds }}s="refreshDashboard" @endif
>
    @teleport('#crudo-navbar-controls')
        <div class="crudo-navbar-toolbar" aria-label="Filtros del tablero de Crudo">
            <label class="crudo-filter">
                <span>Vista</span>
                <select wire:model.change="modo">
                    <option value="dia">Día</option>
                    <option value="rango">Rango</option>
                </select>
            </label>

            @if ($modo === 'rango')
                <label class="crudo-filter">
                    <span>Del</span>
                    <input
                        type="date"
                        wire:model.change="fechaInicio"
                        max="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                    >
                </label>

                <label class="crudo-filter">
                    <span>Al</span>
                    <input
                        type="date"
                        wire:model.change="fechaFin"
                        max="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                    >
                </label>
            @else
                <label class="crudo-filter">
                    <span>Fecha</span>
                    <input
                        type="date"
                        wire:model.change="fecha"
                        max="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                    >
                </label>
            @endif

            <label class="crudo-filter">
                <span>Turno</span>
                <select wire:model.change="turno">
                    <option value="todos">Todos los turnos</option>
                    <option value="1">Turno 1</option>
                    <option value="2">Turno 2</option>
                    <option value="3">Turno 3</option>
                    <option value="4">Turno 4</option>
                </select>
            </label>

            <button
                type="button"
                class="crudo-icon-button"
                wire:click="refreshNow"
                wire:loading.attr="disabled"
                wire:target="refreshNow"
                title="Actualizar ahora"
                aria-label="Actualizar tablero ahora"
            >
                <i class="fa-solid fa-rotate" wire:loading.class="fa-spin" wire:target="refreshNow"></i>
            </button>

            <button
                type="button"
                class="crudo-icon-button"
                data-crudo-fullscreen
                title="Pantalla completa"
                aria-label="Mostrar tablero en pantalla completa"
            >
                <i class="fa-solid fa-expand"></i>
            </button>

            <div
                class="crudo-navbar-freshness"
                data-cache-state="{{ $cacheState }}"
                title="Estado de actualización del tablero"
            >
                <span class="crudo-live-dot" aria-hidden="true"></span>
                @if ($generatedAt)
                    <time datetime="{{ $generatedAt }}" data-crudo-relative-time>ahora</time>
                @else
                    <span>Sin conexión</span>
                @endif
            </div>

            <span
                class="crudo-navbar-loading"
                wire:loading
                wire:target="modo,fecha,fechaInicio,fechaFin,turno,refreshNow"
                aria-label="Actualizando producción"
            >
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                Actualizando
            </span>
        </div>
    @endteleport

    @if ($dataError)
        <div class="crudo-alert crudo-alert-error" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>No fue posible cargar el tablero</strong>
                <p>{{ $dataError }}</p>
            </div>
            <button type="button" wire:click="refreshNow">Reintentar</button>
        </div>
    @elseif ($sourceError)
        <div class="crudo-alert crudo-alert-warning" role="status">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <div>
                <strong>Mostrando el último dato disponible</strong>
                <p>{{ $sourceError }}</p>
            </div>
        </div>
    @endif

    <div class="crudo-content-grid">
        <aside class="crudo-sidebar" aria-label="Resumen del tablero">
            <section class="crudo-panel crudo-panel-overview">
                <div class="crudo-panel-heading">
                    <div>
                        <p class="crudo-eyebrow">Resumen general</p>
                        <h2>Estado de máquinas</h2>
                    </div>
                    <span class="crudo-total-badge">{{ $summary['total'] }}</span>
                </div>

                <div class="crudo-status-list">
                    @foreach ($statusCards as $card)
                        <article
                            class="crudo-status-card"
                            data-state="{{ $card['key'] }}"
                            title="{{ $card['description'] }}"
                            aria-label="{{ $card['label'] }}: {{ $summary[$card['key']] }}. {{ $card['description'] }}"
                        >
                            <span class="crudo-status-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
                            <span class="crudo-status-value">{{ $summary[$card['key']] }}</span>
                            <span class="crudo-status-copy">
                                <strong>{{ $card['label'] }}</strong>
                            </span>
                        </article>
                    @endforeach
                </div>

                <p class="crudo-compact-label">Producción del periodo</p>

                <div class="crudo-kpi-grid">
                    <article>
                        <i class="fa-solid fa-weight-hanging"></i>
                        <strong>{{ number_format(round((float) $summary['kilos'])) }}</strong>
                        <span>Kilogramos</span>
                    </article>
                    <article>
                        <i class="fa-solid fa-layer-group"></i>
                        <strong>{{ number_format((float) $summary['pieces']) }}</strong>
                        <span>Piezas</span>
                    </article>
                    <article>
                        <i class="fa-solid fa-shield-heart"></i>
                        <strong>{{ number_format(round((float) $summary['qualityPercent'])) }}%</strong>
                        <span>Calidad global</span>
                    </article>
                    <article>
                        <i class="fa-solid fa-gauge-high"></i>
                        <strong>{{ number_format(round((float) $summary['efficiencyPercent'])) }}%</strong>
                        <span>Eficiencia global</span>
                    </article>
                    <article>
                        <i class="fa-solid fa-arrow-trend-down"></i>
                        <strong>{{ number_format((float) $summary['seconds']) }}</strong>
                        <span>Segundas</span>
                    </article>
                </div>

                <p class="crudo-rules-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        Alerta desde {{ number_format(round((float) $badQualityThreshold)) }}%
                        · meta provisional 300 kg/día
                        @if ($modo === 'rango')
                            · rango máximo {{ $maxRangeDays }} días
                        @endif
                    </span>
                </p>
            </section>

            <section class="crudo-panel crudo-panel-areas">
                <div class="crudo-panel-heading">
                    <div>
                        <p class="crudo-eyebrow">Detalle por área</p>
                        <h2>Alertas por salón</h2>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="crudo-area-table">
                        <thead>
                            <tr>
                                <th>Salón</th>
                                <th title="Paro">Paro</th>
                                <th title="Mala calidad">Cal.</th>
                                <th title="Bajos kilogramos">Kg</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($areas as $area)
                                <tr>
                                    <td>{{ $areaSalonLabels[$area['name']] ?? $area['name'] }}</td>
                                    <td class="text-red-700">{{ $area['paro'] }}</td>
                                    <td class="text-blue-700">{{ $area['badQuality'] }}</td>
                                    <td class="text-amber-700">{{ $area['lowKilos'] }}</td>
                                    <td>{{ $area['total'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">Sin información</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>

    <main class="crudo-floor" aria-label="Distribución de máquinas por salón">
        <script
            type="application/json"
            id="crudo-machines-data"
            data-crudo-machines-data
        >@json($machines)</script>

        <livewire:crudo.machine-floor
            :floor-layouts="$floorLayouts"
            :key="'crudo-machine-floor'"
        />

        @if (count($machines) === 0 && ! $dataError)
            <div class="crudo-empty-state">
                <i class="fa-solid fa-industry"></i>
                <h2>No hay máquinas configuradas</h2>
                <p>No se encontraron telares de Karl Mayer, Jacquard o Smith en el catálogo.</p>
            </div>
        @endif
    </main>
</div>

    <livewire:crudo.machine-detail />
</div>
