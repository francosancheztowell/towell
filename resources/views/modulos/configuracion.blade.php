@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto px-4 py-6 overflow-y-auto min-h-screen" id="globalLoader">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="Configuración" />

        <!-- Botón de regreso -->
        <x-back-button />

        @if (count($subModulos) === 0)
            <!-- Estado vacío -->
            <x-empty-state
                icon="config"
                title="No hay módulos de configuración disponibles"
                message="No tienes permisos para acceder a los módulos de configuración"
            />
        @else
            <!-- Grid de módulos de configuración usando componente -->
            <x-module-grid :modulos="$subModulos" columns="xl:grid-cols-4" :filterConfig="false" />
        @endif
    </div>
@endsection
