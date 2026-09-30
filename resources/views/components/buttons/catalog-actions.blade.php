{{--
    Componente: Catalog Actions (Acciones de Catálogo)

    Botones de acción para catálogos (crear, editar, eliminar, subir Excel, filtrar) con permisos
    por módulo. Sin onclick ni <script> inline (19-06b): cada botón lleva data-accion-catalogo y
    resources/js/catalogos/catalog-actions.ts lo despacha al handler que registró la pantalla
    (registrarAccionesCatalogo) o, si no hay, al window.<accion><RouteJs>() de siempre (PUENTE para
    las pantallas que siguen en JS, p. ej. Codificación).

    Props:
        @param string $route        Ruta del catálogo (ej: 'telares', 'eficiencia', 'calendarios')
        @param bool   $showFilters  Si debe mostrar botones de filtros (default: false)
        @param bool   $showExcel    Si debe mostrar el botón de Excel (default: true; solo si el catálogo tiene ruta de carga)

    Uso:
        <x-buttons.catalog-actions route="telares" :showFilters="true" />
--}}

@props(['route' => null, 'showFilters' => false, 'showExcel' => true])

@php
    // Mapeo de rutas a nombres de módulos en la tabla SYSRoles
    $rutaToNombreModulo = [
        'telares' => 'Telares',
        'eficiencia' => 'Eficiencias STD',
        'velocidad' => 'Velocidad STD',
        'calendarios' => 'Calendarios',
        'aplicaciones' => 'Aplicaciones (Cat.)',
        'codificacion' => 'Codificación Modelos',
        'matriz-hilos' => 'Matriz Hilos',
        'pesos-rollos' => 'Pesos por Rollos',
    ];

    // Carga de Excel por catálogo. Antes el botón genérico posteaba a
    // /planeacion/catalogos/{route}-modelos/excel, que solo existe para Codificación (y allí va oculto):
    // en Pesos por Rollos y Matriz de Hilos el botón daba 404. Sin ruta, no hay botón.
    $rutaExcel = [
        'telares' => 'planeacion.telares.excel.upload',
        'eficiencia' => 'planeacion.eficiencia.excel.upload',
        'velocidad' => 'planeacion.velocidad.excel.upload',
        'aplicaciones' => 'planeacion.aplicaciones.excel.upload',
    ];

    $nombreModulo = $rutaToNombreModulo[$route] ?? null;
    // Nombre seguro para JS (reemplazar no alfanumérico por guion bajo)
    $routeJs = preg_replace('/[^A-Za-z0-9_]/', '_', ucfirst($route ?? ''));

    $puedeCrear = $nombreModulo ? userCan('crear', $nombreModulo) : false;
    $puedeEditar = $nombreModulo ? userCan('modificar', $nombreModulo) : false;
    $puedeEliminar = $nombreModulo ? userCan('eliminar', $nombreModulo) : false;
    $tieneAcceso = $nombreModulo ? userCan('acceso', $nombreModulo) : false;

    $urlExcel = isset($rutaExcel[$route]) ? route($rutaExcel[$route], absolute: false) : null;
    $conExcelGenerico = $showExcel && $puedeCrear && $tieneAcceso && $urlExcel !== null;
    $configAcciones = ['ruta' => $route, 'routeJs' => $routeJs, 'excel' => $conExcelGenerico ? $urlExcel : null];
    $iconoBoton = 'p-2 rounded-md transition-colors min-h-touch min-w-touch';
@endphp

<div class="flex items-center gap-1" data-catalogo-acciones='@json($configAcciones)'>
    @if($tieneAcceso)
        {{-- Botón especial de Recalcular para calendarios --}}
        @if($route === 'calendarios')
            <button type="button" id="btn-recalcular" data-accion-catalogo="recalcular"
                class="{{ $iconoBoton }} text-white bg-blue-600 hover:bg-blue-800"
                title="Recalcular programas" aria-label="Recalcular programas">
                <span class="mr-2">Recalcular</span>
                <i class="fas fa-calculator text-lg" aria-hidden="true"></i>
            </button>
        @endif

        @if($puedeCrear)
            {{-- Para calendarios, dos botones de Excel separados --}}
            @if($showExcel && $route === 'calendarios')
                <button type="button" id="btn-subir-excel-calendarios" data-accion-catalogo="excel-calendarios"
                    class="{{ $iconoBoton }} text-green-600 hover:text-green-800 hover:bg-green-100"
                    title="Subir Calendarios" aria-label="Subir Calendarios">
                    <i class="fas fa-file-excel text-lg" aria-hidden="true"></i>
                </button>

                <button type="button" id="btn-subir-excel-lineas" data-accion-catalogo="excel-lineas"
                    class="{{ $iconoBoton }} text-green-600 hover:text-green-800 hover:bg-green-100"
                    title="Subir Líneas" aria-label="Subir Líneas">
                    <i class="fas fa-file-excel text-lg" aria-hidden="true"></i>
                </button>
            @elseif($conExcelGenerico)
                <button type="button" id="btn-subir-excel" data-accion-catalogo="subir-excel"
                    class="{{ $iconoBoton }} text-green-600 hover:text-green-800 hover:bg-green-100"
                    title="Subir Excel" aria-label="Subir Excel">
                    <i class="fas fa-file-excel text-lg" aria-hidden="true"></i>
                </button>
            @endif

            <button type="button" id="btn-agregar" data-accion-catalogo="agregar"
                class="{{ $iconoBoton }} text-blue-600 hover:text-blue-800 hover:bg-blue-100"
                title="Añadir" aria-label="Añadir">
                <i class="fas fa-plus text-lg" aria-hidden="true"></i>
            </button>
        @endif

        {{-- Editar/Eliminar: el aspecto de deshabilitado sale del atributo disabled (lo alterna la pantalla). --}}
        @if($puedeEditar)
            <button type="button" id="btn-editar" data-accion-catalogo="editar" disabled
                class="{{ $iconoBoton }} text-yellow-500 hover:text-yellow-600 disabled:text-gray-400 disabled:hover:text-gray-400 disabled:cursor-not-allowed"
                title="Editar" aria-label="Editar">
                <i class="fas fa-edit text-lg" aria-hidden="true"></i>
            </button>
        @endif

        @if($puedeEliminar)
            <button type="button" id="btn-eliminar" data-accion-catalogo="eliminar" disabled
                class="{{ $iconoBoton }} text-red-600 hover:text-red-800 disabled:text-red-300 disabled:hover:text-red-300 disabled:cursor-not-allowed"
                title="Eliminar" aria-label="Eliminar">
                <i class="fas fa-trash text-lg" aria-hidden="true"></i>
            </button>

            @if($route === 'calendarios')
                <button type="button" id="btn-eliminar-rango" data-accion-catalogo="eliminar-rango"
                    class="{{ $iconoBoton }} bg-red-600 text-white hover:bg-red-800"
                    title="Eliminar por Rango" aria-label="Eliminar por Rango">
                    <i class="fas fa-calendar-times text-lg" aria-hidden="true"></i>
                    <span class="ml-1 text-caption">Rango</span>
                </button>
            @endif
        @endif
    @endif

    @if($showFilters)
        <button type="button" id="btn-filtrar" data-accion-catalogo="filtrar"
            class="{{ $iconoBoton }} relative text-blue-600 hover:text-blue-800 hover:bg-blue-100"
            title="Filtrar" aria-label="Filtrar">
            <i class="fas fa-filter text-lg" aria-hidden="true"></i>
            <span id="filter-count" data-catalogo-contador class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-caption font-bold hidden">0</span>
        </button>

        <button type="button" id="btn-restablecer-{{ $route }}" data-accion-catalogo="restablecer"
            class="{{ $iconoBoton }} text-gray-600 hover:text-gray-800 hover:bg-gray-100"
            title="Restablecer" aria-label="Restablecer">
            <i id="icon-restablecer-{{ $route }}" data-catalogo-icono-restablecer class="fas fa-redo text-lg" aria-hidden="true"></i>
        </button>
    @endif
</div>

@if($conExcelGenerico)
    {{-- El <dialog> va al final del body (stack 'scripts'), fuera del <nav> fijo, como el resto
         de los modales de la pantalla. --}}
    @push('scripts')
        <x-ui.modal-base id="catalogo-excel-modal" title="Subir archivo Excel" size="md" :close-on-backdrop="true">
            <form id="catalogo-excel-form" class="space-y-3" data-catalogo-excel-form>
                <label for="catalogo-excel-archivo"
                       class="block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 p-6 text-center hover:border-green-500">
                    <i class="fas fa-file-excel text-green-600 text-4xl mb-2" aria-hidden="true"></i>
                    <span class="block text-sm font-medium text-gray-900">Selecciona o arrastra tu archivo Excel</span>
                    <span class="mt-1 block text-caption text-gray-500">Formatos: .xlsx, .xls (máximo 10 MB)</span>
                    <input type="file" id="catalogo-excel-archivo" name="archivo_excel" accept=".xlsx,.xls" class="sr-only" required>
                </label>
                <p class="text-sm text-gray-700" data-catalogo-excel-nombre hidden></p>
            </form>
            <x-slot:footer>
                <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="catalogo-excel-modal">Cancelar</x-ui.button>
                <x-ui.button variant="create" size="nav" type="submit" form="catalogo-excel-form" icon="fa-upload" data-catalogo-excel-enviar>Procesar Excel</x-ui.button>
            </x-slot:footer>
        </x-ui.modal-base>
    @endpush
@endif

@once
    @push('scripts')
        @vite('resources/js/modulos/catalogos-planeacion/acciones/index.ts')
    @endpush
@endonce
