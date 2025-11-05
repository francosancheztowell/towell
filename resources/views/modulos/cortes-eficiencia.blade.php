@extends('layouts.app')

@section('page-title', 'Cortes de Eficiencia')

@section('navbar-right')
    <!-- Botones de acción para Cortes de Eficiencia -->
    <div class="flex items-center gap-1 hidden">
        <button id="btn-nuevo" onclick="nuevoCorte()" class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors" title="Nuevo">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
        <button id="btn-editar" onclick="editarCorte()" class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors cursor-not-allowed" disabled title="Editar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        </button>
        <button id="btn-finalizar" onclick="finalizarCorte()" class="p-2 text-orange-600 hover:text-orange-800 hover:bg-orange-100 rounded-md transition-colors cursor-not-allowed" disabled title="Finalizar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </button>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Info del Folio Activo -->
    <div id="folio-activo-info" class="bg-blue-50 border-l-4 border-blue-500 text-blue-900 p-3 mb-4 flex justify-between items-center hidden">
        <div class="flex items-center space-x-3">
            <i class="fas fa-edit text-blue-500"></i>
            <span id="tipo-edicion" class="font-medium">Nuevo Corte</span>
            <span class="text-blue-500">|</span>
            <span>Folio: <span id="folio-activo" class="font-bold"></span></span>
        </div>
    </div>

    <!-- Header Section (Inicialmente oculta) -->
    <div id="header-section" class="bg-white shadow-sm -mt-4 p-3 hidden" style="display: none !important;">
        <div class="flex items-center space-x-6">
            <!-- Folio -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Folio:</span>
                <input type="text" id="folio" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-20" placeholder="CE0001" readonly>
            </div>

            <!-- Fecha -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Fecha:</span>
                <input type="date" id="fecha" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-32" readonly>
            </div>

            <!-- Turno -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Turno:</span>
                <select id="turno" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 w-24">
                    <option value="">Seleccionar</option>
                    <option value="1">Turno 1</option>
                    <option value="2">Turno 2</option>
                    <option value="3">Turno 3</option>
                </select>
            </div>

            <!-- Hora -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Hora:</span>
                <div class="relative">
                    <input type="text" id="hora-actual" class="px-8 py-2 text-sm border border-gray-300 rounded bg-white text-gray-700 cursor-pointer w-24 text-center font-mono" readonly onclick="actualizarYGuardarHora()" title="Click para actualizar y guardar hora en TejEficiencia">
                    <svg class="w-4 h-4 absolute left-2 top-1/2 transform -translate-y-1/2 text-blue-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Usuario -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Usuario:</span>
                <input type="text" id="usuario" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-32" placeholder="Usuario actual" readonly>
            </div>

            <!-- Status -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Status:</span>
                <select id="status" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-24" disabled>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En Proceso">En Proceso</option>
                    <option value="Finalizado">Finalizado</option>
                </select>
            </div>

            <!-- NoEmpleado -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">NoEmpleado:</span>
                <input type="text" id="noEmpleado" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-20" placeholder="12345" readonly>
            </div>
            </div>
        </div>

    <!-- Mensaje inicial (eliminado - se muestra directamente la tabla) -->
    <div id="mensaje-inicial" class="hidden"></div>

    <!-- Main Data Table Section - Compacta (Inicialmente oculta) -->
    <div id="segunda-tabla" class="bg-white shadow overflow-hidden mb-6 hidden -mt-4" style="max-width: 100%;">
        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 80vh;">
                <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: 80px;">Telar</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: 100px;"> STD</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: 120px;">% EF STD</th>

                        <!-- Horario 1 -->
                        <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa;">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <div class="flex items-center gap-2">
                                    <span>Horario 1</span>
                                    <button type="button" title="Tomar hora Horario 1" class="p-1 rounded hover:bg-blue-500 focus:outline-none" onclick="actualizarYGuardarHoraHorario(1)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <span id="hora-horario-1" class="text-xs opacity-75">--:--</span>
                            </div>
                        </th>

                        <!-- Horario 2 -->
                        <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80;">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <div class="flex items-center gap-2">
                                    <span>Horario 2</span>
                                    <button type="button" title="Tomar hora Horario 2" class="p-1 rounded hover:bg-green-500 focus:outline-none" onclick="actualizarYGuardarHoraHorario(2)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <span id="hora-horario-2" class="text-xs opacity-75">--:--</span>
                            </div>
                        </th>

                        <!-- Horario 3 -->
                        <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24;">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <div class="flex items-center gap-2">
                                    <span>Horario 3</span>
                                    <button type="button" title="Tomar hora Horario 3" class="p-1 rounded hover:bg-yellow-500 focus:outline-none" onclick="actualizarYGuardarHoraHorario(3)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <span id="hora-horario-3" class="text-xs opacity-75">--:--</span>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium text-white" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"></th>
                        <th class="px-4 py-3 text-xs font-medium text-white" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"></th>
                        <th class="px-4 py-3 text-xs font-medium text-white" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"></th>

                        <!-- Horario 1 subheaders -->
                        <th class="px-4 py-3 text-xs font-medium text-white bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 100px;">RPM</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 100px;">% EF</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 80px;">Obs</th>

                        <!-- Horario 2 subheaders -->
                        <th class="px-4 py-3 text-xs font-medium text-white bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 100px;">RPM</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 100px;">% EF</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 80px;">Obs</th>

                        <!-- Horario 3 subheaders -->
                        <th class="px-4 py-3 text-xs font-medium text-white bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 100px;">RPM</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 100px;">% EF</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 80px;">Obs</th>
                    </tr>
                </thead>
                <tbody id="telares-body" class="bg-white divide-y divide-gray-100">
                    <!-- Telares (orden según InvSecuenciaCorteEf) -->
                    @foreach($telares ?? [] as $i)
                    <tr class="hover:bg-blue-50">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $i }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                            <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 text-center cursor-not-allowed" placeholder="Cargando..." data-telar="{{ $i }}" data-field="rpm_std" readonly>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                            <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 text-center cursor-not-allowed" placeholder="Cargando..." data-telar="{{ $i }}" data-field="eficiencia_std" readonly>
                        </td>

                        <!-- Horario 1 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="relative">
                                <button type="button" class="valor-display-btn text-sm text-gray-900 font-medium cursor-pointer hover:bg-blue-100 px-3 py-1 rounded transition-colors bg-transparent border-0 w-full text-center" data-telar="{{ $i }}" data-horario="1" data-type="rpm">
                                    <span class="valor-display-text">0</span>
                                </button>
                                <div class="valor-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-64" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="number-options-flex flex space-x-1 min-w-max">
                                            <!-- Opciones generadas dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="relative">
                                <button type="button" class="valor-display-btn text-sm text-gray-900 font-medium cursor-pointer hover:bg-blue-100 px-3 py-1 rounded transition-colors bg-transparent border-0 w-full text-center" data-telar="{{ $i }}" data-horario="1" data-type="eficiencia">
                                    <span class="valor-display-text">0%</span>
                                </button>
                                <div class="valor-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="number-options-flex flex space-x-1 min-w-max">
                                            <!-- Opciones generadas dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                            <input type="checkbox" class="obs-checkbox w-3 h-3 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" data-telar="{{ $i }}" data-horario="1">
                        </td>

                        <!-- Horario 2 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="relative">
                                <button type="button" class="valor-display-btn text-sm text-gray-900 font-medium cursor-pointer hover:bg-green-100 px-3 py-1 rounded transition-colors bg-transparent border-0 w-full text-center" data-telar="{{ $i }}" data-horario="2" data-type="rpm">
                                    <span class="valor-display-text">0</span>
                                </button>
                                <div class="valor-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-64" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="number-options-flex flex space-x-1 min-w-max">
                                            <!-- Opciones generadas dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="relative">
                                <button type="button" class="valor-display-btn text-sm text-gray-900 font-medium cursor-pointer hover:bg-green-100 px-3 py-1 rounded transition-colors bg-transparent border-0 w-full text-center" data-telar="{{ $i }}" data-horario="2" data-type="eficiencia">
                                    <span class="valor-display-text">0%</span>
                                </button>
                                <div class="valor-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="number-options-flex flex space-x-1 min-w-max">
                                            <!-- Opciones generadas dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                            <input type="checkbox" class="obs-checkbox w-3 h-3 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500" data-telar="{{ $i }}" data-horario="2">
                        </td>

                        <!-- Horario 3 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="relative">
                                <button type="button" class="valor-display-btn text-sm text-gray-900 font-medium cursor-pointer hover:bg-yellow-100 px-3 py-1 rounded transition-colors bg-transparent border-0 w-full text-center" data-telar="{{ $i }}" data-horario="3" data-type="rpm">
                                    <span class="valor-display-text">0</span>
                                </button>
                                <div class="valor-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-64" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="number-options-flex flex space-x-1 min-w-max">
                                            <!-- Opciones generadas dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="relative">
                                <button type="button" class="valor-display-btn text-sm text-gray-900 font-medium cursor-pointer hover:bg-yellow-100 px-3 py-1 rounded transition-colors bg-transparent border-0 w-full text-center" data-telar="{{ $i }}" data-horario="3" data-type="eficiencia">
                                    <span class="valor-display-text">0%</span>
                                </button>
                                <div class="valor-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="number-options-flex flex space-x-1 min-w-max">
                                            <!-- Opciones generadas dinámicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                            <input type="checkbox" class="obs-checkbox w-3 h-3 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500" data-telar="{{ $i }}" data-horario="3">
                        </td>
                    </tr>
                    @endforeach

                    {{--
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 px-1 py-2 text-center text-sm font-semibold w-16">{{ $i }}</td>
                        <td class="border border-gray-300 px-0 py-2 w-10">
                            <input type="text" class="w-full py-0.5 border border-gray-200 rounded text-sm bg-blue-50 text-center" placeholder="RPM" data-telar="{{ $i }}" data-field="rpm_std" readonly>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10">
                            <input type="text" class="w-full py-0.5 border border-gray-200 rounded text-sm bg-green-50 text-center" placeholder="% EF" data-telar="{{ $i }}" data-field="eficiencia_std" readonly>
                        </td>

                        <!-- Horario 1 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-center relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium cursor-pointer hover:bg-blue-100 px-3 py-1 rounded transition-colors" id="h1_rpm_display_{{ $i }}" onclick="toggleRpmEdit(this)">0</span>
                                <div class="rpm-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 500; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-blue-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="1" data-type="rpm">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2"><div class="flex items-center justify-center relative"><span class="efic-display text-sm text-gray-900 font-medium cursor-pointer hover:bg-blue-100 px-3 py-1 rounded transition-colors" id="h1_efic_display_{{ $i }}" onclick="toggleEficEdit(this)">0%</span>
                                <div class="efic-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-blue-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="1" data-type="eficiencia">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                            <input type="checkbox" class="w-3 h-3 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" data-telar="{{ $i }}" data-horario="1" onclick="abrirModalObservaciones(this)">
                        </td>

                        <!-- Horario 2 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-center relative"><span class="rpm-display text-sm text-gray-900 font-medium cursor-pointer hover:bg-green-100 px-3 py-1 rounded transition-colors" id="h2_rpm_display_{{ $i }}" onclick="toggleRpmEdit(this)">0</span>
                                <div class="rpm-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 500; $j++)
                                                <span class="number-option inline-block w-6 h-6 text-center leading-6 text-sm font-medium cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="2" data-type="rpm">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2"><div class="flex items-center justify-center relative"><span class="efic-display text-sm text-gray-900 font-medium cursor-pointer hover:bg-green-100 px-3 py-1 rounded transition-colors" id="h2_efic_display_{{ $i }}" onclick="toggleEficEdit(this)">0%</span>
                                <div class="efic-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="2" data-type="eficiencia">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                            <input type="checkbox" class="w-3 h-3 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500" data-telar="{{ $i }}" data-horario="2" onclick="abrirModalObservaciones(this)">
                        </td>

                        <!-- Horario 3 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-center relative"><span class="rpm-display text-sm text-gray-900 font-medium cursor-pointer hover:bg-yellow-100 px-3 py-1 rounded transition-colors" id="h3_rpm_display_{{ $i }}" onclick="toggleRpmEdit(this)">0</span>
                                <div class="rpm-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 500; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="3" data-type="rpm">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2"><div class="flex items-center justify-center relative"><span class="efic-display text-sm text-gray-900 font-medium cursor-pointer hover:bg-yellow-100 px-3 py-1 rounded transition-colors" id="h3_efic_display_{{ $i }}" onclick="toggleEficEdit(this)">0%</span>
                                <div class="efic-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="3" data-type="eficiencia">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                            <input type="checkbox" class="w-3 h-3 text-yellow-600 bg-gray-100 border-gray-300 rounded focus:ring-yellow-500" data-telar="{{ $i }}" data-horario="3" onclick="abrirModalObservaciones(this)">
                        </td>
                    </tr>
                    @endfor
                    --}}
                </tbody>
            </table>
        </div>
        </div>
        <!-- Botón de guardar (COMENTADO) -->
        {{-- 
            El botón de guardar está comentado porque ahora el sistema guarda automáticamente.
            Los datos se guardan automáticamente 1 segundo después de cada cambio en:
            - Valores de RPM o Eficiencia
            - Observaciones
            - Cualquier otro campo de la tabla
            
            El guardado usa CREATE para registros nuevos y UPDATE para registros existentes.
            No es necesario presionar ningún botón para guardar.
        --}}
        {{-- <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex justify-end">
            <button id="btn-guardar-tabla" onclick="guardarDatosTabla()" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md shadow-sm transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Guardar Datos
            </button>
        </div> --}}
    </div>

    <!-- El selector grande ha sido reemplazado por selectores inline en cada celda -->

</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    /*
     * SISTEMA DE GUARDADO AUTOMÁTICO
     * ================================
     * Los datos se guardan automáticamente 1 segundo después de cada cambio.
     * 
     * Flujo de guardado:
     * 1. Al crear un nuevo corte (botón +), se genera un folio y se establece isNewRecord = true
     * 2. Cualquier cambio en la tabla (RPM, Eficiencia, Observaciones) dispara guardarAutomatico()
     * 3. guardarAutomatico() usa la ruta store que internamente usa updateOrCreate()
     * 4. Después del primer guardado exitoso, isNewRecord cambia a false
     * 5. Si se presiona "Editar" en un corte existente, isNewRecord = false desde el inicio
     * 
     * No es necesario presionar ningún botón de guardar manualmente.
     */
    
    // Variables globales
    let currentFolio = null;
    let isEditing = false;
    let isNewRecord = true; // Controla si es un registro nuevo (CREATE) o existente (UPDATE)
    let observaciones = {}; // Almacenar observaciones por telar-horario
    let activeModal = null; // Modal activo para evitar múltiples abiertos

    // Cache de elementos DOM
    const elements = {
        folio: null,
        fecha: null,
        turno: null,
        hora: null,
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
        elements.hora = document.getElementById('hora-actual');
        elements.usuario = document.getElementById('usuario');
        elements.noEmpleado = document.getElementById('noEmpleado');
        elements.status = document.getElementById('status');
        elements.segundaTabla = document.getElementById('segunda-tabla');
        elements.headerSection = document.getElementById('header-section');
    }

    // Las funciones del selector grande han sido reemplazadas por selectores inline

    // Obtener valor del horario anterior cuando se presiona un botón (todos empiezan en blanco)
    function obtenerValorHorarioAnterior(telar, horario, tipo) {
        let valor = 0;
        
        if (horario === 1) {
            // Horario 1: usar valores STD como referencia
            const stdInput = document.querySelector(`input[data-telar="${telar}"][data-field="${tipo === 'rpm' ? 'rpm_std' : 'eficiencia_std'}"]`);
            if (stdInput && stdInput.value) {
                const stdValue = parseFloat(stdInput.value.replace('%', '')) || 0;
                valor = tipo === 'rpm' ? Math.round(stdValue) : Math.round(stdValue);
            }
        } else if (horario === 2) {
            // Horario 2: usar el valor ACTUAL (donde terminó) del Horario 1
            const horario1Display = document.querySelector(`button[data-telar="${telar}"][data-horario="1"][data-type="${tipo}"] .valor-display-text`);
            if (horario1Display && horario1Display.textContent !== '0' && horario1Display.textContent !== '0%') {
                const horario1Value = tipo === 'rpm' 
                    ? parseInt(horario1Display.textContent) || 0
                    : parseInt(horario1Display.textContent.replace('%', '')) || 0;
                valor = horario1Value;
            } else {
                // Si horario 1 está en blanco, usar STD como fallback
                const stdInput = document.querySelector(`input[data-telar="${telar}"][data-field="${tipo === 'rpm' ? 'rpm_std' : 'eficiencia_std'}"]`);
                if (stdInput && stdInput.value) {
                    const stdValue = parseFloat(stdInput.value.replace('%', '')) || 0;
                    valor = tipo === 'rpm' ? Math.round(stdValue) : Math.round(stdValue);
                }
            }
        } else if (horario === 3) {
            // Horario 3: usar el valor ACTUAL (donde terminó) del Horario 2
            const horario2Display = document.querySelector(`button[data-telar="${telar}"][data-horario="2"][data-type="${tipo}"] .valor-display-text`);
            if (horario2Display && horario2Display.textContent !== '0' && horario2Display.textContent !== '0%') {
                const horario2Value = tipo === 'rpm' 
                    ? parseInt(horario2Display.textContent) || 0
                    : parseInt(horario2Display.textContent.replace('%', '')) || 0;
                valor = horario2Value;
            } else {
                // Si horario 2 está en blanco, verificar horario 1
                const horario1Display = document.querySelector(`button[data-telar="${telar}"][data-horario="1"][data-type="${tipo}"] .valor-display-text`);
                if (horario1Display && horario1Display.textContent !== '0' && horario1Display.textContent !== '0%') {
                    const horario1Value = tipo === 'rpm' 
                        ? parseInt(horario1Display.textContent) || 0
                        : parseInt(horario1Display.textContent.replace('%', '')) || 0;
                    valor = horario1Value;
                } else {
                    // Si ambos están en blanco, usar STD como fallback
                    const stdInput = document.querySelector(`input[data-telar="${telar}"][data-field="${tipo === 'rpm' ? 'rpm_std' : 'eficiencia_std'}"]`);
                    if (stdInput && stdInput.value) {
                        const stdValue = parseFloat(stdInput.value.replace('%', '')) || 0;
                        valor = tipo === 'rpm' ? Math.round(stdValue) : Math.round(stdValue);
                    }
                }
            }
        }
        
        return valor;
    }

    // No propagar valores automáticamente - cada horario se mantiene en blanco hasta que se seleccione

    // Funciones para manejo de selectores de valores
    function toggleValorSelector(btn) {
        // Cerrar todos los otros selectores primero
        closeAllValorSelectors();
        
        const container = btn.parentElement;
        const selector = container.querySelector('.valor-edit-container');
        const telar = btn.getAttribute('data-telar');
        const horario = parseInt(btn.getAttribute('data-horario'));
        const tipo = btn.getAttribute('data-type');
        
        if (selector.classList.contains('hidden')) {
            // Obtener valor actual del display
            const currentText = btn.querySelector('.valor-display-text').textContent;
            const currentValue = tipo === 'rpm' ? parseInt(currentText) || 0 : parseInt(currentText.replace('%', '')) || 0;
            
            // Determinar el valor inicial del selector:
            // - Si el campo ya tiene un valor (no es 0), usar ese valor
            // - Si está en blanco (0), usar el valor del horario anterior
            let valorInicial;
            if (currentValue === 0) {
                // Campo vacío: posicionar selector en el valor del horario anterior
                valorInicial = obtenerValorHorarioAnterior(telar, horario, tipo);
            } else {
                // Campo con valor: posicionar selector en el valor actual
                valorInicial = currentValue;
            }
            
            // Generar opciones dinámicamente
            generateNumberOptions(selector, tipo, horario, valorInicial);
            
            // Mostrar selector
            selector.classList.remove('hidden');
            
            // Scroll al valor inicial
            scrollToCurrentValue(selector, valorInicial);
        } else {
            // Ocultar selector
            selector.classList.add('hidden');
        }
    }

    function closeAllValorSelectors() {
        document.querySelectorAll('.valor-edit-container').forEach(container => {
            container.classList.add('hidden');
            
            // Opcional: limpiar opciones para liberar memoria (solo para RPM que tiene 500+ opciones)
            const optionsContainer = container.querySelector('.number-options-flex');
            if (optionsContainer && optionsContainer.children.length > 100) {
                // Solo mantener opciones si son pocas (eficiencia), limpiar si son muchas (RPM)
                setTimeout(() => {
                    if (container.classList.contains('hidden')) {
                        optionsContainer.innerHTML = '';
                    }
                }, 5000); // Limpiar después de 5 segundos si sigue cerrado
            }
        });
    }

    function generateNumberOptions(selector, tipo, horario, currentValue) {
        const optionsContainer = selector.querySelector('.number-options-flex');
        
        // Si ya tiene opciones, no regenerar (cache)
        if (optionsContainer.children.length > 0) {
            highlightCurrentOption(selector, currentValue);
            return;
        }
        
        const maxValue = tipo === 'rpm' ? 500 : 100;
        const hoverClass = horario === 1 ? 'hover:bg-blue-100' : 
                          horario === 2 ? 'hover:bg-green-100' : 'hover:bg-yellow-100';
        
        // Renderizado optimizado: solo crear opciones visibles inicialmente
        const viewportWidth = 300; // Ancho estimado del viewport del selector
        const optionWidth = 36; // w-8 + spacing
        const visibleOptions = Math.ceil(viewportWidth / optionWidth);
        const bufferOptions = 20; // Opciones extra para scroll suave
        
        // Calcular rango inicial basado en currentValue
        const startRange = Math.max(0, currentValue - Math.floor(visibleOptions / 2) - bufferOptions);
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
                option.classList.add('bg-blue-500', 'text-white');
            }
            
            fragment.appendChild(option);
        }
        
        // Agregar placeholders para mantener el scroll correcto
        if (startRange > 0) {
            const startPlaceholder = document.createElement('div');
            startPlaceholder.className = 'inline-block';
            startPlaceholder.style.width = `${startRange * optionWidth}px`;
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
        setupLazyOptionLoading(selector, tipo, horario, maxValue, optionWidth, hoverClass);
    }

    function setupLazyOptionLoading(selector, tipo, horario, maxValue, optionWidth, hoverClass) {
        const scrollContainer = selector.querySelector('.number-scroll-container');
        const optionsContainer = selector.querySelector('.number-options-flex');
        
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
        const container = option.closest('.valor-edit-container');
        const btn = container.parentElement.querySelector('.valor-display-btn');
        const telar = btn.getAttribute('data-telar');
        const horario = parseInt(btn.getAttribute('data-horario'));
        const tipo = btn.getAttribute('data-type');
        
        // Actualizar el display con el valor seleccionado
        const displayText = btn.querySelector('.valor-display-text');
        displayText.textContent = tipo === 'rpm' ? value.toString() : value + '%';
        
        // No propagar a horarios siguientes - cada uno se mantiene independiente y en blanco
        
        // Cerrar selector
        container.classList.add('hidden');
        
        // Mostrar feedback visual
        btn.classList.add('bg-green-100');
        setTimeout(() => btn.classList.remove('bg-green-100'), 300);
        
        // Guardar automáticamente después de cambiar el valor
        guardarAutomatico();
    }

    function highlightCurrentOption(selector, value) {
        // Usar requestAnimationFrame para evitar bloqueo si hay muchas opciones
        requestAnimationFrame(() => {
            // Remover highlight previo
            selector.querySelectorAll('.number-option').forEach(opt => {
                opt.classList.remove('bg-blue-500', 'text-white');
                opt.classList.add('bg-gray-100', 'text-gray-700');
            });
            
            // Highlight opción actual
            const currentOption = selector.querySelector(`[data-value="${value}"]`);
            if (currentOption) {
                currentOption.classList.remove('bg-gray-100', 'text-gray-700');
                currentOption.classList.add('bg-blue-500', 'text-white');
            }
        });
    }

    function scrollToCurrentValue(selector, value) {
        const scrollContainer = selector.querySelector('.number-scroll-container');
        
        if (scrollContainer) {
            // Usar requestAnimationFrame para un scroll más suave
            requestAnimationFrame(() => {
                const option = selector.querySelector(`[data-value="${value}"]`);
                
                if (option) {
                    const containerWidth = scrollContainer.clientWidth;
                    const optionLeft = option.offsetLeft;
                    const optionWidth = option.clientWidth;
                    
                    // Calcular posición de scroll para centrar la opción
                    const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);
                    
                    scrollContainer.scrollTo({
                        left: Math.max(0, scrollLeft),
                        behavior: 'smooth'
                    });
                } else {
                    // Si no existe la opción, calcular posición estimada
                    const optionWidth = 36; // w-8 + spacing estimado
                    const estimatedLeft = value * optionWidth;
                    const containerWidth = scrollContainer.clientWidth;
                    const scrollLeft = estimatedLeft - (containerWidth / 2);
                    
                    scrollContainer.scrollTo({
                        left: Math.max(0, scrollLeft),
                        behavior: 'smooth'
                    });
                }
            });
        }
    }

    // Funciones para manejo de hora
    function actualizarHora() {
        const ahora = new Date();
        const hora = ahora.getHours().toString().padStart(2, '0');
        const minutos = ahora.getMinutes().toString().padStart(2, '0');
        const horaFormateada = `${hora}:${minutos}`;
        
        if (elements.hora) {
            elements.hora.value = horaFormateada;
        }
        
        return horaFormateada;
    }

    async function actualizarYGuardarHora() {
        const horaFormateada = actualizarHora();
        const turno = elements.turno ? elements.turno.value : '';
        const folio = elements.folio ? elements.folio.value : '';
        
        // Determinar horario basado en la hora actual
        const horaActual = parseInt(horaFormateada.split(':')[0]);
        let horario = 1;
        
        if (horaActual >= 6 && horaActual < 14) {
            horario = 1;
        } else if (horaActual >= 14 && horaActual < 22) {
            horario = 2;
        } else {
            horario = 3;
        }

        // Guardar en tabla TejEficiencia si hay folio
        if (folio && currentFolio) {
            try {
                const response = await fetch('/modulo-cortes-de-eficiencia/guardar-hora', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        folio: folio,
                        turno: turno,
                        horario: horario,
                        hora: horaFormateada,
                        fecha: elements.fecha ? elements.fecha.value : new Date().toISOString().split('T')[0]
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'Hora guardada',
                        html: `
                            <div class="text-center">
                                <div class="text-2xl mb-2">${horario === 1 ? '🌅' : horario === 2 ? '☀️' : '🌙'}</div>
                                <p class="font-mono text-lg">${horaFormateada}</p>
                                <p class="text-sm text-gray-600">Horario ${horario} - Folio ${folio}</p>
                            </div>
                        `,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    throw new Error(data.message || 'Error al guardar hora');
                }
            } catch (error) {
                Swal.fire({
                    title: 'Hora actualizada',
                    html: `
                        <div class="text-center">
                            <div class="text-2xl mb-2">${horario === 1 ? '🌅' : horario === 2 ? '☀️' : '🌙'}</div>
                            <p class="font-mono text-lg">${horaFormateada}</p>
                            <p class="text-sm text-gray-600">Horario ${horario}</p>
                            <p class="text-xs text-red-500 mt-1">No guardado en BD</p>
                        </div>
                    `,
                    icon: 'warning',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        } else {
            // Solo mostrar actualización sin guardar
            Swal.fire({
                title: 'Hora actualizada',
                html: `
                    <div class="text-center">
                        <div class="text-2xl mb-2">${horario === 1 ? '🌅' : horario === 2 ? '☀️' : '🌙'}</div>
                        <p class="font-mono text-lg">${horaFormateada}</p>
                        <p class="text-sm text-gray-600">Horario ${horario}</p>
                    </div>
                `,
                icon: 'info',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    }

    // Actualizar y guardar hora para un horario específico desde encabezado
    async function actualizarYGuardarHoraHorario(horario) {
        const folio = elements.folio ? elements.folio.value : '';
        const turno = elements.turno ? elements.turno.value : '';
        const fecha = elements.fecha ? elements.fecha.value : new Date().toISOString().split('T')[0];
        const ahora = new Date();
        const horaFormateada = `${ahora.getHours().toString().padStart(2,'0')}:${ahora.getMinutes().toString().padStart(2,'0')}`;

        if (!folio || !turno) {
            Swal.fire({
                title: 'Datos faltantes',
                text: 'Folio y turno son requeridos para guardar la hora',
                icon: 'warning',
                timer: 2000,
                showConfirmButton: false
            });
            return;
        }

        // Mostrar alerta de confirmación
        const confirmacion = await Swal.fire({
            title: '¿Actualizar hora?',
            text: `¿Seguro que quieres actualizar la hora del Horario ${horario}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmacion.isConfirmed) {
            return;
        }

        try {
            const response = await fetch('/modulo-cortes-de-eficiencia/guardar-hora', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    folio: folio,
                    turno: turno,
                    horario: horario,
                    hora: horaFormateada,
                    fecha: fecha
                })
            });

            const data = await response.json();
            if (data.success) {
                // Actualizar la hora en el header del horario correspondiente
                const horaElement = document.getElementById(`hora-horario-${horario}`);
                if (horaElement) {
                    horaElement.textContent = horaFormateada;
                }
                
                Swal.fire({
                    title: 'Hora guardada',
                    text: `Horario ${horario} - ${horaFormateada}`,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                throw new Error(data.message || 'Error al guardar hora');
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: error.message,
                icon: 'error'
            });
        }
    }

    function inicializarHora() {
        const horaFormateada = actualizarHora();
    }

    // Inicialización optimizada
    // Función para mostrar el folio activo
    function mostrarFolioActivo(folio, tipo = 'Nuevo Corte') {
        const infoDiv = document.getElementById('folio-activo-info');
        const folioSpan = document.getElementById('folio-activo');
        const tipoSpan = document.getElementById('tipo-edicion');
        
        if (infoDiv && folioSpan && tipoSpan) {
            folioSpan.textContent = folio;
            tipoSpan.textContent = tipo;
            infoDiv.classList.remove('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar cache de elementos
        initElements();

        // Establecer fecha actual
        elements.fecha.value = new Date().toISOString().split('T')[0];

        // Inicializar hora actual
        inicializarHora();

        // Cargar datos del usuario actual
        elements.usuario.value = 'Usuario Actual';
        elements.noEmpleado.value = '12345';

        // Obtener turno actual y cargar datos de telares en paralelo
        Promise.all([
            cargarTurnoActual(),
            cargarDatosTelares()
        ]).then(async () => {
            // Detectar si venimos a editar: ?folio=CE000X
            const params = new URLSearchParams(window.location.search);
            const folioParam = params.get('folio');

            mostrarSegundaTablaSinHeader();

            if (folioParam) {
                await cargarCorteExistente(folioParam);
            } else {
                await generarNuevoFolio();
            }
        }).catch(async error => {
            mostrarSegundaTablaSinHeader();
            const params = new URLSearchParams(window.location.search);
            const folioParam = params.get('folio');
            if (folioParam) {
                await cargarCorteExistente(folioParam);
            } else {
                await generarNuevoFolio();
            }
        });

        // Delegación de eventos para clicks en displays de valores y selectores
        document.getElementById('telares-body').addEventListener('click', function(e) {
            // Manejar clicks en botones de valor para mostrar selector
            const valorBtn = e.target.closest('.valor-display-btn');
            if (valorBtn) {
                e.preventDefault();
                e.stopPropagation();
                toggleValorSelector(valorBtn);
                return;
            }

            // Manejar clicks en opciones de números
            const numberOption = e.target.closest('.number-option');
            if (numberOption) {
                e.preventDefault();
                e.stopPropagation();
                selectNumberOption(numberOption);
                return;
            }

            // Manejar clicks en checkboxes de observaciones
            const checkbox = e.target.closest('.obs-checkbox');
            if (checkbox) {
                abrirModalObservaciones(checkbox);
                return;
            }

            // Cerrar selectores si se hace click fuera
            const isInsideSelector = e.target.closest('.valor-edit-container');
            if (!isInsideSelector) {
                closeAllValorSelectors();
            }
        });

        // Auto-guardar cuando se cambie el folio
        if (elements.folio) {
            elements.folio.addEventListener('blur', function() {
                if (this.value && !currentFolio) {
                    currentFolio = this.value;
                    enableActionButtons();
                }
                // Guardar automáticamente al cambiar el folio
                guardarAutomatico();
            });
        }
        
        // Auto-guardar cuando se cambie el turno
        if (elements.turno) {
            elements.turno.addEventListener('change', function() {
                if (currentFolio) {
                    guardarAutomatico();
                }
            });
        }
        
        // Auto-guardar cuando se cambie la fecha
        if (elements.fecha) {
            elements.fecha.addEventListener('change', function() {
                if (currentFolio) {
                    guardarAutomatico();
                }
            });
        }

        // Detectar cambios en inputs STD y guardar automáticamente
        const stdInputs = document.querySelectorAll('input[data-field="rpm_std"], input[data-field="eficiencia_std"]');
        stdInputs.forEach(input => {
            input.addEventListener('input', function() {
                if (currentFolio && !isEditing) {
                    isEditing = true;
                }
                // Guardar automáticamente después de cambiar valores STD
                guardarAutomatico();
            });
        });

        // Event listener global para cerrar selectores al hacer clic fuera
        document.addEventListener('click', function(e) {
            const isInsideSelector = e.target.closest('.valor-edit-container');
            const isDisplayBtn = e.target.closest('.valor-display-btn');
            
            if (!isInsideSelector && !isDisplayBtn) {
                closeAllValorSelectors();
            }
        });

        // Event listener para tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllValorSelectors();
            }
        });

        // Manejar botón de Atrás: solo navegación (el guardado es automático)
        const btnBack = document.getElementById('btn-back');
        if (btnBack) {
            btnBack.addEventListener('click', function(e) {
                e.preventDefault();
                // Simplemente navegar hacia atrás, el guardado es automático
                window.location.href = '/modulo-cortes-de-eficiencia/consultar';
            });
        }
    });

    async function cargarTurnoActual() {
        try {
            const response = await fetch('/modulo-cortes-de-eficiencia/turno-info', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                document.getElementById('turno').value = data.turno;
            }

        } catch (error) {
            // Error silencioso para información del turno
        }
    }

    async function cargarDatosTelares() {
        try {
            const response = await fetch('/modulo-cortes-de-eficiencia/datos-programa-tejido', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.telares && Array.isArray(data.telares)) {
                let telaresActualizados = 0;
                
                data.telares.forEach(telar => {
                    const telarNumero = telar.NoTelar || telar.noTelar || telar.telar;

                    if (!telarNumero) {
                        return;
                    }

                    // Buscar y llenar campos RPM STD
                    const rpmInput = document.querySelector(`input[data-telar="${telarNumero}"][data-field="rpm_std"]`);
                    if (rpmInput) {
                        const rpmValue = telar.VelocidadSTD || telar.VelocidadStd || telar.RPM || telar.rpm || 0;
                        rpmInput.value = rpmValue;
                        rpmInput.placeholder = '';
                    }

                    // Buscar y llenar campos Eficiencia STD
                    const eficienciaInput = document.querySelector(`input[data-telar="${telarNumero}"][data-field="eficiencia_std"]`);
                    if (eficienciaInput) {
                        const eficienciaValue = telar.EficienciaSTD || telar.EficienciaStd || telar.Eficiencia || telar.eficiencia || 0;
                        const eficiencia = parseFloat(eficienciaValue);
                        
                        if (!isNaN(eficiencia) && eficiencia > 0) {
                            // Si viene como decimal (0.75), convertir a porcentaje
                            const eficienciaFinal = eficiencia > 1 ? eficiencia : eficiencia * 100;
                            eficienciaInput.value = eficienciaFinal.toFixed(0) + '%';
                            eficienciaInput.placeholder = '';
                        } else {
                            eficienciaInput.value = '0%';
                            eficienciaInput.placeholder = '';
                        }
                    }

                    // Todos los horarios empiezan en blanco (0 / 0%)
                    // Los valores del horario anterior se usarán solo cuando se presione el botón
                    for (let h = 1; h <= 3; h++) {
                        // RPM - siempre empieza en 0
                        const rpmDisplay = document.querySelector(`button[data-telar="${telarNumero}"][data-horario="${h}"][data-type="rpm"] .valor-display-text`);
                        if (rpmDisplay) {
                            rpmDisplay.textContent = '0';
                        }
                        
                        // Eficiencia - siempre empieza en 0%
                        const eficDisplay = document.querySelector(`button[data-telar="${telarNumero}"][data-horario="${h}"][data-type="eficiencia"] .valor-display-text`);
                        if (eficDisplay) {
                            eficDisplay.textContent = '0%';
                        }
                    }

                    telaresActualizados++;
                });

                // Actualizar placeholders para campos no encontrados
                const inputsSinDatos = document.querySelectorAll('input[data-field="rpm_std"][placeholder="Cargando..."], input[data-field="eficiencia_std"][placeholder="Cargando..."]');
                inputsSinDatos.forEach(input => {
                    input.placeholder = 'Sin datos';
                });
                
            } else {
                throw new Error(data.message || 'Respuesta inválida del servidor');
            }

            // Fallback: completar STD faltantes con últimos valores guardados en TejEficienciaLine
            await completarStdDesdeHistorial();

        } catch (error) {
            // Mostrar error en placeholders
            const todosLosInputs = document.querySelectorAll('input[data-field="rpm_std"], input[data-field="eficiencia_std"]');
            todosLosInputs.forEach(input => {
                input.placeholder = 'Error al cargar';
                input.title = `Error: ${error.message}`;
            });
            
            // Mostrar alerta al usuario
            Swal.fire({
                title: 'Error al cargar datos',
                text: `No se pudieron cargar los datos de programa tejido: ${error.message}`,
                icon: 'error',
                toast: true,
                position: 'top-end',
                timer: 5000,
                showConfirmButton: false
            });
            // Intentar fallback aún si fallo principal
            try { await completarStdDesdeHistorial(); } catch (e) {}
        }
    }

    // Completar STD faltantes desde último registro guardado (getDatosTelares)
    async function completarStdDesdeHistorial() {
        const response = await fetch('/modulo-cortes-de-eficiencia/datos-telares', {
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
        });
        const data = await response.json();
        if (data.success && data.telares && Array.isArray(data.telares)) {
            data.telares.forEach(telar => {
                const telarNumero = telar.NoTelarId || telar.NoTelar || telar.telar;
                if (!telarNumero) return;
                const rpmInput = document.querySelector(`input[data-telar="${telarNumero}"][data-field="rpm_std"]`);
                const eficInput = document.querySelector(`input[data-telar="${telarNumero}"][data-field="eficiencia_std"]`);
                if (rpmInput && (!rpmInput.value || rpmInput.value === '' || rpmInput.placeholder === 'Sin datos' || rpmInput.placeholder === 'Error al cargar')) {
                    const rpmValue = telar.VelocidadStd || telar.RpmStd || 0;
                    rpmInput.value = rpmValue;
                    rpmInput.placeholder = '';
                }
                if (eficInput && (!eficInput.value || eficInput.value === '' || eficInput.placeholder === 'Sin datos' || eficInput.placeholder === 'Error al cargar')) {
                    const eVal = telar.EficienciaStd || telar.Eficiencia || 0;
                    const ePct = parseFloat(eVal);
                    eficInput.value = isNaN(ePct) ? '0%' : (ePct > 1 ? ePct : ePct * 100).toFixed(0) + '%';
                    eficInput.placeholder = '';
                }
            });
        }
    }

    // Funciones de botones de acción
    async function nuevoCorte() {
        if (isEditing) {
            // Preguntar si desea crear un nuevo corte (los cambios actuales ya están guardados automáticamente)
            Swal.fire({
                title: '¿Crear nuevo corte?',
                text: 'Los datos actuales ya están guardados. ¿Desea crear un nuevo corte?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, crear nuevo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    generarNuevoFolio();
                }
            });
        } else {
            generarNuevoFolio();
        }
    }

    async function generarNuevoFolio() {
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Generando folio...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Hacer petición para generar folio
            const response = await fetch('/modulo-cortes-de-eficiencia/generar-folio', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                // Llenar formulario con datos generados
                document.getElementById('folio').value = data.folio;
                document.getElementById('usuario').value = data.usuario.nombre;
                document.getElementById('noEmpleado').value = data.usuario.numero_empleado;
                document.getElementById('turno').value = data.turno;
                document.getElementById('status').value = 'En Proceso';
                disableStatusField(); // Asegurar que esté deshabilitado
                
                // Actualizar hora al generar nuevo folio
                actualizarHora();

                currentFolio = data.folio;
                isEditing = true;
                isNewRecord = true; // Es un registro nuevo (CREATE)

                // Cerrar loading
                Swal.close();

                // Mostrar segunda tabla directamente sin animación
                mostrarSegundaTablaSinHeader();

                // Habilitar botones
                enableActionButtons();
                
                // Mostrar el folio activo
                mostrarFolioActivo(data.folio, 'Nuevo Corte');

            } else {
                throw new Error(data.message || 'Error al generar folio');
            }

        } catch (error) {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'Error al generar el folio: ' + error.message,
                icon: 'error'
            });
        }
    }

    // Cargar corte existente por folio (modo edición)
    async function cargarCorteExistente(folio) {
        try {
            Swal.fire({
                title: 'Cargando corte...',
                text: `Folio ${folio}`,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            const response = await fetch(`/modulo-cortes-de-eficiencia/${encodeURIComponent(folio)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'No se pudo cargar el corte');
            }

            const info = data.data;
            // Set header fields
            document.getElementById('folio').value = info.folio;
            document.getElementById('fecha').value = info.fecha || new Date().toISOString().split('T')[0];
            document.getElementById('turno').value = info.turno || '';
            document.getElementById('status').value = info.status || 'En Proceso';
            disableStatusField();
            document.getElementById('usuario').value = info.usuario || '';
            document.getElementById('noEmpleado').value = info.noEmpleado || '';

            // Cargar horarios en los headers
            if (info.horario_1) {
                const horaSpan1 = document.getElementById('hora-horario-1');
                if (horaSpan1) horaSpan1.textContent = info.horario_1;
            }
            if (info.horario_2) {
                const horaSpan2 = document.getElementById('hora-horario-2');
                if (horaSpan2) horaSpan2.textContent = info.horario_2;
            }
            if (info.horario_3) {
                const horaSpan3 = document.getElementById('hora-horario-3');
                if (horaSpan3) horaSpan3.textContent = info.horario_3;
            }

            // Fill table values
            // Mostrar el folio activo como edición
            mostrarFolioActivo(info.folio, 'Editando Corte');

            if (Array.isArray(info.datos_telares)) {
                info.datos_telares.forEach(telar => {
                    const telarId = telar.NoTelar;
                    const rpmStdInput = document.querySelector(`input[data-telar="${telarId}"][data-field="rpm_std"]`);
                    const eficienciaStdInput = document.querySelector(`input[data-telar="${telarId}"][data-field="eficiencia_std"]`);
                    if (rpmStdInput) rpmStdInput.value = telar.RpmStd ?? '';
                    if (eficienciaStdInput) {
                        const val = telar.EficienciaStd;
                        eficienciaStdInput.value = (val ?? '') === '' ? '' : `${parseFloat(val).toFixed(0)}%`;
                    }

                    // Displays por horario
                    const setDisplay = (h, type, value) => {
                        const span = document.querySelector(`button[data-telar="${telarId}"][data-horario="${h}"][data-type="${type}"] .valor-display-text`);
                        if (!span) return;
                        if (value === null || value === undefined || value === '') return;
                        span.textContent = type === 'rpm' ? `${parseInt(value)}` : `${parseFloat(value).toFixed(0)}%`;
                    };
                    setDisplay(1, 'rpm', telar.RpmR1);
                    setDisplay(1, 'eficiencia', telar.EficienciaR1);
                    setDisplay(2, 'rpm', telar.RpmR2);
                    setDisplay(2, 'eficiencia', telar.EficienciaR2);
                    setDisplay(3, 'rpm', telar.RpmR3);
                    setDisplay(3, 'eficiencia', telar.EficienciaR3);

                    // Checkboxes de observaciones
                    const cb = (h) => document.querySelector(`input.obs-checkbox[data-telar="${telarId}"][data-horario="${h}"]`);
                    const cb1 = cb(1), cb2 = cb(2), cb3 = cb(3);
                    if (cb1) cb1.checked = !!(telar.StatusOB1);
                    if (cb2) cb2.checked = !!(telar.StatusOB2);
                    if (cb3) cb3.checked = !!(telar.StatusOB3);

                    // Guardar observaciones en memoria
                    if (telar.ObsR1) observaciones[`${telarId}-1`] = telar.ObsR1;
                    if (telar.ObsR2) observaciones[`${telarId}-2`] = telar.ObsR2;
                    if (telar.ObsR3) observaciones[`${telarId}-3`] = telar.ObsR3;
                });
            }

            currentFolio = info.folio;
            isEditing = true;
            isNewRecord = false; // Muy importante: actualizar, no crear
            enableActionButtons();

            Swal.close();
        } catch (error) {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: error.message,
                icon: 'error'
            });
        }
    }

    function mostrarSegundaTablaSinHeader() {
        if (!elements.segundaTabla) return;
        
        elements.segundaTabla.classList.remove('hidden');
        requestAnimationFrame(() => {
            elements.segundaTabla.style.transform = 'translateY(0)';
            elements.segundaTabla.style.opacity = '1';
        });
    }

    function editarCorte() {
        if (!currentFolio) {
            Swal.fire({
                title: 'Error',
                text: 'No hay un corte seleccionado para editar',
                icon: 'warning'
            });
            return;
        }

        isEditing = true;
        isNewRecord = false; // Cambia a modo UPDATE
        enableActionButtons();

        Swal.fire({
            title: 'Modo Edición',
            text: 'Los cambios se guardarán automáticamente',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        });
    }

    function finalizarCorte() {
        if (!currentFolio) {
            Swal.fire({
                title: 'Error',
                text: 'No hay un corte para finalizar',
                icon: 'warning'
            });
            return;
        }

        Swal.fire({
            title: '¿Finalizar Corte?',
            text: '¿Está seguro de que desea finalizar este corte de eficiencia?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Habilitar temporalmente el campo status para cambiarlo
                enableStatusField();
                document.getElementById('status').value = 'Finalizado';
                // Deshabilitar nuevamente después de un momento
                setTimeout(() => {
                    disableStatusField();
                }, 100);

                Swal.fire({
                    title: 'Corte Finalizado',
                    text: 'El corte de eficiencia ha sido finalizado exitosamente',
                    icon: 'success'
                });

                disableActionButtons();
            }
        });
    }

    function guardarCorte() {
        const folio = document.getElementById('folio').value;
        const fecha = document.getElementById('fecha').value;
        const turno = document.getElementById('turno').value;
        const status = document.getElementById('status').value;
        const usuario = document.getElementById('usuario').value;
        const noEmpleado = document.getElementById('noEmpleado').value;

        if (!folio || !fecha || !turno) {
            Swal.fire({
                title: 'Error',
                text: 'Por favor complete todos los campos requeridos',
                icon: 'error'
            });
            return;
        }

        // Recopilar datos de la tabla
        const datosTelares = recopilarDatosTelares();

        // Mostrar loading
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar datos al servidor
        fetch('{{ route("cortes.eficiencia.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                folio: folio,
                fecha: fecha,
                turno: turno,
                status: status,
                usuario: usuario,
                noEmpleado: noEmpleado,
                datos_telares: datosTelares,
                horario1: document.getElementById('hora-actual').value,
                horario2: null,
                horario3: null
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Guardado Exitoso',
                    text: 'El corte de eficiencia ha sido guardado correctamente',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Redirigir a la página de consultar cortes
                    window.location.href = '{{ route("cortes.eficiencia.consultar") }}';
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Error al guardar el corte de eficiencia',
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Error',
                text: 'Error al guardar el corte de eficiencia: ' + error.message,
                icon: 'error'
            });
        });
    }

    function recopilarDatosTelares() {
        const datos = [];
        const filas = document.querySelectorAll('#telares-body tr');

        filas.forEach(fila => {
            const telar = fila.querySelector('td:first-child')?.textContent?.trim();
            if (!telar) return;

            // Obtener valores STD desde los inputs
            const rpmStdInput = fila.querySelector('input[data-telar="' + telar + '"][data-field="rpm_std"]');
            const eficienciaStdInput = fila.querySelector('input[data-telar="' + telar + '"][data-field="eficiencia_std"]');
            
            // Obtener valores de RPM y Eficiencia de cada horario desde los botones
            const rpmR1Btn = fila.querySelector('button[data-telar="' + telar + '"][data-horario="1"][data-type="rpm"] .valor-display-text');
            const eficienciaR1Btn = fila.querySelector('button[data-telar="' + telar + '"][data-horario="1"][data-type="eficiencia"] .valor-display-text');
            const rpmR2Btn = fila.querySelector('button[data-telar="' + telar + '"][data-horario="2"][data-type="rpm"] .valor-display-text');
            const eficienciaR2Btn = fila.querySelector('button[data-telar="' + telar + '"][data-horario="2"][data-type="eficiencia"] .valor-display-text');
            const rpmR3Btn = fila.querySelector('button[data-telar="' + telar + '"][data-horario="3"][data-type="rpm"] .valor-display-text');
            const eficienciaR3Btn = fila.querySelector('button[data-telar="' + telar + '"][data-horario="3"][data-type="eficiencia"] .valor-display-text');

            // Obtener checkboxes de observaciones
            const obsR1Checkbox = fila.querySelector('input.obs-checkbox[data-telar="' + telar + '"][data-horario="1"]');
            const obsR2Checkbox = fila.querySelector('input.obs-checkbox[data-telar="' + telar + '"][data-horario="2"]');
            const obsR3Checkbox = fila.querySelector('input.obs-checkbox[data-telar="' + telar + '"][data-horario="3"]');

            // Obtener observaciones del objeto observaciones
            const key1 = `${telar}-1`;
            const key2 = `${telar}-2`;
            const key3 = `${telar}-3`;

            // Extraer valores numéricos
            const rpmStd = rpmStdInput ? (parseFloat(rpmStdInput.value) || null) : null;
            const eficienciaStd = eficienciaStdInput ? (parseFloat(eficienciaStdInput.value.replace('%', '')) || null) : null;
            
            const rpmR1 = rpmR1Btn ? (parseInt(rpmR1Btn.textContent) || null) : null;
            const eficienciaR1 = eficienciaR1Btn ? (parseFloat(eficienciaR1Btn.textContent.replace('%', '')) || null) : null;
            const rpmR2 = rpmR2Btn ? (parseInt(rpmR2Btn.textContent) || null) : null;
            const eficienciaR2 = eficienciaR2Btn ? (parseFloat(eficienciaR2Btn.textContent.replace('%', '')) || null) : null;
            const rpmR3 = rpmR3Btn ? (parseInt(rpmR3Btn.textContent) || null) : null;
            const eficienciaR3 = eficienciaR3Btn ? (parseFloat(eficienciaR3Btn.textContent.replace('%', '')) || null) : null;

            // StatusOB3: 1 si el checkbox está marcado (indica que hay comentarios)
            const statusOB1 = obsR1Checkbox?.checked ? 1 : 0;
            const statusOB2 = obsR2Checkbox?.checked ? 1 : 0;
            const statusOB3 = obsR3Checkbox?.checked ? 1 : 0;

            datos.push({
                NoTelar: parseInt(telar),
                SalonTejidoId: null,
                RpmStd: rpmStd,
                EficienciaStd: eficienciaStd, // Se copiará desde RpmStd en el backend si no existe
                RpmR1: rpmR1,
                EficienciaR1: eficienciaR1,
                RpmR2: rpmR2,
                EficienciaR2: eficienciaR2,
                RpmR3: rpmR3,
                EficienciaR3: eficienciaR3,
                ObsR1: observaciones[key1] || null,
                ObsR2: observaciones[key2] || null,
                ObsR3: observaciones[key3] || null,
                StatusOB1: statusOB1,
                StatusOB2: statusOB2,
                StatusOB3: statusOB3,
            });
        });

        return datos;
    }

    // Función para guardar datos de la tabla en TejEficienciaLine
    async function guardarDatosTabla() {
        // Validar que haya un folio
        const folio = elements.folio ? elements.folio.value : null;
        if (!folio || folio.trim() === '') {
            Swal.fire({
                title: 'Error',
                text: 'Por favor, genere un folio antes de guardar',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validar fecha y turno
        const fecha = elements.fecha ? elements.fecha.value : null;
        const turno = elements.turno ? elements.turno.value : null;
        
        if (!fecha || !turno) {
            Swal.fire({
                title: 'Error',
                text: 'Por favor, complete la fecha y el turno',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Recopilar datos de la tabla
        const datosTelares = recopilarDatosTelares();

        if (datosTelares.length === 0) {
            Swal.fire({
                title: 'Error',
                text: 'No hay datos para guardar',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Mostrar loading
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('/modulo-cortes-de-eficiencia/guardar-tabla', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    folio: folio,
                    fecha: fecha,
                    turno: turno,
                    datos_telares: datosTelares
                })
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: '¡Guardado exitoso!',
                    text: `Se guardaron ${datosTelares.length} registros en TejEficienciaLine`,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                throw new Error(data.message || 'Error al guardar los datos');
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Error al guardar los datos: ' + error.message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    }

    function limpiarFormulario() {
        document.getElementById('folio').value = '';
        document.getElementById('fecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('turno').value = '';
        document.getElementById('status').value = 'Pendiente';
        disableStatusField(); // Asegurar que esté deshabilitado

        // Limpiar tabla y valores de display
        const inputs = document.querySelectorAll('#telares-body input');
        inputs.forEach(input => {
            input.value = '';
        });
        
        // Limpiar valores de RPM y Eficiencia en los displays
        document.querySelectorAll('.valor-display-text').forEach(display => {
            const btn = display.closest('.valor-display-btn');
            if (btn && btn.getAttribute('data-type') === 'eficiencia') {
                display.textContent = '0%';
            } else {
                display.textContent = '0';
            }
        });
        
        // Limpiar checkboxes de observaciones
        document.querySelectorAll('.obs-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });

        currentFolio = null;
        isEditing = false;
        isNewRecord = true; // Resetear a modo CREATE para el próximo corte
        observaciones = {}; // Limpiar observaciones
        disableActionButtons();
        mostrarMensajeInicial();
    }

    function enableActionButtons() {
        const btnEditar = document.getElementById('btn-editar');
        const btnFinalizar = document.getElementById('btn-finalizar');
        
        if (btnEditar) {
            btnEditar.disabled = false;
            btnEditar.className = 'inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium';
        }
        if (btnFinalizar) {
            btnFinalizar.disabled = false;
            btnFinalizar.className = 'inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors text-sm font-medium';
        }
    }

    function enableStatusField() {
        if (elements.status) {
            elements.status.disabled = false;
            elements.status.className = 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500';
        }
    }

    function disableStatusField() {
        if (elements.status) {
            elements.status.disabled = true;
            elements.status.className = 'w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed';
        }
    }

    function disableActionButtons() {
        const btnEditar = document.getElementById('btn-editar');
        const btnFinalizar = document.getElementById('btn-finalizar');
        
        if (btnEditar) {
            btnEditar.disabled = true;
            btnEditar.className = 'inline-flex items-center px-4 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
        }
        if (btnFinalizar) {
            btnFinalizar.disabled = true;
            btnFinalizar.className = 'inline-flex items-center px-4 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
        }
    }


    // Modal de observaciones optimizado
    async function abrirModalObservaciones(checkbox) {
        const telar = checkbox.dataset.telar;
        const horario = checkbox.dataset.horario;
        const key = `${telar}-${horario}`;
        const observacionExistente = observaciones[key] || '';

        const result = await Swal.fire({
            title: 'Observaciones',
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        Telar: <strong>${telar}</strong> | Horario: <strong>${horario}</strong>
                    </p>
                </div>
                <textarea id="swal-textarea" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" rows="4" placeholder="Escriba sus observaciones aquí...">${observacionExistente}</textarea>
            `,
            width: '500px',
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            didOpen: () => {
                const textarea = document.getElementById('swal-textarea');
                if (textarea) {
                    textarea.focus();
                    textarea.select();
                }
            },
            preConfirm: () => {
                const textarea = document.getElementById('swal-textarea');
                return textarea ? textarea.value : '';
            }
        });

        if (result.isConfirmed) {
            observaciones[key] = result.value;
            checkbox.checked = result.value.trim() !== '';
            
            // Guardar automáticamente después de agregar observación
            guardarAutomatico();
            
            // Toast de confirmación breve
            Swal.fire({
                title: 'Guardado',
                text: `Telar ${telar} - Horario ${horario}`,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    }

    // Función para guardar automáticamente (CREATE o UPDATE según isNewRecord)
    let timeoutGuardado = null;
    async function guardarAutomatico() {
        // Debounce: esperar 1 segundo después del último cambio antes de guardar
        if (timeoutGuardado) {
            clearTimeout(timeoutGuardado);
        }
        
        timeoutGuardado = setTimeout(async () => {
            // Validar que haya un folio
            const folio = elements.folio ? elements.folio.value : null;
            if (!folio || folio.trim() === '') {
                return;
            }

            // Validar fecha y turno
            const fecha = elements.fecha ? elements.fecha.value : null;
            const turno = elements.turno ? elements.turno.value : null;
            
            if (!fecha || !turno) {
                return;
            }

            // Recopilar datos de la tabla
            const datosTelares = recopilarDatosTelares();

            if (datosTelares.length === 0) {
                return;
            }

            // Obtener usuario y noEmpleado
            const usuario = elements.usuario ? elements.usuario.value : '';
            const noEmpleado = elements.noEmpleado ? elements.noEmpleado.value : '';
            const status = elements.status ? elements.status.value : 'En Proceso';

            try {
                // El método store del controlador usa updateOrCreate, por lo que maneja tanto CREATE como UPDATE
                // basándose en si el Folio ya existe en la base de datos
                const response = await fetch('{{ route("cortes.eficiencia.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        folio: folio,
                        fecha: fecha,
                        turno: turno,
                        status: status,
                        usuario: usuario,
                        noEmpleado: noEmpleado,
                        datos_telares: datosTelares,
                        horario1: null,
                        horario2: null,
                        horario3: null
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Después del primer guardado exitoso, ya no es un registro nuevo
                    if (isNewRecord) {
                        isNewRecord = false;
                    }
                    
                    // Mostrar indicador visual breve de guardado
                    mostrarIndicadorGuardado(isNewRecord ? 'Creado' : 'Actualizado');
                } else {
                    mostrarErrorGuardado(data.message);
                }
            } catch (error) {
                mostrarErrorGuardado(error.message);
            }
        }, 1000); // Esperar 1 segundo después del último cambio
    }

    // Función para mostrar indicador visual de guardado
    function mostrarIndicadorGuardado(accion = 'Guardado') {
        // Crear elemento de notificación si no existe
        let notificacion = document.getElementById('notificacion-guardado');
        if (!notificacion) {
            notificacion = document.createElement('div');
            notificacion.id = 'notificacion-guardado';
            notificacion.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 transition-opacity duration-300 z-50';
            notificacion.style.opacity = '0';
            notificacion.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span id="notificacion-texto">Guardado automáticamente</span>
            `;
            document.body.appendChild(notificacion);
        }

        // Actualizar texto
        const textoElement = notificacion.querySelector('#notificacion-texto');
        if (textoElement) {
            textoElement.textContent = `${accion} automáticamente`;
        }

        // Mostrar notificación
        notificacion.style.opacity = '1';

        // Ocultar después de 2 segundos
        setTimeout(() => {
            notificacion.style.opacity = '0';
        }, 2000);
    }

    // Función para mostrar error en guardado
    function mostrarErrorGuardado(mensaje) {
        // Crear elemento de notificación de error si no existe
        let notificacion = document.getElementById('notificacion-error-guardado');
        if (!notificacion) {
            notificacion = document.createElement('div');
            notificacion.id = 'notificacion-error-guardado';
            notificacion.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 transition-opacity duration-300 z-50';
            notificacion.style.opacity = '0';
            notificacion.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span id="notificacion-error-texto">Error al guardar</span>
            `;
            document.body.appendChild(notificacion);
        }

        // Actualizar texto
        const textoElement = notificacion.querySelector('#notificacion-error-texto');
        if (textoElement) {
            textoElement.textContent = `Error: ${mensaje}`;
        }

        // Mostrar notificación
        notificacion.style.opacity = '1';

        // Ocultar después de 3 segundos
        setTimeout(() => {
            notificacion.style.opacity = '0';
        }, 3000);
    }

    // Función para recargar datos manualmente (para testing)
    async function recargarDatosTelares() {
        await cargarDatosTelares();
    }

    // Hacer funciones globales para testing
    window.actualizarHora = actualizarHora;
    window.actualizarYGuardarHora = actualizarYGuardarHora;
    window.recargarDatosTelares = recargarDatosTelares;
    window.cargarDatosTelares = cargarDatosTelares;
    window.guardarAutomatico = guardarAutomatico;
</script>

<style>
    /* Estilos para la tabla */
    table {
        border-collapse: separate;
        border-spacing: 0;
    }

    /* Hover effect para las filas */
    tbody tr:hover {
        background-color: #eff6ff !important;
    }

    /* Estilos para los inputs en la tabla */
    tbody input {
        transition: border-color 0.2s ease;
    }

    tbody input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
        outline: none;
    }

    /* Estilos para headers sticky */
    thead th {
        position: sticky;
        top: 0;
        z-index: 30;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Estilos para uniformizar inputs y selects en header */
    #header-section input,
    #header-section select {
        height: 24px !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        padding: 2px 4px !important;
        box-sizing: border-box !important;
    }

    #header-section select {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 4px center;
        background-size: 12px;
        padding-right: 20px !important;
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

    /* Estilos para scroll horizontal */
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
        min-width: 60px;
    }

    .valor-display-btn:hover {
        transform: scale(1.05);
    }

    .valor-edit-container {
        z-index: 1000;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .number-option {
        transition: all 0.15s ease;
        flex-shrink: 0;
    }

    .number-option:hover {
        transform: scale(1.1);
    }

    .number-option.selected {
        background-color: #2563eb !important;
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
</style>

@endsection











