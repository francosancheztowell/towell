@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Resumen de Artículos')

@section('navbar-right')
<button onclick="window.print()"
        class="flex items-center gap-2 px-4 py-2 bg-white text-blue-600 rounded-lg font-semibold hover:bg-blue-50 transition-all duration-200 shadow-md hover:shadow-lg print:hidden">
    <i class="fas fa-print"></i>
    <span>Imprimir</span>
</button>
@endsection

@section('content')
@php
    $statusColors = [
        'En Proceso' => 'bg-blue-100 text-blue-800 border-blue-200',
        'Solicitado' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'Surtido' => 'bg-green-100 text-green-800 border-green-200',
        'Cancelado' => 'bg-red-100 text-red-800 border-red-200',
        'Creado' => 'bg-gray-100 text-gray-800 border-gray-200',
    ];
    $statusClass = $statusColors[$requerimiento->Status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
    $fechaFormateada = \Carbon\Carbon::parse($requerimiento->Fecha)->format('d/m/Y');
    $totalCantidad = $consumosPorSalon->flatten()->sum('Cantidad');
@endphp

<div class="min-h-screen bg-white py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Tarjetas de Información Principal -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Folio -->
            <div class="bg-white rounded-lg p-5 border-l-4 border-blue-500 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Folio</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $requerimiento->Folio }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Fecha -->
            <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-green-500 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Fecha</p>
                        <p class="text-xl font-semibold text-gray-900">{{ $fechaFormateada }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-purple-500 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold border {{ $statusClass }}">
                            {{ $requerimiento->Status }}
                        </span>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-info-circle text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Artículos -->
            <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-orange-500 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Artículos</p>
                        <p class="text-2xl font-bold text-orange-600">{{ $totalConsumos }}</p>
                        @if($totalCantidad > 0)
                            <p class="text-xs text-gray-500 mt-1">Cantidad total: {{ number_format($totalCantidad, 0) }}</p>
                        @endif
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-boxes text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información para Impresión (oculta en pantalla) -->
        <div class="print-only bg-white p-4 mb-4 border-2 border-gray-300 rounded-lg">
            <div class="flex justify-between items-center text-sm flex-wrap gap-2">
                <div class="flex gap-6 flex-wrap">
                    <span class="font-semibold text-gray-700">FOLIO: <span class="font-normal">{{ $requerimiento->Folio }}</span></span>
                    <span class="font-semibold text-gray-700">FECHA: <span class="font-normal">{{ $fechaFormateada }}</span></span>
                    <span class="font-semibold text-gray-700">STATUS: <span class="font-normal">{{ $requerimiento->Status }}</span></span>
                    <span class="font-semibold text-gray-700">TOTAL ARTÍCULOS: <span class="font-normal">{{ $totalConsumos }}</span></span>
                    @if($totalCantidad > 0)
                        <span class="font-semibold text-gray-700">CANTIDAD TOTAL: <span class="font-normal">{{ number_format($totalCantidad, 0) }}</span></span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Tablas por Salón -->
        @if($consumosPorSalon->count() > 0)
            @foreach($consumosPorSalon as $salon => $consumos)
                @php
                    $salonCantidad = $consumos->sum('Cantidad');
                    $salonCount = $consumos->count();
                @endphp
                <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6 print:mb-4 print:shadow-none print:border print:border-gray-300">
                    <!-- Header del Salón -->
                    <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4 print:bg-gray-200 print:from-gray-200 print:to-gray-200">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center print:bg-gray-300">
                                    <i class="fas fa-building text-white text-lg print:text-gray-700"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-white print:text-gray-900">Salón: {{ $salon ?? 'Sin asignar' }}</h2>
                                    <p class="text-gray-300 text-sm print:text-gray-600">
                                        {{ $salonCount }} {{ $salonCount === 1 ? 'artículo' : 'artículos' }}
                                        @if($salonCantidad > 0)
                                            • Cantidad total: {{ number_format($salonCantidad, 0) }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Consumos -->
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 print:bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                                        <i class="fas fa-barcode mr-1"></i>Artículo
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                                        <i class="fas fa-thread mr-1"></i>Fibra
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                                        <i class="fas fa-tag mr-1"></i>Código Color
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                                        <i class="fas fa-palette mr-1"></i>Nombre Color
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        <i class="fas fa-cubes mr-1"></i>Cantidad
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($consumos as $index => $consumo)
                                    <tr class="hover:bg-blue-50 transition-colors duration-150 print:hover:bg-transparent {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 border-r border-gray-200">
                                            @if($consumo->CalibreTrama)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-blue-100 text-blue-800 font-semibold">
                                                    {{ number_format($consumo->CalibreTrama, 2) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 border-r border-gray-200">
                                            @if($consumo->FibraTrama)
                                                {{ $consumo->FibraTrama }}
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 border-r border-gray-200">
                                            @if($consumo->CodColorTrama)
                                                <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-gray-800 font-mono text-xs">
                                                    {{ $consumo->CodColorTrama }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 border-r border-gray-200">
                                            @if($consumo->ColorTrama)
                                                {{ $consumo->ColorTrama }}
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right">
                                            @if($consumo->Cantidad && $consumo->Cantidad > 0)
                                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800">
                                                    <i class="fas fa-box mr-1 text-xs"></i>
                                                    {{ number_format($consumo->Cantidad, 0) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <!-- Fila de Total por Salón -->
                                <tr class="bg-gray-100 font-bold print:bg-gray-200">
                                    <td colspan="4" class="px-4 py-3 text-sm text-gray-700 text-right border-r border-gray-200">
                                        Total del Salón:
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                                            {{ number_format($salonCantidad, 0) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach


        @else
            <!-- Estado Vacío -->
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="max-w-md mx-auto">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No hay artículos registrados</h3>
                    <p class="text-gray-500 mb-6">Este folio no tiene consumos registrados en el sistema.</p>
                    <button onclick="window.close()"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-colors shadow-md hover:shadow-lg">
                        <i class="fas fa-arrow-left"></i>
                        <span>Volver</span>
                    </button>
                </div>
            </div>
        @endif

    </div>
</div>

<style>
    /* Estilos de Impresión */
    .print-only {
        display: none;
    }

    @media print {
        @page {
            margin: 1cm;
            size: A4;
        }

        body {
            background: white !important;
            color: black !important;
        }

        .min-h-screen {
            min-height: auto !important;
            background: white !important;
            padding: 0 !important;
        }

        .max-w-7xl {
            max-width: 100% !important;
        }

        /* Ocultar elementos no necesarios en impresión */
        button:not(.print-button),
        .print\\:hidden {
            display: none !important;
        }

        /* Mostrar información de impresión */
        .print-only {
            display: block !important;
        }

        /* Header de impresión */
        .bg-gradient-to-r.from-blue-600 {
            background: #1e40af !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Tablas */
        table {
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }

        /* Evitar cortes de página en salones */
        .bg-white.rounded-xl.shadow-lg {
            page-break-inside: avoid;
            margin-bottom: 1cm;
        }

        /* Ajustes de color para impresión */
        .bg-gray-50,
        .bg-gray-100 {
            background-color: #f3f4f6 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .bg-blue-100,
        .bg-green-100,
        .bg-orange-100 {
            background-color: #dbeafe !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Bordes para impresión */
        .border,
        .border-r,
        .border-b {
            border-color: #d1d5db !important;
        }

        /* Sombras para impresión */
        .shadow-lg,
        .shadow-md,
        .shadow-xl {
            box-shadow: none !important;
        }

        /* Bordes redondeados para impresión */
        .rounded-xl,
        .rounded-lg {
            border-radius: 0 !important;
        }

        /* Espaciado optimizado para impresión */
        .mb-6 {
            margin-bottom: 0.75cm !important;
        }

        .py-6 {
            padding-top: 0.5cm !important;
            padding-bottom: 0.5cm !important;
        }

        .px-4 {
            padding-left: 0.5cm !important;
            padding-right: 0.5cm !important;
        }
    }

    /* Animaciones suaves */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .bg-white.rounded-xl {
        animation: fadeIn 0.3s ease-out;
    }

    /* Scrollbar personalizado */
    .overflow-x-auto::-webkit-scrollbar {
        height: 8px;
    }

    .overflow-x-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>
@endsection


