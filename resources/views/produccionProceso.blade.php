@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title
        title="Producción en Proceso"
    />
@endsection

@section('content')
    <div class="container mx-auto" id="globalLoader">
        <!-- Grid de módulos usando componente -->
        <x-layout.module-grid :modulos="$modulos" columns="xl:grid-cols-4" :filterConfig="true" />
    </div>
@endsection
