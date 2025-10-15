@extends('layouts.app', ['ocultarBotones' => true])

@section('content')

    <div class="container mx-auto px-4 py-6 overflow-y-auto min-h-screen" id="globalLoader">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="PRODUCCIÓN EN PROCESO" />

        @if (count($modulos) === 1)
            <!-- Si solo hay un módulo permitido, redirigir automáticamente -->
            <script>
                window.location.href = "{{ url(reset($modulos)['ruta']) }}";
            </script>
        @else
            <!-- Grid de módulos usando componente -->
            <x-module-grid :modulos="$modulos" columns="xl:grid-cols-4" :filterConfig="true" />
        @endif
    </div>

    <!-- Sistema de Precarga de Módulos -->
    <script src="{{ asset('js/module-prefetch.js') }}"></script>

@endsection
