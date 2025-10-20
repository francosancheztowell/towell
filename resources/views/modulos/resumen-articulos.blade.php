@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-blue-500 px-6 py-4 border-t-4 border-orange-400">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <button onclick="window.close()" class="flex items-center justify-center w-10 h-10 text-white hover:bg-blue-600 rounded-lg transition-colors mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <h1 class="text-2xl font-bold text-white">Resumen de Artículos</h1>
                </div>
                <div class="text-white text-right">
                    <div class="text-sm opacity-90">Folio: <span class="font-semibold">{{ $requerimiento->Folio }}</span></div>
                    <div class="text-sm opacity-90">Fecha: <span class="font-semibold">{{ \Carbon\Carbon::parse($requerimiento->Fecha)->format('d/m/Y') }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información del folio -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-sm text-gray-500 uppercase tracking-wide">Folio</div>
                <div class="text-2xl font-bold text-gray-900">{{ $requerimiento->Folio }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500 uppercase tracking-wide">Fecha</div>
                <div class="text-lg font-semibold text-gray-900">{{ \Carbon\Carbon::parse($requerimiento->Fecha)->format('d/m/Y') }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500 uppercase tracking-wide">Status</div>
                <div class="text-lg font-semibold text-gray-900">{{ $requerimiento->Status }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500 uppercase tracking-wide">Total Artículos</div>
                <div class="text-2xl font-bold text-blue-600">{{ $totalConsumos }}</div>
            </div>
        </div>
    </div>

    <!-- Información horizontal para impresión -->
    <div class="print-only bg-white p-4 mb-4 border border-gray-300">
        <div class="flex justify-between items-center text-sm">
            <div class="flex space-x-8">
                <span><strong>FOLIO:</strong> {{ $requerimiento->Folio }}</span>
                <span><strong>FECHA:</strong> {{ \Carbon\Carbon::parse($requerimiento->Fecha)->format('d/m/Y') }}</span>
                <span><strong>STATUS:</strong> {{ $requerimiento->Status }}</span>
                <span><strong>TOTAL ARTÍCULOS:</strong> {{ $totalConsumos }}</span>
            </div>
        </div>
    </div>

    <!-- Tabla de resumen por salón -->
    @if($consumosPorSalon->count() > 0)
        @foreach($consumosPorSalon as $salon => $consumos)
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Salón: {{ $salon }}</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Artículo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($consumos as $consumo)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                {{ $consumo->CalibreTrama ? number_format($consumo->CalibreTrama, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                {{ $consumo->FibraTrama ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                {{ $consumo->CodColorTrama ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                {{ $consumo->ColorTrama ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                {{ $consumo->Cantidad ? number_format($consumo->Cantidad, 0) : '0' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @else
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay artículos registrados</h3>
            <p class="text-gray-500">Este folio no tiene consumos registrados</p>
        </div>
    @endif

    <!-- Botón de impresión -->
    <div class="text-center mt-6">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
            <i class="fas fa-print mr-2"></i>Imprimir Resumen
        </button>
    </div>
</div>

<!-- Estilos para impresión -->
<style>
.print-only {
    display: none;
}

@media print {
    .container {
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .bg-blue-500 {
        background-color: #3b82f6 !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }

    .bg-gray-50 {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }

    .border-gray-200 {
        border-color: #e5e7eb !important;
    }

    button {
        display: none !important;
    }

    .shadow-lg, .shadow-md {
        box-shadow: none !important;
    }

    .rounded-lg {
        border-radius: 0 !important;
    }

    /* Mostrar información horizontal en impresión */
    .print-only {
        display: block !important;
    }

    /* Ocultar información vertical en impresión */
    .grid.grid-cols-1.md\\:grid-cols-4 {
        display: none !important;
    }

    /* Ajustar espaciado para impresión */
    .print-only {
        margin-bottom: 10px !important;
        padding: 8px !important;
    }
}
</style>
@endsection
