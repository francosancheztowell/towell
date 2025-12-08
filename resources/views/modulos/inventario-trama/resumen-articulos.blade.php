@extends('layouts.app', ['ocultarBotones' => true])

@section('navbar-right')
{{-- Navbar oculto en esta vista --}}
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
    $totalCantidad = $consumosPorSalon->flatten()->sum('Cantidad');
@endphp

<div class="bg-white py-4">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <!-- Resumen compacto -->
        <div class="bg-white border rounded-lg p-4 flex flex-wrap gap-3 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-500 font-semibold">Folio:</span>
                <span class="text-gray-900 font-bold">{{ $requerimiento->Folio }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 font-semibold">Fecha:</span>
                <span class="text-gray-900">{{ formatearFecha($requerimiento->Fecha) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 font-semibold">Status:</span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold border {{ $statusClass }}">
                    {{ $requerimiento->Status }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 font-semibold">Artículos:</span>
                <span class="text-gray-900 font-bold">{{ $totalConsumos }}</span>
            </div>
            @if($totalCantidad > 0)
            <div class="flex items-center gap-2">
                <span class="text-gray-500 font-semibold">Cantidad total:</span>
                <span class="text-gray-900 font-bold">{{ number_format($totalCantidad, 0) }}</span>
            </div>
            @endif
        </div>

        <!-- Resumen agrupado por salón -->
        @php
            $salonesResumen = $consumosPorSalon->map(function ($consumos) {
                return $consumos->groupBy(function ($c) {
                    return implode('|', [
                        trim((string) $c->CalibreTrama),
                        trim((string) $c->FibraTrama),
                        trim((string) $c->CodColorTrama),
                        trim((string) $c->ColorTrama),
                    ]);
                })->map(function ($items) {
                    $first = $items->first();
                    return [
                        'Calibre'   => $first->CalibreTrama,
                        'Fibra'     => $first->FibraTrama,
                        'CodColor'  => $first->CodColorTrama,
                        'Color'     => $first->ColorTrama,
                        'Cantidad'  => $items->sum('Cantidad'),
                        'Partidas'  => $items->count(),
                    ];
                })->values();
            });
        @endphp

        @if($salonesResumen->count() > 0)
            @foreach($salonesResumen as $salon => $items)
                @php
                    $salonCantidad = $items->sum('Cantidad');
                    $salonCount = $items->sum('Partidas');
                @endphp
                <div class="bg-white border rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-100 text-sm">
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-gray-800">Salón: {{ $salon ?? 'Sin asignar' }}</span>
                            <span class="text-gray-600">Partidas: {{ $salonCount }}</span>
                        </div>
                        <div class="text-gray-700 font-semibold">
                            Cantidad total: {{ number_format($salonCantidad, 0) }}
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Calibre</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Fibra</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Cod Color</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Color</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700">Cantidad</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700">Partidas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($items as $row)
                                    <tr class="hover:bg-blue-50">
                                        <td class="px-3 py-2 text-gray-800">{{ $row['Calibre'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-800">{{ $row['Fibra'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-800">{{ $row['CodColor'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-800">{{ $row['Color'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">
                                            {{ number_format($row['Cantidad'] ?? 0, 0) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ $row['Partidas'] ?? 0 }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @else
            <div class="bg-white border rounded-lg p-8 text-center">
                <i class="fas fa-inbox text-3xl text-gray-400 mb-2"></i>
                <p class="text-gray-700 font-semibold">No hay artículos registrados</p>
                <p class="text-gray-500 text-sm">Este folio no tiene consumos registrados.</p>
            </div>
        @endif

    </div>
</div>

<style>
    .card-compact { border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .table-resumen th, .table-resumen td { border-color:#e5e7eb; }
    .table-resumen thead { background:#f8fafc; }
    /* Ocultar navbar en esta vista */
    header, header nav, nav, .navbar, .nav-bar, .top-nav, .topbar, .main-nav, .app-header, .navbar-collapse {
        display: none !important;
        height: 0 !important;
        min-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    body, main, .content, .content-wrapper {
        padding-top: 0 !important;
        margin-top: 0 !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        min-height: auto !important;
    }
    @media print {
        @page { margin: 12mm; size: A4; }
        body { color:#111827; }
        .card-compact { box-shadow:none; }
        .table-resumen th, .table-resumen td { border-color:#d1d5db !important; }
    }
</style>

@endsection

















