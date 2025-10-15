@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="Tejido" subtitulo="Inventario de Telas" />

        <div class="mt-2">
            <x-back-button text="Volver a Tejido" />
        </div>
        @php
            $modulos = [
                [
                    'nombre' => 'Jacquard',
                    'imagen' => 'Jaqcuard.png',
                    'ruta' => '/tejido/inventario-telas/jacquard',
                    'ruta_tipo' => 'url'
                ],
                [
                    'nombre' => 'Itema',
                    'imagen' => 'itema_nuevo.jpg',
                    'ruta' => '/tejido/inventario-telas/itema',
                    'ruta_tipo' => 'url'
                ],
                [
                    'nombre' => 'Karl Mayer',
                    'imagen' => 'smith.jpg',
                    'ruta' => '/tejido/inventario-telas/karl-mayer',
                    'ruta_tipo' => 'url'
                ],
            ];
        @endphp

        <x-module-grid :modulos="$modulos" columns="md:grid-cols-4" image-folder="fotos_tejido" />
    </div>
@endsection
