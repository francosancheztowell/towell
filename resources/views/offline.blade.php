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
        <img src="{{ asset('images/fotos_usuarios/towelin404.png') }}" alt="Sin conexión" class="h-56 mx-auto mb-6">
        <h1 class="text-3xl font-bold text-white mb-4">Sin conexión</h1>
        <p class="text-white mb-6">No hay conexión a internet disponible.</p>
        <button onclick="window.location.href='{{ url('/produccionProceso') }}'" class="bg-white text-blue-500 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transition-colors">
            Intentar nuevamente
        </button>
    </div>
</body>
</html>
