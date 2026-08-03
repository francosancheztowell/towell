@php
    $auditChecklist = [
        ['question' => '¿La alineación coincide con la orden?', 'salon' => null],
        ['question' => '¿El dibujo de Jacquard está bien definido?', 'salon' => 'Jacquard'],
        ['question' => '¿Es correcta la identificación en el julio del lote de hilo y proveedor?', 'salon' => null],
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
                                <h2 id="crudo-machine-modal-title">{{ $selectedMachine['name'] }}</h2>
                                @if ($selectedMachine['programa'])
                                    <p class="crudo-modal-program">
                                        {{ $selectedMachine['programa']['nombre'] ?? 'Sin nombre' }}
                                        · Clave {{ $selectedMachine['programa']['clave'] ?? 'N/D' }}
                                    </p>
                                @endif
                            </div>
                        </article>

                        <article
                            class="crudo-modal-kpi crudo-modal-status-card {{ $selectedMachine['paro'] ? 'has-paro' : '' }}"
                            @if ($selectedMachine['paro'])
                                title="{{ implode(' · ', array_filter([
                                    $selectedMachine['paro']['falla'] ?? 'Paro reportado',
                                    $selectedMachine['paro']['descripcion'] ?? null,
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
                            <strong>{{ number_format((float) $selectedMachine['kilos'], 1) }} kg</strong>
                            <small>
                                {{ $turno === 'todos' ? 'Meta a esta hora' : 'Meta' }}
                                {{ number_format((float) $selectedMachine['expectedKilos'], 1) }} kg
                            </small>
                        </article>
                        <article class="crudo-modal-kpi">
                            <span>Calidad</span>
                            <strong>{{ number_format((float) $selectedMachine['qualityPercent'], 1) }}%</strong>
                            <small>{{ number_format((float) $selectedMachine['secondsPercent'], 1) }}% segundas</small>
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
                                            <th>PurchBarcode</th>
                                            <th>Operador</th>
                                            <th>Peso captura (kg)</th>
                                            <th>Piezas</th>
                                            <th>Seg.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($selectedMachine['captures'] as $capture)
                                            <tr>
                                                <td>{{ $capture['purchBarcode'] ?: '—' }}</td>
                                                <td>{{ $capture['operator'] }}</td>
                                                <td>{{ number_format((float) $capture['weight'], 1) }}</td>
                                                <td>{{ number_format((float) $capture['pieces']) }}</td>
                                                <td>{{ number_format((float) $capture['seconds']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5">Sin capturas en el periodo seleccionado.</td></tr>
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
                                <span class="crudo-detail-count">
                                    {{ count($selectedMachine['defects']) }} tipos
                                    · {{ $selectedMachine['defectLineCount'] ?? 0 }} líneas
                                </span>
                            </div>

                            <div class="crudo-defect-table-wrap">
                                <table class="crudo-detail-table crudo-defect-table">
                                    <caption class="sr-only">
                                        Defectos encontrados y desglose por turno
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
                                                    Sin defectos detallados en este periodo.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <section class="crudo-detail-panel crudo-audit-panel" wire:ignore>
                        <div class="crudo-detail-panel-heading crudo-audit-heading">
                            <div>
                                <p class="crudo-eyebrow">Auditoría</p>
                                <h3>Checklist de telares reincidentes de defectos</h3>
                            </div>
                            <span class="crudo-detail-count">{{ count($visibleAuditChecklist) }} puntos</span>
                        </div>

                        <fieldset class="crudo-audit-table">
                            <legend class="sr-only">Evaluación del telar {{ $selectedMachine['name'] }}</legend>

                            <div class="crudo-audit-row crudo-audit-header" aria-hidden="true">
                                <span>Pregunta a auditar</span>
                                <span>Bien <i class="fa-regular fa-circle-check"></i></span>
                                <span>Mal <i class="fa-regular fa-circle-xmark"></i></span>
                            </div>

                            @foreach ($visibleAuditChecklist as $item)
                                <div class="crudo-audit-row">
                                    <p class="crudo-audit-question">
                                        <strong>{{ $loop->iteration }}.</strong>
                                        <span>{{ $item['question'] }}</span>
                                    </p>

                                    <label class="crudo-audit-option crudo-audit-option-good">
                                        <input
                                            type="radio"
                                            name="crudo-audit-{{ $loop->iteration }}"
                                            value="bien"
                                            aria-label="Pregunta {{ $loop->iteration }}: Bien"
                                        >
                                        <span><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                    </label>

                                    <label class="crudo-audit-option crudo-audit-option-bad">
                                        <input
                                            type="radio"
                                            name="crudo-audit-{{ $loop->iteration }}"
                                            value="mal"
                                            aria-label="Pregunta {{ $loop->iteration }}: Mal"
                                        >
                                        <span><i class="fa-solid fa-xmark" aria-hidden="true"></i></span>
                                    </label>
                                </div>
                            @endforeach
                        </fieldset>
                    </section>

                    <footer class="crudo-modal-actions" aria-label="Acciones del detalle del telar">
                        <button
                            type="button"
                            class="crudo-modal-action crudo-modal-action-audit"
                            data-crudo-save-audit
                        >
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                            <span>Guardar auditoría</span>
                        </button>

                        <button
                            type="button"
                            class="crudo-modal-action crudo-modal-action-stop"
                            data-crudo-save-stop
                        >
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            <span>Guardar paro</span>
                        </button>
                    </footer>
                </div>
            </article>
        </div>
    @endif
</div>
