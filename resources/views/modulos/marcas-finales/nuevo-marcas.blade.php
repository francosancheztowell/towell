@extends('layouts.app')

@section('page-title', 'Marcas')

@section('navbar-right')
    @php
        // Obtener el usuario actual y sus permisos para el módulo de Marcas Finales
        $user = Auth::user();
        $permisosMarcas = null;
        
        if ($user) {
            // Buscar permisos específicos para el módulo de Marcas Finales
            $permisosMarcas = DB::table('SYSUsuariosRoles')
                ->join('SYSRoles', 'SYSUsuariosRoles.idrol', '=', 'SYSRoles.idrol')
                ->where('SYSUsuariosRoles.idusuario', $user->idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->where(function($query) {
                    $query->where('SYSRoles.modulo', 'LIKE', '%Marcas Finales%')
                          ->orWhere('SYSRoles.modulo', 'LIKE', '%Nuevas Marcas Finales%')
                          ->orWhere('SYSRoles.modulo', 'LIKE', '%marcas finales%')
                          ->orWhere('SYSRoles.modulo', 'LIKE', '%nuevas marcas finales%');
                })
                ->select('SYSUsuariosRoles.*', 'SYSRoles.modulo')
                ->first();
        }
        
        // Variables de permisos para usar en la vista
        $puedeCrear = $permisosMarcas && $permisosMarcas->crear;
        $puedeModificar = $permisosMarcas && $permisosMarcas->modificar;
        $puedeEliminar = $permisosMarcas && $permisosMarcas->eliminar;
        $tieneAcceso = $permisosMarcas && $permisosMarcas->acceso;
    @endphp

    <!-- Botones de acción para Marcas -->
    <div class="flex items-center gap-1">
        @if($tieneAcceso)
            @if($puedeCrear)
                <!-- Botón Nuevo - Solo usuarios con permiso de crear -->
                <button id="btn-nuevo" onclick="nuevaMarca()" 
                        class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors" 
                        title="Nuevo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            @endif

            @if($puedeModificar)
                <!-- Botón Editar - Solo usuarios con permiso de modificar -->
                <button id="btn-editar" onclick="editarMarca()" 
                        class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors cursor-not-allowed" 
                        disabled 
                        title="Editar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
            @endif

            @if($puedeEliminar || $puedeModificar)
                <!-- Botón Finalizar - Solo usuarios con permiso de eliminar o modificar -->
                <button id="btn-finalizar" onclick="finalizarMarca()" 
                        class="p-2 text-orange-600 hover:text-orange-800 hover:bg-orange-100 rounded-md transition-colors cursor-not-allowed" 
                        disabled 
                        title="Finalizar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            @endif

            @if(!$puedeCrear && !$puedeModificar && !$puedeEliminar)
                <!-- Usuario tiene acceso pero sin permisos de acción -->
                <div class="text-yellow-600 text-sm px-3 py-2 bg-yellow-50 rounded-md border border-yellow-200">
                    <i class="fas fa-eye mr-1"></i>
                    Solo lectura
                </div>
            @endif
        @else
            <!-- Usuario sin acceso al módulo -->
            <div class="text-red-500 text-sm px-3 py-2 bg-red-50 rounded-md border border-red-200">
                <i class="fas fa-lock mr-1"></i>
                Sin permisos para este módulo
            </div>
        @endif
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Campos ocultos para el formulario -->
    <div class="hidden">
        <input type="hidden" id="folio" name="folio">
        <input type="hidden" id="fecha" name="fecha">
        <input type="hidden" id="turno" name="turno">
        <input type="hidden" id="status" name="status">
        <input type="hidden" id="usuario" name="usuario">
        <input type="hidden" id="noEmpleado" name="noEmpleado">
    </div>

    <!-- Info del Folio Activo -->
    <div id="folio-activo-info" class="bg-purple-50 border-l-4 border-purple-500 text-purple-900 p-3 mb-4 hidden">
        <div class="flex items-center space-x-3">
            <i class="fas fa-edit text-purple-500"></i>
            <span id="tipo-edicion" class="font-medium">Nueva Marca</span>
            <span class="text-purple-500">|</span>
            <span>Folio: <span id="folio-activo" class="font-bold"></span></span>
        </div>
    </div>

    <!-- Mensaje inicial (eliminado - se muestra directamente la tabla) -->
    <div id="mensaje-inicial" class="hidden"></div>

    <!-- Main Data Table Section - Compacta (Visible desde el inicio) -->
    <div id="segunda-tabla" class="bg-white shadow overflow-hidden mb-6 -mt-4" style="max-width: 100%;">
        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 80vh;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-purple-500 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #9333ea; min-width: 100px;">Telar</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #9333ea; min-width: 100px;">Salón</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #9333ea; min-width: 100px;">% Efi</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-purple-600" style="position: sticky; top: 0; z-index: 30; background-color: #7c3aed; min-width: 120px;">Marcas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 100px;">Trama</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 100px;">Pie</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 100px;">Rizo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-red-400" style="position: sticky; top: 0; z-index: 30; background-color: #f87171; min-width: 100px;">Otros</th>
                        </tr>
                    </thead>
                    <tbody id="telares-body" class="bg-white divide-y divide-gray-200">
                        <!-- Telares (orden según InvSecuenciaMarcas) -->
                        @foreach($telares ?? [] as $telar)
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap border-r border-gray-200">{{ $telar->NoTelarId }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap text-center border-r border-gray-200">
                                <input type="text" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-50 text-gray-600 text-center cursor-not-allowed" value="{{ $telar->SalonId ?? '-' }}" data-telar="{{ $telar->NoTelarId }}" data-field="salon" readonly>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap text-center border-r border-gray-200">
                                <div class="relative">
                                    @if($puedeCrear || $puedeModificar)
                                        <!-- % Eficiencia editable como selector -->
                                        <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-indigo-50 hover:border-indigo-400 transition-colors flex items-center justify-between bg-white shadow-sm"
                                            data-telar="{{ $telar->NoTelarId }}" data-type="efi">
                                            <span class="valor-display-text text-indigo-600 font-semibold">-</span>
                                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-indigo-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                            <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                                <div class="number-options-flex p-2 flex gap-1"></div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- % Eficiencia solo lectura -->
                                        <div class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed flex items-center justify-between">
                                            <span class="valor-display-text text-gray-500 font-semibold">-</span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v-3m0 0V9m0 3h3m-3 0H9"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Marcas (editable según permisos) -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    @if($puedeCrear || $puedeModificar)
                                        <!-- Usuario con permisos de edición -->
                                        <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-purple-50 hover:border-purple-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="marcas">
                                            <span class="valor-display-text text-purple-600 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-purple-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                            <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                                <div class="number-options-flex p-2 flex gap-1"></div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Usuario sin permisos de edición -->
                                        <div class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed flex items-center justify-between">
                                            <span class="valor-display-text text-gray-500 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v-3m0 0V9m0 3h3m-3 0H9"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Trama (editable según permisos) -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    @if($puedeCrear || $puedeModificar)
                                        <!-- Usuario con permisos de edición -->
                                        <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-blue-50 hover:border-blue-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="trama">
                                            <span class="valor-display-text text-blue-600 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-blue-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                            <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                                <div class="number-options-flex p-2 flex gap-1"></div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Usuario sin permisos de edición -->
                                        <div class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed flex items-center justify-between">
                                            <span class="valor-display-text text-gray-500 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v-3m0 0V9m0 3h3m-3 0H9"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Pie (editable según permisos) -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    @if($puedeCrear || $puedeModificar)
                                        <!-- Usuario con permisos de edición -->
                                        <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-green-50 hover:border-green-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="pie">
                                            <span class="valor-display-text text-green-600 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-green-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                            <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                                <div class="number-options-flex p-2 flex gap-1"></div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Usuario sin permisos de edición -->
                                        <div class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed flex items-center justify-between">
                                            <span class="valor-display-text text-gray-500 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v-3m0 0V9m0 3h3m-3 0H9"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Rizo -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    @if($puedeCrear || $puedeModificar)
                                        <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-yellow-50 hover:border-yellow-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="rizo">
                                            <span class="valor-display-text text-yellow-600 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-yellow-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                            <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                                <div class="number-options-flex p-2 flex gap-1"></div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="w-full px-3 py-2 border border-gray-200 rounded text-sm font-medium text-gray-500 bg-gray-50 flex items-center justify-between cursor-not-allowed" style="opacity: 0.6;">
                                            <span class="valor-display-text text-gray-400 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Otros -->
                            <td class="px-2 py-2">
                                <div class="relative">
                                    @if($puedeCrear || $puedeModificar)
                                        <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-red-50 hover:border-red-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="otros">
                                            <span class="valor-display-text text-red-600 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-red-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                            <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                                <div class="number-options-flex p-2 flex gap-1"></div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="w-full px-3 py-2 border border-gray-200 rounded text-sm font-medium text-gray-500 bg-gray-50 flex items-center justify-between cursor-not-allowed" style="opacity: 0.6;">
                                            <span class="valor-display-text text-gray-400 font-semibold">0</span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    /*
     * SISTEMA DE GUARDADO AUTOMÁTICO PARA MARCAS
     * ============================================
     * Los datos se guardan automáticamente 1 segundo después de cada cambio.
     * El turno se determina automáticamente basado en la hora actual:
     * - Turno 1: 6:30 AM - 2:30 PM
     * - Turno 2: 2:30 PM - 10:30 PM
     * - Turno 3: 10:30 PM - 6:30 AM
     *
     * Campos editables con rangos específicos:
     * - Marcas: 100-250
     * - Trama, Pie, Rizo, Otros: 1-100
     *
     * Flujo de guardado:
     * 1. Al crear una nueva marca (botón +), se genera un folio y se determina el turno automáticamente
     * 2. Cualquier cambio en la tabla (Marcas, Trama, Pie, Rizo, Otros) dispara guardarAutomatico()
     * 3. guardarAutomatico() usa la ruta store que internamente usa updateOrCreate()
     * 4. Después del primer guardado exitoso, isNewRecord cambia a false
     * 5. Si se presiona "Editar" en una marca existente, mantiene el turno original
     *
     * No es necesario presionar ningún botón de guardar manualmente.
     */
    
    // Variables globales
    let currentFolio = null;
    let isEditing = false;
    let isNewRecord = true; // Controla si es un registro nuevo (CREATE) o existente (UPDATE)

    // Cache de elementos DOM
    const elements = {
        folio: null,
        fecha: null,
        turno: null,
        usuario: null,
        noEmpleado: null,
        status: null,
        segundaTabla: null,
        headerSection: null
    };

    // Inicializar cache de elementos
    function initElements() {
        elements.folio = document.getElementById('folio');
        elements.fecha = document.getElementById('fecha');
        elements.turno = document.getElementById('turno');
        elements.usuario = document.getElementById('usuario');
        elements.noEmpleado = document.getElementById('noEmpleado');
        elements.status = document.getElementById('status');
        elements.segundaTabla = document.getElementById('segunda-tabla');
        elements.headerSection = document.getElementById('header-section');
    }

    // Función para determinar el turno actual basado en la hora
    function determinarTurnoActual() {
        const ahora = new Date();
        const hora = ahora.getHours();
        const minutos = ahora.getMinutes();
        const tiempoActual = hora * 60 + minutos; // Convertir a minutos desde medianoche

        // Turno 1: 6:30 AM - 2:30 PM (6:30 = 390 minutos, 2:30 PM = 870 minutos)
        // Turno 2: 2:30 PM - 10:30 PM (2:30 PM = 870 minutos, 10:30 PM = 1350 minutos)
        // Turno 3: 10:30 PM - 6:30 AM (10:30 PM = 1350 minutos hasta 6:30 AM = 390 minutos del día siguiente)

        // Debug: mostrar información en consola
        console.log(`Hora actual: ${hora}:${minutos.toString().padStart(2, '0')}`);
        console.log(`Tiempo en minutos: ${tiempoActual}`);

        let turnoCalculado;
        if (tiempoActual >= 390 && tiempoActual < 870) {
            turnoCalculado = 1; // Turno 1: 6:30 AM - 2:30 PM
        } else if (tiempoActual >= 870 && tiempoActual < 1350) {
            turnoCalculado = 2; // Turno 2: 2:30 PM - 10:30 PM
        } else {
            turnoCalculado = 3; // Turno 3: 10:30 PM - 6:30 AM (incluye madrugada)
        }

        console.log(`Turno calculado: ${turnoCalculado}`);
        return turnoCalculado;
    }

    // Funciones para manejo de selectores de valores
    function toggleValorSelector(btn) {
        // Cerrar todos los otros selectores primero
        closeAllValorSelectors();
        
        const container = btn.parentElement;
        const selector = container.querySelector('.valor-edit-container');
        const telar = btn.getAttribute('data-telar');
        const tipo = btn.getAttribute('data-type');
        
        if (selector.classList.contains('hidden')) {
            // Obtener valor actual del display
            const currentText = btn.querySelector('.valor-display-text').textContent;
            const currentValue = parseInt(currentText) || 0;
            
            // Generar opciones dinámicamente
            generateNumberOptions(selector, tipo, currentValue);
            
            // Mostrar selector
            selector.classList.remove('hidden');
            
            // Scroll al valor actual
            scrollToCurrentValue(selector, currentValue);
        } else {
            // Ocultar selector
            selector.classList.add('hidden');
        }
    }

    function closeAllValorSelectors() {
        document.querySelectorAll('.valor-edit-container').forEach(container => {
            container.classList.add('hidden');
            
            // Opcional: limpiar opciones para liberar memoria
            const optionsContainer = container.querySelector('.number-options-flex');
            if (optionsContainer && optionsContainer.children.length > 100) {
                setTimeout(() => {
                    if (container.classList.contains('hidden')) {
                        optionsContainer.innerHTML = '';
                    }
                }, 5000); // Limpiar después de 5 segundos si sigue cerrado
            }
        });
    }

    function generateNumberOptions(selector, tipo, currentValue) {
        const optionsContainer = selector.querySelector('.number-options-flex');

        // Si ya tiene opciones, no regenerar (cache)
        if (optionsContainer.children.length > 0) {
            highlightCurrentOption(selector, currentValue);
            return;
        }

        // Definir rangos según el tipo de campo
        let minValue, maxValue, hoverClass;
        switch(tipo) {
            case 'marcas':
                minValue = 100;
                maxValue = 250;
                hoverClass = 'hover:bg-purple-100';
                break;
            case 'efi':
                minValue = 0;
                maxValue = 100;
                hoverClass = 'hover:bg-indigo-100';
                break;
            case 'trama':
                minValue = 1;
                maxValue = 100;
                hoverClass = 'hover:bg-blue-100';
                break;
            case 'pie':
                minValue = 1;
                maxValue = 100;
                hoverClass = 'hover:bg-green-100';
                break;
            case 'rizo':
                minValue = 1;
                maxValue = 100;
                hoverClass = 'hover:bg-yellow-100';
                break;
            case 'otros':
                minValue = 1;
                maxValue = 100;
                hoverClass = 'hover:bg-red-100';
                break;
            default:
                minValue = 1;
                maxValue = 100;
                hoverClass = 'hover:bg-gray-100';
        }

        // Renderizado optimizado: solo crear opciones visibles inicialmente
        const viewportWidth = 300; // Ancho estimado del viewport del selector
        const optionWidth = 36; // w-8 + spacing
        const visibleOptions = Math.ceil(viewportWidth / optionWidth);
        const bufferOptions = 20; // Opciones extra para scroll suave

        // Calcular rango inicial basado en currentValue
        const startRange = Math.max(minValue, currentValue - Math.floor(visibleOptions / 2) - bufferOptions);
        const endRange = Math.min(maxValue + 1, startRange + visibleOptions + (bufferOptions * 2));

        const fragment = document.createDocumentFragment();

        // Crear opciones en el rango visible
        for (let i = startRange; i < endRange; i++) {
            const option = document.createElement('span');
            option.className = `number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer ${hoverClass} rounded transition-colors bg-gray-100 text-gray-700`;
            option.setAttribute('data-value', i.toString());
            option.textContent = i.toString();

            // Highlight si es el valor actual
            if (i === currentValue) {
                option.classList.remove('bg-gray-100', 'text-gray-700');
                option.classList.add('bg-purple-500', 'text-white');
            }

            fragment.appendChild(option);
        }

        // Agregar placeholders para mantener el scroll correcto
        if (startRange > minValue) {
            const startPlaceholder = document.createElement('div');
            startPlaceholder.className = 'inline-block';
            startPlaceholder.style.width = `${(startRange - minValue) * optionWidth}px`;
            startPlaceholder.style.height = '32px';
            optionsContainer.appendChild(startPlaceholder);
        }

        optionsContainer.appendChild(fragment);

        if (endRange < maxValue + 1) {
            const endPlaceholder = document.createElement('div');
            endPlaceholder.className = 'inline-block';
            endPlaceholder.style.width = `${(maxValue + 1 - endRange) * optionWidth}px`;
            endPlaceholder.style.height = '32px';
            optionsContainer.appendChild(endPlaceholder);
        }

        // Configurar lazy loading para el resto de opciones si es necesario
    setupLazyOptionLoading(selector, tipo, maxValue, optionWidth, hoverClass);
    }

    function setupLazyOptionLoading(selector, tipo, maxValue, optionWidth, hoverClass) {
        const scrollContainer = selector.querySelector('.number-scroll-container');
        const optionsContainer = selector.querySelector('.number-options-flex');
        
        // Definir minValue según el tipo
        let minValue;
        switch(tipo) {
            case 'marcas':
                minValue = 100;
                break;
            case 'efi':
                minValue = 0;
                break;
            default:
                minValue = 1;
        }
        
        let isLoading = false;
        
        scrollContainer.addEventListener('scroll', () => {
            if (isLoading) return;
            
            const scrollLeft = scrollContainer.scrollLeft;
            const scrollWidth = scrollContainer.scrollWidth;
            const clientWidth = scrollContainer.clientWidth;
            
            // Si está cerca del final, cargar más opciones
            if (scrollLeft + clientWidth > scrollWidth - 100) {
                isLoading = true;
                
                // Generar más opciones si es necesario
                const currentOptions = optionsContainer.querySelectorAll('.number-option').length;
                if (currentOptions < maxValue + 1) {
                    const fragment = document.createDocumentFragment();
                    const start = currentOptions;
                    const end = Math.min(start + 50, maxValue + 1);
                    
                    for (let i = start; i < end; i++) {
                        const option = document.createElement('span');
                        option.className = `number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer ${hoverClass} rounded transition-colors bg-gray-100 text-gray-700`;
                        option.setAttribute('data-value', i.toString());
                        option.textContent = i.toString();
                        fragment.appendChild(option);
                    }
                    
                    optionsContainer.appendChild(fragment);
                }
                
                isLoading = false;
            }
        });
    }

    function selectNumberOption(option) {
        const value = parseInt(option.getAttribute('data-value'));
        const selector = option.closest('.valor-edit-container');
        const btn = selector.previousElementSibling;
        const displayText = btn.querySelector('.valor-display-text');
        const telar = btn.getAttribute('data-telar');
        const tipo = btn.getAttribute('data-type');
        
        // Actualizar el display
        if (tipo === 'efi') {
            displayText.textContent = `${value}%`;
        } else {
            displayText.textContent = value;
        }
        
        // Cerrar selector
        selector.classList.add('hidden');
        
        // Actualizar display de marcas totales (removido - ahora es editable)
        // actualizarMarcasTotales(telar);
        
        // Guardar automáticamente
        guardarAutomatico();
    }

    function highlightCurrentOption(selector, value) {
        const options = selector.querySelectorAll('.number-option');
        options.forEach(opt => {
            if (parseInt(opt.getAttribute('data-value')) === value) {
                opt.classList.remove('bg-gray-100', 'text-gray-700');
                opt.classList.add('bg-purple-500', 'text-white');
            } else {
                opt.classList.remove('bg-purple-500', 'text-white');
                opt.classList.add('bg-gray-100', 'text-gray-700');
            }
        });
    }

    function scrollToCurrentValue(selector, value) {
        setTimeout(() => {
            const scrollContainer = selector.querySelector('.number-scroll-container');
            const option = selector.querySelector(`.number-option[data-value="${value}"]`);
            
            if (option && scrollContainer) {
                const optionOffset = option.offsetLeft;
                const containerWidth = scrollContainer.clientWidth;
                const optionWidth = option.offsetWidth;
                
                // Centrar la opción en el contenedor
                scrollContainer.scrollLeft = optionOffset - (containerWidth / 2) + (optionWidth / 2);
            }
        }, 10);
    }



    let guardarTimeout = null;
    function guardarAutomatico() {
        // Cancelar guardado previo pendiente
        if (guardarTimeout) {
            clearTimeout(guardarTimeout);
        }
        
        // Esperar 1 segundo antes de guardar
        guardarTimeout = setTimeout(() => {
            guardarDatosTabla();
        }, 1000);
    }

    function guardarDatosTabla() {
        if (!currentFolio) {
            console.error('No hay folio activo');
            return;
        }
        
        // Recopilar datos de la tabla
        const datos = [];
        document.querySelectorAll('#telares-body tr').forEach(row => {
            const telarCell = row.querySelector('td:first-child');
            if (!telarCell) return;
            
            const telar = telarCell.textContent.trim();
            const porcentajeEfiText = row.querySelector(`button[data-telar="${telar}"][data-type="efi"] .valor-display-text`)?.textContent || '';
            const porcentajeEfi = parseInt((porcentajeEfiText || '').toString().replace('%','')) || 0;
            const trama = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="trama"] .valor-display-text`)?.textContent) || 0;
            const pie = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="pie"] .valor-display-text`)?.textContent) || 0;
            const rizo = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="rizo"] .valor-display-text`)?.textContent) || 0;
            const otros = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="otros"] .valor-display-text`)?.textContent) || 0;
            const marcas = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="marcas"] .valor-display-text`)?.textContent) || 0;
            
            datos.push({
                NoTelarId: telar,
                PorcentajeEfi: porcentajeEfi,
                Trama: trama,
                Pie: pie,
                Rizo: rizo,
                Otros: otros,
                Marcas: marcas
            });
        });
        
        // Debug: mostrar datos que se van a enviar
        const datosEnvio = {
            folio: currentFolio,
            fecha: elements.fecha?.value,
            turno: elements.turno?.value,
            status: elements.status?.value,
            lineas: datos
        };
        console.log('Datos enviando al backend:', datosEnvio);
        
        // Enviar al backend
        fetch('/modulo-marcas/store', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(datosEnvio)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Datos guardados automáticamente');
                // Después del primer guardado exitoso, ya no es un registro nuevo
                if (isNewRecord) {
                    isNewRecord = false;
                }
            } else {
                console.error('Error al guardar:', data.message);
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
        });
    }

    function nuevaMarca() {
        // Generar nuevo folio
        generarNuevoFolio();
    }

    function generarNuevoFolio() {
        fetch('/modulo-marcas/generar-folio', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.folio) {
                currentFolio = data.folio;
                isNewRecord = true;
                isEditing = true;
                
                // Determinar turno automáticamente basado en la hora actual
                const turnoActual = determinarTurnoActual();
                
                // Debug: verificar si los elementos existen
                console.log('Elementos encontrados:', {
                    folio: !!elements.folio,
                    fecha: !!elements.fecha,
                    turno: !!elements.turno,
                    status: !!elements.status
                });
                
                // Actualizar UI
                if (elements.folio) elements.folio.value = data.folio;
                if (elements.fecha) elements.fecha.value = new Date().toISOString().split('T')[0];
                if (elements.turno) {
                    elements.turno.value = turnoActual;
                    console.log('Campo turno actualizado a:', turnoActual);
                } else {
                    console.log('Campo turno no encontrado en el DOM');
                }
                if (elements.status) elements.status.value = 'En Proceso';
                if (elements.usuario) elements.usuario.value = data.usuario || '';
                if (elements.noEmpleado) elements.noEmpleado.value = data.numero_empleado || '';
                
                // Mostrar info del folio
                const folioInfo = document.getElementById('folio-activo-info');
                if (folioInfo) {
                    folioInfo.classList.remove('hidden');
                    // Usar el mismo turno calculado anteriormente, no volver a calcularlo
                    const tipoEdicionElement = document.getElementById('tipo-edicion');
                    const folioActivoElement = document.getElementById('folio-activo');
                    
                    if (tipoEdicionElement) {
                        tipoEdicionElement.textContent = 'Nueva Marca';
                    } else {
                        console.error('Elemento tipo-edicion no encontrado en nueva marca');
                    }
                    
                    if (folioActivoElement) {
                        folioActivoElement.textContent = data.folio;
                    } else {
                        console.error('Elemento folio-activo no encontrado en nueva marca');
                    }
                    // Agregar info del turno
                    const turnoInfo = document.createElement('span');
                    turnoInfo.className = 'text-purple-500';
                    turnoInfo.textContent = ' | Turno: ' + turnoActual;
                    const folioElement = document.getElementById('folio-activo');
                    if (folioElement && folioElement.nextSibling) {
                        folioElement.parentNode.insertBefore(turnoInfo, folioElement.nextSibling);
                    }
                }
                
                // Mostrar secciones
                if (elements.headerSection) elements.headerSection.style.display = 'block';
                
                console.log('Folio generado correctamente: ' + data.folio + ' - Turno: ' + turnoActual);
            }
        })
        .catch(error => {
            console.error('Error al generar folio:', error);
            Swal.fire('Error', 'No se pudo generar el folio', 'error');
        });
    }

    function cargarDatosSTD() {
        // Cargar Salón y %Efi desde InvSecuenciaMarcas y ReqProgramaTejido
        fetch('/modulo-marcas/obtener-datos-std', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.datos) {
                // Actualizar campos de Salón y %Efi
                data.datos.forEach(item => {
                    // Actualizar Salón
                    const salonInput = document.querySelector(`input[data-telar="${item.telar}"][data-field="salon"]`);
                    if (salonInput) {
                        salonInput.value = item.salon || '-';
                    }
                    
                    // Actualizar %Efi
                    const efiDisplay = document.querySelector(`button[data-telar="${item.telar}"][data-type="efi"] .valor-display-text`) || document.querySelector(`[data-telar="${item.telar}"][data-type="efi"] .valor-display-text`);
                    if (efiDisplay) {
                        efiDisplay.textContent = (item.porcentaje_efi !== null && item.porcentaje_efi !== undefined) ? (item.porcentaje_efi + '%') : '-';
                    }
                });
                
                console.log('Datos STD cargados correctamente');
            }
        })
        .catch(error => {
            console.error('Error al cargar datos STD:', error);
        });
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        initElements();
        
        // Event delegation para botones de valor
        document.addEventListener('click', function(e) {
            if (e.target.closest('.valor-display-btn')) {
                const btn = e.target.closest('.valor-display-btn');
                toggleValorSelector(btn);
            } else if (e.target.classList.contains('number-option')) {
                selectNumberOption(e.target);
            } else if (!e.target.closest('.valor-edit-container')) {
                // Click fuera de selectores - cerrar todos
                closeAllValorSelectors();
            }
        });
        
        // Cambio en turno - guardar automáticamente
        if (elements.turno) {
            elements.turno.addEventListener('change', guardarAutomatico);
        }
        
        // Verificar si viene con parámetro folio en URL (modo edición)
        const urlParams = new URLSearchParams(window.location.search);
        const folioParam = urlParams.get('folio');
        if (folioParam) {
            cargarMarcaExistente(folioParam);
        } else {
            // Si no hay folio, generar uno nuevo automáticamente
            generarNuevoFolio();
        }
        
        // Cargar datos STD (salón y %Efi) al inicio
        cargarDatosSTD();
        
        // Guardar automáticamente cuando el usuario presiona atrás o cierra la página
        window.addEventListener('beforeunload', function(e) {
            if (currentFolio) {
                // Guardar datos antes de salir
                guardarDatosTabla();
            }
        });
        
        // Interceptar navegación hacia atrás
        window.addEventListener('popstate', function(e) {
            if (currentFolio) {
                guardarDatosTabla();
            }
        });
    });

    function cargarMarcaExistente(folio) {
        fetch(`/modulo-marcas/${folio}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentFolio = folio;
                isNewRecord = false;
                isEditing = true;
                
                // Cargar datos en UI
                if (elements.folio) elements.folio.value = data.marca.Folio;
                if (elements.fecha) elements.fecha.value = data.marca.Date;
                if (elements.turno) {
                    // Al editar una marca existente, SIEMPRE mantener el turno original guardado
                    // No determinar automáticamente el turno al editar
                    elements.turno.value = data.marca.Turno;
                }
                if (elements.status) elements.status.value = data.marca.Status;
                
                // Mostrar info del folio
                const folioInfo = document.getElementById('folio-activo-info');
                if (folioInfo) {
                    folioInfo.classList.remove('hidden');
                    // Al editar, usar siempre el turno guardado en la marca existente
                    const turnoActual = data.marca.Turno;
                    const tipoEdicionElement = document.getElementById('tipo-edicion');
                    const folioActivoElement = document.getElementById('folio-activo');
                    
                    if (tipoEdicionElement) {
                        tipoEdicionElement.textContent = 'Editando Marca';
                    } else {
                        console.error('Elemento tipo-edicion no encontrado');
                    }
                    
                    if (folioActivoElement) {
                        folioActivoElement.textContent = folio;
                    } else {
                        console.error('Elemento folio-activo no encontrado');
                    }
                    // Agregar info del turno
                    const turnoInfo = document.createElement('span');
                    turnoInfo.className = 'text-purple-500';
                    turnoInfo.textContent = ' | Turno: ' + turnoActual;
                    const folioElement = document.getElementById('folio-activo');
                    if (folioElement && folioElement.nextSibling) {
                        folioElement.parentNode.insertBefore(turnoInfo, folioElement.nextSibling);
                    }
                }
                
                // Mostrar secciones
                if (elements.headerSection) elements.headerSection.style.display = 'block';
                
                // Cargar líneas
                if (data.lineas) {
                    data.lineas.forEach(linea => {
                        // Actualizar valores en la tabla
                        // Actualizar valores en la tabla con validación de elementos
                        const efiBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="efi"] .valor-display-text`);
                        const marcasBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="marcas"] .valor-display-text`);
                        const tramaBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="trama"] .valor-display-text`);
                        const pieBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="pie"] .valor-display-text`);
                        const rizoBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="rizo"] .valor-display-text`);
                        const otrosBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="otros"] .valor-display-text`);
                        
                        // Validar que los elementos existan antes de asignar valores
                        if (efiBtn) {
                            const efVal = (typeof linea.Eficiencia === 'number') ? Math.round(linea.Eficiencia * 100) : (parseInt(linea.Eficiencia || linea.EficienciaSTD || linea.EficienciaStd) || 0);
                            efiBtn.textContent = efVal ? `${efVal}%` : '-';
                        } else {
                            console.warn(`Elemento %Efi no encontrado para telar ${linea.NoTelarId}`);
                        }
                        if (marcasBtn) {
                            marcasBtn.textContent = linea.Marcas || 0;
                        } else {
                            console.warn(`Elemento marcas no encontrado para telar ${linea.NoTelarId}`);
                        }
                        
                        if (tramaBtn) {
                            tramaBtn.textContent = linea.Trama || 0;
                        } else {
                            console.warn(`Elemento trama no encontrado para telar ${linea.NoTelarId}`);
                        }
                        
                        if (pieBtn) {
                            pieBtn.textContent = linea.Pie || 0;
                        } else {
                            console.warn(`Elemento pie no encontrado para telar ${linea.NoTelarId}`);
                        }
                        
                        if (rizoBtn) {
                            rizoBtn.textContent = linea.Rizo || 0;
                        } else {
                            console.warn(`Elemento rizo no encontrado para telar ${linea.NoTelarId}`);
                        }
                        
                        if (otrosBtn) {
                            otrosBtn.textContent = linea.Otros || 0;
                        } else {
                            console.warn(`Elemento otros no encontrado para telar ${linea.NoTelarId}`);
                        }
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar marca:', error);
            Swal.fire('Error', 'No se pudo cargar la marca', 'error');
        });
    }
</script>

<style>
    /* Estilos para la tabla */
    table {
        border-collapse: separate;
        border-spacing: 0;
    }

    /* Hover effect para las filas */
    tbody tr:hover {
        background-color: #faf5ff !important;
    }

    /* Estilos para los inputs en la tabla */
    tbody input {
        transition: border-color 0.2s ease;
    }

    tbody input:focus {
        border-color: #9333ea;
        box-shadow: 0 0 0 1px #9333ea;
        outline: none;
    }

    /* Estilos para headers sticky */
    thead th {
        position: sticky;
        top: 0;
        z-index: 30;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Estilos para el scroll */
    .overflow-x-auto::-webkit-scrollbar,
    .overflow-y-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-x-auto::-webkit-scrollbar-track,
    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb,
    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb:hover,
    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Ocultar scrollbar pero mantener funcionalidad */
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    /* Estilos para los selectores de valores */
    .valor-display-btn {
        transition: all 0.2s ease;
        min-width: 80px;
    }

    .valor-display-btn:hover {
        transform: scale(1.02);
    }

    .valor-edit-container {
        z-index: 1000;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .number-option {
        transition: all 0.15s ease;
        flex-shrink: 0;
    }

    .number-option:hover {
        transform: scale(1.1);
    }

    .number-option.selected {
        background-color: #9333ea !important;
        color: white !important;
        transform: scale(1.1);
    }

    /* Animación suave para mostrar/ocultar selector */
    .valor-edit-container.hidden {
        opacity: 0;
        transform: translateX(-50%) translateY(-100%) scale(0.95);
        transition: all 0.2s ease;
    }

    .valor-edit-container:not(.hidden) {
        opacity: 1;
        transform: translateX(-50%) translateY(-100%) scale(1);
        transition: all 0.2s ease;
    }
    
    /* Estilos para el display de marcas totales */
    .marcas-display {
        transition: all 0.3s ease;
    }
    
    .marcas-display:not(:empty) {
        animation: pulse 0.5s ease-out;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }
</style>
@endsection
