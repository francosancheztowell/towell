@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold text-center mb-10">TEJEDORES</h1>

    @php
        $modulos = [
            ['nombre' => 'Tejedor 1', 'imagen' => 'tejedores.jpg', 'ruta' => '/tejedores/tejedores1', 'ruta_tipo' => 'url'],
            ['nombre' => 'Tejedor 2', 'imagen' => 'tejedores.jpg', 'ruta' => '/tejedores/tejedores2', 'ruta_tipo' => 'url'],
            ['nombre' => 'Tejedor 3', 'imagen' => 'tejedores.jpg', 'ruta' => '/tejedores/tejedores3', 'ruta_tipo' => 'url'],
            ['nombre' => 'Tejedor 4', 'imagen' => 'tejedores.jpg', 'ruta' => '/tejedores/tejedores4', 'ruta_tipo' => 'url'],
            ['nombre' => 'Tejedor 5', 'imagen' => 'tejedores.jpg', 'ruta' => '/tejedores/tejedores5', 'ruta_tipo' => 'url'],
            ['nombre' => 'Programar Requerimientos', 'imagen' => 'tejedores.jpg', 'ruta' => '/tejedores/programar-requerimientos', 'ruta_tipo' => 'url']
        ];
    @endphp

    <x-module-grid :modulos="$modulos" columns="md:grid-cols-3" />
</div>
@endsection

