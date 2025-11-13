@extends('layouts.app')

@section('page-title', 'Producción de Urdido')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            onclick="finalizar()"
            title="Finalizar"
            icon="fa-check-circle"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Finalizar"
            bg="bg-blue-500"
        />
    </div>
@endsection

@section('content')
<style>
    /* Inputs de hora en formato 24h (HH:MM) */
    .time-input-24h {
        direction: ltr;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
</style>

<div class="w-full ">
    <!-- Sección superior: Información General -->
    <div class="bg-white p-1 ">
        <div class="grid grid-cols-12 gap-2 items-stretch">
            <!-- Columna Izquierda -->
            <div class="col-span-12 md:col-span-2 flex flex-col space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Folio:</span>
                    <span class="text-sm text-gray-900 flex-1">{{ $orden ? $orden->Folio : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Cuenta:</span>
                    <span class="text-sm text-gray-900 flex-1">{{ $orden ? ($orden->Cuenta ?? '-') : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Urdido:</span>
                    <span class="text-sm text-gray-900 flex-1">{{ $orden ? ($orden->MaquinaId ?? '-') : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Metros:</span>
                    <span class="text-sm text-gray-900 flex-1">{{ $metros ?? '0' }}</span>
                </div>

            </div>

            <!-- Columna Centro -->
            <div class="col-span-12 md:col-span-2 flex flex-col space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Tipo:</span>
                    @if($orden && $orden->RizoPie)
                        @php
                            $tipo = strtoupper(trim($orden->RizoPie));
                            $isRizo = $tipo === 'RIZO';
                            $isPie = $tipo === 'PIE';
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isRizo ? 'bg-rose-100 text-rose-700' : ($isPie ? 'bg-teal-100 text-teal-700' : 'bg-gray-200 text-gray-800') }}">
                            {{ $orden->RizoPie }}
                        </span>
                    @else
                        <span class="text-sm text-gray-500 italic">-</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Destino:</span>
                    <span class="text-sm text-gray-900 flex-1">{{ $destino ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Ordenado por:</span>
                    <span class="text-sm text-gray-900 flex-1">{{ $nomEmpl ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Hilo:</span>
                    <span class="text-sm text-gray-500 italic flex-1">{{ $hilo ?? '-' }}</span>
                </div>
                <div class="flex-1"></div>
            </div>

            <!-- Columna 3: Tabla No. JULIO y HILOS -->
            <div class="col-span-12 md:col-span-4 flex flex-col ">
                <div class="flex-1 flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700  text-center">Información de Julio</label>
                    <div class="border border-gray-300 rounded overflow-hidden max-w-md mx-auto w-full">
                        <table class="w-full text-sm" style="table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 border-gray-300 text-sm px-2" style="width: 80px;">No. Julio</th>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 text-sm px-2" style="width: 70px;">Hilos</th>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 text-sm px-2" style="width: 180px;">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($julios && $julios->count() > 0)
                                    @foreach($julios as $julio)
                                        <tr>
                                            <td class="border border-r border-gray-200 text-center py-1 px-2" style="width: 80px;">
                                                <span class="text-sm text-gray-900">{{ $julio->Julios ?? '-' }}</span>
                                            </td>
                                            <td class="border text-center py-1 px-2" style="width: 70px;">
                                                <span class="text-sm text-gray-900">{{ $julio->Hilos ?? '-' }}</span>
                                            </td>
                                            <td class="border text-center py-1 px-2" style="width: 180px;">
                                                <span class="text-sm text-gray-900">{{ $julio->Obs ?? '-' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="3" class="border text-center py-1 px-2 text-gray-500 italic">
                                            No hay información de julios
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex-1"></div>
            </div>

            <!-- Columna 4: Observaciones -->
            <div class="col-span-12 md:col-span-4 flex flex-col">
                <div class="flex-1 flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700">Observaciones:</label>
                    <div class="flex-1 w-full border border-gray-300 rounded px-2 text-sm overflow-y-auto">
                        <span class="text-gray-500 whitespace-pre-wrap">{{ $observaciones ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección inferior: Tabla de Producción -->
    <div class="bg-white shadow-md overflow-hidden">
        <div class="overflow-x-auto max-h-[55vh] overflow-y-auto">
            <table class=" divide-y divide-gray-200 text-sm">
                <thead class="bg-blue-500 text-white sticky top-0 z-20">
                    <tr>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        <th colspan="4" class="py-1 text-center bg-blue-700">Roturas</th>
                    </tr>
                    <tr>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold sticky left-0 bg-blue-500 z-30 text-xs md:text-sm">Fecha</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">Oficial</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">Turno</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm hidden lg:table-cell">H. Inicio</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm hidden lg:table-cell">H. Fin</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">No. Julio</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm hidden lg:table-cell">Hilos</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">Kg. Bruto</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">Tara</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">Kg. Neto</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm">Metros</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-sm">Hilat.</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-sm">Maq.</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-sm">Operac.</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-sm">Transf.</th>
                    </tr>
                </thead>
                <tbody id="tabla-produccion-body" class="bg-white divide-y divide-gray-200">
                    @php
                        $totalRegistros = isset($totalRegistros) ? (int)$totalRegistros : 0;

                        if ($totalRegistros == 0 && isset($julios) && $julios->count() > 0) {
                            foreach ($julios as $julio) {
                                $numeroJulio = (int) ($julio->Julios ?? 0);
                                if ($numeroJulio > 0) {
                                    $totalRegistros += $numeroJulio;
                                }
                            }
                        }
                    @endphp
                    @if($totalRegistros > 0)
                        @for($rowIndex = 1; $rowIndex <= (int)$totalRegistros; $rowIndex++)
                            @php
                                // Obtener el registro correspondiente a esta fila (índice 0-based)
                                $registro = isset($registrosProduccion) && $registrosProduccion->count() > 0
                                    ? $registrosProduccion->get($rowIndex - 1)
                                    : null;

                                // Valores del registro existente o valores por defecto
                                $fecha = $registro && $registro->Fecha ? date('Y-m-d', strtotime($registro->Fecha)) : date('Y-m-d');
                                $horaInicio = $registro && $registro->HoraInicial ? substr($registro->HoraInicial, 0, 5) : '';
                                $horaFin = $registro && $registro->HoraFinal ? substr($registro->HoraFinal, 0, 5) : '';
                                $noJulio = $registro ? ($registro->NoJulio ?? '') : '';
                                $hilos = $registro ? ($registro->Hilos ?? '') : '';
                                $kgBruto = $registro ? ($registro->KgBruto ?? '') : '';
                                $tara = $registro ? ($registro->Tara ?? '') : '';
                                $kgNeto = $registro ? ($registro->KgNeto ?? '') : '';
                                $metros = $registro ? ($registro->Metros1 ?? '') : '';
                                $hilatura = $registro ? ($registro->Hilatura ?? 0) : 0;
                                $maquina = $registro ? ($registro->Maquina ?? 0) : 0;
                                $operac = $registro ? ($registro->Operac ?? 0) : 0;
                                $transf = $registro ? ($registro->Transf ?? 0) : 0;
                                $registroId = $registro ? $registro->Id : null;

                                // Obtener oficiales del registro
                                $oficiales = [];
                                for ($i = 1; $i <= 3; $i++) {
                                    $nomEmpl = $registro ? ($registro->{"NomEmpl{$i}"} ?? null) : null;
                                    if ($nomEmpl) {
                                        $oficiales[] = [
                                            'numero' => $i,
                                            'nombre' => $nomEmpl,
                                            'clave' => $registro->{"CveEmpl{$i}"} ?? null,
                                            'metros' => $registro->{"Metros{$i}"} ?? null,
                                            'turno' => $registro->{"Turno{$i}"} ?? null,
                                        ];
                                    }
                                }
                                $tieneOficiales = count($oficiales) > 0;
                            @endphp
                            <tr class="hover:bg-gray-50" data-registro-id="{{ $registroId }}">
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap sticky left-0 bg-white z-10 border-r border-gray-200">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <input type="date" data-field="fecha" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="{{ $fecha }}">
                                    </div>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1">
                                        @if($tieneOficiales)
                                            <select data-field="oficial" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 oficial-select" data-registro-id="{{ $registroId }}">
                                                <option value="">Seleccionar oficial...</option>
                                                @foreach($oficiales as $oficial)
                                                    <option value="{{ $oficial['numero'] }}" data-numero="{{ $oficial['numero'] }}" data-nombre="{{ $oficial['nombre'] }}" data-clave="{{ $oficial['clave'] }}" data-metros="{{ $oficial['metros'] }}" data-turno="{{ $oficial['turno'] }}">
                                                        {{ $oficial['nombre'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="w-full flex items-center justify-center text-gray-400">
                                                <span class="text-xs italic">Sin oficiales</span>
                                            </div>
                                        @endif
                                        <button type="button" class="btn-agregar-oficial flex-shrink-0 p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors" data-registro-id="{{ $registroId }}" title="Agregar oficial">
                                            <i class="fa-solid fa-plus-circle text-lg"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap" style="min-width: 70px;">
                                    <select data-field="turno" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500" min="1" max="3">
                                        <option value="">Seleccionar...</option>
                                        <option value="1" {{ ($registro && $registro->Turno1 == 1) ? 'selected' : '' }}>1</option>
                                        <option value="2" {{ ($registro && $registro->Turno1 == 2) ? 'selected' : '' }}>2</option>
                                        <option value="3" {{ ($registro && $registro->Turno1 == 3) ? 'selected' : '' }}>3</option>
                                    </select>
                                </td>
                                <!-- H. INICIO -->
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <input
                                            type="text"
                                            data-field="h_inicio"
                                            class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 time-input-24h"
                                            placeholder="HH:MM"
                                            value="{{ $horaInicio }}"
                                        >
                                        <i class="fa-solid fa-clock text-gray-400 text-base cursor-pointer hover:text-blue-500 set-current-time" data-time-target="h_inicio" title="Establecer hora actual"></i>
                                    </div>
                                </td>
                                <!-- H. FIN -->
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <input
                                            type="text"
                                            data-field="h_fin"
                                            class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 time-input-24h"
                                            placeholder="HH:MM"
                                            value="{{ $horaFin }}"
                                        >
                                        <i class="fa-solid fa-clock text-gray-400 text-base cursor-pointer hover:text-blue-500 set-current-time" data-time-target="h_fin" title="Establecer hora actual"></i>
                                    </div>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <select data-field="no_julio" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500 select-julio" data-valor-inicial="{{ $noJulio }}">
                                        <option value="">Seleccionar...</option>
                                        <!-- Las opciones se cargarán dinámicamente desde el catálogo -->
                                    </select>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell">
                                    <input type="number" disabled data-field="hilos" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-hilos" value="{{ $hilos }}">
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <input type="number" step="0.01" data-field="kg_bruto" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="{{ $kgBruto }}">
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <input type="number" step="0.01" disabled data-field="tara" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="{{ $tara }}">
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <input type="number" step="0.01" data-field="kg_neto" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center bg-gray-50 text-gray-600 cursor-not-allowed" value="{{ $kgNeto }}" readonly>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <input type="number" data-field="metros" class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="{{ $metros }}">
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center relative">
                                        <button type="button" class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors" onclick="toggleQuantityEdit(this, 'hilat')">
                                            <span class="quantity-display font-semibold" data-field="hilat">{{ $hilatura }}</span>
                                        </button>
                                        <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                            <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                <div class="flex space-x-1 min-w-max">
                                                    @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                        <span class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $hilatura ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $numIndex }}">{{ $numIndex }}</span>
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center relative">
                                        <button type="button" class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors" onclick="toggleQuantityEdit(this, 'maq')">
                                            <span class="quantity-display font-semibold" data-field="maq">{{ $maquina }}</span>
                                        </button>
                                        <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                            <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                <div class="flex space-x-1 min-w-max">
                                                    @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                        <span class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $maquina ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $numIndex }}">{{ $numIndex }}</span>
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center relative">
                                        <button type="button" class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors" onclick="toggleQuantityEdit(this, 'operac')">
                                            <span class="quantity-display font-semibold" data-field="operac">{{ $operac }}</span>
                                        </button>
                                        <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                            <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                <div class="flex space-x-1 min-w-max">
                                                    @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                        <span class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $operac ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $numIndex }}">{{ $numIndex }}</span>
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center relative">
                                        <button type="button" class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors" onclick="toggleQuantityEdit(this, 'transf')">
                                            <span class="quantity-display font-semibold" data-field="transf">{{ $transf }}</span>
                                        </button>
                                        <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                            <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                <div class="flex space-x-1 min-w-max">
                                                    @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                        <span class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $transf ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $numIndex }}">{{ $numIndex }}</span>
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endfor
                    @else
                        <tr>
                            <td colspan="15" class="px-2 py-4 text-center text-gray-500 italic">
                                No hay registros para generar.
                                @if(isset($julios) && $julios->count() > 0)
                                    <br>Total calculado: {{ $totalRegistros }} | Cantidad de julios: {{ $julios->count() }}
                                    <br><small>Valores de julios encontrados:
                                    @foreach($julios as $j)
                                        {{ $j->Julios ?? 'N/A' }},
                                    @endforeach
                                    </small>
                                @else
                                    No hay julios registrados para esta orden.
                                @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para agregar oficial -->
<div id="modal-oficial" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Agregar Oficial</h3>
            <button type="button" id="btn-cerrar-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-times text-xl"></i>
            </button>
        </div>
        <form id="form-oficial" class="p-6">
            <input type="hidden" id="modal-registro-id" name="registro_id">
            <input type="hidden" id="modal-numero-oficial" name="numero_oficial">

            <div class="mb-4">
                <label for="modal-cve-empl" class="block text-sm font-medium text-gray-700 mb-1">Clave de Empleado</label>
                <input type="text" id="modal-cve-empl" name="cve_empl" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="{{ $usuarioClave }}" required>
            </div>

            <div class="mb-4">
                <label for="modal-nom-empl" class="block text-sm font-medium text-gray-700 mb-1">Nombre de Empleado</label>
                <input type="text" id="modal-nom-empl" name="nom_empl" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="{{ $usuarioNombre }}" required>
            </div>

            <div class="mb-4">
                <label for="modal-metros" class="block text-sm font-medium text-gray-700 mb-1">Metros</label>
                <input type="number" step="0.01" id="modal-metros" name="metros" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" min="0">
            </div>

            <div class="mb-6">
                <label for="modal-turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                <select id="modal-turno" name="turno" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Seleccionar...</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
            </div>

            <div class="flex gap-3 justify-end">
                <button type="button" id="btn-cancelar-modal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Función para calcular Kg. NETO automáticamente
    function calcularNeto(row) {
        const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
        const taraInput = row.querySelector('input[data-field="tara"]');
        const netoInput = row.querySelector('input[data-field="kg_neto"]');

        if (brutoInput && taraInput && netoInput) {
            const bruto = parseFloat(brutoInput.value) || 0;
            const tara = parseFloat(taraInput.value) || 0;
            const neto = bruto - tara;
            netoInput.value = neto.toFixed(2);
        }
    }


    // Función para toggle del editor de cantidad - debe estar disponible globalmente
    window.toggleQuantityEdit = function(element, fieldName) {
        const cell = element.closest('td');
        const editContainer = cell.querySelector('.quantity-edit-container');
        const editBtn = cell.querySelector('.edit-quantity-btn');
        const quantityDisplay = cell.querySelector('.quantity-display');

        closeAllQuantityEditors();

        if (editContainer && editBtn) {
            const isHidden = editContainer.classList.contains('hidden');
            editContainer.classList.toggle('hidden');
            editBtn.classList.toggle('hidden');

            if (isHidden) {
                const currentValue = quantityDisplay ? quantityDisplay.textContent.trim() : '0';
                const allOptions = editContainer.querySelectorAll('.number-option');
                allOptions.forEach(o => {
                    const value = o.getAttribute('data-value');
                    if (String(value) === String(currentValue)) {
                        o.classList.remove('bg-gray-100', 'text-gray-700');
                        o.classList.add('bg-blue-500', 'text-white');
                    } else {
                        o.classList.remove('bg-blue-500', 'text-white');
                        o.classList.add('bg-gray-100', 'text-gray-700');
                    }
                });
            }
        }
    };

    function closeAllQuantityEditors() {
        document.querySelectorAll('.quantity-edit-container').forEach(container => {
            if (!container.classList.contains('hidden')) {
                const row = container.closest('tr');
                const editBtn = row ? row.querySelector('.edit-quantity-btn') : null;
                container.classList.add('hidden');
                if (editBtn) editBtn.classList.remove('hidden');
            }
        });
    }

    // Forzar formato 24h HH:MM en inputs de hora
    function forzarFormato24h() {
        const timeInputs = document.querySelectorAll('.time-input-24h');

        timeInputs.forEach(input => {
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('maxlength', '5');
            input.setAttribute('placeholder', 'HH:MM');

            const normalizar24h = (value) => {
                if (!value) return '';

                // Aceptar formatos como "8:5", "08:5", "8:05", "08:05"
                const match = value.match(/^(\d{1,2}):(\d{1,2})$/);
                if (!match) return value.replace(/[^0-9:]/g, '');

                let horas = parseInt(match[1], 10);
                let minutos = parseInt(match[2], 10);

                if (isNaN(horas) || isNaN(minutos)) return '';
                if (horas < 0 || horas > 23) return '';
                if (minutos < 0 || minutos > 59) return '';

                return String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0');
            };

            // Solo permitir números y dos puntos
            input.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9:]/g, '');
            });

            const handler = function(e) {
                const val = e.target.value.trim();
                if (!val) return;
                e.target.value = normalizar24h(val);
            };

            input.addEventListener('change', handler);
            input.addEventListener('blur', handler);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const tablaBody = document.getElementById('tabla-produccion-body');

        if (tablaBody) {
            // Calcular neto cuando cambian bruto o tara
            tablaBody.addEventListener('input', function(e) {
                const row = e.target.closest('tr');
                if (row && (e.target.dataset.field === 'kg_bruto' || e.target.dataset.field === 'tara')) {
                    calcularNeto(row);
                }
            });

            // Calcular neto para todas las filas existentes
            const rows = tablaBody.querySelectorAll('tr');
            rows.forEach(row => {
                calcularNeto(row);
            });
        }

        // Forzar formato de 24 horas en los inputs de hora
        forzarFormato24h();

        // Cargar catálogo de julios y llenar los selects
        cargarCatalogosJulios();

        // Actualizar campo Tara cuando cambia el select de No. Julio
        // Los hilos ya vienen rellenados desde la base de datos al crear los registros
        if (tablaBody) {
            tablaBody.addEventListener('change', function(e) {
                if (e.target.classList.contains('select-julio')) {
                    const row = e.target.closest('tr');
                    const taraInput = row ? row.querySelector('input[data-field="tara"]') : null;
                    const selectedOption = e.target.options[e.target.selectedIndex];

                    if (taraInput && selectedOption) {
                        const tara = selectedOption.getAttribute('data-tara') || '0';
                        taraInput.value = tara;

                        // Recalcular Kg. Neto automáticamente
                        const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
                        const netoInput = row.querySelector('input[data-field="kg_neto"]');
                        if (brutoInput && netoInput) {
                            const bruto = parseFloat(brutoInput.value) || 0;
                            const taraVal = parseFloat(tara) || 0;
                            const neto = bruto - taraVal;
                            netoInput.value = neto.toFixed(2);
                        }
                    }
                }
            });
        }

        // Función para cargar catálogo de julios
        async function cargarCatalogosJulios() {
            try {
                const response = await fetch('{{ route('urdido.modulo.produccion.urdido.catalogos.julios') }}');
                const result = await response.json();

                if (result.success && result.data) {
                    const catalogosJulios = result.data;
                    const selectJulios = document.querySelectorAll('.select-julio');
                    const folio = '{{ $orden ? $orden->Folio : '' }}';

                    selectJulios.forEach(async (select) => {
                        // Obtener valor inicial si existe
                        const valorInicial = select.getAttribute('data-valor-inicial');

                        // Limpiar opciones existentes excepto la primera
                        while (select.options.length > 1) {
                            select.remove(1);
                        }

                        // Agregar opciones del catálogo
                        catalogosJulios.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.julio;
                            option.setAttribute('data-tara', item.tara || '0');
                            option.textContent = item.julio;

                            // Seleccionar si coincide con el valor inicial
                            if (valorInicial && String(item.julio) === String(valorInicial)) {
                                option.selected = true;
                            }

                            select.appendChild(option);
                        });

                        // Si hay valor inicial y se seleccionó, actualizar el campo tara
                        // Los hilos ya vienen rellenados desde la base de datos
                        if (valorInicial && select.value) {
                            const row = select.closest('tr');
                            const taraInput = row ? row.querySelector('input[data-field="tara"]') : null;
                            const selectedOption = select.options[select.selectedIndex];

                            if (taraInput && selectedOption) {
                                const tara = selectedOption.getAttribute('data-tara') || '0';
                                taraInput.value = tara;

                                // Recalcular Kg. Neto
                                const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
                                const netoInput = row.querySelector('input[data-field="kg_neto"]');
                                if (brutoInput && netoInput) {
                                    const bruto = parseFloat(brutoInput.value) || 0;
                                    const taraVal = parseFloat(tara) || 0;
                                    const neto = bruto - taraVal;
                                    netoInput.value = neto.toFixed(2);
                                }
                            }
                        }
                    });
                } else {
                    console.error('Error al cargar catálogo de julios:', result.error || 'Error desconocido');
                }
            } catch (error) {
                console.error('Error al cargar catálogo de julios:', error);
            }
        }

        // Event listener para cerrar editores de cantidad al hacer clic fuera
        document.addEventListener('click', function(event) {
            const isInsideEditor = event.target.closest('.quantity-edit-container');
            const isEditButton = event.target.closest('.edit-quantity-btn');
            if (!isInsideEditor && !isEditButton) {
                closeAllQuantityEditors();
            }
        });

        // Event listener para seleccionar números en el editor de cantidad
        document.addEventListener('click', function(e) {
            const opt = e.target.closest('.number-option');
            if (!opt) return;

            e.preventDefault();
            e.stopPropagation();

            const container = opt.closest('.number-scroll-container');
            const allOptions = container.querySelectorAll('.number-option');
            const cell = opt.closest('td');
            const selectedValue = opt.getAttribute('data-value');

            allOptions.forEach(o => {
                o.classList.remove('bg-blue-500', 'text-white');
                o.classList.add('bg-gray-100', 'text-gray-700');
            });
            opt.classList.remove('bg-gray-100', 'text-gray-700');
            opt.classList.add('bg-blue-500', 'text-white');

            const quantityDisplay = cell.querySelector('.quantity-display');
            if (quantityDisplay) {
                quantityDisplay.textContent = selectedValue;
            }

            const editContainer = cell.querySelector('.quantity-edit-container');
            const editBtn = cell.querySelector('.edit-quantity-btn');
            if (editContainer) editContainer.classList.add('hidden');
            if (editBtn) editBtn.classList.remove('hidden');
        });

        // Establecer hora actual al hacer clic en el icono
        document.addEventListener('click', function(e) {
            const iconElement = e.target.closest('.set-current-time');
            if (iconElement) {
                e.preventDefault();
                const timeTarget = iconElement.getAttribute('data-time-target');
                const row = iconElement.closest('tr');
                const timeInput = row ? row.querySelector(`input[data-field="${timeTarget}"]`) : null;

                if (timeInput) {
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const currentTime = `${hours}:${minutes}`;

                    timeInput.value = currentTime;
                    timeInput.dispatchEvent(new Event('change', { bubbles: true }));

                    iconElement.classList.add('text-blue-500');
                    setTimeout(() => {
                        iconElement.classList.remove('text-blue-500');
                    }, 300);
                }
            }
        });

        // ========== LÓGICA DE OFICIALES ==========
        const modalOficial = document.getElementById('modal-oficial');
        const formOficial = document.getElementById('form-oficial');
        const btnCerrarModal = document.getElementById('btn-cerrar-modal');
        const btnCancelarModal = document.getElementById('btn-cancelar-modal');
        const modalRegistroId = document.getElementById('modal-registro-id');
        const modalNumeroOficial = document.getElementById('modal-numero-oficial');

        // Función para abrir el modal
        function abrirModalOficial(registroId) {
            // Determinar qué número de oficial usar (1, 2 o 3)
            const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
            if (!row) return;

            const selectOficial = row.querySelector('.oficial-select');
            let numeroOficial = 1;

            if (selectOficial) {
                // Si ya hay oficiales, contar cuántos hay
                const opciones = selectOficial.querySelectorAll('option[value]');
                const numerosUsados = Array.from(opciones).map(opt => parseInt(opt.value)).filter(n => !isNaN(n));

                // Encontrar el primer número disponible (1, 2 o 3)
                for (let i = 1; i <= 3; i++) {
                    if (!numerosUsados.includes(i)) {
                        numeroOficial = i;
                        break;
                    }
                }

                // Si todos están ocupados, usar el siguiente disponible (pero máximo 3)
                if (numerosUsados.length >= 3) {
                    numeroOficial = 3; // Solo permitir hasta 3 oficiales
                }
            }

            modalRegistroId.value = registroId;
            modalNumeroOficial.value = numeroOficial;
            modalOficial.classList.remove('hidden');
            modalOficial.style.display = 'flex';
        }

        // Función para cerrar el modal
        function cerrarModalOficial() {
            modalOficial.classList.add('hidden');
            modalOficial.style.display = 'none';
            formOficial.reset();
        }

        // Event listeners para abrir el modal
        document.addEventListener('click', function(e) {
            const btnAgregar = e.target.closest('.btn-agregar-oficial');
            if (btnAgregar) {
                e.preventDefault();
                const registroId = btnAgregar.getAttribute('data-registro-id');
                if (registroId) {
                    abrirModalOficial(registroId);
                }
            }
        });

        // Event listeners para cerrar el modal
        if (btnCerrarModal) {
            btnCerrarModal.addEventListener('click', cerrarModalOficial);
        }
        if (btnCancelarModal) {
            btnCancelarModal.addEventListener('click', cerrarModalOficial);
        }

        // Cerrar modal al hacer clic fuera
        modalOficial.addEventListener('click', function(e) {
            if (e.target === modalOficial) {
                cerrarModalOficial();
            }
        });

        // Enviar formulario de oficial
        if (formOficial) {
            formOficial.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = new FormData(formOficial);
                const data = {
                    registro_id: formData.get('registro_id'),
                    numero_oficial: formData.get('numero_oficial'),
                    cve_empl: formData.get('cve_empl'),
                    nom_empl: formData.get('nom_empl'),
                    metros: formData.get('metros') || null,
                    turno: formData.get('turno') || null,
                };

                try {
                    const response = await fetch('{{ route('urdido.modulo.produccion.urdido.guardar.oficial') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify(data),
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Recargar la página para mostrar los cambios
                        window.location.reload();
                    } else {
                        alert('Error al guardar oficial: ' + (result.error || 'Error desconocido'));
                    }
                } catch (error) {
                    console.error('Error al guardar oficial:', error);
                    alert('Error al guardar oficial. Por favor, intenta nuevamente.');
                }
            });
        }
    });

    window.finalizar = function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Finalizar registro?',
                text: 'Esta acción marcará el registro como finalizado',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registro finalizado',
                        text: 'El registro ha sido marcado como finalizado',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        } else {
            if (confirm('¿Finalizar registro?')) {
                alert('Registro finalizado');
            }
        }
    };
})();
</script>
@endsection
