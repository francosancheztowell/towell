@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    {{-- Texto plano: el navbar pone el <h1>. sidebar.ts lo cambia por la subsección elegida. --}}
    <span data-pvoc-page-title>Plan de Ventas vs OC vs Real</span>
@endsection

@section('navbar-right')
    <div class="relative flex items-center gap-2">
        <x-navbar.button-report
            id="btn-filtrar-ventas-compara"
            title="Filtrar"
            text="Filtrar"
            icon="fa-filter"
            bg="bg-green-600"
            iconColor="text-white"
            :checkPermission="false"
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-controls="pvoc-filter-panel"
        />
    </div>
@endsection

@section('content')
    <livewire:ventas.dashboard-pv-vs-oc />
@endsection
