@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Plan de Ventas vs OC vs Real" />
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
