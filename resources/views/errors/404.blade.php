@extends('errors.layout')

@section('color', 'blue')
@section('codigo', '404')
@section('titulo', 'Página no encontrada')
@section('mensaje', 'La página que buscas no existe o cambió de dirección.')
@section('imagen')
    <picture>
        <source srcset="{{ asset('images/fotos_usuarios/towelin404.webp') }}" type="image/webp">
        <img src="{{ asset('images/fotos_usuarios/towelin404.png') }}" alt="" width="700" height="906" decoding="async" class="h-48 w-auto mx-auto">
    </picture>
@endsection
