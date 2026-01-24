<div class="flex items-center gap-1">
    <div class="flex items-center gap-1">
        <!-- Botón Ver Líneas de Detalle -->
        <button type="button" 
                id="layoutBtnVerLineas"
                class="relative w-9 h-9 flex items-center justify-center rounded-full bg-orange-500 disabled:bg-orange-300 text-white hover:bg-orange-600 disabled:hover:bg-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-400 transition-colors disabled:cursor-not-allowed"
                title="Ver líneas de detalle" 
                aria-label="Ver líneas de detalle" 
                disabled>
            <i class="fa-solid fa-info text-sm"></i>
        </button>

        <!-- Grupo 1: Controles principales -->
        <div class="flex items-center gap-1">
            <!-- Botón Drag and Drop -->
            <button type="button" 
                    id="btnDragDrop"
                    onclick="toggleDragDropMode()"
                    class="relative w-9 h-9 flex items-center justify-center rounded-full bg-black text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
                    title="Activar/Desactivar arrastrar filas" 
                    aria-label="Drag and Drop">
                <i class="fa-solid fa-arrows-alt-v text-sm"></i>
            </button>

            <!-- Botón Edición Inline -->
            <button type="button" 
                    id="btnInlineEdit"
                    onclick="toggleInlineEditMode()"
                    class="relative w-9 h-9 flex items-center justify-center rounded-full bg-yellow-500 text-white hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-blue-800 transition-colors"
                    title="Activar/Desactivar edición en línea" 
                    aria-label="Edición en línea">
                <i class="fa-solid fa-pen text-sm"></i>
            </button>

            <!-- Descargar programa -->
            <x-navbar.button-report
                onclick="descargarPrograma()"
                title="Descargar programa"
                module="Programa Tejido"
                icon="fa-download"
                bg="bg-blue-500"
                iconColor="text-white"
                hoverBg="hover:bg-blue-600"
                class="text-sm" />

            <!-- Liberar órdenes -->
            <x-navbar.button-report
                onclick="mostrarModalDiasLiberar()"
                title="Liberar órdenes"
                module="Programa Tejido"
                icon="fa-unlock"
                bg="bg-stone-500"
                iconColor="text-white"
                hoverBg="hover:bg-stone-600"
                class="text-sm" />
        </div>

        <!-- Grupo 2: Controles de columnas -->
        @include('components.navbar.sections.column-controls', [
            'resetId' => 'btnResetColumns',
            'resetIconId' => 'iconResetColumns'
        ])

        <!-- Grupo 3: Acciones adicionales -->
        <a href="{{ route('programa-tejido.balancear') }}" 
           class="w-9 h-9 flex items-center justify-center rounded-full bg-green-500 text-white hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-teal-400 transition-colors" 
           title="Balancear" 
           aria-label="Balancear">
            <i class="fa-solid fa-scale-balanced text-sm"></i>
        </a>

        <button type="button" 
                id="btnVincularExistentes" 
                onclick="vincularRegistrosExistentes()" 
                class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors disabled:bg-blue-300 disabled:cursor-not-allowed" 
                title="Vincular registros existentes - Click para activar modo selección múltiple" 
                aria-label="Vincular registros existentes">
            <i class="fa-solid fa-link text-sm"></i>
        </button>

        <a href="{{ route('planeacion.catalogos.index') }}" 
           class="w-9 h-9 flex items-center justify-center rounded-full bg-purple-500 text-white hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-400 transition-colors" 
           title="Catálogos" 
           aria-label="Catálogos">
            <i class="fa-solid fa-database text-sm"></i>
        </a>

        <!-- Botón Actualizar con Dropdown -->
        <div class="relative">
            <button type="button" 
                    id="btnActualizarDropdown"
                    onclick="document.getElementById('actualizarDropdownMenu').classList.toggle('hidden')"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors"
                    title="Actualizar" 
                    aria-label="Actualizar">
                <i class="fa-solid fa-tower-observation text-sm"></i>
            </button>
            <div id="actualizarDropdownMenu" 
                 class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50">
                <div class="py-1">
                    <button type="button" 
                            id="menuActCalendarios" 
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                        <i class="fa-solid fa-calendar text-blue-600"></i>
                        <span>Act. Calendarios</span>
                    </button>
                    <button type="button" 
                            id="menuActFechas" 
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days text-green-600"></i>
                        <span>Act. Fechas</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <button type="button" 
            id="btnFilters"
            class="relative w-9 h-9 flex items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-colors"
            title="Filtros" 
            aria-label="Filtros">
        <i class="fa-solid fa-filter text-sm"></i>
        <span id="filterCount"
              class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs font-bold hidden">0</span>
    </button>
</div>
