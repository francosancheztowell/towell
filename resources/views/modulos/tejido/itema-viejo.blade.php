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
    <div class="container mx-auto">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="Tejido" subtitulo="Itema Viejo" />

        @php
            $telares = ['303', '304', '317', '318'];
            $modulos = array_map(function($telar) {
                return [
                    'nombre' => $telar,
                    'imagen' => 'itema_viejo.jpg',
                    'ruta_tipo' => 'route',
                    'ruta' => 'tejido.mostrarTelarSulzer',
                    'params' => ['telar' => $telar]
                ];
            }, $telares);
        @endphp

        <x-module-grid :modulos="$modulos" columns="md:grid-cols-6" image-folder="fotos_tejido" :filterConfig="false" />
    </div>
@endsection
