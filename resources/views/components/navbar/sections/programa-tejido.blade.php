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
            <x-navbar.button-create
                icon="fa-arrows-up-down"
                type="button"
                id="btnDragDrop"
                bg="bg-black"
                iconColor="text-white"
                module="Programa Tejido"
                onclick="toggleDragDropMode()"
                title="Activar/Desactivar arrastrar filas"/>



            <!-- Descargar programa -->
            <x-navbar.button-report
                onclick="descargarPrograma()"
                title="Descargar programa"
                module="Programa Tejido"
                icon="fa-download"
                bg="bg-blue-500"
                iconColor="text-white"
                hoverBg="hover:bg-blue-600"
                module="Programa Tejido"
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
        <x-navbar.button-edit
        onclick="window.location.href='{{ route('programa-tejido.balancear') }}'"
        title="Balancear"
        module="Programa Tejido"
        icon="fa-scale-balanced"
        bg="bg-green-500"
        iconColor="text-white"
        hoverBg="hover:bg-green-600"
        class="text-sm" />

        <x-navbar.button-edit
        type="button"
        id="btnVincularExistentes"
        onclick="vincularRegistrosExistentes()"
        title="Vincular registros existentes - Click para activar modo selección múltiple"
        module="Programa Tejido"
        icon="fa-link"
        bg="bg-blue-500"
        iconColor="text-white"
        hoverBg="hover:bg-blue-600"
        class="text-sm" />

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
                    <x-navbar.button-edit type="button"
                        id="menuActCalendarios"
                        onclick="document.getElementById('actualizarDropdownMenu').classList.toggle('hidden')"
                        title="Actualizar Calendarios"
                        text="Actualizar Calendarios"
                        module="Programa Tejido"
                        icon="fa-calendar-days"
                        bg="bg-white"
                        iconColor="text-blue-500"
                        hoverBg="hover:bg-blue-600"
                        class="text-sm" />

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
