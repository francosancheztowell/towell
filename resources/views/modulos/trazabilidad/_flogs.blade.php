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
        {{-- Información general — una tarjeta, grid denso label arriba / valor abajo --}}
        <section class="flog-card" aria-labelledby="flog-titulo-general">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-clipboard-list"></i></span>
                <h2 id="flog-titulo-general" class="flog-card__title">Información general del proyecto</h2>
            </header>
            <div class="flog-card__body">
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

                <div class="flog-fields">
                    {{-- TwFlogsCustomer --}}
                    <div class="flog-campo">
                        <span class="flog-campo__label">Cuenta cliente</span>
                        <span class="flog-campo__valor flog-campo__valor--accent">{{ $v($general['custAccount'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo flog-campo--half">
                        <span class="flog-campo__label">Nombre cliente</span>
                        <span class="flog-campo__valor flog-campo__valor--accent">{{ $v($general['custName'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Núm. proveedor</span>
                        <span class="flog-campo__valor">{{ $v($general['numProveedor'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Tipo cliente</span>
                        <span class="flog-campo__valor">{{ $v($general['tipoClienteId'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Categoría calidad</span>
                        <span class="flog-campo__valor">{{ $v($general['categoriaCalidad'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Proceso Cadmex</span>
                        <span class="flog-campo__valor">{{ $v($general['procesoCatMex'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">C. agente</span>
                        <span class="flog-campo__valor">{{ $v($general['cAgente'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">N. agente</span>
                        <span class="flog-campo__valor">{{ $v($general['nAgente'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Prueba lab. id</span>
                        <span class="flog-campo__valor">{{ $v($general['pruebaLabId'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo flog-campo--half">
                        <span class="flog-campo__label">Pruebas lab. texto</span>
                        <span class="flog-campo__valor">{{ $v($general['pruebasLabTxt'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Suavizante</span>
                        <span class="flog-campo__valor">{{ $v($general['twSuavizante'] ?? null) }}</span>
                    </div>
                    @if (filled($general['avisoEspecialTxt'] ?? null))
                        <div class="flog-campo flog-campo--wide">
                            <div class="flog-meta-nota flog-meta-nota--verde">
                                <span class="flog-meta-nota__titulo">Aviso especial</span>
                                {{ $general['avisoEspecialTxt'] }}
                            </div>
                        </div>
                    @endif
                    @if (filled($general['infoImportante'] ?? null))
                        <div class="flog-campo flog-campo--wide">
                            <div class="flog-meta-nota">
                                <span class="flog-meta-nota__titulo">Información importante</span>
                                {{ $general['infoImportante'] }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Empaque + etiquetas: meta compacta a la izquierda, galería grande a la derecha --}}
        @php
            $imagenesFlog = [];
            foreach ($empaques as $emp) {
                if (! empty($emp['imagenUrl'])) {
                    $imagenesFlog[] = [
                        'url' => $emp['imagenUrl'],
                        'caption' => 'Empaque — '.($emp['idEmpaque'] ?? 'Imagen'),
                    ];
                }
            }
            foreach ($etiquetas as $etiq) {
                if (! empty($etiq['imagenUrl'])) {
                    $imagenesFlog[] = [
                        'url' => $etiq['imagenUrl'],
                        'caption' => $etiq['comentarios'] ?: ($etiq['name'] ?: 'Etiqueta'),
                    ];
                }
            }
            $soloUnaImagen = count($imagenesFlog) === 1;
        @endphp

        <section class="flog-card" aria-labelledby="flog-titulo-visual">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-box-open"></i></span>
                <h2 id="flog-titulo-visual" class="flog-card__title">Empaque y etiquetado</h2>
            </header>
            <div class="flog-card__body flog-visual-layout">
                <aside class="flog-visual-meta">
                    <div class="flog-campo">
                        <span class="flog-campo__label">Empaques registrados</span>
                        <span class="flog-campo__valor">{{ count($empaques) }}</span>
                    </div>
                    @forelse ($empaques as $idx => $emp)
                        <div class="flog-empaque-block">
                            <div class="flog-campo">
                                <span class="flog-campo__label">Id empaque{{ count($empaques) > 1 ? ' #'.($idx + 1) : '' }}</span>
                                <span class="flog-campo__valor">{{ $v($emp['idEmpaque'] ?? null) }}</span>
                            </div>
                            @if (filled($emp['otroEmpaque'] ?? null))
                                <div class="flog-campo">
                                    <span class="flog-campo__label">Otro empaque</span>
                                    <span class="flog-campo__valor whitespace-pre-line">{{ $emp['otroEmpaque'] }}</span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="flog-campo">
                            <span class="flog-campo__label">Id empaque</span>
                            <span class="flog-campo__valor">—</span>
                        </div>
                    @endforelse
                    <div class="flog-campo">
                        <span class="flog-campo__label">Etiquetas registradas</span>
                        <span class="flog-campo__valor">{{ count($etiquetas) }}</span>
                    </div>
                </aside>

                @if (! empty($imagenesFlog))
                    <div class="flog-visual-gallery {{ $soloUnaImagen ? 'flog-visual-gallery--solo' : '' }}">
                        @foreach ($imagenesFlog as $img)
                            <figure class="flog-visual-frame" data-flog-zoom="{{ $img['url'] }}" role="button" tabindex="0" aria-label="{{ $img['caption'] }}">
                                <div class="flog-visual-frame__img-wrap">
                                    <img src="{{ $img['url'] }}" alt="{{ $img['caption'] }}" loading="lazy">
                                </div>
                                <figcaption class="flog-visual-frame__caption">{{ $img['caption'] }}</figcaption>
                                <div class="flog-visual-frame__zoom-hint" aria-hidden="true">
                                    <span><i class="fa-solid fa-magnifying-glass-plus mr-1"></i> Clic para tamaño completo</span>
                                </div>
                            </figure>
                        @endforeach
                    </div>
                @else
                    <div class="flog-visual-placeholder" role="presentation" aria-hidden="true">
                        <div class="flog-visual-placeholder__frame">
                            <span class="flog-visual-placeholder__icon">
                                <i class="fa-regular fa-image"></i>
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            @if (count($etiquetas) > 0)
                <div class="flog-etiquetas-section">
                    <h3 class="flog-etiquetas-section__title">Etiquetas (TwFlogsEtiquetasLinea)</h3>
                    <div class="flog-lineas-scroll" tabindex="0" role="region" aria-label="Tabla de etiquetas del Flog">
                        <table class="flog-lineas-table flog-etiquetas-table">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Nombre</th>
                                    <th scope="col">Comentarios</th>
                                    <th scope="col">Imagen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($etiquetas as $etiq)
                                    <tr>
                                        <td>{{ $v($etiq['itemId'] ?? null) }}</td>
                                        <td class="flog-lineas-table__celda--larga">{{ $v($etiq['name'] ?? null) }}</td>
                                        <td class="flog-lineas-table__celda--larga flog-lineas-table__celda--wrap">{{ $v($etiq['comentarios'] ?? null) }}</td>
                                        <td class="flog-lineas-table__celda--img">
                                            @if (! empty($etiq['imagenUrl']))
                                                <button
                                                    type="button"
                                                    class="flog-lineas-thumb"
                                                    data-flog-zoom="{{ $etiq['imagenUrl'] }}"
                                                    data-flog-zoom-title="Etiqueta — {{ $etiq['itemId'] ?? '' }}"
                                                    aria-label="Ver imagen etiqueta"
                                                >
                                                    <img src="{{ $etiq['imagenUrl'] }}" alt="" loading="lazy" draggable="false">
                                                </button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <section class="flog-card" aria-labelledby="flog-titulo-lineas">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-list"></i></span>
                <h2 id="flog-titulo-lineas" class="flog-card__title">Líneas</h2>
                <span class="flog-lineas-count">{{ count($lineas) }}</span>
            </header>
            <div class="flog-card__body flog-lineas-wrap">
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
                                <tr>
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
            </div>
        </section>
    </div>
@endif
