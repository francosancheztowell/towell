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
        <x-produccion-proceso-header titulo="Tejido" subtitulo="Smith" />

        @php
            $telares = ['305', '306', '307', '308', '309', '310', '311', '312', '313', '314', '315', '316'];
            $modulos = array_map(function($telar) {
                return [
                    'nombre' => $telar,
                    'imagen' => 'smith.jpg',
                    'ruta_tipo' => 'route',
                    'ruta' => 'tejido.mostrarTelarSulzer',
                    'params' => ['telar' => $telar]
                ];
            }, $telares);
        @endphp

        <x-module-grid :modulos="$modulos" columns="md:grid-cols-6" image-folder="fotos_tejido" :filterConfig="false" />
    </div>
@endsection
