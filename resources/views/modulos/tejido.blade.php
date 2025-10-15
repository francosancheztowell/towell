@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto">
        @php
            // Detectar si estás en la ruta especial
            $esTejido = request()->is('modulo-tejido'); //tejido/jacquard-sulzer/ ESTA ES LA URL GLOBAL DE LA VISTA DINÁMICA, NUNCA CAMBIARÁ ESTA PARTE
        @endphp
        @if ($esTejido)
            <div class="flex gap-4 justify-center items-center z-5 botonesApp">
                <!-- Botón Atrás -->
                <button id="backBotton"
                    class="bg-white text-blue-500 font-bold py-2 px-4 rounded-lg shadow-md hover:bg-gray-300 transition duration-300 flex items-center gap-2">
                    ⬅️
                </button>
            </div>
        @endif
        <script>
            document.getElementById('backBotton').addEventListener('click', function() {
                window.location.href = '/produccionProceso';
            });
        </script>

        <h1 class="text-3xl font-bold text-center sm:mt-2 md:-mt-4 mb-2">TEJIDO</h1>

        @php
            $modulos = [
                [
                    'nombre' => 'Jacquard Sulzer',
                    'imagen' => 'Jaqcuard.png',
                    'ruta' => '/tejido/jacquard-sulzer',
                    'ruta_tipo' => 'url'
                ],
                ['nombre' => 'Jacquard Smith', 'imagen' => 'jsmith.jpg', 'ruta' => '/tejido/jacquard-smith', 'ruta_tipo' => 'url'],
                ['nombre' => 'Smith', 'imagen' => 'smith.jpg', 'ruta' => '/tejido/smith', 'ruta_tipo' => 'url'],
                ['nombre' => 'Itema Viejo', 'imagen' => 'itema_viejo.jpg', 'ruta' => '/tejido/itema-viejo', 'ruta_tipo' => 'url'],
                ['nombre' => 'Itema Nuevo', 'imagen' => 'itema_nuevo.jpg', 'ruta' => '/tejido/itema-nuevo', 'ruta_tipo' => 'url'],
            ];
        @endphp

        <x-module-grid :modulos="$modulos" columns="md:grid-cols-3" image-folder="fotos_tejido" />
    </div>
@endsection
