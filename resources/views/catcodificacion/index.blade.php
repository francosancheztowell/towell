@extends('layouts.app')

@section('page-title', 'Codificación')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button type="button" onclick="mostrarAlertaNavbar()"
            class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors"
            title="Mostrar alerta" aria-label="Mostrar alerta">
            <i class="fa-solid fa-bell text-sm"></i>
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

    {{-- Script principal --}}
    <script>
        (function () {
            // =========================
            //   CONFIG / ESTADO
            // =========================
            const CONFIG = {
                columnas: {!! json_encode($columnas ?? []) !!},
                columnLabels: @json($columnLabels ?? []),
                columnSegmentClass: @json($columnSegmentClass ?? []),
                apiUrl: {!! json_encode($apiUrl ?? '/planeacion/codificacion/api/all-fast') !!},
                totalRegistros: {{ isset($totalRegistros) ? (int) $totalRegistros : 0 }},
                dateColumns: ['FechaTejido', 'FechaCumplimiento', 'FechaCompromiso', 'FechaCreacion', 'FechaModificacion'],
            };

            const state = {
                data: [],
                filtered: [],
                filtros: [],       // { columna: index, valor: string }
                filtrosPorColumna: [], // { column: string, value: string } para filtros desde menú contextual
                pinnedColumns: [],
                page: 1,
                perPage: 500,
                total: CONFIG.totalRegistros || 0,
                loading: false,
                selectedRowIndex: null, // Índice global de la fila seleccionada
            };

            // =========================
            //   HELPERS DOM
            // =========================
            const $  = (sel, ctx = document) => ctx.querySelector(sel);
            const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

            const getCsrf = () =>
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            function formatDateOnly(val, columnName) {
                if (val == null || val === '') return '';
                if (!CONFIG.dateColumns || !CONFIG.dateColumns.includes(columnName)) return String(val);
                const s = String(val).trim();
                if (!s) return '';
                const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (m) return m[1] + '-' + m[2] + '-' + m[3];
                const d = new Date(s);
                if (!isNaN(d.getTime())) {
                    const y = d.getFullYear();
                    const mo = String(d.getMonth() + 1).padStart(2, '0');
                    const da = String(d.getDate()).padStart(2, '0');
                    return y + '-' + mo + '-' + da;
                }
                return s;
            }

            function setLoading(isLoading, message = 'Cargando datos...', count = '') {
                state.loading = isLoading;
                const overlay  = $('#loading-overlay');
                const messageEl = $('#loading-message');
                const countEl   = $('#loading-count');

                if (!overlay) return;

                if (messageEl) messageEl.textContent = message;
                if (countEl)   countEl.textContent   = count;

                if (isLoading) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                } else {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                }
            }

            // =========================
            //   TOAST SIMPLE (fallback)
            // =========================
            function internalToast(message, type = 'info') {
                const colors = {
                    success: 'bg-green-600',
                    error: 'bg-red-600',
                    warning: 'bg-yellow-500',
                    info: 'bg-blue-600',
                };

                let container = $('#toast-notification');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toast-notification';
                    container.className = 'fixed top-4 right-4 z-[9999] max-w-sm w-full';
                    document.body.appendChild(container);
                }

                container.innerHTML =
                    '<div class="rounded-lg shadow-lg text-white px-4 py-3 ' + (colors[type] || colors.info) + '">' +
                        '<div class="flex items-center justify-between gap-3">' +
                            '<span class="text-sm">' + message + '</span>' +
                            '<button type="button" class="text-sm font-bold hover:opacity-80" onclick="this.closest(\'#toast-notification\').remove()">' +
                                '&times;' +
                            '</button>' +
                        '</div>' +
                    '</div>';

                setTimeout(() => {
                    container?.remove();
                }, 3500);
            }

            // Si ya existe showToast global, úsalo; si no, define el nuestro
            const showToast = window.showToast || internalToast;
            if (!window.showToast) {
                window.showToast = showToast;
            }

            /**
             * Muestra el modal de formulario de codificación al hacer clic en el botón de la navbar.
             */
            function mostrarAlertaNavbar() {
                if (typeof Swal === 'undefined') {
                    internalToast('SweetAlert2 no está cargado.', 'warning');
                    return;
                }

                // Obtener el registro seleccionado si existe
                const registroSeleccionado = state.selectedRowIndex !== null && state.selectedRowIndex !== undefined
                    ? state.filtered[state.selectedRowIndex]
                    : null;

                // Valores iniciales desde el registro seleccionado o vacíos
                const ordenTejido = registroSeleccionado?.OrdenTejido || '';
                const telar = registroSeleccionado?.TelarId || '';
                const articulo = registroSeleccionado?.ItemId || registroSeleccionado?.ClaveModelo || '';
                const pesoMuestra = registroSeleccionado?.PesoMuestra || '';
                const actLmat = registroSeleccionado?.ActualizaLmat === true || registroSeleccionado?.ActualizaLmat === 1 || registroSeleccionado?.ActualizaLmat === '1';
                const bomId = registroSeleccionado?.BomId || '';

                Swal.fire({
                    title: 'Peso Muestra',
                    html: `
                        <div class="text-left space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Orden Tejido</label>
                                    <select
                                        id="swal-orden-tejido"
                                        class="w-full px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-800 appearance-none cursor-pointer swal-select-orden"
                                    >
                                        <option value="">Seleccione una orden en proceso...</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telar</label>
                                    <input
                                        type="text"
                                        id="swal-telar"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none"
                                        placeholder="Los trae del cat codificados"
                                        value="${telar}"
                                        readonly
                                        title="solo para visualización"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Articulo</label>
                                    <input
                                        type="text"
                                        id="swal-articulo"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none"
                                        placeholder="De cat codificados"
                                        value="${articulo}"
                                        readonly
                                        title="solo para visualización"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 border-t border-gray-200 pt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Peso Muestra</label>
                                    <input
                                        type="number"
                                        id="swal-peso-muestra"
                                        step="any"
                                        min="0"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="lo obtiene de catcodificacos y se puede editar"
                                        value="${pesoMuestra !== '' && pesoMuestra !== null && pesoMuestra !== undefined ? Number(pesoMuestra) : ''}"
                                    >
                                </div>
                                <div class="flex items-end pb-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="swal-act-lmat"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                            ${actLmat ? 'checked' : ''}
                                        >
                                        <span class="text-sm font-medium text-gray-700">Act Lmat</span>
                                    </label>
                                </div>
                                <div id="swal-lista-mat-container">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lista L Mat (BomId) <span id="swal-lista-mat-req" class="text-red-500 ${actLmat ? '' : 'hidden'}">*</span></label>
                                    <input
                                        type="text"
                                        id="swal-lista-mat"
                                        list="swal-lista-mat-options"
                                        autocomplete="off"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${actLmat ? '' : 'bg-gray-100 cursor-not-allowed'}"
                                        placeholder="Escriba o elija de la lista (desde L.Mat en TI)"
                                        value="${bomId}"
                                        ${actLmat ? '' : 'disabled readonly'}
                                    >
                                    <datalist id="swal-lista-mat-options"></datalist>
                                    <p id="swal-lista-mat-message" class="text-xs text-gray-500 mt-1 hidden"></p>
                                </div>
                            </div>
                        </div>
                    `,
                    width: '600px',
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    didOpen: () => {
                        // Habilitar/bloquear campo Lista Mat según checkbox Act Lmat (siempre visible)
                        const actLmatCheckbox = document.getElementById('swal-act-lmat');
                        const listaMatInputRef = document.getElementById('swal-lista-mat');

                        function actualizarEstadoListaMat() {
                            const inp = document.getElementById('swal-lista-mat');
                            const reqSpan = document.getElementById('swal-lista-mat-req');
                            if (!inp) return;
                            const activo = actLmatCheckbox && actLmatCheckbox.checked;
                            inp.disabled = !activo;
                            inp.readOnly = !activo;
                            inp.classList.toggle('bg-gray-100', !activo);
                            inp.classList.toggle('cursor-not-allowed', !activo);
                            if (reqSpan) reqSpan.classList.toggle('hidden', !activo);
                            if (!activo) inp.value = '';
                        }

                        if (actLmatCheckbox && listaMatInputRef) {
                            actLmatCheckbox.addEventListener('change', actualizarEstadoListaMat);
                            actualizarEstadoListaMat();
                        }

                        // GET órdenes en proceso (ReqProgramaTejido) y rellenar select
                        const selectOrden = document.getElementById('swal-orden-tejido');
                        const telarInput = document.getElementById('swal-telar');
                        const articuloInput = document.getElementById('swal-articulo');
                        const pesoMuestraInput = document.getElementById('swal-peso-muestra');
                        const listaMatInput = document.getElementById('swal-lista-mat');
                        const listaMatOptions = document.getElementById('swal-lista-mat-options');
                        const listaMatMessage = document.getElementById('swal-lista-mat-message');

                        function actualizarDatalistLmat(opciones, mostrarMensajeSinResultados) {
                            if (!listaMatOptions) return;
                            listaMatOptions.innerHTML = '';
                            if (listaMatMessage) {
                                listaMatMessage.classList.add('hidden');
                                listaMatMessage.textContent = '';
                            }
                            if (opciones && Array.isArray(opciones) && opciones.length > 0) {
                                opciones.forEach(function(item) {
                                    const opt = document.createElement('option');
                                    opt.value = (item.bomId != null) ? String(item.bomId) : '';
                                    opt.label = (item.bomName != null) ? String(item.bomName) : opt.value;
                                    if (opt.value) listaMatOptions.appendChild(opt);
                                });
                            } else if (listaMatMessage && (mostrarMensajeSinResultados === undefined || mostrarMensajeSinResultados)) {
                                listaMatMessage.textContent = 'Sin resultados. Escriba para buscar L.Mat (búsqueda libre sin filtro de tamaño).';
                                listaMatMessage.classList.remove('hidden');
                            }
                        }

                        function buscarBomLibre(term, callback) {
                            const t = (term || '').toString().trim();
                            if (t.length < 2) {
                                if (callback) callback([]);
                                return;
                            }
                            fetch('/planeacion/programa-tejido/liberar-ordenes/bom-sugerencias?freeMode=1&term=' + encodeURIComponent(t), { headers: { 'Accept': 'application/json' } })
                                .then(resp => resp.json())
                                .then(json => {
                                    if (json.success && Array.isArray(json.data)) {
                                        const items = json.data.map(function(r) { return { bomId: r.bomId, bomName: r.bomName }; });
                                        if (callback) callback(items);
                                    } else if (callback) callback([]);
                                })
                                .catch(function() { if (callback) callback([]); });
                        }

                        var debounceBuscarBom = null;
                        if (listaMatInput) {
                            listaMatInput.addEventListener('input', function() {
                                const val = this.value.trim();
                                clearTimeout(debounceBuscarBom);
                                if (val.length < 2) {
                                    actualizarDatalistLmat([], false);
                                    if (listaMatMessage) {
                                        listaMatMessage.textContent = val.length > 0 ? 'Escriba al menos 2 caracteres para buscar.' : 'Escriba para buscar L.Mat (búsqueda libre sin filtro de tamaño).';
                                        listaMatMessage.classList.remove('hidden');
                                    }
                                    return;
                                }
                                debounceBuscarBom = setTimeout(function() {
                                    buscarBomLibre(val, function(opciones) {
                                        actualizarDatalistLmat(opciones, opciones.length === 0);
                                    });
                                }, 300);
                            });
                            listaMatInput.addEventListener('focus', function() {
                                const val = this.value.trim();
                                if (val.length >= 2) buscarBomLibre(val, function(opciones) { actualizarDatalistLmat(opciones, false); });
                            });
                        }

                        function cargarCatCodificadosPorOrden(orden) {
                            const ord = (orden || '').toString().trim();
                            if (!ord) {
                                if (pesoMuestraInput) pesoMuestraInput.value = '';
                                if (actLmatCheckbox) actLmatCheckbox.checked = false;
                                if (listaMatInput) listaMatInput.value = '';
                                if (typeof actualizarEstadoListaMat === 'function') actualizarEstadoListaMat();
                                actualizarDatalistLmat([]);
                                return;
                            }
                            fetch('/planeacion/codificacion/api/catcodificados-por-orden/' + encodeURIComponent(ord), { headers: { 'Accept': 'application/json' } })
                                .then(resp => resp.json())
                                .then(json => {
                                    if (!json.s) return;
                                    const d = json.d;
                                    if (!d) {
                                        if (pesoMuestraInput) pesoMuestraInput.value = '';
                                        if (actLmatCheckbox) actLmatCheckbox.checked = false;
                                        if (listaMatInput) listaMatInput.value = '';
                                        if (typeof actualizarEstadoListaMat === 'function') actualizarEstadoListaMat();
                                        actualizarDatalistLmat([]);
                                        return;
                                    }
                                    if (pesoMuestraInput) pesoMuestraInput.value = (d.pesoMuestra != null && d.pesoMuestra !== '') ? Number(d.pesoMuestra) : '';
                                    if (actLmatCheckbox) actLmatCheckbox.checked = d.actualizaLmat === true || d.actualizaLmat === 1;
                                    if (listaMatInput) listaMatInput.value = d.bomId != null ? String(d.bomId) : '';
                                    if (typeof actualizarEstadoListaMat === 'function') actualizarEstadoListaMat();
                                    actualizarDatalistLmat(d.listaLmat || []);
                                    if (telarInput && (d.telarId != null && d.telarId !== '')) telarInput.value = String(d.telarId);
                                    if (articuloInput && (d.itemId != null || d.nombre != null)) articuloInput.value = (d.itemId != null ? String(d.itemId) : '') || (d.nombre != null ? String(d.nombre) : '');
                                })
                                .catch(() => {});
                        }

                        if (selectOrden) {
                            fetch('/planeacion/codificacion/api/ordenes-en-proceso', { headers: { 'Accept': 'application/json' } })
                                .then(resp => resp.json())
                                .then(json => {
                                    if (json.s && Array.isArray(json.d)) {
                                        json.d.forEach(item => {
                                            const opt = document.createElement('option');
                                            opt.value = item.noProduccion || '';
                                            opt.dataset.noTelarId = item.noTelarId != null ? String(item.noTelarId) : '';
                                            opt.dataset.itemId = item.itemId != null ? String(item.itemId) : '';
                                            opt.dataset.nombreProducto = item.nombreProducto || '';
                                            opt.textContent = item.noProduccion || '';
                                            selectOrden.appendChild(opt);
                                        });
                                        if (ordenTejido) selectOrden.value = ordenTejido;
                                        selectOrden.addEventListener('change', function() {
                                            const opt = this.options[this.selectedIndex];
                                            if (opt && opt.value) {
                                                if (telarInput) telarInput.value = opt.dataset.noTelarId || '';
                                                if (articuloInput) articuloInput.value = opt.dataset.itemId || opt.dataset.nombreProducto || '';
                                                cargarCatCodificadosPorOrden(this.value);
                                            } else {
                                                if (telarInput) telarInput.value = '';
                                                if (articuloInput) articuloInput.value = '';
                                                cargarCatCodificadosPorOrden('');
                                            }
                                        });
                                        if (selectOrden.value && selectOrden.dispatchEvent) selectOrden.dispatchEvent(new Event('change'));
                                    }
                                })
                                .catch(() => {});
                            selectOrden.focus();
                        }
                    },
                    preConfirm: () => {
                        const ordenTejido = document.getElementById('swal-orden-tejido')?.value.trim() || '';
                        const pesoMuestraRaw = document.getElementById('swal-peso-muestra')?.value?.trim();
                        const pesoMuestra = pesoMuestraRaw === '' || pesoMuestraRaw === undefined
                            ? null
                            : parseFloat(pesoMuestraRaw);
                        const actLmat = document.getElementById('swal-act-lmat')?.checked || false;
                        const bomIdRaw = document.getElementById('swal-lista-mat')?.value.trim() || '';
                        const bomId = actLmat ? bomIdRaw : null;

                        // Validaciones básicas
                        if (!ordenTejido) {
                            Swal.showValidationMessage('Seleccione una orden en proceso');
                            return false;
                        }
                        if (pesoMuestra !== null && (Number.isNaN(pesoMuestra) || pesoMuestra < 0)) {
                            Swal.showValidationMessage('Peso Muestra debe ser un número mayor o igual a 0');
                            return false;
                        }
                        if (actLmat && (!bomId || bomId.trim() === '')) {
                            Swal.showValidationMessage('Lista L Mat (BomId) es obligatoria cuando Act Lmat está activo');
                            return false;
                        }

                        return {
                            ordenTejido,
                            pesoMuestra,
                            actLmat,
                            bomId
                        };
                    }
                }).then(async (result) => {
                    if (result.isConfirmed && result.value) {
                        const datos = result.value;
                        try {
                            Swal.fire({
                                title: 'Guardando...',
                                text: 'Por favor espera',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            const resp = await fetch('/planeacion/codificacion/api/actualizar-peso-muestra-lmat', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': getCsrf(),
                                },
                                body: JSON.stringify({
                                    ordenTejido: datos.ordenTejido,
                                    pesoMuestra: datos.pesoMuestra,
                                    actualizaLmat: datos.actLmat,
                                    bomId: datos.bomId || null,
                                }),
                            });

                            const json = await resp.json();

                            Swal.close();

                            if (!json.s) {
                                showToast(json.e || 'Error al guardar los datos', 'error');
                                return;
                            }

                            showToast(json.message || 'Datos guardados correctamente', 'success');
                            if (json.actualizados && json.actualizados.length > 0) {
                                console.log('Actualizados:', json.actualizados);
                            }
                            // Recargar tabla con datos frescos (nocache + caché limpiada en backend)
                            await loadData(true);
                        } catch (error) {
                            Swal.close();
                            showToast('Error al guardar: ' + (error.message || 'Error desconocido'), 'error');
                            console.error('Error al guardar:', error);
                        }
                    }
                });
            }

            // =========================
            //   CARGA DE DATOS
            // =========================
            async function loadData(forceRefresh) {
                if (!CONFIG.apiUrl || state.loading) return;

                setLoading(true, 'Cargando datos...', '');

                const url = forceRefresh
                    ? (CONFIG.apiUrl + (CONFIG.apiUrl.indexOf('?') !== -1 ? '&' : '?') + 'nocache=1')
                    : CONFIG.apiUrl;

                try {
                    const resp = await fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!resp.ok) {
                        throw new Error('HTTP ' + resp.status);
                    }

                    const json = await resp.json();

                    if (!json.s) {
                        throw new Error(json.e || 'Error al cargar datos');
                    }

                    const columnas = (json.c && json.c.length) ? json.c : CONFIG.columnas;
                    const raw      = Array.isArray(json.d) ? json.d : [];
                    const totalRows = raw.length;

                    setLoading(true, 'Procesando registros...', totalRows.toLocaleString() + ' registros');

                    state.data = raw.map(rowArr => {
                        const obj = {};
                        const rowLen = Array.isArray(rowArr) ? rowArr.length : 0;
                        const colLen = columnas.length;
                        for (let j = 0; j < colLen; j++) {
                            const key = columnas[j];
                            if (key) obj[key] = (j < rowLen ? rowArr[j] : null) ?? null;
                        }
                        return obj;
                    });

                    // Ordenar por Id descendente (más nuevos primero)
                    const idIndex = columnas.indexOf('Id');
                    if (idIndex !== -1) {
                        state.data.sort((a, b) => {
                            const idA = parseInt(a.Id) || 0;
                            const idB = parseInt(b.Id) || 0;
                            return idB - idA; // Descendente: mayor Id primero
                        });
                    }

                    state.filtered = [...state.data];
                    state.total    = json.t || state.data.length;

                    aplicarFiltrosAND();

                    renderPage();
                    updateFilterCount();
                    actualizarEstadoBotonReimprimir();

                    setLoading(false);
                } catch (error) {
                    console.error('loadData error:', error);
                    const tbody = $('#catcodificacion-body');
                    const colCount = (CONFIG.columnas && CONFIG.columnas.length) ? CONFIG.columnas.length : 1;
                    if (tbody) {
                        tbody.innerHTML =
                            '<tr>' +
                                '<td colspan="' + colCount + '" class="py-16 text-center">' +
                                    '<div class="flex flex-col items-center gap-2">' +
                                        '<i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>' +
                                        '<p class="text-red-600 font-medium">Error al cargar datos</p>' +
                                        '<p class="text-sm text-gray-500">' + (error.message || 'Error desconocido') + '</p>' +
                                        '<button type="button" class="mt-2 px-3 py-1.5 text-sm rounded bg-blue-500 text-white hover:bg-blue-600" onclick="loadData()">' +
                                            'Reintentar' +
                                        '</button>' +
                                    '</div>' +
                                '</td>' +
                            '</tr>';
                    }
                    showToast('Error al cargar datos: ' + (error.message || 'Error desconocido'), 'error');
                    setLoading(false);
                }
            }

            // =========================
            //   RENDER / PAGINACIÓN
            // =========================
            function renderPage() {
                const tbody = $('#catcodificacion-body');
                if (!tbody) return;

                const totalCols = CONFIG.columnas.length;
                const data = state.filtered;

                if (!data.length) {
                    tbody.innerHTML =
                        '<tr>' +
                            '<td colspan="' + totalCols + '" class="py-16 text-center">' +
                                '<div class="flex flex-col items-center gap-2">' +
                                    '<i class="fas fa-search text-gray-300 text-4xl"></i>' +
                                    '<p class="text-gray-500 text-sm font-medium">No hay datos para mostrar</p>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                    updatePagination();
                    return;
                }

                const startIndex = (state.page - 1) * state.perPage;
                const endIndex   = startIndex + state.perPage;
                const pageData   = data.slice(startIndex, endIndex);

                const fragment = document.createDocumentFragment();

                pageData.forEach((row, rowIndex) => {
                    const globalIndex = startIndex + rowIndex;
                    const tr = document.createElement('tr');
                    tr.className = 'cursor-pointer transition-colors';
                    tr.dataset.index = globalIndex;

                    // Verificar si esta fila está seleccionada
                    const isSelected = state.selectedRowIndex === globalIndex;
                    const isEven = rowIndex % 2 === 0;

                    // Aplicar estilos según selección y alternancia
                    if (isSelected) {
                        tr.classList.add('codificacion-row-selected');
                    } else {
                        tr.classList.add(...(isEven ? ['bg-white', 'hover:bg-gray-100'] : ['bg-gray-100', 'hover:bg-gray-200']));
                    }

                    // Evento click para seleccionar/deseleccionar
                    tr.addEventListener('click', () => {
                        // Si ya está seleccionada, deseleccionar
                        if (state.selectedRowIndex === globalIndex) {
                            state.selectedRowIndex = null;
                            tr.classList.remove('codificacion-row-selected', 'bg-white', 'bg-gray-100', 'hover:bg-gray-100', 'hover:bg-gray-200');
                            tr.classList.add(...(rowIndex % 2 === 0 ? ['bg-white', 'hover:bg-gray-100'] : ['bg-gray-100', 'hover:bg-gray-200']));
                            tr.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-white');
                                td.classList.add('text-gray-700');
                            });
                            actualizarEstadoBotonReimprimir();
                        } else {
                            // Deseleccionar fila anterior si existe
                            const prevSelected = tbody.querySelector('tr.codificacion-row-selected');
                            if (prevSelected) {
                                const prevIdx = parseInt(prevSelected.dataset.index, 10);
                                const prevRowIdx = prevIdx - startIndex;
                                prevSelected.classList.remove('codificacion-row-selected', 'bg-white', 'bg-gray-100', 'hover:bg-gray-100', 'hover:bg-gray-200');
                                prevSelected.classList.add(...(prevRowIdx % 2 === 0 ? ['bg-white', 'hover:bg-gray-100'] : ['bg-gray-100', 'hover:bg-gray-200']));
                                prevSelected.querySelectorAll('td').forEach(td => {
                                    td.classList.remove('text-white');
                                    td.classList.add('text-gray-700');
                                });
                            }

                            // Seleccionar nueva fila: quitar fondos alternados y aplicar clase de selección
                            tr.classList.remove('bg-white', 'bg-gray-100', 'hover:bg-gray-100', 'hover:bg-gray-200');
                            tr.classList.add('codificacion-row-selected');
                            tr.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-gray-700');
                                td.classList.add('text-white');
                            });
                            state.selectedRowIndex = globalIndex;
                            actualizarEstadoBotonReimprimir();
                        }
                    });

                    CONFIG.columnas.forEach((col, colIdx) => {
                        const segmentClass = (CONFIG.columnSegmentClass && CONFIG.columnSegmentClass[col]) ? CONFIG.columnSegmentClass[col] : '';
                        const td = document.createElement('td');
                        td.className = 'px-3 py-1.5 border-b border-gray-100 whitespace-nowrap text-[11px] column-' + colIdx + ' ' + segmentClass + ' ' +
                            (isSelected ? 'text-white' : 'text-gray-700');
                        td.setAttribute('data-column', col);
                        td.setAttribute('data-index', colIdx);
                        const value = row[col] ?? '';
                        td.textContent = formatDateOnly(value, col);
                        tr.appendChild(td);
                    });

                    fragment.appendChild(tr);
                });

                tbody.innerHTML = '';
                tbody.appendChild(fragment);
                try {
                    updatePinnedPositions();
                    updateColumnHeaderIcons();
                } catch (e) {
                    console.warn('updatePinnedPositions/updateColumnHeaderIcons:', e);
                }
                updatePagination();
                actualizarEstadoBotonReimprimir();
            }

            function updatePagination() {
                const total      = state.filtered.length;
                const totalPages = Math.max(1, Math.ceil(total / state.perPage));

                state.page = Math.min(state.page, totalPages);

                const start = total ? (state.page - 1) * state.perPage + 1 : 0;
                const end   = total ? Math.min(state.page * state.perPage, total) : 0;

                const currentEl    = $('#pagination-current');
                const totalPagesEl = $('#pagination-total-pages');
                const startEl      = $('#pagination-start');
                const endEl        = $('#pagination-end');
                const totalEl      = $('#pagination-total');

                if (currentEl)    currentEl.textContent    = state.page;
                if (totalPagesEl) totalPagesEl.textContent = totalPages;
                if (startEl)      startEl.textContent      = start.toLocaleString();
                if (endEl)        endEl.textContent        = end.toLocaleString();
                if (totalEl)      totalEl.textContent      = total.toLocaleString();

                const prev = $('#pagination-prev');
                const next = $('#pagination-next');

                if (prev) prev.disabled = state.page <= 1;
                if (next) next.disabled = state.page >= totalPages;
            }

            // =========================
            //   COLUMNAS FIJADAS
            // =========================
            function getColumnElements(index) {
                return $$('#mainTable .column-' + index);
            }

            function updatePinnedPositions() {
                const table = $('#mainTable');
                if (!table || !state.pinnedColumns || !state.pinnedColumns.length) {
                    $$('#mainTable th[data-index], #mainTable td[data-index]').forEach(el => {
                        el.classList.remove('codificacion-pinned');
                        el.style.left = '';
                        el.style.zIndex = '';
                        if (el.tagName === 'TD') el.style.position = '';
                        if (el.tagName === 'TH') el.style.top = '';
                    });
                    return;
                }
                let left = 0;
                state.pinnedColumns.forEach(idx => {
                    const els = getColumnElements(idx);
                    const th = els.find(el => el.tagName === 'TH');
                    if (!th) return;
                    const w = th.offsetWidth || 80;
                    els.forEach(el => {
                        el.classList.add('codificacion-pinned');
                        el.style.left = left + 'px';
                        el.style.position = 'sticky';
                        if (el.tagName === 'TH') {
                            el.style.top = '0';
                            el.style.zIndex = '1200';
                        } else {
                            el.style.zIndex = '30';
                        }
                    });
                    left += w;
                });
                $$('#mainTable th[data-index], #mainTable td[data-index]').forEach(el => {
                    const dataIndex = el.getAttribute('data-index');
                    const idx = dataIndex !== null && dataIndex !== '' ? parseInt(dataIndex, 10) : NaN;
                    if (Number.isNaN(idx) || !state.pinnedColumns.includes(idx)) {
                        el.classList.remove('codificacion-pinned');
                        el.style.left = '';
                        el.style.zIndex = '';
                        if (el.tagName === 'TD') el.style.position = '';
                        if (el.tagName === 'TH') el.style.top = '';
                    }
                });
            }

            function updateColumnHeaderIcons() {
                if (!CONFIG.columnas || !CONFIG.columnas.length) return;
                CONFIG.columnas.forEach((col, idx) => {
                    const th = document.querySelector('#mainTable thead th.column-' + idx);
                    if (!th) return;
                    const field = col;
                    const container = th.querySelector('.codificacion-header-icons');
                    if (!container) return;
                    let html = '';
                    const hasFilter = (state.filtrosPorColumna || []).some(f => f.column === field);
                    if (hasFilter) {
                        html += '<button type="button" class="codificacion-header-icon" data-action="clear-filter" data-column="' + escapeHtml(field) + '" title="Quitar filtro"><i class="fas fa-filter"></i></button>';
                    }
                    if (state.pinnedColumns.includes(idx)) {
                        html += '<button type="button" class="codificacion-header-icon" data-action="unpin" data-index="' + idx + '" title="Desfijar"><i class="fas fa-thumbtack"></i></button>';
                    }
                    container.innerHTML = html;
                });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // =========================
            //   FILTROS
            // =========================
            function aplicarFiltrosAND() {
                if (!state.data.length) {
                    state.filtered = [];
                    return;
                }

                let filtered = [...state.data];

                // Aplicar filtros antiguos (por índice de columna)
                if (state.filtros.length) {
                    filtered = filtered.filter(row => {
                        return state.filtros.every(f => {
                            const colName = CONFIG.columnas[f.columna];
                            if (!colName) return true;

                            const cell   = String(row[colName] ?? '').toLowerCase();
                            const needle = f.valor.toLowerCase().trim();
                            return cell.includes(needle);
                        });
                    });
                }

                // Aplicar filtros por columna (desde menú contextual)
                if (state.filtrosPorColumna && state.filtrosPorColumna.length) {
                    const byColumn = {};
                    state.filtrosPorColumna.forEach(f => {
                        if (!byColumn[f.column]) byColumn[f.column] = [];
                        byColumn[f.column].push(String(f.value || '').toLowerCase().trim());
                    });
                    filtered = filtered.filter(row => {
                        return Object.entries(byColumn).every(([col, values]) => {
                            const cellVal = row[col];
                            const str = (cellVal != null ? String(cellVal) : '').toLowerCase().trim();
                            return values.includes(str);
                        });
                    });
                }

                state.filtered = filtered;

                // Mantener ordenamiento por Id descendente (más nuevos primero)
                state.filtered.sort((a, b) => {
                    const idA = parseInt(a.Id) || 0;
                    const idB = parseInt(b.Id) || 0;
                    return idB - idA; // Descendente: mayor Id primero
                });

                state.page = 1;
                state.selectedRowIndex = null; // Limpiar selección al filtrar
                renderPage();
                updateFilterCount();
                actualizarEstadoBotonReimprimir();
            }

            function updateFilterCount() {
                const counter = document.getElementById('filter-count');
                if (!counter) return;

                const count = state.filtros.length + (state.filtrosPorColumna?.length || 0);
                if (count > 0) {
                    counter.textContent = count;
                    counter.classList.remove('hidden');
                } else {
                    counter.classList.add('hidden');
                }
            }

            /**
             * Acción rápida: filtrar solo registros con OrdCompartida y OrdCompartidaLider llenos.
             */
            function aplicarAccionRapidaOrdCompartida() {
                if (!state.data.length) {
                    showToast('Espera a que carguen los datos', 'warning');
                    return;
                }

                state.filtered = state.data.filter(row => {
                    const ordCompartida = row.OrdCompartida;
                    const ordCompartidaLider = row.OrdCompartidaLider;
                    const hasOrdCompartida = ordCompartida != null && ordCompartida !== '' && String(ordCompartida).trim() !== '';
                    const hasOrdCompartidaLider = ordCompartidaLider != null && ordCompartidaLider !== '' && String(ordCompartidaLider).trim() !== '';
                    return hasOrdCompartida && hasOrdCompartidaLider;
                });

                state.page = 1;
                state.selectedRowIndex = null;
                renderPage();
                updatePagination();
                updateFilterCount();
                actualizarEstadoBotonReimprimir();

                showToast(
                    state.filtered.length
                        ? state.filtered.length + ' de ' + state.data.length + ' registros con OrdCompartida'
                        : 'No hay registros con OrdCompartida y OrdCompartidaLider llenos',
                    state.filtered.length ? 'success' : 'warning'
                );
            }

            function filtrarCodificacion() {
                Swal.fire({
                    html: `
                        <div class="text-left">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-base font-semibold text-gray-800">Filtrar datos</h2>
                                <button type="button" id="btn-close-modal" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-lg border-2 border-amber-200 bg-amber-50 p-3">
                                    <p class="text-sm font-semibold text-amber-900 mb-2">Acciones rápidas</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" id="btn-quick-ordcompartida" class="px-3 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-medium transition-colors flex items-center gap-2">
                                            <i class="fas fa-link"></i> Obtener OrdCompartida
                                        </button>
                                    </div>
                                    <p class="text-xs text-amber-800 mt-1">Solo registros con OrdCompartida y OrdCompartidaLider llenos.</p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-2">
                                    <select
                                        id="filtro-columna"
                                        class="flex-1 px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="">Columna...</option>
                                    </select>

                                    <input
                                        type="text"
                                        id="filtro-valor"
                                        class="flex-1 px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Valor a buscar..."
                                    >

                                    <button
                                        type="button"
                                        id="btn-add-filter"
                                        class="px-3 py-2 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600"
                                    >
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>

                                <div id="modal-active-filters" class="space-y-2 hidden">
                                    <p class="text-sm font-semibold text-gray-700">Filtros activos:</p>
                                    <div id="modal-filters-list" class="flex flex-wrap gap-2"></div>
                                </div>

                                <div id="btn-clear-container" class="hidden">
                                    <button
                                        type="button"
                                        id="btn-clear-filters"
                                        class="w-full px-3 py-2 bg-red-500 text-white rounded-md text-sm hover:bg-red-600"
                                    >
                                        Limpiar todos los filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                    `,
                    showConfirmButton: false,
                    showCancelButton: false,
                    width: 580,
                    didOpen: () => {
                        const btnQuickOrdCompartida = document.getElementById('btn-quick-ordcompartida');
                        if (btnQuickOrdCompartida) {
                            btnQuickOrdCompartida.addEventListener('click', () => {
                                aplicarAccionRapidaOrdCompartida();
                                Swal.close();
                            });
                        }

                        // Poblar select columnas
                        const colSelect = document.getElementById('filtro-columna');
                        if (colSelect) {
                            CONFIG.columnas.forEach((col, idx) => {
                                const option = document.createElement('option');
                                option.value = idx;
                                option.textContent = col;
                                colSelect.appendChild(option);
                            });
                        }

                        const activeFilters = document.getElementById('modal-active-filters');
                        const clearContainer = document.getElementById('btn-clear-container');
                        if (state.filtros.length > 0 || (state.filtrosPorColumna && state.filtrosPorColumna.length > 0)) {
                            activeFilters?.classList.remove('hidden');
                            clearContainer?.classList.remove('hidden');
                        }

                        const closeBtn = document.getElementById('btn-close-modal');
                        closeBtn?.addEventListener('click', () => Swal.close());

                        document.getElementById('btn-add-filter')?.addEventListener('click', addFilterFromModal);
                        document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
                            state.filtros = [];
                            state.filtrosPorColumna = [];
                            aplicarFiltrosAND();
                            state.page = 1;
                            renderPage();
                            updateColumnHeaderIcons();
                            renderModalFilters();
                            updateFilterCount();
                            Swal.close();
                        });

                        const valorInput = document.getElementById('filtro-valor');
                        valorInput?.addEventListener('keydown', e => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                addFilterFromModal();
                            }
                        });

                        renderModalFilters();
                    }
                });
            }

            function addFilterFromModal() {
                const colSelect  = document.getElementById('filtro-columna');
                const valueInput = document.getElementById('filtro-valor');
                if (!colSelect || !valueInput) return;

                const columna = parseInt(colSelect.value, 10);
                const valor   = valueInput.value.trim();

                if (Number.isNaN(columna)) {
                    showToast('Selecciona una columna', 'warning');
                    return;
                }
                if (!valor) {
                    showToast('Ingresa un valor para filtrar', 'warning');
                    return;
                }

                const exists = state.filtros.some(
                    f => f.columna === columna && f.valor.toLowerCase() === valor.toLowerCase()
                );
                if (exists) {
                    showToast('Este filtro ya existe', 'warning');
                    return;
                }

                state.filtros.push({ columna, valor });
                valueInput.value = '';
                colSelect.selectedIndex = 0;

                aplicarFiltrosAND();
                renderModalFilters();

                if (!state.filtered.length) {
                    showToast('No se encontraron resultados', 'warning');
                } else {
                    showToast(state.filtered.length + ' registros encontrados', 'success');
                }
            }

            function renderModalFilters() {
                const container = document.getElementById('modal-active-filters');
                const list      = document.getElementById('modal-filters-list');
                const clearBox  = document.getElementById('btn-clear-container');

                if (!container || !list) return;

                const totalFiltros = state.filtros.length + (state.filtrosPorColumna?.length || 0);
                if (totalFiltros === 0) {
                    container.classList.add('hidden');
                    clearBox?.classList.add('hidden');
                    return;
                }

                container.classList.remove('hidden');
                clearBox?.classList.remove('hidden');

                let html = '';
                // Filtros antiguos (por índice)
                state.filtros.forEach((filtro, index) => {
                    const colName = CONFIG.columnas[filtro.columna] || 'Columna';
                    html += '<span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-[10px]">' +
                        '<span class="font-semibold">' + escapeHtml(colName) + ':</span>' +
                        '<span>"' + escapeHtml(filtro.valor) + '"</span>' +
                        '<button type="button" class="ml-1 hover:text-red-600 font-bold" onclick="removeFilterFromModal(' + index + ')">' +
                            '&times;' +
                        '</button>' +
                    '</span>';
                });
                // Filtros por columna (desde menú contextual)
                if (state.filtrosPorColumna && state.filtrosPorColumna.length) {
                    state.filtrosPorColumna.forEach((filtro, index) => {
                        const colLabel = CONFIG.columnLabels[filtro.column] || filtro.column;
                        html += '<span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-[10px]">' +
                            '<i class="fas fa-filter text-yellow-600"></i>' +
                            '<span class="font-semibold">' + escapeHtml(colLabel) + ':</span>' +
                            '<span>"' + escapeHtml(filtro.value) + '"</span>' +
                            '<button type="button" class="ml-1 hover:text-red-600 font-bold" onclick="removeFilterPorColumna(' + index + ')">' +
                                '&times;' +
                            '</button>' +
                        '</span>';
                    });
                }
                list.innerHTML = html;
            }

            function removeFilterPorColumna(index) {
                state.filtrosPorColumna.splice(index, 1);
                aplicarFiltrosAND();
                state.page = 1;
                renderPage();
                updateColumnHeaderIcons();
                renderModalFilters();
                updateFilterCount();
                showToast('Filtro eliminado', 'info');
            }

            function removeFilterFromModal(index) {
                state.filtros.splice(index, 1);
                aplicarFiltrosAND();
                renderModalFilters();
                showToast('Filtro eliminado', 'info');
            }

            function limpiarFiltrosCodificacion() {
                state.filtros = [];
                state.filtrosPorColumna = [];
                aplicarFiltrosAND();
                state.page = 1;
                renderPage();
                updateColumnHeaderIcons();
                updateFilterCount();
                showToast('Filtros limpiados', 'info');
            }

            // =========================
            //   EXCEL
            // =========================
            function subirExcelCatCodificacion() {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = '.xlsx,.xls';

                input.onchange = e => {
                    const file = e.target.files?.[0];
                    if (!file) return;

                    const sizeMB = file.size / 1024 / 1024;
                    if (sizeMB > 10) {
                        showToast('El archivo máximo permitido es de 10MB', 'warning');
                        return;
                    }

                    Swal.fire({
                        title: '¿Procesar Excel?',
                        text: file.name + ' (' + sizeMB.toFixed(2) + ' MB)',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Procesar',
                        cancelButtonText: 'Cancelar',
                    }).then(result => {
                        if (result.isConfirmed) {
                            procesarExcel(file);
                        }
                    });
                };

                input.click();
            }

            function procesarExcel(file) {
                const formData = new FormData();
                formData.append('archivo_excel', file);

                Swal.fire({
                    title: 'Procesando...',
                    html: '<p class="text-sm text-gray-600">Estamos procesando tu archivo, esto puede tardar unos minutos.</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('/planeacion/codificacion/excel', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrf(),
                    },
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data?.poll_url) {
                        pollImportProgress(data.data.poll_url);
                        return;
                    }

                    Swal.close();
                    showToast(data.message || 'Error al procesar el archivo', 'error');
                })
                .catch(error => {
                    Swal.close();
                    showToast(error.message, 'error');
                });
            }

            function pollImportProgress(url, attempts = 0) {
                if (attempts > 600) {
                    Swal.close();
                    showToast('Tiempo de espera agotado al procesar el archivo', 'warning');
                    return;
                }

                fetch(url)
                    .then(r => r.json())
                    .then(result => {
                        if (!result.success || !result.data) {
                            setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                            return;
                        }

                        const data     = result.data;
                        const processed = data.processed_rows ?? 0;
                        const total     = data.total_rows ?? '?';
                        const created   = data.created ?? 0;
                        const updated   = data.updated ?? 0;
                        const percent   = result.percent ?? 0;

                        Swal.update({
                            html:
                                '<div class="text-sm text-gray-700 text-left">' +
                                    '<p class="mb-2">Procesando archivo...</p>' +
                                    '<p class="font-semibold mb-2">' + processed + '/' + total + ' filas (' + percent + '%)</p>' +
                                    '<p class="text-sm text-gray-600 mb-1">Creados: ' + created + ' · Actualizados: ' + updated + '</p>' +
                                '</div>'
                        });

                        if (data.status === 'done') {
                            Swal.close();
                            Swal.fire({
                                title: 'Importación completa',
                                icon: 'success',
                                html:
                                    '<div class="text-sm text-gray-700 text-left">' +
                                        '<p>Registros creados: <strong>' + created + '</strong></p>' +
                                        '<p>Registros actualizados: <strong>' + updated + '</strong></p>' +
                                    '</div>',
                            }).then(() => location.reload());
                            return;
                        }

                        setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                    })
                    .catch(() => {
                        setTimeout(() => pollImportProgress(url, attempts + 1), 1000);
                    });
            }

            // =========================
            //   MENÚ CONTEXTUAL EN ENCABEZADOS
            // =========================
            const menu = $('#codificacionContextMenuHeader');
            let menuColumnIndex = null;
            let menuColumnField = null;

            function hideContextMenu() {
                if (menu) {
                    menu.classList.add('hidden');
                    menu.style.display = 'none';
                }
                menuColumnIndex = null;
                menuColumnField = null;
            }

            function showContextMenu(e, columnIndex, columnField) {
                menuColumnIndex = columnIndex;
                menuColumnField = columnField;
                if (!menu) return;
                const fijarLabel = $('#codificacionCtxFijarLabel');
                if (fijarLabel) fijarLabel.textContent = state.pinnedColumns.includes(columnIndex) ? 'Desfijar' : 'Fijar';
                menu.style.left = e.clientX + 'px';
                menu.style.top = e.clientY + 'px';
                menu.style.display = 'block';
                const rect = menu.getBoundingClientRect();
                if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
                if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';
                menu.classList.remove('hidden');
            }

            function openFilterModal(columnIndex, columnField) {
                const columnLabel = CONFIG.columnLabels[columnField] || columnField;
                const valueCounts = new Map();
                state.filtered.forEach(row => {
                    const v = row[columnField];
                    const str = (v != null ? String(v) : '').trim();
                    if (!valueCounts.has(str)) valueCounts.set(str, { raw: str, count: 0 });
                    valueCounts.get(str).count++;
                });
                const uniqueValues = Array.from(valueCounts.keys()).filter(Boolean).sort();
                if (uniqueValues.length === 0) {
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'info', title: 'Sin valores', text: 'No hay valores para filtrar en esta columna.' });
                    return;
                }
                const currentForColumn = (state.filtrosPorColumna || []).filter(f => f.column === columnField).map(f => f.value);

                let html = '<div class="text-left"><p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>' + escapeHtml(columnLabel) + '</strong></p>';
                html += '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">';
                html += '<div class="mb-2 pb-2 border-b border-gray-200"><input type="text" id="codificacionFilterSearch" placeholder="Buscar..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></div>';
                html += '<div id="codificacionFilterCheckboxes" class="space-y-1">';
                uniqueValues.forEach(value => {
                    const entry = valueCounts.get(value);
                    const count = entry ? entry.count : 0;
                    const checked = currentForColumn.includes(value) ? ' checked' : '';
                    html += '<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"><div class="flex items-center gap-2">';
                    html += '<input type="checkbox" class="codificacion-filter-cb w-4 h-4 text-blue-600" value="' + escapeHtml(value) + '"' + checked + '>';
                    html += '<span class="text-sm text-gray-700">' + escapeHtml(value) + '</span></div><span class="text-xs text-gray-500">(' + count + ')</span></label>';
                });
                html += '</div></div></div>';

                Swal.fire({
                    title: 'Filtrar columna',
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    width: '500px',
                    didOpen: () => {
                        const search = document.getElementById('codificacionFilterSearch');
                        const container = document.getElementById('codificacionFilterCheckboxes');
                        if (search && container) {
                            search.addEventListener('input', () => {
                                const term = search.value.toLowerCase();
                                container.querySelectorAll('label').forEach(lab => {
                                    const text = (lab.textContent || '').toLowerCase();
                                    lab.style.display = text.includes(term) ? '' : 'none';
                                });
                            });
                        }
                    },
                    preConfirm: () => {
                        const checked = $$('.codificacion-filter-cb:checked').map(cb => cb.value);
                        return checked;
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;
                    state.filtrosPorColumna = (state.filtrosPorColumna || []).filter(f => f.column !== columnField);
                    (result.value || []).forEach(v => {
                        state.filtrosPorColumna.push({ column: columnField, value: v });
                    });
                    aplicarFiltrosAND();
                    state.page = 1;
                    renderPage();
                    updateFilterCount();
                });
            }

            // =========================
            //   INIT
            // =========================
            function initPaginationEvents() {
                const prev = $('#pagination-prev');
                const next = $('#pagination-next');

                if (prev) {
                    prev.addEventListener('click', () => {
                        if (state.page > 1 && !state.loading) {
                            state.page--;
                            renderPage();
                        }
                    });
                }

                if (next) {
                    next.addEventListener('click', () => {
                        const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.perPage));
                        if (state.page < totalPages && !state.loading) {
                            state.page++;
                            renderPage();
                        }
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                initPaginationEvents();
                loadData();
                window.addEventListener('resize', () => updatePinnedPositions());

                // Eventos del menú contextual en encabezados
                const thead = $('#mainTable thead');
                if (thead) {
                    thead.addEventListener('contextmenu', (e) => {
                        const th = e.target.closest('th');
                        if (!th) return;
                        e.preventDefault();
                        e.stopPropagation();
                        const columnIndex = parseInt(th.getAttribute('data-index'), 10);
                        const columnField = th.getAttribute('data-column');
                        if (Number.isNaN(columnIndex) || !columnField) return;
                        showContextMenu(e, columnIndex, columnField);
                    });
                }

                document.addEventListener('click', (e) => {
                    if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target)) hideContextMenu();
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') hideContextMenu();
                });

                // Clic en iconos del encabezado: quitar filtro o desfijar
                const mainTable = $('#mainTable');
                if (mainTable) {
                    mainTable.addEventListener('click', (e) => {
                        const btn = e.target.closest('.codificacion-header-icon');
                        if (!btn) return;
                        e.preventDefault();
                        e.stopPropagation();
                        const action = btn.getAttribute('data-action');
                        if (action === 'clear-filter') {
                            const field = btn.getAttribute('data-column');
                            if (field) {
                                state.filtrosPorColumna = (state.filtrosPorColumna || []).filter(f => f.column !== field);
                                aplicarFiltrosAND();
                                state.page = 1;
                                renderPage();
                                updateFilterCount();
                            }
                        } else if (action === 'unpin') {
                            const idx = parseInt(btn.getAttribute('data-index'), 10);
                            if (!Number.isNaN(idx)) {
                                const i = state.pinnedColumns.indexOf(idx);
                                if (i >= 0) {
                                    state.pinnedColumns.splice(i, 1);
                                    updatePinnedPositions();
                                    updateColumnHeaderIcons();
                                }
                            }
                        }
                    });
                    mainTable.addEventListener('contextmenu', (e) => {
                        if (e.target.closest('.codificacion-header-icon')) e.stopPropagation();
                    });
                }

                $('#codificacionCtxFiltrar')?.addEventListener('click', () => {
                    const idx = menuColumnIndex;
                    const field = menuColumnField;
                    hideContextMenu();
                    if (idx != null && field) openFilterModal(idx, field);
                });

                $('#codificacionCtxFijar')?.addEventListener('click', () => {
                    const idx = menuColumnIndex;
                    hideContextMenu();
                    if (idx == null) return;
                    const i = state.pinnedColumns.indexOf(idx);
                    if (i >= 0) {
                        state.pinnedColumns.splice(i, 1);
                    } else {
                        state.pinnedColumns.push(idx);
                        state.pinnedColumns.sort((a, b) => a - b);
                    }
                    updatePinnedPositions();
                    updateColumnHeaderIcons();
                });
            });

            // =========================
            //   ACTUALIZAR ESTADO BOTÓN REIMPRIMIR Y BALANCEAR
            // =========================
            function actualizarEstadoBotonReimprimir() {
                const btnReimprimir = document.getElementById('btn-reimprimir-seleccionado');
                if (!btnReimprimir) return;

                if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
                    btnReimprimir.disabled = true;
                    actualizarEstadoBotonBalancear();
                    return;
                }

                const registroSeleccionado = state.filtered[state.selectedRowIndex];
                if (!registroSeleccionado) {
                    btnReimprimir.disabled = true;
                    actualizarEstadoBotonBalancear();
                    return;
                }

                // Verificar que tenga UsuarioCrea (indica que el registro fue creado)
                const usuarioCrea = registroSeleccionado.UsuarioCrea;
                const puedeReimprimir = usuarioCrea !== null && usuarioCrea !== undefined && usuarioCrea !== '';

                btnReimprimir.disabled = !puedeReimprimir || !registroSeleccionado.Id;
                actualizarEstadoBotonBalancear();
            }

            function actualizarEstadoBotonBalancear() {
                const btnBalancear = document.getElementById('btn-balancear');
                if (!btnBalancear) return;

                if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
                    btnBalancear.disabled = true;
                    return;
                }

                const registroSeleccionado = state.filtered[state.selectedRowIndex];
                if (!registroSeleccionado) {
                    btnBalancear.disabled = true;
                    return;
                }

                const ordCompartida = registroSeleccionado.OrdCompartida;
                const tieneOrdCompartida = ordCompartida != null && ordCompartida !== '' && String(ordCompartida).trim() !== '';

                btnBalancear.disabled = !tieneOrdCompartida;
            }

            // =========================
            //   BALANCEAR - VER REGISTROS COMPARTIDOS
            // =========================
            async function abrirModalBalancear() {
                if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
                    showToast('Selecciona un registro primero', 'warning');
                    return;
                }

                const registroSeleccionado = state.filtered[state.selectedRowIndex];
                if (!registroSeleccionado) {
                    showToast('No se pudo obtener el registro seleccionado', 'error');
                    return;
                }

                const ordCompartida = registroSeleccionado.OrdCompartida;
                const tieneOrdCompartida = ordCompartida != null && ordCompartida !== '' && String(ordCompartida).trim() !== '';

                if (!tieneOrdCompartida) {
                    showToast('El registro seleccionado no tiene OrdCompartida', 'warning');
                    return;
                }

                try {
                    Swal.fire({
                        title: 'Cargando registros compartidos...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    const resp = await fetch(`/planeacion/codificacion/api/registros-ord-compartida/${encodeURIComponent(ordCompartida)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await resp.json();

                    Swal.close();

                    if (!data.success || !Array.isArray(data.registros)) {
                        showToast(data.message || 'Error al cargar registros compartidos', 'error');
                        return;
                    }

                    const registros = data.registros;
                    const cantidad = registros.length;
                    const tieneLideres = data.tieneLideres === true;

                    const escapeHtml = (t) => {
                        const d = document.createElement('div');
                        d.textContent = t;
                        return d.innerHTML;
                    };

                    let html = '<div class="text-left">';
                    html += '<p class="text-sm text-gray-600 mb-3">OrdCompartida: <strong>' + escapeHtml(String(ordCompartida)) + '</strong> · ' + cantidad + ' registro(s)</p>';
                    html += '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">';
                    html += '<table class="w-full text-sm">';
                    html += '<thead class="bg-blue-500 text-white sticky top-0"><tr>';
                    if (tieneLideres) {
                        html += '<th class="px-3 py-2 text-left">Líder</th>';
                    }
                    html += '<th class="px-3 py-2 text-left">Orden</th>';
                    html += '<th class="px-3 py-2 text-left">Telar</th>';
                    html += '<th class="px-3 py-2 text-left">Modelo</th>';
                    html += '<th class="px-3 py-2 text-left">Cantidad</th>';
                    html += '<th class="px-3 py-2 text-left">Producción</th>';
                    html += '<th class="px-3 py-2 text-left">Saldos</th>';
                    html += '<th class="px-3 py-2 text-left">Total Segundas</th>';
                    html += '</tr></thead><tbody>';

                    registros.forEach(r => {
                        const esLider = r.OrdCompartidaLider === 1 || r.OrdCompartidaLider === true || r.OrdCompartidaLider === '1';
                        const rowClass = esLider ? 'bg-amber-100 font-semibold' : 'bg-white hover:bg-gray-50';
                        html += '<tr class="' + rowClass + '">';
                        if (tieneLideres) {
                            html += '<td class="px-3 py-2">' + (esLider ? '<i class="fas fa-crown text-amber-600" title="OrdCompartidaLider"></i>' : '') + '</td>';
                        }
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.OrdenTejido ?? '')) + '</td>';
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.TelarId ?? '')) + '</td>';
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.Nombre ?? r.ClaveModelo ?? '')) + '</td>';
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.Cantidad ?? '')) + '</td>';
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.Produccion ?? '')) + '</td>';
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.Saldos ?? '')) + '</td>';
                        html += '<td class="px-3 py-2">' + escapeHtml(String(r.TotalSegundas ?? '')) + '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table></div></div>';

                    Swal.fire({
                        title: 'Registros compartidos',
                        html: html,
                        width: '880px',
                        showConfirmButton: true,
                        confirmButtonText: 'Cerrar',
                        confirmButtonColor: '#3b82f6'
                    });
                } catch (error) {
                    Swal.close();
                    showToast(error.message || 'Error al cargar registros compartidos', 'error');
                }
            }

            // =========================
            //   REIMPRIMIR ORDEN SELECCIONADA
            // =========================
            function reimprimirOrdenSeleccionada() {
                if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
                    showToast('Debes seleccionar un registro primero', 'warning');
                    return;
                }

                const registroSeleccionado = state.filtered[state.selectedRowIndex];
                if (!registroSeleccionado || !registroSeleccionado.Id) {
                    showToast('No se pudo obtener el ID del registro seleccionado', 'error');
                    return;
                }

                // Verificar que tenga UsuarioCrea (indica que el registro fue creado)
                const usuarioCrea = registroSeleccionado.UsuarioCrea;
                const puedeReimprimir = usuarioCrea !== null && usuarioCrea !== undefined && usuarioCrea !== '';

                if (!puedeReimprimir) {
                    showToast('Este registro no puede ser reimpreso porque no tiene un usuario de creación asignado', 'warning');
                    return;
                }

                reimprimirOrden(registroSeleccionado.Id);
            }

            // =========================
            //   REIMPRIMIR ORDEN POR ID
            // =========================
            async function reimprimirOrden(id) {
                if (!id) {
                    showToast('ID de orden no válido', 'error');
                    return;
                }

                try {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Generando orden de cambio...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Llamar a la ruta de reimpresión
                    const url = `/planeacion/programa-tejido/reimprimir-ordenes/${id}`;

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        }
                    });

                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.message || `Error ${response.status}: ${response.statusText}`);
                    }

                    // Obtener el blob del Excel
                    const blob = await response.blob();

                    // Crear URL temporal y descargar
                    const downloadUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = downloadUrl;
                    a.download = `REIMPRESION_ORDEN_${id}_${new Date().toISOString().slice(0,10)}.xlsx`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(downloadUrl);

                    Swal.close();
                    showToast('Orden reimpresa correctamente', 'success');
                } catch (error) {
                    Swal.close();
                    showToast(error.message || 'Error al reimprimir la orden', 'error');
                    console.error('Error al reimprimir orden:', error);
                }
            }

            // =========================
            //   EXPOSE GLOBAL
            // =========================
            window.mostrarAlertaNavbar         = mostrarAlertaNavbar;
            window.subirExcelCatCodificacion   = subirExcelCatCodificacion;
            window.procesarExcel               = procesarExcel;
            window.pollImportProgress          = pollImportProgress;
            window.filtrarCodificacion         = filtrarCodificacion;
            window.limpiarFiltrosCodificacion  = limpiarFiltrosCodificacion;
            window.removeFilterFromModal       = removeFilterFromModal;
            window.removeFilterPorColumna      = removeFilterPorColumna;
            window.loadData                    = loadData;
            window.reimprimirOrden              = reimprimirOrden;
            window.reimprimirOrdenSeleccionada  = reimprimirOrdenSeleccionada;
            window.abrirModalBalancear          = abrirModalBalancear;
        })();
    </script>
@endsection
