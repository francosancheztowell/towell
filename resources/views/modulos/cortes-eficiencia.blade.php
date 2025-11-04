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
    <!-- Header Section (Inicialmente oculta) -->
    <div id="header-section" class="bg-white shadow-sm -mt-4 p-3 hidden" style="display: none !important;">
        <div class="flex items-center space-x-6">
            <!-- Folio -->
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Folio:</span>
                <input type="text" id="folio" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-20" placeholder="F0001" readonly>
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
                        <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: 100px;">RPM STD</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: 120px;">Eficiencia STD</th>

                        <!-- Horario 1 -->
                        <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa;">Horario 1</th>

                        <!-- Horario 2 -->
                        <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80;">Horario 2</th>

                        <!-- Horario 3 -->
                        <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24;">Horario 3</th>
                    </tr>
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium text-white" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"></th>
                        <th class="px-4 py-3 text-xs font-medium text-white" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"></th>
                        <th class="px-4 py-3 text-xs font-medium text-white" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"></th>

                        <!-- Horario 1 subheaders -->
                        <th class="px-4 py-3 text-xs font-medium text-white bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 100px;">RPM</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 100px;">Eficiencia</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 80px;">Obs</th>

                        <!-- Horario 2 subheaders -->
                        <th class="px-4 py-3 text-xs font-medium text-white bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 100px;">RPM</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 100px;">Eficiencia</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 80px;">Obs</th>

                        <!-- Horario 3 subheaders -->
                        <th class="px-4 py-3 text-xs font-medium text-white bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 100px;">RPM</th>
                        <th class="px-4 py-3 text-xs font-medium text-white bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 100px;">Eficiencia</th>
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
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 500; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}">{{ $j }}</span>
                                            @endfor
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
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}">{{ $j }}</span>
                                            @endfor
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
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 500; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}">{{ $j }}</span>
                                            @endfor
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
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}">{{ $j }}</span>
                                            @endfor
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
                            <input type="text" class="w-full py-0.5 border border-gray-200 rounded text-sm bg-green-50 text-center" placeholder="Eficiencia" data-telar="{{ $i }}" data-field="eficiencia_std" readonly>
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
    </div>

    <!-- El selector grande ha sido reemplazado por selectores inline en cada celda -->

</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Variables globales
    let currentFolio = null;
    let isEditing = false;
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

    // Obtener valor predeterminado según horario
    function obtenerValorPredeterminado(telar, horario, tipo) {
        let valor = 0;
        
        if (horario === 1) {
            // Horario 1: usar valores STD
            const stdInput = document.querySelector(`input[data-telar="${telar}"][data-field="${tipo === 'rpm' ? 'rpm_std' : 'eficiencia_std'}"]`);
            if (stdInput && stdInput.value) {
                const stdValue = parseFloat(stdInput.value.replace('%', '')) || 0;
                valor = tipo === 'rpm' ? Math.round(stdValue) : Math.round(stdValue);
            }
        } else if (horario === 2) {
            // Horario 2: usar valores del Horario 1
            const horario1Display = document.querySelector(`button[data-telar="${telar}"][data-horario="1"][data-type="${tipo}"] .valor-display-text`);
            if (horario1Display) {
                const horario1Value = tipo === 'rpm' 
                    ? parseInt(horario1Display.textContent) || 0
                    : parseInt(horario1Display.textContent.replace('%', '')) || 0;
                if (horario1Value > 0) {
                    valor = horario1Value;
                }
            }
        } else if (horario === 3) {
            // Horario 3: usar valores del Horario 2
            const horario2Display = document.querySelector(`button[data-telar="${telar}"][data-horario="2"][data-type="${tipo}"] .valor-display-text`);
            if (horario2Display) {
                const horario2Value = tipo === 'rpm' 
                    ? parseInt(horario2Display.textContent) || 0
                    : parseInt(horario2Display.textContent.replace('%', '')) || 0;
                if (horario2Value > 0) {
                    valor = horario2Value;
                }
            }
        }
        
        return valor;
    }

    // Propagar valor a horarios siguientes
    function propagarValor(telar, horario, tipo, valor) {
        const horariosSiguientes = horario === 1 ? [2, 3] : horario === 2 ? [3] : [];
        const suffix = tipo === 'rpm' ? '' : '%';
        
        horariosSiguientes.forEach(h => {
            const display = document.querySelector(`button[data-telar="${telar}"][data-horario="${h}"][data-type="${tipo}"] .valor-display-text`);
            if (display && (display.textContent === '0' || display.textContent === '0%')) {
                display.textContent = valor + suffix;
            }
        });
    }

    // Funciones para manejo de selectores de valores
    function toggleValorSelector(btn) {
        // Cerrar todos los otros selectores primero
        closeAllValorSelectors();
        
        const container = btn.parentElement;
        const selector = container.querySelector('.valor-edit-container');
        const telar = btn.getAttribute('data-telar');
        const horario = btn.getAttribute('data-horario');
        const tipo = btn.getAttribute('data-type');
        
        if (selector.classList.contains('hidden')) {
            // Mostrar selector
            selector.classList.remove('hidden');
            
            // Obtener valor actual y pre-seleccionarlo
            const currentText = btn.querySelector('.valor-display-text').textContent;
            const currentValue = tipo === 'rpm' ? parseInt(currentText) || 0 : parseInt(currentText.replace('%', '')) || 0;
            
            // Si es 0, usar valor predeterminado
            const finalValue = currentValue === 0 ? obtenerValorPredeterminado(telar, horario, tipo) : currentValue;
            
            // Resaltar la opción actual
            highlightCurrentOption(selector, finalValue);
            
            // Scroll al valor actual
            scrollToCurrentValue(selector, finalValue);
        } else {
            // Ocultar selector
            selector.classList.add('hidden');
        }
    }

    function closeAllValorSelectors() {
        document.querySelectorAll('.valor-edit-container').forEach(container => {
            container.classList.add('hidden');
        });
    }

    function selectNumberOption(option) {
        const value = parseInt(option.getAttribute('data-value'));
        const container = option.closest('.valor-edit-container');
        const btn = container.parentElement.querySelector('.valor-display-btn');
        const telar = btn.getAttribute('data-telar');
        const horario = parseInt(btn.getAttribute('data-horario'));
        const tipo = btn.getAttribute('data-type');
        
        // Actualizar el display
        const displayText = btn.querySelector('.valor-display-text');
        displayText.textContent = tipo === 'rpm' ? value.toString() : value + '%';
        
        // Propagar valor a horarios siguientes si es necesario
        propagarValor(telar, horario, tipo, value);
        
        // Cerrar selector
        container.classList.add('hidden');
        
        // Mostrar feedback visual
        btn.classList.add('bg-green-100');
        setTimeout(() => btn.classList.remove('bg-green-100'), 300);
    }

    function highlightCurrentOption(selector, value) {
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
    }

    function scrollToCurrentValue(selector, value) {
        const scrollContainer = selector.querySelector('.number-scroll-container');
        const option = selector.querySelector(`[data-value="${value}"]`);
        
        if (scrollContainer && option) {
            const containerWidth = scrollContainer.clientWidth;
            const optionLeft = option.offsetLeft;
            const optionWidth = option.clientWidth;
            
            // Calcular posición de scroll para centrar la opción
            const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);
            
            scrollContainer.scrollTo({
                left: Math.max(0, scrollLeft),
                behavior: 'smooth'
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

    function inicializarHora() {
        const horaFormateada = actualizarHora();
    }

    // Inicialización optimizada
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar cache de elementos
        initElements();

        // Establecer fecha actual
        elements.fecha.value = new Date().toISOString().split('T')[0];

        // Inicializar hora actual
        inicializarHora();

        // Cargar datos del usuario actual (simulado)
        elements.usuario.value = 'Usuario Actual';
        elements.noEmpleado.value = '12345';

        // Obtener turno actual y cargar datos de telares en paralelo
        Promise.all([
            cargarTurnoActual(),
            cargarDatosTelares()
        ]).then(() => {
            // Modo captura por defecto: mostrar tabla y generar folio sin pulsar "+"
            mostrarSegundaTablaSinHeader();
            generarNuevoFolio();
        }).catch(error => {
            // Aún mostrar la tabla aunque falle la carga de datos
            mostrarSegundaTablaSinHeader();
            generarNuevoFolio();
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
            });
        }

        // Detectar cambios para habilitar edición (solo en inputs de STD)
        const stdInputs = document.querySelectorAll('input[data-field="rpm_std"], input[data-field="eficiencia_std"]');
        stdInputs.forEach(input => {
            input.addEventListener('input', function() {
                if (currentFolio && !isEditing) {
                    isEditing = true;
                }
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
                            {{-- console.log(`✅ Eficiencia STD actualizada para telar ${telarNumero}: ${eficienciaFinal}%`); --}}
                        } else {
                            eficienciaInput.value = '0%';
                            eficienciaInput.placeholder = '';
                            {{-- console.log(`⚠️ Eficiencia inválida para telar ${telarNumero}: ${eficienciaValue}`); --}}
                        }
                    }

                    // Actualizar displays de valores con datos de la BD si están disponibles
                    // Esto inicializará los campos con valores predeterminados basados en los STD
                    for (let h = 1; h <= 3; h++) {
                        // RPM
                        const rpmDisplay = document.querySelector(`button[data-telar="${telarNumero}"][data-horario="${h}"][data-type="rpm"] .valor-display-text`);
                        if (rpmDisplay && rpmDisplay.textContent === '0') {
                            const defaultRpm = obtenerValorPredeterminado(telarNumero, h, 'rpm');
                            if (defaultRpm > 0) {
                                rpmDisplay.textContent = defaultRpm.toString();
                            }
                        }
                        
                        // Eficiencia
                        const eficDisplay = document.querySelector(`button[data-telar="${telarNumero}"][data-horario="${h}"][data-type="eficiencia"] .valor-display-text`);
                        if (eficDisplay && eficDisplay.textContent === '0%') {
                            const defaultEfic = obtenerValorPredeterminado(telarNumero, h, 'eficiencia');
                            if (defaultEfic > 0) {
                                eficDisplay.textContent = defaultEfic + '%';
                            }
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
        }
    }

    // Funciones de botones de acción
    async function nuevoCorte() {
        if (isEditing) {
            Swal.fire({
                title: '¿Guardar cambios?',
                text: 'Hay cambios sin guardar. ¿Desea guardarlos antes de crear un nuevo corte?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'No, descartar'
            }).then((result) => {
                if (result.isConfirmed) {
                    guardarCorte();
                } else {
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

                // Cerrar loading
                Swal.close();

                // Mostrar segunda tabla directamente sin animación
                mostrarSegundaTablaSinHeader();

                // Habilitar botones
                enableActionButtons();

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
        enableActionButtons();

        Swal.fire({
            title: 'Modo Edición',
            text: 'Ahora puedes editar los datos del corte',
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

        // Simular guardado
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        setTimeout(() => {
            currentFolio = folio;
            isEditing = false;

            Swal.fire({
                title: 'Guardado Exitoso',
                text: 'Los datos del corte han sido guardados',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });

            enableActionButtons();
        }, 1500);
    }

    function recopilarDatosTelares() {
        const datos = [];
        const inputs = document.querySelectorAll('#telares-body input');

        inputs.forEach(input => {
            const telar = input.getAttribute('data-telar');
            const field = input.getAttribute('data-field');
            const value = input.value;

            if (!datos.find(d => d.telar === telar)) {
                datos.push({ telar: telar });
            }

            const index = datos.findIndex(d => d.telar === telar);
            datos[index][field] = value;
        });

        return datos;
    }

    function limpiarFormulario() {
        document.getElementById('folio').value = '';
        document.getElementById('fecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('turno').value = '';
        document.getElementById('status').value = 'Pendiente';
        disableStatusField(); // Asegurar que esté deshabilitado

        // Limpiar tabla
        const inputs = document.querySelectorAll('#telares-body input');
        inputs.forEach(input => {
            input.value = '';
        });

        currentFolio = null;
        isEditing = false;
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

    // Función para recargar datos manualmente (para testing)
    async function recargarDatosTelares() {
        await cargarDatosTelares();
    }

    // Hacer funciones globales para testing
    window.actualizarHora = actualizarHora;
    window.actualizarYGuardarHora = actualizarYGuardarHora;
    window.recargarDatosTelares = recargarDatosTelares;
    window.cargarDatosTelares = cargarDatosTelares;
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











