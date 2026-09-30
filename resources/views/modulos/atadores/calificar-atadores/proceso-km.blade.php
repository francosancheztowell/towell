@extends('layouts.app')

@section('page-title', $titulo)

@section('content')
    @php
        $tipo = trim((string) $item->Tipo);
        $tipoTexto = preg_match('/^[1-4]$/', $tipo) ? 'Barra '.$tipo : $tipo;
    @endphp
    @php
        $usuarioActual = auth()->user();
        $configPagina = [
            'guardar' => route('atadores.save'),
            'empleados' => url('/obtener-empleados'),
            'noJulio' => (string) $item->NoJulio,
            'noOrden' => (string) $item->NoProduccion,
            'yo' => $usuarioActual ? ['cve' => (string) $usuarioActual->numero_empleado, 'nombre' => (string) $usuarioActual->nombre, 'area' => (string) $usuarioActual->area] : null,
        ];
    @endphp
    <div class="container mx-auto px-4 py-6 max-w-3xl" id="proceso-km" data-pagina='@json($configPagina)'>
        <a href="{{ route('atadores.calificar', ['no_julio' => $item->NoJulio, 'no_orden' => $item->NoProduccion]) }}"
            class="inline-flex items-center text-sm text-blue-700 hover:text-blue-900 mb-4">
            Volver al atado
        </a>

        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <span class="block text-xs text-gray-500">Telar</span>
                    <span class="font-semibold text-gray-800">{{ $item->NoTelarId ?? '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Tipo</span>
                    <span class="font-semibold text-gray-800">{{ $tipoTexto !== '' ? $tipoTexto : '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Julio</span>
                    <span class="font-semibold text-gray-800">{{ $item->NoJulio }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Orden</span>
                    <span class="font-semibold text-gray-800">{{ $item->NoProduccion }}</span>
                </div>
            </div>
        </div>

        @include('modulos.atadores.calificar-atadores._proceso-km', [
            'titulo' => $titulo,
            'prefijo' => $proceso,
            'registro' => $registro,
        ])
    </div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/atadores/proceso-km/index.ts')
@endpush
