<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin conexión - Towell</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-blue-300 flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center">
        <picture>
            <source srcset="{{ asset('images/fotos_usuarios/towelin404.webp') }}" type="image/webp">
            <img src="{{ asset('images/fotos_usuarios/towelin404.png') }}" alt="Sin conexión" width="700" height="906" decoding="async" class="h-56 w-auto mx-auto mb-6">
        </picture>
        <h1 class="text-3xl font-bold text-white mb-4">Sin conexión</h1>
        <p class="text-white mb-6">No hay conexión a internet disponible.</p>
        <button onclick="window.location.href='{{ url('/produccionProceso') }}'" class="bg-white text-blue-500 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transition-colors">
            Intentar nuevamente
        </button>
    </div>
</body>
</html>
