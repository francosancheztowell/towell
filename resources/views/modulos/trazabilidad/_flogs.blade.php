@php
    $general = $flogs['general'] ?? [];
    $etiquetas = $flogs['etiquetas'] ?? [];
    $empaques = $flogs['empaques'] ?? [];
    $encontrado = (bool) ($flogs['encontrado'] ?? false);
    $hayFlogFiltro = filled($filtros['flog'] ?? null);
    $lineas = $flogs['lineas'] ?? [];

    $columnasLineas = [
        ['key' => 'lineNum', 'label' => 'Línea', 'tipo' => 'entero'],
        ['key' => 'estadoLinea', 'label' => 'Estado línea', 'tipo' => 'estado'],
        ['key' => 'fechaCancelacion', 'label' => 'Fecha cancelación'],
        ['key' => 'itemId', 'label' => 'Item'],
        ['key' => 'itemName', 'label' => 'Nombre artículo'],
        ['key' => 'tipoHiloId', 'label' => 'Tipo hilo'],
        ['key' => 'inventSizeId', 'label' => 'Tamaño'],
        ['key' => 'inventColorId', 'label' => 'Color'],
        ['key' => 'colorName', 'label' => 'Nombre color'],
        ['key' => 'rasuradoCrudo', 'label' => 'Rasurado crudo'],
        ['key' => 'tipoDobladillo', 'label' => 'Tipo dobladillo'],
        ['key' => 'tipoCostura', 'label' => 'Tipo costura'],
        ['key' => 'tipoCorteBataId', 'label' => 'Tipo corte bata'],
        ['key' => 'valorAgregado', 'label' => 'Valor agregado'],
        ['key' => 'puntadasBordado', 'label' => 'Puntadas bordado', 'tipo' => 'decimal'],
        ['key' => 'infoAdicional', 'label' => 'Info adicional'],
        ['key' => 'ancho', 'label' => 'Ancho', 'tipo' => 'decimal'],
        ['key' => 'largo', 'label' => 'Largo', 'tipo' => 'decimal'],
        ['key' => 'pesoAcabado', 'label' => 'Peso acabado', 'tipo' => 'decimal'],
        ['key' => 'densidad', 'label' => 'Densidad', 'tipo' => 'decimal'],
        ['key' => 'inventQty', 'label' => 'Cantidad', 'tipo' => 'decimal'],
        ['key' => 'salesUnit', 'label' => 'Ud. venta'],
        ['key' => 'purchBarCode', 'label' => 'Cód. barras'],
        ['key' => 'dun14', 'label' => 'DUN14'],
        ['key' => 'retailLink', 'label' => 'Retail link'],
        ['key' => 'nombreEtiqueta', 'label' => 'Nombre etiqueta'],
        ['key' => 'createdDate', 'label' => 'Fecha creación'],
        ['key' => 'simulacionVtasUrl', 'label' => 'Simulación vtas', 'tipo' => 'imagen', 'titulo' => 'Simulación ventas'],
        ['key' => 'simulacionDisenoUrl', 'label' => 'Simulación diseño', 'tipo' => 'imagen', 'titulo' => 'Simulación diseño'],
    ];

    $estadoLineaBadge = [
        '0' => 'flog-estado-badge--abierto',
        '1' => 'flog-estado-badge--facturado',
        '2' => 'flog-estado-badge--cancelado',
        '3' => 'flog-estado-badge--todo',
    ];

    $estadosLineaFiltro = [];
    foreach ($lineas as $linea) {
        $codigo = (string) ($linea['estadoLineaCodigo'] ?? '');
        if ($codigo === '') {
            continue;
        }
        if (! isset($estadosLineaFiltro[$codigo])) {
            $estadosLineaFiltro[$codigo] = [
                'codigo' => $codigo,
                'label' => $linea['estadoLinea'] ?? $codigo,
                'clase' => $estadoLineaBadge[$codigo] ?? 'flog-estado-badge--otro',
                'count' => 0,
            ];
        }
        $estadosLineaFiltro[$codigo]['count']++;
    }
    ksort($estadosLineaFiltro);

    $v = fn (?string $valor): string => filled($valor) ? $valor : '—';
@endphp

@if (! $hayFlogFiltro)
    <div class="flog-card p-10 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-3">
            <i class="fa-solid fa-list-ol text-blue-500 text-lg"></i>
        </div>
        <p class="text-slate-800 text-base font-semibold">Selecciona un Flog para ver la información</p>
        <p class="text-slate-500 text-sm mt-1">Usa el filtro <strong>Flog</strong> en la barra superior.</p>
    </div>
@elseif (! $encontrado)
    <div class="flog-card p-10 text-center border-amber-300">
        <div class="mx-auto w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mb-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg"></i>
        </div>
        <p class="text-slate-800 text-base font-semibold">No se encontró el Flog en TI</p>
        <p class="text-slate-500 text-sm mt-1 font-mono">{{ $filtros['flog'] ?? '' }}</p>
    </div>
@else
    <div class="flog-wrap">
        {{-- Información general — visible por defecto; icono para ocultar/mostrar --}}
        <section class="flog-card flog-card--collapsible is-expanded" id="flog-seccion-general" aria-labelledby="flog-titulo-general">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-clipboard-list"></i></span>
                <h2 id="flog-titulo-general" class="flog-card__title">Información general del proyecto</h2>
                <button
                    type="button"
                    class="flog-card__toggle"
                    aria-expanded="true"
                    aria-controls="flog-general-body"
                    title="Ocultar información general"
                >
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
            </header>
            <div id="flog-general-body" class="flog-card__body">
                {{-- TwFlogsTable — una sola fila horizontal --}}
                <div class="flog-tabla-fila" role="row" aria-label="Datos del Flog">
                    <div class="flog-tabla-fila__celda flog-tabla-fila__celda--flog" role="cell">
                        <span class="flog-tabla-fila__label">Id Flog</span>
                        <span class="flog-tabla-fila__valor flog-tabla-fila__valor--accent">{{ $v($general['idFlog'] ?? null) }}</span>
                    </div>
                    <div class="flog-tabla-fila__celda" role="cell">
                        <span class="flog-tabla-fila__label">Tipo pedido</span>
                        <span class="flog-tabla-fila__valor">{{ $v($general['tipoPedido'] ?? null) }}</span>
                    </div>
                    <div class="flog-tabla-fila__celda flog-tabla-fila__celda--proyecto" role="cell">
                        <span class="flog-tabla-fila__label">Proyecto</span>
                        <span class="flog-tabla-fila__valor">{{ $v($general['nameProyect'] ?? null) }}</span>
                    </div>
                    <div class="flog-tabla-fila__celda" role="cell">
                        <span class="flog-tabla-fila__label">Empresa</span>
                        <span class="flog-tabla-fila__valor">{{ $v($general['empresaLabel'] ?? $general['empresa'] ?? null) }}</span>
                    </div>
                    <div class="flog-tabla-fila__celda" role="cell">
                        <span class="flog-tabla-fila__label">Fecha transacción</span>
                        <span class="flog-tabla-fila__valor">{{ $v($general['transDate'] ?? null) }}</span>
                    </div>
                </div>

                {{-- TwFlogsCustomer — 7 columnas × 2 filas --}}
                <div class="flog-cliente-grid" role="grid" aria-label="Datos del cliente">
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Cuenta cliente</span>
                        <span class="flog-cliente-grid__valor flog-cliente-grid__valor--accent">{{ $v($general['custAccount'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Nombre cliente</span>
                        <span class="flog-cliente-grid__valor flog-cliente-grid__valor--accent">{{ $v($general['custName'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Núm. proveedor</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['numProveedor'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Tipo cliente</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['tipoClienteId'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Categoría calidad</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['categoriaCalidad'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Proceso Cadmex</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['procesoCatMex'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">C. agente</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['cAgente'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">N. agente</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['nAgente'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Prueba lab. id</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['pruebaLabId'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Pruebas lab. texto</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['pruebasLabTxt'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda" role="gridcell">
                        <span class="flog-cliente-grid__label">Suavizante</span>
                        <span class="flog-cliente-grid__valor">{{ $v($general['twSuavizante'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda flog-cliente-grid__celda--aviso" role="gridcell">
                        <span class="flog-cliente-grid__label">Aviso especial</span>
                        <span class="flog-cliente-grid__valor flog-cliente-grid__valor--limitado">{{ $v($general['avisoEspecialTxt'] ?? null) }}</span>
                    </div>
                    <div class="flog-cliente-grid__celda flog-cliente-grid__celda--info flog-cliente-grid__celda--full-row" role="gridcell">
                        <span class="flog-cliente-grid__label">Información importante</span>
                        <span class="flog-cliente-grid__valor flog-cliente-grid__valor--limitado">{{ $v($general['infoImportante'] ?? null) }}</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Empaque y etiquetado — tablas compactas --}}
        <section class="flog-card" aria-labelledby="flog-titulo-visual">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-box-open"></i></span>
                <h2 id="flog-titulo-visual" class="flog-card__title">Empaque y etiquetado</h2>
            </header>
            <div class="flog-card__body flog-meta-tables">
                <div class="flog-meta-table-wrap">
                    <h3 class="flog-meta-table__titulo">Empaques <span class="flog-meta-table__count">{{ count($empaques) }}</span></h3>
                    <div class="flog-meta-table-scroll">
                        <table class="flog-meta-table">
                            <thead>
                                <tr>
                                    <th scope="col">Id empaque</th>
                                    <th scope="col">Otro empaque</th>
                                    <th scope="col" class="flog-meta-table__th--img">Imagen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($empaques as $emp)
                                    <tr>
                                        <td class="whitespace-nowrap font-semibold text-slate-800">{{ $v($emp['idEmpaque'] ?? null) }}</td>
                                        <td class="flog-meta-table__celda--larga">{{ $v($emp['otroEmpaque'] ?? null) }}</td>
                                        <td class="flog-meta-table__celda--img">
                                            @if (! empty($emp['imagenUrl']))
                                                <button
                                                    type="button"
                                                    class="flog-lineas-thumb flog-meta-thumb"
                                                    data-flog-zoom="{{ $emp['imagenUrl'] }}"
                                                    data-flog-zoom-title="Empaque — {{ $emp['idEmpaque'] ?? '' }}"
                                                    aria-label="Ver empaque"
                                                >
                                                    <img src="{{ $emp['imagenUrl'] }}" alt="" loading="lazy" decoding="async" data-flog-img draggable="false">
                                                </button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="flog-meta-table__vacio">Sin empaques registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flog-meta-table-wrap">
                    <h3 class="flog-meta-table__titulo">Etiquetas <span class="flog-meta-table__count">{{ count($etiquetas) }}</span></h3>
                    <div class="flog-meta-table-scroll">
                        <table class="flog-meta-table">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Nombre</th>
                                    <th scope="col">Comentarios</th>
                                    <th scope="col" class="flog-meta-table__th--img">Imagen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($etiquetas as $etiq)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ $v($etiq['itemId'] ?? null) }}</td>
                                        <td>{{ $v($etiq['name'] ?? null) }}</td>
                                        <td class="flog-meta-table__celda--larga">{{ $v($etiq['comentarios'] ?? null) }}</td>
                                        <td class="flog-meta-table__celda--img">
                                            @if (! empty($etiq['imagenUrl']))
                                                <button
                                                    type="button"
                                                    class="flog-lineas-thumb flog-meta-thumb"
                                                    data-flog-zoom="{{ $etiq['imagenUrl'] }}"
                                                    data-flog-zoom-title="Etiqueta — {{ $etiq['name'] ?? $etiq['itemId'] ?? '' }}"
                                                    aria-label="Ver etiqueta"
                                                >
                                                    <img src="{{ $etiq['imagenUrl'] }}" alt="" loading="lazy" decoding="async" data-flog-img draggable="false">
                                                </button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="flog-meta-table__vacio">Sin etiquetas registradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="flog-card" aria-labelledby="flog-titulo-lineas">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-list"></i></span>
                <h2 id="flog-titulo-lineas" class="flog-card__title">Líneas</h2>
                <span class="flog-lineas-count">{{ count($lineas) }}</span>
            </header>
            <div class="flog-card__body flog-lineas-wrap">
                @if (count($lineas) > 0 && count($estadosLineaFiltro) > 0)
                    <div class="flog-lineas-filtros" role="group" aria-label="Filtrar por estado de línea">
                        <button type="button" class="flog-lineas-filtro-btn is-active" data-flog-linea-filtro="todos">
                            <span class="flog-estado-badge flog-estado-badge--todos">Todos</span>
                            <span class="flog-lineas-filtro-count">{{ count($lineas) }}</span>
                        </button>
                        @foreach ($estadosLineaFiltro as $estadoFiltro)
                            <button type="button" class="flog-lineas-filtro-btn" data-flog-linea-filtro="{{ $estadoFiltro['codigo'] }}">
                                <span class="flog-estado-badge {{ $estadoFiltro['clase'] }}">{{ $estadoFiltro['label'] }}</span>
                                <span class="flog-lineas-filtro-count">{{ $estadoFiltro['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                <div class="flog-lineas-scroll" tabindex="0" role="region" aria-label="Tabla de líneas del Flog">
                    <table class="flog-lineas-table">
                        <thead>
                            <tr>
                                @foreach ($columnasLineas as $col)
                                    <th scope="col">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lineas as $linea)
                                <tr data-estado-linea="{{ $linea['estadoLineaCodigo'] ?? '' }}">
                                    @foreach ($columnasLineas as $col)
                                        @php
                                            $celda = $linea[$col['key']] ?? '';
                                            $tipo = $col['tipo'] ?? 'texto';
                                            $esLargo = $tipo === 'texto' && in_array($col['key'], ['itemName', 'infoAdicional', 'nombreEtiqueta', 'retailLink'], true);
                                        @endphp
                                        <td @class([
                                            'flog-lineas-table__celda--larga' => $esLargo,
                                            'flog-lineas-table__celda--num' => in_array($tipo, ['decimal', 'entero'], true),
                                            'flog-lineas-table__celda--img' => $tipo === 'imagen',
                                            'flog-lineas-table__celda--estado' => $tipo === 'estado',
                                        ])>
                                            @if ($tipo === 'estado')
                                                @if (filled($celda) && $celda !== '—')
                                                    @php
                                                        $codigoEstado = (string) ($linea['estadoLineaCodigo'] ?? '');
                                                        $claseEstado = $estadoLineaBadge[$codigoEstado] ?? 'flog-estado-badge--otro';
                                                    @endphp
                                                    <span class="flog-estado-badge {{ $claseEstado }}">{{ $celda }}</span>
                                                @else
                                                    —
                                                @endif
                                            @elseif ($tipo === 'imagen')
                                                @if (filled($celda))
                                                    <button
                                                        type="button"
                                                        class="flog-lineas-thumb"
                                                        data-flog-zoom="{{ $celda }}"
                                                        data-flog-zoom-title="{{ ($col['titulo'] ?? $col['label']) }} — Línea {{ $linea['lineNum'] ?? '' }}"
                                                        aria-label="Ver {{ $col['label'] }}"
                                                    >
                                                        <img src="{{ $celda }}" alt="" loading="lazy" draggable="false">
                                                    </button>
                                                @else
                                                    —
                                                @endif
                                            @else
                                                {{ $v($celda !== '' && $celda !== '—' ? $celda : null) }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columnasLineas) }}" class="flog-lineas-table__vacio">Sin líneas registradas para este Flog.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="flog-lineas-sin-filtro hidden" role="status">Ninguna línea coincide con el estado seleccionado.</p>
            </div>
        </section>
    </div>
@endif
