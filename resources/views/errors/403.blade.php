<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado - TOWELL S.A DE C.V</title>
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

        <!-- Número 403 -->
        <div class="mb-8">
            <h1 class="text-6xl font-light text-gray-800 mb-2">
                403
            </h1>
            <div class="w-16 h-1 bg-red-500 mx-auto"></div>
        </div>

        <!-- Mensaje principal -->
        <div class="mb-12">
            <h2 class="text-xl font-medium text-gray-700 mb-4">
                Acceso denegado
            </h2>
            <p class="text-gray-500 text-sm leading-relaxed">
                No tienes permisos para acceder a esta página.
            </p>
        </div>

        <!-- Botón de acción -->
        <div class="mb-8">
            <a href="{{ url('/') }}"
               class="inline-block bg-red-500 text-white px-8 py-3 rounded-md font-medium text-sm hover:bg-red-600 transition-colors duration-200">
                Volver al inicio
            </a>
        </div>

        <!-- Información de contacto -->
        <div class="text-gray-400 text-xs">
            <p>TOWELL S.A DE C.V</p>
        </div>
    </div>
</body>
</html>
