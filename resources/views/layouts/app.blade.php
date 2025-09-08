<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'TOWELL S.A DE C.V')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Agregar Axios desde el CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <!-- jQuery (debe ir primero) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Animate.css (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" />
    {{-- Chart.js (CDN) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Estilos personalizados -->
    <style>
        body {
            background: linear-gradient(135deg, #099ff6, #c2e7ff, #0857be);
            background-size: 300% 300%;
            animation: gradientAnimation 5s ease infinite;
            position: relative;
            overflow: hidden;
            /* Para que los círculos no se salgan del body */
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
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
    @stack('styles') <!-- Aquí se inyectarán los estilos agregados con @push('styles') -->
    </head>

    <body class="min-h-screen flex flex-col">
        <!-- Incluir el loader global -->
        @include('layouts.globalLoader')

        <a href="/chatbot" class="text-3xl font-extrabold">
            <img src="{{ asset('images/fondosTowell/TOWELLIN.png') }} " alt="Towelling"
                class="absolute top-1 right-2 w-[36px] z-1">
        </a>

        @if (Route::currentRouteName() === 'produccion.index')
            <a href="#" id="logout-btn" class="absolute top-1 right-[1000px] z-1 btn btn-warning text-xs">
                CERRAR SESIÓN
            </a>

            <div class="relative z-1" style="position: absolute; left: 450px;">
                <button id="btnUsuarios"
                    class="mt-[5px] bg-orange-500 text-black font-bold px-4 py-1 rounded-md shadow hover:bg-orange-700 transition-all duration-200 cursor-pointer text-xs">
                    USUARIOS
                </button>
                <div id="menuUsuarios"
                    class="hidden absolute bg-white border border-gray-300 mt-1 w-40 rounded-md shadow-lg z-[99] transition transform scale-95 opacity-0"
                    style="left: 0px;">
                    <a href="{{ route('usuarios.create') }}"
                        class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100 bg-green-200 font-bold">
                        ALTA 📝</a>
                    <a href="{{ route('usuarios.select') }}"
                        class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100 bg-blue-300 font-bold">
                        VER USUARIOS 👥</a>
                </div>
            </div>
        @endif



        <!-- El siguiente if, es para injertar un titulo en la parte de app.balde, esto por solicitud del jefazo, solo funciona en la pagina de informacion del modulo de urdido-->
        @if (Route::currentRouteName() === 'produccion.ordenTrabajo')
            <h2 class="fixed top-[5px] left-[310px] z-50 -translate-x-1/2 px-4 py-2 text-xl md:text-2xl font-extrabold bg-transparent pointer-events-none select-none"
                style="
            background: transparent;
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
            background-image: linear-gradient(90deg, #3b82f6 10%, #60a5fa 50%, #2563eb 90%);
        ">
                PRODUCCIÓN DE URDIDO
            </h2>
        @endif


        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>

        <script>
            document.getElementById('logout-btn').addEventListener('click', function(event) {
                event.preventDefault();
                Swal.fire({
                    title: '¿CONFIRMA PARA CERRAR SESIÓN?',
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
        </script>


        <!-- Nombre del usuario -->
        <p class="hidden md:block nombreApp text-black font-bold uppercase text-xs">
            {{ Auth::user()->nombre }}
        </p>

        <a href="{{ route('telares.falla') }}"
            class="absolute top-1 right-20 z-1 btn btn-danger text-sm  focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600"
            style="font-family: 'Playfair Display', serif;">⚠️
            Notificar
            Falla ⚠️</a>
        <!-- Navbar -->
        <nav class="bg-blue-350 text-white ">
            <div class="container mx-auto flex justify-between items-center relative">
                <!-- Logo Towell -->
                <a href="/produccionProceso" class="text-3xl font-extrabold">
                    <img src="{{ asset('images/fondosTowell/logo_towell2.png') }} " alt="Logo_Towell"
                        class="absolute top-1 left-2 w-[120px] z-1 no-print">
                </a>

                @yield('menu-planeacion')

                @if (!isset($ocultarBotones) || !$ocultarBotones)
                    <!-- Botones de navegación -->
                    <div
                        class="flex gap-4 justify-center items-center z-5 botonesApp  md:gap-4 md:items-center lg:flex-row lg:gap-6 lg:ml-0">
                        <!-- Botón Atrás -->
                        <button onclick="history.back()"
                            class="bg-white text-blue-500 font-bold py-2 px-4 rounded-lg shadow-md hover:bg-gray-300 transition duration-300 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polygon points="11 19 2 12 11 5 11 19"></polygon>
                                <polygon points="22 19 13 12 22 5 22 19"></polygon>
                            </svg>
                        </button>

                        <!-- Botón Adelante -->
                        <button onclick="history.forward()"
                            class="bg-white text-blue-500 font-bold py-2 px-4 rounded-lg shadow-md hover:bg-gray-300 transition duration-300 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polygon points="13 19 22 12 13 5 13 19"></polygon>
                                <polygon points="2 19 11 12 2 5 2 19"></polygon>
                            </svg>
                        </button>
                    </div>
                @endif

            </div>
        </nav>

        <!-- Contenido de la página -->
        <main class="">
            @yield('content')
            <!-- JavaScript para mostrar/ocultar el loader -->
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white text-center p-3 mt-16">
            &copy; Towell {{ date('Y') }}. Todos los derechos reservados.
            <!-- Nombre del usuario -->
            <p class=" sm:block md:hidden text-white font-bold uppercase text-sm"> {{ Auth::user()->nombre }}</p>
        </footer>

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
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    }
                });

                // También cerrar al hacer clic en una opción
                menu.querySelectorAll("a").forEach(link => {
                    link.addEventListener("click", () => {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    });
                });
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


    </body>

    </html>
