@php
    $statusStyles = [
        'Programado' => 'border-sky-200 bg-sky-50 text-sky-700',
        'En Proceso' => 'border-amber-200 bg-amber-50 text-amber-700',
        'Parcial' => 'border-violet-200 bg-violet-50 text-violet-700',
        'Cancelado' => 'border-rose-200 bg-rose-50 text-rose-700',
    ];
    $qualityMeta = [
        'A' => ['label' => 'Aprobado', 'class' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-check'],
        'R' => ['label' => 'Rechazado', 'class' => 'bg-rose-100 text-rose-700', 'icon' => 'fa-xmark'],
        'O' => ['label' => 'Observado', 'class' => 'bg-amber-100 text-amber-700', 'icon' => 'fa-triangle-exclamation'],
    ];
@endphp

<div
    data-program-board
    data-program-board-shell
    data-module="{{ $moduleMeta['value'] }}"
    class="program-board min-h-full"
    wire:keydown.escape.window="closeModal"
    @if (! $interactionPaused) wire:poll.visible.{{ $pollSeconds }}s="refreshBoard" @endif
>
    @teleport('#program-board-navbar-controls')
        <div class="program-board-navbar" aria-label="Acciones de {{ $moduleMeta['title'] }}">
            @if ($canLoadProduction)
                <button
                    type="button"
                    class="program-board-navbar-button is-primary"
                    wire:click="openProduction"
                    wire:loading.attr="disabled"
                    wire:target="openProduction"
                    @disabled($selectedOrder === null)
                    title="Cargar la orden seleccionada en Producción"
                >
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Cargar</span>
                </button>
            @endif

            @if ($canReprint)
                <a
                    href="{{ $moduleMeta['reprintUrl'] }}"
                    class="program-board-navbar-button is-neutral"
                    title="Consultar reimpresiones"
                >
                    <i class="fa-solid fa-print"></i>
                    <span>Reimpresión</span>
                </a>
            @endif

            <button
                type="button"
                class="program-board-navbar-button is-icon"
                wire:click="refreshBoard"
                wire:loading.attr="disabled"
                wire:target="refreshBoard"
                title="Actualizar órdenes"
                aria-label="Actualizar órdenes"
            >
                <i class="fa-solid fa-rotate" wire:loading.class="fa-spin" wire:target="refreshBoard"></i>
            </button>

            <button
                type="button"
                class="program-board-navbar-button is-icon"
                data-program-fullscreen
                title="Pantalla completa"
                aria-label="Mostrar tablero en pantalla completa"
            >
                <i class="fa-solid fa-expand"></i>
            </button>

            <a
                href="{{ $moduleMeta['legacyUrl'] }}"
                class="program-board-navbar-button is-legacy"
                title="Abrir temporalmente la pantalla anterior"
            >
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Anterior</span>
            </a>
        </div>
    @endteleport

    <div class="program-board-header">
        <div class="min-w-0">
            <p class="program-board-eyebrow">Control operativo</p>
            <h1>{{ $moduleMeta['title'] }}</h1>
            <p>Selecciona una orden para consultar sus acciones. Arrastra desde el control punteado para cambiar prioridad.</p>
        </div>

        <div class="program-board-filters">
            <label class="program-board-search">
                <span class="sr-only">Buscar orden</span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Folio, tamaño, fibra o máquina"
                    autocomplete="off"
                >
                @if ($search !== '')
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        aria-label="Limpiar búsqueda"
                        title="Limpiar búsqueda"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </label>

            <label class="program-board-status-filter">
                <span>Estado</span>
                <select wire:model.live="status">
                    <option value="todos">Todos activos</option>
                    @foreach (\App\Support\Programas\ProgramaConfig::ACTIVE_STATUSES as $statusOption)
                        <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @if ($dataError)
        <div class="program-board-alert" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>No fue posible cargar el tablero</strong>
                <p>{{ $dataError }}</p>
            </div>
            <button type="button" wire:click="refreshBoard">Reintentar</button>
        </div>
    @endif

    <div class="program-board-layout">
        <aside class="program-board-summary" aria-label="Resumen de órdenes visibles">
            <div class="program-board-summary-heading">
                <div>
                    <p class="program-board-eyebrow">Resumen</p>
                    <h2>Órdenes visibles</h2>
                </div>
                <span>{{ $board['summary']['total'] }}</span>
            </div>

            <div class="program-board-kpis">
                <article data-state="programado">
                    <i class="fa-regular fa-calendar-check"></i>
                    <strong>{{ $board['summary']['programado'] }}</strong>
                    <span>Programadas</span>
                </article>
                <article data-state="proceso">
                    <i class="fa-solid fa-gears"></i>
                    <strong>{{ $board['summary']['en_proceso'] }}</strong>
                    <span>En proceso</span>
                </article>
                <article data-state="parcial">
                    <i class="fa-solid fa-chart-pie"></i>
                    <strong>{{ $board['summary']['parcial'] }}</strong>
                    <span>Parciales</span>
                </article>
            </div>

            <div class="program-board-meter-total">
                <span>Metros programados</span>
                <strong>{{ number_format($board['summary']['metros'], 0) }}</strong>
            </div>

            <div class="program-board-guide">
                <i class="fa-solid fa-grip-vertical"></i>
                <p>La prioridad solo se intercambia dentro de la misma máquina.</p>
            </div>
        </aside>

        <main
            class="program-board-lanes {{ $moduleMeta['isUrdido'] ? 'is-urdido' : 'is-engomado' }}"
            aria-label="Órdenes por máquina"
        >
            @foreach ($board['lanes'] as $lane)
                <section class="program-board-lane" wire:key="lane-{{ $moduleMeta['value'] }}-{{ $lane['key'] }}">
                    <header>
                        <div class="program-board-lane-code">{{ $lane['short'] }}</div>
                        <div>
                            <h2>{{ $lane['label'] }}</h2>
                            <p>{{ count($lane['orders']) }} {{ count($lane['orders']) === 1 ? 'orden' : 'órdenes' }}</p>
                        </div>
                        <span>{{ count($lane['orders']) }}</span>
                    </header>

                    <div
                        class="program-board-order-list"
                        data-program-lane-list
                        data-lane="{{ $lane['key'] }}"
                    >
                        @forelse ($lane['orders'] as $order)
                            @php
                                $selected = $selectedOrderId === (int) $order['id'];
                                $quality = $qualityMeta[$order['quality']] ?? null;
                            @endphp
                            <article
                                class="program-board-order {{ $selected ? 'is-selected' : '' }}"
                                data-program-order
                                data-order-id="{{ $order['id'] }}"
                                data-lane="{{ $lane['key'] }}"
                                wire:key="order-{{ $moduleMeta['value'] }}-{{ $order['id'] }}"
                                wire:click="selectOrder({{ $order['id'] }})"
                                tabindex="0"
                                role="button"
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                            >
                                <div class="program-board-order-top">
                                    <button
                                        type="button"
                                        class="program-board-drag-handle"
                                        data-drag-handle
                                        title="Arrastrar para cambiar prioridad"
                                        aria-label="Mover prioridad de {{ $order['folio'] }}"
                                    >
                                        <i class="fa-solid fa-grip-vertical"></i>
                                    </button>
                                    <span class="program-board-priority" title="Prioridad">
                                        {{ $order['priority'] }}
                                    </span>
                                    <div class="min-w-0">
                                        <h3 title="{{ $order['folio'] }}">{{ $order['folio'] }}</h3>
                                        <p>{{ $order['machine'] }}</p>
                                    </div>
                                    <span class="program-board-status {{ $statusStyles[$order['status']] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">
                                        {{ $order['status'] }}
                                    </span>
                                </div>

                                <div class="program-board-order-data">
                                    <div>
                                        <span>{{ $moduleMeta['isUrdido'] && $lane['key'] === '4' ? 'Barras' : 'Tipo' }}</span>
                                        <strong>{{ $order['type'] !== '' ? $order['type'] : '—' }}</strong>
                                    </div>
                                    <div>
                                        <span>Cuenta / calibre</span>
                                        <strong title="{{ $order['size'] }}">{{ $order['size'] !== '' ? $order['size'] : '—' }}</strong>
                                    </div>
                                    <div>
                                        <span>Configuración</span>
                                        <strong title="{{ $order['configuration'] }}">{{ $order['configuration'] !== '' ? $order['configuration'] : '—' }}</strong>
                                    </div>
                                    <div>
                                        <span>Metros</span>
                                        <strong>{{ number_format($order['meters'], 0) }}</strong>
                                    </div>
                                </div>

                                @if (! $moduleMeta['isUrdido'] && $order['formula'] !== '')
                                    <div class="program-board-formula">
                                        <i class="fa-solid fa-flask"></i>
                                        <span>{{ $order['formula'] }}</span>
                                    </div>
                                @endif

                                <footer>
                                    <span class="program-board-observation {{ $order['observations'] !== '' ? 'has-content' : '' }}">
                                        <i class="fa-regular fa-message"></i>
                                        {{ $order['observations'] !== '' ? $order['observations'] : 'Sin observaciones' }}
                                    </span>

                                    @if ($moduleMeta['isUrdido'])
                                        <span class="program-board-quality {{ $quality['class'] ?? 'bg-slate-100 text-slate-500' }}">
                                            <i class="fa-solid {{ $quality['icon'] ?? 'fa-minus' }}"></i>
                                            {{ $quality['label'] ?? 'Sin evaluar' }}
                                        </span>
                                    @elseif (! $order['urdido_finished'])
                                        <span class="program-board-prerequisite">
                                            <i class="fa-solid fa-lock"></i>
                                            Urdido pendiente
                                        </span>
                                    @endif
                                </footer>
                            </article>
                        @empty
                            <div class="program-board-empty">
                                <i class="fa-regular fa-folder-open"></i>
                                <strong>Sin órdenes</strong>
                                <p>No hay resultados para esta máquina con los filtros actuales.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </main>
    </div>

    @if ($selectedOrder)
        <section class="program-board-selection" aria-label="Orden seleccionada">
            <div class="program-board-selection-main">
                <span>Orden seleccionada</span>
                <strong>{{ $selectedOrder['folio'] }}</strong>
                <small>{{ $selectedOrder['machine'] }} · Prioridad {{ $selectedOrder['priority'] }}</small>
            </div>

            @if ($canEdit)
                <div class="program-board-status-action">
                    <label for="program-board-pending-status">Cambiar estado</label>
                    <select id="program-board-pending-status" wire:model="pendingStatus">
                        @foreach ($statusOptions as $statusOption)
                            @php
                                $opcionBloqueadaPorAx = ($selectedOrder['bloqueado_por_ax'] ?? false)
                                    && in_array($statusOption, $statusBloqueadosPorAx, true)
                                    && $statusOption !== ($selectedOrder['status'] ?? '');
                            @endphp
                            <option
                                value="{{ $statusOption }}"
                                @disabled($opcionBloqueadaPorAx)
                                title="{{ $opcionBloqueadaPorAx ? 'Este folio ya tiene producción en AX (AX = 1).' : '' }}"
                            >{{ $statusOption }}</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        wire:click="changeStatus"
                        wire:loading.attr="disabled"
                        wire:target="changeStatus,confirmCancellation"
                    >
                        Aplicar
                    </button>
                    @error('pendingStatus')
                        <p>{{ $message }}</p>
                    @enderror
                </div>

                <button type="button" class="program-board-selection-button" wire:click="openObservations">
                    <i class="fa-regular fa-message"></i>
                    Observaciones
                </button>
            @endif

            @if ($canEvaluateQuality)
                <button type="button" class="program-board-selection-button is-quality" wire:click="openQuality">
                    <i class="fa-solid fa-clipboard-check"></i>
                    Calidad
                </button>
            @endif

            @if ($canLoadProduction)
                <button
                    type="button"
                    class="program-board-selection-button is-production"
                    wire:click="openProduction"
                    wire:loading.attr="disabled"
                    wire:target="openProduction"
                >
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    Ir a producción
                </button>
            @endif

            <button
                type="button"
                class="program-board-selection-close"
                wire:click="clearSelection"
                aria-label="Quitar selección"
                title="Quitar selección"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </section>
    @endif

    <div class="program-board-loading" wire:loading.delay.long wire:target="search,status,refreshBoard,reorder">
        <i class="fa-solid fa-rotate fa-spin"></i>
        <span>Actualizando tablero…</span>
    </div>

    @teleport('body')
        <div>
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
