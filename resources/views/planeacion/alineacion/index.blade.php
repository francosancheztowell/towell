@extends('layouts.app')

@section('page-title', 'Alineación')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button type="button" id="alineacionNavFijar" class="bg-blue-500 rounded-full flex items-center gap-2 px-3 py-2 text-sm font-medium shadow-sm" title="Ver columnas fijadas">
            <i class="fas fa-thumbtack text-white"></i>

        </button>
    </div>
@endsection

@section('content')
    @php
        // Estructura fija de columnas en la vista para que no cambie según el controlador
        if (!isset($columnas)) {
            $columnas = [
                'NoTelarId', 'NoProduccion', 'FechaCambio', 'FechaCompromiso', 'ItemId', 'NombreProducto',
                'Tolerancia', 'RazSN', 'TipoRizo', 'CalibreRizo',
                'Ancho', 'LargoCrudo', 'PesoCrudo', 'Luchaje', 'TipoPlano', 'MedidaPlano',                 'NoTiras',
                'CuentaRizo', 'CuentaPie', 'CalibreTrama',
                'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4',
                'AnchoToalla', 'PesoGRM2', 'PesoMin', 'PesoMax', 'MuestraMin', 'MuestraMax',
                'TotalPedido', 'ProdAcumMesAnt', 'ProdAcumMes', 'Produccion', 'SaldoPedido',
                'DiasEficiencia', 'ProdKgDia', 'DiasPorEjecutar', 'Observaciones',
            ];
        }
        if (!isset($columnLabels)) {
            $columnLabels = [
                'NoTelarId' => 'Telar', 'NoProduccion' => 'No. Orden', 'FechaCambio' => 'Fecha de cambio',
                'FechaCompromiso' => 'Fecha comprom.', 'ItemId' => 'Clave AX', 'NombreProducto' => 'Modelo',
                'Tolerancia' => 'Tolerancia', 'RazSN' => 'Raz. S/N', 'TipoRizo' => 'Tipo Rizo', 'CalibreRizo' => 'Alt Rizo',
                'Ancho' => 'Crudo Anc.', 'LargoCrudo' => 'Crudo Lar.', 'PesoCrudo' => 'Crudo Peso', 'Luchaje' => 'Luc.', 'TipoPlano' => 'Tipo Plano',
                'MedidaPlano' => 'Med. plano',                 'NoTiras' => 'Tiras',
                'CuentaRizo' => 'Hilo Rizo', 'CuentaPie' => 'Hilo Pie', 'CalibreTrama' => 'Hilo Trama',
                'PasadasComb1' => '1', 'PasadasComb2' => '2', 'PasadasComb3' => '3', 'PasadasComb4' => '4',
                'AnchoToalla' => 'Med. Cen.', 'PesoGRM2' => 'Peso Muestra',
                'PesoMin' => 'Peso Min', 'PesoMax' => 'Peso Max',
                'MuestraMin' => 'Muestra Min', 'MuestraMax' => 'Muestra Max',
                'TotalPedido' => 'Cantidad Solicitada', 'ProdAcumMesAnt' => 'Prod. Acum. Mes Ant.',
                'ProdAcumMes' => 'Prod. Acum. Mes', 'Produccion' => 'Prod. Acum.', 'SaldoPedido' => 'Diferencia',
                'DiasEficiencia' => 'Días de prod.',
                'ProdKgDia' => 'Prod. Prom. X Día', 'DiasPorEjecutar' => 'Días por Ejecutar',
                'Observaciones' => 'Observaciones',
            ];
        }
        if (!isset($subColumnLabels)) {
            $subColumnLabels = [
                'NoTelarId' => '', 'NoProduccion' => '', 'FechaCambio' => '', 'FechaCompromiso' => '',
                'ItemId' => '', 'NombreProducto' => '', 'Tolerancia' => '', 'RazSN' => '', 'TipoRizo' => '',
                'CalibreRizo' => '', 'Ancho' => 'Anc.', 'LargoCrudo' => 'Lar.', 'PesoCrudo' => 'Peso', 'Luchaje' => '',
                'TipoPlano' => '', 'MedidaPlano' => '',                 'NoTiras' => '',
                'CuentaRizo' => 'Rizo', 'CuentaPie' => 'Pie', 'CalibreTrama' => 'Trama',
                'PasadasComb1' => '1', 'PasadasComb2' => '2', 'PasadasComb3' => '3', 'PasadasComb4' => '4',
                'AnchoToalla' => '', 'PesoGRM2' => '',
                'PesoMin' => 'Min', 'PesoMax' => 'Max',
                'MuestraMin' => 'Min', 'MuestraMax' => 'Max',
                'TotalPedido' => '', 'ProdAcumMesAnt' => '', 'ProdAcumMes' => '',
                'Produccion' => '', 'SaldoPedido' => '', 'DiasEficiencia' => '',
                'ProdKgDia' => '', 'DiasPorEjecutar' => '', 'Observaciones' => '',
            ];
        }
        if (!isset($headerGroups)) {
            $headerGroups = [
                'Crudo' => ['Ancho', 'LargoCrudo', 'PesoCrudo'],
                'Hilo' => ['CuentaRizo', 'CuentaPie', 'CalibreTrama'],
                'Cenefa Trama' => ['PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4'],
                'Peso' => ['PesoMin', 'PesoMax'],
                'Muestra' => ['MuestraMin', 'MuestraMax'],
            ];
        }
        $items = $items ?? [];
    @endphp
    <div class="container-fluid">
        <div class="relative bg-white rounded-lg shadow-sm flex flex-col" style="height: calc(100vh);">

            <div
                id="table-container"
                class="relative flex-1 overflow-y-auto overflow-x-auto"
                style="max-height: calc(100vh - 70px);"
            >
                @php
                        $headerGroups = $headerGroups ?? [];
                        $groupFirstCols = [];
                        $colInGroup = [];
                        foreach ($headerGroups as $parent => $cols) {
                            $groupFirstCols[$cols[0]] = ['parent' => $parent, 'colspan' => count($cols)];
                            foreach ($cols as $c) {
                                $colInGroup[$c] = true;
                            }
                        }
                    @endphp
                <table id="mainTable" class="w-full min-w-full text-sm leading-tight">
                    <thead class="alineacion-thead bg-blue-600 text-white sticky top-0 z-10 alineacion-header-context">
                        {{-- Fila 1: columnas con un solo encabezado usan rowspan="2"; los grupos usan colspan --}}
                        <tr>
                            @foreach($columnas as $idx => $columna)
                                @if(isset($groupFirstCols[$columna]))
                                    <th colspan="{{ $groupFirstCols[$columna]['colspan'] }}" class="px-3 py-2 text-center font-semibold whitespace-nowrap border-b border-blue-700/60 bg-blue-600 column-{{ $idx }}" data-column="{{ $columna }}" data-index="{{ $idx }}">
                                        <span class="inline-flex items-center justify-center gap-1"><span class="truncate">{{ $groupFirstCols[$columna]['parent'] }}</span><span class="alineacion-header-icons ml-1 inline-flex items-center gap-0.5"></span></span>
                                    </th>
                                @elseif(!empty($colInGroup[$columna]))
                                    @continue
                                @else
                                    <th rowspan="2" class="px-3 py-2 text-center font-semibold whitespace-nowrap border-b border-blue-700/60 bg-blue-600 align-middle column-{{ $idx }}" data-column="{{ $columna }}" data-index="{{ $idx }}">
                                        <span class="inline-flex items-center justify-center gap-1"><span class="truncate">{{ $columnLabels[$columna] ?? $columna }}</span><span class="alineacion-header-icons ml-1 inline-flex items-center gap-0.5"></span></span>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                        {{-- Fila 2: solo subencabezados de los grupos --}}
                        <tr>
                            @foreach($columnas as $idx => $columna)
                                @if(!empty($colInGroup[$columna]))
                                    <th class="px-3 py-1.5 text-center font-medium whitespace-nowrap border-b border-blue-700/60 bg-blue-600 text-blue-100 column-{{ $idx }}" data-column="{{ $columna }}" data-index="{{ $idx }}">
                                        <span class="inline-flex items-center justify-center gap-1"><span class="truncate">{{ $subColumnLabels[$columna] ?? '' }}</span><span class="alineacion-header-icons ml-1 inline-flex items-center gap-0.5"></span></span>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="alineacion-body" class="bg-white text-gray-800">
                        {{-- Se llena por JS con datos iniciales (luego por GET) --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Menú contextual para encabezados de columnas (Filtrar, Fijar, Ocultar) --}}
    <div id="alineacionContextMenuHeader" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-[9999] py-1 min-w-[180px]">
        <button type="button" id="alineacionCtxFiltrar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
            <i class="fas fa-filter text-yellow-500"></i>
            <span>Filtrar</span>
        </button>
        <button type="button" id="alineacionCtxFijar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 flex items-center gap-2">
            <i class="fas fa-thumbtack text-blue-500"></i>
            <span id="alineacionCtxFijarLabel">Fijar</span>
        </button>
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
        #table-container::-webkit-scrollbar { width: 14px; height: 14px; }
        #table-container::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 7px; }
        #table-container::-webkit-scrollbar-thumb { background: #6b7280; border-radius: 7px; border: 2px solid #e5e7eb; }
        #table-container::-webkit-scrollbar-thumb:hover { background: #4b5563; }
        #table-container { scrollbar-width: auto; scrollbar-color: #6b7280 #e5e7eb; }

        #mainTable {
            position: relative;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: max-content;
            table-layout: auto;
        }
        #mainTable thead.alineacion-thead,
        #mainTable thead.alineacion-thead th {
            border-right: none !important;
            border-left: none !important;
        }
        /* Encabezados fijos: todo el thead como un solo bloque al hacer scroll vertical */
        #mainTable thead {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1000 !important;
            background-color: #2563eb !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            border-bottom: 2px solid #1d4ed8 !important;
        }
        #mainTable thead th {
            position: relative;
            background-color: #2563eb !important;
            white-space: nowrap;
        }
        #mainTable tbody td {
            border-right: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        #mainTable tbody td:last-child { border-right: none; }
        .container-fluid { position: relative; height: 100%; display: flex; flex-direction: column; }
        .bg-white.rounded-lg.shadow-sm { display: flex; flex-direction: column; height: 100%; min-height: 0; }

        /* Menú contextual en encabezados */
        .alineacion-header-context th { cursor: context-menu; }

        /* Iconos en encabezados (filtro activo / columna fijada) */
        .alineacion-header-icons { flex-shrink: 0; display: inline-flex; align-items: center; gap: 2px; }
        .alineacion-header-icon {
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
        .alineacion-header-icon:hover { opacity: 0.9; background: rgba(255,255,255,0.25); }
        .alineacion-header-icon .fa-filter { color: #fcd34d !important; }
        .alineacion-header-icon .fa-thumbtack { color: #fff !important; }

        /* Columnas fijadas (pin) */
        #mainTable thead th.alineacion-pinned,
        #mainTable tbody td.alineacion-pinned {
            position: sticky !important;
            z-index: 5;
            background-color: #1d4ed8 !important;
            color: #fff !important;
        }
        #mainTable tbody td.alineacion-pinned { z-index: 1; }
        #mainTable thead th.alineacion-pinned { z-index: 11 !important; }

        /* Filas seleccionadas (bg-blue-500 text-white) */
        #mainTable tbody tr.alineacion-row-selected,
        #mainTable tbody tr.alineacion-row-selected td { background-color: #3b82f6 !important; color: #fff !important; }
        #mainTable tbody tr.alineacion-row-selected td.alineacion-pinned { background-color: #2563eb !important; color: #fff !important; }
        #mainTable tbody tr.alineacion-row-selected:hover,
        #mainTable tbody tr.alineacion-row-selected:hover td { background-color: #2563eb !important; }
    </style>

    <script>
        (function () {
            const CONFIG = {
                columnas: {!! json_encode($columnas) !!},
                columnLabels: @json($columnLabels ?? []),
                apiUrl: {!! json_encode(route('planeacion.alineacion.api.data')) !!},
            };
            const state = {
                data: @json($items),
                filtered: [],
                pinnedColumns: [],
                filters: [],
                selectedRowIndex: null,
            };
            state.filtered = [...state.data];

            const $ = (sel, ctx = document) => ctx.querySelector(sel);
            const $$ = (sel, ctx = document) => Array.from((ctx || document).querySelectorAll(sel));

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function applyFiltersToData() {
                if (!state.filters || state.filters.length === 0) {
                    state.filtered = [...state.data];
                    return;
                }
                const byColumn = {};
                state.filters.forEach(f => {
                    if (!byColumn[f.column]) byColumn[f.column] = [];
                    byColumn[f.column].push(String(f.value || '').toLowerCase().trim());
                });
                state.filtered = state.data.filter(row => {
                    return Object.entries(byColumn).every(([col, values]) => {
                        const cellVal = row[col];
                        const str = (cellVal != null ? String(cellVal) : '').toLowerCase().trim();
                        return values.includes(str);
                    });
                });
            }

            function getColumnElements(index) {
                return $$('#mainTable .column-' + index);
            }

            function updatePinnedPositions() {
                const table = $('#mainTable');
                if (!table) return;
                let left = 0;
                state.pinnedColumns.forEach(idx => {
                    const els = getColumnElements(idx);
                    const th = els.find(el => el.tagName === 'TH');
                    if (!th) return;
                    const w = th.offsetWidth || 80;
                    els.forEach(el => {
                        el.classList.add('alineacion-pinned');
                        el.style.left = left + 'px';
                        el.style.position = 'sticky';
                        if (el.tagName === 'TH') el.style.top = '0';
                    });
                    left += w;
                });
                $$('#mainTable th[data-index], #mainTable td[data-index]').forEach(el => {
                    const dataIndex = el.getAttribute('data-index');
                    const idx = dataIndex !== null && dataIndex !== '' ? parseInt(dataIndex, 10) : NaN;
                    if (Number.isNaN(idx) || !state.pinnedColumns.includes(idx)) {
                        el.classList.remove('alineacion-pinned');
                        el.style.left = '';
                        el.style.position = '';
                        el.style.top = '';
                    }
                });
            }

            function updateColumnHeaderIcons() {
                CONFIG.columnas.forEach((col, idx) => {
                    const thList = getColumnElements(idx).filter(el => el.tagName === 'TH');
                    const th = thList[0];
                    if (!th) return;
                    const field = col;
                    const container = th.querySelector('.alineacion-header-icons');
                    if (!container) return;
                    let html = '';
                    const hasFilter = (state.filters || []).some(f => f.column === field);
                    if (hasFilter) {
                        html += '<button type="button" class="alineacion-header-icon" data-action="clear-filter" data-column="' + escapeHtml(field) + '" title="Quitar filtro"><i class="fas fa-filter"></i></button>';
                    }
                    if (state.pinnedColumns.includes(idx)) {
                        html += '<button type="button" class="alineacion-header-icon" data-action="unpin" data-index="' + idx + '" title="Desfijar"><i class="fas fa-thumbtack"></i></button>';
                    }
                    container.innerHTML = html;
                });
            }

            function renderTable() {
                applyFiltersToData();
                const tbody = $('#alineacion-body');
                if (!tbody) return;

                const data = state.filtered;
                const totalCols = CONFIG.columnas.length;

                if (!data.length) {
                    tbody.innerHTML =
                        '<tr><td colspan="' + totalCols + '" class="py-16 text-center text-gray-500">No hay datos para mostrar</td></tr>';
                    return;
                }

                tbody.innerHTML = data.map((row, index) => {
                    const selected = state.selectedRowIndex === index;
                    const isEven = index % 2 === 0;
                    const baseClass = selected ? 'alineacion-row-selected bg-blue-500 text-white hover:bg-blue-600' : (isEven ? 'bg-white hover:bg-gray-100' : 'bg-gray-50 hover:bg-gray-200');
                    const rowClass = 'alineacion-selectable-row cursor-pointer transition-colors ' + baseClass;
                    const cellClass = selected ? 'px-3 py-1.5 border-b border-r border-blue-400 whitespace-nowrap text-sm text-white column-' : 'px-3 py-1.5 border-b border-r border-gray-200 whitespace-nowrap text-sm text-gray-700 column-';
                    const cells = CONFIG.columnas.map((col, colIdx) => {
                        let value = row[col] ?? '';
                        let raw = value !== null && value !== '' ? String(value) : '';
                        if ((col === 'AnchoToalla' || col === 'PesoGRM2') && value !== '' && value != null && !isNaN(parseFloat(value))) {
                            raw = parseFloat(value).toFixed(3);
                        }
                        // Días de prod. = fecha actual - FechaTejido (catcodificados, ej. 2020-02-01), calculado en cliente
                        if (col === 'DiasEficiencia') {
                            const fechaStr = (row['FechaTejido'] != null ? String(row['FechaTejido']).trim() : '') || (row['FechaCambio'] != null ? String(row['FechaCambio']).trim() : '');
                            let fechaBase = null;
                            // YYYY-MM-DD o YYYY-MM-DD HH:MM:SS (formato catcodificados)
                            const iso = fechaStr.match(/^(\d{4})-(\d{2})-(\d{2})(?:[\sT](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/);
                            if (iso) {
                                const y = parseInt(iso[1], 10), m = parseInt(iso[2], 10) - 1, d = parseInt(iso[3], 10);
                                const h = iso[4] != null ? parseInt(iso[4], 10) : 0, min = iso[5] != null ? parseInt(iso[5], 10) : 0, seg = iso[6] != null ? parseInt(iso[6], 10) : 0;
                                if (y > 1900 && y < 2100 && m >= 0 && m <= 11 && d >= 1 && d <= 31)
                                    fechaBase = new Date(y, m, d, h, min, seg, 0);
                            }
                            // DD/MM/YYYY o DD-MM-YYYY (día primero)
                            if (!fechaBase && /^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/.test(fechaStr)) {
                                const parts = fechaStr.split(/[\/\-]/);
                                const d = parseInt(parts[0], 10), m = parseInt(parts[1], 10) - 1;
                                let y = parseInt(parts[2], 10);
                                if (y < 100) y += y < 50 ? 2000 : 1900;
                                if (d >= 1 && d <= 31 && m >= 0 && m <= 11 && y > 1900 && y < 2100)
                                    fechaBase = new Date(y, m, d, 0, 0, 0, 0);
                            }
                            if (fechaBase && !isNaN(fechaBase.getTime())) {
                                const hoy = new Date();
                                const msPorDia = 1000 * 60 * 60 * 24;
                                const diffDias = (hoy.getTime() - fechaBase.getTime()) / msPorDia;
                                if (Number.isFinite(diffDias)) raw = diffDias >= 0 ? Number(diffDias).toFixed(1) : '0';
                            }
                        }
                        return '<td class="' + cellClass + colIdx + '" data-column="' + escapeHtml(col) + '" data-index="' + colIdx + '" data-value="' + escapeHtml(raw) + '">' +
                            (raw ? escapeHtml(raw) : '') + '</td>';
                    }).join('');
                    return '<tr class="' + rowClass + '" data-row-index="' + index + '">' + cells + '</tr>';
                }).join('');

                updatePinnedPositions();
                updateColumnHeaderIcons();
            }

            function setSelectedRow(rowIndex) {
                if (state.selectedRowIndex === rowIndex) {
                    state.selectedRowIndex = null;
                } else {
                    state.selectedRowIndex = rowIndex;
                }
                renderTable();
            }

            /**
             * Refresca datos desde API (simula sockets). Se ejecuta cada 5 min.
             */
            async function refreshData() {
                try {
                    const resp = await fetch(CONFIG.apiUrl, { headers: { 'Accept': 'application/json' } });
                    const json = await resp.json();
                    if (json.s && Array.isArray(json.items)) {
                        state.data = json.items;
                        state.selectedRowIndex = null;
                        applyFiltersToData();
                        renderTable();
                    }
                } catch (e) {
                    console.warn('Alineación: error al refrescar datos', e);
                }
            }

            // ----- Menú contextual en encabezados -----
            const menu = $('#alineacionContextMenuHeader');
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
                const fijarLabel = $('#alineacionCtxFijarLabel');
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
                const currentForColumn = (state.filters || []).filter(f => f.column === columnField).map(f => f.value);

                let html = '<div class="text-left"><p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>' + escapeHtml(columnLabel) + '</strong></p>';
                html += '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">';
                html += '<div class="mb-2 pb-2 border-b border-gray-200"><input type="text" id="alineacionFilterSearch" placeholder="Buscar..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></div>';
                html += '<div id="alineacionFilterCheckboxes" class="space-y-1">';
                uniqueValues.forEach(value => {
                    const entry = valueCounts.get(value);
                    const count = entry ? entry.count : 0;
                    const checked = currentForColumn.includes(value) ? ' checked' : '';
                    html += '<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"><div class="flex items-center gap-2">';
                    html += '<input type="checkbox" class="alineacion-filter-cb w-4 h-4 text-blue-600" value="' + escapeHtml(value) + '"' + checked + '>';
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
                        const search = document.getElementById('alineacionFilterSearch');
                        const container = document.getElementById('alineacionFilterCheckboxes');
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
                        const checked = $$('.alineacion-filter-cb:checked').map(cb => cb.value);
                        return checked;
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;
                    state.filters = (state.filters || []).filter(f => f.column !== columnField);
                    (result.value || []).forEach(v => {
                        state.filters.push({ column: columnField, value: v });
                    });
                    renderTable();
                });
            }

            function getColumnLabel(idx) {
                const field = CONFIG.columnas[idx];
                return (CONFIG.columnLabels && CONFIG.columnLabels[field]) || field || ('Columna ' + idx);
            }

            function openPanelFijar() {
                const pinnedSet = new Set(state.pinnedColumns || []);
                let html = '<div class="text-left"><p class="text-sm text-gray-600 mb-3">Marca las columnas que quieres <strong>fijar</strong> (quedan a la izquierda al hacer scroll):</p>';
                html += '<div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">';
                CONFIG.columnas.forEach((_, idx) => {
                    const label = escapeHtml(getColumnLabel(idx));
                    const checked = pinnedSet.has(idx) ? ' checked' : '';
                    html += '<label class="flex items-center gap-2 py-1.5 px-2 hover:bg-gray-50 rounded cursor-pointer alineacion-fijar-row">';
                    html += '<input type="checkbox" class="alineacion-fijar-cb w-4 h-4 text-amber-600 rounded border-gray-300" data-index="' + idx + '"' + checked + '>';
                    html += '<span class="text-sm text-gray-800">' + label + '</span></label>';
                });
                html += '</div></div>';
                Swal.fire({
                    title: 'Fijar columnas',
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    width: '380px',
                    preConfirm: () => {
                        const checked = $$('.alineacion-fijar-cb:checked').map(cb => parseInt(cb.getAttribute('data-index'), 10)).filter(i => !Number.isNaN(i));
                        return checked;
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;
                    state.pinnedColumns = (result.value || []).slice().sort((a, b) => a - b);
                    updatePinnedPositions();
                    updateColumnHeaderIcons();
                });
            }

            document.addEventListener('DOMContentLoaded', () => {
                renderTable();

                $('#alineacionNavFijar')?.addEventListener('click', openPanelFijar);

                const tbody = $('#alineacion-body');
                if (tbody) {
                    tbody.addEventListener('click', (e) => {
                        const tr = e.target.closest('tr.alineacion-selectable-row');
                        if (!tr) return;
                        const idx = parseInt(tr.getAttribute('data-row-index'), 10);
                        if (!Number.isNaN(idx)) setSelectedRow(idx);
                    });
                }

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
                        const btn = e.target.closest('.alineacion-header-icon');
                        if (!btn) return;
                        e.preventDefault();
                        e.stopPropagation();
                        const action = btn.getAttribute('data-action');
                        if (action === 'clear-filter') {
                            const field = btn.getAttribute('data-column');
                            if (field) {
                                state.filters = (state.filters || []).filter(f => f.column !== field);
                                renderTable();
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
                        if (e.target.closest('.alineacion-header-icon')) e.stopPropagation();
                    });
                }

                $('#alineacionCtxFiltrar')?.addEventListener('click', () => {
                    const idx = menuColumnIndex;
                    const field = menuColumnField;
                    hideContextMenu();
                    if (idx != null && field) openFilterModal(idx, field);
                });

                $('#alineacionCtxFijar')?.addEventListener('click', () => {
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

                // Refresco automático cada 5 min (simula sockets)
                setInterval(refreshData, 5 * 60 * 1000);
            });
        })();
    </script>
@endsection
