<!-- NAVBAR -->
<nav class="bg-white sticky top-0 z-50">
    <div class="container mx-auto px-4 md:px-6 py-2">
        <div class="flex items-center justify-between">
            <!-- Izquierda -->
            <div class="flex items-center gap-2 md:gap-3">
                <button id="btn-back" class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg transition-all duration-200 shadow-md hover:shadow-lg active:scale-95 {{ Route::currentRouteName() === 'produccion.index' ? 'bg-white text-white opacity-0 pointer-events-none' : 'bg-blue-200 hover:bg-blue-400 text-black opacity-100' }}"
                    title="Volver atrás" aria-label="Volver atrás" {{ Route::currentRouteName() === 'produccion.index' ? 'disabled' : '' }}>
                        <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                </button>

                        <a href="{{ route('produccion.index') }}" class="flex items-center">
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
               @section('navbar-right')

              @if(request()->routeIs('catalogos.req-programa-tejido') || (request()->is('planeacion/programa-tejido') && !request()->is('*programa-tejido/*/editar') && !request()->is('*programa-tejido/nuevo*')))
                <div class="flex items-center gap-1">

                  <!-- Controles de columnas -->
                  <div class="flex items-center gap-2 mr-2">
                    <!-- Grupo 1: Dropdown Agregar + Editar + Eliminar (compacto, solo íconos) -->
                    <div class="flex items-center gap-2 mr-2">
                      <div class="relative">
                        <button id="layoutBtnAddMenu" type="button" class="w-9 h-9 flex items-center justify-center rounded-full bg-green-600 text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400" title="Agregar" aria-label="Agregar">
                          <i class="fa-solid fa-plus"></i>
                        </button>
                        <div id="layoutAddMenu" class="hidden absolute right-0 mt-2 w-60 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 origin-top-right transform transition ease-out duration-150 scale-95 opacity-0 z-50">
                          <div class="py-1">
                            <button type="button" id="menuNuevoRegistro" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                              <i class="fa-solid fa-file-circle-plus text-gray-500"></i>
                              Nuevo registro
                            </button>
                            <a href="{{ route('programa-tejido.altas-especiales') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                              <i class="fa-solid fa-layer-group text-blue-600"></i>
                              Alta C.E.
                            </a>
                            <button type="button" id="menuAltaPronosticos" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                              <i class="fa-solid fa-chart-line text-green-600"></i>
                              Alta de pronósticos
                            </button>
                            <button type="button" id="menuOrdenCambio" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                <i class="fa-solid fa-file-pen text-blue-600"></i>
                                Orden de Cambio
                            </button>
                          </div>
                        </div>
                      </div>
                      <button type="button" id="layoutBtnEditar" class="w-9 h-9 flex items-center justify-center rounded-full bg-yellow-500 text-white hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed" title="Editar" aria-label="Editar" disabled>
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                      <button type="button" id="layoutBtnEliminar" class="w-9 h-9 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed" title="Eliminar" aria-label="Eliminar" disabled>
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>

                    <!-- Grupo 2: Controles de columnas -->
                    <button type="button" onclick="openPinColumnsModal()"
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-yellow-500 text-white hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-400 transition-colors border-0"
                            style="border-radius: 50%;"
                            title="Fijar columnas" aria-label="Fijar columnas">
                      <i class="fa-solid fa-thumbtack"></i>
                    </button>
                    <button type="button" onclick="openHideColumnsModal()"
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors"
                            title="Ocultar columnas" aria-label="Ocultar columnas">
                      <i class="fa-solid fa-eye-slash"></i>
                    </button>
                    <button type="button" onclick="resetColumnsSpin()"
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-500 text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors"
                            title="Restablecer columnas" aria-label="Restablecer columnas">
                      <i id="iconResetColumns" class="fa-solid fa-rotate"></i>
                    </button>

                    <!-- Grupo 3: Catálogos (icono) -->
                    <a href="{{ route('submodulos.nivel3', '104') }}" class="w-9 h-9 flex items-center justify-center rounded-full bg-purple-500 text-white hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-400 transition-colors" title="Catálogos" aria-label="Catálogos">
                      <i class="fa-solid fa-database"></i>
                    </a>
                  </div>

                  <!-- Prioridad (solo si hay selección) -->
                  <div id="rowPriorityControls" class="flex items-center gap-2 hidden">
                    <!-- Subir (verde) -->
                    <button type="button" onclick="moveRowUp()"
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-green-500 text-white hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 transition-colors"
                            title="Subir prioridad" aria-label="Subir prioridad">
                      <i class="fa-solid fa-arrow-up"></i>
                    </button>
                    <!-- Bajar (rojo) -->
                    <button type="button" onclick="moveRowDown()"
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors"
                            title="Bajar prioridad" aria-label="Bajar prioridad">
                      <i class="fa-solid fa-arrow-down"></i>
                    </button>
                        </div>

                  <!-- Filtros (reactivo) -->
                  <button type="button" id="btnFilters"
                          class="relative w-9 h-9 flex items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors"
                          title="Filtros" aria-label="Filtros">
                    <i class="fa-solid fa-filter"></i>
                    <!-- Badge con número (solo cuando hay filtros activos) -->
                    <span id="filterCount"
                          class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs font-bold hidden">0</span>
                  </button>
                </div>
              @endif

                            @yield('navbar-right')

              @if(!request()->routeIs('catalogos.req-programa-tejido') && !request()->routeIs('programa.urd.eng.reservar.programar'))
                <a href="{{ url('mantenimiento/nuevo-paro') }}"
                        class="bg-yellow-400 hover:bg-yellow-500 flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors">
                  <i class="fas fa-exclamation-triangle"></i>
                  Paro
                </a>
              @endif

                            @if (Route::currentRouteName() === 'produccion.index')
                <button id="logout-btn"
                        class="flex items-center gap-1 px-2 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-700 rounded-lg transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                    Salir
                </button>
                            @endif

                            @if(isset($tieneConfiguracion) && $tieneConfiguracion)
                <a href="{{ route('configuracion.index') }}"
                   class="w-10 h-10 bg-blue-100 hover:bg-blue-200 rounded-full flex items-center justify-center text-blue-800 hover:text-blue-900 transition-all duration-200 shadow-sm hover:shadow-md"
                   title="Configuración">
                    <i class="fas fa-cog"></i>
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
                        <i class="fas fa-building text-gray-400 flex-shrink-0"></i>
                        <span class="text-gray-600 truncate">{{ $usuario->area }}</span>
                    </div>
                    @endif

                    @if(isset($usuario->turno) && $usuario->turno)
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-gray-400 flex-shrink-0"></i>
                        <span class="text-gray-600">Turno {{ $usuario->turno }}</span>
                    </div>
                    @endif

                    @if(isset($usuario->correo) && $usuario->correo)
                    <div class="flex items-center gap-2">
                        <i class="fas fa-envelope text-gray-400 flex-shrink-0"></i>
                        <span class="text-gray-600 truncate">{{ $usuario->correo }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

