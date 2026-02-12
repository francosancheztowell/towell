@extends('layouts.app')

@section('page-title', 'Captura de Fórmulas')

@section('navbar-right')
    @php
        $desdeProduccion = !empty($folioFiltro);
    @endphp
    <div class="flex items-center gap-2">
        @if($desdeProduccion)
            <a href="{{ !empty($ordenIdProduccion) ? route('engomado.modulo.produccion.engomado', ['orden_id' => $ordenIdProduccion]) : route('engomado.modulo.produccion.engomado') }}"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition"
                title="Volver a Producción de Engomado">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Producción</span>
            </a>
        @endif
        @if($desdeProduccion)
            <x-navbar.button-create
            onclick="openCreateModal()"
            title="Autorizar Formula"
            module="Captura de Formula"
            />
            <x-navbar.button-delete
            id="btn-delete"
            onclick="confirmDelete()"
            title="Eliminar Fórmula"
            module="Captura de Formula"
            :disabled="true"
            />
            <x-navbar.button-edit
            id="btn-edit"
            onclick="openEditModal()"
            title="Editar"
            bg="bg-purple-500"
            text="Editar"
            iconColor="text-white"
            hoverBg="hover:bg-purple-300"
            class="text-white"
            module="Captura de Formula"
            :disabled="true"
            />
        @endif
        <x-navbar.button-edit
        id="btn-view"
        onclick="openViewModal()"
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
        $puedeVerCalidad = function_exists('userCan') ? userCan('registrar', 'Captura de Formula') : true;
    @endphp
    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Error',
                    text: '{{ session('error') }}',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif
    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif

    <div class="overflow-x-auto overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-md mt-4 mx-4" style="max-height: 70vh;">
        <table id="formulaTable" class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-sm">
                <tr>
                    @if($puedeVerCalidad)
                    <th class="text-center px-4 py-3 font-semibold whitespace-nowrap first:rounded-tl-xl">Calidad</th>
                    @endif
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap {{ $puedeVerCalidad ? '' : 'first:rounded-tl-xl' }}">ID</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Orden</th>
                    <th id="th-fecha" class="text-left px-4 py-3 font-semibold whitespace-nowrap cursor-pointer select-none">Fecha <i class="fa-solid fa-filter text-xs ml-1 opacity-80"></i></th>
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
                    <tr class="formula-row  border-gray-100 cursor-pointer transition-all duration-150 hover:bg-blue-50/80 even:bg-gray-50/50"
                        onclick="selectRow(this, '{{ $item->Folio }}', {{ $item->Id ?? 'null' }})"
                        data-folio="{{ $item->Folio }}"
                        data-id="{{ $item->Id ?? '' }}"
                        data-fecha="{{ ($item->fecha ?? $item->Fecha) ? \Carbon\Carbon::parse($item->fecha ?? $item->Fecha)->format('Y-m-d') : '' }}"
                        data-hora="{{ $item->Hora ? substr($item->Hora, 0, 5) : '' }}"
                        data-status="{{ $item->Status }}"
                        data-cuenta="{{ $item->Cuenta }}"
                        data-calibre="{{ $item->Calibre }}"
                        data-tipo="{{ $item->Tipo }}"
                        data-nomempl="{{ $item->NomEmpl }}"
                        data-cveempl="{{ $item->CveEmpl ?? '' }}"
                        data-olla="{{ $item->Olla }}"
                        data-formula="{{ $item->Formula }}"
                        data-kilos="{{ $item->Kilos }}"
                        data-litros="{{ $item->Litros }}"
                        data-tiempo="{{ $item->TiempoCocinado }}"
                        data-solidos="{{ $item->Solidos }}"
                        data-viscocidad="{{ $item->Viscocidad }}"
                        data-maquina="{{ $item->MaquinaId ?? '' }}"
                        data-prodid="{{ $item->ProdId ?? '' }}"
                        data-oktiempo="{{ $item->OkTiempo === null ? '' : ($item->OkTiempo ? '1' : '0') }}"
                        data-okviscocidad="{{ ($item->OkViscocidad ?? $item->OkViscosidad ?? null) === null ? '' : (($item->OkViscocidad ?? $item->OkViscosidad) ? '1' : '0') }}"
                        data-oksolidos="{{ $item->OkSolidos === null ? '' : ($item->OkSolidos ? '1' : '0') }}"
                    >
                        @if($puedeVerCalidad)
                        <td class="px-4 py-3 text-center">
                            @php
                                $tieneObs = !empty($item->obs_calidad);
                                $iconoCalidad = $tieneObs ? 'fa-clipboard-check' : 'fa-clipboard-list';
                                $tituloCalidad = $tieneObs ? e($item->obs_calidad) : 'Calidad (sin observaciones)';
                            @endphp
                            <x-navbar.button-report
                                onclick="event.stopPropagation(); abrirModalObsCalidad(this)"
                                title="{{ $tituloCalidad }}"
                                icon="{{ $iconoCalidad }}"
                                iconColor="{{ $tieneObs ? 'text-blue-700' : 'text-blue-500' }}"
                                hoverBg="hover:bg-blue-50"
                                module="Captura de Formula"
                                class="obs-calidad-btn"
                                data-folio="{{ $item->Folio }}"
                                data-id="{{ $item->Id ?? '' }}"
                                data-formula="{{ $item->Formula ?? '' }}"
                                data-litros="{{ $item->Litros ?? '' }}"
                                data-tiempo="{{ $item->TiempoCocinado ?? '' }}"
                                data-solidos="{{ $item->Solidos ?? '' }}"
                                data-viscocidad="{{ $item->Viscocidad ?? '' }}"
                                data-oktiempo="{{ $item->OkTiempo === null ? '' : ($item->OkTiempo ? '1' : '0') }}"
                                data-okviscocidad="{{ ($item->OkViscocidad ?? $item->OkViscosidad ?? null) === null ? '' : (($item->OkViscocidad ?? $item->OkViscosidad) ? '1' : '0') }}"
                                data-oksolidos="{{ $item->OkSolidos === null ? '' : ($item->OkSolidos ? '1' : '0') }}"
                                data-has-obs="{{ $tieneObs ? '1' : '0' }}"
                            />
                        </td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-blue-700">{{ $item->Id }}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $item->Folio }}</td>
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

    <!-- Modal Crear/Editar -->
    <div id="createModal" class="hidden fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div id="createModalContent" class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div id="create_modal_header" class="bg-blue-50 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <h3 id="create_modal_title" class="text-xl font-semibold">Nueva Formulación de Engomado</h3>
                <button onclick="cerrarModalCreate()" class="text-white hover:text-gray-200 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            <label class="block text-xs font-medium text-gray-700 mb-1">Folio (Programa Engomado) <span class="text-red-600">*</span></label>
                            <select name="FolioProg" id="create_folio_prog" required onchange="cargarDatosPrograma(this, false)" class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed" style="pointer-events: none;" tabindex="-1">
                                <option value="">-- Seleccione un Folio --</option>
                                @foreach($foliosPrograma as $prog)
                                    <option value="{{ $prog->Folio }}"
                                            data-cuenta="{{ $prog->Cuenta }}"
                                            data-calibre="{{ $prog->Calibre }}"
                                            data-tipo="{{ $prog->RizoPie }}"
                                            data-formula="{{ $prog->BomFormula }}"
                                            {{ isset($folioFiltro) && $folioFiltro === $prog->Folio ? 'selected' : '' }}>
                                        {{ $prog->Folio }} - {{ $prog->Cuenta }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha</label>
                            <input type="date" name="fecha" id="create_fecha" value="{{ date('Y-m-d') }}" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Hora</label>
                            <input type="time" name="Hora" id="create_hora" value="{{ date('H:i') }}" step="60" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" id="create_display_numero" value="{{ auth()->user()->numero_empleado ?? (auth()->user()->numero ?? '') }}" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Operador</label>
                            <input type="text" id="create_display_operador" value="{{ auth()->user()->nombre ?? '' }}" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fórmula</label>
                            <input type="text" name="Formula" id="create_formula" readonly class="campo-siempre-bloqueado w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <!-- Campos ocultos para datos de EngProgramaEngomado -->
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
                    {{-- <h4 class="text-sm font-semibold text-purple-700 mb-2 pb-2 border-b border-purple-200">Datos de Captura</h4> --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
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
                            <label class="block text-xs font-medium text-gray-700 mb-1">Kilos (Kg.) <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0" name="Kilos" id="create_kilos" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="No se aceptan valores negativos">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Litros <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" name="Litros" id="create_litros" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Debe ser mayor a cero">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tiempo Cocinado (Min) <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" name="TiempoCocinado" id="create_tiempo" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Debe ser mayor a cero">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">% Sólidos <span class="text-red-600">*</span></label>
                            <input
                            required
                            type="number" step="0.01" min="0.01" name="Solidos" id="create_solidos" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" title="Debe ser mayor a cero">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Viscosidad <span class="text-red-600">*</span></label>
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
                        <button type="button" id="btn-create-add-row" onclick="agregarFilaComponenteCreate()"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition">
                            <i class="fa-solid fa-plus mr-1"></i>Agregar fila
                        </button>
                    </div>

                    <!-- Loading -->
                    <div id="create_componentes_loading" class="hidden text-center py-6">
                        <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-500"></i>
                        <p class="text-gray-600 mt-2 text-sm">Cargando componentes...</p>
                    </div>

                    <!-- Error -->
                    <div id="create_componentes_error" class="hidden">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                            <i class="fa-solid fa-exclamation-triangle text-red-500 text-2xl mb-1"></i>
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
                                    <!-- Se llenará dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Observaciones de Calidad (solo visible en Ver/Editar cuando hay obs) -->
                <div id="create_obs_section" class="hidden mb-4">
                    <h4 class="text-sm font-semibold text-purple-700 mb-2 pb-2 border-b border-purple-200">Observaciones de Calidad</h4>
                    <div id="create_obs_text" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-gray-700 whitespace-pre-wrap max-h-32 overflow-y-auto"></div>
                </div>

                <!-- Botones -->
                <div id="create-modal-buttons" class="flex gap-2 justify-end pt-3 border-t border-gray-200 mt-4">
                    <button type="button" id="btn-cancel-create" onclick="cerrarModalCreate()"
                            class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 w-full transition">
                        <i class="fa-solid fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" id="btn-submit-create" class="px-4 py-2 text-sm font-medium bg-blue-500 w-full text-white rounded-lg hover:bg-blue-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-save mr-1"></i><span id="submit-text-create">Crear Formulación</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-yellow-700 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <h3 id="edit_modal_title" class="text-xl font-semibold">Editar Formulación</h3>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-white hover:text-gray-200 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="editForm" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <!-- Sección 1: Información General -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-yellow-700 mb-3 pb-2 border-b border-yellow-200">Información General</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hora</label>
                            <input type="time" name="Hora" id="edit_hora" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo Fórmula (Máquina)</label>
                            <select name="MaquinaId" id="edit_maquina" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                                <option value="">Seleccione...</option>
                                @foreach($maquinas as $maquina)
                                    <option value="{{ $maquina->MaquinaId }}">{{ $maquina->Nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cuenta/Título</label>
                            <input type="text" name="Cuenta" id="edit_cuenta" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Operador -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-yellow-700 mb-3 pb-2 border-b border-yellow-200">Operador</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Empleado</label>
                            <select name="NomEmpl" id="edit_empleado" onchange="fillEmpleadoEdit(this)" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                                <option value="">Seleccione...</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->nombre }}" data-numero="{{ $usuario->numero_empleado }}">
                                        {{ $usuario->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Clave Empleado</label>
                            <input type="text" name="CveEmpl" id="edit_cve_empl" readonly class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <!-- Sección 3: Detalles de Formulación -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-yellow-700 mb-3 pb-2 border-b border-yellow-200">Detalles de Formulación</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Olla</label>
                            <input type="text" name="Olla" id="edit_olla" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fórmula</label>
                            <input type="text" name="Formula" id="edit_formula" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Producto AX</label>
                            <input type="text" name="ProdId" id="edit_prod_id" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                    </div>
                </div>

                <!-- Sección 4: Especificaciones Técnicas -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-yellow-700 mb-3 pb-2 border-b border-yellow-200">Especificaciones Técnicas</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Calibre</label>
                            <input type="number" step="0.01" name="Calibre" id="edit_calibre" placeholder="0.00" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                            <input type="text" name="Tipo" id="edit_tipo" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kilos (Kg.)</label>
                            <input type="number" step="0.01" name="Kilos" id="edit_kilos" placeholder="0.00" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                    </div>
                </div>

                <!-- Sección 5: Mediciones -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-yellow-700 mb-3 pb-2 border-b border-yellow-200">Mediciones y Propiedades</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Litros</label>
                            <input type="number" step="0.01" name="Litros" id="edit_litros" placeholder="0.00" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tiempo Cocinado (Min)</label>
                            <input type="number" step="0.01" name="TiempoCocinado" id="edit_tiempo" placeholder="0.00" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">% Sólidos</label>
                            <input type="number" step="0.01" name="Solidos" id="edit_solidos" placeholder="0.00" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Viscosidad</label>
                            <input type="number" step="0.01" name="Viscocidad" id="edit_viscocidad" placeholder="0.00" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                        </div>
                    </div>
                </div>

                <!-- Sección 6: Componentes (solo vista) -->
                <div id="view_componentes_container" class="mb-6 hidden">
                    <h4 class="text-sm font-semibold text-yellow-700 mb-3 pb-2 border-b border-yellow-200">Componentes de la Fórmula</h4>
                    <div id="view_componentes_loading" class="text-center py-6 hidden">
                        <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
                        <p class="text-gray-600 mt-2 text-sm">Cargando componentes...</p>
                    </div>
                    <div id="view_componentes_error" class="hidden">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                            <i class="fa-solid fa-exclamation-triangle text-red-500 text-2xl mb-1"></i>
                            <p class="text-red-700 font-medium text-sm" id="view_componentes_error_message">Error al cargar componentes</p>
                        </div>
                    </div>
                    <div id="view_componentes_table" class="hidden">
                        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left font-semibold rounded-tl-xl">ItemId</th>
                                        <th class="px-4 py-2.5 text-left font-semibold">Nombre</th>
                                        <th class="px-4 py-2.5 text-left font-semibold">ConfigId</th>
                                        <th class="px-4 py-2.5 text-right font-semibold">Consumo Unitario</th>
                                        <th class="px-4 py-2.5 text-right font-semibold">Consumo Total</th>
                                        <th class="px-4 py-2.5 text-left font-semibold">Unidad</th>
                                        <th class="px-4 py-2.5 text-left font-semibold rounded-tr-xl">Almacén</th>
                                    </tr>
                                </thead>
                                <tbody id="view_componentes_tbody" class="bg-white divide-y divide-gray-100">
                                    <!-- Se llenará dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex gap-3 justify-end pt-4 border-t border-gray-200 mt-6">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                            class="px-6 py-3 text-base font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fa-solid fa-times mr-2"></i>Cancelar
                    </button>
                    <button id="btn-ver-componentes" type="button" onclick="abrirModalComponentes()" class="px-6 py-3 text-base font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-flask mr-2"></i>Ver Componentes
                    </button>
                    <button id="btn-edit-submit" type="submit" class="px-6 py-3 text-base font-medium bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-check mr-2"></i>Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Componentes de Fórmula -->
    <div id="componentesModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-7xl w-full max-h-[90vh] overflow-y-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <div>
                    <h3 class="text-xl font-semibold">Componentes de la Fórmula</h3>
                    <p class="text-sm text-blue-100 mt-1">Fórmula: <span id="modal_formula_nombre">-</span></p>
                </div>
                <button onclick="cerrarModalComponentes()" class="text-white hover:text-gray-200 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Contenido -->
            <div class="p-6">
                <!-- Loading -->
                <div id="componentes_loading" class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin text-4xl text-blue-500"></i>
                    <p class="text-gray-600 mt-3">Cargando componentes...</p>
                </div>

                <!-- Error -->
                <div id="componentes_error" class="hidden">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                        <i class="fa-solid fa-exclamation-triangle text-red-500 text-3xl mb-2"></i>
                        <p class="text-red-700 font-medium" id="error_message">Error al cargar componentes</p>
                    </div>
                </div>

                <!-- Tabla de Componentes -->
                <div id="componentes_tabla_container" class="hidden">
                    <!-- Toolbar -->
                    <div class="bg-blue-50 rounded-lg p-4 mb-4 flex justify-between items-center">
                        <div class="flex gap-3">
                            <button id="btn-nuevo-componente" onclick="nuevoComponente()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                <i class="fa-solid fa-plus mr-2"></i>Nuevo
                            </button>
                            <button id="btn-editar-componente" onclick="editarComponente()" disabled class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-edit mr-2"></i>Editar
                            </button>
                            <button id="btn-eliminar-componente" onclick="eliminarComponenteSeleccionado()" disabled class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-trash mr-2"></i>Eliminar
                            </button>
                        </div>
                        <div class="text-sm text-gray-600">
                            Total: <span id="total_componentes" class="font-bold text-blue-700">0</span> componentes
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="overflow-x-auto border rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Folio</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">ItemId</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">ItemName</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">ConfigId</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold">Consumo Unitario</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold">Consumo Total</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Unidad</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Almacén</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="componentes_tbody" class="bg-white divide-y divide-gray-200">
                                <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end gap-3">
                <button type="button" onclick="cerrarModalComponentes()" class="px-6 py-3 text-base font-medium border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                    <i class="fa-solid fa-times mr-2"></i>Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Form para eliminar -->
    <form id="deleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
        <input type="hidden" name="formulacion_id" id="delete_formulacion_id">
    </form>

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
    </style>

    <script>
        let selectedRow = null;
        let selectedFolio = null;
        let selectedId = null;
        let viewOnlyMode = false;
        let editMode = false;
        let editFormInitialSnapshot = null;
        const observaciones = {};
        let fechaSortAsc = null;
        const desdeProduccion = {{ $desdeProduccion ? 'true' : 'false' }};

        function selectRow(row, folio, id) {
            document.querySelectorAll('#formulaTable tbody tr.selected').forEach(existing => {
                existing.classList.remove('selected');
            });
            selectedRow = row;
            selectedFolio = folio;
            selectedId = id;
            row.classList.add('selected');

            enableButtons();
        }

        function openCreateModal(readOnly = false) {
            editMode = false;
            editFormInitialSnapshot = null;
            viewOnlyMode = readOnly;
            const modal = document.getElementById('createModal');
            if (modal) {
                modal.classList.remove('hidden');
            }

            // Configurar modal para CREAR
            document.getElementById('create_modal_title').textContent = 'Nueva Formulación de Engomado';
            document.getElementById('create_method').value = 'POST';
            const form = document.getElementById('createForm');
            if (form) {
                form.action = "{{ route('eng-formulacion.store') }}";
            }

            // Actualizar botón submit (siempre habilitado al crear)
            const submitBtn = document.getElementById('btn-submit-create');
            const submitText = document.getElementById('submit-text-create');
            if (submitText) submitText.textContent = 'Crear Formulación';
            if (submitBtn) {
                submitBtn.classList.remove('bg-yellow-600', 'hover:bg-yellow-700', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                submitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                submitBtn.disabled = false;
            }

            // Color azul para crear
            const header = document.getElementById('create_modal_header');
            if (header) {
                header.className = 'bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10';
            }

            // Restaurar altura normal del modal
            const modalContent = document.getElementById('createModalContent');
            if (modalContent) {
                modalContent.classList.remove('max-h-[70vh]');
                modalContent.classList.add('max-h-[90vh]');
            }

            document.getElementById('create_formulacion_id').value = '';
            document.getElementById('create_obs_section')?.classList.add('hidden');
            // LIMPIAR todos los campos (para crear nuevo)
            document.getElementById('create_olla').value = '';
            document.getElementById('create_kilos').value = '0';
            document.getElementById('create_litros').value = '0';
            document.getElementById('create_tiempo').value = '0';
            document.getElementById('create_solidos').value = '0';
            document.getElementById('create_viscocidad').value = '0';
            document.getElementById('create_obs_calidad').value = '';

            kilosCreateFormula = 0;
            litrosCreateFormula = 0;
            componentesCreateData = [];
            formulaCreateActual = '';

            // Si hay un folio seleccionado en el dropdown, cargar sus datos básicos
            const select = document.getElementById('create_folio_prog');
            if (select && select.value) {
                cargarDatosPrograma(select, false);
            }

            setCreateModalReadOnly(readOnly);
        }

        function cerrarModalCreate() {
            const modal = document.getElementById('createModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function disableButtons() {
            ['btn-edit', 'btn-view', 'btn-delete'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            });
        }

        function enableButtons() {
            ['btn-edit', 'btn-view', 'btn-delete'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        }

        function obtenerFechaRow(row) {
            let rowFecha = row.dataset.fecha || '';
            if (!rowFecha) {
                const cellFecha = (row.cells[1]?.textContent || '').trim();
                if (cellFecha.includes('/')) {
                    const parts = cellFecha.split('/');
                    if (parts.length === 3) {
                        const [d, m, y] = parts;
                        rowFecha = `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
                    }
                }
            }
            return rowFecha;
        }

        function ordenarPorFecha(asc) {
            const tbody = document.getElementById('formulaTableBody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr[data-folio]'));
            const dataRows = rows.map((row, index) => ({
                row,
                index,
                fecha: obtenerFechaRow(row)
            }));

            dataRows.sort((a, b) => {
                const aFecha = a.fecha;
                const bFecha = b.fecha;

                if (!aFecha && !bFecha) return a.index - b.index;
                if (!aFecha) return 1;
                if (!bFecha) return -1;
                if (aFecha === bFecha) return a.index - b.index;
                return asc ? aFecha.localeCompare(bFecha) : bFecha.localeCompare(aFecha);
            });

            dataRows.forEach(item => tbody.appendChild(item.row));
        }

        function toggleOrdenFecha() {
            fechaSortAsc = fechaSortAsc === null ? false : !fechaSortAsc;
            ordenarPorFecha(fechaSortAsc);
            const thFecha = document.getElementById('th-fecha');
            if (thFecha) {
                thFecha.setAttribute('aria-sort', fechaSortAsc ? 'ascending' : 'descending');
            }
        }

        function openEditModal() {
            if (!selectedRow || !selectedFolio || !selectedId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selección requerida',
                    text: 'Debe seleccionar una fórmula primero',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Validar que selectedId sea un número válido
            const formulacionId = parseInt(selectedId);
            if (isNaN(formulacionId) || formulacionId <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'ID inválido',
                    text: 'El ID de la formulación no es válido: ' + selectedId,
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            editMode = true;
            viewOnlyMode = false;
            const modal = document.getElementById('createModal');
            if (modal) {
                modal.classList.remove('hidden');
            }

            // Configurar modal para EDITAR
            document.getElementById('create_modal_title').textContent = 'Editar Formulación';
            document.getElementById('create_method').value = 'PUT';
            const form = document.getElementById('createForm');
            if (form) {
                form.action = `/eng-formulacion/${selectedFolio}`;
            }

            // Actualizar botón submit (deshabilitado hasta que haya cambios)
            const submitBtn = document.getElementById('btn-submit-create');
            const submitText = document.getElementById('submit-text-create');
            if (submitText) submitText.textContent = 'Guardar Cambios';
            if (submitBtn) {
                submitBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                submitBtn.classList.add('bg-yellow-600', 'hover:bg-yellow-700');
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }

            // Cambiar color del header a amarillo para editar
            const header = document.getElementById('create_modal_header');
            if (header) {
                header.className = 'bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10';
            }

            // Restaurar altura normal del modal
            const modalContent = document.getElementById('createModalContent');
            if (modalContent) {
                modalContent.classList.remove('max-h-[70vh]');
                modalContent.classList.add('max-h-[90vh]');
            }

            // Mostrar loading
            document.getElementById('create_componentes_loading').classList.remove('hidden');
            document.getElementById('create_componentes_error').classList.add('hidden');
            document.getElementById('create_componentes_tabla_container').classList.add('hidden');

            // IMPORTANTE: Cargar datos completos desde la BD por ID específico
            // GET directo a EngFormulacionLine WHERE EngProduccionFormulacionId = {formulacionId}
            fetch(`/eng-formulacion/by-id?id=${formulacionId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    document.getElementById('create_componentes_error').classList.add('hidden');

                    if (data.success && data.formulacion) {
                        const form = data.formulacion;

                        // Validar que el ID de la formulación coincida
                        if (parseInt(form.Id) !== formulacionId) {
                            console.error('Error: El ID de la formulación no coincide', {
                                esperado: formulacionId,
                                recibido: form.Id
                            });
                            mostrarErrorComponentesCreate('Error: El ID de la formulación no coincide');
                            return;
                        }

                        // IMPORTANTE: En modo editar, NO llamar a cargarDatosPrograma() porque sobrescribe los componentes
                        // Solo establecer el valor del select sin disparar el evento onchange
                        const select = document.getElementById('create_folio_prog');
                        if (select) {
                            // Guardar el valor actual de componentes antes de cambiar el select
                            const componentesGuardados = [...componentesCreateData];

                            // Remover temporalmente el evento onchange para evitar que se dispare
                            const originalOnchange = select.getAttribute('onchange');
                            select.removeAttribute('onchange');

                            // Establecer el valor sin disparar eventos
                            select.value = form.Folio;

                            // Restaurar el evento onchange
                            if (originalOnchange) {
                                select.setAttribute('onchange', originalOnchange);
                            }

                            // Restaurar los componentes que ya cargamos desde la BD
                            componentesCreateData = componentesGuardados;

                            // Solo llenar los campos ocultos sin cargar componentes desde AX
                            document.getElementById('create_cuenta').value = form.Cuenta || '';
                            document.getElementById('create_calibre').value = form.Calibre || '';
                            document.getElementById('create_tipo').value = form.Tipo || '';
                            document.getElementById('create_formula').value = form.Formula || '';
                            formulaCreateActual = form.Formula || '';
                        }

                        document.getElementById('create_olla').value = form.Olla || '';

                        // Actualizar valores de Kilos y Litros ANTES de renderizar componentes
                        // para que el cálculo de ConsumoTotal sea correcto
                        const kilosInput = document.getElementById('create_kilos');
                        if (kilosInput) {
                            kilosInput.value = form.Kilos || '0';
                            kilosCreateFormula = parseFloat(form.Kilos) || 0;
                        }

                        const litrosInput = document.getElementById('create_litros');
                        if (litrosInput) {
                            litrosInput.value = form.Litros || '0';
                            litrosCreateFormula = parseFloat(form.Litros) || 0;
                        }

                        document.getElementById('create_tiempo').value = form.TiempoCocinado || '0';
                        document.getElementById('create_solidos').value = form.Solidos || '0';
                        document.getElementById('create_viscocidad').value = form.Viscocidad || '0';

                        document.getElementById('create_nom_empl').value = form.NomEmpl || '';
                        document.getElementById('create_cve_empl').value = form.CveEmpl || '';
                        document.getElementById('create_obs_calidad').value = form.obs_calidad || '';
                        const displayNum = document.getElementById('create_display_numero');
                        const displayOper = document.getElementById('create_display_operador');
                        if (displayNum) displayNum.value = form.CveEmpl || '';
                        if (displayOper) displayOper.value = form.NomEmpl || '';

                        const obsSection = document.getElementById('create_obs_section');
                        const obsText = document.getElementById('create_obs_text');
                        if (obsSection && obsText) {
                            if (form.obs_calidad) {
                                obsText.textContent = form.obs_calidad;
                                obsSection.classList.remove('hidden');
                            } else {
                                obsSection.classList.add('hidden');
                            }
                        }

                        formulaCreateActual = form.Formula || '';

                        // IMPORTANTE: Cargar componentes desde EngFormulacionLine filtrados por EngProduccionFormulacionId
                        // Estos componentes vienen directamente de SELECT * FROM EngFormulacionLine WHERE EngProduccionFormulacionId = {formulacionId}
                        // NO usar cargarDatosPrograma() porque carga componentes desde AX y sobrescribe estos
                        if (data.componentes && data.componentes.length > 0) {
                            // Mapear componentes preservando todos los datos
                            componentesCreateData = data.componentes.map(comp => ({
                                Id: comp.Id,
                                ItemId: comp.ItemId || '',
                                ItemName: comp.ItemName || '',
                                ConfigId: comp.ConfigId || '',
                                ConsumoUnitario: comp.ConsumoUnitario || 0,
                                ConsumoTotal: comp.ConsumoTotal || 0,
                                Unidad: comp.Unidad || '',
                                Almacen: comp.Almacen || '',
                                esNuevo: false // No es nuevo, viene de la BD
                            }));

                            renderizarTablaComponentesCreate();
                            document.getElementById('create_componentes_tabla_container').classList.remove('hidden');
                        } else {
                            componentesCreateData = [];
                            renderizarTablaComponentesCreate();
                            document.getElementById('create_componentes_tabla_container').classList.add('hidden');
                        }
                        document.getElementById('create_formulacion_id').value = formulacionId;
                        editFormInitialSnapshot = obtenerSnapshotFormulacionCreate();
                        actualizarBotonGuardarEdicion();
                    } else {
                        console.error('Error al cargar formulación:', data.error);
                        mostrarErrorComponentesCreate(data.error || 'Error al cargar la formulación');
                    }
                })
                .catch(error => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    mostrarErrorComponentesCreate('Error de conexión: ' + error.message);
                });

            setCreateModalReadOnly(false);
        }

        function obtenerSnapshotFormulacionCreate() {
            const componentes = obtenerComponentesCreateDesdeTabla();
            const conCalibre = componentes.filter(c => (c.ItemId || '').trim()).map(c => ({
                ItemId: c.ItemId,
                ItemName: c.ItemName,
                ConfigId: c.ConfigId,
                ConsumoTotal: c.ConsumoTotal,
                ConsumoUnitario: c.ConsumoUnitario
            }));
            return JSON.stringify({
                Olla: document.getElementById('create_olla')?.value || '',
                Kilos: document.getElementById('create_kilos')?.value || '0',
                Litros: document.getElementById('create_litros')?.value || '0',
                TiempoCocinado: document.getElementById('create_tiempo')?.value || '0',
                Solidos: document.getElementById('create_solidos')?.value || '0',
                Viscocidad: document.getElementById('create_viscocidad')?.value || '0',
                NomEmpl: document.getElementById('create_nom_empl')?.value || '',
                CveEmpl: document.getElementById('create_cve_empl')?.value || '',
                obs_calidad: document.getElementById('create_obs_calidad')?.value || '',
                componentes: conCalibre
            });
        }

        function haCambiadoFormulacionCreate() {
            if (!editFormInitialSnapshot) return false;
            const actual = obtenerSnapshotFormulacionCreate();
            return actual !== editFormInitialSnapshot;
        }

        function actualizarBotonGuardarEdicion() {
            const submitBtn = document.getElementById('btn-submit-create');
            if (!submitBtn || !editMode || viewOnlyMode) return;
            const method = document.getElementById('create_method');
            if (method?.value !== 'PUT') return;
            const hayCambios = haCambiadoFormulacionCreate();
            submitBtn.disabled = !hayCambios;
            submitBtn.classList.toggle('opacity-50', !hayCambios);
            submitBtn.classList.toggle('cursor-not-allowed', !hayCambios);
            submitBtn.classList.toggle('pointer-events-none', !hayCambios);
        }

        function openViewModal() {
            if (!selectedRow || !selectedFolio || !selectedId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selección requerida',
                    text: 'Debe seleccionar una fórmula primero',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const formulacionId = parseInt(selectedId);
            if (isNaN(formulacionId) || formulacionId <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'ID inválido',
                    text: 'El ID de la formulación no es válido: ' + selectedId,
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            editMode = true;
            viewOnlyMode = true;
            const modal = document.getElementById('createModal');
            if (modal) {
                modal.classList.remove('hidden');
            }

            // Configurar modal para VER
            document.getElementById('create_modal_title').textContent = 'Visualización de Fórmula';

            // Cambiar color del header a azul para ver
            const header = document.getElementById('create_modal_header');
            if (header) {
                header.className = 'bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10';
            }

            // Reducir altura del modal en modo ver
            const modalContent = document.getElementById('createModalContent');
            if (modalContent) {
                modalContent.classList.remove('max-h-[90vh]');
                modalContent.classList.add('max-h-[70vh]');
            }

            // Mostrar loading
            document.getElementById('create_componentes_loading').classList.remove('hidden');
            document.getElementById('create_componentes_error').classList.add('hidden');
            document.getElementById('create_componentes_tabla_container').classList.add('hidden');

            // Cargar datos completos desde la BD por ID (igual que editar)
            fetch(`/eng-formulacion/by-id?id=${selectedId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    document.getElementById('create_componentes_error').classList.add('hidden');

                    if (data.success && data.formulacion) {
                        const form = data.formulacion;

                        // Validar que el ID de la formulación coincida
                        if (parseInt(form.Id) !== formulacionId) {
                            console.error('Error: El ID de la formulación no coincide', {
                                esperado: formulacionId,
                                recibido: form.Id
                            });
                            mostrarErrorComponentesCreate('Error: El ID de la formulación no coincide');
                            return;
                        }

                        // Llenar campos del formulario SIN llamar a cargarDatosPrograma
                        // (eso carga desde BOM y sobrescribe los componentes de EngFormulacionLine)
                        const select = document.getElementById('create_folio_prog');
                        if (select) {
                            const originalOnchange = select.getAttribute('onchange');
                            select.removeAttribute('onchange');
                            select.value = form.Folio;
                            if (originalOnchange) select.setAttribute('onchange', originalOnchange);
                            document.getElementById('create_cuenta').value = form.Cuenta || '';
                            document.getElementById('create_calibre').value = form.Calibre || '';
                            document.getElementById('create_tipo').value = form.Tipo || '';
                            document.getElementById('create_formula').value = form.Formula || '';
                            formulaCreateActual = form.Formula || '';
                        }

                        document.getElementById('create_olla').value = form.Olla || '';

                        const kilosInput = document.getElementById('create_kilos');
                        if (kilosInput) {
                            kilosInput.value = form.Kilos || '0';
                            kilosCreateFormula = parseFloat(form.Kilos) || 0;
                        }

                        const litrosInput = document.getElementById('create_litros');
                        if (litrosInput) {
                            litrosInput.value = form.Litros || '0';
                            litrosCreateFormula = parseFloat(form.Litros) || 0;
                        }

                        document.getElementById('create_tiempo').value = form.TiempoCocinado || '0';
                        document.getElementById('create_solidos').value = form.Solidos || '0';
                        document.getElementById('create_viscocidad').value = form.Viscocidad || '0';

                        document.getElementById('create_nom_empl').value = form.NomEmpl || '';
                        document.getElementById('create_cve_empl').value = form.CveEmpl || '';
                        document.getElementById('create_obs_calidad').value = form.obs_calidad || '';
                        const displayNum = document.getElementById('create_display_numero');
                        const displayOper = document.getElementById('create_display_operador');
                        if (displayNum) displayNum.value = form.CveEmpl || '';
                        if (displayOper) displayOper.value = form.NomEmpl || '';

                        const obsSection = document.getElementById('create_obs_section');
                        const obsText = document.getElementById('create_obs_text');
                        if (obsSection && obsText) {
                            if (form.obs_calidad) {
                                obsText.textContent = form.obs_calidad;
                                obsSection.classList.remove('hidden');
                            } else {
                                obsSection.classList.add('hidden');
                            }
                        }

                        formulaCreateActual = form.Formula || '';

                        // IMPORTANTE: Cargar componentes desde EngFormulacionLine filtrados por EngProduccionFormulacionId
                        // Estos componentes vienen directamente de SELECT * FROM EngFormulacionLine WHERE EngProduccionFormulacionId = {formulacionId}
                        if (data.componentes && data.componentes.length > 0) {
                            componentesCreateData = data.componentes.map(comp => ({
                                Id: comp.Id,
                                ItemId: comp.ItemId || '',
                                ItemName: comp.ItemName || '',
                                ConfigId: comp.ConfigId || '',
                                ConsumoUnitario: comp.ConsumoUnitario || 0,
                                ConsumoTotal: comp.ConsumoTotal || 0,
                                Unidad: comp.Unidad || '',
                                Almacen: comp.Almacen || '',
                                esNuevo: false // No es nuevo, viene de la BD
                            }));
                            renderizarTablaComponentesCreate();
                            document.getElementById('create_componentes_tabla_container').classList.remove('hidden');
                        } else {
                            componentesCreateData = [];
                            renderizarTablaComponentesCreate();
                            document.getElementById('create_componentes_tabla_container').classList.add('hidden');
                        }
                    } else {
                        console.error('Error al cargar formulación:', data.error);
                        mostrarErrorComponentesCreate(data.error || 'Error al cargar la formulación');
                    }
                })
                .catch(error => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    mostrarErrorComponentesCreate('Error de conexión: ' + error.message);
                });

            setCreateModalReadOnly(true);
        }

        function setEditModalReadOnly(isReadOnly) {
            const modal = document.getElementById('editModal');
            const title = document.getElementById('edit_modal_title');
            const submitBtn = document.getElementById('btn-edit-submit');
            const btnVerComponentes = document.getElementById('btn-ver-componentes');
            const viewComponentesContainer = document.getElementById('view_componentes_container');
            const fields = modal ? modal.querySelectorAll('input, select, textarea') : [];

            fields.forEach(field => {
                if (isReadOnly) {
                    field.setAttribute('disabled', 'disabled');
                } else {
                    field.removeAttribute('disabled');
                }
            });

            if (submitBtn) {
                submitBtn.classList.toggle('hidden', isReadOnly);
            }
            if (title) {
                title.textContent = isReadOnly ? 'Ver Formulación' : 'Editar Formulación';
            }
            if (btnVerComponentes) {
                btnVerComponentes.classList.toggle('hidden', isReadOnly);
            }
            if (viewComponentesContainer) {
                viewComponentesContainer.classList.toggle('hidden', !isReadOnly);
            }
        }

        function setCreateModalReadOnly(isReadOnly) {
            const modal = document.getElementById('createModal');
            if (!modal) return;

            const fields = modal.querySelectorAll('input, select, textarea');
            fields.forEach(field => {
                // NUNCA desbloquear: Folio, Fecha, Hora, No Empleado, Operador, Fórmula
                if (field.classList.contains('campo-siempre-bloqueado')) {
                    field.setAttribute('readonly', 'readonly');
                    // No usar disabled en inputs con name (fecha, Hora, Formula) para que se envíen
                    if (field.tagName === 'SELECT') {
                        field.style.pointerEvents = 'none';
                        field.setAttribute('tabindex', '-1');
                    }
                    if (!field.name || field.id === 'create_display_numero' || field.id === 'create_display_operador') {
                        field.setAttribute('disabled', 'disabled');
                    }
                    field.classList.add('bg-gray-50', 'cursor-not-allowed');
                    return;
                }
                if (isReadOnly) {
                    field.setAttribute('readonly', 'readonly');
                    field.classList.add('bg-gray-50', 'text-gray-700', 'cursor-not-allowed');
                    if (field.tagName === 'SELECT') {
                        field.setAttribute('disabled', 'disabled');
                        field.classList.add('pointer-events-none');
                    }
                } else {
                    field.removeAttribute('readonly');
                    field.removeAttribute('disabled');
                    field.classList.remove('bg-gray-50', 'text-gray-700', 'cursor-not-allowed', 'pointer-events-none');
                }
            });

            const addRowBtn = document.getElementById('btn-create-add-row');
            if (addRowBtn) {
                addRowBtn.classList.toggle('hidden', isReadOnly);
                addRowBtn.disabled = isReadOnly;
                addRowBtn.classList.toggle('opacity-50', isReadOnly);
                addRowBtn.classList.toggle('cursor-not-allowed', isReadOnly);
            }

            const submitBtn = document.getElementById('btn-submit-create');
            if (submitBtn) {
                submitBtn.classList.toggle('hidden', isReadOnly);
                submitBtn.disabled = isReadOnly;
            }

            const cancelBtn = document.getElementById('btn-cancel-create');
            if (cancelBtn) {
                cancelBtn.classList.toggle('hidden', isReadOnly);
            }
        }

        function fillEditModalFromRow(row) {
            const data = row.dataset || {};
            const editForm = document.getElementById('editForm');
            if (editForm && data.folio) {
                editForm.action = `/eng-formulacion/${data.folio}`;
            }

            const horaInput = document.getElementById('edit_hora');
            if (horaInput) horaInput.value = data.hora || '';

            const maquinaSelect = document.getElementById('edit_maquina');
            if (maquinaSelect) maquinaSelect.value = data.maquina || '';

            const cuentaInput = document.getElementById('edit_cuenta');
            if (cuentaInput) cuentaInput.value = data.cuenta || '';

            const empleadoSelect = document.getElementById('edit_empleado');
            if (empleadoSelect) {
                empleadoSelect.value = data.nomempl || '';
                if (data.nomempl) {
                    fillEmpleadoEdit(empleadoSelect);
                }
            }

            const cveInput = document.getElementById('edit_cve_empl');
            if (cveInput) cveInput.value = data.cveempl || '';

            const ollaInput = document.getElementById('edit_olla');
            if (ollaInput) ollaInput.value = data.olla || '';

            const formulaInput = document.getElementById('edit_formula');
            if (formulaInput) formulaInput.value = data.formula || '';

            const prodInput = document.getElementById('edit_prod_id');
            if (prodInput) prodInput.value = data.prodid || '';

            const calibreInput = document.getElementById('edit_calibre');
            if (calibreInput) calibreInput.value = data.calibre || '';

            const tipoInput = document.getElementById('edit_tipo');
            if (tipoInput) tipoInput.value = data.tipo || '';

            const kilosInput = document.getElementById('edit_kilos');
            if (kilosInput) kilosInput.value = data.kilos || '';

            const litrosInput = document.getElementById('edit_litros');
            if (litrosInput) litrosInput.value = data.litros || '';

            const tiempoInput = document.getElementById('edit_tiempo');
            if (tiempoInput) tiempoInput.value = data.tiempo || '';

            const solidosInput = document.getElementById('edit_solidos');
            if (solidosInput) solidosInput.value = data.solidos || '';

            const viscInput = document.getElementById('edit_viscocidad');
            if (viscInput) viscInput.value = data.viscocidad || '';

            formulaActual = data.formula || '';
            kilosFormula = parseFloat(data.kilos) || 0;
        }

        function cargarComponentesVista() {
            if (!formulaActual) {
                return;
            }

            const loading = document.getElementById('view_componentes_loading');
            const errorBox = document.getElementById('view_componentes_error');
            const tableBox = document.getElementById('view_componentes_table');
            if (loading) loading.classList.remove('hidden');
            if (errorBox) errorBox.classList.add('hidden');
            if (tableBox) tableBox.classList.add('hidden');

            fetch(`/eng-formulacion/componentes/formula?formula=${encodeURIComponent(formulaActual)}`)
                .then(response => response.json())
                .then(data => {
                    if (loading) loading.classList.add('hidden');
                    if (errorBox) errorBox.classList.add('hidden');

                    if (data.success) {
                        const componentes = data.componentes || [];
                        renderizarTablaComponentesVista(componentes);
                        if (tableBox) tableBox.classList.remove('hidden');
                    } else {
                        mostrarErrorComponentesVista(data.error || 'Error al cargar componentes');
                    }
                })
                .catch(error => {
                    if (loading) loading.classList.add('hidden');
                    mostrarErrorComponentesVista('Error de conexión: ' + error.message);
                });
        }

        function renderizarTablaComponentesVista(componentes) {
            const tbody = document.getElementById('view_componentes_tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            if (!componentes || componentes.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                            No hay componentes para esta fórmula
                        </td>
                    </tr>
                `;
                return;
            }

            componentes.forEach((comp, idx) => {
                const consumoUnitario = parseFloat(comp.ConsumoUnitario) || 0;
                const consumoTotal = (parseFloat(kilosFormula) || 0) * consumoUnitario;
                const row = document.createElement('tr');
                row.className = 'hover:bg-blue-50/50 transition-colors' + (idx % 2 === 1 ? ' bg-gray-50/50' : '');
                row.innerHTML = `
                    <td class="px-4 py-2 text-sm font-medium">${comp.ItemId || ''}</td>
                    <td class="px-4 py-2 text-sm">${comp.ItemName || ''}</td>
                    <td class="px-4 py-2 text-sm">${comp.ConfigId || ''}</td>
                    <td class="px-4 py-2 text-sm text-right">${consumoUnitario.toFixed(4)}</td>
                    <td class="px-4 py-2 text-sm text-right font-semibold text-blue-700">${consumoTotal.toFixed(4)}</td>
                    <td class="px-4 py-2 text-sm">${comp.Unidad || ''}</td>
                    <td class="px-4 py-2 text-sm">${comp.Almacen || ''}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function mostrarErrorComponentesVista(mensaje) {
            const errorBox = document.getElementById('view_componentes_error');
            const errorMsg = document.getElementById('view_componentes_error_message');
            if (errorBox && errorMsg) {
                errorBox.classList.remove('hidden');
                errorMsg.textContent = mensaje;
            }
        }

        function confirmDelete() {
            if (!selectedFolio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }

            Swal.fire({
                title: '¿Estás seguro?',
                html: `Se eliminará la formulación con folio <strong>${selectedFolio}</strong> y todas sus líneas asociadas`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteForm');
                    form.action = `/eng-formulacion/${selectedFolio}`;
                    document.getElementById('delete_formulacion_id').value = selectedId || '';
                    form.submit();
                }
            });
        }

        function cargarDatosPrograma(select, mostrarAlerta = true) {
            const option = select.options[select.selectedIndex];

            if (!option.value) {
                // Limpiar campos si no hay selección
                document.getElementById('create_cuenta').value = '';
                document.getElementById('create_calibre').value = '';
                document.getElementById('create_tipo').value = '';
                document.getElementById('create_formula').value = '';
                formulaCreateActual = '';
                componentesCreateData = [];
                renderizarTablaComponentesCreate();
                document.getElementById('create_componentes_tabla_container').classList.add('hidden');
                document.getElementById('create_componentes_loading').classList.add('hidden');
                document.getElementById('create_componentes_error').classList.add('hidden');
                return;
            }

            // Obtener datos del option seleccionado
            const cuenta = option.getAttribute('data-cuenta') || '';
            const calibre = option.getAttribute('data-calibre') || '';
            const tipo = option.getAttribute('data-tipo') || '';
            const formula = option.getAttribute('data-formula') || '';

            // Llenar campos ocultos
            document.getElementById('create_cuenta').value = cuenta;
            document.getElementById('create_calibre').value = calibre;
            document.getElementById('create_tipo').value = tipo;
            document.getElementById('create_formula').value = formula;
            formulaCreateActual = formula;
            cargarComponentesCreate(formulaCreateActual);

            // Obtener operador actual del sistema (ya está cargado en Auth)
            @if(Auth::check())
                document.getElementById('create_nom_empl').value = '{{ Auth::user()->nombre ?? "" }}';
                document.getElementById('create_cve_empl').value = '{{ Auth::user()->numero ?? "" }}';
            @endif

            // Al cargar datos ya no se muestra alerta
        }

        function fillEmpleadoEdit(select) {
            const option = select.options[select.selectedIndex];
            document.getElementById('edit_cve_empl').value = option.getAttribute('data-numero') || '';
        }

        // ===== FUNCIONES PARA MODAL DE COMPONENTES =====
        let componentesData = [];
        let formulaActual = '';
        let kilosFormula = 0;
        let componentesCreateData = [];
        let formulaCreateActual = '';
        let kilosCreateFormula = 0;
        let litrosCreateFormula = 0;

        function abrirModalComponentes(kilos = 0) {
            if (!formulaActual) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Fórmula no especificada',
                    text: 'Debe seleccionar un registro primero',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            kilosFormula = parseFloat(kilos) || 0;

            // Mostrar modal
            document.getElementById('componentesModal').classList.remove('hidden');
            document.getElementById('modal_formula_nombre').textContent = formulaActual;

            // Mostrar loading
            document.getElementById('componentes_loading').classList.remove('hidden');
            document.getElementById('componentes_error').classList.add('hidden');
            document.getElementById('componentes_tabla_container').classList.add('hidden');
            actualizarToolbarComponentes();

            // Cargar componentes desde el servidor
            fetch(`/eng-formulacion/componentes/formula?formula=${encodeURIComponent(formulaActual)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('componentes_loading').classList.add('hidden');
                    document.getElementById('componentes_error').classList.add('hidden');

                    if (data.success) {
                        componentesData = data.componentes || [];
                        document.getElementById('componentes_tabla_container').classList.remove('hidden');
                        renderizarTablaComponentes();
                        actualizarToolbarComponentes();

                        // Mostrar alerta si está vacío
                        if (data.vacio || componentesData.length === 0) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Sin componentes',
                                text: 'No se encontraron componentes para la fórmula "' + formulaActual + '"',
                                confirmButtonColor: '#3b82f6',
                                timer: 3000
                            });
                        }
                    } else {
                        mostrarErrorComponentes(data.error || 'Error al cargar componentes');
                    }
                })
                .catch(error => {
                    document.getElementById('componentes_loading').classList.add('hidden');
                    mostrarErrorComponentes('Error de conexión: ' + error.message);
                });
        }

        function cerrarModalComponentes() {
            document.getElementById('componentesModal').classList.add('hidden');
            componentesData = [];
        }

        function mostrarErrorComponentes(mensaje) {
            document.getElementById('componentes_error').classList.remove('hidden');
            document.getElementById('error_message').textContent = mensaje;
        }

        let selectedComponenteIndex = null;
        let selectedComponenteRow = null;

        function actualizarToolbarComponentes() {
            const btnNuevo = document.getElementById('btn-nuevo-componente');
            const btnEditar = document.getElementById('btn-editar-componente');
            const btnEliminar = document.getElementById('btn-eliminar-componente');

            if (btnNuevo) {
                btnNuevo.disabled = viewOnlyMode;
                btnNuevo.classList.toggle('opacity-50', viewOnlyMode);
                btnNuevo.classList.toggle('cursor-not-allowed', viewOnlyMode);
            }

            if (btnEditar) {
                const disabled = viewOnlyMode || selectedComponenteIndex === null;
                btnEditar.disabled = disabled;
                btnEditar.classList.toggle('opacity-50', disabled);
                btnEditar.classList.toggle('cursor-not-allowed', disabled);
            }

            if (btnEliminar) {
                const disabled = viewOnlyMode || selectedComponenteIndex === null;
                btnEliminar.disabled = disabled;
                btnEliminar.classList.toggle('opacity-50', disabled);
                btnEliminar.classList.toggle('cursor-not-allowed', disabled);
            }
        }

        function renderizarTablaComponentes() {
            const tbody = document.getElementById('componentes_tbody');
            tbody.innerHTML = '';
            selectedComponenteIndex = null;
            selectedComponenteRow = null;
            actualizarToolbarComponentes();

            if (componentesData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            No hay componentes para esta fórmula
                        </td>
                    </tr>
                `;
                return;
            }

            componentesData.forEach((comp, index) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-blue-50 transition-colors cursor-pointer';
                row.onclick = () => seleccionarComponente(index, row);

                // Validar y convertir valores numéricos
                const consumoUnitario = parseFloat(comp.ConsumoUnitario) || 0;
                const consumoTotal = calcularConsumoTotal(consumoUnitario);

                row.innerHTML = `
                    <td class="px-4 py-3 text-sm">${selectedFolio || '-'}</td>
                    <td class="px-4 py-3 text-sm font-medium">${comp.ItemId || ''}</td>
                    <td class="px-4 py-3 text-sm">${comp.ItemName || ''}</td>
                    <td class="px-4 py-3 text-sm">${comp.ConfigId || ''}</td>
                    <td class="px-4 py-3 text-sm text-right">${consumoUnitario.toFixed(4)}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-blue-700">
                        ${consumoTotal.toFixed(4)}
                    </td>
                    <td class="px-4 py-3 text-sm">${comp.Unidad || ''}</td>
                    <td class="px-4 py-3 text-sm">${comp.Almacen || ''}</td>
                    <td class="px-4 py-3 text-center">
                        <i class="fa-solid fa-bars text-gray-400"></i>
                    </td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('total_componentes').textContent = componentesData.length;
        }

        function seleccionarComponente(index, row) {
            // Limpiar selección anterior
            if (selectedComponenteRow) {
                selectedComponenteRow.classList.remove('bg-blue-100');
            }

            // Nueva selección
            selectedComponenteIndex = index;
            selectedComponenteRow = row;
            row.classList.add('bg-blue-100');

            // Habilitar botones
            actualizarToolbarComponentes();
        }

        function habilitarBotonesComponente() {
            actualizarToolbarComponentes();
        }

        function deshabilitarBotonesComponente() {
            actualizarToolbarComponentes();
        }

        function calcularConsumoTotal(consumoUnitario) {
            return (consumoUnitario || 0) * kilosFormula;
        }

        function nuevoComponente() {
            if (viewOnlyMode) {
                return;
            }
            Swal.fire({
                title: 'Nuevo Componente',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ItemId</label>
                            <input id="nuevo_itemid" type="text" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ItemName</label>
                            <input id="nuevo_itemname" type="text" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ConfigId</label>
                            <input id="nuevo_configid" type="text" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Consumo Unitario</label>
                            <input id="nuevo_consumo" type="number" step="0.0001" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unidad</label>
                            <input id="nuevo_unidad" type="text" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Agregar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    return {
                        ItemId: document.getElementById('nuevo_itemid').value,
                        ItemName: document.getElementById('nuevo_itemname').value,
                        ConfigId: document.getElementById('nuevo_configid').value,
                        ConsumoUnitario: parseFloat(document.getElementById('nuevo_consumo').value) || 0,
                        Unidad: document.getElementById('nuevo_unidad').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    componentesData.push(result.value);
                    renderizarTablaComponentes();
                    Swal.fire({
                        icon: 'success',
                        title: 'Componente agregado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }

        function editarComponente() {
            if (viewOnlyMode) {
                return;
            }
            if (selectedComponenteIndex === null) return;

            const comp = componentesData[selectedComponenteIndex];

            Swal.fire({
                title: 'Editar Componente',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ItemId</label>
                            <input id="edit_itemid" type="text" value="${comp.ItemId || ''}" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ItemName</label>
                            <input id="edit_itemname" type="text" value="${comp.ItemName || ''}" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ConfigId</label>
                            <input id="edit_configid" type="text" value="${comp.ConfigId || ''}" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Consumo Unitario</label>
                            <input id="edit_consumo" type="number" step="0.0001" value="${comp.ConsumoUnitario || 0}" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unidad</label>
                            <input id="edit_unidad" type="text" value="${comp.Unidad || ''}" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    return {
                        ItemId: document.getElementById('edit_itemid').value,
                        ItemName: document.getElementById('edit_itemname').value,
                        ConfigId: document.getElementById('edit_configid').value,
                        ConsumoUnitario: parseFloat(document.getElementById('edit_consumo').value) || 0,
                        Unidad: document.getElementById('edit_unidad').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    componentesData[selectedComponenteIndex] = result.value;
                    renderizarTablaComponentes();
                    Swal.fire({
                        icon: 'success',
                        title: 'Componente actualizado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }

        function eliminarComponenteSeleccionado() {
            if (viewOnlyMode) {
                return;
            }
            if (selectedComponenteIndex === null) return;

            const comp = componentesData[selectedComponenteIndex];

            Swal.fire({
                title: '¿Eliminar componente?',
                text: `Se eliminará ${comp.ItemName || comp.ItemId}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    componentesData.splice(selectedComponenteIndex, 1);
                    renderizarTablaComponentes();
                    Swal.fire({
                        icon: 'success',
                        title: 'Componente eliminado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }

        /**
         * Cargar componentes desde AX (para crear nueva formulación)
         */
        function cargarComponentesCreate(formula) {
            if (!formula) {
                componentesCreateData = [];
                renderizarTablaComponentesCreate();
                document.getElementById('create_componentes_tabla_container').classList.add('hidden');
                document.getElementById('create_componentes_loading').classList.add('hidden');
                document.getElementById('create_componentes_error').classList.add('hidden');
                return;
            }

            document.getElementById('create_componentes_loading').classList.remove('hidden');
            document.getElementById('create_componentes_error').classList.add('hidden');
            document.getElementById('create_componentes_tabla_container').classList.add('hidden');

            fetch(`/eng-formulacion/componentes/formula?formula=${encodeURIComponent(formula)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    document.getElementById('create_componentes_error').classList.add('hidden');

                    if (data.success) {
                        componentesCreateData = data.componentes || [];
                        renderizarTablaComponentesCreate();
                        document.getElementById('create_componentes_tabla_container').classList.remove('hidden');
                    } else {
                        mostrarErrorComponentesCreate(data.error || 'Error al cargar componentes');
                    }
                })
                .catch(error => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    mostrarErrorComponentesCreate('Error de conexión: ' + error.message);
                });
        }

        /**
         * Cargar componentes desde EngFormulacionLine por ID (para editar formulación existente)
         * Esta función se mantiene por compatibilidad, pero ahora se usa getFormulacionById
         */
        function cargarComponentesFormulacion(folio, id = null) {
            if (!id && !folio) {
                componentesCreateData = [];
                renderizarTablaComponentesCreate();
                document.getElementById('create_componentes_tabla_container').classList.add('hidden');
                document.getElementById('create_componentes_loading').classList.add('hidden');
                document.getElementById('create_componentes_error').classList.add('hidden');
                return;
            }

            document.getElementById('create_componentes_loading').classList.remove('hidden');
            document.getElementById('create_componentes_error').classList.add('hidden');
            document.getElementById('create_componentes_tabla_container').classList.add('hidden');

            const url = id
                ? `/eng-formulacion/componentes/formulacion?id=${encodeURIComponent(id)}`
                : `/eng-formulacion/componentes/formulacion?folio=${encodeURIComponent(folio)}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    document.getElementById('create_componentes_error').classList.add('hidden');

                    if (data.success) {
                        // Convertir los componentes al formato esperado
                        componentesCreateData = (data.componentes || []).map(comp => ({
                            Id: comp.Id,
                            ItemId: comp.ItemId || '',
                            ItemName: comp.ItemName || '',
                            ConfigId: comp.ConfigId || '',
                            ConsumoUnitario: comp.ConsumoUnitario || 0,
                            ConsumoTotal: comp.ConsumoTotal || 0,
                            Unidad: comp.Unidad || '',
                            Almacen: comp.Almacen || '',
                            esNuevo: false // No es nuevo, viene de la BD
                        }));
                        renderizarTablaComponentesCreate();
                        document.getElementById('create_componentes_tabla_container').classList.remove('hidden');
                    } else {
                        mostrarErrorComponentesCreate(data.error || 'Error al cargar componentes');
                    }
                })
                .catch(error => {
                    document.getElementById('create_componentes_loading').classList.add('hidden');
                    mostrarErrorComponentesCreate('Error de conexión: ' + error.message);
                });
        }

        // Cache y rutas para materiales
        const componenteMaterialRoutes = {
            calibres: "{{ route('eng-formulacion.calibres') }}",
            fibras: "{{ route('eng-formulacion.fibras') }}",
            colores: "{{ route('eng-formulacion.colores') }}"
        };

        const componenteMaterialCache = {
            calibres: null,
            fibras: new Map(),
            colores: new Map()
        };

        const fetchComponenteJson = async (url, params = {}) => {
            const query = new URLSearchParams(params);
            const fullUrl = query.toString() ? `${url}?${query}` : url;
            const response = await fetch(fullUrl);
            if (!response.ok) {
                throw new Error(`Request failed: ${response.status}`);
            }
            return response.json();
        };

        const getComponenteCalibres = async () => {
            if (componenteMaterialCache.calibres) return componenteMaterialCache.calibres;
            try {
                const data = await fetchComponenteJson(componenteMaterialRoutes.calibres);
                const items = (data?.data || []).filter(i => i.ItemId);
                componenteMaterialCache.calibres = items;
                return items;
            } catch (e) {
                console.error('No se pudieron cargar calibres', e);
                return [];
            }
        };

        const getComponenteFibras = async (itemId) => {
            if (componenteMaterialCache.fibras.has(itemId)) return componenteMaterialCache.fibras.get(itemId);
            try {
                const data = await fetchComponenteJson(componenteMaterialRoutes.fibras, { itemId });
                const items = (data?.data || []).map(i => i.ConfigId).filter(Boolean);
                componenteMaterialCache.fibras.set(itemId, items);
                return items;
            } catch (e) {
                console.error('No se pudieron cargar fibras', e);
                return [];
            }
        };

        const setComponenteSelectOptions = (select, options, placeholder, selectedValue = '') => {
            if (!select) return;
            select.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            select.appendChild(placeholderOption);

            options.forEach((opt) => {
                const option = document.createElement('option');
                // Si es un objeto con ItemId, usarlo, sino es string simple
                if (typeof opt === 'object' && opt.ItemId) {
                    option.value = opt.ItemId;
                    option.textContent = opt.ItemId;
                    option.setAttribute('data-itemname', opt.ItemName || '');
                } else {
                    option.value = opt;
                    option.textContent = opt;
                }
                select.appendChild(option);
            });

            select.value = selectedValue || '';
            select.disabled = options.length === 0;
        };

        const ensureComponenteOption = (select, value, label) => {
            if (!select || !value) return;
            const exists = Array.from(select.options).some(opt => opt.value === value);
            if (!exists) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label || value;
                select.appendChild(option);
            }
        };

        async function initComponenteSelectorsForRow(row, comp) {
            const calibreEl = row.querySelector('[data-field="ItemId"]');
            const fibraEl = row.querySelector('[data-field="ConfigId"]');

            if (!calibreEl || !fibraEl) return;

            // Cargar calibres
            setComponenteSelectOptions(calibreEl, [], 'Cargando...');
            const calibres = await getComponenteCalibres();
            setComponenteSelectOptions(calibreEl, calibres, 'Selecciona calibre', comp.ItemId || '');

            if (comp.ItemId) {
                ensureComponenteOption(calibreEl, comp.ItemId, comp.ItemId);
                calibreEl.value = comp.ItemId;
            }

            // Cargar fibras si hay calibre seleccionado
            if (comp.ItemId) {
                setComponenteSelectOptions(fibraEl, [], 'Cargando...');
                const fibras = await getComponenteFibras(comp.ItemId);
                setComponenteSelectOptions(fibraEl, fibras, 'Selecciona fibra', comp.ConfigId || '');

                if (comp.ConfigId) {
                    ensureComponenteOption(fibraEl, comp.ConfigId, comp.ConfigId);
                    fibraEl.value = comp.ConfigId;
                }
            } else {
                setComponenteSelectOptions(fibraEl, [], 'Selecciona calibre primero');
            }

            // Event listener para cuando cambie el calibre
            calibreEl.addEventListener('change', async (e) => {
                const itemId = e.target.value;
                const itemNameEl = row.querySelector('[data-field="ItemName"]');

                if (itemId) {
                    // Obtener ItemName del option seleccionado
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    const itemName = selectedOption.getAttribute('data-itemname') || '';
                    if (itemNameEl) itemNameEl.value = itemName;

                    // Actualizar índice en componentesCreateData
                    const index = parseInt(calibreEl.getAttribute('data-index'));
                    if (componentesCreateData[index]) {
                        componentesCreateData[index].ItemId = itemId;
                        componentesCreateData[index].ItemName = itemName;
                    }

                    setComponenteSelectOptions(fibraEl, [], 'Cargando...');
                    const fibras = await getComponenteFibras(itemId);
                    setComponenteSelectOptions(fibraEl, fibras, 'Selecciona fibra');

                    // Auto-seleccionar ConfigId cuando solo hay 1 fibra (igual que se autocompleta Nombre)
                    if (fibras.length === 1) {
                        fibraEl.value = fibras[0];
                        const idx = parseInt(calibreEl.getAttribute('data-index'));
                        if (componentesCreateData[idx]) {
                            componentesCreateData[idx].ConfigId = fibras[0];
                        }
                    }
                    renderizarTablaComponentesCreate();
                } else {
                    if (itemNameEl) itemNameEl.value = '';
                    const idx = parseInt(calibreEl.getAttribute('data-index'));
                    if (componentesCreateData[idx]) {
                        componentesCreateData[idx].ItemId = '';
                        componentesCreateData[idx].ItemName = '';
                        componentesCreateData[idx].ConfigId = '';
                    }
                    setComponenteSelectOptions(fibraEl, [], 'Selecciona calibre primero');
                    renderizarTablaComponentesCreate();
                }
            });

            // Sincronizar ConfigId a componentesCreateData
            fibraEl.addEventListener('change', function() {
                const idx = parseInt(fibraEl.getAttribute('data-index'));
                if (componentesCreateData[idx]) {
                    componentesCreateData[idx].ConfigId = this.value || '';
                }
            });
        }

        function renderizarTablaComponentesCreate() {
            const tbody = document.getElementById('create_componentes_tbody');
            if (!tbody) {
                console.error('No se encontró el tbody con id create_componentes_tbody');
                return;
            }

            tbody.innerHTML = '';


            if (componentesCreateData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                            No hay componentes para esta formula
                        </td>
                    </tr>
                `;
                return;
            }

            componentesCreateData.forEach((comp, index) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-blue-50/50 transition-colors' + (index % 2 === 1 ? ' bg-gray-50/30' : '');

                const consumoUnitario = parseFloat(comp.ConsumoUnitario) || 0;
                const consumoTotal = consumoUnitario * litrosCreateFormula;
                const disabledAttr = viewOnlyMode ? 'disabled' : '';
                const disabledClass = viewOnlyMode ? 'bg-gray-100 cursor-not-allowed' : '';
                const sinCalibre = !(comp.ItemId || '').trim();
                const consumoTotalDisabled = sinCalibre ? 'disabled' : disabledAttr;

                // Solo usar selects si viene desde producción Y es una fila nueva (agregada con botón)
                if (desdeProduccion && comp.esNuevo) {
                    row.innerHTML = `
                        <td class="px-4 py-2 text-sm">
                            <select data-index="${index}" data-field="ItemId"
                                class="componente-calibre w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr}>
                                <option value="">Cargando...</option>
                            </select>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <input type="text" value="${comp.ItemName || ''}" data-index="${index}" data-field="ItemName"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr} readonly>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <select data-index="${index}" data-field="ConfigId"
                                class="componente-fibra w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr}>
                                <option value="">Selecciona calibre primero</option>
                            </select>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <input type="number" step="0.0001" value="${consumoTotal.toFixed(4)}" data-index="${index}" data-field="ConsumoTotal"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-right font-semibold text-blue-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${consumoTotalDisabled}
                                title="${sinCalibre ? 'Seleccione Artículo (calibre) primero' : ''}">
                        </td>
                    `;
                } else {
                    const consumoTotalDisabledInput = sinCalibre ? 'disabled' : disabledAttr;
                    row.innerHTML = `
                        <td class="px-4 py-2 text-sm">
                            <input type="text" value="${comp.ItemId || ''}" data-index="${index}" data-field="ItemId"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr}>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <input type="text" value="${comp.ItemName || ''}" data-index="${index}" data-field="ItemName"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr}>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <input type="text" value="${comp.ConfigId || ''}" data-index="${index}" data-field="ConfigId"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr}>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <input type="number" step="0.0001" value="${consumoTotal.toFixed(4)}" data-index="${index}" data-field="ConsumoTotal"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-right font-semibold text-blue-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${consumoTotalDisabledInput}
                                title="${sinCalibre ? 'Seleccione Artículo (calibre) primero' : ''}">
                        </td>
                    `;
                }

                tbody.appendChild(row);

                // Inicializar selects en cascada solo si viene desde producción Y es fila nueva
                if (desdeProduccion && comp.esNuevo) {
                    initComponenteSelectorsForRow(row, comp);
                }

                // Si ItemId es input (calibre manual), sincronizar al cambiar y re-renderizar para habilitar ConsumoTotal
                const itemIdEl = row.querySelector('[data-field="ItemId"]');
                if (itemIdEl && itemIdEl.tagName === 'INPUT') {
                    itemIdEl.addEventListener('change', function() {
                        if (componentesCreateData[index]) {
                            componentesCreateData[index].ItemId = (this.value || '').trim();
                        }
                        renderizarTablaComponentesCreate();
                    });
                }

                // IMPORTANTE: Agregar listener al campo ConsumoTotal para actualizar ConsumoUnitario cuando cambie
                // Esto asegura que cuando el usuario edite manualmente el ConsumoTotal,
                // el ConsumoUnitario se actualice correctamente para que cuando cambien los litros,
                // el ConsumoTotal se recalcule correctamente
                const consumoTotalInput = row.querySelector('[data-field="ConsumoTotal"]');
                if (consumoTotalInput) {
                    // Remover listeners anteriores si existen para evitar duplicados
                    const nuevoInput = consumoTotalInput.cloneNode(true);
                    consumoTotalInput.parentNode.replaceChild(nuevoInput, consumoTotalInput);

                    nuevoInput.addEventListener('input', function() {
                        const nuevoConsumoTotal = parseFloat(this.value) || 0;
                        const nuevoConsumoUnitario = litrosCreateFormula > 0
                            ? nuevoConsumoTotal / litrosCreateFormula
                            : 0;

                        // Actualizar el valor en componentesCreateData
                        if (componentesCreateData[index]) {
                            componentesCreateData[index].ConsumoTotal = nuevoConsumoTotal;
                            componentesCreateData[index].ConsumoUnitario = nuevoConsumoUnitario;
                        }
                    });
                }
            });

        }

        function agregarFilaComponenteCreate() {
            const hayFilaSinCalibre = componentesCreateData.some(c => !(c.ItemId || '').trim());
            if (hayFilaSinCalibre) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione calibre primero',
                    text: 'No puede agregar una fila nueva si alguna fila no tiene Artículo (calibre) seleccionado. Seleccione el calibre en la fila incompleta o elimínela.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            componentesCreateData.push({
                ItemId: '',
                ItemName: '',
                ConfigId: '',
                ConsumoUnitario: 0,
                Unidad: '',
                Almacen: '',
                esNuevo: true
            });
            renderizarTablaComponentesCreate();
            document.getElementById('create_componentes_tabla_container').classList.remove('hidden');
            document.getElementById('create_componentes_loading').classList.add('hidden');
            document.getElementById('create_componentes_error').classList.add('hidden');
            actualizarBotonGuardarEdicion();
        }

        function eliminarFilaComponenteCreate(index) {
            if (index >= 0 && index < componentesCreateData.length) {
                componentesCreateData.splice(index, 1);
                renderizarTablaComponentesCreate();
                actualizarBotonGuardarEdicion();
            }
        }

        function obtenerComponentesCreateDesdeTabla() {
            const tbody = document.getElementById('create_componentes_tbody');
            if (!tbody) return [];

            const filas = Array.from(tbody.querySelectorAll('tr'));
            return filas.map((row, index) => {
                const itemId = row.querySelector('[data-field="ItemId"]')?.value || '';
                const itemName = row.querySelector('[data-field="ItemName"]')?.value || '';
                const configId = row.querySelector('[data-field="ConfigId"]')?.value || '';
                const consumoTotal = parseFloat(row.querySelector('[data-field="ConsumoTotal"]')?.value) || 0;

                const original = componentesCreateData[index] || {};

                // Calcular ConsumoUnitario desde ConsumoTotal si los litros están disponibles
                // Esto asegura que cuando el usuario edite manualmente el ConsumoTotal,
                // el ConsumoUnitario se actualice correctamente
                let consumoUnitario = parseFloat(original.ConsumoUnitario) || 0;
                if (litrosCreateFormula > 0 && consumoTotal > 0) {
                    // Si el usuario editó el ConsumoTotal manualmente, recalcular ConsumoUnitario
                    consumoUnitario = consumoTotal / litrosCreateFormula;
                }

                return {
                    ItemId: itemId,
                    ItemName: itemName,
                    ConfigId: configId,
                    ConsumoUnitario: consumoUnitario,
                    ConsumoTotal: consumoTotal,
                    Unidad: original.Unidad || '',
                    Almacen: original.Almacen || ''
                };
            });
        }

        function mostrarErrorComponentesCreate(mensaje) {
            const errorBox = document.getElementById('create_componentes_error');
            const errorMsg = document.getElementById('create_componentes_error_message');
            if (errorBox && errorMsg) {
                errorBox.classList.remove('hidden');
                errorMsg.textContent = mensaje;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();
            // No cargar aquí: se carga al abrir el modal para evitar renders duplicados

            const thFecha = document.getElementById('th-fecha');
            if (thFecha) {
                thFecha.setAttribute('aria-sort', 'none');
                thFecha.addEventListener('click', toggleOrdenFecha);
            }

            const recalcularYRenderizar = () => {
                if (componentesCreateData.length === 0) return;
                componentesCreateData.forEach((comp) => {
                    const consumoUnitario = parseFloat(comp.ConsumoUnitario) || 0;
                    comp.ConsumoTotal = consumoUnitario * litrosCreateFormula;
                });
                renderizarTablaComponentesCreate();
                actualizarBotonGuardarEdicion();
            };

            const debounce = (fn, ms) => {
                let t;
                return function() { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), ms); };
            };

            const kilosInput = document.getElementById('create_kilos');
            if (kilosInput) {
                kilosCreateFormula = parseFloat(kilosInput.value) || 0;
                kilosInput.addEventListener('input', debounce(function() {
                    kilosCreateFormula = parseFloat(this.value) || 0;
                    recalcularYRenderizar();
                }, 300));
            }

            const litrosInput = document.getElementById('create_litros');
            if (litrosInput) {
                litrosCreateFormula = parseFloat(litrosInput.value) || 0;
                litrosInput.addEventListener('input', debounce(function() {
                    litrosCreateFormula = parseFloat(this.value) || 0;
                    recalcularYRenderizar();
                }, 300));
            }

            const createForm = document.getElementById('createForm');
            if (createForm) {
                createForm.addEventListener('input', debounce(actualizarBotonGuardarEdicion, 200));
                createForm.addEventListener('change', actualizarBotonGuardarEdicion);
                createForm.addEventListener('submit', function(e) {
                    const kilosVal = parseFloat(document.getElementById('create_kilos')?.value) ?? NaN;
                    const litrosVal = parseFloat(document.getElementById('create_litros')?.value) ?? NaN;
                    const tiempoVal = parseFloat(document.getElementById('create_tiempo')?.value) ?? NaN;
                    const solidosVal = parseFloat(document.getElementById('create_solidos')?.value) ?? NaN;
                    const viscVal = parseFloat(document.getElementById('create_viscocidad')?.value) ?? NaN;

                    if (kilosVal < 0 || isNaN(kilosVal)) {
                        e.preventDefault();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Los Kilos no pueden ser negativos', showConfirmButton: false, timer: 3000 });
                        return;
                    }
                    if (litrosVal <= 0 || isNaN(litrosVal)) {
                        e.preventDefault();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Los Litros deben ser mayor a cero', showConfirmButton: false, timer: 3000 });
                        return;
                    }
                    if (tiempoVal <= 0 || isNaN(tiempoVal)) {
                        e.preventDefault();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'El Tiempo Cocinado debe ser mayor a cero', showConfirmButton: false, timer: 3000 });
                        return;
                    }
                    if (solidosVal <= 0 || isNaN(solidosVal)) {
                        e.preventDefault();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'El % Sólidos debe ser mayor a cero', showConfirmButton: false, timer: 3000 });
                        return;
                    }
                    if (viscVal <= 0 || isNaN(viscVal)) {
                        e.preventDefault();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'La Viscosidad debe ser mayor a cero', showConfirmButton: false, timer: 3000 });
                        return;
                    }
                    const method = document.getElementById('create_method');
                    if (method && method.value === 'PUT' && !haCambiadoFormulacionCreate()) {
                        e.preventDefault();
                        return;
                    }
                    const componentes = obtenerComponentesCreateDesdeTabla();
                    const conCalibre = componentes.filter(c => (c.ItemId || '').trim());

                    const payload = document.getElementById('create_componentes_payload');
                    if (payload) {
                        payload.value = JSON.stringify(conCalibre);
                    }

                    // Si es edición, también enviar componentes
                    if (method && method.value === 'PUT') {
                        // Los componentes se envían en el payload
                    }
                });
            }
        });

        function verObsCalidad(event, texto) {
            if (event) event.stopPropagation();
            const obs = texto || 'Sin observaciones';
            Swal.fire({
                title: 'Observaciones de Calidad',
                html: `<div class="text-left p-3 bg-gray-50 rounded-lg whitespace-pre-wrap max-h-64 overflow-y-auto">${obs.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>`,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#3b82f6',
                width: '500px'
            });
        }

        function abrirModalObsCalidad(btnCalidad) {
            const folio = btnCalidad.dataset.folio || '';
            const formula = btnCalidad.dataset.formula || '';
            const litros = btnCalidad.dataset.litros || '';
            const tiempo = btnCalidad.dataset.tiempo || '';
            const solidos = btnCalidad.dataset.solidos || '';
            const viscocidad = btnCalidad.dataset.viscocidad || '';
            // Valores: '' = null (vacío), '0' = tache, '1' = palomita
            const okTiempoVal = btnCalidad.dataset.oktiempo ?? '';
            const okViscocidadVal = btnCalidad.dataset.okviscocidad ?? '';
            const okSolidosVal = btnCalidad.dataset.oksolidos ?? '';
            const obsActual = btnCalidad.dataset.hasObs === '1' ? (btnCalidad.title || '') : '';

            // Una sola celda por fila: 1 toque = palomita (✓), 2 toques = equis (✗). Solo dos estados.
            const cicloState = (name, current) => {
                const v = (current === '1' || current === '0') ? current : '1';
                const simbolo = v === '1' ? '✓' : '✗';
                const clase = v === '1' ? 'text-green-600' : 'text-red-600';
                return `<button type="button" class="calidad-ciclo w-12 h-9 rounded-lg border-2 border-gray-300 bg-gray-50 hover:bg-gray-100 hover:border-blue-400 text-lg font-bold ${clase} transition-colors" data-field="${name}" data-value="${v}" title="1 toque ✓, 2 toques ✗">${simbolo}</button>`;
            };

            Swal.fire({
                title: 'Calidad',
                width: '480px',
                html: `
                    <div class="text-left">
                        <div class="flex gap-4 mb-4 text-sm">
                            <div><span class="text-gray-500">Folio</span> <span class="font-semibold">${folio}</span></div>
                            <div><span class="text-gray-500">Fórmula</span> <span class="font-semibold">${formula}</span></div>
                            <div><span class="text-gray-500">Litros</span> <span class="font-semibold">${litros}</span></div>
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
                                    <tr class="hover:bg-blue-50/50">
                                        <td class="px-4 py-2.5 font-medium text-gray-700">Tiempo (Min)</td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-blue-700">${tiempo}</td>
                                        <td class="px-4 py-2.5 text-center">${cicloState('oktiempo', okTiempoVal)}</td>
                                    </tr>
                                    <tr class="hover:bg-blue-50/50">
                                        <td class="px-4 py-2.5 font-medium text-gray-700">Sólidos (%)</td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-blue-700">${solidos}</td>
                                        <td class="px-4 py-2.5 text-center">${cicloState('oksolidos', okSolidosVal)}</td>
                                    </tr>
                                    <tr class="hover:bg-blue-50/50">
                                        <td class="px-4 py-2.5 font-medium text-gray-700">Viscosidad</td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-blue-700">${viscocidad}</td>
                                        <td class="px-4 py-2.5 text-center">${cicloState('okviscocidad', okViscocidadVal)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-200 pt-3 mt-4">
                            <label class="text-gray-500 block mb-1 text-sm font-medium">Observaciones</label>
                            <input type="text" id="swal-obs-calidad" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Añadir observaciones (opcional)...">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const obs = document.getElementById('swal-obs-calidad').value;
                    const getVal = (field) => {
                        const el = document.querySelector('.calidad-ciclo[data-field="' + field + '"]');
                        if (!el) return 1;
                        return el.dataset.value === '0' ? 0 : 1;
                    };
                    return { obs, okTiempo: getVal('oktiempo'), okViscocidad: getVal('okviscocidad'), okSolidos: getVal('oksolidos') };
                },
                didOpen: () => {
                    const input = document.getElementById('swal-obs-calidad');
                    if (input) {
                        input.value = obsActual || '';
                        input.focus();
                    }
                    document.querySelectorAll('.calidad-ciclo').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const next = this.dataset.value === '1' ? '0' : '1';
                            this.dataset.value = next;
                            this.textContent = next === '1' ? '✓' : '✗';
                            this.classList.remove('text-green-600', 'text-red-600');
                            this.classList.add(next === '1' ? 'text-green-600' : 'text-red-600');
                        });
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { obs, okTiempo, okViscocidad, okSolidos } = result.value;
                    guardarObsCalidad(folio, obs, btnCalidad, { okTiempo, okViscocidad, okSolidos });
                }
            });
        }

        async function guardarObsCalidad(folio, observaciones, btnCalidad, checks = {}) {
            try {
                const formulacionId = btnCalidad.dataset.id || '';
                const url = `/eng-formulacion/${folio}`;
                // null = vacío, 0 = tache, 1 = palomita
                const toVal = (v) => (v === null || v === undefined) ? null : (v ? 1 : 0);
                const okT = checks.hasOwnProperty('okTiempo') ? (checks.okTiempo === null ? null : (checks.okTiempo ? 1 : 0)) : (btnCalidad.dataset.oktiempo === '' ? null : (btnCalidad.dataset.oktiempo === '1' ? 1 : 0));
                const okV = checks.hasOwnProperty('okViscocidad') ? (checks.okViscocidad === null ? null : (checks.okViscocidad ? 1 : 0)) : (btnCalidad.dataset.okviscocidad === '' ? null : (btnCalidad.dataset.okviscocidad === '1' ? 1 : 0));
                const okS = checks.hasOwnProperty('okSolidos') ? (checks.okSolidos === null ? null : (checks.okSolidos ? 1 : 0)) : (btnCalidad.dataset.oksolidos === '' ? null : (btnCalidad.dataset.oksolidos === '1' ? 1 : 0));
                const body = {
                    obs_calidad: observaciones,
                    ok_tiempo: okT,
                    ok_viscocidad: okV,
                    ok_solidos: okS
                };
                if (formulacionId) body.formulacion_id = formulacionId;
                const response = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Error al guardar');
                }

                // Actualizar botón: icono, tooltip y data de los checks ('' = null, '0' = tache, '1' = palomita)
                btnCalidad.dataset.oktiempo = okT === null ? '' : okT.toString();
                btnCalidad.dataset.okviscocidad = okV === null ? '' : okV.toString();
                btnCalidad.dataset.oksolidos = okS === null ? '' : okS.toString();
                const row = btnCalidad.closest('tr');
                if (row) {
                    row.dataset.oktiempo = btnCalidad.dataset.oktiempo;
                    row.dataset.okviscocidad = btnCalidad.dataset.okviscocidad;
                    row.dataset.oksolidos = btnCalidad.dataset.oksolidos;
                }
                const iconEl = btnCalidad.querySelector('i');
                if (observaciones.trim()) {
                    btnCalidad.dataset.hasObs = '1';
                    btnCalidad.title = observaciones;
                    btnCalidad.classList.remove('text-blue-500');
                    btnCalidad.classList.add('text-blue-700');
                    if (iconEl) iconEl.className = 'fa-solid fa-clipboard-check text-sm text-blue-700';
                } else {
                    btnCalidad.dataset.hasObs = '0';
                    btnCalidad.title = 'Calidad (sin observaciones)';
                    btnCalidad.classList.remove('text-blue-700');
                    btnCalidad.classList.add('text-blue-500');
                    if (iconEl) iconEl.className = 'fa-solid fa-clipboard-list text-sm text-blue-500';
                }
                Swal.fire({
                    icon: 'success',
                    title: formulacionId ? 'Calidad actualizada' : 'Calidad creada',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron guardar las observaciones'
                });
                // Revertir el estado del icono en caso de error (el usuario puede reintentar)
            }
        }

        function abrirModalObservaciones(checkbox) {
            const folio = checkbox.dataset.folio || '';
            const key = folio;
            const cur = observaciones[key] || '';
            Swal.fire({
                title: 'Observaciones',
                html: `
                    <textarea id="obs-textarea"
                              class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                              rows="4"
                              placeholder="Escriba sus observaciones aquí...">${cur}</textarea>
                `,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const value = document.getElementById('obs-textarea')?.value || '';
                    return value.trim();
                }
            }).then(result => {
                if (!result.isConfirmed) {
                    return;
                }

                const text = result.value || '';
                if (text) {
                    observaciones[key] = text;
                    checkbox.title = text;
                } else {
                    delete observaciones[key];
                    checkbox.title = '';
                }
            });
        }

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>

@endsection
