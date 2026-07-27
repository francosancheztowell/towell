@extends('layouts.app')

@section('page-title', 'Alineación')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <a href="{{ route('planeacion.alineacion.export.excel') }}" class="bg-green-600 rounded-full flex items-center gap-2 px-3 py-2 text-sm font-medium shadow-sm" title="Descargar Excel">
            <i class="fas fa-file-excel text-white"></i>
        </a>
        <a href="{{ route('planeacion.alineacion.export.pdf') }}" class="bg-red-600 rounded-full flex items-center gap-2 px-3 py-2 text-sm font-medium shadow-sm" title="Descargar PDF">
            <i class="fas fa-file-pdf text-white"></i>
        </a>
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
                'FibraRizo', 'FibraPie', 'CalibreTrama',
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
                'FibraRizo' => 'Hilo Rizo', 'FibraPie' => 'Hilo Pie', 'CalibreTrama' => 'Hilo Trama',
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
                'FibraRizo' => 'Rizo', 'FibraPie' => 'Pie', 'CalibreTrama' => 'Trama',
                'PasadasComb1' => '1', 'PasadasComb2' => '2', 'PasadasComb3' => '3', 'PasadasComb4' => '4',
                'AnchoToalla' => '', 'PesoGRM2' => '',
                'PesoMin' => 'Mín.', 'PesoMax' => 'Máx.',
                'MuestraMin' => 'Mín.', 'MuestraMax' => 'Máx.',
                'TotalPedido' => '', 'ProdAcumMesAnt' => '', 'ProdAcumMes' => '',
                'Produccion' => '', 'SaldoPedido' => '', 'DiasEficiencia' => '',
                'ProdKgDia' => '', 'DiasPorEjecutar' => '', 'Observaciones' => '',
            ];
        }
        if (!isset($headerGroups)) {
            $headerGroups = [
                'Crudo' => ['Ancho', 'LargoCrudo', 'PesoCrudo'],
                'Hilo' => ['FibraRizo', 'FibraPie', 'CalibreTrama'],
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


@include('planeacion.alineacion._styles')

@include('planeacion.alineacion._script')
@endsection
