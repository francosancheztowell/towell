@extends('layouts.app')

@section('page-title', 'Captura de Fórmulas - Engomado')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="p-2 rounded-lg transition hover:bg-green-100" title="Crear Nueva Fórmula">
            <i class="fa-solid fa-plus text-green-600 text-lg"></i>
        </button>
        <button onclick="openEditModal()" id="btn-edit" disabled class="p-2 rounded-lg transition hover:bg-yellow-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Editar">
            <i class="fa-solid fa-edit text-yellow-600 text-lg"></i>
        </button>
        <button onclick="confirmAutorizar()" id="btn-autorizar" disabled class="p-2 rounded-lg transition hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Autorizar">
            <i class="fa-solid fa-check-circle text-blue-600 text-lg"></i>
        </button>
        <button onclick="confirmDelete()" id="btn-delete" disabled class="p-2 rounded-lg transition hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Eliminar">
            <i class="fa-solid fa-trash text-red-600 text-lg"></i>
        </button>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif
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
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Orden</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Hr</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Cuenta/Titulo</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Calibre</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tipo</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Operador</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Olla</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Formula</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tipo Formula</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Kg.</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Litros</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Prod. AX</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tiempo(Min) Cocinado</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">% Solidos</th>
                    <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Viscocidad</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr class="border-b hover:bg-blue-100 cursor-pointer transition-colors" onclick="selectRow(this, '{{ $item->Folio }}')">
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $item->Folio }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->Hora }}</td>
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
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->MaquinaId }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Kilos ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Litros ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $item->ProdId }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->TiempoCocinado ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Solidos ?? 0, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">{{ number_format($item->Viscocidad ?? 0, 2) }}</td>
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

            <form action="{{ route('eng-formulacion.store') }}" method="POST" class="p-6">
                @csrf
                
                <!-- Sección 1: Selección de Folio -->
                <div class="mb-5">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Folio (Programa Engomado) <span class="text-red-600">*</span></label>
                            <select name="FolioProg" id="create_folio_prog" required onchange="cargarDatosPrograma(this)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                <option value="">-- Seleccione un Folio --</option>
                                @foreach($foliosPrograma as $prog)
                                    <option value="{{ $prog->Folio }}" 
                                            data-cuenta="{{ $prog->Cuenta }}" 
                                            data-calibre="{{ $prog->Calibre }}" 
                                            data-tipo="{{ $prog->RizoPie }}"
                                            data-formula="{{ $prog->BomFormula }}">
                                        {{ $prog->Folio }} - {{ $prog->Cuenta }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Campos ocultos para datos de EngProgramaEngomado -->
                <input type="hidden" name="Cuenta" id="create_cuenta">
                <input type="hidden" name="Calibre" id="create_calibre">
                <input type="hidden" name="Tipo" id="create_tipo">
                <input type="hidden" name="NomEmpl" id="create_nom_empl">
                <input type="hidden" name="CveEmpl" id="create_cve_empl">
                <input type="hidden" name="Formula" id="create_formula">

                <!-- Sección 2: Datos de Captura -->
                <div class="mb-5">
                    <h4 class="text-sm font-semibold text-purple-700 mb-3 pb-2 border-b border-purple-200">Datos de Captura</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Hora <span class="text-red-600">*</span></label>
                            <input type="time" name="Hora" id="create_hora" value="{{ date('H:i') }}" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
                            <input type="text" name="Olla" id="create_olla" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
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

                <!-- Botones -->
                <div class="flex gap-2 justify-end pt-3 border-t border-gray-200 mt-4">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                            class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fa-solid fa-times mr-1"></i>Cancelar
                    </button>
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
                <h3 class="text-xl font-semibold">Editar Formulación</h3>
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

                <!-- Botones -->
                <div class="flex gap-3 justify-end pt-4 border-t border-gray-200 mt-6">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                            class="px-6 py-3 text-base font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fa-solid fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="button" onclick="abrirModalComponentes()" class="px-6 py-3 text-base font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-flask mr-2"></i>Ver Componentes
                    </button>
                    <button type="submit" class="px-6 py-3 text-base font-medium bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition shadow-lg hover:shadow-xl">
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
                            <button onclick="nuevoComponente()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedRow = null;
        let selectedFolio = null;

        function selectRow(row, folio) {
            if (selectedRow) {
                selectedRow.classList.remove('bg-purple-100');
            }
            
            selectedRow = row;
            selectedFolio = folio;
            row.classList.add('bg-purple-100');
            
            enableButtons();
        }

        function enableButtons() {
            ['btn-edit', 'btn-autorizar', 'btn-delete'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
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

            const cells = selectedRow.cells;
            
            // Orden: cells[0] = Folio (Orden), cells[1] = Hr, cells[2] = Status, cells[3] = Cuenta, 
            // Calibre: cells[4], Tipo: cells[5], Operador: cells[6], Olla: cells[7],
            // Formula: cells[8], TipoFormula: cells[9], Kg: cells[10], Litros: cells[11],
            // ProdAX: cells[12], Tiempo: cells[13], Solidos: cells[14], Viscocidad: cells[15]
            
            // Usar el Folio (que ahora es el folio del programa) para buscar componentes
            formulaActual = cells[0].textContent.trim(); // El Folio del registro (que es el FolioProg)
            const kilosValue = cells[10].textContent.trim().replace(/,/g, '');
            
            // Abrir directamente el modal de componentes usando el Folio como referencia
            abrirModalComponentes(kilosValue);
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

        function cargarDatosPrograma(select) {
            const option = select.options[select.selectedIndex];
            
            if (!option.value) {
                // Limpiar campos si no hay selección
                document.getElementById('create_cuenta').value = '';
                document.getElementById('create_calibre').value = '';
                document.getElementById('create_tipo').value = '';
                document.getElementById('create_formula').value = '';
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

            // Obtener operador actual del sistema (ya está cargado en Auth)
            @if(Auth::check())
                document.getElementById('create_nom_empl').value = '{{ Auth::user()->nombre ?? "" }}';
                document.getElementById('create_cve_empl').value = '{{ Auth::user()->numero ?? "" }}';
            @endif

            // Mostrar confirmación con los datos cargados
            Swal.fire({
                icon: 'success',
                title: 'Datos cargados',
                html: `<div class="text-left text-sm">
                    <p><strong>Folio:</strong> ${option.value}</p>
                    <p><strong>Cuenta:</strong> ${cuenta || '-'}</p>
                    <p><strong>Calibre:</strong> ${calibre || '-'}</p>
                    <p><strong>Tipo:</strong> ${tipo || '-'}</p>
                    <p><strong>Fórmula:</strong> ${formula || '-'}</p>
                </div>`,
                confirmButtonColor: '#a855f7',
                timer: 2000,
                timerProgressBar: true
            });
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

        function renderizarTablaComponentes() {
            const tbody = document.getElementById('componentes_tbody');
            tbody.innerHTML = '';
            selectedComponenteIndex = null;
            selectedComponenteRow = null;
            deshabilitarBotonesComponente();

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
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm">${selectedFolio || '-'}</td>
                    <td class="px-4 py-3 text-sm font-medium">${comp.ItemId || ''}</td>
                    <td class="px-4 py-3 text-sm">${comp.ItemName || ''}</td>
                    <td class="px-4 py-3 text-sm">${comp.ConfigId || ''}</td>
                    <td class="px-4 py-3 text-sm text-right">${(comp.ConsumoUnitario || 0).toFixed(4)}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-blue-700">
                        ${calcularConsumoTotal(comp.ConsumoUnitario).toFixed(4)}
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
            habilitarBotonesComponente();
        }

        function habilitarBotonesComponente() {
            document.getElementById('btn-editar-componente').disabled = false;
            document.getElementById('btn-eliminar-componente').disabled = false;
        }

        function deshabilitarBotonesComponente() {
            document.getElementById('btn-editar-componente').disabled = true;
            document.getElementById('btn-eliminar-componente').disabled = true;
        }

        function calcularConsumoTotal(consumoUnitario) {
            return (consumoUnitario || 0) * kilosFormula;
        }

        function nuevoComponente() {
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

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>

@endsection
