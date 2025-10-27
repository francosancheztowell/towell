<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'TOWELL S.A DE C.V')</title>

    <!-- Preload de imágenes críticas para mejor rendimiento -->
    <link rel="preload" as="image" href="{{ asset('images/fondosTowell/logo.png') }}">
    @if(file_exists(public_path('images/fotos_usuarios/TOWELLIN.png')))
    <link rel="preload" as="image" href="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}">
    @endif

    <!-- Optimización de imágenes -->
    <style>
        /* Placeholder mientras carga la imagen */
        img[loading="lazy"] {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Optimización para imágenes de módulos */
        .module-grid img {
            will-change: transform;
            backface-visibility: hidden;
        }
    </style>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Agregar Axios desde el CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <!-- Configuración de Axios para CSRF -->
    <script>
        // Configurar Axios para incluir automáticamente el token CSRF
        window.axios = axios;
        window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

        // Obtener el token CSRF del meta tag
        const token = document.head.querySelector('meta[name="csrf-token"]');
        if (token) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
        } else {
            console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
        }
    </script>

    <!-- jQuery (debe ir primero) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Toastr para notificaciones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Animate.css (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" />
    {{-- Chart.js (CDN) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Desregistrar y limpiar Service Workers antiguos -->
    <script>
        (function() {
            // Ejecutar INMEDIATAMENTE, antes de que cargue cualquier cosa
            console.log('[ServiceWorker Cleanup] Iniciando limpieza...');

            if ('serviceWorker' in navigator) {
                // Función para limpiar todo
                async function cleanupServiceWorkers() {
                    try {
                        // 1. Obtener todos los registros
                        const registrations = await navigator.serviceWorker.getRegistrations();
                        console.log('[ServiceWorker] Encontrados', registrations.length, 'registros');

                        // 2. Desregistrar cada uno
                        for (const registration of registrations) {
                            const success = await registration.unregister();
                            console.log('[ServiceWorker] Desregistrado:', success);
                        }

                        // 3. Limpiar caché
                        if ('caches' in window) {
                            const cacheNames = await caches.keys();
                            console.log('[ServiceWorker] Encontrados', cacheNames.length, 'caches');

                            for (const name of cacheNames) {
                                await caches.delete(name);
                                console.log('[ServiceWorker] Cache eliminado:', name);
                            }
                        }

                        // 4. Forzar actualización
                        if (navigator.serviceWorker.controller) {
                            navigator.serviceWorker.controller.postMessage({action: 'skipWaiting'});
                        }

                        console.log('[ServiceWorker Cleanup] ✅ Limpieza completada');
                    } catch (error) {
                        console.error('[ServiceWorker Cleanup] ❌ Error:', error);
                    }
                }

                // Ejecutar ahora
                cleanupServiceWorkers();

                // También ejecutar al cargar la página
                window.addEventListener('load', cleanupServiceWorkers);

                // Ejecutar cuando la página está activa
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden) {
                        cleanupServiceWorkers();
                    }
                });
            } else {
                console.log('[ServiceWorker] No disponible en este navegador');
            }
        })();
    </script>

    <!-- Configuración de Tailwind para animación personalizada -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'gradientAnimation': 'gradientAnimation 5s ease infinite',
                    },
                    keyframes: {
                        gradientAnimation: {
                            '0%, 100%': { 'background-position': '0% 50%' },
                            '50%': { 'background-position': '100% 50%' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Efecto ripple - requiere ::before dinámico */
        @keyframes ripple {
            0% {
                width: 0;
                height: 0;
            }
            100% {
                width: 300px;
                height: 300px;
            }
        }

        .ripple-effect:active::before {
            animation: ripple 0.6s ease-out;
        }
    </style>
    @stack('styles')  @push('styles')
    </head>

    <!-- Script para optimizar la navegación y evitar recargas innecesarias -->
    <script>
        // Guardar el estado de la navbar en sessionStorage
        (function() {
            const currentPath = window.location.pathname;
            const storedPath = sessionStorage.getItem('lastNavbarPath');

            // Si la ruta ya está cargada, marcar la navbar como persistente
            if (storedPath === currentPath) {
                document.documentElement.setAttribute('data-navbar-loaded', 'true');
            } else {
                sessionStorage.setItem('lastNavbarPath', currentPath);
                document.documentElement.setAttribute('data-navbar-loaded', 'false');
            }

            // Detectar cambios de navegación
            let isNavigating = false;

            document.addEventListener('click', function(e) {
                const link = e.target.closest('a[href]');
                if (!link || link.target === '_blank') return;

                // Ignorar links externos
                if (link.hostname !== window.location.hostname) return;

                const href = link.getAttribute('href');

                // Si es un link diferente al actual, marcar como navegando
                if (href && href !== '#' && href !== window.location.pathname) {
                    isNavigating = true;
                }
            });

            // Limpiar el flag cuando la navegación se complete
            window.addEventListener('pageshow', function(e) {
                isNavigating = false;
            });
        })();
    </script>

    <body class="min-h-screen flex flex-col overflow-x-hidden h-screen bg-gradient-to-br from-blue-500 via-blue-200 to-blue-700 bg-[length:300%_300%] animate-gradientAnimation relative">
        <!-- Incluir el loader global -->
        @include('layouts.globalLoader')

        <!-- Navbar Blanco Minimalista -->
        <nav class="bg-white border-gray-200 sticky top-0 z-50 shadow-sm">
            <div class="container mx-auto px-4 md:px-6 py-3">
                <div class="flex items-center justify-between">
                    <!-- Sección Izquierda: Botón Atrás + Logo -->
                    <div class="flex items-center gap-2 md:gap-3">
                        <!-- Botón Atrás -->
                        <button id="btn-back" class="opacity-0 invisible pointer-events-none w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-blue-200 hover:bg-blue-400 text-black rounded-lg transition-all duration-200 shadow-md hover:shadow-lg active:scale-95 touch-manipulation" title="Volver atrás">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Logo Towell -->
                        <a href="/produccionProceso" class="flex items-center">
                            <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Logo Towell" class="h-10 md:h-12">
                        </a>
                    </div>

                    <!-- Sección central -->
                    <div class="flex items-center gap-4">
                        <!-- Título dinámico -->
                        @hasSection('page-title')
                            <h1 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-800 animate-fade-in">
                                @yield('page-title')
                            </h1>
                        @endif
                        @yield('menu-planeacion')

                        <!-- Botones de acción para catálogo de telares -->
                        @if(request()->routeIs('planeacion.catalogos.telares') || request()->routeIs('telares.index'))
                            <x-action-buttons route="telares" :showFilters="true" />
                        @endif

                        <!-- Botones de acción para catálogo de eficiencia -->
                        @if(request()->routeIs('planeacion.catalogos.eficiencia') || request()->routeIs('eficiencia.index'))
                            <x-action-buttons route="eficiencia" :showFilters="true" />
                        @endif

                        <!-- Botones de acción para catálogo de velocidad -->
                        @if(request()->routeIs('planeacion.catalogos.velocidad') || request()->routeIs('velocidad.index'))
                            <x-action-buttons route="velocidad" :showFilters="true" />
                        @endif

                        <!-- Botones de acción para calendarios -->
                        @if(request()->routeIs('planeacion.catalogos.calendarios') || request()->routeIs('calendarios.index'))
                            <x-action-buttons route="calendarios" :showFilters="true" />
                        @endif

                        <!-- Botones de acción para aplicaciones -->
                        @if(request()->routeIs('planeacion.catalogos.aplicaciones') || request()->routeIs('planeacion.aplicaciones'))
                            <x-action-buttons route="aplicaciones" :showFilters="true" />
                        @endif
                    </div>

                    <!-- Sección derecha (usuarios, notificaciones, perfil) -->
                    <div class="flex items-center gap-4">

                        @yield('navbar-right')

                        <!-- Notificar Falla con texto e ícono -->
                        <button href="{{ route('planeacion.telares.falla') }}" class="bg-yellow-400 hover:bg-yellow-500 flex items-center gap-2 px-3 py-2 text-sm font-medium  rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 " fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            Paro
                        </button>



                        <!-- Cerrar sesión -->
                        @if (Route::currentRouteName() === 'produccion.index')
                            <button id="logout-btn" class="flex items-center gap-1 px-2 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-700 rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Salir
                            </button>
                        @endif

                        <!-- Icono de configuración (solo si el usuario tiene permisos) -->
                        @if(isset($tieneConfiguracion) && $tieneConfiguracion)
                            <a href="{{ route('configuracion.index') }}" class="w-10 h-10 bg-blue-100 hover:bg-blue-200 rounded-full flex items-center justify-center text-blue-800 hover:text-blue-900 transition-all duration-200 shadow-sm hover:shadow-md" title="Configuración">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </a>
                        @endif

                        <!-- Círculo de usuario -->
                        <div class="relative">
                            @php
                                $usuario = Auth::user();
                                $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
                            @endphp
                            <button id="btn-user-avatar" class="w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 overflow-hidden touch-manipulation">
                                @if($fotoUrl)
                                    <img src="{{ $fotoUrl }}" alt="Foto de {{ $usuario->nombre }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm md:text-base hover:from-blue-600 hover:to-blue-700">
                                        {{ strtoupper(substr($usuario->nombre, 0, 1)) }}
                                    </div>
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Modal Compacto de Usuario (estilo redes sociales) -->
        <div id="user-modal" class="fixed top-16 right-4 max-w-[calc(100vw-2rem)] w-72 bg-white rounded-lg shadow-lg border border-gray-200 z-50 opacity-0 invisible scale-95 transition-all duration-200 origin-top-right">
            <!-- Contenido del modal -->
            <div class="p-4">
                <!-- Avatar y nombre -->
                <div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0">
                        @if($fotoUrl)
                            <img src="{{ $fotoUrl }}" alt="Foto de {{ $usuario->nombre }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-base">
                                {{ strtoupper(substr($usuario->nombre, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-gray-900 text-sm truncate">{{ $usuario->nombre }}</h4>
                        <p class="text-xs text-gray-500">{{ $usuario->puesto ?? 'Usuario' }}</p>
                    </div>
                </div>

                <!-- Información compacta -->
                <div class="space-y-2 text-sm">
                    <!-- Puesto -->


                    <!-- Área -->
                    @if(isset($usuario->area) && $usuario->area)
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-gray-600 truncate">{{ $usuario->area }}</span>
                    </div>
                    @endif

                    <!-- Turno -->
                    @if(isset($usuario->turno) && $usuario->turno)
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-gray-600">Turno {{ $usuario->turno }}</span>
                    </div>
                    @endif

                    <!-- Correo -->
                    @if(isset($usuario->correo) && $usuario->correo)
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span class="text-gray-600 truncate">{{ $usuario->correo }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>

        <script>
            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    Swal.fire({
                        title: '¿Confirma cerrar sesión?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, salir',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.getElementById('logout-form').submit();
                        }
                    });
                });
            }
        </script>

        <!-- Script para el botón de atrás -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const btnBack = document.getElementById('btn-back');
                const currentPath = window.location.pathname;
                const homePath = '/produccionProceso';

                // Mostrar el botón solo si NO estamos en la página principal
                if (btnBack && currentPath !== homePath) {
                    // Hacer visible el botón con animación suave
                    btnBack.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
                    btnBack.classList.add('flex', 'opacity-100', 'visible');

                    // Funcionalidad del botón
                    btnBack.addEventListener('click', function() {
                        // Verificar si hay historial previo
                        if (window.history.length > 1 && document.referrer) {
                            window.history.back();
                        } else {
                            // Si no hay historial, ir a la página principal
                            window.location.href = homePath;
                        }
                    });
                }
            });
        </script>

        <!-- Script para el Modal de Usuario -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const btnAvatar = document.getElementById('btn-user-avatar');
                const modal = document.getElementById('user-modal');
                let isModalOpen = false;

                // Función para abrir el modal
                function openModal(e) {
                    e.stopPropagation();
                    if (modal) {
                        modal.classList.remove('opacity-0', 'invisible', 'scale-95');
                        modal.classList.add('opacity-100', 'visible', 'scale-100');
                        isModalOpen = true;
                    }
                }

                // Función para cerrar el modal
                function closeModal() {
                    if (modal) {
                        modal.classList.remove('opacity-100', 'visible', 'scale-100');
                        modal.classList.add('opacity-0', 'invisible', 'scale-95');
                        isModalOpen = false;
                    }
                }

                // Toggle modal al hacer clic en el avatar
                if (btnAvatar) {
                    btnAvatar.addEventListener('click', function(e) {
                        if (isModalOpen) {
                            closeModal();
                        } else {
                            openModal(e);
                        }
                    });
                }

                // Cerrar modal al hacer clic fuera
                document.addEventListener('click', function(e) {
                    if (isModalOpen && modal && !modal.contains(e.target) && !btnAvatar.contains(e.target)) {
                        closeModal();
                    }
                });

                // Cerrar modal con la tecla ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && isModalOpen) {
                        closeModal();
                    }
                });
            });
        </script>

        <!-- Contenido de la página -->
        <main class="overflow-x-hidden max-w-full">
            @yield('content')
            <!-- JavaScript para mostrar/ocultar el loader -->
        </main>


        <script>
            // Muestra el loader cuando la página empieza a cargar
            document.addEventListener('DOMContentLoaded', function() {
                const loader = document.getElementById('globalLoader');
                loader.style.display = 'none'; // Oculta el loader cuando la página se carga
            });
            // Puedes agregar más scripts para mostrar el loader durante eventos específicos (AJAX, formularios, etc.)
        </script>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const btn = document.getElementById("btnUsuarios");
                const menu = document.getElementById("menuUsuarios");

                // Verificar que los elementos existen antes de agregar event listeners
                if (!btn || !menu) {
                    return;
                }

                btn.addEventListener("click", function(e) {
                    e.stopPropagation(); // Evita que el evento se propague y se cierre de inmediato
                    const isOpen = !menu.classList.contains("hidden");

                    // Cerrar si ya está abierto
                    if (isOpen) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    } else {
                        menu.classList.remove("hidden");
                        // Forzar reflow para animación (truco CSS)
                        void menu.offsetWidth;
                        menu.classList.remove("scale-95", "opacity-0");
                        menu.classList.add("scale-100", "opacity-100");
                    }
                });

                // Ocultar el menú si haces clic fuera de él
                document.addEventListener("click", function(e) {
                    if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    }
                });

                // También cerrar al hacer clic en una opción
                if (menu) {
                    menu.querySelectorAll("a").forEach(link => {
                        link.addEventListener("click", () => {
                            menu.classList.add("hidden");
                            menu.classList.remove("scale-100", "opacity-100");
                            menu.classList.add("scale-95", "opacity-0");
                        });
                    });
                }
            });
        </script>

        {{-- FORZAMOS A RECARGAR la página --}}
        <script>
            window.addEventListener('pageshow', function() {
                if (sessionStorage.getItem('forceReload')) {
                    sessionStorage.removeItem('forceReload');
                    location.reload();
                }
            });
        </script>

        <!-- Script de sonidos para clicks -->
        <script src="{{ asset('js/simple-click-sounds.js') }}"></script>

        <!-- Configuración de Toastr -->
        <script>
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };
        </script>

    </body>

    </html>
