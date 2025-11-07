@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-page-title
        title="Producción en Proceso"
    />
@endsection

@section('content')
    <div class="container" id="globalLoader">
        <!-- Grid de módulos usando componente -->
        <x-module-grid :modulos="$modulos" columns="xl:grid-cols-4" :filterConfig="true" />
    </div>
@endsection
