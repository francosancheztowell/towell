@extends('layouts.app')

@section('navbar-right')
    <!-- Botones de acción para Cortes de Eficiencia -->
    <div class="flex items-center space-x-2">
        <button id="btn-nuevo" onclick="nuevoCorte()" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm font-medium">
            <i class="fas fa-plus mr-1"></i>Nuevo
        </button>
        <button id="btn-editar" onclick="editarCorte()" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium" disabled>
            <i class="fas fa-edit mr-1"></i>Editar
        </button>
        <button id="btn-finalizar" onclick="finalizarCorte()" class="inline-flex items-center px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors text-sm font-medium" disabled>
            <i class="fas fa-check mr-1"></i>Finalizar
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

    <!-- Mensaje inicial -->
    <div id="mensaje-inicial" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center mb-6">
        <div class="flex flex-col items-center">
            <i class="fas fa-clipboard-list text-6xl text-blue-400 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Cortes de Eficiencia</h3>
            <p class="text-gray-600 mb-4">Haz clic en "Nuevo" para comenzar un nuevo corte de eficiencia</p>
            <div class="text-sm text-gray-500">
                <p>• Selecciona el turno correspondiente</p>
                <p>• Completa los datos de RPM para cada telar</p>
                <p>• Guarda el corte cuando hayas terminado</p>
            </div>
        </div>
    </div>

    <!-- Main Data Table Section - Compacta (Inicialmente oculta) -->
    <div id="segunda-tabla" class="bg-white shadow-sm rounded-lg overflow-hidden mb-6 hidden -mt-4">
        <div class="table-container">
            <table class="min-w-full border-collapse border border-gray-300">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="border border-gray-300 px-1 py-2 text-left text-xs font-semibold text-gray-700 w-16">Telar</th>
                        <th class="border border-gray-300 px-0 py-2 text-center text-xs font-semibold text-gray-700 w-10">RPM STD</th>
                        <th class="border border-gray-300 px-0 py-2 text-center text-xs font-semibold text-gray-700 w-10">Eficiencia STD</th>

                        <!-- Horario 1 -->
                        <th colspan="3" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-blue-100">Horario 1</th>

                        <!-- Horario 2 -->
                        <th colspan="3" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-green-100">Horario 2</th>

                        <!-- Horario 3 -->
                        <th colspan="3" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-yellow-100">Horario 3</th>
                    </tr>
                    <tr>
                        <th class="border border-gray-300 px-1 py-2 text-xs font-medium text-gray-600 w-16"></th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600"></th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600"></th>

                        <!-- Horario 1 subheaders -->
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-blue-50">RPM</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-blue-50">Eficiencia</th>
                        <th class="border border-gray-300 px-0 py-2 text-xs font-medium text-gray-600 bg-blue-50 w-10">Obs</th>

                        <!-- Horario 2 subheaders -->
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-green-50">RPM</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-green-50">Eficiencia</th>
                        <th class="border border-gray-300 px-0 py-2 text-xs font-medium text-gray-600 bg-green-50 w-10">Obs</th>

                        <!-- Horario 3 subheaders -->
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-yellow-50">RPM</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-yellow-50">Eficiencia</th>
                        <th class="border border-gray-300 px-0 py-2 text-xs font-medium text-gray-600 bg-yellow-50 w-10">Obs</th>
                    </tr>
                </thead>
                <tbody id="telares-body">
                    <!-- Telares 201-215 -->
                    @for($i = 201; $i <= 215; $i++)
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
                            <div class="flex items-center justify-between relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium" id="h1_rpm_display_{{ $i }}">0</span>
                                <button class="edit-rpm-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleRpmEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="efic-display text-sm text-gray-900 font-medium" id="h1_efic_display_{{ $i }}">0%</span>
                                <button class="edit-efic-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleEficEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                            <div class="flex items-center justify-between relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium" id="h2_rpm_display_{{ $i }}">0</span>
                                <button class="edit-rpm-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleRpmEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="efic-display text-sm text-gray-900 font-medium" id="h2_efic_display_{{ $i }}">0%</span>
                                <button class="edit-efic-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleEficEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                            <div class="flex items-center justify-between relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium" id="h3_rpm_display_{{ $i }}">0</span>
                                <button class="edit-rpm-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleRpmEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="efic-display text-sm text-gray-900 font-medium" id="h3_efic_display_{{ $i }}">0%</span>
                                <button class="edit-efic-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleEficEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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

                    <!-- Telares 299-320 -->
                    @for($i = 299; $i <= 320; $i++)
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
                            <div class="flex items-center justify-between relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium" id="h1_rpm_display_{{ $i }}">0</span>
                                <button class="edit-rpm-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleRpmEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="efic-display text-sm text-gray-900 font-medium" id="h1_efic_display_{{ $i }}">0%</span>
                                <button class="edit-efic-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleEficEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                            <div class="flex items-center justify-between relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium" id="h2_rpm_display_{{ $i }}">0</span>
                                <button class="edit-rpm-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleRpmEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="efic-display text-sm text-gray-900 font-medium" id="h2_efic_display_{{ $i }}">0%</span>
                                <button class="edit-efic-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleEficEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                            <div class="flex items-center justify-between relative">
                                <span class="rpm-display text-sm text-gray-900 font-medium" id="h3_rpm_display_{{ $i }}">0</span>
                                <button class="edit-rpm-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleRpmEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="efic-display text-sm text-gray-900 font-medium" id="h3_efic_display_{{ $i }}">0%</span>
                                <button class="edit-efic-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleEficEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
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
                </tbody>
            </table>
        </div>
    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Variables globales
    let currentFolio = null;
    let isEditing = false;
    let currentCheckbox = null;
    let observaciones = {}; // Almacenar observaciones por telar-horario

    // Funciones para editores de RPM y Eficiencia
    function inicializarValor(telar, horario, tipo, valor) {
        const display = document.getElementById(`h${horario}_${tipo === 'rpm' ? 'rpm' : 'efic'}_display_${telar}`);
        if (display) {
            if (tipo === 'rpm') {
                display.textContent = valor;
            } else {
                // Para eficiencia, mantener el mismo formato que RPM (valor directo)
                display.textContent = valor + '%';
            }
        }
    }

    function toggleRpmEdit(button) {
        const container = button.nextElementSibling;
        const display = button.previousElementSibling;

        if (container.classList.contains('hidden')) {
            // Cerrar todos los editores abiertos primero
            closeAllEditors();

            // Si el display está en 0, inicializar con valor STD
            if (display.textContent === '0') {
                const telar = button.closest('tr').querySelector('td:first-child').textContent;
                const rpmStdInput = document.querySelector(`input[data-telar="${telar}"][data-field="rpm_std"]`);
                if (rpmStdInput && rpmStdInput.value) {
                    const rpmStd = parseFloat(rpmStdInput.value) || 0;
                    display.textContent = rpmStd;
                }
            }

            // Mostrar editor actual
            container.classList.remove('hidden');
            button.classList.add('hidden');
            display.classList.add('hidden');

            // Inicializar el modal con el valor actual y centrar el scroll
            setTimeout(() => {
                const currentValue = parseInt(display.textContent) || 0;

                // Si el valor es 0, inicializar con el valor STD
                if (currentValue === 0) {
                    const telar = button.closest('tr').querySelector('td:first-child').textContent;
                    const rpmStdInput = document.querySelector(`input[data-telar="${telar}"][data-field="rpm_std"]`);
                    if (rpmStdInput && rpmStdInput.value) {
                        const rpmStd = parseFloat(rpmStdInput.value) || 0;
                        display.textContent = rpmStd;

                        // Buscar el valor exacto
                        const exactValue = Math.round(rpmStd);
                        const exactOption = container.querySelector(`span[data-value="${exactValue}"]`);

                        if (exactOption) {
                            exactOption.classList.remove('bg-gray-100', 'text-gray-700');
                            exactOption.classList.add('bg-blue-500', 'text-white');

                            // Centrar el scroll en el número exacto
                            const scrollContainer = container.querySelector('.number-scroll-container');
                            const containerWidth = scrollContainer.offsetWidth;
                            const optionLeft = exactOption.offsetLeft;
                            const optionWidth = exactOption.offsetWidth;
                            const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                            scrollContainer.scrollTo({
                                left: scrollLeft,
                                behavior: 'smooth'
                            });
                        }
                    }
                } else {
                    // Si ya tiene un valor, buscar el valor exacto
                    const exactValue = Math.round(currentValue);
                    const exactOption = container.querySelector(`span[data-value="${exactValue}"]`);

                    if (exactOption) {
                        exactOption.classList.remove('bg-gray-100', 'text-gray-700');
                        exactOption.classList.add('bg-blue-500', 'text-white');

                        // Centrar el scroll en el número exacto
                        const scrollContainer = container.querySelector('.number-scroll-container');
                        const containerWidth = scrollContainer.offsetWidth;
                        const optionLeft = exactOption.offsetLeft;
                        const optionWidth = exactOption.offsetWidth;
                        const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                        scrollContainer.scrollTo({
                            left: scrollLeft,
                            behavior: 'smooth'
                        });
                    }
                }
            }, 50);
        } else {
            // Ocultar editor
            container.classList.add('hidden');
            button.classList.remove('hidden');
            display.classList.remove('hidden');
        }
    }

    function toggleEficEdit(button) {
        const container = button.nextElementSibling;
        const display = button.previousElementSibling;

        if (container.classList.contains('hidden')) {
            // Cerrar todos los editores abiertos primero
            closeAllEditors();

            // Si el display está en 0%, inicializar con valor STD
            if (display.textContent === '0%') {
                const telar = button.closest('tr').querySelector('td:first-child').textContent;
                const eficienciaStdInput = document.querySelector(`input[data-telar="${telar}"][data-field="eficiencia_std"]`);
                if (eficienciaStdInput && eficienciaStdInput.value) {
                    const eficienciaStd = parseFloat(eficienciaStdInput.value.replace('%', '')) || 0;
                    const eficienciaStdInt = Math.round(eficienciaStd * 100); // 0.77 -> 77
                    display.textContent = eficienciaStdInt + '%';
                }
            }

            // Mostrar editor actual
            container.classList.remove('hidden');
            button.classList.add('hidden');
            display.classList.add('hidden');

            // Inicializar el modal con el valor actual y centrar el scroll
            setTimeout(() => {
                const currentValue = parseInt(display.textContent.replace('%', '')) || 0;

                // Si el valor es 0, inicializar con el valor STD
                if (currentValue === 0) {
                    const telar = button.closest('tr').querySelector('td:first-child').textContent;
                    const eficStdInput = document.querySelector(`input[data-telar="${telar}"][data-field="eficiencia_std"]`);
                    if (eficStdInput && eficStdInput.value) {
                        const eficStd = parseFloat(eficStdInput.value.replace('%', '')) || 0;
                        const eficStdInt = Math.round(eficStd * 100); // 0.77 -> 77
                        display.textContent = eficStdInt + '%';

                        // Buscar el valor exacto en el modal - convertir decimal a entero
                        const exactOption = container.querySelector(`span[data-value="${eficStdInt}"]`);
                        if (exactOption) {
                            exactOption.classList.remove('bg-gray-100', 'text-gray-700');
                            exactOption.classList.add('bg-blue-500', 'text-white');

                            // Centrar el scroll
                            const scrollContainer = container.querySelector('.number-scroll-container');
                            const containerWidth = scrollContainer.offsetWidth;
                            const optionLeft = exactOption.offsetLeft;
                            const optionWidth = exactOption.offsetWidth;
                            const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                            scrollContainer.scrollTo({
                                left: scrollLeft,
                                behavior: 'smooth'
                            });
                        }
                    }
                } else {
                    // Si ya tiene un valor, buscar el valor exacto en el modal
                    const exactOption = container.querySelector(`span[data-value="${currentValue}"]`);
                    if (exactOption) {
                        exactOption.classList.remove('bg-gray-100', 'text-gray-700');
                        exactOption.classList.add('bg-blue-500', 'text-white');

                        // Centrar el scroll
                        const scrollContainer = container.querySelector('.number-scroll-container');
                        const containerWidth = scrollContainer.offsetWidth;
                        const optionLeft = exactOption.offsetLeft;
                        const optionWidth = exactOption.offsetWidth;
                        const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                        scrollContainer.scrollTo({
                            left: scrollLeft,
                            behavior: 'smooth'
                        });
                    }
                }
            }, 50);
        } else {
            // Ocultar editor
            container.classList.add('hidden');
            button.classList.remove('hidden');
            display.classList.remove('hidden');
        }
    }

    function closeAllEditors() {
        document.querySelectorAll('.rpm-edit-container, .efic-edit-container').forEach(container => {
            if (!container.classList.contains('hidden')) {
                const button = container.previousElementSibling;
                const display = button.previousElementSibling;

                container.classList.add('hidden');
                button.classList.remove('hidden');
                display.classList.remove('hidden');
            }
        });
    }

    function propagarValor(telar, horario, tipo, valor) {
        // Si es Horario 1, propagar a Horario 2 y 3 (solo si están en 0)
        if (horario === 1) {
            // Verificar si Horario 2 está en 0
            const displayH2 = document.getElementById(`h2_${tipo === 'rpm' ? 'rpm' : 'efic'}_display_${telar}`);
            if (displayH2 && (displayH2.textContent === '0' || displayH2.textContent === '0%')) {
                inicializarValor(telar, 2, tipo, valor);
            }

            // Verificar si Horario 3 está en 0
            const displayH3 = document.getElementById(`h3_${tipo === 'rpm' ? 'rpm' : 'efic'}_display_${telar}`);
            if (displayH3 && (displayH3.textContent === '0' || displayH3.textContent === '0%')) {
                inicializarValor(telar, 3, tipo, valor);
            }
        }
        // Si es Horario 2, propagar a Horario 3 (solo si está en 0)
        else if (horario === 2) {
            // Verificar si Horario 3 está en 0
            const displayH3 = document.getElementById(`h3_${tipo === 'rpm' ? 'rpm' : 'efic'}_display_${telar}`);
            if (displayH3 && (displayH3.textContent === '0' || displayH3.textContent === '0%')) {
                inicializarValor(telar, 3, tipo, valor);
            }
        }
        // Horario 3 no propaga a nadie
    }

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer fecha actual
        document.getElementById('fecha').value = new Date().toISOString().split('T')[0];

        // Cargar datos del usuario actual (simulado)
        document.getElementById('usuario').value = 'Usuario Actual';
        document.getElementById('noEmpleado').value = '12345';

        // Obtener turno actual
        cargarTurnoActual();

        // Cargar datos de telares desde la base de datos
        cargarDatosTelares();

        // Deshabilitar botones inicialmente
        disableActionButtons();

        // Mostrar mensaje inicial y ocultar tabla
        mostrarMensajeInicial();

        // Event listeners para los números de RPM y Eficiencia
        document.querySelectorAll('.number-option').forEach(option => {
            option.addEventListener('click', function() {
                const telar = this.getAttribute('data-telar');
                const horario = parseInt(this.getAttribute('data-horario'));
                const tipo = this.getAttribute('data-type');
                const valor = parseInt(this.getAttribute('data-value'));

                const container = this.closest('.number-scroll-container');
                const allOptions = container.querySelectorAll('.number-option');
                const editContainer = this.closest('.rpm-edit-container, .efic-edit-container');
                const button = editContainer.previousElementSibling;
                const display = button.previousElementSibling;

                // Remover selección anterior
                allOptions.forEach(opt => {
                    opt.classList.remove('bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'text-white');
                    opt.classList.add('bg-gray-100', 'text-gray-700');
                });

                // Seleccionar opción actual con color según horario
                this.classList.remove('bg-gray-100', 'text-gray-700');
                if (horario === 1) {
                    this.classList.add('bg-blue-500', 'text-white');
                } else if (horario === 2) {
                    this.classList.add('bg-green-500', 'text-white');
                } else if (horario === 3) {
                    this.classList.add('bg-yellow-500', 'text-white');
                }

                // Actualizar display
                if (tipo === 'rpm') {
                    display.textContent = valor;
                } else {
                    // Para eficiencia, mantener el mismo formato que RPM (valor directo)
                    display.textContent = valor + '%';
                }

                // Propagar valor
                propagarValor(telar, horario, tipo, valor);

                // Centrar el número seleccionado
                const containerWidth = container.offsetWidth;
                const optionLeft = this.offsetLeft;
                const optionWidth = this.offsetWidth;
                const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                container.scrollTo({
                    left: scrollLeft,
                    behavior: 'smooth'
                });

                // Ocultar el editor después de seleccionar
                setTimeout(() => {
                    editContainer.classList.add('hidden');
                    button.classList.remove('hidden');
                    display.classList.remove('hidden');
                }, 500);
            });
        });

        // Cerrar editores al hacer clic fuera de ellos
        document.addEventListener('click', function(event) {
            const isInsideEditor = event.target.closest('.rpm-edit-container, .efic-edit-container');
            const isEditButton = event.target.closest('.edit-rpm-btn, .edit-efic-btn');

            if (!isInsideEditor && !isEditButton) {
                closeAllEditors();
            }
        });

        // Auto-guardar cuando se cambie el folio
        const folioElement = document.getElementById('folio');
        if (folioElement) {
            folioElement.addEventListener('blur', function() {
                if (this.value && !currentFolio) {
                    currentFolio = this.value;
                    enableActionButtons();
                }
            });
        }

        // Detectar cambios en los inputs para habilitar edición
        document.querySelectorAll('#telares-body input').forEach(input => {
            input.addEventListener('input', function() {
                if (currentFolio && !isEditing) {
                    isEditing = true;
                }
            });
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
                console.log('Turno actual cargado:', data.turno, data.descripcion);
            } else {
                console.error('Error al cargar turno:', data.message);
            }

        } catch (error) {
            console.error('Error al cargar información del turno:', error);
        }
    }

    async function cargarDatosTelares() {
        try {
            const response = await fetch('/modulo-cortes-de-eficiencia/datos-telares', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                // Llenar los campos de RPM STD y Eficiencia STD con los datos de la base de datos
                data.telares.forEach(telar => {
                    const telarNumero = telar.NoTelarId;

                    // Buscar y llenar campos RPM STD
                    const rpmInput = document.querySelector(`input[data-telar="${telarNumero}"][data-field="rpm_std"]`);
                    if (rpmInput) {
                        rpmInput.value = telar.VelocidadStd || '';
                    }

                    // Buscar y llenar campos Eficiencia STD
                    const eficienciaInput = document.querySelector(`input[data-telar="${telarNumero}"][data-field="eficiencia_std"]`);
                    if (eficienciaInput) {
                        const eficiencia = telar.EficienciaStd || '';
                        // Formatear como porcentaje de dos dígitos
                        if (eficiencia && !isNaN(eficiencia)) {
                            eficienciaInput.value = parseFloat(eficiencia).toFixed(2) + '%';
                        } else {
                            eficienciaInput.value = '';
                        }
                    }

                    // NO inicializar automáticamente - dejar en 0 hasta que el usuario haga clic
                    // Los valores STD se muestran solo en los campos de RPM STD y Eficiencia STD
                });

                console.log('Datos de telares cargados:', data.telares.length, 'telares');
            } else {
                console.error('Error al cargar datos de telares:', data.message);
            }

        } catch (error) {
            console.error('Error al cargar datos de telares:', error);
        }
    }

    // Funciones de botones de acción
    async function nuevoCorte() {
        console.log('Función nuevoCorte llamada');
        console.log('isEditing:', isEditing);

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
            console.log('Llamando a generarNuevoFolio');
            generarNuevoFolio();
        }
    }

    async function generarNuevoFolio() {
        console.log('Función generarNuevoFolio llamada');
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

            console.log('Haciendo petición a /modulo-cortes-de-eficiencia/generar-folio');
            // Hacer petición para generar folio
            const response = await fetch('/modulo-cortes-de-eficiencia/generar-folio', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            console.log('Respuesta recibida:', response);
            const data = await response.json();
            console.log('Datos recibidos:', data);

            if (data.success) {
                // Llenar formulario con datos generados
                document.getElementById('folio').value = data.folio;
                document.getElementById('usuario').value = data.usuario.nombre;
                document.getElementById('noEmpleado').value = data.usuario.numero_empleado;
                document.getElementById('turno').value = data.turno;
                document.getElementById('status').value = 'En Proceso';
                disableStatusField(); // Asegurar que esté deshabilitado

                currentFolio = data.folio;
                isEditing = true;

                // Cerrar loading
                Swal.close();

                // Mostrar segunda tabla con animación (sin header)
                mostrarSegundaTablaSinHeader();

                // Habilitar botones
                enableActionButtons();

                Swal.fire({
                    title: 'Folio Generado',
                    text: `Folio: ${data.folio}`,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });

            } else {
                throw new Error(data.message || 'Error al generar folio');
            }

        } catch (error) {
            console.error('Error en generarNuevoFolio:', error);
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'Error al generar el folio: ' + error.message,
                icon: 'error'
            });
        }
    }

    function mostrarMensajeInicial() {
        const mensajeInicial = document.getElementById('mensaje-inicial');
        const segundaTabla = document.getElementById('segunda-tabla');
        const headerSection = document.getElementById('header-section');

        mensajeInicial.classList.remove('hidden');
        segundaTabla.classList.add('hidden');
        headerSection.classList.add('hidden');
    }

    function ocultarMensajeInicial() {
        const mensajeInicial = document.getElementById('mensaje-inicial');
        mensajeInicial.classList.add('hidden');
    }

    function mostrarHeaderSection() {
        const headerSection = document.getElementById('header-section');
        headerSection.classList.remove('hidden');
    }

    function ocultarHeaderSection() {
        const headerSection = document.getElementById('header-section');
        headerSection.classList.add('hidden');
    }

    function mostrarSegundaTabla() {
        const segundaTabla = document.getElementById('segunda-tabla');

        // Ocultar mensaje inicial y mostrar header
        ocultarMensajeInicial();
        mostrarHeaderSection();

        // Remover clase hidden y agregar animación
        segundaTabla.classList.remove('hidden');

        // Aplicar animación de deslizamiento
        segundaTabla.style.transform = 'translateY(-20px)';
        segundaTabla.style.opacity = '0';
        segundaTabla.style.transition = 'all 0.5s ease-in-out';

        // Forzar reflow
        segundaTabla.offsetHeight;

        // Aplicar estado final
        segundaTabla.style.transform = 'translateY(0)';
        segundaTabla.style.opacity = '1';
    }

    function mostrarSegundaTablaSinHeader() {
        const segundaTabla = document.getElementById('segunda-tabla');

        // Ocultar mensaje inicial (sin mostrar header)
        ocultarMensajeInicial();

        // Remover clase hidden y agregar animación
        segundaTabla.classList.remove('hidden');

        // Aplicar animación de deslizamiento
        segundaTabla.style.transform = 'translateY(-20px)';
        segundaTabla.style.opacity = '0';
        segundaTabla.style.transition = 'all 0.5s ease-in-out';

        // Forzar reflow
        segundaTabla.offsetHeight;

        // Aplicar estado final
        segundaTabla.style.transform = 'translateY(0)';
        segundaTabla.style.opacity = '1';
    }

    function ocultarSegundaTabla() {
        const segundaTabla = document.getElementById('segunda-tabla');

        // Aplicar animación de salida
        segundaTabla.style.transform = 'translateY(-20px)';
        segundaTabla.style.opacity = '0';

        // Después de la animación, ocultar y mostrar mensaje inicial
        setTimeout(() => {
            segundaTabla.classList.add('hidden');
            segundaTabla.style.transform = '';
            segundaTabla.style.opacity = '';
            // No mostrar header section (mantener oculto)
            mostrarMensajeInicial();
        }, 500);
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
        document.getElementById('btn-editar').disabled = false;
        document.getElementById('btn-finalizar').disabled = false;

        document.getElementById('btn-editar').className = 'inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium';
        document.getElementById('btn-finalizar').className = 'inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors text-sm font-medium';
    }

    function enableStatusField() {
        const statusField = document.getElementById('status');
        statusField.disabled = false;
        statusField.className = 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500';
    }

    function disableStatusField() {
        const statusField = document.getElementById('status');
        statusField.disabled = true;
        statusField.className = 'w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed';
    }

    function disableActionButtons() {
        document.getElementById('btn-editar').disabled = true;
        document.getElementById('btn-finalizar').disabled = true;

        document.getElementById('btn-editar').className = 'inline-flex items-center px-4 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
        document.getElementById('btn-finalizar').className = 'inline-flex items-center px-4 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
    }


    // Funciones para el modal de observaciones con SweetAlert2
    function abrirModalObservaciones(checkbox) {
        currentCheckbox = checkbox;
        const telar = checkbox.getAttribute('data-telar');
        const horario = checkbox.getAttribute('data-horario');

        // Cargar observaciones existentes si las hay
        const key = `${telar}-${horario}`;
        const observacionExistente = observaciones[key] || '';

        // Mostrar SweetAlert2 con textarea
        Swal.fire({
            title: 'Observaciones',
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        <i class="fas fa-industry mr-1"></i> Telar: <strong>${telar}</strong>
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i> Horario: <strong>${horario}</strong>
                    </p>
                </div>
                <textarea id="swal-textarea" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" rows="4" placeholder="Escriba sus observaciones aquí...">${observacionExistente}</textarea>
            `,
            width: '500px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save mr-1"></i> Guardar',
            cancelButtonText: '<i class="fas fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            allowOutsideClick: false,
            allowEscapeKey: true,
            focusConfirm: false,
            preConfirm: () => {
                const textarea = document.getElementById('swal-textarea');
                const observacion = textarea ? textarea.value : '';

                // Guardar observación
                const key = `${telar}-${horario}`;
                observaciones[key] = observacion;

                // Marcar checkbox si hay observación
                currentCheckbox.checked = observacion.trim() !== '';

                return observacion;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar confirmación de guardado
                Swal.fire({
                    title: 'Observación Guardada',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                            <p class="text-gray-600">Observación guardada para:</p>
                            <p class="font-semibold">Telar ${telar} - Horario ${horario}</p>
                        </div>
                    `,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
            currentCheckbox = null;
        });

        // Focus en el textarea después de que se muestre el modal
        setTimeout(() => {
            const textarea = document.getElementById('swal-textarea');
            if (textarea) {
                textarea.focus();
                textarea.select();
            }
        }, 100);
    }
</script>

<style>
    /* Estilos adicionales para la tabla */
    .border-collapse {
        border-collapse: collapse;
    }

    /* Hover effect para las filas */
    tbody tr:hover {
        background-color: #f9fafb;
    }

    /* Estilos para los inputs en la tabla */
    tbody input {
        transition: border-color 0.2s ease;
    }

    tbody input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
    }

    /* Estilos para headers sticky */
    thead th {
        background-color: #f9fafb !important;
        position: sticky;
        top: 0;
        z-index: 10;
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

    /* Asegurar que el contenedor tenga scroll vertical */
    .table-container {
        max-height: 80vh;
        overflow-y: auto;
        overflow-x: auto;
    }

    /* Mejorar la apariencia del scroll */
    .table-container::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb:hover {
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

    /* Estilos para botones de edición */
    .edit-rpm-btn, .edit-efic-btn {
        transition: all 0.2s ease;
    }

    .edit-rpm-btn:hover, .edit-efic-btn:hover {
        transform: scale(1.1);
    }
</style>

@endsection


