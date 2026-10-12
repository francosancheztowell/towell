@extends('layouts.app')

@section('title', 'Hoja de Costo Unitario')

@section('page-title')
    Hoja de Costo Unitario
@endsection

@section('navbar-right')
    {{-- El componente teletransporta aquí sus botones. --}}
    <div id="hoja-navbar-acciones" class="flex items-center gap-2"></div>
@endsection

@section('content')
    <livewire:costos.hoja-costos />
@endsection
