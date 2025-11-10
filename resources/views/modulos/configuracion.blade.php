@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title
        title="Configuración"
    />
@endsection

@section('content')
    <div class="container mx-auto px-4 py-6 overflow-y-auto min-h-screen" id="globalLoader">

        @if (count($subModulos) === 0)
            <!-- Estado vacío -->
            <x-empty.empty-state
                icon="config"
                title="No hay módulos de configuración disponibles"
                message="No tienes permisos para acceder a los módulos de configuración"
            />
        @else
            <!-- Grid de módulos de configuración usando componente -->
            <x-layout.module-grid :modulos="$subModulos" columns="xl:grid-cols-4" :filterConfig="false" />
        @endif
    </div>
@endsection
