<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Towell</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/jsqr/dist/jsQR.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .font-inter {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="images-base" content="{{ asset('images') }}">
</head>

<body class="bg-white h-screen overflow-hidden font-inter">
    <div class="h-screen flex overflow-hidden">
        <!-- Panel izquierdo - Branding -->
        <div class="hidden lg:flex lg:w-2/5 relative overflow-hidden bg-blue-600">
            <div class="relative z-10 p-12 h-full flex flex-col justify-center text-white">
                <div class="text-center mb-8">
                    <div class="flex justify-center mb-10">
                        <img src="images/fotos_usuarios/TOWELLIN.png" alt="Logo" class="w-40 h-40">
                    </div>
                    <div class="mt-20">
                        <h1 class="text-white text-5xl font-bold leading-tight mb-6">Bienvenido</h1>
                        <p class="text-xl text-white text-opacity-90 leading-relaxed">
                            Accede a tu cuenta de forma rápida y segura con nuestro sistema de autenticación dual
                        </p>
                    </div>
                </div>
                <div class="mt-auto text-sm text-white text-center text-opacity-70">
                    <p>© 2025 Towell. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>

        <!-- Panel derecho - Formulario -->
        <div class="w-full lg:w-3/5 flex flex-col items-center justify-center p-8 lg:p-12 overflow-hidden h-screen">
            <div class="text-center w-full flex-shrink-0 order-first">
                <img src="{{ asset('images/fondosTowell/logo.png') }}" class="h-20 mx-auto" alt="Logo_Towell">
            </div>

            <div class="w-full max-w-2xl bg-white rounded-2xl p-10 shadow-sm">
                <!-- Tabs de autenticación -->
                <div class="flex bg-slate-100 rounded-lg p-1 mb-8 gap-0">
                    <button type="button" id="qr-tab" class="flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 text-slate-600 bg-transparent">
                        <svg fill="currentColor" viewBox="0 0 20 20" class="w-4 h-4">
                            <path d="M3 3h7v7H3V3zm9 0h7v7h-7V3zm-9 9h7v7H3v-7zm15 0h3v3h-3v-3zm-3-9h3v3h-3V3zm3 6h3v3h-3V9zm-9 6h3v3h-3v-3zm6 0h3v3h-3v-3zm-3 0h3v3h-3v-3z"/>
                        </svg>
                        Código QR
                    </button>
                    <button type="button" id="user-tab" class="flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 text-white bg-blue-600 shadow-sm">
                        <svg fill="currentColor" viewBox="0 0 20 20" class="w-4 h-4">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        ID Usuario
                    </button>
                </div>

                <!-- Formulario de ID Usuario -->
                <div id="user-form" class="block">
                    <x-login-form
                        title="Acceso por ID"
                        subtitle="Ingresa tu identificador único"
                        :errors="$errors ?? []"
                    />
                </div>

                <!-- Formulario de QR -->
                <div id="qr-form" class="hidden">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900 mb-2">Acceso por QR</h2>
                        <p class="text-slate-600">Escanea tu código QR para acceder</p>
                    </div>

                    <x-action-button
                        onclick="openQRModal('qr-video-container')"
                        variant="primary"
                        size="lg"
                        fullWidth="true"
                    >
                        Escanear Código QR
                    </x-action-button>
                </div>


            </div>
        </div>
    </div>

    <!-- Modal de QR usando componente -->
    <x-qr-modal id="qr-video-container" title="Escanea tu código..." />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js"></script>

    <script>
        // Variables globales
        let currentAuthMode = 'user';
        let stream = null;
        let interval = null;

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
        });

        // Manejo de tabs
        function initializeTabs() {
            const qrTab = document.getElementById('qr-tab');
            const userTab = document.getElementById('user-tab');
            const qrForm = document.getElementById('qr-form');
            const userForm = document.getElementById('user-form');

            qrTab.addEventListener('click', () => switchToTab('qr'));
            userTab.addEventListener('click', () => switchToTab('user'));
        }

        function switchToTab(mode) {
            const qrTab = document.getElementById('qr-tab');
            const userTab = document.getElementById('user-tab');
            const qrForm = document.getElementById('qr-form');
            const userForm = document.getElementById('user-form');

            currentAuthMode = mode;

            if (mode === 'qr') {
                qrTab.className = 'flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 text-white bg-blue-600 shadow-sm';
                userTab.className = 'flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 text-slate-600 bg-transparent';
                qrForm.classList.remove('hidden');
                userForm.classList.add('hidden');
            } else {
                userTab.className = 'flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 text-white bg-blue-600 shadow-sm';
                qrTab.className = 'flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 text-slate-600 bg-transparent';
                userForm.classList.remove('hidden');
                qrForm.classList.add('hidden');
            }
        }

        // Función global para abrir modal QR
        window.openQRModal = function(modalId) {
            if (window.qrScanners && window.qrScanners[modalId]) {
                window.qrScanners[modalId].start();
            }
        };

        // Función global para cerrar modal QR
        window.closeQRModal = function(modalId) {
            if (window.qrScanners && window.qrScanners[modalId]) {
                window.qrScanners[modalId].stop();
            }
        };
    </script>

    <!-- Script para recarga de página -->
    <script>
        // Detecta si esta página fue accedida desde el historial (adelante o atrás)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                window.location.reload(); // Fuerza recarga completa
            }
        });
    </script>

</body>

</html>
