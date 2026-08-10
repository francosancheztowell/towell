@extends('layouts.app')

@section('page-title', 'Reportes')

@section('content')
    <div class="w-full p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h1 class="text-xl font-bold text-white">Reportes</h1>
            </div>

            @if (count($reportes) === 0)
                <div class="px-6 py-10 text-center">
                    <i class="fa-solid fa-chart-column text-gray-300 text-3xl mb-3"></i>
                    <p class="text-gray-600 font-medium">No hay reportes disponibles todavía</p>
                    <p class="text-sm text-gray-500 mt-1">Los reportes de mecánicos se agregarán en esta sección.</p>
                </div>
            @else
                <div class="divide-y divide-gray-200">
                    @foreach ($reportes as $num => $reporte)
                        <a href="{{ $reporte['url'] }}"
                           class="block px-6 py-4 hover:bg-gray-50 transition-colors {{ !($reporte['disponible'] ?? true) ? 'opacity-80' : '' }}">
                            <div class="flex items-center gap-4">
                                <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm border border-blue-200">
                                    {{ $num + 1 }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <span class="font-semibold text-gray-900 block">{{ $reporte['nombre'] }}</span>
                                    <span class="text-sm text-gray-500">{{ $reporte['accion'] }}</span>
                                </div>
                                @if ($reporte['disponible'] ?? true)
                                    <i class="fas fa-chevron-right text-gray-400 flex-shrink-0"></i>
                                @else
                                    <span class="text-xs text-amber-600 font-medium flex-shrink-0">Próximamente</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
