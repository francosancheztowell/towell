@extends('layouts.app')

@section('title', 'Costo por julio')

@section('page-title')
    Costo por julio
@endsection

@section('navbar-right')
    {{-- Directo / Absorbente (x-costos.costeo), el mismo de Cuotas y la Hoja de costos. --}}
    <div id="costos-navbar-costeo"></div>
@endsection

@section('content')
    <div class="pantalla-completa bg-white p-2 md:px-4">
        <livewire:costos.costo-julios />
    </div>
@endsection
