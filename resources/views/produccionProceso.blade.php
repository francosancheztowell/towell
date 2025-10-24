@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container" id="globalLoader">
        <!-- Grid de módulos usando componente -->
        <x-module-grid :modulos="$modulos" columns="xl:grid-cols-4" :filterConfig="true" />
    </div>

    <!-- Sistema de Precarga de Módulos -->
    <script src="{{ asset('js/module-prefetch.js') }}"></script>
@endsection
