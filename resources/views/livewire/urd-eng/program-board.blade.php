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
                <div class="program-board-modal-backdrop" wire:click.self="closeModal">
                    <section class="program-board-modal is-wide" role="dialog" aria-modal="true" aria-labelledby="priority-title" data-board-id="{{ $this->getId() }}">
                        <header>
                            <h2 id="priority-title">Editar prioridad de órdenes</h2>
                            <button type="button" wire:click="closeModal" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                        </header>
                        <div class="program-board-priority-table-wrap">
                            <table class="program-board-priority-table">
                                <thead>
                                    <tr>
                                        <th>Prioridad</th>
                                        <th>Folio</th>
                                        <th>Tipo</th>
                                        <th>Cuenta/Calibre</th>
                                        <th>Configuración</th>
                                        <th>Metros</th>
                                        <th>Máquina</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="priority-sort-body">
                                    @forelse ($priorityRows as $row)
                                        <tr draggable="true" data-priority-id="{{ $row['id'] }}">
                                            <td><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i> {{ $row['priority'] }}</td>
                                            <td>{{ $row['folio'] }}</td>
                                            <td>{{ $row['type'] }}</td>
                                            <td>{{ $row['size'] }}</td>
                                            <td>{{ $row['configuration'] }}</td>
                                            <td>{{ $row['meters'] !== null && $row['meters'] !== '' ? (int) round((float) $row['meters']) : '' }}</td>
                                            <td>{{ $row['machine'] }}</td>
                                            <td>{{ $row['status'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8">No hay órdenes disponibles</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @error('priorityRows')<p class="program-board-field-error">{{ $message }}</p>@enderror
                        <footer>
                            <button type="button" class="is-secondary" wire:click="closeModal">Cancelar</button>
                            <button type="button" class="is-primary" wire:click="savePriority" wire:loading.attr="disabled" @disabled($priorityRows === [])>Guardar cambios</button>
                        </footer>
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
                                <h2 id="quality-title">Evaluación de calidad</h2>
                            </div>
                            <button type="button" wire:click="closeModal" aria-label="Cerrar">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </header>
                        <form wire:submit="saveQuality">
                            <p class="program-board-eyebrow">Folio {{ $selectedOrder['folio'] ?? '' }}</p>
                            <div class="program-board-checklist">
                                @foreach (\App\Models\Urdido\UrdProgramaUrdido::CALIDAD_PUNTOS as $campo => $etiqueta)
                                    @php $valor = $qualityPoints[$campo] ?? null; @endphp
                                    <div class="program-board-checklist-row">
                                        <span>{{ $etiqueta }}</span>
                                        <button type="button" wire:click="toggleQualityPoint('{{ $campo }}')"
                                            class="{{ $valor === true ? 'is-good' : ($valor === false ? 'is-bad' : '') }}"
                                            title="Clic para alternar bueno / malo">
                                            {{ $valor === true ? '✓' : ($valor === false ? '✕' : '—') }}
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @error('quality')
                                <p class="program-board-field-error">{{ $message }}</p>
                            @enderror

                            <label>
                                <span>Observaciones</span>
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

<script>
    (function () {
        if (window.__prioritySortBound) {
            return;
        }
        window.__prioritySortBound = true;

        document.addEventListener('dragstart', function (event) {
            const row = event.target.closest('#priority-sort-body tr[data-priority-id]');
            if (!row) {
                return;
            }
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.priorityId);
        });

        document.addEventListener('dragover', function (event) {
            if (!event.target.closest('#priority-sort-body')) {
                return;
            }
            event.preventDefault();
        });

        document.addEventListener('drop', function (event) {
            const body = event.target.closest('#priority-sort-body');
            if (!body) {
                return;
            }
            event.preventDefault();
            const dragging = body.querySelector('tr.is-dragging');
            const target = event.target.closest('tr[data-priority-id]');
            if (!dragging || !target || dragging === target) {
                return;
            }
            const rect = target.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            body.insertBefore(dragging, after ? target.nextSibling : target);
            const ids = Array.from(body.querySelectorAll('tr[data-priority-id]')).map(function (row) {
                return row.dataset.priorityId;
            });
            const section = body.closest('[data-board-id]');
            if (section && window.Livewire) {
                window.Livewire.find(section.dataset.boardId).call('reorderPriorities', ids);
            }
        });

        document.addEventListener('dragend', function (event) {
            const row = event.target.closest('tr');
            if (row) {
                row.classList.remove('is-dragging');
            }
        });
    })();
</script>
