@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold text-center mb-10">PROGRAMAR REQUERIMIENTOS</h1>

    @php
        $modulos = [
            ['nombre' => 'Requerimiento 1', 'imagen' => 'requerimientos.jpg', 'ruta' => '#', 'ruta_tipo' => 'url'],
            ['nombre' => 'Requerimiento 2', 'imagen' => 'requerimientos.jpg', 'ruta' => '#', 'ruta_tipo' => 'url']
        ];
    @endphp

    <x-module-grid :modulos="$modulos" columns="md:grid-cols-3" image-folder="fotos_tejido" />
</div>
@endsection
-->
