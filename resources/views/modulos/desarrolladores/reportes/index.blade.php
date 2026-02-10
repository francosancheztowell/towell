@extends('layouts.app')

@section('page-title', 'Reportes Desarrolladores')

@section('content')
    <div class="w-full p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h1 class="text-xl font-bold text-white">Reportes Desarrolladores</h1>
            </div>
            <div class="divide-y divide-gray-200">
                @foreach ($reportes as $num => $reporte)
                    <a href="{{ $reporte['url'] }}"
                       class="block px-6 py-4 hover:bg-gray-50 transition-colors {{ !$reporte['disponible'] ? 'opacity-80' : '' }}">
                        <div class="flex items-center gap-4">
                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">
                                {{ $num + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-gray-900 block">{{ $reporte['nombre'] }}</span>
                                <span class="text-sm text-gray-500">{{ $reporte['accion'] }}</span>
                            </div>
                            @if ($reporte['disponible'])
                                <i class="fas fa-chevron-right text-gray-400 flex-shrink-0"></i>
                            @else
                                <span class="text-xs text-amber-600 font-medium flex-shrink-0">Próximamente</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endsection