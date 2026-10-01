@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Estatus a la vista (antes en un modal): programa/index.ts pone aria-pressed y aplica.
             Filtro por columna: clic derecho o mantener presionado en el encabezado, como antes. --}}
        <flux:button.group>
            @foreach ([['todos', 'list-bullet', 'Todos'], ['activo', 'play', 'Activo'], ['en-proceso', 'arrow-path', 'En proceso'], ['calificados', 'star', 'Calificados'], ['terminados', 'check-circle', 'Terminados'], ['autorizados', 'hand-thumb-up', 'Autorizado']] as [$clave, $icono, $texto])
                <flux:button data-filtro="{{ $clave }}" aria-pressed="false" :icon="$icono" class="min-h-touch" :title="$texto" :aria-label="$texto">
                    <span class="hidden xl:inline">{{ $texto }}</span>
                </flux:button>
            @endforeach
        </flux:button.group>

        <x-navbar.button-create id="btnIniciarAtado" data-accion="iniciar-atado" disabled :moduleId="45"
            title="Iniciar Atado" text="Iniciar Atado" />

    </div>
@endsection

@section('content')
    @php
        // Estatus → color de flux:badge. Las <template data-badge-estatus> de abajo los reusa el refresco de 15 s.
        $coloresEstatus = ['Activo' => 'zinc', 'En Proceso' => 'blue', 'Terminado' => 'purple', 'Calificado' => 'yellow', 'Autorizado' => 'green'];
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
    <div class="pantalla-completa p-2" id="programa-atadores" data-pagina='@json($configPagina)'>

        {{-- flux:table + .tabla-cebra / .tabla-seleccionable (app.css). Orden y filtro por columna: programa/index.ts. --}}
        <div class="tabla-pantalla rounded-lg shadow-md bg-white overflow-hidden">
            <flux:table class="tabla-cebra tabla-seleccionable">
                <flux:table.columns sticky id="atadoresTableHead" class="towell-acciones-zona bg-white">
                        <flux:table.column data-sort="fecha" data-column="fecha" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Fecha <span class="sort-icon ml-1 opacity-80">▲</span></flux:table.column>
                        <flux:table.column data-sort="estatus" data-column="estatus" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Estatus <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="turno" data-column="turno" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Turno <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="telar" data-column="telar" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Telar <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="tipo" data-column="tipo" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Tipo <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="julio" data-column="no-julio" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Julio <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="ubicacion" data-column="ubicacion" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Ubicación <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="metros" data-column="metros" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Metros <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="orden" data-column="no-orden" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Orden <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="tipo-atado" data-column="tipo-atado" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Tipo atado <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="cuenta" data-column="cuenta" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Cuenta <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="calibre" data-column="calibre" class="th-sortable cursor-pointer select-none hidden md:table-cell" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Calibre <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="hilo" data-column="hilo" class="th-sortable cursor-pointer select-none hidden md:table-cell" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Hilo <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="lote" data-column="lote" class="th-sortable cursor-pointer select-none hidden md:table-cell" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Lote <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="no-prov" data-column="no-prov" class="th-sortable cursor-pointer select-none hidden md:table-cell" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">No. Prov. <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                        <flux:table.column data-sort="hr-paro" data-column="hora-paro" class="th-sortable cursor-pointer select-none" role="button" title="Clic para ordenar | Clic derecho o mantener presionado para filtrar">Hr. Paro <span class="sort-icon ml-1 opacity-80"></span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows id="tb-body">
                    @forelse($inventarioTelares as $item)
                        <flux:table.row class="table-row" aria-selected="false"
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
                            <flux:table.cell>
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </flux:table.cell>
                            <flux:table.cell data-status="{{ $item->status_proceso }}">
                                <flux:badge size="sm" inset="top bottom" :color="$coloresEstatus[$item->status_proceso] ?? 'zinc'">{{ $item->status_proceso }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->turno ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->no_telar ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
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
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ collect([$item->no_julio ?? null, $item->no_julio2 ?? null, $item->no_julio3 ?? null, $item->no_julio4 ?? null])->filter()->implode(', ') ?: '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->localidad ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ ($item->metros === null || $item->metros === '') ? '-' : number_format((float) $item->metros, 2) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->no_orden ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->tipo_atado ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->cuenta ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">
                                {{ ($item->calibre === null || $item->calibre === '') ? '-' : number_format((float) $item->calibre, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">
                                {{ $item->hilo ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">
                                {{ $item->LoteProveedor ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">
                                {{ $item->NoProveedor ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $item->horaParo ?? '-' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="16" class="py-4 text-center">
                                No hay datos disponibles en el inventario de telares
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    @foreach ($coloresEstatus as $estatus => $color)
        <template data-badge-estatus="{{ $estatus }}"><flux:badge size="sm" inset="top bottom" :color="$color">{{ $estatus }}</flux:badge></template>
    @endforeach
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
