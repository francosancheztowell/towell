<!DOCTYPE html>
<html lang="es">
<head>
    <x-layout-head title="Sistema Towell" :simple="true" />
    <x-layout-styles :simple="true" />
    <x-layout-scripts :simple="true" />
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
