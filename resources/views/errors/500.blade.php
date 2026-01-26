<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del Servidor - TOWEL S.A DE C.V</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto px-6 text-center">
        <!-- Logo TOWELLIN -->
        <div class="mb-12">
            <img src="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}" alt="TOWELLIN" class="h-24 mx-auto">
        </div>

        <!-- Número 500 -->
        <div class="mb-8">
            <h1 class="text-6xl font-light text-gray-800 mb-2">
                500
            </h1>
            <div class="w-16 h-1 bg-orange-500 mx-auto"></div>
        </div>

        <!-- Mensaje principal -->
        <div class="mb-12">
            <h2 class="text-xl font-medium text-gray-700 mb-4">
                Error del servidor
            </h2>
            <p class="text-gray-500 text-sm leading-relaxed">
                Algo salió mal en nuestro servidor. Nuestro equipo técnico ha sido notificado.
            </p>
        </div>

        <!-- Botones de acción -->
        <div class="mb-8 space-x-4">
            <a href="{{ url('/') }}"
               class="inline-block bg-orange-500 text-white px-8 py-3 rounded-md font-medium text-sm hover:bg-orange-600 transition-colors duration-200">
                Volver al inicio
            </a>
            <button onclick="window.location.reload()"
               class="inline-block bg-gray-500 text-white px-8 py-3 rounded-md font-medium text-sm hover:bg-gray-600 transition-colors duration-200">
                Reintentar
            </button>
        </div>

        <!-- Información de contacto -->
        <div class="text-gray-400 text-xs">
            <p>TOWEL S.A DE C.V</p>
        </div>
    </div>
</body>
</html>
