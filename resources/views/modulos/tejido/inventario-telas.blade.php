@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Inventario de Telas')

@section('content')
    <div class="container mx-auto">
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
