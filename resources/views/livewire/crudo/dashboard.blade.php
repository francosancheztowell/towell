@php
    $areaSalonLabels = ['Karl Mayer' => 'KM', 'Jacquard' => 'JAC', 'Smith' => 'SMI'];
    $statusCards = [
        ['key' => 'paro', 'label' => 'Paro', 'description' => 'Máquina detenida', 'icon' => 'fa-triangle-exclamation'],
        ['key' => 'bad_quality', 'label' => 'Mala calidad', 'description' => 'Máquinas en alerta', 'icon' => 'fa-circle-xmark'],
        ['key' => 'low_kilos', 'label' => 'Bajos kg', 'description' => 'Debajo de la meta', 'icon' => 'fa-arrow-down'],
        ['key' => 'operating', 'label' => 'En operación', 'description' => 'Sin alertas', 'icon' => 'fa-circle-check'],
        ['key' => 'no_data', 'label' => 'Sin datos', 'description' => 'Sin captura', 'icon' => 'fa-minus'],
    ];
@endphp

<div
    class="crudo-dashboard"
    data-crudo-dashboard
    data-crudo-fecha="{{ $fecha }}"
    data-crudo-fecha-inicio="{{ $fechaInicio }}"
    data-crudo-fecha-fin="{{ $fechaFin }}"
    data-crudo-modo="{{ $this->modo }}"
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

            @if ($puedeDescargarReporte)
                <a
                    class="crudo-icon-button"
                    href="{{ route('crudo.reporte-dia', ['fecha' => $fecha]) }}"
                    data-crudo-reporte
                    data-crudo-nombre-archivo="reporte_telares_{{ $fecha }}.xlsx"
                    title="Descargar reporte del día en Excel"
                    aria-label="Descargar reporte del día en Excel"
                >
                    <i class="fa-solid fa-file-excel"></i>
                </a>
            @endif

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
                wire:target="modo,fecha,fechaInicio,fechaFin,refreshNow"
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
                        <h2>{{ $summary['total'] }} telares</h2>
                    </div>
                </div>

                <div class="crudo-status-list">
                    @foreach ($statusCards as $card)
                        <button
                            type="button"
                            class="crudo-status-card"
                            data-state="{{ $card['key'] }}"
                            wire:click="abrirEstado('{{ $card['key'] }}')"
                            @disabled(($summary[$card['key']] ?? 0) === 0)
                            title="{{ ($summary[$card['key']] ?? 0) === 0 ? $card['description'] : $card['description'].' · Ver detalle' }}"
                            aria-label="{{ $card['label'] }}: {{ $summary[$card['key']] }}. {{ $card['description'] }}"
                        >
                            <span class="crudo-status-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
                            <span class="crudo-status-value">{{ $summary[$card['key']] }}</span>
                            <span class="crudo-status-copy">
                                <strong>{{ $card['label'] }}</strong>
                            </span>
                        </button>
                    @endforeach
                </div>

                <p class="crudo-compact-label">Producción del periodo</p>

                @php
                    $kilos = (float) $summary['kilos'];
                    $metaKilos = (float) $summary['expectedKilos'];
                    $stdDia = (float) ($summary['dailyTargetKilos'] ?? 0);
                    $kilosPercent = $metaKilos > 0 ? min(100, $kilos / $metaKilos * 100) : 0.0;
                    $calidad = (float) $summary['qualityPercent'];
                    $eficiencia = (float) $summary['efficiencyPercent'];
                    $tono = fn (float $v, float $ok, float $medio) => $v >= $ok ? 'good' : ($v >= $medio ? 'warn' : 'bad');
                @endphp

                <div class="crudo-kpi-grid">
                    <article class="crudo-kpi-kilos">
                        {{-- Std del día arriba como contexto; prod manda y esperado lo acompaña. --}}
                        <p class="crudo-kpi-kilos-dia">meta <strong>{{ number_format(round($stdDia)) }}</strong></p>

                        <p class="crudo-kpi-kilos-row" aria-label="{{ number_format(round($kilos)) }} kg reales de {{ number_format(round($metaKilos)) }} kg esperados">
                            <span class="crudo-kpi-kilos-col">
                                <strong>{{ number_format(round($kilos)) }}</strong>
                                <span>real</span>
                            </span>
                            <span class="crudo-kpi-kilos-sep" aria-hidden="true">/</span>
                            <span class="crudo-kpi-kilos-col crudo-kpi-kilos-meta">
                                <strong>{{ number_format(round($metaKilos)) }}</strong>
                                <span>esperado</span>
                            </span>
                        </p>

                        @if ($metaKilos > 0)
                            <div
                                class="crudo-progress"
                                data-tone="{{ $tono($kilosPercent, 90, 75) }}"
                                style="--crudo-progress: {{ round($kilosPercent, 1) }}%"
                                role="progressbar"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="{{ round($kilosPercent) }}"
                                aria-label="Kilos contra meta del periodo"
                            ><span></span></div>
                        @endif
                    </article>

                    <div class="crudo-gauge-pair">
                        <x-crudo.gauge :value="$calidad" label="Calidad" :tone="$tono($calidad, 93, 85)" />
                        <x-crudo.gauge :value="$eficiencia" label="Eficiencia" :tone="$tono($eficiencia, 90, 75)" />
                    </div>

                    <article class="crudo-kpi-mini">
                        <strong>{{ number_format((float) $summary['pieces']) }}</strong>
                        <span>pzas</span>
                    </article>
                    <button
                        type="button"
                        class="crudo-kpi-mini crudo-kpi-mini-button"
                        wire:click="abrirDefectos"
                        @disabled((float) $summary['seconds'] === 0.0)
                        title="Ver defectos por telar"
                    >
                        <strong>{{ number_format((float) $summary['seconds']) }}</strong>
                        <span>2das</span>
                    </button>
                </div>
            </section>

            <section class="crudo-panel crudo-panel-areas">
                <div class="crudo-panel-heading">
                    <div>
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

{{-- Desglose del contador de estado: qué telares, desde cuándo y quién reportó. --}}
@if ($estadoDetalle !== null)
    @php
        $estadoCard = collect($statusCards)->firstWhere('key', $estadoDetalle) ?? [
            'label' => 'Estado', 'icon' => 'fa-circle-info', 'description' => '',
        ];
    @endphp

    <div class="crudo-modal-backdrop" wire:click.self="cerrarEstado" data-state="{{ $estadoDetalle }}">
        <div class="crudo-modal crudo-modal-estado" role="dialog" aria-modal="true"
             aria-label="Telares en estado {{ $estadoCard['label'] }}">
            <header class="crudo-estado-header" data-state="{{ $estadoDetalle }}">
                <span class="crudo-estado-header-icon"><i class="fa-solid {{ $estadoCard['icon'] }}"></i></span>
                <div>
                    <h2>{{ $estadoCard['label'] }}</h2>
                    <p>{{ count($machinesDetalle) }} {{ count($machinesDetalle) === 1 ? 'telar' : 'telares' }} · {{ $estadoCard['description'] }}</p>
                </div>
                <button type="button" class="crudo-modal-close" wire:click="cerrarEstado" data-crudo-modal-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>

            <div class="crudo-estado-list">
                @forelse ($machinesDetalle as $item)
                    @php $paro = $item['paro'] ?? null; @endphp
                    <article wire:key="crudo-estado-item-{{ $item['telar'] }}" class="crudo-estado-item" data-state="{{ $item['state'] }}">
                        <header class="crudo-estado-item-head">
                            <span class="crudo-estado-telar">{{ $item['telar'] }}</span>
                            <span class="crudo-estado-salon">{{ $item['salon'] }}</span>
                            @if (($paro['count'] ?? 1) > 1)
                                <span class="crudo-estado-multiple">{{ $paro['count'] }} paros</span>
                            @endif
                        </header>

                        <div class="crudo-estado-datos">

                            @if ($paro)
                                <ul class="crudo-estado-paros">
                                    @foreach ($paro['todos'] ?? [$paro] as $registro)
                                        <li>
                                            <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                            <span>{{ trim((string) ($registro['since'] ?? '')) ?: 'Hora sin registrar' }}</span>
                                            <span class="crudo-estado-falla">
                                                {{ $registro['falla'] ?? 'Paro reportado' }}
                                                @if (filled($registro['tipo'] ?? null))
                                                    · {{ $registro['tipo'] }}
                                                @endif
                                            </span>
                                            <span class="crudo-estado-reporto">
                                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                                {{ $registro['reportedBy'] ?? 'Sin registrar' }}
                                                @if (filled($registro['depto'] ?? null))
                                                    · {{ $registro['depto'] }}
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <ul class="crudo-estado-metricas">
                                    <li><span>Calidad</span><strong>{{ number_format((float) $item['qualityPercent'], 1) }}%</strong></li>
                                    <li><span>Eficiencia</span><strong>{{ number_format((float) ($item['efficiencyPercent'] ?? 0), 1) }}%</strong></li>
                                    <li>
                                        <span>Kilos</span>
                                        <strong>
                                            {{ number_format((float) $item['kilos'], 1) }}
                                            @if (($item['hasProductionStandard'] ?? false) && (float) $item['expectedKilos'] > 0)
                                                <em>/ {{ number_format((float) $item['expectedKilos'], 1) }}</em>
                                            @endif
                                        </strong>
                                    </li>
                                    <li><span>Piezas</span><strong>{{ number_format((float) $item['pieces']) }}</strong></li>
                                    <li><span>Segundas</span><strong>{{ number_format((float) $item['seconds']) }}</strong></li>
                                </ul>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="crudo-estado-vacio">Ningún telar en este estado ahora mismo.</p>
                @endforelse
            </div>
        </div>
    </div>
@endif

{{-- Desglose del KPI de segundas: defectos por telar, en tabla o en gráfica. --}}
@if ($defectosAbierto && $defectos !== null)
    <div class="crudo-modal-backdrop" wire:click.self="cerrarDefectos">
        <div class="crudo-modal crudo-modal-defectos" role="dialog" aria-modal="true"
             aria-label="Segundas por telar">
            <header class="crudo-estado-header" data-state="bad_quality">
                <span class="crudo-estado-header-icon"><i class="fa-solid fa-circle-xmark"></i></span>
                <div>
                    <h2>Segundas por telar</h2>
                    <p>
                        {{ number_format($defectos['total']) }} piezas de segunda ·
                        {{ count($defectos['telares']) }} {{ count($defectos['telares']) === 1 ? 'telar' : 'telares' }}
                    </p>
                </div>
                <button type="button" class="crudo-modal-close" wire:click="cerrarDefectos" data-crudo-modal-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>

            {{--
                Filtro por salón: radios nativos + CSS. Cambiar de salón es
                instantáneo y no manda ninguna petición a Livewire.
            --}}
            @php($salonesDefectos = collect($defectos['telares'])->countBy('salon'))
            @php($filtrosDefectos = [
                'todos' => ['etiqueta' => 'Todos', 'conteo' => count($defectos['telares'])],
                'Jacquard' => ['etiqueta' => 'JAC', 'conteo' => $salonesDefectos['Jacquard'] ?? 0],
                'Smith' => ['etiqueta' => 'SMI', 'conteo' => $salonesDefectos['Smith'] ?? 0],
                'Karl Mayer' => ['etiqueta' => 'KM', 'conteo' => $salonesDefectos['Karl Mayer'] ?? 0],
            ])

            @foreach ($filtrosDefectos as $clave => $filtro)
                <input
                    type="radio"
                    class="crudo-defectos-vista-input"
                    name="crudo-defectos-salon"
                    id="crudo-defectos-salon-{{ Str::slug($clave) }}"
                    value="{{ $clave }}"
                    @checked($clave === 'todos')
                >
            @endforeach

            <div class="crudo-defectos-vistas" role="radiogroup" aria-label="Filtrar por salón">
                @foreach ($filtrosDefectos as $clave => $filtro)
                    <label for="crudo-defectos-salon-{{ Str::slug($clave) }}" data-vista="{{ $clave }}">
                        {{ $filtro['etiqueta'] }}
                        <span class="crudo-paros-conteo">{{ $filtro['conteo'] }}</span>
                    </label>
                @endforeach

                {{-- Orden: sí pasa por el servidor, porque el pulso repinta la tabla. --}}
                <span class="crudo-defectos-orden">
                    @foreach (['telar' => 'Telar', 'desc' => '2das ↓', 'asc' => '2das ↑'] as $orden => $etiqueta)
                        <button
                            type="button"
                            class="{{ $defectosOrden === $orden ? 'is-active' : '' }}"
                            aria-pressed="{{ $defectosOrden === $orden ? 'true' : 'false' }}"
                            wire:click="$set('defectosOrden', '{{ $orden }}')"
                            title="{{ $orden === 'telar' ? 'Ordenar por número de telar' : ($orden === 'desc' ? 'De más a menos segundas' : 'De menos a más segundas') }}"
                        >{{ $etiqueta }}</button>
                    @endforeach
                </span>
            </div>

            <div class="crudo-defectos-cuerpo">
                @if (count($defectos['telares']) === 0)
                    <p class="crudo-estado-vacio">Sin defectos capturados en este periodo.</p>
                @else
                    {{-- Mismo orden de colores que la gráfica apilada (COLORES_DEFECTO en defect-chart.ts). --}}
                    @php($coloresDefecto = ['#4d5cff', '#f97316', '#16a34a', '#dc2626', '#a855f7', '#0891b2', '#94a3b8'])
                    @php($mayorTotal = max(1.0, (float) $defectos['maximo']))

                    <div class="crudo-defectos-panel">
                        <div class="crudo-detail-table-scroll">
                            <table class="crudo-detail-table crudo-defectos-table">
                                <thead>
                                    <tr>
                                        <th>Telar</th>
                                        @foreach ($defectos['columnas'] as $indice => $columna)
                                            <th style="--color-defecto: {{ $coloresDefecto[$indice % count($coloresDefecto)] }}">
                                                <span class="crudo-defectos-chip" aria-hidden="true"></span>
                                                {{ $columna }}
                                            </th>
                                        @endforeach
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($defectos['telares'] as $fila)
                                        <tr wire:key="crudo-defecto-fila-{{ $fila['telar'] }}" data-salon="{{ $fila['salon'] ?? 'Sin clasificar' }}">
                                            <td class="crudo-defectos-telar">{{ $fila['telar'] }}</td>
                                            @foreach ($defectos['columnas'] as $indice => $columna)
                                                @php($valor = (float) ($fila['defectos'][$columna] ?? 0))
                                                <td
                                                    @class(['crudo-defectos-cero' => $valor === 0.0])
                                                    style="--color-defecto: {{ $coloresDefecto[$indice % count($coloresDefecto)] }}"
                                                >
                                                    {{ $valor === 0.0 ? '·' : number_format($valor) }}
                                                </td>
                                            @endforeach
                                            <td class="crudo-defectos-total">
                                                {{-- Barra de proporción: el peor telar se ve sin leer los números. --}}
                                                <span
                                                    class="crudo-defectos-barra"
                                                    style="--parte: {{ round($fila['total'] / $mayorTotal * 100) }}%"
                                                    aria-hidden="true"
                                                ></span>
                                                <strong>{{ number_format((float) $fila['total']) }}</strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($defectos['recortados'] > 0)
                            <p class="crudo-defectos-nota">
                                Los {{ $defectos['recortados'] }} tipos de defecto menos frecuentes están sumados en "Otros".
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

</div>
