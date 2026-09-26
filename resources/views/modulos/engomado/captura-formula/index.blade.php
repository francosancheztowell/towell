@extends('layouts.app')

@section('page-title', 'Captura de Fórmulas')

{{--
  Captura de Fórmula (Engomado). JS: resources/js/modulos/engomado/captura-formula/index.ts (19-01).
  Datos del servidor en data-pagina; acciones por data-accion (sin onclick) y rutas con route().
--}}

@section('navbar-right')
    @php
        $desdeProduccion = !empty($folioFiltro);
    @endphp
    <div class="flex items-center gap-2">
        @if($desdeProduccion)
            <a href="{{ !empty($ordenIdProduccion) ? route('engomado.modulo.produccion.engomado', ['orden_id' => $ordenIdProduccion]) : route('engomado.modulo.produccion.engomado') }}"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition"
                title="Volver a Producción de Engomado">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>Producción</span>
            </a>
        @endif
        @if($desdeProduccion)
            <x-navbar.button-create
            data-accion="nueva"
            title="Autorizar Formula"
            module="Captura de Formula"
            />
            <x-navbar.button-delete
            id="btn-delete"
            data-accion="eliminar"
            title="Eliminar Fórmula"
            module="Captura de Formula"
            :disabled="true"
            />
        @endif
        <x-navbar.button-edit
            id="btn-edit"
            data-accion="editar"
            title="Editar"
            bg="bg-purple-500"
            text="Editar"
            iconColor="text-white"
            hoverBg="hover:bg-purple-300"
            class="text-white"
            module="Captura de Formula"
            :disabled="true"
        />
        <x-navbar.button-edit
        id="btn-view"
        data-accion="ver"
        title="Ver"
        bg="bg-orange-500"
        text="Ver"
        iconColor="text-white"
        icon="fa-list"
        hoverBg="hover:bg-orange-300"
        class="text-white"
        module="Captura de Formula"
        :disabled="true"
        />
    </div>
@endsection

@section('content')
    @php
        $desdeProduccion = !empty($folioFiltro);
        $puedeVerCalidad = function_exists('userCan') ? userCan('registrar', 'Captura de Formula') : true;
        $configPagina = [
            'desdeProduccion' => $desdeProduccion,
            // Mismo origen que antes (Auth::user()->numero): el store toma CveEmpl del programa.
            'usuario' => ['nombre' => auth()->user()->nombre ?? '', 'numero' => auth()->user()->numero ?? ''],
            'rutas' => [
                'guardar' => route('eng-formulacion.store'),
                'formulacion' => route('eng-formulacion.update', ['folio' => '__FOLIO__']),
                'porId' => route('eng-formulacion.by-id'),
                'componentes' => route('eng-formulacion.componentes'),
                'formulasDisponibles' => route('eng-formulacion.formulas-disponibles'),
                'calibres' => route('eng-formulacion.calibres'),
                'fibras' => route('eng-formulacion.fibras'),
            ],
        ];
    @endphp

    <div id="captura-formula" data-pagina='@json($configPagina)'>
    <div class="overflow-x-auto overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-md mt-4 mx-4" style="max-height: 70vh;">
        <table id="formulaTable" class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-sm">
                <tr>
                    @if($puedeVerCalidad)
                    <th class="text-center px-4 py-3 font-semibold whitespace-nowrap first:rounded-tl-xl">Calidad</th>
                    @endif
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap {{ $puedeVerCalidad ? '' : 'first:rounded-tl-xl' }}">ID</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Orden</th>
                    <th id="th-fecha" class="text-left px-4 py-3 font-semibold whitespace-nowrap cursor-pointer select-none">Fecha <i class="fa-solid fa-filter text-xs ml-1 opacity-80" aria-hidden="true"></i></th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Hr</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Cuenta</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Calibre</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tipo</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Operador</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Olla</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Formula</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Kg.</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Litros</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tiempo (Min)</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">% Solidos</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Viscocidad</th>
                </tr>
            </thead>
            <tbody id="formulaTableBody">
                @forelse($items as $item)
                    @php
                        $folioOrden = trim((string) ($item->folio_resuelto ?? $item->Folio ?? $item->ProdId ?? ''));
                        $okTiempo = $item->OkTiempo === null ? '' : ($item->OkTiempo ? '1' : '0');
                        $okVisc = ($item->OkViscocidad ?? $item->OkViscosidad ?? null) === null ? '' : (($item->OkViscocidad ?? $item->OkViscosidad) ? '1' : '0');
                        $okSolidos = $item->OkSolidos === null ? '' : ($item->OkSolidos ? '1' : '0');
                    @endphp
                    <tr class="formula-row  border-gray-100 cursor-pointer transition-all duration-150 hover:bg-blue-50/80 even:bg-gray-50/50"
                        data-folio="{{ $folioOrden }}"
                        data-id="{{ $item->Id ?? '' }}"
                        data-fecha="{{ ($item->fecha ?? $item->Fecha) ? \Carbon\Carbon::parse($item->fecha ?? $item->Fecha)->format('Y-m-d') : '' }}"
                        data-status="{{ $item->Status }}"
                        data-ax="{{ $item->AX ?? 0 }}"
                    >
                        @if($puedeVerCalidad)
                        <td class="px-4 py-3 text-center">
                            @php
                                $tieneObs = !empty($item->obs_calidad);
                            @endphp
                            <x-navbar.button-report
                                data-accion="calidad"
                                title="{{ $tieneObs ? $item->obs_calidad : 'Calidad (sin observaciones)' }}"
                                icon="{{ $tieneObs ? 'fa-clipboard-check' : 'fa-clipboard-list' }}"
                                iconColor="{{ $tieneObs ? 'text-blue-700' : 'text-blue-500' }}"
                                hoverBg="hover:bg-blue-50"
                                module="Captura de Formula"
                                class="obs-calidad-btn"
                                data-folio="{{ $folioOrden }}"
                                data-id="{{ $item->Id ?? '' }}"
                                data-formula="{{ $item->Formula ?? '' }}"
                                data-litros="{{ $item->Litros ?? '' }}"
                                data-tiempo="{{ $item->TiempoCocinado ?? '' }}"
                                data-solidos="{{ number_format($item->Solidos ?? 0, 2) }}"
                                data-viscocidad="{{ $item->Viscocidad ?? '' }}"
                                data-oktiempo="{{ $okTiempo }}"
                                data-okviscocidad="{{ $okVisc }}"
                                data-oksolidos="{{ $okSolidos }}"
                                data-obs="{{ $item->obs_calidad ?? '' }}"
                                data-programa-status="{{ $item->programa_status ?? '' }}"
                            />
                        </td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-blue-700">{{ $item->Id }}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $folioOrden !== '' ? $folioOrden : '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ ($item->fecha ?? $item->Fecha) ? \Carbon\Carbon::parse($item->fecha ?? $item->Fecha)->format('d/m/Y') : '' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Hora ? substr($item->Hora, 0, 5) : '' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold
                                @if($item->Status === 'Creado') bg-yellow-100 text-yellow-800
                                @elseif($item->Status === 'En Proceso') bg-blue-100 text-blue-800
                                @elseif($item->Status === 'Terminado') bg-green-100 text-green-800
                                @endif">
                                {{ $item->Status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Cuenta }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Calibre ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Tipo }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->NomEmpl }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Olla }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Formula }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Kilos ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Litros ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->TiempoCocinado ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Solidos ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Viscocidad ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $puedeVerCalidad ? 17 : 16 }}" class="px-4 py-8 text-center text-gray-500">No hay fórmulas disponibles</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Crear/Editar/Ver -->
    <div id="createModal" class="hidden fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="create_modal_title">
        <div id="createModalContent" class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div id="create_modal_header" class="bg-blue-50 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <h3 id="create_modal_title" class="text-xl font-semibold">Nueva Formulación de Engomado</h3>
                <button type="button" data-accion="cerrar-modal" class="text-white hover:text-gray-200 transition" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="createForm" action="{{ route('eng-formulacion.store') }}" method="POST" class="p-6">
                @csrf
                <input type="hidden" id="create_method" name="_method" value="POST">

                <!-- Sección 1: Datos principales (3 columnas) -->
                <div class="mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="create_folio_prog" class="block text-xs font-medium text-gray-700 mb-1">Folio (Programa Engomado) <span class="text-red-600">*</span></label>
                            <select name="FolioProg" id="create_folio_prog" required class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed" style="pointer-events: none;" tabindex="-1">
                                <option value="">-- Seleccione un Folio --</option>
                                @foreach($foliosPrograma as $prog)
                                    <option value="{{ $prog->Folio }}"
                                            data-bomeng="{{ $prog->BomEng }}"
                                            data-cuenta="{{ $prog->Cuenta }}"
                                            data-calibre="{{ $prog->Calibre }}"
                                            data-tipo="{{ $prog->RizoPie }}"
                                            data-formula="{{ $prog->BomFormula }}"
                                            data-status="{{ $prog->Status ?? '' }}"
                                            {{ isset($folioFiltro) && $folioFiltro === $prog->Folio ? 'selected' : '' }}>
                                        {{ $prog->Folio }} - {{ $prog->Cuenta }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="text" id="create_folio_prog_display" readonly tabindex="-1" aria-label="Folio (Programa Engomado)" class="hidden w-full px-0 py-2 text-sm text-gray-900 bg-transparent border-0 rounded-lg focus:outline-none focus:ring-0">
                        </div>
                        <div>
                            <label for="create_fecha" class="block text-xs font-medium text-gray-700 mb-1">Fecha</label>
                            <input type="date" name="fecha" id="create_fecha" value="{{ date('Y-m-d') }}" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label for="create_hora" class="block text-xs font-medium text-gray-700 mb-1">Hora</label>
                            <input type="time" name="Hora" id="create_hora" value="{{ date('H:i') }}" step="60" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label for="create_display_numero" class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="create_display_numero" value="{{ auth()->user()->numero_empleado ?? (auth()->user()->numero ?? '') }}" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label for="create_display_operador" class="block text-xs font-medium text-gray-700 mb-1">Operador</label>
                            <input type="text" id="create_display_operador" value="{{ auth()->user()->nombre ?? '' }}" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label for="create_formula" class="block text-xs font-medium text-gray-700 mb-1">Fórmula</label>
                            <select id="create_formula"
                                class="campo-formula-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                                <option value="">-- Seleccione fórmula --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Campos ocultos para datos de EngProgramaEngomado -->
                <input type="hidden" name="Formula" id="create_formula_value" value="">
                <input type="hidden" name="Cuenta" id="create_cuenta">
                <input type="hidden" name="Calibre" id="create_calibre">
                <input type="hidden" name="Tipo" id="create_tipo">
                <input type="hidden" name="NomEmpl" id="create_nom_empl">
                <input type="hidden" name="CveEmpl" id="create_cve_empl">
                <input type="hidden" name="obs_calidad" id="create_obs_calidad">
                <input type="hidden" name="formulacion_id" id="create_formulacion_id">
                <input type="hidden" name="componentes" id="create_componentes_payload">
                <!-- Sección 2: Datos de Captura -->
                <div class="mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="create_olla" class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
                            <select
                            required
                            name="Olla" id="create_olla" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                <option value="">Seleccione...</option>
                                @for($i = 1; $i <= 6; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label for="create_kilos" class="block text-xs font-medium text-gray-700 mb-1">Kilos (Kg.) <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0" name="Kilos" id="create_kilos" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="No se aceptan valores negativos">
                        </div>
                        <div>
                            <label for="create_litros" class="block text-xs font-medium text-gray-700 mb-1">Litros <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" max="1500" name="Litros" id="create_litros" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Entre 0.01 y 1500">
                        </div>
                        <div>
                            <label for="create_tiempo" class="block text-xs font-medium text-gray-700 mb-1">Tiempo Cocinado (Min) <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" name="TiempoCocinado" id="create_tiempo" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Debe ser mayor a cero">
                        </div>
                        <div>
                            <label for="create_solidos" class="block text-xs font-medium text-gray-700 mb-1">% Sólidos <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" name="Solidos" id="create_solidos" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Debe ser mayor a cero">
                        </div>
                        <div>
                            <label for="create_viscocidad" class="block text-xs font-medium text-gray-700 mb-1">Viscosidad <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" name="Viscocidad" id="create_viscocidad" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Debe ser mayor a cero">
                        </div>
                    </div>
                </div>

                <!-- Sección 3: Componentes de la Fórmula -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2 pb-2 border-b border-purple-200">
                        <h4 class="text-sm font-semibold text-purple-700">Componentes de la Fórmula</h4>
                        <button type="button" id="btn-create-add-row" data-accion="agregar-fila"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition">
                            <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Agregar fila
                        </button>
                    </div>

                    <!-- Loading -->
                    <div id="create_componentes_loading" class="hidden text-center py-6">
                        <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-500" aria-hidden="true"></i>
                        <p class="text-gray-600 mt-2 text-sm">Cargando componentes...</p>
                    </div>

                    <!-- Error -->
                    <div id="create_componentes_error" class="hidden">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                            <i class="fa-solid fa-exclamation-triangle text-red-500 text-2xl mb-1" aria-hidden="true"></i>
                            <p class="text-red-700 font-medium text-sm" id="create_componentes_error_message">Error al cargar componentes</p>
                        </div>
                    </div>

                    <!-- Tabla de Componentes -->
                    <div id="create_componentes_tabla_container" class="hidden">
                        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left font-semibold rounded-tl-xl">Articulo</th>
                                        <th class="px-4 py-2.5 text-left font-semibold">Nombre</th>
                                        <th class="px-4 py-2.5 text-left font-semibold">ConfigId</th>
                                        <th class="px-4 py-2.5 text-right font-semibold rounded-tr-xl">Consumo Total</th>
                                    </tr>
                                </thead>
                                <tbody id="create_componentes_tbody" class="bg-white divide-y divide-gray-100">
                                    <!-- Lo llena componentes.ts -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div id="create-modal-buttons" class="flex gap-2 justify-end pt-3 border-t border-gray-200 mt-4">
                    <button type="button" id="btn-cancel-create" data-accion="cerrar-modal"
                            class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 w-full transition">
                        <i class="fa-solid fa-times mr-1" aria-hidden="true"></i>Cancelar
                    </button>
                    <button type="submit" id="btn-submit-create" class="px-4 py-2 text-sm font-medium bg-blue-500 w-full text-white rounded-lg hover:bg-blue-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-save mr-1" aria-hidden="true"></i><span id="submit-text-create">Crear Formulación</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Filtro tipo Excel por columna (clic derecho o pulsación larga en el encabezado). Antes: Swal con html. --}}
    <x-ui.modal-base id="modalFiltroColumna" title="Filtrar" size="sm" :close-on-backdrop="true">
        <div class="relative mb-3">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" aria-hidden="true"></i>
            <input type="search" data-filtro="buscar" aria-label="Buscar en lista"
                   class="w-full min-h-touch pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Buscar en lista...">
        </div>
        <div class="flex justify-between items-center mb-2">
            <button type="button" data-filtro-accion="todos" class="min-h-touch px-2 text-caption text-blue-600 hover:text-blue-800 font-medium">
                <i class="fa-solid fa-check-double mr-1" aria-hidden="true"></i>Todos
            </button>
            <button type="button" data-filtro-accion="ninguno" class="min-h-touch px-2 text-caption text-gray-500 hover:text-gray-700 font-medium">
                <i class="fa-solid fa-square mr-1" aria-hidden="true"></i>Ninguno
            </button>
            <span data-filtro="conteo" class="text-caption text-gray-500 font-medium" aria-live="polite"></span>
        </div>
        <div data-filtro="valores" class="filtro-col-valores max-h-[220px] overflow-y-auto border border-gray-200 rounded-lg bg-gray-50/50"></div>
        <div data-filtro="quitar-todos" class="hidden mt-3 pt-3 border-t border-gray-200">
            <button type="button" data-filtro-accion="quitar-todos" class="min-h-touch text-caption text-red-500 hover:text-red-700 hover:underline">
                <i class="fa-solid fa-filter-circle-xmark mr-1" aria-hidden="true"></i>Quitar todos los filtros
            </button>
        </div>
        <x-slot:footer>
            <x-ui.button variant="neutral" data-ui-modal-close-target="modalFiltroColumna">Cancelar</x-ui.button>
            <x-ui.button variant="delete" data-filtro-accion="limpiar">Limpiar filtro</x-ui.button>
            <x-ui.button variant="create" icon="fa-filter" data-filtro-accion="aplicar">Aplicar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>

    {{-- Calidad de una formulación (PUT eng-formulacion.update). Antes: Swal con html + preConfirm. --}}
    @if($puedeVerCalidad)
    <x-ui.modal-base id="modalCalidad" title="Calidad" size="md" :close-on-backdrop="true">
        <div class="flex flex-wrap gap-4 mb-4 text-sm items-center">
            <div><span class="text-gray-500">Folio</span> <span class="font-semibold" data-calidad="folio"></span></div>
            <div><span class="text-gray-500">Fórmula</span> <span class="font-semibold" data-calidad="formula"></span></div>
            <div><span class="text-gray-500">Litros</span> <span class="font-semibold" data-calidad="litros"></span></div>
            <div><span data-calidad="status" class="px-2 py-0.5 rounded-full text-xs font-semibold"></span></div>
        </div>
        <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Concepto</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Valor</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @foreach(['oktiempo' => ['Tiempo (Min)', 'tiempo'], 'oksolidos' => ['Sólidos (%)', 'solidos'], 'okviscocidad' => ['Viscosidad', 'viscocidad']] as $campo => [$concepto, $valor])
                        <tr class="hover:bg-blue-50/50">
                            <td class="px-4 py-2.5 font-medium text-gray-700">{{ $concepto }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-blue-700" data-calidad="{{ $valor }}"></td>
                            <td class="px-4 py-2.5 text-center">
                                <button type="button" data-calidad-ciclo="{{ $campo }}" data-concepto="{{ $concepto }}"
                                        class="w-12 h-11 rounded-lg border-2 border-gray-300 bg-gray-50 hover:bg-gray-100 hover:border-blue-400 text-lg font-bold transition-colors"
                                        title="1 toque ✓, 2 toques ✗"></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 pt-3 mt-4">
            <label for="calidad-obs" class="text-gray-500 block mb-1 text-sm font-medium">Observaciones</label>
            <input type="text" id="calidad-obs" maxlength="150"
                   class="w-full min-h-touch border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Añadir observaciones (opcional)...">
        </div>
        <x-slot:footer>
            <x-ui.button variant="neutral" data-ui-modal-close-target="modalCalidad">Cancelar</x-ui.button>
            <x-ui.button variant="create" icon="fa-floppy-disk" data-calidad-accion="guardar">Guardar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>
    @endif

    <!-- Form para eliminar -->
    <form id="deleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
        <input type="hidden" name="formulacion_id" id="delete_formulacion_id">
    </form>
    </div>

    <style>
        .formula-row:hover,
        .formula-row:hover td {
            background-color: rgb(219, 234, 255) !important;
            color: rgb(30, 64, 175) !important;
        }
        .formula-row:hover a,
        .formula-row:hover span,
        .formula-row:hover div {
            color: rgb(30, 64, 175) !important;
        }
        .formula-row:hover .bg-yellow-100,
        .formula-row:hover .bg-blue-100,
        .formula-row:hover .bg-green-100 {
            background-color: rgba(147, 197, 253, 0.5) !important;
            color: rgb(30, 64, 175) !important;
        }
        .formula-row.selected,
        .formula-row.selected td {
            background-color: rgb(191, 219, 254) !important;
            color: rgb(30, 64, 175) !important;
        }
        .formula-row.selected a,
        .formula-row.selected span,
        .formula-row.selected div {
            color: rgb(30, 64, 175) !important;
        }
        .formula-row.selected .bg-yellow-100,
        .formula-row.selected .bg-blue-100,
        .formula-row.selected .bg-green-100 {
            background-color: rgba(147, 197, 253, 0.6) !important;
            color: rgb(30, 64, 175) !important;
        }
        .formula-row:hover .obs-calidad-btn,
        .formula-row.selected .obs-calidad-btn {
            color: rgb(37, 99, 235) !important;
        }
        .formula-row:hover .obs-calidad-btn i,
        .formula-row.selected .obs-calidad-btn i {
            color: rgb(37, 99, 235) !important;
        }
        .formula-row:hover .obs-calidad-btn:hover,
        .formula-row.selected .obs-calidad-btn:hover {
            background-color: rgba(147, 197, 253, 0.8) !important;
        }
        .filtro-col-valores::-webkit-scrollbar {
            width: 6px;
        }
        .filtro-col-valores::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        .filtro-col-valores::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }
        #createModal.view-only-presentation input:not([type="hidden"]),
        #createModal.view-only-presentation select,
        #createModal.view-only-presentation textarea {
            border-color: transparent !important;
            background: transparent !important;
            box-shadow: none !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            color: #111827 !important;
            cursor: default !important;
        }
        #createModal.view-only-presentation select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none !important;
        }
        #createModal.view-only-presentation input:disabled,
        #createModal.view-only-presentation select:disabled,
        #createModal.view-only-presentation textarea:disabled,
        #createModal.view-only-presentation input[readonly],
        #createModal.view-only-presentation textarea[readonly] {
            opacity: 1 !important;
            -webkit-text-fill-color: #111827 !important;
        }
        #createModal.view-only-presentation .cursor-not-allowed,
        #createModal.view-only-presentation .pointer-events-none {
            cursor: default !important;
        }
        #createModal.view-only-presentation .focus\:ring-2,
        #createModal.view-only-presentation .focus\:ring-1,
        #createModal.view-only-presentation .focus\:border-transparent,
        #createModal.view-only-presentation .focus\:border-blue-500,
        #createModal.view-only-presentation .focus\:border-purple-500 {
            --tw-ring-shadow: 0 0 #0000 !important;
        }
        #create_folio_prog_display[readonly] {
            color: #111827;
            -webkit-text-fill-color: #111827;
            opacity: 1;
        }
    </style>
@endsection

@push('scripts')
    @vite('resources/js/modulos/engomado/captura-formula/index.ts')
@endpush
