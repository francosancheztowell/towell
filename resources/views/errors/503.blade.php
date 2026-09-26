@extends('errors.layout')

@section('color', 'amber')
@section('codigo', '503')
@section('titulo', 'Sistema en mantenimiento')
@section('mensaje', 'Estamos actualizando el sistema. Vuelve a intentarlo en unos minutos.')
@section('acciones')
    <a href="{{ request()->isMethod('GET') ? request()->fullUrl() : url('/produccionProceso') }}"
       class="inline-flex items-center justify-center min-h-touch bg-amber-600 hover:bg-amber-700 text-white px-8 py-3 rounded-md font-medium text-sm transition-colors duration-200">
        Reintentar
    </a>
@endsection
