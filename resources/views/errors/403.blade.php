@extends('errors.layout')

@php
    // Mensaje propio del abort(403, '…') (p. ej. "No tienes acceso a este módulo."); el texto
    // por defecto de Laravel viene en inglés y no se muestra.
    $mensajePropio = isset($exception) ? trim((string) $exception->getMessage()) : '';
    if (in_array($mensajePropio, ['', 'Forbidden', 'This action is unauthorized.'], true)) {
        $mensajePropio = 'No tienes permisos para acceder a esta página.';
    }
@endphp

@section('color', 'red')
@section('codigo', '403')
@section('titulo', 'Acceso denegado')
@section('mensaje', $mensajePropio)
@section('extra')
    <p class="text-gray-600 text-sm leading-relaxed mt-2">Si necesitas entrar, pide el permiso a Sistemas.</p>
@endsection
