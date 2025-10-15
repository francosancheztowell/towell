{{--
    Componente: Header Producción en Proceso

    Descripción:
        Componente reutilizable para mostrar el header "PRODUCCIÓN EN PROCESO"
        con título personalizable para diferentes módulos y submódulos.

    Props:
        @param string $titulo - Título del módulo/submódulo (ej: "Planeación", "Tejido", etc.)
        @param string $subtitulo - Subtítulo opcional (ej: "Jacquard", "Inventario de Telas", etc.)

    Uso:
        <x-produccion-proceso-header titulo="Planeación" />
        <x-produccion-proceso-header titulo="Tejido" subtitulo="Jacquard" />
        <x-produccion-proceso-header titulo="Configuración" subtitulo="Usuarios" />
--}}

@props([
    'titulo' => 'Producción en Proceso',
    'subtitulo' => null
])

<div class="text-center mb-4">
    <div class="bg-blue-600/50 rounded-2xl p-2 md:p-4 shadow-lg">
        <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">
            @if($subtitulo)
                {{ $titulo }} - {{ $subtitulo }}
            @else
                {{ $titulo }}
            @endif
        </h1>

    </div>
</div>
