@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold text-center mb-10">ATADORES</h1>

    @php
        $modulos = [
            ['nombre' => 'Programación', 'imagen' => 'Atadores.jpg', 'ruta' => '/atadores-juliosAtados', 'ruta_tipo' => 'url'],
        ];
    @endphp

    <x-module-grid :modulos="$modulos" columns="md:grid-cols-3" />
</div>
@endsection

