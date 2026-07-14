@extends('layouts.app')

@section('page-title', 'Codificación')

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{--
        <button type="button" onclick="subirExcelCatCodificacion()" class="bg-black hover:bg-gray-800 text-white px-4 py-2 rounded-md">
            <i class="fas fa-file-excel text-white"></i>
            <span>Subir Excel</span>
        </button>
        --}}
        <button type="button" onclick="mostrarAlertaNavbar()"
            class="w-28 h-9 flex items-center justify-center p-4 bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors"
            title="Peso Muestra" aria-label="Mostrar alerta">
            Peso M
        </button>
        <button id="btn-lmat" type="button" onclick="mostrarModalLMat()"
            class="w-28 h-9 flex items-center justify-center p-4 bg-black text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            disabled
            title="Lista de materiales" aria-label="Mostrar lista de materiales">
            L Mat
        </button>
        <button id="btn-filtrar" onclick="filtrarCodificacion()"
            class="relative p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
            title="Filtrar" aria-label="Filtrar">
            <i class="fas fa-filter text-lg" aria-hidden="true"></i>
            <span id="filter-count" class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs font-bold hidden">0</span>
        </button>
        <button id="btn-balancear"
            onclick="abrirModalBalancear()"
            class="inline-flex items-center gap-1 px-3 py-3 border border-gray-300 bg-green-500 text-white hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed transition rounded-full p-4"
            disabled
            title="Ver registros compartidos y OrdCompartidaLider"
        >
            <i class="fas fa-balance-scale"></i>
        </button>
        <button id="btn-reimprimir-seleccionado"
            onclick="reimprimirOrdenSeleccionada()"
            class="inline-flex items-center gap-1 px-3 py-1 rounded border border-gray-300 bg-purple-500 text-white hover:bg-purple-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
            disabled
        >
            <i class="fas fa-print"></i>
            <span>Reimprimir Orden</span>
        </button>
        <button id="btn-revivir-programa"
            type="button"
            onclick="revivirOrdenAlPrograma()"
            class="inline-flex items-center gap-1 px-3 py-1 rounded border border-gray-300 bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
            disabled
            title="Limpia FechaFinaliza en cat y crea la orden en programa de tejido"
        >
            <i class="fas fa-undo"></i>
            <span>Revivir a programa</span>
        </button>
    </div>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="relative bg-white rounded-lg shadow-sm flex flex-col" style="height: calc(100vh);">

            {{-- Loading overlay único --}}
            <div
                id="loading-overlay"
                class="absolute inset-0 bg-white hidden items-center text-center justify-center z-20"
            >
                <div class="flex flex-col items-center gap-4">
                    <div class="h-12 w-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    <div class="text-center">
                        <p id="loading-message" class="text-sm font-medium text-gray-700">Cargando datos...</p>
                        <p id="loading-count" class="text-sm text-gray-500 mt-1"></p>
                    </div>
                </div>
            </div>

            {{-- Contenedor tabla + scroffffll --}}
            <div
                id="table-container"
                class="relative flex-1 overflow-y-auto overflow-x-auto"
                style="max-height: calc(100vh - 110px);"
            >
                @php
                    $columnas = $columnas ?? [];
                    $columnLabels = [
                        'OrdenTejido' => 'Num de Orden',
                        'FechaTejido' => 'Fecha Orden',
                        'FechaCumplimiento' => 'Fecha Cumplimiento',
                        'TelarId' => 'Telar',
                        'Nombre' => 'Modelo',
                        'ClaveModelo' => 'Clave Modelo',
                        'ItemId' => 'Clave AX',
                        'HiloAX' => 'Hilo AX',
                        'InventSizeId' => 'Tamaño',
                        'FlogsId' => 'Flogs',
                        'NombreProyecto' => 'Nombre de Formato Logístico',
                        'Cantidad' => 'Cantidad a Producir',
                        'JulioRizo' => 'No Julio Rizo',
                        'JulioPie' => 'No Julio Pie',
                        'EfiInicial' => 'Eficiencia de Inicio',
                        'EfiFinal' => 'Eficiencia Final',
                        'DesperdicioTrama' => 'Desperdicio Trama',
                        'PesoMuestra' => 'Peso Muestra',
                        'OrdPrincipal' => 'Ord. Principal',
                    ];

                    /**
                     * Orden de columnas (CatCodificados).
                     * Nota: sólo reordena las columnas que existan en $columnas;
                     * cualquier columna no listada se conserva al final.
                     */
                    $ordenDeseado = [
                        // Encabezado principal
                        'OrdenTejido',          // Num de Orden
                        'OrdPrincipal',
                        'PesoMuestra',          // Peso Muestra
                        'FechaTejido',         // Fecha Orden
                        'FechaCumplimiento',    // Fecha Cumplimiento
                        'Departamento',
                        'TelarId',              // Telar
                        'Prioridad',
                        'Nombre',               // Modelo
                        'ClaveModelo',          // Clave Modelo
                        'ItemId',               // Clave AX
                        'HiloAX',               // Hilo AX
                        'InventSizeId',         // Tamaño
                        'Tolerancia',
                        'CodigoDibujo',
                        'FechaCompromiso',
                        'FlogsId',              // Flogs
                        'NombreProyecto',       // Nombre de Formato Logístico
                        'Clave',

                        // Medidas / especificación
                        'Cantidad',             // Cantidad a Producir
                        'Peine',
                        'Ancho',
                        'Largo',
                        'P_crudo',
                        'Luchaje',
                        'Tra',

                        // Plano / rizo / pie
                        'DobladilloId',         // Tipo plano
                        'MedidaPlano',          // Med plano
                        'TipoRizo',
                        'AlturaRizo',

                        // Velocidades / observaciones
                        'VelocidadSTD',
                        'Obs',

                        // Cenefa / repeticiones / marbetes
                        'MedidaCenefa',
                        'MedIniRizoCenefa',
                        'Razurada',
                        'NoTiras',
                        'Repeticiones',
                        'NoMarbete',
                        'CambioRepaso',

                        // Comercial / orden / observaciones
                        'Vendedor',
                        'Obs5',                 // Observaciones

                        // Trama / lucha
                        'TramaAnchoPeine',
                        'LogLuchaTotal',

                        // Totales / tiempos
                        'Total',
                        'RespInicio',
                        'HrInicio',
                        'HrTermino',
                        'MinutosCambio',
                        'RegAlinacion',
                        'OBSParaPro',

                        // Producción (final)
                        'CantidadProducir_2',   // Cantidad a Producir (2)
                        'Tejidas',
                        'pzaXrollo',
                    ];

                    // Reordenar: primero las del orden deseado, luego las restantes
                    $presentes = array_values(array_intersect($ordenDeseado, $columnas));
                    $restantes = array_values(array_diff($columnas, $ordenDeseado));
                    $columnas = array_values(array_merge($presentes, $restantes));
                    $columnas = array_values(array_diff($columnas, ['NoOrden']));

                    // Clases visuales por segmento de columnas para mejorar lectura
                    $segmentos = [
                        'seg-main' => [
                            'OrdenTejido','OrdPrincipal','PesoMuestra','FechaTejido','FechaCumplimiento','Departamento',
                            'TelarId','Prioridad','Nombre','ClaveModelo','ItemId','HiloAX','InventSizeId','Tolerancia',
                            'CodigoDibujo','FechaCompromiso','FlogsId','NombreProyecto','Clave',
                        ],
                        'seg-medidas' => ['Cantidad','Peine','Ancho','Largo','P_crudo','Luchaje','Tra'],
                        'seg-plano' => ['DobladilloId','MedidaPlano','TipoRizo','AlturaRizo'],
                        'seg-vel' => ['VelocidadSTD','Obs'],
                        'seg-cenefa' => ['MedidaCenefa','MedIniRizoCenefa','Razurada','NoTiras','Repeticiones','NoMarbete','CambioRepaso'],
                        'seg-comercial' => ['Vendedor','Obs5'],
                        'seg-trama' => ['TramaAnchoPeine','LogLuchaTotal'],
                        'seg-tiempos' => ['Total','RespInicio','HrInicio','HrTermino','MinutosCambio','RegAlinacion','OBSParaPro'],
                        'seg-prod' => ['CantidadProducir_2','Tejidas','pzaXrollo'],
                    ];

                    $columnSegmentClass = [];
                    foreach ($segmentos as $segmentClass => $cols) {
                        foreach ($cols as $col) {
                            $columnSegmentClass[$col] = $segmentClass;
                        }
                    }
                @endphp

                <table id="mainTable" class="w-full min-w-full text-[11px] leading-tight">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10 codificacion-header-context">
                        <tr>
                            @foreach($columnas as $idx => $columna)
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap border-b border-blue-600/70 column-{{ $idx }} {{ $columnSegmentClass[$columna] ?? '' }}" data-column="{{ $columna }}" data-index="{{ $idx }}">
                                    <span class="block truncate">{{ $columnLabels[$columna] ?? $columna }}</span>
                                    <span class="codificacion-header-icons ml-1 inline-flex items-center gap-0.5"></span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody id="catcodificacion-body" class="bg-white text-gray-800">
                        {{-- El contenido se llena por JS --}}
                    </tbody>
                </table>
            </div>

            {{-- Menú contextual para encabezados de columnas (Filtrar, Fijar) --}}
            <div id="codificacionContextMenuHeader" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-[9999] py-1 min-w-[180px]">
                <button type="button" id="codificacionCtxFiltrar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
                    <i class="fas fa-filter text-yellow-500"></i>
                    <span>Filtrar</span>
                </button>
                <button type="button" id="codificacionCtxFijar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 flex items-center gap-2">
                    <i class="fas fa-thumbtack text-blue-500"></i>
                    <span id="codificacionCtxFijarLabel">Fijar</span>
                </button>
            </div>

            {{-- Paginación fija abajo --}}
            <div
                id="pagination-container"
                class="px-4 border-t border-gray-200 bg-white flex-shrink-0 z-20"
            >
                <div class="flex items-center gap-2">
                    <button
                        id="pagination-prev"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded border border-gray-300 bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        disabled
                    >
                        <i class="fas fa-chevron-left text-[10px]"></i>
                        <span>Anterior</span>
                    </button>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2 text-gray-700">
                        <span>
                            Página
                            <span id="pagination-current" class="font-semibold">1</span>
                            de
                            <span id="pagination-total-pages" class="font-semibold">1</span>
                        </span>
                        <span class="text-gray-500">
                            · Mostrando
                            <span id="pagination-start">0</span> -
                            <span id="pagination-end">0</span>
                            de
                            <span id="pagination-total">0</span>
                        </span>
                    </div>

                    <button
                        id="pagination-next"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded border border-gray-300 bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        <span>Siguiente</span>
                        <i class="fas fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #table-container {
            position: relative;
            overflow-y: auto;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            flex: 1;
            min-height: 0;
        }

        #table-container::-webkit-scrollbar {
            width: 14px;
            height: 14px;
        }

        #table-container::-webkit-scrollbar-track {
            background: #e5e7eb;
            border-radius: 7px;
        }

        #table-container::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 7px;
            border: 2px solid #e5e7eb;
        }

        #table-container::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        #table-container::-webkit-scrollbar:horizontal {
            height: 14px;
        }

        #table-container {
            scrollbar-width: auto;
            scrollbar-color: #6b7280 #e5e7eb;
        }

        .swal-select-orden {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        #mainTable {
            position: relative;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: max-content;
            table-layout: auto;
        }

        #mainTable thead {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 1000 !important;
            background-color: #3b82f6 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #mainTable thead th {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1001 !important;
            background-color: #3b82f6 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid #2563eb !important;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            white-space: nowrap;
        }

        #mainTable thead th:last-child {
            border-right: none;
        }

        #mainTable tbody td {
            border-right: 1px solid rgba(0, 0, 0, 0.05);
            white-space: nowrap;
            position: relative;
        }

        .container-fluid {
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .bg-white.rounded-lg.shadow-sm {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
        }

        #pagination-container {
            padding-top: 0.375rem !important;
            padding-bottom: 0.375rem !important;
            min-height: auto !important;
            max-height: 44px !important;
        }

        #pagination-container button {
            padding-top: 0.25rem !important;
            padding-bottom: 0.25rem !important;
            font-size: 0.875rem !important;
            line-height: 1.3 !important;
        }

        #pagination-container span {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 1.2 !important;
        }

        /* Menú contextual en encabezados */
        .codificacion-header-context th {
            cursor: context-menu;
            position: relative;
        }

        /* Iconos en encabezados (filtro activo / columna fijada) */
        .codificacion-header-icons {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        .codificacion-header-icon {
            cursor: pointer;
            border: none;
            background: transparent;
            padding: 3px 4px;
            min-width: 22px;
            min-height: 22px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: inherit;
            font-size: 11px;
        }
        .codificacion-header-icon:hover {
            opacity: 0.9;
            background: rgba(255,255,255,0.25);
        }
        .codificacion-header-icon .fa-filter {
            color: #fcd34d !important;
        }
        .codificacion-header-icon .fa-thumbtack {
            color: #fff !important;
        }

        /* Columnas fijadas (pin) */
        #mainTable thead th.codificacion-pinned,
        #mainTable tbody td.codificacion-pinned {
            position: sticky !important;
            z-index: 5;
            background-color: #1d4ed8 !important;
            color: #fff !important;
            box-shadow: 2px 0 0 rgba(0,0,0,0.12);
        }
        #mainTable tbody td.codificacion-pinned {
            z-index: 1;
        }
        #mainTable thead th.codificacion-pinned {
            z-index: 1200 !important;
            top: 0 !important;
        }

        /* Segmentación visual tipo Excel para facilitar lectura */
        #mainTable thead th.seg-main { background-color: #1d4ed8 !important; }
        #mainTable thead th.seg-medidas { background-color: #166534 !important; }
        #mainTable thead th.seg-plano { background-color: #0f766e !important; }
        #mainTable thead th.seg-vel { background-color: #7c3aed !important; }
        #mainTable thead th.seg-cenefa { background-color: #92400e !important; }
        #mainTable thead th.seg-comercial { background-color: #be123c !important; }
        #mainTable thead th.seg-trama { background-color: #0e7490 !important; }
        #mainTable thead th.seg-tiempos { background-color: #374151 !important; }
        #mainTable thead th.seg-prod { background-color: #854d0e !important; }

        #mainTable tbody td.seg-main { background-color: rgba(29, 78, 216, 0.04); }
        #mainTable tbody td.seg-medidas { background-color: rgba(22, 101, 52, 0.05); }
        #mainTable tbody td.seg-plano { background-color: rgba(15, 118, 110, 0.05); }
        #mainTable tbody td.seg-vel { background-color: rgba(124, 58, 237, 0.05); }
        #mainTable tbody td.seg-cenefa { background-color: rgba(146, 64, 14, 0.05); }
        #mainTable tbody td.seg-comercial { background-color: rgba(190, 18, 60, 0.05); }
        #mainTable tbody td.seg-trama { background-color: rgba(14, 116, 144, 0.05); }
        #mainTable tbody td.seg-tiempos { background-color: rgba(55, 65, 81, 0.05); }
        #mainTable tbody td.seg-prod { background-color: rgba(133, 77, 14, 0.06); }

        /* Fila seleccionada (bg-blue-500, text-white) */
        #mainTable tbody tr.codificacion-row-selected,
        #mainTable tbody tr.codificacion-row-selected td {
            background-color: #3b82f6 !important;
            color: #fff !important;
        }
        #mainTable tbody tr.codificacion-row-selected:hover,
        #mainTable tbody tr.codificacion-row-selected:hover td {
            background-color: #2563eb !important;
            color: #fff !important;
        }
    </style>

    @php
        $catcodificacionConfig = [
            'columnas' => $columnas ?? [],
            'columnLabels' => $columnLabels ?? [],
            'columnSegmentClass' => $columnSegmentClass ?? [],
            'apiUrl' => $apiUrl ?? '/planeacion/codificacion/api/all-fast',
            'totalRegistros' => isset($totalRegistros) ? (int) $totalRegistros : 0,
        ];
    @endphp
    <script id="catcodificacion-config" type="application/json">
        @json($catcodificacionConfig)
    </script>

    @vite('resources/js/catcodificacion/index.js')
    @include('catcodificacion.partials.excel-upload')
@endsection
