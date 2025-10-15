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
        <h1 class="text-3xl font-bold text-center sm:mt-2 md:-mt-4 mb-2">JACQUARD SMITH</h1>

        @php
            $telares = ['201', '202', '203', '204', '205', '206', '213', '214', '215'];
            $modulos = array_map(function($telar) {
                return [
                    'nombre' => $telar,
                    'imagen' => 'jsmith.jpg',
                    'ruta_tipo' => 'route',
                    'ruta' => 'tejido.mostrarTelarSulzer',
                    'params' => ['telar' => $telar]
                ];
            }, $telares);
        @endphp

        <x-module-grid :modulos="$modulos" columns="md:grid-cols-6" image-folder="fotos_tejido" :filterConfig="false" />
    </div>
@endsection
