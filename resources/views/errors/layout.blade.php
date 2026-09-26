{{--
    Layout de las páginas de error (UX-09). Autocontenido: no usa layouts/app (el error
    puede venir del propio layout, de la BD o de una ruta sin sesión), pero sí carga el CSS de
    Vite (antes estas vistas usaban clases de Tailwind sin cargarlo). Si el manifiesto de Vite
    no existe, la página sale igual con el estilo mínimo de abajo.

    "Volver al inicio" siempre va a /produccionProceso: con sesión es el inicio y sin ella
    el middleware auth lleva al login. "Regresar" solo aparece si hay una página anterior
    del mismo sitio.

    @section codigo, titulo, mensaje, extra (opcional), acciones (opcional)
    @section color  (clases del acento: botón y línea)
--}}
@php
    $color = trim($__env->yieldContent('color')) ?: 'blue';
    $acentos = [
        'blue' => ['linea' => 'bg-blue-500', 'boton' => 'bg-blue-600 hover:bg-blue-700', 'numero' => 'text-blue-700'],
        'red' => ['linea' => 'bg-red-500', 'boton' => 'bg-red-600 hover:bg-red-700', 'numero' => 'text-gray-800'],
        'orange' => ['linea' => 'bg-orange-500', 'boton' => 'bg-orange-600 hover:bg-orange-700', 'numero' => 'text-gray-800'],
        'amber' => ['linea' => 'bg-amber-500', 'boton' => 'bg-amber-600 hover:bg-amber-700', 'numero' => 'text-gray-800'],
    ];
    $acento = $acentos[$color] ?? $acentos['blue'];
    $inicio = url('/produccionProceso');
    $anterior = url()->previous();
    $mostrarRegresar = str_starts_with($anterior, url('/').'/') && $anterior !== url()->current() && $anterior !== $inicio && $anterior !== url('/');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') · Towell</title>
    {!! rescue(fn () => app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css'])->toHtml(), '', false) !!}
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="max-w-md mx-auto px-6 py-10 text-center">
        <div class="mb-8">
            @hasSection('imagen')
                @yield('imagen')
            @else
                <picture>
                    <source srcset="{{ asset('images/fotos_usuarios/TOWELLIN.webp') }}" type="image/webp">
                    <img src="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}" alt="" width="307" height="391" decoding="async" class="h-24 w-auto mx-auto">
                </picture>
            @endif
        </div>

        <p class="text-6xl font-light {{ $acento['numero'] }} mb-2" aria-hidden="true">@yield('codigo')</p>
        <div class="w-16 h-1 {{ $acento['linea'] }} mx-auto mb-8"></div>

        <h1 class="text-xl font-medium text-gray-700 mb-4">@yield('titulo')</h1>
        <p class="text-gray-600 text-sm leading-relaxed">@yield('mensaje')</p>
        @yield('extra')

        <div class="mt-10 mb-8 flex flex-wrap items-center justify-center gap-3">
            @hasSection('acciones')
                @yield('acciones')
            @else
                <a href="{{ $inicio }}"
                   class="inline-flex items-center justify-center min-h-touch {{ $acento['boton'] }} text-white px-8 py-3 rounded-md font-medium text-sm transition-colors duration-200">
                    Volver al inicio
                </a>
            @endif
            @if($mostrarRegresar)
                <a href="{{ $anterior }}"
                   class="inline-flex items-center justify-center min-h-touch bg-white text-gray-700 border border-gray-300 px-8 py-3 rounded-md font-medium text-sm hover:bg-gray-50 transition-colors duration-200">
                    Regresar
                </a>
            @endif
        </div>

        <p class="text-gray-500 text-xs">TOWEL S.A. de C.V.</p>
    </main>
</body>
</html>
