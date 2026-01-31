@extends('layouts.app')

@section('page-title', 'Captura de Fórmulas')

@section('navbar-right')
    @php
        $desdeProduccion = !empty($folioFiltro);
    @endphp
    <div class="flex items-center gap-2">
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
            <x-navbar.button-report
            id="btn-autorizar"
            onclick="confirmAutorizar()"
            title="Autorizar"
            bg="bg-green-600"
            text="Finalizar"
            iconColor="text-white"
            hoverBg="hover:bg-green-600"
            class="text-white"
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
    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '{{ session('error') }}',
                    confirmButtonColor: '#3b82f6'
                });
            });
        </script>
    @endif

    <div class="overflow-x-auto overflow-y-auto rounded-lg border bg-white shadow-sm mt-4 mx-4" style="max-height: 70vh;">
        <table id="formulaTable" class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-blue-500 to-blue-600 text-white ">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Orden</th>
                    <th id="th-fecha" class="text-left px-4 py-3 font-semibold whitespace-nowrap cursor-pointer select-none">Fecha <i class="fa-solid fa-filter text-xs ml-1 opacity-80"></i></th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Hr</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Cuenta/Titulo</th>
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
                    <th class="text-center px-4 py-3 font-semibold whitespace-nowrap">Obs</th>
                </tr>
            </thead>
            <tbody id="formulaTableBody">
                @forelse($items as $item)
                    <tr class="formula-row border-b cursor-pointer transition-colors"
                        onclick="selectRow(this, '{{ $item->Folio }}')"
                        data-folio="{{ $item->Folio }}"
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
                    >
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
                        <td class="px-4 py-3 text-center">
                            <input
                                type="checkbox"
                                class="obs-checkbox w-3 h-3 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-offset-0 focus:ring-0"
                                data-folio="{{ $item->Folio }}"
                            >
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="px-4 py-8 text-center text-gray-500">No hay fórmulas disponibles</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Crear -->
    <div id="createModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <h3 class="text-xl font-semibold">Nueva Formulación de Engomado</h3>
                <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-white hover:text-gray-200 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="createForm" action="{{ route('eng-formulacion.store') }}" method="POST" class="p-6">
                @csrf

                <!-- Sección 1: Datos principales (3 columnas) -->
                <div class="mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Folio (Programa Engomado) <span class="text-red-600">*</span></label>
                            <select name="FolioProg" id="create_folio_prog" required onchange="cargarDatosPrograma(this, false)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
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
                            <input type="date" name="fecha" value="{{ date('Y-m-d') }}" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Hora</label>
                            <input type="time" name="Hora" id="create_hora" value="{{ date('H:i') }}" step="60" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">No. Empleado</label>
                            <input type="text" value="{{ auth()->user()->numero_empleado ?? (auth()->user()->numero ?? '') }}" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Operador</label>
                            <input type="text" value="{{ auth()->user()->nombre ?? '' }}" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fórmula</label>
                            <input type="text" name="Formula" id="create_formula" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <!-- Campos ocultos para datos de EngProgramaEngomado -->
                <input type="hidden" name="Cuenta" id="create_cuenta">
                <input type="hidden" name="Calibre" id="create_calibre">
                <input type="hidden" name="Tipo" id="create_tipo">
                <input type="hidden" name="NomEmpl" id="create_nom_empl">
                <input type="hidden" name="CveEmpl" id="create_cve_empl">
                <input type="hidden" name="componentes" id="create_componentes_payload">
                <!-- Sección 2: Datos de Captura -->
                <div class="mb-4">
                    {{-- <h4 class="text-sm font-semibold text-purple-700 mb-2 pb-2 border-b border-purple-200">Datos de Captura</h4> --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
                            <select name="Olla" id="create_olla" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                <option value="">Seleccione...</option>
                                @for($i = 1; $i <= 6; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Kilos (Kg.)</label>
                            <input type="number" step="0.01" name="Kilos" id="create_kilos" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Litros</label>
                            <input type="number" step="0.01" name="Litros" id="create_litros" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tiempo Cocinado (Min)</label>
                            <input type="number" step="0.01" name="TiempoCocinado" id="create_tiempo" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">% Sólidos</label>
                            <input type="number" step="0.01" name="Solidos" id="create_solidos" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Viscosidad</label>
                            <input type="number" step="0.01" name="Viscocidad" id="create_viscocidad" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        </div>
                    </div>
                </div>

                <!-- Sección 3: Componentes de la Fórmula -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2 pb-2 border-b border-purple-200">
                        <h4 class="text-sm font-semibold text-purple-700">Componentes de la Fórmula</h4>
                        <button type="button" id="btn-create-add-row" onclick="agregarFilaComponenteCreate()"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                            <i class="fa-solid fa-plus mr-1"></i>Agregar fila
                        </button>
                    </div>

                    <!-- Loading -->
                    <div id="create_componentes_loading" class="hidden text-center py-6">
                        <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
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
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold">Articulo</th>
                                        <th class="px-4 py-2 text-left font-semibold">Nombre</th>
                                        <th class="px-4 py-2 text-left font-semibold">ConfigId</th>
                                        <th class="px-4 py-2 text-right font-semibold">Consumo Total</th>
                                    </tr>
                                </thead>
                                <tbody id="create_componentes_tbody" class="bg-white divide-y divide-gray-200">
                                    <!-- Se llenará dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex gap-2 justify-end pt-3 border-t border-gray-200 mt-4">
                    {{-- <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                            class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fa-solid fa-times mr-1"></i>Cancelar
                    </button> --}}
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-save mr-1"></i>Crear Formulación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
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
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold">ItemId</th>
                                        <th class="px-4 py-2 text-left font-semibold">Nombre</th>
                                        <th class="px-4 py-2 text-left font-semibold">ConfigId</th>
                                        <th class="px-4 py-2 text-right font-semibold">Consumo Unitario</th>
                                        <th class="px-4 py-2 text-right font-semibold">Consumo Total</th>
                                        <th class="px-4 py-2 text-left font-semibold">Unidad</th>
                                        <th class="px-4 py-2 text-left font-semibold">Almacén</th>
                                    </tr>
                                </thead>
                                <tbody id="view_componentes_tbody" class="bg-white divide-y divide-gray-200">
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
    </form>

    <style>
        .formula-row:hover,
        .formula-row:hover td {
            background-color: rgb(59, 130, 246) !important;
            color: white !important;
        }
        .formula-row:hover a,
        .formula-row:hover span,
        .formula-row:hover div {
            color: white !important;
        }
        .formula-row:hover .bg-yellow-100,
        .formula-row:hover .bg-blue-100,
        .formula-row:hover .bg-green-100 {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: white !important;
        }
        .formula-row.selected,
        .formula-row.selected td {
            background-color: rgb(59, 130, 246) !important;
            color: white !important;
        }
        .formula-row.selected a,
        .formula-row.selected span,
        .formula-row.selected div {
            color: white !important;
        }
        .formula-row.selected .bg-yellow-100,
        .formula-row.selected .bg-blue-100,
        .formula-row.selected .bg-green-100 {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: white !important;
        }
    </style>

    <script>
        let selectedRow = null;
        let selectedFolio = null;
        let viewOnlyMode = false;
        const observaciones = {};
        let fechaSortAsc = null;

        function selectRow(row, folio) {
            document.querySelectorAll('#formulaTable tbody tr.selected').forEach(existing => {
                existing.classList.remove('selected');
            });
            selectedRow = row;
            selectedFolio = folio;
            row.classList.add('selected');

            enableButtons();
        }

        function openCreateModal(readOnly = false) {
            viewOnlyMode = readOnly;
            const modal = document.getElementById('createModal');
            if (modal) {
                modal.classList.remove('hidden');
            }

            if (!selectedRow || !selectedFolio) {
                setCreateModalReadOnly(readOnly);
                return;
            }

            const select = document.getElementById('create_folio_prog');
            if (select) {
                const existeFolio = Array.from(select.options).some(option => option.value === selectedFolio);
                if (existeFolio) {
                    select.value = selectedFolio;
                    cargarDatosPrograma(select, false);
                }
            }

            const cells = selectedRow.cells;
            const fechaTexto = (cells[1]?.textContent || '').trim();
            const horaTexto = (cells[2]?.textContent || '').trim();
            const ollaTexto = (cells[8]?.textContent || '').trim();
            const formulaTexto = (cells[9]?.textContent || '').trim();
            const kilosTexto = (cells[10]?.textContent || '').trim().replace(/,/g, '');
            const litrosTexto = (cells[11]?.textContent || '').trim().replace(/,/g, '');
            const tiempoTexto = (cells[12]?.textContent || '').trim().replace(/,/g, '');
            const solidosTexto = (cells[13]?.textContent || '').trim().replace(/,/g, '');
            const viscocidadTexto = (cells[14]?.textContent || '').trim().replace(/,/g, '');

            const fechaInput = document.querySelector('#createModal input[name="fecha"]');
            if (fechaInput && fechaTexto.includes('/')) {
                const partes = fechaTexto.split('/');
                if (partes.length === 3) {
                    const [dia, mes, anio] = partes;
                    fechaInput.value = `${anio}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}`;
                }
            }

            const horaInput = document.getElementById('create_hora');
            if (horaInput && horaTexto) {
                horaInput.value = horaTexto;
            }

            const ollaInput = document.getElementById('create_olla');
            if (ollaInput && ollaTexto) {
                ollaInput.value = ollaTexto;
            }

            const formulaInput = document.getElementById('create_formula');
            if (formulaInput && formulaTexto) {
                formulaInput.value = formulaTexto;
                formulaCreateActual = formulaTexto;
            }

            const kilosInput = document.getElementById('create_kilos');
            if (kilosInput) {
                kilosInput.value = kilosTexto || '';
                kilosCreateFormula = parseFloat(kilosTexto) || 0;
                if (componentesCreateData.length > 0) {
                    renderizarTablaComponentesCreate();
                }
            }

            const litrosInput = document.getElementById('create_litros');
            if (litrosInput) litrosInput.value = litrosTexto || '';
            const tiempoInput = document.getElementById('create_tiempo');
            if (tiempoInput) tiempoInput.value = tiempoTexto || '';
            const solidosInput = document.getElementById('create_solidos');
            if (solidosInput) solidosInput.value = solidosTexto || '';
            const viscocidadInput = document.getElementById('create_viscocidad');
            if (viscocidadInput) viscocidadInput.value = viscocidadTexto || '';

            if (formulaTexto && (!select || select.value !== selectedFolio)) {
                cargarComponentesCreate(formulaTexto);
            }

            setCreateModalReadOnly(readOnly);
        }

        function disableButtons() {
            ['btn-edit', 'btn-view', 'btn-autorizar', 'btn-delete'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            });
        }

        function enableButtons() {
            ['btn-edit', 'btn-view', 'btn-autorizar', 'btn-delete'].forEach(id => {
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
            if (!selectedRow) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }

            viewOnlyMode = false;
            setEditModalReadOnly(false);
            fillEditModalFromRow(selectedRow);

            document.getElementById('editModal').classList.remove('hidden');
        }

        function openViewModal() {
            if (!selectedRow) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }

            openCreateModal(true);
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
                if (isReadOnly) {
                    field.setAttribute('disabled', 'disabled');
                } else {
                    field.removeAttribute('disabled');
                }
            });

            const addRowBtn = document.getElementById('btn-create-add-row');
            if (addRowBtn) {
                addRowBtn.classList.toggle('hidden', isReadOnly);
                addRowBtn.disabled = isReadOnly;
                addRowBtn.classList.toggle('opacity-50', isReadOnly);
                addRowBtn.classList.toggle('cursor-not-allowed', isReadOnly);
            }

            const submitBtn = modal.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.toggle('hidden', isReadOnly);
                submitBtn.disabled = isReadOnly;
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

            componentes.forEach(comp => {
                const consumoUnitario = parseFloat(comp.ConsumoUnitario) || 0;
                const consumoTotal = (parseFloat(kilosFormula) || 0) * consumoUnitario;
                const row = document.createElement('tr');
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

        function confirmAutorizar() {
            if (!selectedFolio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ningún registro seleccionado',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }

            Swal.fire({
                title: '¿Autorizar esta formulación?',
                html: `Se autorizará la formulación con folio <strong>${selectedFolio}</strong>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, autorizar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Crear un formulario temporal para enviar la solicitud
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/eng-formulacion/${selectedFolio}/autorizar`;

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);

                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PATCH';
                    form.appendChild(methodInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // ===== FUNCIONES PARA MODAL DE COMPONENTES =====
        let componentesData = [];
        let formulaActual = '';
        let kilosFormula = 0;
        let componentesCreateData = [];
        let formulaCreateActual = '';
        let kilosCreateFormula = 0;

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

        function renderizarTablaComponentesCreate() {
            const tbody = document.getElementById('create_componentes_tbody');
            if (!tbody) return;

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
                row.className = 'hover:bg-blue-50 transition-colors';

                const consumoUnitario = parseFloat(comp.ConsumoUnitario) || 0;
                const consumoTotal = consumoUnitario * kilosCreateFormula;
                const disabledAttr = viewOnlyMode ? 'disabled' : '';
                const disabledClass = viewOnlyMode ? 'bg-gray-100 cursor-not-allowed' : '';

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
                            class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-right font-semibold text-blue-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${disabledClass}" ${disabledAttr}>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function agregarFilaComponenteCreate() {
            componentesCreateData.push({
                ItemId: '',
                ItemName: '',
                ConfigId: '',
                ConsumoUnitario: 0,
                Unidad: '',
                Almacen: ''
            });
            renderizarTablaComponentesCreate();
            document.getElementById('create_componentes_tabla_container').classList.remove('hidden');
            document.getElementById('create_componentes_loading').classList.add('hidden');
            document.getElementById('create_componentes_error').classList.add('hidden');
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
                return {
                    ItemId: itemId,
                    ItemName: itemName,
                    ConfigId: configId,
                    ConsumoUnitario: parseFloat(original.ConsumoUnitario) || 0,
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
            const select = document.getElementById('create_folio_prog');
            if (select && select.value) {
                cargarDatosPrograma(select, false);
            }

            document.querySelectorAll('.obs-checkbox').forEach(cb => {
                cb.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
                cb.addEventListener('change', () => {
                    const folio = cb.dataset.folio || '';
                    if (cb.checked) {
                        abrirModalObservaciones(cb);
                    } else {
                        delete observaciones[folio];
                        cb.title = '';
                    }
                });
            });

            const thFecha = document.getElementById('th-fecha');
            if (thFecha) {
                thFecha.setAttribute('aria-sort', 'none');
                thFecha.addEventListener('click', toggleOrdenFecha);
            }

            const kilosInput = document.getElementById('create_kilos');
            if (kilosInput) {
                kilosCreateFormula = parseFloat(kilosInput.value) || 0;
                kilosInput.addEventListener('input', function() {
                    kilosCreateFormula = parseFloat(this.value) || 0;
                    if (componentesCreateData.length > 0) {
                        renderizarTablaComponentesCreate();
                    }
                });
            }

            const createForm = document.getElementById('createForm');
            if (createForm) {
                createForm.addEventListener('submit', function() {
                    const componentes = obtenerComponentesCreateDesdeTabla();
                    const payload = document.getElementById('create_componentes_payload');
                    if (payload) {
                        payload.value = JSON.stringify(componentes);
                    }
                });
            }
        });

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
