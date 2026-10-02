@extends('layouts.app')

@section('page-title')
    Lista de Materiales Urdido
@endsection

@section('navbar-right')
    {{-- El componente teletransporta aquí sus botones (ver x-tabla). --}}
    <div id="tabla-navbar-acciones" class="flex items-center gap-2"></div>
@endsection

@push('styles')
    <style>
        body:has(.lmat-urdido),
        body:has(.lmat-urdido) main.app-main,
        .lmat-urdido .bg-slate-50\/60 {
            background: #fff;
        }
    </style>
@endpush

@section('content')
    <div class="lmat-urdido pantalla-completa bg-white p-2 md:px-4">
        <livewire:urdido.lista-materiales />
    </div>
@endsection
