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
                        'JulioRizo' => 'No Julio Rizo',
                        'JulioPie' => 'No Julio Pie',
                        'EfiInicial' => 'Eficiencia de Inicio',
                        'EfiFinal' => 'Eficiencia Final',
                        'DesperdicioTrama' => 'Desperdicio Trama',
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
                        'FechaTejido',         // Fecha Orden
                        'FechaCumplimiento',    // Fecha Cumplimiento
                        'Departamento',
                        'TelarId',              // Telar Actual
                        'Prioridad',
                        'Nombre',               // Modelo
                        'ClaveModelo',          // CLAVE MODELO
                        'HiloAX',               // CLAVE AX
                        'InventSizeId',         // Tamaño
                        'Tolerancia',
                        'CodigoDibujo',
                        'FechaCompromiso',
                        'FlogsId',
                        'NombreProyecto',       // Nombre de Formato Logístico
                        'Clave',

                        // Medidas / especificación
                        'Cantidad',
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
                        'NoOrden',
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
                        'PesoMuestra',
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
                @endphp

                <table id="mainTable" class="w-full min-w-full text-[11px] leading-tight">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            @foreach($columnas as $columna)
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap border-b border-blue-600/70">
                                    <span class="block truncate">{{ $columnLabels[$columna] ?? $columna }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody id="catcodificacion-body" class="bg-white text-gray-800">
                        {{-- El contenido se llena por JS --}}
                    </tbody>
                </table>
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
    </style>

    {{-- Script principal --}}
    <script>
        (function () {
            // =========================
            //   CONFIG / ESTADO
            // =========================
            const CONFIG = {
                columnas: {!! json_encode($columnas ?? []) !!},
                apiUrl: {!! json_encode($apiUrl ?? '/planeacion/codificacion/api/all-fast') !!},
                totalRegistros: {{ isset($totalRegistros) ? (int) $totalRegistros : 0 }},
            };

            const state = {
                data: [],
                filtered: [],
                filtros: [],       // { columna: index, valor: string }
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

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Peso Muestra</label>
                                <input
                                    type="text"
                                    id="swal-peso-muestra"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="lo obtiene de catcodificacos y se puede editar"
                                    value="${pesoMuestra}"
                                >
                            </div>
                            <div>
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
                            <div id="swal-lista-mat-container" class="${actLmat ? '' : 'hidden'}">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lista Mat (BomId)</label>
                                <input
                                    type="text"
                                    id="swal-lista-mat"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="lo obtiene de catcodificacos y se puede editar"
                                    value="${bomId}"
                                >
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
                        // Mostrar/ocultar campo Lista Mat según checkbox Act Lmat
                        const actLmatCheckbox = document.getElementById('swal-act-lmat');
                        const listaMatContainer = document.getElementById('swal-lista-mat-container');

                        if (actLmatCheckbox && listaMatContainer) {
                            actLmatCheckbox.addEventListener('change', function() {
                                if (this.checked) {
                                    listaMatContainer.classList.remove('hidden');
                                } else {
                                    listaMatContainer.classList.add('hidden');
                                }
                            });
                        }

                        // GET órdenes en proceso (ReqProgramaTejido) y rellenar select
                        const selectOrden = document.getElementById('swal-orden-tejido');
                        const telarInput = document.getElementById('swal-telar');
                        const articuloInput = document.getElementById('swal-articulo');

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
                                            opt.textContent = (item.noProduccion || '') + (item.noTelarId ? ' — Telar ' + item.noTelarId : '') + (item.nombreProducto ? ' — ' + item.nombreProducto : '');
                                            selectOrden.appendChild(opt);
                                        });
                                        if (ordenTejido) selectOrden.value = ordenTejido;
                                        // Al elegir una orden, rellenar Telar y Articulo
                                        selectOrden.addEventListener('change', function() {
                                            const opt = this.options[this.selectedIndex];
                                            if (opt && opt.value) {
                                                if (telarInput) telarInput.value = opt.dataset.noTelarId || '';
                                                if (articuloInput) articuloInput.value = opt.dataset.itemId || opt.dataset.nombreProducto || '';
                                            } else {
                                                if (telarInput) telarInput.value = '';
                                                if (articuloInput) articuloInput.value = '';
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
                        const pesoMuestra = document.getElementById('swal-peso-muestra')?.value.trim() || '';
                        const actLmat = document.getElementById('swal-act-lmat')?.checked || false;
                        const bomId = document.getElementById('swal-lista-mat')?.value.trim() || '';

                        // Validaciones básicas
                        if (!ordenTejido) {
                            Swal.showValidationMessage('Seleccione una orden en proceso');
                            return false;
                        }

                        return {
                            ordenTejido,
                            pesoMuestra,
                            actLmat,
                            bomId
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        const datos = result.value;
                        // Aquí puedes hacer la petición al servidor para guardar
                        console.log('Datos a guardar:', datos);
                        internalToast('Datos guardados correctamente', 'success');
                        // TODO: Implementar guardado real con fetch al servidor
                    }
                });
            }

            // =========================
            //   CARGA DE DATOS
            // =========================
            async function loadData() {
                if (!CONFIG.apiUrl || state.loading) return;

                setLoading(true, 'Cargando datos...', '');

                try {
                    const resp = await fetch(CONFIG.apiUrl, {
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

                    const columnas = json.c || CONFIG.columnas;
                    const raw      = json.d || [];
                    const totalRows = raw.length;

                    setLoading(true, 'Procesando registros...', totalRows.toLocaleString() + ' registros');

                    state.data = raw.map(rowArr => {
                        const obj = {};
                        for (let j = 0; j < columnas.length; j++) {
                            obj[columnas[j]] = rowArr[j] ?? null;
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

                    if (state.filtros.length) {
                        aplicarFiltrosAND();
                    }

                    renderPage();
                    updateFilterCount();
                    actualizarEstadoBotonReimprimir();

                    setLoading(false);
                } catch (error) {
                    const tbody = $('#catcodificacion-body');
                    if (tbody) {
                        tbody.innerHTML =
                            '<tr>' +
                                '<td colspan="' + CONFIG.columnas.length + '" class="py-16 text-center">' +
                                    '<div class="flex flex-col items-center gap-2">' +
                                        '<i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>' +
                                        '<p class="text-red-600 font-medium">Error al cargar datos</p>' +
                                        '<p class="text-sm text-gray-500">' + error.message + '</p>' +
                                        '<button type="button" class="mt-2 px-3 py-1.5 text-sm rounded bg-blue-500 text-white hover:bg-blue-600" onclick="loadData()">' +
                                            'Reintentar' +
                                        '</button>' +
                                    '</div>' +
                                '</td>' +
                            '</tr>';
                    }
                    showToast('Error al cargar datos', 'error');
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

                    // Aplicar estilos según selección
                    if (isSelected) {
                        tr.classList.add('bg-blue-500', 'text-white');
                    } else {
                        tr.classList.add('hover:bg-gray-50');
                    }

                    // Evento click para seleccionar/deseleccionar
                    tr.addEventListener('click', () => {
                        // Si ya está seleccionada, deseleccionar
                        if (state.selectedRowIndex === globalIndex) {
                            state.selectedRowIndex = null;
                            tr.classList.remove('bg-blue-500', 'text-white');
                            tr.classList.add('hover:bg-gray-50');
                            tr.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-white');
                                td.classList.add('text-gray-700');
                            });
                            actualizarEstadoBotonReimprimir();
                        } else {
                            // Deseleccionar fila anterior si existe
                            const prevSelected = tbody.querySelector('tr.bg-blue-500');
                            if (prevSelected) {
                                prevSelected.classList.remove('bg-blue-500', 'text-white');
                                prevSelected.classList.add('hover:bg-gray-50');
                                prevSelected.querySelectorAll('td').forEach(td => {
                                    td.classList.remove('text-white');
                                    td.classList.add('text-gray-700');
                                });
                            }

                            // Seleccionar nueva fila
                            state.selectedRowIndex = globalIndex;
                            tr.classList.add('bg-blue-500', 'text-white');
                            tr.classList.remove('hover:bg-gray-50');
                            tr.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-gray-700');
                                td.classList.add('text-white');
                            });
                            actualizarEstadoBotonReimprimir();
                        }
                    });

                    for (const col of CONFIG.columnas) {
                        const td = document.createElement('td');
                        td.className = 'px-3 py-1.5 border-b border-gray-100 whitespace-nowrap text-[11px] ' +
                            (isSelected ? 'text-white' : 'text-gray-700');
                        const value = row[col] ?? '';
                        td.textContent = value !== null ? String(value) : '';
                        tr.appendChild(td);
                    }

                    fragment.appendChild(tr);
                });

                tbody.innerHTML = '';
                tbody.appendChild(fragment);
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
            //   FILTROS
            // =========================
            function aplicarFiltrosAND() {
                if (!state.data.length) {
                    state.filtered = [];
                    return;
                }

                if (!state.filtros.length) {
                    state.filtered = [...state.data];
                } else {
                    state.filtered = state.data.filter(row => {
                        return state.filtros.every(f => {
                            const colName = CONFIG.columnas[f.columna];
                            if (!colName) return true;

                            const cell   = String(row[colName] ?? '').toLowerCase();
                            const needle = f.valor.toLowerCase().trim();
                            return cell.includes(needle);
                        });
                    });
                }

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

                const count = state.filtros.length;
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
                        if (state.filtros.length > 0) {
                            activeFilters?.classList.remove('hidden');
                            clearContainer?.classList.remove('hidden');
                        }

                        const closeBtn = document.getElementById('btn-close-modal');
                        closeBtn?.addEventListener('click', () => Swal.close());

                        document.getElementById('btn-add-filter')?.addEventListener('click', addFilterFromModal);
                        document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
                            state.filtros = [];
                            aplicarFiltrosAND();
                            renderModalFilters();
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

                if (!state.filtros.length) {
                    container.classList.add('hidden');
                    clearBox?.classList.add('hidden');
                    return;
                }

                container.classList.remove('hidden');
                clearBox?.classList.remove('hidden');

                list.innerHTML = state.filtros.map((filtro, index) => {
                    const colName = CONFIG.columnas[filtro.columna] || 'Columna';
                    return '' +
                        '<span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-[10px]">' +
                            '<span class="font-semibold">' + colName + ':</span>' +
                            '<span>"' + filtro.valor + '"</span>' +
                            '<button type="button" class="ml-1 hover:text-red-600 font-bold" onclick="removeFilterFromModal(' + index + ')">' +
                                '&times;' +
                            '</button>' +
                        '</span>';
                }).join('');
            }

            function removeFilterFromModal(index) {
                state.filtros.splice(index, 1);
                aplicarFiltrosAND();
                renderModalFilters();
                showToast('Filtro eliminado', 'info');
            }

            function limpiarFiltrosCodificacion() {
                state.filtros = [];
                aplicarFiltrosAND();
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
            });

            // =========================
            //   ACTUALIZAR ESTADO BOTÓN REIMPRIMIR
            // =========================
            function actualizarEstadoBotonReimprimir() {
                const btnReimprimir = document.getElementById('btn-reimprimir-seleccionado');
                if (!btnReimprimir) return;

                if (state.selectedRowIndex === null || state.selectedRowIndex === undefined) {
                    btnReimprimir.disabled = true;
                    return;
                }

                const registroSeleccionado = state.filtered[state.selectedRowIndex];
                if (!registroSeleccionado) {
                    btnReimprimir.disabled = true;
                    return;
                }

                // Verificar que tenga UsuarioCrea (indica que el registro fue creado)
                const usuarioCrea = registroSeleccionado.UsuarioCrea;
                const puedeReimprimir = usuarioCrea !== null && usuarioCrea !== undefined && usuarioCrea !== '';

                btnReimprimir.disabled = !puedeReimprimir || !registroSeleccionado.Id;
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
            window.loadData                    = loadData;
            window.reimprimirOrden              = reimprimirOrden;
            window.reimprimirOrdenSeleccionada  = reimprimirOrdenSeleccionada;
        })();
    </script>
@endsection
