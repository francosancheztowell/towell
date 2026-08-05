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
    $programOrder = trim((string) (
        $selectedMachine['programa']['orden']
        ?? $selectedMachine['programa']['clave']
        ?? ''
    ));
    $modelKey = trim((string) ($selectedMachine['programa']['claveModelo'] ?? ''));
    $itemId = trim((string) ($selectedMachine['programa']['itemId'] ?? ''));
@endphp

<div>
    @if ($selectedMachine)
        @if (! $auditModalOpen)
        <div
            class="crudo-modal-backdrop"
            wire:click.self="close"
            wire:loading.class="is-closing"
            wire:target="openAudit"
            wire:key="crudo-machine-detail-modal-{{ $selectedMachine['telar'] }}"
            data-crudo-modal
            data-crudo-detail-modal
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
                    wire:target="loadDetail,refreshDetail"
                >
                    <section class="crudo-modal-overview" data-state="{{ $selectedMachine['state'] }}">
                        <article class="crudo-modal-identity-card">
                            <span class="crudo-modal-machine-icon">
                                <i class="fa-solid fa-industry"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="crudo-modal-location">
                                    {{ $selectedMachine['salon'] }} · {{ $selectedMachine['group'] ?: 'Sin grupo' }}
                                </p>
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
                                {{ $modo === 'rango' ? 'Meta diaria promedio' : 'Meta al día' }}
                                @if ((float) ($selectedMachine['dailyTargetKilos'] ?? 0) > 0)
                                    {{ number_format(round((float) $selectedMachine['dailyTargetKilos'])) }} kg
                                @else
                                    sin estándar
                                @endif
                            </small>
                            <small>
                                {{ $modo === 'rango' ? 'Meta acumulada del rango' : 'Meta a esta hora' }}
                                @if (($selectedMachine['productionStandardStatus'] ?? 'missing') === 'complete')
                                    {{ number_format(round((float) $selectedMachine['expectedKilos'])) }} kg
                                @elseif (($selectedMachine['productionStandardStatus'] ?? 'missing') === 'partial')
                                    {{ number_format(round((float) $selectedMachine['expectedKilos'])) }} kg parcial
                                @else
                                    sin estándar
                                @endif
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

                    @if (! $detailLoaded)
                        <div
                            class="crudo-modal-skeleton"
                            wire:init="loadDetail"
                            aria-label="Cargando capturas y defectos del telar"
                        >
                            <div class="crudo-skeleton-columns" aria-hidden="true">
                                <div class="crudo-skeleton-block" style="height: 14rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 14rem;"></div>
                                <div class="crudo-skeleton-block" style="height: 14rem;"></div>
                            </div>
                            <p class="crudo-detail-loading-copy">Consultando capturas y defectos…</p>
                        </div>
                    @else
                    @php
                        $flogBarcodes = array_values(array_unique(array_filter(array_map(
                            static fn (array $capture): string => trim((string) ($capture['purchBarcode'] ?? '')),
                            $selectedMachine['captures'],
                        ))));
                    @endphp
                    <div class="crudo-production-detail-grid">
                        <section class="crudo-detail-panel crudo-orders-panel">
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Órdenes y turnos</h3>
                                </div>
                                <span>{{ $selectedMachine['captureCount'] }}</span>
                            </div>

                            <div class="crudo-detail-table-scroll crudo-orders-table-wrap">
                                <table class="crudo-detail-table crudo-orders-table">
                                    <colgroup>
                                        <col class="crudo-orders-col-date">
                                        <col class="crudo-orders-col-roll">
                                        <col class="crudo-orders-col-order">
                                        <col class="crudo-orders-col-number">
                                        <col class="crudo-orders-col-number">
                                        <col class="crudo-orders-col-number">
                                        <col class="crudo-orders-col-lot">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>No. Rollo</th>
                                            <th>Orden</th>
                                            <th>Kg</th>
                                            <th>Pzas</th>
                                            <th>2das</th>
                                            <th>Lote</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($selectedMachine['captures'] as $capture)
                                            <tr>
                                                <td title="{{ $capture['date'] ?? '' }}">{{ ($capture['date'] ?? '') ?: '—' }}</td>
                                                <td title="{{ $capture['purchBarcode'] ?? '' }}">{{ ($capture['purchBarcode'] ?? '') ?: '—' }}</td>
                                                <td title="{{ $capture['weavingOrder'] ?? '' }}">{{ ($capture['weavingOrder'] ?? '') ?: '—' }}</td>
                                                <td>{{ number_format((float) $capture['weight'], 1) }}</td>
                                                <td>{{ number_format((int) $capture['pieces']) }}</td>
                                                <td>{{ number_format((int) $capture['seconds']) }}</td>
                                                <td title="{{ $capture['supplierLot'] ?? '' }}">{{ ($capture['supplierLot'] ?? '') ?: '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7">Sin capturas en el periodo seleccionado.</td></tr>
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

                            <div class="crudo-detail-table-scroll crudo-defect-table-wrap">
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

                        <livewire:crudo.machine-flog-summary
                            :program="$selectedMachine['programa'] ?? null"
                            :purch-barcodes="$flogBarcodes"
                            :key="'crudo-flog-'.($selectedMachine['telar'] ?? 'unknown').'-'.$fecha.'-'.$turno"
                        />
                    </div>
                    @endif

                    <div
                        class="crudo-audit-disclosure"
                        wire:key="crudo-audit-history-{{ $selectedMachine['telar'] }}"
                    >
                        <section
                            class="crudo-detail-panel crudo-audit-history-panel"
                            wire:ignore
                            data-crudo-audit-history
                            data-crudo-audit-history-url="{{ route('crudo.auditorias.today', ['telar' => $selectedMachine['telar']]) }}"
                            data-crudo-audit-telar="{{ $selectedMachine['telar'] }}"
                        >
                            <div class="crudo-detail-panel-heading">
                                <div>
                                    <h3>Auditorías de hoy</h3>
                                </div>
                                <span class="crudo-detail-count" data-crudo-audit-history-count>…</span>
                            </div>

                            <div
                                class="crudo-audit-history-list"
                                data-crudo-audit-history-list
                                aria-live="polite"
                            >
                                <p class="crudo-audit-history-state">Cargando auditorías…</p>
                            </div>
                        </section>

                        @if ($canRegisterAudit)
                            <div class="crudo-audit-toolbar">
                                <button
                                    type="button"
                                    class="crudo-audit-toggle"
                                    wire:click="openAudit"
                                    wire:loading.attr="disabled"
                                    wire:target="openAudit"
                                    data-crudo-open-audit
                                >
                                    <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                                    <span>Agregar auditoría</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        </div>
        @else
        <div
            class="crudo-modal-backdrop"
            wire:click.self="close"
            wire:key="crudo-machine-audit-modal-{{ $selectedMachine['telar'] }}"
            data-crudo-modal
            data-crudo-audit-modal
            role="dialog"
            aria-modal="true"
            aria-labelledby="crudo-audit-modal-title"
        >
            <article class="crudo-modal crudo-audit-modal">
                <button
                    type="button"
                    class="crudo-modal-close"
                    wire:click="close"
                    data-crudo-modal-close
                    aria-label="Cerrar auditoría"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <div class="crudo-modal-body crudo-audit-modal-body">
                    <header class="crudo-audit-modal-header">
                        <span class="crudo-audit-modal-icon" aria-hidden="true">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </span>
                        <div>
                            <p>{{ $selectedMachine['salon'] }} · Telar {{ $selectedMachine['telar'] }}</p>
                            <h2 id="crudo-audit-modal-title">Nueva auditoría · {{ $selectedMachine['name'] }}</h2>
                            @if ($programOrder !== '')
                                <span>Orden {{ $programOrder }}</span>
                            @endif
                        </div>
                    </header>

                    <div
                        class="crudo-audit-modal-form"
                        wire:key="crudo-audit-form-{{ $selectedMachine['telar'] }}"
                        data-crudo-audit-form
                        data-crudo-audit-url="{{ route('crudo.auditorias.store') }}"
                        data-crudo-audit-stop-url="{{ route('crudo.auditorias.store-with-stop') }}"
                        data-crudo-audit-telar="{{ $selectedMachine['telar'] }}"
                        data-crudo-audit-salon="{{ $selectedMachine['salon'] }}"
                        data-crudo-audit-order="{{ $programOrder }}"
                    >
                        <div
                            id="crudo-audit-content"
                            class="crudo-audit-modal-grid"
                            data-crudo-audit-content
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
                                    <span>Observaciones</span>
                                    <textarea
                                        name="crudo-audit-observations"
                                        rows="3"
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
                        </div>

                        <p
                            class="crudo-audit-feedback"
                            data-crudo-audit-feedback
                            role="status"
                            aria-live="polite"
                            hidden
                        ></p>

                        <div class="crudo-modal-actions crudo-audit-modal-actions" aria-label="Acciones de auditoría">
                            <button
                                type="button"
                                class="crudo-modal-action crudo-modal-action-audit"
                                data-crudo-save-audit
                                disabled
                                aria-disabled="true"
                            >
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                <span>Guardar auditoría</span>
                            </button>

                            <button
                                type="button"
                                class="crudo-modal-action crudo-modal-action-stop"
                                data-crudo-save-stop
                                disabled
                                aria-disabled="true"
                            >
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                <span>Guardar paro</span>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </div>
        @endif
    @endif
</div>
