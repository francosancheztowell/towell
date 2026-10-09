@php
    $general = $flogs['general'] ?? [];
    $etiquetas = $flogs['etiquetas'] ?? [];
    $empaques = $flogs['empaques'] ?? [];
    $encontrado = (bool) ($flogs['encontrado'] ?? false);
    $estadoFlogs = $flogs['estado'] ?? ($encontrado ? 'ok' : 'not_found');
    $errorMensaje = $flogs['errorMensaje'] ?? null;
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
        ['key' => 'ancho', 'label' => 'Ancho', 'tipo' => 'entero'],
        ['key' => 'largo', 'label' => 'Largo', 'tipo' => 'entero'],
        ['key' => 'pesoAcabado', 'label' => 'Peso acabado', 'tipo' => 'entero'],
        ['key' => 'densidad', 'label' => 'Densidad', 'tipo' => 'decimal'],
        ['key' => 'inventQty', 'label' => 'Cantidad', 'tipo' => 'entero'],
        ['key' => 'facturado', 'label' => 'Facturado', 'tipo' => 'entero'],
        ['key' => 'porEntregar', 'label' => 'Por entregar', 'tipo' => 'entero'],
        ['key' => 'salesUnit', 'label' => 'Ud. venta'],
        ['key' => 'purchBarCode', 'label' => 'Cód. barras'],
        ['key' => 'dun14', 'label' => 'DUN14'],
        ['key' => 'retailLink', 'label' => 'Retail link'],
        ['key' => 'nombreEtiqueta', 'label' => 'Nombre etiqueta'],
        ['key' => 'createdDate', 'label' => 'Fecha creación'],
        ['key' => 'simulacionVtasUrl', 'label' => 'Simulación vtas', 'tipo' => 'imagen', 'titulo' => 'Simulación ventas'],
        ['key' => 'simulacionDisenoUrl', 'label' => 'Simulación diseño', 'tipo' => 'imagen', 'titulo' => 'Simulación diseño'],
    ];

    // Código de estado de línea en AX → color del badge.
    $colorEstado = ['0' => 'blue', '1' => 'emerald', '2' => 'red', '3' => 'violet'];

    $estadosLineaFiltro = [];
    foreach ($lineas as $linea) {
        $codigo = (string) ($linea['estadoLineaCodigo'] ?? '');
        if ($codigo === '') {
            continue;
        }
        $estadosLineaFiltro[$codigo] ??= ['codigo' => $codigo, 'label' => $linea['estadoLinea'] ?? $codigo, 'count' => 0];
        $estadosLineaFiltro[$codigo]['count']++;
    }
    ksort($estadosLineaFiltro);

    $v = fn (?string $valor): string => filled($valor) ? $valor : '—';

    $datosProyecto = [
        ['Id Flog', $general['idFlog'] ?? null, true],
        ['Tipo pedido', $general['tipoPedido'] ?? null, false],
        ['Proyecto', $general['nameProyect'] ?? null, false],
        ['Empresa', $general['empresaLabel'] ?? $general['empresa'] ?? null, false],
        ['Fecha transacción', $general['transDate'] ?? null, false],
    ];
    $datosCliente = [
        ['Cuenta cliente', $general['custAccount'] ?? null, true],
        ['Nombre cliente', $general['custName'] ?? null, true],
        ['Núm. proveedor', $general['numProveedor'] ?? null, false],
        ['Tipo cliente', $general['tipoClienteId'] ?? null, false],
        ['Categoría calidad', $general['categoriaCalidad'] ?? null, false],
        ['Agente', $general['nAgente'] ?? null, false],
        ['Pruebas lab', $general['pruebasLabTxt'] ?? null, false],
        ['Suavizante', $general['twSuavizante'] ?? null, false],
    ];
    $notas = [
        ['Aviso especial', $general['avisoEspecialTxt'] ?? null],
        ['Información importante', $general['infoImportante'] ?? null],
    ];
@endphp

<div id="flogs-contenido" class="space-y-4">
@if (! $hayFlogFiltro)
    <x-trazabilidad.vacio icono="document-text" titulo="Elige un Flog para ver su información"
                          texto="Usa el filtro Flog de arriba." />
@elseif ($estadoFlogs === 'error')
    <flux:callout variant="danger" icon="server" heading="No se pudo consultar la información del Flog">
        <flux:callout.text>{{ $errorMensaje }} El incidente quedó registrado.</flux:callout.text>
    </flux:callout>
@elseif (! $encontrado)
    <x-trazabilidad.vacio icono="exclamation-triangle" titulo="El Flog no existe en el sistema de pedidos"
                          :texto="$filtros['flog'] ?? ''" />
@else
    {{-- <details> nativo: se pliega con teclado y tacto sin JS. --}}
    <flux:card class="!p-0">
        <details open class="group">
            <summary class="flex min-h-touch cursor-pointer list-none items-center gap-2 border-b border-zinc-100 px-4 py-2 [&::-webkit-details-marker]:hidden">
                <flux:icon.chevron-right variant="micro" class="text-zinc-400 transition-transform group-open:rotate-90" />
                <flux:heading size="lg" level="3">Información general</flux:heading>
            </summary>

            <dl class="grid grid-cols-2 border-b border-zinc-100 md:grid-cols-5">
                @foreach ($datosProyecto as [$etiqueta, $valor, $resaltado])
                    <div class="min-w-0 px-4 py-3">
                        <dt class="text-xs font-medium text-zinc-500">{{ $etiqueta }}</dt>
                        <dd @class(['mt-0.5 break-words text-sm font-semibold', 'text-blue-700' => $resaltado, 'text-zinc-800' => ! $resaltado])>{{ $v($valor) }}</dd>
                    </div>
                @endforeach
            </dl>

            <dl class="grid grid-cols-2 border-b border-zinc-100 md:grid-cols-4">
                @foreach ($datosCliente as [$etiqueta, $valor, $resaltado])
                    <div class="min-w-0 px-4 py-3">
                        <dt class="text-xs font-medium text-zinc-500">{{ $etiqueta }}</dt>
                        <dd @class(['mt-0.5 break-words text-sm font-semibold', 'text-blue-700' => $resaltado, 'text-zinc-800' => ! $resaltado])>{{ $v($valor) }}</dd>
                    </div>
                @endforeach
            </dl>

            <dl class="grid grid-cols-1 md:grid-cols-2">
                @foreach ($notas as [$etiqueta, $valor])
                    <div class="min-w-0 px-4 py-3">
                        <dt class="text-xs font-medium text-zinc-500">{{ $etiqueta }}</dt>
                        <dd class="traza-scroll mt-0.5 max-h-20 overflow-y-auto whitespace-pre-line text-sm text-zinc-800">{{ $v($valor) }}</dd>
                    </div>
                @endforeach
            </dl>
        </details>
    </flux:card>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <flux:card class="!p-0">
            <header class="flex min-h-14 items-center gap-2 border-b border-zinc-100 px-4">
                <flux:heading size="lg" level="3">Empaques</flux:heading>
                <flux:badge size="sm">{{ count($empaques) }}</flux:badge>
            </header>
            <flux:table class="traza-tabla" container:class="traza-tabla-limitada">
                <flux:table.columns sticky>
                    <flux:table.column>Id empaque</flux:table.column>
                    <flux:table.column>Otro empaque</flux:table.column>
                    <flux:table.column align="center">Imagen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($empaques as $emp)
                        <flux:table.row>
                            <flux:table.cell class="font-semibold text-zinc-800">{{ $v($emp['idEmpaque'] ?? null) }}</flux:table.cell>
                            <flux:table.cell class="!whitespace-normal">{{ $v($emp['otroEmpaque'] ?? null) }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @if (! empty($emp['imagenUrl']))
                                    <button type="button" class="flog-lineas-thumb" aria-label="Ver empaque {{ $emp['idEmpaque'] ?? '' }}"
                                            data-flog-zoom="{{ $emp['imagenUrl'] }}"
                                            data-flog-zoom-title="Empaque — {{ $emp['idEmpaque'] ?? '' }}">
                                        <img src="{{ $emp['imagenUrl'] }}" alt="" loading="lazy" decoding="async" data-flog-img draggable="false">
                                    </button>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-6 text-center text-zinc-500">Sin empaques registrados.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card class="!p-0">
            <header class="flex min-h-14 items-center gap-2 border-b border-zinc-100 px-4">
                <flux:heading size="lg" level="3">Etiquetas</flux:heading>
                <flux:badge size="sm">{{ count($etiquetas) }}</flux:badge>
            </header>
            <flux:table class="traza-tabla" container:class="traza-tabla-limitada">
                <flux:table.columns sticky>
                    <flux:table.column>Item</flux:table.column>
                    <flux:table.column>Nombre</flux:table.column>
                    <flux:table.column>Comentarios</flux:table.column>
                    <flux:table.column align="center">Imagen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($etiquetas as $etiq)
                        <flux:table.row>
                            <flux:table.cell>{{ $v($etiq['itemId'] ?? null) }}</flux:table.cell>
                            <flux:table.cell class="!whitespace-normal">{{ $v($etiq['name'] ?? null) }}</flux:table.cell>
                            <flux:table.cell class="!whitespace-normal">{{ $v($etiq['comentarios'] ?? null) }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @if (! empty($etiq['imagenUrl']))
                                    <button type="button" class="flog-lineas-thumb" aria-label="Ver etiqueta {{ $etiq['name'] ?? $etiq['itemId'] ?? '' }}"
                                            data-flog-zoom="{{ $etiq['imagenUrl'] }}"
                                            data-flog-zoom-title="Etiqueta — {{ $etiq['name'] ?? $etiq['itemId'] ?? '' }}">
                                        <img src="{{ $etiq['imagenUrl'] }}" alt="" loading="lazy" decoding="async" data-flog-img draggable="false">
                                    </button>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-6 text-center text-zinc-500">Sin etiquetas registradas.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:card class="flog-lineas-wrap !p-0">
        <header class="flex min-h-14 flex-wrap items-center gap-2 border-b border-zinc-100 px-4 py-2">
            <flux:heading size="lg" level="3">Líneas</flux:heading>
            <flux:badge size="sm">{{ count($lineas) }}</flux:badge>

            @if (count($lineas) > 0 && count($estadosLineaFiltro) > 0)
                <div class="ms-auto flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado de línea">
                    <flux:button size="sm" variant="outline" class="min-h-touch" data-flog-linea-filtro="todos" aria-pressed="true">
                        Todas <span class="tabular-nums opacity-70">{{ count($lineas) }}</span>
                    </flux:button>
                    @foreach ($estadosLineaFiltro as $estadoFiltro)
                        <flux:button size="sm" variant="outline" class="min-h-touch" data-flog-linea-filtro="{{ $estadoFiltro['codigo'] }}" aria-pressed="false">
                            {{ $estadoFiltro['label'] }} <span class="tabular-nums opacity-70">{{ $estadoFiltro['count'] }}</span>
                        </flux:button>
                    @endforeach
                </div>
            @endif
        </header>

        <flux:table class="flog-lineas-table traza-tabla" container:class="traza-tabla-limitada traza-tabla-limitada--alta" tabindex="0" aria-label="Líneas del Flog">
            <flux:table.columns sticky>
                @foreach ($columnasLineas as $col)
                    <flux:table.column :align="in_array($col['tipo'] ?? 'texto', ['decimal', 'entero'], true) ? 'end' : 'start'">{{ $col['label'] }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($lineas as $linea)
                    <flux:table.row data-estado-linea="{{ $linea['estadoLineaCodigo'] ?? '' }}">
                        @foreach ($columnasLineas as $col)
                            @php
                                $celda = $linea[$col['key']] ?? '';
                                $tipo = $col['tipo'] ?? 'texto';
                                $esLargo = $tipo === 'texto' && in_array($col['key'], ['itemName', 'infoAdicional', 'nombreEtiqueta', 'retailLink'], true);
                            @endphp
                            <flux:table.cell :align="in_array($tipo, ['decimal', 'entero'], true) ? 'end' : 'start'"
                                             @class(['tabular-nums' => in_array($tipo, ['decimal', 'entero'], true), 'min-w-56 !whitespace-normal' => $esLargo])>
                                @if ($tipo === 'estado')
                                    @if (filled($celda) && $celda !== '—')
                                        <flux:badge size="sm" :color="$colorEstado[(string) ($linea['estadoLineaCodigo'] ?? '')] ?? 'zinc'">{{ $celda }}</flux:badge>
                                    @else
                                        —
                                    @endif
                                @elseif ($tipo === 'imagen')
                                    @if (filled($celda))
                                        <button type="button" class="flog-lineas-thumb" aria-label="Ver {{ $col['label'] }}"
                                                data-flog-zoom="{{ $celda }}"
                                                data-flog-zoom-title="{{ $col['titulo'] ?? $col['label'] }} — Línea {{ $linea['lineNum'] ?? '' }}">
                                            <img src="{{ $celda }}" alt="" loading="lazy" data-flog-img draggable="false">
                                        </button>
                                    @else
                                        —
                                    @endif
                                @else
                                    {{ $v($celda !== '' && $celda !== '—' ? $celda : null) }}
                                @endif
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ count($columnasLineas) }}" class="py-6 text-center text-zinc-500">Sin líneas registradas para este Flog.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <p class="flog-lineas-sin-filtro hidden py-6 text-center text-sm text-zinc-500" role="status">Ninguna línea coincide con el estado elegido.</p>
    </flux:card>
@endif
</div>
