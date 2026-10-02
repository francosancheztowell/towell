@extends('layouts.app')

@section('page-title', 'Máquinas Urdido')

@section('navbar-right')
    {{-- El componente teletransporta aquí sus botones (ver x-tabla). --}}
    <div id="tabla-navbar-acciones" class="flex items-center gap-2"></div>
@endsection

@section('content')
    <div class="pantalla-completa bg-white p-2 md:px-4">
        <livewire:urdido.catalogo-maquinas />
    </div>
@endsection
