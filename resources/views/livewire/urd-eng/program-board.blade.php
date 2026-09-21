@php
    $qualityMeta = [
        'A' => ['label' => 'Aprobado', 'class' => 'text-emerald-600', 'icon' => 'fa-check'],
        'R' => ['label' => 'Rechazado', 'class' => 'text-red-600', 'icon' => 'fa-xmark'],
        'O' => ['label' => 'Observado', 'class' => 'text-amber-600', 'icon' => 'fa-triangle-exclamation'],
    ];
@endphp

<div data-program-board data-program-board-shell data-module="{{ $moduleMeta['value'] }}"
    class="program-board" x-data="{ visualSelection: $wire.entangle('selectedOrderId') }"
    wire:keydown.escape.window="closeModal"
    @if (! $interactionPaused) wire:poll.visible.{{ $pollSeconds }}s="refreshBoard" @endif>
    @teleport('#program-board-navbar-controls')
        <div class="program-board-navbar" aria-label="Acciones de {{ $moduleMeta['title'] }}">
            @if ($canLoadProduction)
                <button type="button" class="program-board-navbar-button is-primary" wire:click="openProduction"
                    wire:loading.attr="disabled" @disabled($selectedOrder === null) title="Cargar la orden seleccionada">
                    <i class="fa-solid fa-download"></i><span>Cargar</span>
                </button>
            @endif
            @if ($canEdit)
                <button type="button" class="program-board-navbar-button is-priority" wire:click="openPriority"
                    wire:loading.attr="disabled" @disabled($selectedOrder === null) title="Editar prioridad de la orden seleccionada">
                    <i class="fa-solid fa-sort-numeric-up"></i><span>Editar Prioridad</span>
                </button>
            @endif
            @if ($canReprint)
                <a href="{{ $moduleMeta['reprintUrl'] }}" class="program-board-navbar-button is-reprint" aria-label="Edición" title="Edición">
                    <i class="fa-solid fa-pen-to-square"></i><span>Edición</span>
                </a>
            @endif
            @if ($canEvaluateQuality)
                <button type="button" class="program-board-navbar-button is-quality" wire:click="openQuality" aria-label="Calidad" title="Calidad"
                    wire:loading.attr="disabled" @disabled($selectedOrder === null)>
                    <i class="fa-solid fa-clipboard-check"></i><span>Calidad</span>
                </button>
            @endif
        </div>
    @endteleport

    @if ($dataError)
        <div class="program-board-alert" role="alert">
            <span>{{ $dataError }}</span>
            <button type="button" wire:click="refreshBoard">Reintentar</button>
        </div>
    @endif
    @error('pendingStatus')
        <p class="program-board-field-error" role="alert">{{ $message }}</p>
    @enderror
    @if ($search !== '' || $status !== 'todos')
        <div class="program-board-alert">
            <span>Hay filtros activos: {{ $search }} {{ $status !== 'todos' ? $status : '' }}</span>
            <button type="button" wire:click="clearFilters">Mostrar todas las órdenes</button>
        </div>
    @endif

    <div class="program-board-lanes {{ $moduleMeta['isUrdido'] ? 'is-urdido' : 'is-engomado' }}" aria-label="Órdenes por máquina">
        @foreach ($board['lanes'] as $lane)
            <section class="program-board-lane" wire:key="lane-{{ $moduleMeta['value'] }}-{{ $lane['key'] }}">
                <h2>{{ $lane['label'] }}</h2>
                <div class="program-board-table-scroll" tabindex="0" role="region" aria-label="Tabla desplazable de {{ $lane['label'] }}">
                    <table class="program-board-table" aria-label="Órdenes de {{ $lane['label'] }}">
                        <thead><tr>
                            <th scope="col">Prioridad</th><th scope="col">Folio</th>
                            <th scope="col">{{ $moduleMeta['isUrdido'] && (string) $lane['key'] === '4' ? 'Barras' : 'Tipo' }}</th>
                            <th scope="col">Cuenta/Calibre</th><th scope="col">Configuración</th><th scope="col">Metros</th>
                            @if (! $moduleMeta['isUrdido'])<th scope="col">Fórmula</th>@endif
                            <th scope="col">Status</th><th scope="col">Observaciones</th>
                            @if ($moduleMeta['isUrdido'])<th scope="col">Calidad</th>@endif
                        </tr></thead>
                        <tbody data-program-lane-list data-lane="{{ $lane['key'] }}">
                            @forelse ($lane['orders'] as $order)
                                @php
                                    $selected = $selectedOrderId === (int) $order['id'];
                                    $qualityInfo = $qualityMeta[$order['quality']] ?? null;
                                @endphp
                                <tr class="program-board-order"
                                    x-bind:class="{ 'is-selected': Number(visualSelection) === {{ $order['id'] }} }"
                                    x-on:click="visualSelection = {{ $order['id'] }}"
                                    x-on:keydown.enter.self="visualSelection = {{ $order['id'] }}"
                                    data-program-order data-order-id="{{ $order['id'] }}" data-lane="{{ $lane['key'] }}"
                                    wire:key="order-{{ $moduleMeta['value'] }}-{{ $order['id'] }}"
                                    wire:click="selectOrder({{ $order['id'] }})" wire:keydown.enter.self="selectOrder({{ $order['id'] }})"
                                    tabindex="0" x-bind:aria-selected="Number(visualSelection) === {{ $order['id'] }} ? 'true' : 'false'">
                                    <td class="program-board-priority-cell">
                                        @if ($canEdit)
                                            <button type="button" class="program-board-drag-handle" data-drag-handle
                                                title="Arrastrar para cambiar prioridad" aria-label="Mover prioridad de {{ $order['folio'] }}">
                                                <i class="fa-solid fa-grip-vertical"></i>
                                            </button>
                                        @endif
                                        {{ $order['priority'] }}
                                    </td>
                                    <td>{{ $order['folio'] }}</td>
                                    <td><span class="program-board-type {{ strtolower($order['type']) === 'rizo' ? 'is-rizo' : (strtolower($order['type']) === 'pie' ? 'is-pie' : '') }}">{{ $order['type'] ?: '—' }}</span></td>
                                    <td>{{ $order['size'] ?: '—' }}</td>
                                    <td>{{ $order['configuration'] ?: '—' }}</td>
                                    <td>{{ number_format($order['meters'], 0, '.', '') }}</td>
                                    @if (! $moduleMeta['isUrdido'])<td>{{ $order['formula'] ?: '—' }}</td>@endif
                                    <td>
                                        @if ($canEdit)
                                            <select aria-label="Status de {{ $order['folio'] }}" wire:click.stop
                                                wire:key="status-{{ $order['id'] }}-{{ $order['status'] }}-{{ $selected ? $pendingStatus : '' }}-{{ $showCancellationConfirmation ? 'confirm' : 'normal' }}"
                                                wire:change="changeOrderStatus({{ $order['id'] }}, $event.target.value)"
                                                wire:loading.attr="disabled" wire:target="changeOrderStatus,confirmCancellation">
                                                @foreach ($statusOptions as $statusOption)
                                                    <option value="{{ $statusOption }}" @selected($order['status'] === $statusOption)
                                                        @disabled(($order['bloqueado_por_ax'] ?? false) && in_array($statusOption, $statusBloqueadosPorAx, true) && $statusOption !== $order['status'])>{{ $statusOption }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $order['status'] }}
                                        @endif
                                    </td>
                                    <td class="program-board-observations-cell">
                                        @if ($canEdit)
                                            <button type="button" class="program-board-observation {{ $order['observations'] === '' ? 'is-empty' : '' }}"
                                                wire:click.stop="editOrderObservations({{ $order['id'] }})" title="Editar observaciones de {{ $order['folio'] }}">
                                                {{ $order['observations'] ?: 'Escriba observaciones...' }}
                                            </button>
                                        @else
                                            {{ $order['observations'] }}
                                        @endif
                                    </td>
                                    @if ($moduleMeta['isUrdido'])
                                        <td class="program-board-quality-cell" title="{{ $qualityInfo['label'] ?? 'Sin evaluar' }}">
                                            <i class="fa-solid {{ $qualityInfo['icon'] ?? 'fa-minus' }} {{ $qualityInfo['class'] ?? 'text-gray-400' }}" aria-label="{{ $qualityInfo['label'] ?? 'Sin evaluar' }}"></i>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="9" class="program-board-empty">No hay órdenes pendientes</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
    @if ($selectedOrder)
        <p class="sr-only" role="status">Orden seleccionada: {{ $selectedOrder['folio'] }}</p>
    @endif
    <div class="program-board-loading" wire:loading.delay.long wire:target="refreshBoard,reorder,savePriority,changeOrderStatus">
        <i class="fa-solid fa-rotate fa-spin"></i><span>Actualizando…</span>
    </div>

    @teleport('body')
        <div>
            @if ($showPriority)
                @php
                    $priorityChoices = collect($board['lanes'])->flatMap(fn ($lane) => $lane['orders'])
                        ->filter(fn ($order) => $selectedOrder && $order['lane'] === $selectedOrder['lane'] && $order['id'] !== $selectedOrder['id']);
                    $priorityTarget = $priorityChoices->firstWhere('id', (int) $priorityTargetId);
                @endphp
                <div class="program-board-modal-backdrop" wire:click.self="closeModal">
                    <section class="program-board-modal" role="dialog" aria-modal="true" aria-labelledby="priority-title">
                        <header>
                            <h2 id="priority-title">Cambiar prioridad</h2>
                            <button type="button" wire:click="closeModal" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                        </header>
                        <div class="program-board-priority-context">
                            <span>{{ $selectedOrder['machine'] ?? '' }}</span>
                            <strong>Orden {{ $selectedOrder['folio'] ?? '' }}</strong>
                            <span>Prioridad actual: <b>{{ $selectedOrder['priority'] ?? '—' }}</b></span>
                        </div>
                        <form wire:submit="savePriority">
                            @if ($priorityChoices->isEmpty())
                                <p class="program-board-priority-help">No hay otra orden en esta máquina para intercambiar la prioridad.</p>
                            @else
                                <label>
                                    <span>¿Qué prioridad quieres darle?</span>
                                    <select wire:model.live="priorityTargetId" required>
                                        <option value="">Selecciona la nueva prioridad</option>
                                        @foreach ($priorityChoices as $priorityOrder)
                                            <option value="{{ $priorityOrder['id'] }}">Prioridad {{ $priorityOrder['priority'] }} · Orden {{ $priorityOrder['folio'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <p class="program-board-priority-help">Las dos órdenes intercambiarán sus prioridades.</p>
                            @endif
                            @if ($priorityTarget)
                                <div class="program-board-priority-preview" aria-live="polite">
                                    <strong>Así quedarán al guardar</strong>
                                    <table>
                                        <thead><tr><th>Orden</th><th>Actual</th><th>Nueva</th></tr></thead>
                                        <tbody>
                                            <tr><td>{{ $selectedOrder['folio'] }}</td><td>{{ $selectedOrder['priority'] }}</td><td><b>{{ $priorityTarget['priority'] }}</b></td></tr>
                                            <tr><td>{{ $priorityTarget['folio'] }}</td><td>{{ $priorityTarget['priority'] }}</td><td><b>{{ $selectedOrder['priority'] }}</b></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            @error('priorityTargetId')<p class="program-board-field-error">{{ $message }}</p>@enderror
                            <footer><button type="submit" class="is-primary" wire:loading.attr="disabled" @disabled(! $priorityTarget)>Guardar prioridades</button></footer>
                        </form>
                    </section>
                </div>
            @endif

            @if ($showObservations)
                <div class="program-board-modal-backdrop" role="presentation" wire:click.self="closeModal">
                    <section class="program-board-modal" role="dialog" aria-modal="true" aria-labelledby="observations-title">
                        <header>
                            <div>
                                <p class="program-board-eyebrow">Orden {{ $selectedOrderId }}</p>
                                <h2 id="observations-title">Observaciones</h2>
                            </div>
                            <button type="button" wire:click="closeModal" aria-label="Cerrar">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </header>
                        <form wire:submit="saveObservations">
                            <label>
                                <span>Notas operativas</span>
                                <textarea
                                    wire:model="observations"
                                    rows="6"
                                    maxlength="{{ $observationsMaxLength }}"
                                    autofocus
                                ></textarea>
                                <small>{{ mb_strlen($observations) }} / {{ $observationsMaxLength }}</small>
                            </label>
                            @error('observations')
                                <p class="program-board-field-error">{{ $message }}</p>
                            @enderror
                            <footer>
                                <button type="button" class="is-secondary" wire:click="closeModal">Cerrar</button>
                                <button type="submit" class="is-primary" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="saveObservations">Guardar</span>
                                    <span wire:loading wire:target="saveObservations">Guardando…</span>
                                </button>
                            </footer>
                        </form>
                    </section>
                </div>
            @endif

            @if ($showQuality)
                <div class="program-board-modal-backdrop" role="presentation" wire:click.self="closeModal">
                    <section class="program-board-modal" role="dialog" aria-modal="true" aria-labelledby="quality-title">
                        <header>
                            <div>
                                <p class="program-board-eyebrow">Evaluación de Urdido</p>
                                <h2 id="quality-title">Calidad de la orden</h2>
                            </div>
                            <button type="button" wire:click="closeModal" aria-label="Cerrar">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </header>
                        <form wire:submit="saveQuality">
                            <fieldset class="program-board-quality-options">
                                <legend>Resultado</legend>
                                @foreach ([
                                    'A' => ['Aprobado', 'fa-check', 'is-approved'],
                                    'R' => ['Rechazado', 'fa-xmark', 'is-rejected'],
                                    'O' => ['Con observaciones', 'fa-triangle-exclamation', 'is-observed'],
                                ] as $value => [$label, $icon, $class])
                                    <label class="{{ $class }}">
                                        <input type="radio" wire:model="quality" value="{{ $value }}">
                                        <i class="fa-solid {{ $icon }}"></i>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @error('quality')
                                <p class="program-board-field-error">{{ $message }}</p>
                            @enderror

                            <label>
                                <span>Comentario</span>
                                <textarea
                                    wire:model="qualityComment"
                                    rows="4"
                                    maxlength="{{ $qualityCommentMaxLength }}"
                                ></textarea>
                                <small>{{ mb_strlen($qualityComment) }} / {{ $qualityCommentMaxLength }}</small>
                            </label>
                            @error('qualityComment')
                                <p class="program-board-field-error">{{ $message }}</p>
                            @enderror

                            <footer>
                                <button type="button" class="is-secondary" wire:click="closeModal">Cerrar</button>
                                <button type="submit" class="is-primary" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="saveQuality">Guardar</span>
                                    <span wire:loading wire:target="saveQuality">Guardando…</span>
                                </button>
                            </footer>
                        </form>
                    </section>
                </div>
            @endif

            @if ($showCancellationConfirmation)
                <div class="program-board-modal-backdrop" role="presentation" wire:click.self="closeModal">
                    <section class="program-board-modal is-danger" role="alertdialog" aria-modal="true" aria-labelledby="cancel-title">
                        <header>
                            <div>
                                <p class="program-board-eyebrow">Acción destructiva</p>
                                <h2 id="cancel-title">¿Cancelar esta orden?</h2>
                            </div>
                            <button type="button" wire:click="closeModal" aria-label="Cerrar">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </header>
                        <div class="program-board-danger-copy">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong>La cancelación elimina los registros de producción relacionados.</strong>
                                <p>La orden saldrá del tablero activo y sus prioridades serán recalculadas.</p>
                            </div>
                        </div>
                        @error('pendingStatus')
                            <p class="program-board-field-error">{{ $message }}</p>
                        @enderror
                        <footer>
                            <button type="button" class="is-secondary" wire:click="closeModal">Conservar orden</button>
                            <button
                                type="button"
                                class="is-danger"
                                wire:click="confirmCancellation"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="confirmCancellation">Sí, cancelar</span>
                                <span wire:loading wire:target="confirmCancellation">Cancelando…</span>
                            </button>
                        </footer>
                    </section>
                </div>
            @endif
        </div>
    @endteleport
</div>
