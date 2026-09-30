@extends('layouts.app')

@section('page-title', 'OEE Atadores')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalRangoTejido"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-calendar-alt"></i> Seleccionar Fechas
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('atadores.reportes.atadores.descargar', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-download"></i> Exportar Excel
        </a>
        <button type="button" data-accion="exportar-oee"
            class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-export"></i> Exportar a OEE
        </button>
    @endif
@endsection

@section('content')
    @php
        $configOee = [
            'indice' => route('atadores.reportes.index'),
            'fechaIni' => (string) ($fechaIni ?? ''),
            'fechaFin' => (string) ($fechaFin ?? ''),
            'rutas' => [
                'verificar' => route('atadores.reportes.oee.verificar'),
                'despachar' => route('atadores.reportes.oee.despachar'),
                'estado' => route('atadores.reportes.oee.estado', ['token' => '__TOKEN__']),
            ],
        ];
    @endphp
    <div class="w-full p-4" id="reporte-atadores" data-indice="{{ route('atadores.reportes.index') }}" data-oee='@json($configOee)'>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">OEE Atadores</h2>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <div class="text-right text-white text-sm">
                        <div>Seleccionado: {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</div>
                        @if (!empty($lunesIni) && !empty($domingoFin))
                            <div>Semanas por FechaArranque: {{ \Carbon\Carbon::parse($lunesIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($domingoFin)->format('d/m/Y') }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-6">
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione un rango de fechas</p>
                        <p class="text-gray-400 text-sm mt-2">Puede descargar el Excel del rango seleccionado o exportar los datos al archivo <strong>OEE_ATADORES.xlsx</strong>.</p>
                        <button type="button" data-ui-modal-open="modalRangoTejido"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Fechas
                        </button>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-table text-6xl text-blue-400 mb-4"></i>
                        <p class="text-gray-700 text-lg mb-2">Rango seleccionado</p>
                        <p class="text-gray-500 text-sm mb-6">
                            {{ \Carbon\Carbon::parse($lunesIni ?? $fechaIni)->format('d/m/Y') }}
                            al
                            {{ \Carbon\Carbon::parse($domingoFin ?? $fechaFin)->format('d/m/Y') }}
                            &mdash; solo registros <strong>Autorizado</strong> por <strong>FechaArranque</strong>.
                        </p>
                        <div class="flex items-center justify-center gap-4 flex-wrap">
                            <a href="{{ route('atadores.reportes.atadores.descargar', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors">
                                <i class="fas fa-download"></i> Exportar Excel
                            </a>
                            <button type="button" data-accion="exportar-oee"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors">
                                <i class="fas fa-file-export"></i> Exportar a OEE
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('modulos.tejido.reportes.partials.rango-fechas', [
        'ruta' => route('atadores.reportes.atadores'),
        'titulo' => 'Seleccionar rango de fechas',
        'descripcion' => 'El sistema agrupa las semanas de lunes a domingo usando FechaArranque.',
    ])
@endsection

@push('scripts')
    @vite('resources/js/modulos/atadores/reportes/oee/index.ts')
@endpush
