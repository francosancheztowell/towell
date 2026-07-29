@php
    $salonOrder = ['Karl Mayer', 'Jacquard', 'Smith', 'Sin clasificar'];
    $salonLabels = ['Karl Mayer' => 'KM'];
    $statusCards = [
        ['key' => 'bad_quality', 'label' => 'Mala calidad', 'description' => 'Máquinas en alerta', 'icon' => 'fa-circle-xmark'],
        ['key' => 'low_kilos', 'label' => 'Bajos kg', 'description' => 'Debajo de la meta', 'icon' => 'fa-arrow-down'],
        ['key' => 'operating', 'label' => 'En operación', 'description' => 'Sin alertas', 'icon' => 'fa-circle-check'],
        ['key' => 'no_data', 'label' => 'Sin datos', 'description' => 'Sin captura', 'icon' => 'fa-minus'],
    ];
@endphp

<div
    class="crudo-dashboard"
    data-crudo-dashboard
    @if ($isToday) wire:poll.visible.{{ $pollSeconds }}s="refreshDashboard" @endif
>
    @teleport('#crudo-navbar-controls')
        <div class="crudo-navbar-toolbar" aria-label="Filtros del tablero de Crudo">
            <label class="crudo-filter">
                <span>Fecha</span>
                <input
                    type="date"
                    wire:model.change="fecha"
                    max="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                >
            </label>

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
                        <strong>{{ number_format((float) $summary['kilos'], 1) }}</strong>
                        <span>Kilogramos</span>
                    </article>
                    <article>
                        <i class="fa-solid fa-layer-group"></i>
                        <strong>{{ number_format((float) $summary['pieces']) }}</strong>
                        <span>Piezas</span>
                    </article>
                    <article>
                        <i class="fa-solid fa-shield-heart"></i>
                        <strong>{{ number_format((float) $summary['qualityPercent'], 1) }}%</strong>
                        <span>Calidad global</span>
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
                        Alerta desde {{ number_format($badQualityThreshold, 1) }}%
                        · meta provisional 300 kg/día
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
                                <th title="Mala calidad">Cal.</th>
                                <th title="Bajos kilogramos">Kg</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($areas as $area)
                                <tr>
                                    <td>{{ $area['name'] }}</td>
                                    <td class="text-rose-700">{{ $area['badQuality'] }}</td>
                                    <td class="text-amber-700">{{ $area['lowKilos'] }}</td>
                                    <td>{{ $area['total'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">Sin información</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>

        <main class="crudo-floor" aria-label="Distribución de máquinas por salón">
            <div class="crudo-salons-grid">
                @foreach ($salonOrder as $salon)
                    @php($salonLayout = $floorLayouts[$salon] ?? null)
                    @continue($salonLayout === null || $salonLayout['count'] === 0)

                    <section class="crudo-salon crudo-salon-{{ str($salon)->slug() }}">
                        <header>
                            <h2>Salón {{ $salonLabels[$salon] ?? $salon }}</h2>
                            <span>{{ $salonLayout['count'] }} máquinas</span>
                        </header>

                        @if ($salonLayout['physical'])
                            <div class="crudo-machine-grid crudo-machine-grid-physical">
                                @foreach ($salonLayout['columns'] as $columnIndex => $machineColumn)
                                    <div class="crudo-machine-column" data-column="{{ $columnIndex + 1 }}">
                                        @foreach ($machineColumn as $machine)
                                            <x-crudo.machine-card :machine="$machine" />
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="crudo-machine-grid">
                                @foreach ($salonLayout['columns'][0] as $machine)
                                    <x-crudo.machine-card :machine="$machine" />
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            @if (count($machines) === 0 && ! $dataError)
                <div class="crudo-empty-state">
                    <i class="fa-solid fa-industry"></i>
                    <h2>No hay máquinas configuradas</h2>
                    <p>No se encontraron telares de Karl Mayer, Jacquard o Smith en el catálogo.</p>
                </div>
            @endif
        </main>
    </div>

    <div
        wire:loading.flex
        wire:target="fecha,turno,refreshNow"
        class="crudo-loading"
        role="status"
        aria-live="polite"
    >
        <span>
            <i class="fa-solid fa-circle-notch fa-spin"></i>
            Actualizando producción
        </span>
    </div>

    @if ($selectedMachine)
        <div
            class="crudo-modal-backdrop"
            wire:click.self="closeMachine"
            data-crudo-modal
            role="dialog"
            aria-modal="true"
            aria-labelledby="crudo-machine-modal-title"
        >
            <article class="crudo-modal">
                <header class="crudo-modal-header" data-state="{{ $selectedMachine['state'] }}">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="crudo-modal-machine-icon">
                            <i class="fa-solid fa-industry"></i>
                        </span>
                        <div class="min-w-0">
                            <p>{{ $selectedMachine['salon'] }} · {{ $selectedMachine['group'] ?: 'Sin grupo' }}</p>
                            <h2 id="crudo-machine-modal-title">
                                {{ $selectedMachine['name'] }}
                            </h2>
                        </div>
                    </div>
                    <button type="button" wire:click="closeMachine" data-crudo-modal-close aria-label="Cerrar detalle">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="crudo-modal-body">
                    <section class="crudo-machine-detail-summary">
                        <article>
                            <span>Estado</span>
                            <strong data-state="{{ $selectedMachine['state'] }}">
                                <i class="fa-solid {{ $selectedMachine['stateIcon'] }}"></i>
                                {{ $selectedMachine['stateLabel'] }}
                            </strong>
                        </article>
                        <article>
                            <span>Producción</span>
                            <strong>{{ number_format((float) $selectedMachine['kilos'], 1) }} kg</strong>
                            <small>Meta a este corte: {{ number_format((float) $selectedMachine['expectedKilos'], 1) }} kg</small>
                        </article>
                        <article>
                            <span>Calidad</span>
                            <strong>{{ number_format((float) $selectedMachine['qualityPercent'], 1) }}%</strong>
                            <small>{{ number_format((float) $selectedMachine['secondsPercent'], 1) }}% segundas</small>
                        </article>
                        <article>
                            <span>Piezas</span>
                            <strong>{{ number_format((float) $selectedMachine['pieces']) }}</strong>
                            <small>{{ number_format((float) $selectedMachine['seconds']) }} segundas</small>
                        </article>
                    </section>

                    <div class="crudo-modal-columns">
                        <section class="crudo-detail-panel">
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <p class="crudo-eyebrow">Capturas</p>
                                    <h3>Órdenes y turnos</h3>
                                </div>
                                <span>{{ $selectedMachine['captureCount'] }}</span>
                            </div>

                            <div class="overflow-auto">
                                <table class="crudo-detail-table">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Operador</th>
                                            <th>Peso</th>
                                            <th>T1</th>
                                            <th>T2</th>
                                            <th>T3</th>
                                            <th>T4</th>
                                            <th>Piezas</th>
                                            <th>Seg.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($selectedMachine['captures'] as $capture)
                                            <tr>
                                                <td class="font-bold text-slate-900">{{ $capture['order'] }}</td>
                                                <td>{{ $capture['operator'] }}</td>
                                                <td>{{ number_format((float) $capture['weight'], 1) }}</td>
                                                <td>{{ number_format((float) $capture['piecesT1']) }}</td>
                                                <td>{{ number_format((float) $capture['piecesT2']) }}</td>
                                                <td>{{ number_format((float) $capture['piecesT3']) }}</td>
                                                <td>{{ number_format((float) $capture['piecesT4']) }}</td>
                                                <td>{{ number_format((float) $capture['pieces']) }}</td>
                                                <td>{{ number_format((float) $capture['seconds']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="9">Sin capturas en el periodo seleccionado.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="crudo-detail-panel">
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <p class="crudo-eyebrow">Calidad</p>
                                    <h3>Defectos encontrados</h3>
                                </div>
                                <span>{{ count($selectedMachine['defects']) }}</span>
                            </div>

                            <div class="crudo-defect-list">
                                @forelse ($selectedMachine['defects'] as $defect)
                                    <article>
                                        <span>{{ $defect['code'] }}</span>
                                        <div>
                                            <strong>{{ $defect['description'] }}</strong>
                                            <small>{{ number_format((float) $defect['quantity']) }} piezas</small>
                                        </div>
                                    </article>
                                @empty
                                    <div class="crudo-detail-empty">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <p>Sin defectos detallados en este periodo.</p>
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </div>
            </article>
        </div>
    @endif
</div>
