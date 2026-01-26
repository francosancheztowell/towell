<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página No Encontrada - TOWEL S.A DE C.V</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #c8e3ff 0%, #e2e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto px-6 text-center">
        <!-- Logo TOWELLIN -->
        <div class="mb-6">
            <img src="{{ asset('images/fotos_usuarios/towelin404.png') }}" alt="TOWELLIN" class="h-56 mx-auto">
        </div>

        <!-- Número 404 -->
        <div class="mb-6">
            <h1 class="text-8xl font-medium text-blue-700 mb-2">
                404
            </h1>
            <div class="w-16 h-1 bg-blue-500 mx-auto"></div>
        </div>

        <!-- Mensaje principal -->
        <div class="mb-12">
            <h2 class="text-xl font-medium text-gray-700 mb-4">
                Página en construcción
            </h2>
            <p class="text-gray-500 text-sm leading-relaxed">
                La página que buscas está en construcción.
            </p>
        </div>

        <!-- Botón de acción -->
        <div class="mb-8">
            <a href="{{ url('/produccionProceso') }}"
               class="inline-block bg-blue-500 text-white px-8 py-3 rounded-md font-medium text-sm hover:bg-blue-600 transition-colors duration-200">
                Volver al inicio
            </a>
        </div>

        <!-- Información de contacto -->
        <div class="text-gray-400 text-xs">
            <p>TOWEL S.A DE C.V</p>
        </div>
    </div>
</body>
</html>
