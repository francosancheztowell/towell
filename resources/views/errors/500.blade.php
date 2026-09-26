@extends('errors.layout')

@php
    // Código de referencia = evento de SYSMonErrorEvento de ESTA excepción (HANDOFF 20-02 #5).
    // bootstrap/app.php guarda excepción → Id en el WeakMap `monitoreo.eventos_por_excepcion`.
    // Laravel envuelve lo que no es HttpException en HttpException(500, …, $original): se busca
    // en toda la cadena de getPrevious(). EstadoRequest::eventoId (el último error de la
    // request, que puede ser otro) solo cuenta si la vista se pinta sin excepción.
    $codigoReferencia = rescue(function () use ($exception) {
        if (! isset($exception)) {
            return app(\App\Services\Monitoreo\EstadoRequest::class)->eventoId;
        }
        $mapa = app()->bound('monitoreo.eventos_por_excepcion') ? app('monitoreo.eventos_por_excepcion') : null;
        for ($e = $exception; $e !== null && $mapa !== null; $e = $e->getPrevious()) {
            if (isset($mapa[$e])) {
                return $mapa[$e];
            }
        }

        return null;
    }, null, false);
@endphp

@section('color', 'orange')
@section('codigo', '500')
@section('titulo', 'Error del servidor')
@section('mensaje', 'Algo salió mal en nuestro servidor.')
@section('extra')
    @if($codigoReferencia)
        <p class="text-gray-600 text-sm leading-relaxed mt-2">
            Código de referencia: <strong class="text-gray-700">#{{ $codigoReferencia }}</strong><br>
            Si el problema continúa, comparte este código con Sistemas.
        </p>
    @endif
@endsection
@section('acciones')
    <a href="{{ url('/produccionProceso') }}"
       class="inline-flex items-center justify-center min-h-touch bg-orange-600 hover:bg-orange-700 text-white px-8 py-3 rounded-md font-medium text-sm transition-colors duration-200">
        Volver al inicio
    </a>
    @if(request()->isMethod('GET'))
        <a href="{{ request()->fullUrl() }}"
           class="inline-flex items-center justify-center min-h-touch bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-md font-medium text-sm transition-colors duration-200">
            Reintentar
        </a>
    @endif
@endsection
