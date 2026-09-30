@extends('layouts.app')

@section('page-title', 'Reportes Mantenimiento')

@section('content')
    <div id="pagina-reportes-mant" class="w-full p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white">Reportes Mantenimiento</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @foreach ($reportes as $num => $reporte)
                    <button type="button"
                            data-accion="abrir-rango"
                            data-url="{{ $reporte['url'] }}"
                            @if (!$reporte['disponible']) aria-disabled="true" @endif
                            class="block w-full text-left px-6 py-4 hover:bg-gray-50 transition-colors {{ !$reporte['disponible'] ? 'opacity-80 cursor-not-allowed' : 'cursor-pointer' }}">
                        <div class="flex items-center gap-4">
                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">
                                {{ $num + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-gray-900 block">{{ $reporte['nombre'] }}</span>
                                <span class="text-sm text-gray-500">{{ $reporte['accion'] }}</span>
                            </div>
                            @if ($reporte['disponible'])
                                <i class="fas fa-chevron-right text-gray-400 flex-shrink-0" aria-hidden="true"></i>
                            @else
                                <span class="text-xs text-amber-600 font-medium flex-shrink-0">Próximamente</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Modal Rango de Fechas --}}
    <div id="modal-fechas-mant" data-modal-rango role="dialog" aria-modal="true" aria-labelledby="titulo-rango-mant" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white max-w-md w-full rounded-xl shadow-xl p-6 m-4">
            <h2 id="titulo-rango-mant" class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fa-solid fa-calendar-days text-blue-600 mr-2"></i>Rango de fechas
            </h2>
            <div class="space-y-4">
                <div>
                    <label for="fecha_ini_mant" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                    <input type="date" id="fecha_ini_mant" data-rango="ini" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="fecha_fin_mant" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                    <input type="date" id="fecha_fin_mant" data-rango="fin" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button type="button" id="btn-confirmar-fechas" data-accion="confirmar-rango" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm">
                    Consultar
                </button>
                <button type="button" id="btn-cerrar-modal-fechas" data-accion="cerrar-rango" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/mantenimiento/reportes/index.ts')
@endpush
