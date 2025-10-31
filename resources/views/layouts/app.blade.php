<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TOWELL S.A DE C.V')</title>

  <!-- Preload -->
    <link rel="preload" as="image" href="{{ asset('images/fondosTowell/logo.png') }}">
    @if(file_exists(public_path('images/fotos_usuarios/TOWELLIN.png')))
    <link rel="preload" as="image" href="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}">
    @endif

  <!-- Optimización imágenes -->
    <style>
    img[loading="lazy"]{
      background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);
      background-size:200% 100%;animation:loading 1.5s infinite
    }
    @keyframes loading{0%{background-position:200% 0}100%{background-position:-200% 0}}
    .module-grid img{will-change:transform;backface-visibility:hidden}
    </style>

  <!-- CSS/JS base -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        window.axios = axios;
    window.axios.defaults.headers.common['X-Requested-With']='XMLHttpRequest';
    const _csrf = document.head.querySelector('meta[name="csrf-token"]');
    if(_csrf){ window.axios.defaults.headers.common['X-CSRF-TOKEN'] = _csrf.content; }
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

  <!-- Limpieza SW antiguos -->
    <script>
    (function(){
      if(!('serviceWorker' in navigator)) return;
      async function cleanup(){ try{
        const regs=await navigator.serviceWorker.getRegistrations();
        for(const r of regs) await r.unregister();
        if('caches'in window){ for(const n of await caches.keys()) await caches.delete(n); }
        if(navigator.serviceWorker.controller){
          navigator.serviceWorker.controller.postMessage({action:'skipWaiting'});
        }
      }catch(e){}}
      cleanup();
      window.addEventListener('load',cleanup);
      document.addEventListener('visibilitychange',()=>{ if(!document.hidden) cleanup(); });
        })();
    </script>

  <!-- Tailwind extra -->
    <script>
    tailwind.config={ theme:{ extend:{} } }
    </script>

    <style>
    @keyframes ripple{0%{width:0;height:0}100%{width:300px;height:300px}}
    .ripple-effect:active::before{animation:ripple .6s ease-out}
    @keyframes spin360{to{transform:rotate(360deg)}}
    .spin-1s{animation:spin360 .9s linear 1;transform-origin:50% 50%}
    .animate-fade-in{animation:fadeIn .18s ease-out both}
    @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
    </style>

  @stack('styles') @push('styles')
    </head>

<!-- Optimización navegación simple -->
    <script>
  (function(){
    const path=location.pathname, prev=sessionStorage.getItem('lastNavbarPath');
    document.documentElement.setAttribute('data-navbar-loaded', prev===path ? 'true' : 'false');
    sessionStorage.setItem('lastNavbarPath', path);
    document.addEventListener('click',e=>{
      const link=e.target.closest('a[href]'); if(!link||link.target==='_blank') return;
      if(link.hostname!==location.hostname) return;
    });
    window.addEventListener('pageshow',()=>{});
        })();
    </script>

    <body class="min-h-screen flex flex-col overflow-x-hidden h-screen bg-gradient-to-b from-blue-400 to-blue-200 relative">
        @include('layouts.globalLoader')

  <!-- NAVBAR -->
        <nav class="bg-white sticky top-0 z-50">
            <div class="container mx-auto px-4 md:px-6 py-2">
                <div class="flex items-center justify-between">
        <!-- Izquierda -->
        <div class="flex items-center gap-2 md:gap-3">
            <button id="btn-back" class="opacity-0 invisible pointer-events-none w-8 h-8 md:w-10 md:h-10 flex items-center justify-center bg-blue-200 hover:bg-blue-400 text-black rounded-lg transition-all duration-200 shadow-md hover:shadow-lg active:scale-95"
                title="Volver atrás" aria-label="Volver atrás">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
            </button>

                    <a href="/produccionProceso" class="flex items-center">
                            <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Logo Towell" class="h-10 md:h-12">
                        </a>
                    </div>

        <!-- Centro -->
                    <div class="flex items-center gap-4">
                        @hasSection('page-title')
                            <h1 class="text-lg md:text-xl lg:text-2xl font-bold text-blue-600 animate-fade-in">
                                @yield('page-title')
                            </h1>
                        @endif
                        @yield('menu-planeacion')
        </div>

        <!-- Derecha -->
        <div class="flex items-center gap-4">
          <!-- Botones específicos para Telares -->
                        @if(request()->routeIs('planeacion.catalogos.telares') || request()->routeIs('telares.index'))
                            <x-action-buttons route="telares" :showFilters="true" />
                        @endif
                        @if(request()->routeIs('planeacion.catalogos.eficiencia') || request()->routeIs('eficiencia.index'))
                            <x-action-buttons route="eficiencia" :showFilters="true" />
                        @endif
                        @if(request()->routeIs('planeacion.catalogos.velocidad') || request()->routeIs('velocidad.index'))
                            <x-action-buttons route="velocidad" :showFilters="true" />
                        @endif
                        @if(request()->routeIs('planeacion.catalogos.calendarios') || request()->routeIs('calendarios.index'))
                            <x-action-buttons route="calendarios" :showFilters="true" />
                        @endif
                        @if(request()->routeIs('planeacion.catalogos.aplicaciones') || request()->routeIs('planeacion.aplicaciones'))
                            <x-action-buttons route="aplicaciones" :showFilters="true" />
                        @endif

          @if(request()->routeIs('catalogos.req-programa-tejido'))
            <div class="flex items-center gap-1">

              <!-- Controles de columnas -->
              <div class="flex items-center gap-1 mr-2">
                <button type="button" onclick="openPinColumnsModal()"
                        class="p-2 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-100 rounded-md transition-colors"
                        title="Fijar columnas">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15.5 3.5 20 8l-3 3 1 3-2 2-3-1-3 3-2-2 3-3-1-3 2-2 3 1 3-3Z"/>
                    <path d="M11 15l-4 4"/>
                  </svg>
                </button>
                <button type="button" onclick="openHideColumnsModal()"
                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors"
                        title="Ocultar columnas">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 11-4.243-4.243m4.242 4.242L9.88 9.88" />
                  </svg>
                </button>
                <button type="button" onclick="resetColumnVisibility()"
                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
                        title="Restablecer columnas">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>
              </div>

              <!-- Prioridad (solo si hay selección) -->
              <div id="rowPriorityControls" class="flex items-center gap-1 hidden">
                <!-- Subir (verde) -->
                <button type="button" onclick="moveRowUp()"
                        class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                        title="Subir prioridad" aria-label="Subir prioridad">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </button>
                <!-- Bajar (rojo) -->
                <button type="button" onclick="moveRowDown()"
                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors"
                        title="Bajar prioridad" aria-label="Bajar prioridad">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                    </div>

              <!-- Nuevo (plus-circle) -->
              <button type="button" onclick="abrirNuevo(); return false;"
                      class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
                      title="Nuevo registro" aria-label="Nuevo registro">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v8m4-4H8m12 0a8 8 0 11-16 0 8 8 0 0116 0z"/>
                </svg>
              </button>

              <!-- Catálogos (grid) -->
              <a href="/submodulos-nivel3/104"
                 class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
                 title="Catálogos" aria-label="Catálogos">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
                </svg>
              </a>

              <!-- Filtros (reactivo) -->
              <button type="button" id="btnFilters"
                      class="relative p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
                      title="Filtros" aria-label="Filtros">
                <!-- Icono de filtro (siempre visible) -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/>
                </svg>
                <!-- Badge con número (solo cuando hay filtros activos) -->
                <span id="filterCount"
                      class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs font-bold hidden">0</span>
              </button>
            </div>
          @endif

                        @yield('navbar-right')

          @if(!request()->routeIs('catalogos.req-programa-tejido'))
            <button href="{{ route('planeacion.telares.falla') }}"
                    class="bg-yellow-400 hover:bg-yellow-500 flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            Paro
                        </button>
          @endif

                        @if (Route::currentRouteName() === 'produccion.index')
            <button id="logout-btn"
                    class="flex items-center gap-1 px-2 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-700 rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Salir
                            </button>
                        @endif

                        @if(isset($tieneConfiguracion) && $tieneConfiguracion)
            <a href="{{ route('configuracion.index') }}"
               class="w-10 h-10 bg-blue-100 hover:bg-blue-200 rounded-full flex items-center justify-center text-blue-800 hover:text-blue-900 transition-all duration-200 shadow-sm hover:shadow-md"
               title="Configuración">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </a>
                        @endif

          <!-- Usuario -->
                        <div class="relative">
                            @php
                                $usuario = Auth::user();
                                $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
                            @endphp
            <button id="btn-user-avatar" class="w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 overflow-hidden">
                                @if($fotoUrl)
                <img src="{{ $fotoUrl }}" alt="Foto de {{ $usuario->nombre }}" class="w-full h-full object-cover">
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

  <!-- Modal usuario -->
        <div id="user-modal" class="fixed top-16 right-4 max-w-[calc(100vw-2rem)] w-72 bg-white rounded-lg shadow-lg border border-gray-200 z-50 opacity-0 invisible scale-95 transition-all duration-200 origin-top-right">
            <div class="p-4">
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

                <div class="space-y-2 text-sm">
                    @if(isset($usuario->area) && $usuario->area)
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span class="text-gray-600 truncate">{{ $usuario->area }}</span>
                    </div>
                    @endif

                    @if(isset($usuario->turno) && $usuario->turno)
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-gray-600">Turno {{ $usuario->turno }}</span>
                    </div>
                    @endif

                    @if(isset($usuario->correo) && $usuario->correo)
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span class="text-gray-600 truncate">{{ $usuario->correo }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">@csrf</form>

        <main class="overflow-x-hidden max-w-full">
            @yield('content')
        </main>

  <!-- ====== Modal de Filtros (simple) ====== -->
  <div id="filtersModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white w-full max-w-lg rounded-xl shadow-lg overflow-hidden animate-fade-in">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">Filtros</h3>
        <button class="p-2 rounded-md hover:bg-gray-100" onclick="closeFilterModal()" aria-label="Cerrar">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="p-4 space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-500">Salón</label>
            <select id="f_salon" class="form-select w-full border-gray-300 rounded-md">
              <option value="">(Todos)</option>
              <option value="Jacquard">Jacquard</option>
              <option value="Sulzer">Sulzer</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-gray-500">Telar</label>
            <input id="f_telar" type="text" class="form-control w-full border-gray-300 rounded-md" placeholder="Ej. 201">
          </div>
          <div class="col-span-2">
            <label class="text-xs text-gray-500">Producto</label>
            <input id="f_producto" type="text" class="form-control w-full border-gray-300 rounded-md" placeholder="Buscar…">
          </div>
        </div>
      </div>

      <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <button id="btnResetFilters"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 transition-all"
                onclick="resetFiltersSpin()" title="Restablecer">
          <svg id="iconReset" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v6h6M20 20v-6h-6M20 9a7 7 0 10-3.29 5.98"/>
          </svg>
          Restablecer
        </button>

        <div class="flex items-center gap-2">
          <button class="px-3 py-2 text-sm font-medium rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700"
                  onclick="closeFilterModal()">Cancelar</button>
          <button class="px-3 py-2 text-sm font-medium rounded-md bg-blue-600 hover:bg-blue-700 text-white"
                  onclick="confirmFilters()">Aplicar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ====== Scripts ====== -->
        <script>
    // Logout modal
    (function(){
      const btn = document.getElementById('logout-btn');
      if(!btn) return;
      btn.addEventListener('click', function(e){
        e.preventDefault();
        Swal.fire({
          title:'¿Confirma cerrar sesión?',icon:'warning',
          showCancelButton:true,confirmButtonColor:'#3085d6',cancelButtonColor:'#d33',
          confirmButtonText:'Sí, salir',cancelButtonText:'Cancelar'
        }).then(res=>{ if(res.isConfirmed) document.getElementById('logout-form').submit(); });
      });
    })();

    // Botón atrás
    document.addEventListener('DOMContentLoaded', function(){
      const btnBack = document.getElementById('btn-back');
      const homePath = '/produccionProceso';
      if(btnBack && location.pathname !== homePath){
        btnBack.classList.remove('opacity-0','invisible','pointer-events-none');
        btnBack.classList.add('flex','opacity-100','visible');
        btnBack.addEventListener('click', function(){
          if (history.length > 1 && document.referrer){ history.back(); }
          else { location.href = homePath; }
        });
      }
      // Loader off
      const loader = document.getElementById('globalLoader');
      if(loader) loader.style.display='none';
    });

    // Menú usuario compacto
    (function(){
      const btn = document.getElementById('btn-user-avatar');
      const modal = document.getElementById('user-modal');
      let open=false;
      function show(e){ e&&e.stopPropagation(); modal.classList.remove('opacity-0','invisible','scale-95'); modal.classList.add('opacity-100','visible','scale-100'); open=true; }
      function hide(){ modal.classList.remove('opacity-100','visible','scale-100'); modal.classList.add('opacity-0','invisible','scale-95'); open=false; }
      if(btn && modal){
        btn.addEventListener('click',e=> open ? hide() : show(e));
        document.addEventListener('click',e=>{ if(open && !modal.contains(e.target) && !btn.contains(e.target)) hide(); });
        document.addEventListener('keydown',e=>{ if(e.key==='Escape' && open) hide(); });
      }
    })();

    // Persistencia UI simple
    window.addEventListener('pageshow', function(){
                if (sessionStorage.getItem('forceReload')) {
                    sessionStorage.removeItem('forceReload');
                    location.reload();
                }
            });

    // Toastr base
            toastr.options = {
      closeButton:true, debug:false, newestOnTop:true, progressBar:true,
      positionClass:"toast-top-right", preventDuplicates:false, showDuration:"300",
      hideDuration:"1000", timeOut:"5000", extendedTimeOut:"1000",
      showEasing:"swing", hideEasing:"linear", showMethod:"fadeIn", hideMethod:"fadeOut"
    };

    // ====== Filtros reactivos ======
    let filtersState = { active:false, count:0, values:{} };

    const $btnFilters = document.getElementById('btnFilters');
    if($btnFilters){
      $btnFilters.addEventListener('click', function(){
        if(filtersState.active){ clearFilters(); } else { openFilterModal(); }
      });
    }

    function openFilterModal(){
      const m = document.getElementById('filtersModal'); if(!m) return;
      m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeFilterModal(){
      const m = document.getElementById('filtersModal'); if(!m) return;
      m.classList.remove('flex'); m.classList.add('hidden');
    }
    function confirmFilters(){
      const v = {
        salon: (document.getElementById('f_salon')||{}).value || '',
        telar: (document.getElementById('f_telar')||{}).value || '',
        producto: (document.getElementById('f_producto')||{}).value || ''
      };
      const count = Object.values(v).filter(x=> (x??'').toString().trim()!=='').length;
      filtersState.values = v;
      applyFilters(count);
      // TODO: integra tu refresh real aquí, p.ej.: refreshTablaConFiltros(v);
      closeFilterModal();
    }
    function applyFilters(count){
      filtersState.active = count>0;
      filtersState.count  = count;
      updateFilterUI();
    }
    function clearFilters(){
      filtersState = {active:false,count:0,values:{}};
      updateFilterUI();
      // limpiar controles (opcional)
      ['f_salon','f_telar','f_producto'].forEach(id=>{
        const el=document.getElementById(id); if(el){ if(el.tagName==='SELECT'){ el.selectedIndex=0; } else { el.value=''; } }
      });
      // TODO: integra tu limpieza real, p.ej.: refreshTablaConFiltros({});
    }
    function updateFilterUI(){
      const badge= document.getElementById('filterCount');
      const btn  = document.getElementById('btnFilters');
      if(!badge||!btn) return;
      if(filtersState.active){
        badge.textContent = String(filtersState.count);
        badge.classList.remove('hidden');
        btn.classList.add('bg-blue-50','ring-1','ring-blue-300');
      }else{
        badge.classList.add('hidden');
        btn.classList.remove('bg-blue-50','ring-1','ring-blue-300');
      }
    }
    function resetFiltersSpin(){
      const icon=document.getElementById('iconReset'); if(!icon) return;
      icon.classList.add('spin-1s'); setTimeout(()=>icon.classList.remove('spin-1s'),900);
      clearFilters();
    }
    // Exponer si ocupas desde Blade/otros scripts
    window.openFilterModal = openFilterModal;
    window.applyFilters   = applyFilters;
    window.clearFilters   = clearFilters;
    window.confirmFilters = confirmFilters;
    window.resetFiltersSpin = resetFiltersSpin;
        </script>

  <script src="{{ asset('js/simple-click-sounds.js') }}"></script>
    </body>
    </html>
