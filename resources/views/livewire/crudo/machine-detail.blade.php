@php
    $auditChecklist = [
        [
            'key' => 'alineacion_orden',
            'question' => '¿La alineación coincide con la orden?',
            'salon' => null,
        ],
        [
            'key' => 'dibujo_jacquard',
            'question' => '¿El dibujo de Jacquard está bien definido?',
            'salon' => 'Jacquard',
        ],
        [
            'key' => 'identificacion_julio',
            'question' => '¿Es correcta la identificación en el julio del lote de hilo y proveedor?',
            'salon' => null,
        ],
    ];
    $isSelectedMachineJacquard = strcasecmp(
        trim((string) ($selectedMachine['salon'] ?? '')),
        'Jacquard',
    ) === 0;
    $visibleAuditChecklist = $isSelectedMachineJacquard
        ? $auditChecklist
        : [$auditChecklist[0], $auditChecklist[2]];
@endphp

<div>
    @if ($selectedMachine)
        <div
            class="crudo-modal-backdrop"
            wire:click.self="close"
            data-crudo-modal
            role="dialog"
            aria-modal="true"
            aria-labelledby="crudo-machine-modal-title"
        >
            <article class="crudo-modal">
                <button
                    type="button"
                    class="crudo-modal-close"
                    wire:click="close"
                    data-crudo-modal-close
                    aria-label="Cerrar detalle"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <div
                    class="crudo-modal-body"
                    wire:loading.class="crudo-modal-body-loading"
                    wire:target="open,refreshDetail"
                >
                    @if ($detail === null && $detailError === null)
                        <div class="crudo-modal-skeleton" aria-hidden="true">
                            <div class="crudo-skeleton-overview">
                                <div class="crudo-skeleton-block" style="height: 4.75rem; grid-column: 1 / -1;"></div>
                                <div class="crudo-skeleton-block" style="height: 4.75rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 4.75rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 4.75rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 4.75rem;"></div>
                            </div>
                            <div class="crudo-skeleton-columns">
                                <div class="crudo-skeleton-block" style="height: 14rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 14rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 14rem;"></div>
                            </div>
                        </div>
                    @endif

                    <section class="crudo-modal-overview" data-state="{{ $selectedMachine['state'] }}">
                        <article class="crudo-modal-identity-card">
                            <span class="crudo-modal-machine-icon">
                                <i class="fa-solid fa-industry"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="crudo-modal-location">
                                    {{ $selectedMachine['salon'] }} · {{ $selectedMachine['group'] ?: 'Sin grupo' }}
                                </p>
                                @php
                                    $programOrder = trim((string) (
                                        $selectedMachine['programa']['orden']
                                        ?? $selectedMachine['programa']['clave']
                                        ?? ''
                                    ));
                                    $modelKey = trim((string) ($selectedMachine['programa']['claveModelo'] ?? ''));
                                    $itemId = trim((string) ($selectedMachine['programa']['itemId'] ?? ''));
                                @endphp
                                <h2 id="crudo-machine-modal-title">
                                    {{ $selectedMachine['name'] }}
                                    @if ($programOrder !== '')
                                        <span class="crudo-modal-order">- {{ $programOrder }}</span>
                                    @endif
                                </h2>
                                @if ($selectedMachine['programa'])
                                    <p class="crudo-modal-program">
                                        <span>Clave modelo <strong>{{ $modelKey !== '' ? $modelKey : 'N/D' }}</strong></span>
                                        <span aria-hidden="true">·</span>
                                        <span>Clave AX <strong>{{ $itemId !== '' ? $itemId : 'N/D' }}</strong></span>
                                    </p>
                                @endif
                            </div>
                        </article>

                        <article
                            class="crudo-modal-kpi crudo-modal-status-card {{ $selectedMachine['paro'] ? 'has-paro' : '' }}"
                            @if ($selectedMachine['paro'])
                                title="{{ implode(' · ', array_filter([
                                    $selectedMachine['paro']['falla'] ?? 'Paro reportado',
                                    'Reportó: '.($selectedMachine['paro']['reportedBy'] ?? 'Sin registrar'),
                                    'Desde: '.(trim($selectedMachine['paro']['since'] ?? '') ?: 'Sin registrar'),
                                ])) }}"
                            @endif
                        >
                            <span>Estado</span>
                            <strong class="crudo-modal-state">
                                <i class="fa-solid {{ $selectedMachine['stateIcon'] }}"></i>
                                {{ $selectedMachine['stateLabel'] }}
                            </strong>
                            @if ($selectedMachine['paro'])
                                <small class="crudo-modal-paro-falla">
                                    {{ $selectedMachine['paro']['falla'] ?? 'Paro reportado' }}
                                </small>
                                <small class="crudo-modal-paro-tiempo">
                                    Desde {{ trim($selectedMachine['paro']['since'] ?? '') ?: 'Sin registrar' }}
                                </small>
                            @endif
                        </article>
                        <article class="crudo-modal-kpi">
                            <span>Producción</span>
                            <strong>{{ number_format(round((float) $selectedMachine['kilos'])) }} kg</strong>
                            <small>
                                {{ $turno === 'todos' ? 'Meta a esta hora' : 'Meta' }}
                                {{ number_format(round((float) $selectedMachine['expectedKilos'])) }} kg
                            </small>
                        </article>
                        <article class="crudo-modal-kpi">
                            <span>Calidad</span>
                            <strong>{{ number_format(round((float) $selectedMachine['qualityPercent'])) }}%</strong>
                            <small>{{ number_format(round((float) $selectedMachine['secondsPercent'])) }}% segundas</small>
                        </article>
                        <article class="crudo-modal-kpi">
                            <span>Piezas</span>
                            <strong>{{ number_format((float) $selectedMachine['pieces']) }}</strong>
                            <small>{{ number_format((float) $selectedMachine['seconds']) }} segundas</small>
                        </article>
                    </section>

                    @if ($detailError)
                        <div class="crudo-detail-error" role="alert">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            <span>{{ $detailError }}</span>
                        </div>
                    @endif

                    <div class="crudo-production-detail-grid">
                        <section class="crudo-detail-panel">
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Órdenes y turnos</h3>
                                </div>
                                <span>{{ $selectedMachine['captureCount'] }}</span>
                            </div>

                            <div class="overflow-auto">
                                <table class="crudo-detail-table">
                                    <thead>
                                        <tr>
                                            <th>No. Rollo</th>
                                            <th>Peso (kg)</th>
                                            <th>Piezas</th>
                                            <th>Segundas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($selectedMachine['captures'] as $capture)
                                            <tr>
                                                <td>{{ $capture['purchBarcode'] ?: '—' }}</td>
                                                <td>{{ number_format((int) $capture['weight'], 1) }}</td>
                                                <td>{{ number_format((int) $capture['pieces']) }}</td>
                                                <td>{{ number_format((int) $capture['seconds']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4">Sin capturas en el periodo seleccionado.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="crudo-detail-panel crudo-process-defects-panel">
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Defectos registrados</h3>
                                </div>
                                <span class="crudo-detail-count">
                                    {{ count($selectedMachine['defects']) }} tipos
                                    · {{ $selectedMachine['defectLineCount'] ?? 0 }} líneas
                                </span>
                            </div>

                            <div class="crudo-defect-table-wrap">
                                <table class="crudo-detail-table crudo-defect-table">
                                    <caption class="sr-only">
                                        Defectos consultados de producción y desglose por turno
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th>Defecto</th>
                                            <th>T1</th>
                                            <th>T2</th>
                                            <th>T3</th>
                                            <th>T4</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($selectedMachine['defects'] as $defect)
                                            <tr>
                                                <td class="font-bold text-slate-900">
                                                    {{ $defect['description'] }}
                                                </td>
                                                @foreach (['1', '2', '3', '4'] as $defectTurn)
                                                    <td>
                                                        {{ number_format((float) ($defect['turns'][$defectTurn] ?? 0)) }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="crudo-defect-empty" colspan="5">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    Sin defectos registrados en este periodo.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="crudo-detail-panel crudo-flog-panel">
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Datos del Flog</h3>
                                </div>
                                @if (($flogSummary['status'] ?? null) === 'ok')
                                    <span class="crudo-detail-count">Vinculado</span>
                                @endif
                            </div>

                            @if (($flogSummary['status'] ?? null) === 'ok')
                                <dl class="crudo-flog-fields">
                                    <div>
                                        <dt>Flog</dt>
                                        <dd>{{ $flogSummary['flog'] ?: '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Cliente</dt>
                                        <dd title="{{ $flogSummary['client'] }}">
                                            {{ $flogSummary['client'] ?: '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Artículo</dt>
                                        <dd>{{ $flogSummary['itemId'] ?: '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Tamaño</dt>
                                        <dd>{{ $flogSummary['inventSizeId'] ?: '—' }}</dd>
                                    </div>
                                </dl>

                                <div class="crudo-flog-simulations">
                                    <p>Simulación</p>
                                    <div class="crudo-flog-simulation-grid">
                                        @foreach ([
                                            ['label' => 'Ventas', 'url' => $flogSummary['simulationSalesUrl'] ?? null],
                                            ['label' => 'Diseño', 'url' => $flogSummary['simulationDesignUrl'] ?? null],
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
                                                        alt="Simulación de {{ strtolower($simulation['label']) }} del Flog {{ $flogSummary['flog'] }}"
                                                        loading="lazy"
                                                        decoding="async"
                                                        fetchpriority="low"
                                                    >
                                                    <span>{{ $simulation['label'] }}</span>
                                                </a>
                                            @endif
                                        @endforeach

                                        @if (empty($flogSummary['simulationSalesUrl']) && empty($flogSummary['simulationDesignUrl']))
                                            <div class="crudo-flog-empty-simulation">
                                                <i class="fa-regular fa-image" aria-hidden="true"></i>
                                                <span>Sin simulación</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if (! ($flogSummary['lineMatched'] ?? false))
                                    <p class="crudo-flog-line-warning">
                                        El Flog existe, pero no hay una línea inequívoca para este artículo, tamaño o rollo.
                                    </p>
                                @endif
                            @elseif (($flogSummary['status'] ?? null) === 'error')
                                <div class="crudo-flog-state is-error">
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <p>No fue posible consultar TI_PRO en este momento.</p>
                                </div>
                            @elseif (($flogSummary['status'] ?? null) === 'not_found')
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
                    </div>

                    <div
                        class="crudo-audit-disclosure"
                        data-crudo-audit-form
                        data-crudo-audit-url="{{ route('crudo.auditorias.store') }}"
                        data-crudo-audit-stop-url="{{ route('crudo.auditorias.store-with-stop') }}"
                        data-crudo-audit-history-url="{{ route('crudo.auditorias.today', ['telar' => $selectedMachine['telar']]) }}"
                        data-crudo-audit-telar="{{ $selectedMachine['telar'] }}"
                        data-crudo-audit-salon="{{ $selectedMachine['salon'] }}"
                        data-crudo-audit-order="{{ $programOrder }}"
                    >
                        <div class="crudo-audit-toolbar">
                            <button
                                type="button"
                                class="crudo-audit-toggle"
                                wire:click="toggleAudit"
                                data-crudo-audit-toggle
                                aria-expanded="{{ $auditExpanded ? 'true' : 'false' }}"
                                aria-controls="crudo-audit-content"
                            >
                                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                                <span data-crudo-audit-toggle-label>
                                    {{ $auditExpanded ? 'Ocultar auditoría' : 'Agregar auditoría' }}
                                </span>
                                <i class="fa-solid fa-chevron-down crudo-audit-toggle-chevron" aria-hidden="true"></i>
                            </button>

                            <div class="crudo-modal-actions" aria-label="Acciones del detalle del telar">
                                <button
                                    type="button"
                                    class="crudo-modal-action crudo-modal-action-audit"
                                    data-crudo-save-audit
                                    @if (! $auditExpanded) hidden @endif
                                >
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                    <span>Guardar auditoría</span>
                                </button>

                                <button
                                    type="button"
                                    class="crudo-modal-action crudo-modal-action-stop"
                                    data-crudo-save-stop
                                    @if (! $auditExpanded) hidden @endif
                                >
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <span>Guardar paro</span>
                                </button>
                            </div>
                        </div>

                        <div
                            id="crudo-audit-content"
                            class="crudo-modal-columns crudo-audit-defects-grid"
                            data-crudo-audit-content
                            @if (! $auditExpanded) hidden @endif
                        >
                        <section class="crudo-detail-panel crudo-audit-panel" wire:ignore>
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Checklist de telares reincidentes de defectos</h3>
                                </div>
                                <span class="crudo-detail-count">{{ count($visibleAuditChecklist) }} puntos</span>
                            </div>

                            <fieldset class="crudo-audit-table">
                                <legend class="sr-only">Evaluación del telar {{ $selectedMachine['name'] }}</legend>

                                <div class="crudo-audit-row crudo-audit-header" aria-hidden="true">
                                    <span>Pregunta a auditar</span>
                                    <span>Bien / Mal <i class="fa-solid fa-arrows-rotate"></i></span>
                                </div>

                                @foreach ($visibleAuditChecklist as $item)
                                    <div class="crudo-audit-row">
                                        <p class="crudo-audit-question">
                                            <strong>{{ $loop->iteration }}.</strong>
                                            <span>{{ $item['question'] }}</span>
                                        </p>

                                        <div class="crudo-audit-result-cell">
                                            <button
                                                type="button"
                                                class="crudo-audit-result-button"
                                                data-crudo-audit-result
                                                data-state="empty"
                                                data-question-number="{{ $loop->iteration }}"
                                                data-question-key="{{ $item['key'] }}"
                                                aria-label="Pregunta {{ $loop->iteration }}: Sin evaluar"
                                                title="Clic para cambiar: palomita, tache o vacío"
                                            >
                                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                <span class="sr-only" data-crudo-audit-result-label>Sin evaluar</span>
                                            </button>
                                            <input
                                                type="hidden"
                                                name="checklist[{{ $item['key'] }}]"
                                                value=""
                                                data-crudo-audit-result-input
                                                data-question-key="{{ $item['key'] }}"
                                            >
                                        </div>
                                    </div>
                                @endforeach
                            </fieldset>

                            <label class="crudo-audit-observations">
                                <span>Obs.</span>
                                <textarea
                                    name="crudo-audit-observations"
                                    rows="2"
                                    maxlength="500"
                                    placeholder="Observaciones de la auditoría"
                                ></textarea>
                            </label>
                        </section>

                        <section class="crudo-detail-panel crudo-audit-defects-panel" wire:ignore>
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Defectos encontrados</h3>
                                </div>
                                <span class="crudo-detail-count">Manual</span>
                            </div>

                            <div
                                class="crudo-audit-defect-editor"
                                data-crudo-audit-defects
                                data-crudo-quality-defects-url="{{ route('api.mantenimiento.fallas', ['departamento' => 'Calidad']) }}"
                            >
                                <div class="crudo-audit-defect-list" data-crudo-audit-defect-list>
                                    @for ($auditDefectLine = 1; $auditDefectLine <= 5; $auditDefectLine++)
                                        <div class="crudo-audit-defect-row" data-crudo-audit-defect-row>
                                            <label>
                                                <span>Defecto {{ $auditDefectLine }}</span>
                                                <select
                                                    name="crudo-audit-defect[]"
                                                    data-crudo-quality-defect-select
                                                    aria-label="Defecto encontrado {{ $auditDefectLine }}"
                                                >
                                                    <option value="">Cargando catálogo...</option>
                                                </select>
                                            </label>
                                            <label class="crudo-audit-defect-quantity">
                                                <span>Pzas</span>
                                                <input
                                                    type="number"
                                                    name="crudo-audit-defect-pieces[]"
                                                    min="0"
                                                    step="1"
                                                    inputmode="numeric"
                                                    value="0"
                                                    aria-label="Piezas del defecto {{ $auditDefectLine }}"
                                                >
                                            </label>
                                        </div>
                                    @endfor
                                </div>

                                <p class="crudo-audit-defect-help">
                                    Hasta cinco defectos del catálogo de Calidad.
                                </p>
                            </div>
                        </section>

                        <section class="crudo-detail-panel crudo-audit-history-panel" wire:ignore>
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Auditorías de hoy</h3>
                                </div>
                                <span class="crudo-detail-count" data-crudo-audit-history-count>0</span>
                            </div>

                            <div
                                class="crudo-audit-history-list"
                                data-crudo-audit-history-list
                                aria-live="polite"
                            >
                                <p class="crudo-audit-history-state">Cargando auditorías…</p>
                            </div>
                        </section>
                        </div>

                        <p
                            class="crudo-audit-feedback"
                            data-crudo-audit-feedback
                            role="status"
                            aria-live="polite"
                            hidden
                        ></p>
                    </div>
                </div>
            </article>
        </div>
    @endif
</div>
