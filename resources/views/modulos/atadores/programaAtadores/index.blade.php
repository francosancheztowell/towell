@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Botón de Filtros --}}
        <x-navbar.button-report id="btn-open-filters" title="Filtros" icon="fa-filter" text="Filtrar"
            module="Programa Atadores" iconColor="text-white" hoverBg="hover:bg-green-600" class="text-white"
            bg="bg-green-600" />

        <x-navbar.button-create id="btnIniciarAtado" data-accion="iniciar-atado" disabled :moduleId="45"
            title="Iniciar Atado" text="Iniciar Atado" />

    </div>
@endsection

@section('content')
    {{-- Modal de Filtros --}}
    <div id="modalFiltros" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-slate-500">Puedes elegir uno o varios filtros. Clic de nuevo para quitar.</p>
                <button type="button" data-accion="cerrar-filtros" aria-label="Cerrar"
                    class="text-slate-500 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {{-- Fila 1: Ver Todos, Activo --}}
                <button type="button" id="btn-filter-todos" data-filtro="todos"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-list text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Ver Todos</div>
                </button>
                <button type="button" id="btn-filter-activo" data-filtro="activo"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-circle-dot text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Activo</div>
                </button>

                {{-- Fila 2: En Proceso, Calificados, Terminados --}}
                <button type="button" id="btn-filter-en-proceso" data-filtro="en-proceso"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-play-circle text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">En Proceso</div>
                </button>
                <button type="button" id="btn-filter-calificados" data-filtro="calificados"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-star text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Calificados</div>
                </button>
                <button type="button" id="btn-filter-terminados" data-filtro="terminados"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-check-circle text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Terminados</div>
                </button>
                <button type="button" id="btn-filter-autorizados" data-filtro="autorizados"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-thumbs-up text-2xl mb-2 block"></i>
                    <div class="font-semibold text-sm">Autorizado</div>
                </button>
            </div>
        </div>
    </div>

    @php
        $configPagina = [
            'rutas' => [
                'programa' => route('atadores.programa'),
                'estatus' => route('atadores.programa.estatus'),
                'iniciar' => route('atadores.iniciar'),
            ],
            // Filtros activos al cargar: los de ?vista= o el de ?filtro= (todos = ninguno).
            'filtros' => $vista ? array_values(array_filter(explode(',', $vista))) : ($filtroAplicado === 'todos' ? [] : [$filtroAplicado]),
            'telaresUsuario' => array_values(array_map('strval', $telaresUsuario ?? [])),
            'esTejedor' => (bool) ($esTejedor ?? false),
            'esSupervisor' => (bool) ($esSupervisor ?? false),
            'filtroGlobalActivo' => (bool) ($filtroGlobalActivo ?? false),
        ];
    @endphp
    <div class="container mx-auto px-4 py-4" id="programa-atadores" data-pagina='@json($configPagina)'>

        <div class="overflow-auto rounded-lg shadow-md bg-white" style="max-height: calc(100vh - 7rem);">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead id="atadoresTableHead" class="towell-acciones-zona bg-blue-500 sticky top-0 z-10">
                    <tr>
                        <th data-sort="fecha" data-column="fecha"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Fecha <span
                                class="sort-icon ml-1 opacity-80">▲</span> </th>
                        <th data-sort="estatus" data-column="estatus"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Estatus <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="turno" data-column="turno"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Turno <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="telar" data-column="telar"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Telar <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="tipo" data-column="tipo"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Tipo <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="julio" data-column="no-julio"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Julio <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="ubicacion" data-column="ubicacion"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Ubicación <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="metros" data-column="metros"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Metros <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="orden" data-column="no-orden"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Orden <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="tipo-atado" data-column="tipo-atado"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Tipo atado <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="cuenta" data-column="cuenta"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Cuenta <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="calibre" data-column="calibre"
                            class="th-sortable hidden md:table-cell px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Calibre <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="hilo" data-column="hilo"
                            class="th-sortable hidden md:table-cell px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Hilo <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="lote" data-column="lote"
                            class="th-sortable hidden md:table-cell px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Lote <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="no-prov" data-column="no-prov"
                            class="th-sortable hidden md:table-cell px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> No. Prov. <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="hr-paro" data-column="hora-paro"
                            class="th-sortable px-2 py-2 text-left text-sm font-medium text-white sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none"
                            role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar"> Hr. Paro <span
                                class="sort-icon ml-1 opacity-80"></span> </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tb-body">
                    @forelse($inventarioTelares as $item)
                        <tr class="table-row hover:bg-blue-100 cursor-pointer transition-colors duration-150"
                            data-id="{{ $item->id }}"
                            data-fecha="{{ $item->fecha ? $item->fecha->format('Y-m-d') : '9999-99-99' }}"
                            data-estatus="{{ $item->status_proceso ?? 'Activo' }}" data-turno="{{ $item->turno ?? '' }}"
                            data-telar="{{ $item->no_telar ?? '' }}" data-tipo="{{ $item->tipo ?? '' }}"
                            data-no-julio="{{ $item->no_julio ?? '' }}" data-ubicacion="{{ $item->localidad ?? '' }}"
                            data-metros="{{ $item->metros !== null && $item->metros !== '' ? $item->metros : '-999999' }}"
                            data-no-orden="{{ $item->no_orden ?? '' }}" data-config-id="{{ $item->ConfigId ?? '' }}"
                            data-invent-size-id="{{ $item->InventSizeId ?? '' }}"
                            data-invent-color-id="{{ $item->InventColorId ?? '' }}"
                            data-tipo-atado="{{ $item->tipo_atado ?? '' }}" data-cuenta="{{ $item->cuenta ?? '' }}"
                            data-calibre="{{ $item->calibre !== null && $item->calibre !== '' ? $item->calibre : '-999999' }}"
                            data-hilo="{{ $item->hilo ?? '' }}" data-lote="{{ $item->LoteProveedor ?? '' }}"
                            data-no-prov="{{ $item->NoProveedor ?? '' }}" data-hora-paro="{{ $item->horaParo ?? '' }}"
                            data-status="{{ $item->status_proceso ?? 'Activo' }}">
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm" data-status="{{ $item->status_proceso }}">
                                <span class="px-1.5 py-0.5 rounded-full text-sm font-semibold
                                            @if($item->status_proceso === 'Activo') bg-gray-200 text-gray-800
                                            @elseif($item->status_proceso === 'En Proceso') bg-blue-200 text-blue-800
                                            @elseif($item->status_proceso === 'Terminado') bg-purple-200 text-purple-800
                                            @elseif($item->status_proceso === 'Calificado') bg-yellow-200 text-yellow-800
                                            @elseif($item->status_proceso === 'Autorizado') bg-green-200 text-green-800
                                            @endif">
                                    {{ $item->status_proceso }}
                                </span>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->turno ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->no_telar ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                @php
                                    $tipo = $item->tipo ?? '-';
                                    if ($tipo === 'Rizo') {
                                        $tipo = 'R';
                                    } elseif ($tipo === 'Pie') {
                                        $tipo = 'P';
                                    } elseif (preg_match('/^[1-4]$/', (string) $tipo)) {
                                        $tipo = 'B'.$tipo; // Barra Karl Mayer
                                    }
                                @endphp
                                {{ $tipo }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ collect([$item->no_julio ?? null, $item->no_julio2 ?? null, $item->no_julio3 ?? null, $item->no_julio4 ?? null])->filter()->implode(', ') ?: '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->localidad ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ ($item->metros === null || $item->metros === '') ? '-' : number_format((float) $item->metros, 2) }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->no_orden ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->tipo_atado ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->cuenta ?? '-' }}
                            </td>
                            <td class="hidden md:table-cell px-2 py-2 whitespace-nowrap text-sm">
                                {{ ($item->calibre === null || $item->calibre === '') ? '-' : number_format((float) $item->calibre, 2) }}
                            </td>
                            <td class="hidden md:table-cell px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->hilo ?? '-' }}
                            </td>
                            <td class="hidden md:table-cell px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->LoteProveedor ?? '-' }}
                            </td>
                            <td class="hidden md:table-cell px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->NoProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm">
                                {{ $item->horaParo ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="px-6 py-4 text-center text-sm text-gray-500">
                                No hay datos disponibles en el inventario de telares
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <template id="plantillaSinResultados">
        <tr class="no-results">
            <td colspan="16" class="px-6 py-4 text-center text-sm text-gray-500">
                <div class="flex flex-col items-center gap-2">
                    <i class="fa-solid fa-inbox text-4xl text-gray-300" aria-hidden="true"></i>
                    <span class="text-base font-medium">Sin resultados con los filtros aplicados</span>
                </div>
            </td>
        </tr>
    </template>

    <x-ui.modal-base id="modalFiltroColumna" title="Filtrar columna" size="sm">
        <form id="formFiltroColumna">
            <x-ui.field as="input" name="valor" id="filtroColumnaValor" label="Valor a buscar" placeholder="Escribe el valor a buscar..." autocomplete="off" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" data-ui-modal-close-target="modalFiltroColumna">Cancelar</x-ui.button>
            <x-ui.button variant="create" type="submit" form="formFiltroColumna" icon="fa-check">Aplicar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>

    {{-- Context Menu para filtrar columnas --}}
    <div id="tableContextMenu"
        class="hidden fixed z-50 min-w-[220px] bg-white border border-gray-200 rounded-lg shadow-lg p-1">
        <button type="button" data-action="filter-column"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md flex items-center gap-2">
            <i class="fa-solid fa-filter text-blue-500"></i>
            Filtrar columna
        </button>
        <button type="button" data-action="clear-column-filter"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md flex items-center gap-2">
            <i class="fa-solid fa-eraser text-orange-500"></i>
            Quitar filtro de columna
        </button>
        <hr class="my-1 border-gray-200">
        <button type="button" data-action="clear-all-filters"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md flex items-center gap-2">
            <i class="fa-solid fa-broom text-red-500"></i>
            Quitar todos los filtros
        </button>
    </div>

@endsection

@push('scripts')
    @vite('resources/js/modulos/atadores/programa/index.ts')
@endpush
