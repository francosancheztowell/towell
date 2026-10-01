@extends('layouts.app')

@section('title', 'Catálogo de Fallas')

@section('page-title')
    Catálogo de Fallas
@endsection

@section('navbar-right')
    {{-- El componente teletransporta aquí sus botones (ver x-tabla). --}}
    <div id="tabla-navbar-acciones" class="flex items-center gap-2"></div>
@endsection

@section('content')
    {{-- Tabla a pantalla completa (.pantalla-completa → raíz flex del componente → x-tabla). --}}
    <div class="pantalla-completa p-2 md:px-4">
        <livewire:mantenimiento.catalogo-fallas />
    </div>
@endsection
