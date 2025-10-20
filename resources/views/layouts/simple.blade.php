<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema Towell')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Estilos personalizados -->
    <style>
        body {
            background: linear-gradient(135deg, #099ff6, #c2e7ff, #0857be);
            background-size: 300% 300%;
            animation: gradientAnimation 5s ease infinite;
            position: relative;
        }

        /* Animación del fondo */
        @keyframes gradientAnimation {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Navbar Simple -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo Towell -->
                <a href="/produccionProceso" class="flex items-center">
                    <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Logo Towell" class="h-10">
                </a>

                <!-- Botones de acción -->
                <div class="flex items-center gap-4">
                    @yield('menu-planeacion')
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="container mx-auto px-6 py-8">
        @yield('content')
    </main>

    <!-- Scripts adicionales -->
    @yield('scripts')
</body>
</html>
