@extends('layouts.app')

@section('navbar-right')
    <!-- Botones de acción para Marcas Finales -->
    <div class="flex items-center space-x-2">
        <button id="btn-nuevo" onclick="nuevaMarca()" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm font-medium">
            <i class="fas fa-plus mr-1"></i>Nuevo
        </button>
        <button id="btn-editar" onclick="editarMarca()" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium" disabled>
            <i class="fas fa-edit mr-1"></i>Editar
        </button>
        <button id="btn-finalizar" onclick="finalizarMarca()" class="inline-flex items-center px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors text-sm font-medium" disabled>
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
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Marcas Finales</h3>
            <p class="text-gray-600 mb-4">Haz clic en "Nuevo" para comenzar un nuevo registro de marcas finales</p>
            <div class="text-sm text-gray-500">
                <p>• Selecciona el turno correspondiente</p>
                <p>• Completa los datos de Trama, Pie, Rizo y Otros para cada telar</p>
                <p>• Guarda el registro cuando hayas terminado</p>
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

                        <!-- Horario 1 -->
                        <th colspan="4" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-blue-100">Horario 1</th>

                        <!-- Horario 2 -->
                        <th colspan="4" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-green-100">Horario 2</th>

                        <!-- Horario 3 -->
                        <th colspan="4" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-yellow-100">Horario 3</th>
                    </tr>
                    <tr>
                        <th class="border border-gray-300 px-1 py-2 text-xs font-medium text-gray-600 w-16"></th>

                        <!-- Horario 1 subheaders -->
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-blue-50">Trama</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-blue-50">Pie</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-blue-50">Rizo</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-blue-50">Otros</th>

                        <!-- Horario 2 subheaders -->
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-green-50">Trama</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-green-50">Pie</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-green-50">Rizo</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-green-50">Otros</th>

                        <!-- Horario 3 subheaders -->
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-yellow-50">Trama</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-yellow-50">Pie</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-yellow-50">Rizo</th>
                        <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-yellow-50">Otros</th>
                        </tr>
                    </thead>
                <tbody id="telares-body">
                    <!-- Telares 207-320 -->
                    @for($i = 207; $i <= 320; $i++)
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 px-1 py-2 text-center text-sm font-semibold w-16">{{ $i }}</td>

                        <!-- Horario 1 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="trama-display text-sm text-gray-900 font-medium" id="h1_trama_display_{{ $i }}">0</span>
                                <button class="edit-trama-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleTramaEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="trama-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-blue-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="1" data-type="trama">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="pie-display text-sm text-gray-900 font-medium" id="h1_pie_display_{{ $i }}">0</span>
                                <button class="edit-pie-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="togglePieEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="pie-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-blue-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="1" data-type="pie">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="rizo-display text-sm text-gray-900 font-medium" id="h1_rizo_display_{{ $i }}">0</span>
                                <button class="edit-rizo-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleRizoEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="rizo-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-blue-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="1" data-type="rizo">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="otros-display text-sm text-gray-900 font-medium" id="h1_otros_display_{{ $i }}">0</span>
                                <button class="edit-otros-btn ml-1 p-1 text-gray-500 hover:text-blue-600 transition-colors" onclick="toggleOtrosEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="otros-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-blue-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="1" data-type="otros">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </td>

                        <!-- Horario 2 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="trama-display text-sm text-gray-900 font-medium" id="h2_trama_display_{{ $i }}">0</span>
                                <button class="edit-trama-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleTramaEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    </button>
                                <div class="trama-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="2" data-type="trama">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="pie-display text-sm text-gray-900 font-medium" id="h2_pie_display_{{ $i }}">0</span>
                                <button class="edit-pie-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="togglePieEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    </button>
                                <div class="pie-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="2" data-type="pie">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="rizo-display text-sm text-gray-900 font-medium" id="h2_rizo_display_{{ $i }}">0</span>
                                <button class="edit-rizo-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleRizoEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="rizo-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="2" data-type="rizo">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
            </div>
        </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="otros-display text-sm text-gray-900 font-medium" id="h2_otros_display_{{ $i }}">0</span>
                                <button class="edit-otros-btn ml-1 p-1 text-gray-500 hover:text-green-600 transition-colors" onclick="toggleOtrosEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
                                </button>
                                <div class="otros-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-green-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="2" data-type="otros">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
        </div>
                        </td>

                        <!-- Horario 3 -->
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="trama-display text-sm text-gray-900 font-medium" id="h3_trama_display_{{ $i }}">0</span>
                                <button class="edit-trama-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleTramaEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                    </button>
                                <div class="trama-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="3" data-type="trama">{{ $j }}</span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="pie-display text-sm text-gray-900 font-medium" id="h3_pie_display_{{ $i }}">0</span>
                                <button class="edit-pie-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="togglePieEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                    </button>
                                <div class="pie-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="3" data-type="pie">{{ $j }}</span>
                                            @endfor
                                        </div>
                </div>
            </div>
        </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="rizo-display text-sm text-gray-900 font-medium" id="h3_rizo_display_{{ $i }}">0</span>
                                <button class="edit-rizo-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleRizoEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="rizo-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="3" data-type="rizo">{{ $j }}</span>
                                            @endfor
                                        </div>
                </div>
                </div>
                </div>
                        </td>
                        <td class="border border-gray-300 px-1 py-2">
                            <div class="flex items-center justify-between relative">
                                <span class="otros-display text-sm text-gray-900 font-medium" id="h3_otros_display_{{ $i }}">0</span>
                                <button class="edit-otros-btn ml-1 p-1 text-gray-500 hover:text-yellow-600 transition-colors" onclick="toggleOtrosEdit(this)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <div class="otros-edit-container hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-32" style="scrollbar-width: none; -ms-overflow-style: none;">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($j = 0; $j <= 100; $j++)
                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer hover:bg-yellow-100 rounded transition-colors bg-gray-100 text-gray-700" data-value="{{ $j }}" data-telar="{{ $i }}" data-horario="3" data-type="otros">{{ $j }}</span>
                                            @endfor
                </div>
                </div>
            </div>
        </div>
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

    // Funciones para editores de Trama, Pie, Rizo y Otros
    function inicializarValor(telar, horario, tipo, valor) {
        const display = document.getElementById(`h${horario}_${tipo}_display_${telar}`);
        if (display) {
            display.textContent = valor;
        }
    }

    function toggleTramaEdit(button) {
        toggleEdit(button, 'trama');
    }

    function togglePieEdit(button) {
        toggleEdit(button, 'pie');
    }

    function toggleRizoEdit(button) {
        toggleEdit(button, 'rizo');
    }

    function toggleOtrosEdit(button) {
        toggleEdit(button, 'otros');
    }

    function toggleEdit(button, tipo) {
        const container = button.nextElementSibling;
        const display = button.previousElementSibling;

        if (container.classList.contains('hidden')) {
            // Cerrar todos los editores abiertos primero
            closeAllEditors();

            // Mostrar editor actual
            container.classList.remove('hidden');
            button.classList.add('hidden');
            display.classList.add('hidden');

            // Inicializar el modal con el valor actual y centrar el scroll
            setTimeout(() => {
                const currentValue = parseInt(display.textContent) || 0;
                const exactOption = container.querySelector(`span[data-value="${currentValue}"]`);

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
            }, 50);
        } else {
            // Ocultar editor
            container.classList.add('hidden');
            button.classList.remove('hidden');
            display.classList.remove('hidden');
        }
    }

    function closeAllEditors() {
        document.querySelectorAll('.trama-edit-container, .pie-edit-container, .rizo-edit-container, .otros-edit-container').forEach(container => {
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
            const displayH2 = document.getElementById(`h2_${tipo}_display_${telar}`);
            if (displayH2 && displayH2.textContent === '0') {
                inicializarValor(telar, 2, tipo, valor);
            }

            // Verificar si Horario 3 está en 0
            const displayH3 = document.getElementById(`h3_${tipo}_display_${telar}`);
            if (displayH3 && displayH3.textContent === '0') {
                inicializarValor(telar, 3, tipo, valor);
            }
        }
        // Si es Horario 2, propagar a Horario 3 (solo si está en 0)
        else if (horario === 2) {
            // Verificar si Horario 3 está en 0
            const displayH3 = document.getElementById(`h3_${tipo}_display_${telar}`);
            if (displayH3 && displayH3.textContent === '0') {
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

        // Deshabilitar botones inicialmente
        disableActionButtons();

        // Mostrar mensaje inicial y ocultar tabla
        mostrarMensajeInicial();

        // Event listeners para los números
        document.querySelectorAll('.number-option').forEach(option => {
            option.addEventListener('click', function() {
                const telar = this.getAttribute('data-telar');
                const horario = parseInt(this.getAttribute('data-horario'));
                const tipo = this.getAttribute('data-type');
                const valor = parseInt(this.getAttribute('data-value'));

                const container = this.closest('.number-scroll-container');
                const allOptions = container.querySelectorAll('.number-option');
                const editContainer = this.closest('.trama-edit-container, .pie-edit-container, .rizo-edit-container, .otros-edit-container');
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
                display.textContent = valor;

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
            const isInsideEditor = event.target.closest('.trama-edit-container, .pie-edit-container, .rizo-edit-container, .otros-edit-container');
            const isEditButton = event.target.closest('.edit-trama-btn, .edit-pie-btn, .edit-rizo-btn, .edit-otros-btn');

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
    });

    async function cargarTurnoActual() {
        try {
            const response = await fetch('/modulo-marcas-finales/turno-info', {
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

    // Funciones de botones de acción
    async function nuevaMarca() {
        console.log('Función nuevaMarca llamada');
        console.log('isEditing:', isEditing);

        if (isEditing) {
            Swal.fire({
                title: '¿Guardar cambios?',
                text: 'Hay cambios sin guardar. ¿Desea guardarlos antes de crear una nueva marca?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'No, descartar'
            }).then((result) => {
                if (result.isConfirmed) {
                    guardarMarca();
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

            console.log('Haciendo petición a /modulo-marcas-finales/generar-folio');
            // Hacer petición para generar folio
            const response = await fetch('/modulo-marcas-finales/generar-folio', {
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

    function editarMarca() {
        if (!currentFolio) {
            Swal.fire({
                title: 'Error',
                text: 'No hay una marca seleccionada para editar',
                icon: 'warning'
            });
            return;
        }

        isEditing = true;
        enableActionButtons();

        Swal.fire({
            title: 'Modo Edición',
            text: 'Ahora puedes editar los datos de la marca',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        });
    }

    function finalizarMarca() {
        if (!currentFolio) {
            Swal.fire({
                title: 'Error',
                text: 'No hay una marca para finalizar',
                icon: 'warning'
            });
            return;
        }

        Swal.fire({
            title: '¿Finalizar Marca?',
            text: '¿Está seguro de que desea finalizar esta marca final?',
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
                    title: 'Marca Finalizada',
                    text: 'La marca final ha sido finalizada exitosamente',
                    icon: 'success'
                });

                disableActionButtons();
            }
        });
    }

    function guardarMarca() {
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
                text: 'Los datos de la marca han sido guardados',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });

            enableActionButtons();
        }, 1500);
    }

    function recopilarDatosTelares() {
        const datos = [];

        // Recopilar datos de todos los telares (207-320)
        for (let telar = 207; telar <= 320; telar++) {
            const telarData = { telar: telar };

            // Recopilar datos de los 3 horarios y 4 tipos
            for (let horario = 1; horario <= 3; horario++) {
                for (let tipo of ['trama', 'pie', 'rizo', 'otros']) {
                    const display = document.getElementById(`h${horario}_${tipo}_display_${telar}`);
                    if (display) {
                        telarData[`h${horario}_${tipo}`] = parseInt(display.textContent) || 0;
                    }
                }
            }

            datos.push(telarData);
        }

        return datos;
    }

    function limpiarFormulario() {
        document.getElementById('folio').value = '';
        document.getElementById('fecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('turno').value = '';
        document.getElementById('status').value = 'Pendiente';
        disableStatusField(); // Asegurar que esté deshabilitado

        // Limpiar tabla - resetear todos los valores a 0
        for (let telar = 207; telar <= 320; telar++) {
            for (let horario = 1; horario <= 3; horario++) {
                for (let tipo of ['trama', 'pie', 'rizo', 'otros']) {
                    const display = document.getElementById(`h${horario}_${tipo}_display_${telar}`);
                    if (display) {
                        display.textContent = '0';
                    }
                }
            }
        }

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
    .edit-trama-btn, .edit-pie-btn, .edit-rizo-btn, .edit-otros-btn {
        transition: all 0.2s ease;
    }

    .edit-trama-btn:hover, .edit-pie-btn:hover, .edit-rizo-btn:hover, .edit-otros-btn:hover {
        transform: scale(1.1);
    }
</style>

@endsection
