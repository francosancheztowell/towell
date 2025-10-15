@extends('layouts.app')

@section('content')
    @if (session('warning'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'ATENCIÓN',
                    text: @json(session('warning')),
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    backdrop: true,
                    customClass: {
                        popup: 'text-xl'
                    }
                });
            });
        </script>
    @endif

    <div class="container">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="Tejido" subtitulo="Jacquard Sulzer" />

        @php
            $modulos = [
                ['nombre' => '207', 'imagen' => 'Jaqcuard.png', 'ruta_tipo' => 'route', 'ruta' => 'tejido.mostrarTelarSulzer', 'params' => ['telar' => '207']],
                ['nombre' => '208', 'imagen' => 'Jaqcuard.png', 'ruta_tipo' => 'route', 'ruta' => 'tejido.mostrarTelarSulzer', 'params' => ['telar' => '208']],
                ['nombre' => '209', 'imagen' => 'Jaqcuard.png', 'ruta_tipo' => 'route', 'ruta' => 'tejido.mostrarTelarSulzer', 'params' => ['telar' => '209']],
                ['nombre' => '210', 'imagen' => 'Jaqcuard.png', 'ruta_tipo' => 'route', 'ruta' => 'tejido.mostrarTelarSulzer', 'params' => ['telar' => '210']],
                ['nombre' => '211', 'imagen' => 'Jaqcuard.png', 'ruta_tipo' => 'route', 'ruta' => 'tejido.mostrarTelarSulzer', 'params' => ['telar' => '211']],
            ];
        @endphp

        <x-module-grid :modulos="$modulos" columns="md:grid-cols-4" image-folder="fotos_tejido" :filterConfig="false" />
    </div>
@endsection
