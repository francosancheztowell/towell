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

@php
    // Checkbox Fin visible para todos los usuarios (sin validación de permiso registrar)
    $hasFinalizarPermission = true;
@endphp

@section('content')

    <div class="w-full">
    <!-- Sección superior: Información General -->
        <div class="bg-white p-1">
        <div class="grid grid-cols-12 gap-2 items-stretch">
            <!-- Columna Izquierda -->
            <div class="col-span-12 md:col-span-2 flex flex-col space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Folio:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $orden ? $orden->Folio : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Cuenta:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $orden ? ($orden->Cuenta ?? '-') : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Urdido:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $orden ? ($orden->MaquinaId ?? '-') : '-' }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Metros:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $metros ?? '0' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Proveedor:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $loteProveedor ?? '-' }}</span>
                </div>
            </div>

            <!-- Columna Centro -->
                <div class="col-span-12 md:col-span-2 flex flex-col space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">{{ ($isKarlMayer ?? false) ? 'Barras' : 'Tipo' }}:</span>
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
                            <span class="text-md text-gray-500 italic">-</span>
                        @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Destino:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $destino ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Ordenado por:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $nomEmpl ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Hilo:</span>
                        <span class="text-md text-gray-500 italic flex-1">{{ $hilo ?? '-' }}</span>
                </div>
                <div class="flex-1"></div>
            </div>

            <!-- Columna 3: Tabla No. JULIO y HILOS -->
                <div class="col-span-12 md:col-span-4 flex flex-col">
                <div class="flex-1 flex flex-col">
                        <label class="block text-md font-semibold text-gray-700 text-center">Información de Julio</label>
                    <div class="border border-gray-300 rounded overflow-hidden max-w-md mx-auto w-full">
                        <table class="w-full text-md" style="table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 border-gray-300 text-md px-2" style="width: 80px;">No. Julio</th>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 text-md px-2" style="width: 70px;">Hilos</th>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 text-md px-2" style="width: 180px;">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @if($julios && $julios->count() > 0)
                                        @foreach($julios as $julio)
                                <tr>
                                    <td class="border border-r border-gray-200 text-center py-1 px-2" style="width: 80px;">
                                                    <span class="text-md text-gray-900">{{ $julio->Julios ?? '-' }}</span>
                                    </td>
                                    <td class="border text-center py-1 px-2" style="width: 70px;">
                                                    <span class="text-md text-gray-900">{{ $julio->Hilos ?? '-' }}</span>
                                    </td>
                                    <td class="border text-center py-1 px-2" style="width: 180px;">
                                                    <span class="text-md text-gray-900">{{ $julio->Obs ?? '-' }}</span>
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
                    <label class="block text-md font-semibold text-gray-700">Observaciones:</label>
                    <div class="flex-1 w-full border border-gray-300 rounded px-2 text-md overflow-y-auto">
                            <span class="text-gray-500 whitespace-pre-wrap">{{ $observaciones ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección inferior: Tabla de Producción -->
    <div class="bg-white shadow-md overflow-hidden">
            <div class="overflow-x-auto max-h-[55vh] overflow-y-auto">
                <table class="text-md w-full min-w-[1120px]">
                <thead class="bg-blue-500 text-white sticky top-0 z-20">
                    <tr>
                        @if($hasFinalizarPermission)
                        <th class="py-1"></th>
                        @endif
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
                        @if($isKarlMayer ?? false)
                        <th class="py-1"></th>
                        <th class="py-1"></th>
                        @endif
                        <th colspan="4" class="py-1 text-center bg-blue-700">Roturas</th>
                    </tr>
                    <!-- Cabecera de la tabla de producción -->
                    <tr>
                        @if($hasFinalizarPermission)
                        <th class="py-2 px-0 text-center font-semibold text-[9px] md:text-[10px]" style="width: 28px; min-width: 28px; max-width: 28px;">Fin</th>
                        @endif
                        <th class="py-2 px-1 md:px-1 text-center font-semibold sticky left-0 z-30 text-xs md:text-md" style="width: 3rem; min-width: 3rem; max-width: 3rem;">Fecha</th>
                        <th class="py-2 px-1 md:px-1 text-left font-semibold text-xs md:text-sm" style="width: 9rem; min-width: 9rem; max-width: 9rem;">No. Empleado</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-md hidden lg:table-cell w-28 max-w-[104px]">H. Inicio</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-md hidden lg:table-cell w-28 max-w-[104px]">H. Fin</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-md w-24 max-w-[75px]">No. Julio</th>
                        <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-md hidden lg:table-cell w-20 max-w-[60px]">Hilos</th>
                        <th class="py-2 px-1 md:px-0.5 text-center font-semibold text-[10px] md:text-xs w-12 md:w-10 lg:w-12">Kg. Bruto</th>
                        <th class="py-2 px-1 md:px-0.5 text-center font-semibold text-[10px] md:text-xs w-10 md:w-9 lg:w-10">Tara</th>
                        <th class="py-2 px-1 md:px-0.5 text-center font-semibold text-[10px] md:text-xs w-12 md:w-10 lg:w-12">Kg. Neto</th>
                        <th class="py-2 px-1 md:px-1.5 text-center font-semibold text-xs md:text-md w-14 md:w-12 lg:w-20">Metros</th>
                        <th class="py-2 px-1 md:px-1 text-center font-semibold bg-blue-700 text-[10px] md:text-xs w-12 md:w-10 lg:w-12 h-10 md:h-12 relative align-bottom">
                            <span class="absolute bottom-0 left-1/2 whitespace-nowrap" style="transform: translateX(-50%) rotate(-45deg); transform-origin: left bottom;">Hilat.</span>
                        </th>
                        <th class="py-2 px-1 md:px-1 text-center font-semibold bg-blue-700 text-[10px] md:text-xs w-12 md:w-10 lg:w-12 h-10 md:h-12 relative align-bottom">
                            <span class="absolute bottom-0 left-1/2 whitespace-nowrap" style="transform: translateX(-50%) rotate(-45deg); transform-origin: left bottom;">Maq.</span>
                        </th>
                        <th class="py-2 px-1 md:px-1 text-center font-semibold bg-blue-700 text-[10px] md:text-xs w-12 md:w-10 lg:w-12 h-10 md:h-12 relative align-bottom">
                            <span class="absolute bottom-0 left-1/2 whitespace-nowrap" style="transform: translateX(-50%) rotate(-45deg); transform-origin: left bottom;">Operac.</span>
                        </th>
                        <th class="py-2 px-1 md:px-1 text-center font-semibold bg-blue-700 text-[10px] md:text-xs w-12 md:w-10 lg:w-12 h-10 md:h-12 relative align-bottom">
                            <span class="absolute bottom-0 left-1/2 whitespace-nowrap" style="transform: translateX(-50%) rotate(-45deg); transform-origin: left bottom;">Transf.</span>
                        </th>
                        @if($isKarlMayer ?? false)
                        <th class="py-2 px-1 md:px-1 text-center font-semibold bg-emerald-700 text-[10px] md:text-xs w-16 md:w-14 lg:w-16 h-10 md:h-12 relative align-bottom">
                            <span class="absolute bottom-0 left-1/2 whitespace-nowrap" style="transform: translateX(-50%) rotate(-45deg); transform-origin: left bottom;">Vueltas</span>
                        </th>
                        <th class="py-2 px-1 md:px-1 text-center font-semibold bg-emerald-700 text-[10px] md:text-xs w-16 md:w-14 lg:w-16 h-10 md:h-12 relative align-bottom">
                            <span class="absolute bottom-0 left-1/2 whitespace-nowrap" style="transform: translateX(-50%) rotate(-45deg); transform-origin: left bottom;">Diámetro</span>
                        </th>
                        @endif
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
                                    $registro = isset($registrosProduccion) && $registrosProduccion->count() > 0
                                        ? $registrosProduccion->get($rowIndex - 1)
                                        : null;

                                    $fecha = $registro && $registro->Fecha ? date('Y-m-d', strtotime($registro->Fecha)) : date('Y-m-d');
                                    $horaInicio = $registro && $registro->HoraInicial ? substr($registro->HoraInicial, 0, 5) : '';
                                    $horaFin = $registro && $registro->HoraFinal ? substr($registro->HoraFinal, 0, 5) : '';
                                    $noJulio = $registro ? ($registro->NoJulio ?? '') : '';
                                    $hilos = $registro ? ($registro->Hilos ?? '') : '';
                                    $kgBruto = $registro ? ($registro->KgBruto ?? '') : '';
                                    $tara = $registro ? ($registro->Tara ?? '') : '';
                                    $kgNeto = $registro ? ($registro->KgNeto ?? '') : '';

                                    $metros = '';
                                    if ($registro) {
                                        $metros1 = isset($registro->Metros1) && $registro->Metros1 !== null ? (float)$registro->Metros1 : 0;
                                        $metros2 = isset($registro->Metros2) && $registro->Metros2 !== null ? (float)$registro->Metros2 : 0;
                                        $metros3 = isset($registro->Metros3) && $registro->Metros3 !== null ? (float)$registro->Metros3 : 0;
                                        $sumaMetros = $metros1 + $metros2 + $metros3;
                                        $metros = $sumaMetros > 0 ? $sumaMetros : '';
                                    }

                                    $hilatura = $registro ? ($registro->Hilatura ?? 0) : 0;
                                    $maquina = $registro ? ($registro->Maquina ?? 0) : 0;
                                    $operac = $registro ? ($registro->Operac ?? 0) : 0;
                                    $transf = $registro ? ($registro->Transf ?? 0) : 0;
                                    $vueltas = $registro ? ($registro->Vueltas ?? '') : '';
                                    $diametro = $registro ? ($registro->Diametro ?? '') : '';
                                    $registroId = $registro ? $registro->Id : null;
                                    $listo = $registro ? (int)($registro->Finalizar ?? 0) : 0;
                                    $ax = $registro ? (int)($registro->AX ?? 0) : 0;

                                    $oficiales = [];
                                    $codigosOficiales = [];
                                    $infoOficial = [];
                                    for ($i = 1; $i <= 3; $i++) {
                                        $cve = trim((string) ($registro ? ($registro->{"CveEmpl{$i}"} ?? '') : ''));
                                        $nomEmpl = trim((string) ($registro ? ($registro->{"NomEmpl{$i}"} ?? '') : ''));
                                        $turnoOficial = $registro ? ($registro->{"Turno{$i}"} ?? null) : null;
                                        $metrosOficial = $registro ? ($registro->{"Metros{$i}"} ?? null) : null;

                                        if ($cve !== '' || $nomEmpl !== '' || $turnoOficial !== null || ($metrosOficial !== null && $metrosOficial !== '')) {
                                            $oficiales[] = [
                                                'numero' => $i,
                                                'nombre' => $nomEmpl !== '' ? $nomEmpl : null,
                                                'clave' => $cve !== '' ? $cve : null,
                                                'metros' => $metrosOficial,
                                                'turno' => $turnoOficial,
                                            ];
                                        }

                                        if ($cve !== '') {
                                            $codigosOficiales[] = $cve;
                                        }

                                        if ($nomEmpl !== '') {
                                            $infoOficial[] = [
                                                'nombre' => $nomEmpl,
                                                'turno' => $turnoOficial,
                                            ];
                                        }
                                    }
                                    $tieneOficiales = count($oficiales) > 0;
                                    $textoCodigos = count($codigosOficiales) > 0 ? implode(', ', $codigosOficiales) : '-';
                                @endphp

                                <tr class="hover:bg-gray-50" data-registro-id="{{ $registroId }}">
                                    {{-- Finalizar (checkbox con permiso registrar) --}}
                                    @if($hasFinalizarPermission)
                                    <td class="px-0 py-1 text-center whitespace-nowrap" style="width: 28px; min-width: 28px; max-width: 28px;">
                                        <input
                                            type="checkbox"
                                            class="checkbox-finalizar w-4 h-4 {{ $ax ? 'text-gray-400 border-gray-400' : 'text-blue-600 border-gray-300' }} bg-gray-100 rounded focus:ring-blue-500 focus:ring-1 {{ $ax ? 'cursor-not-allowed' : 'cursor-pointer' }}"
                                            data-registro-id="{{ $registroId }}"
                                            data-row-index="{{ $rowIndex }}"
                                            data-ax="{{ $ax }}"
                                            title="{{ $ax ? 'Enviado a AX - No modificable' : 'Marcar como finalizado' }}"
                                            {{ $listo ? 'checked' : '' }}
                                        >
                                    </td>
                                    @endif
                                    {{-- Fecha --}}
                                <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap sticky left-0 z-10 border-r border-gray-200" style="width: 3rem; min-width: 3rem; max-width: 3rem;">
                            <div class="flex items-center justify-center gap-0.5 relative">
                                @php
                                    $fechaGuardada = $registro && $registro->Fecha ? date('Y-m-d', strtotime($registro->Fecha)) : null;
                                    $fechaMostrar = $registro && $registro->Fecha ? date('d/m', strtotime($registro->Fecha)) : date('d/m');
                                @endphp
                                <input
                                    type="date"
                                    data-field="fecha"
                                    data-registro-id="{{ $registroId }}"
                                    data-fecha-inicial="{{ $fechaGuardada ?? '' }}"
                                    class="input-fecha"
                                    value="{{ $fecha }}"
                                    style="position:absolute;opacity:0;width:0;height:0;pointer-events:none;z-index:-1;"
                                >
                                <button
                                    type="button"
                                    class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs bg-white hover:bg-gray-50 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 btn-fecha-display flex items-center justify-center cursor-pointer"
                                    data-registro-id="{{ $registroId }}"
                                >
                                    <span class="fecha-display-text text-gray-900 font-medium">
                                        {{ $fechaMostrar }}
                                    </span>
                                </button>
                            </div>
                        </td>

                                    {{-- Oficial --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-left align-top" style="width: 9rem; min-width: 9rem; max-width: 9rem;">
                                        <div class="flex items-start gap-0.5">
                                            <div
                                                class="oficial-texto text-xs leading-tight min-w-0"
                                                data-registro-id="{{ $registroId }}"
                                                data-oficiales-json="{{ $tieneOficiales ? json_encode($oficiales) : '[]' }}"
                                            >
                                                @if($tieneOficiales)
                                                    <div class="text-gray-800 font-semibold">{{ $textoCodigos }}</div>
                                                    @foreach($infoOficial as $of)
                                                        <div class="text-xs text-gray-600">{{ $of['nombre'] }} <span class="text-amber-600">(T{{ $of['turno'] ?? '-' }})</span></div>
                                                    @endforeach
                                                @else
                                                    <div class="text-gray-400 italic">Sin oficiales</div>
                                                @endif
                                            </div>

                                            @php
                                                $cantidadOficiales = count($oficiales);
                                            @endphp
                                            <button
                                                type="button"
                                                class="btn-agregar-oficial flex-shrink-0 p-1.5 md:p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors"
                                                data-registro-id="{{ $registroId }}"
                                                data-cantidad-oficiales="{{ $cantidadOficiales }}"
                                                title="Agregar oficial"
                                            >
                                                <i class="fa-solid fa-plus-circle text-lg md:text-xl"></i>
                                            </button>
                                        </div>
                        </td>

                                    {{-- H. INICIO --}}
                        <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell w-28 max-w-[104px]">
                            <div class="flex items-center justify-center gap-1">
                                            <input
                                                type="time"
                                                data-field="h_inicio"
                                                lang="en-US"
                                                class="w-24 border border-gray-300 rounded px-2 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                value="{{ $horaInicio }}"
                                            >
                                            <i
                                                class="fa-solid fa-clock text-gray-400 text-2xl md:text-3xl cursor-pointer hover:text-blue-500 hover:bg-blue-50 set-current-time flex-shrink-0 inline-flex items-center justify-center w-10 h-10 md:w-12 md:h-12 rounded-full transition-colors"
                                                data-time-target="h_inicio"
                                                title="Establecer hora actual"
                                            ></i>
                            </div>
                        </td>

                                    {{-- H. FIN --}}
                        <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell w-28 max-w-[104px]">
                            <div class="flex items-center justify-center gap-1">
                                            <input
                                                type="time"
                                                data-field="h_fin"
                                                lang="en-US"
                                                class="w-24 border border-gray-300 rounded px-2 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                value="{{ $horaFin }}"
                                            >
                                            <i
                                                class="fa-solid fa-clock text-gray-400 text-2xl md:text-3xl cursor-pointer hover:text-blue-500 hover:bg-blue-50 set-current-time flex-shrink-0 inline-flex items-center justify-center w-10 h-10 md:w-12 md:h-12 rounded-full transition-colors"
                                                data-time-target="h_fin"
                                                title="Establecer hora actual"
                                            ></i>
                            </div>
                        </td>

                                    {{-- No. Julio --}}
                        <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap w-24 max-w-[75px]">
                                        <select
                                            data-field="no_julio"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-md text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500 select-julio"
                                            data-valor-inicial="{{ $noJulio }}"
                                        >
                                <option value="">Seleccionar...</option>
                            </select>
                        </td>

                                    {{-- Hilos --}}
                        <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell w-20 max-w-[60px]">
                                        <input
                                            type="number"
                                            disabled
                                            data-field="hilos"
                                            class="w-full border border-gray-300 rounded px-1 py-0.5 text-md text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-hilos"
                                            value="{{ $hilos }}"
                                        >
                        </td>

                                    {{-- Kg Bruto --}}
                        <td class="px-1 md:px-0.5 py-1 md:py-1.5 text-center whitespace-nowrap w-12 md:w-10 lg:w-12">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="kg_bruto"
                                            class="w-full border border-gray-300 rounded px-0.5 md:px-1 py-0.5 md:py-1 text-md text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ isset($kgBruto) && $kgBruto !== '' ? number_format((float)$kgBruto, 2, '.', '') : '' }}"
                                        >
                        </td>

                                    {{-- Tara --}}
                        <td class="px-1 md:px-0.5 py-1 md:py-1.5 text-center whitespace-nowrap w-10 md:w-9 lg:w-10">
                                        <input
                                            type="number"
                                            step="0.01"
                                            disabled
                                            data-field="tara"
                                            class="w-full border border-gray-300 rounded px-0.5 md:px-1 py-0.5 md:py-1 text-md text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $tara }}"
                                        >
                        </td>

                                    {{-- Kg Neto --}}
                        <td class="px-1 md:px-0.5 py-1 md:py-1.5 text-center whitespace-nowrap w-12 md:w-10 lg:w-12">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="kg_neto"
                                            class="w-full border border-gray-300 rounded px-0.5 md:px-1 py-0.5 md:py-1 text-md text-center bg-gray-50 text-gray-600 cursor-not-allowed"
                                            value="{{ $kgNeto }}"
                                            readonly
                                        >
                        </td>

                                    {{-- Metros --}}
                        <td class="px-1 md:px-1.5 py-1 md:py-1.5 text-center whitespace-nowrap w-16 md:w-14 lg:w-20">
                                        <input
                                            type="number"
                                            disabled
                                            data-field="metros"
                                            class="w-full border border-gray-300 rounded px-1 md:px-2 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $metros }}"
                                        >
                        </td>

                                    {{-- Hilatura --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-12 md:w-10 lg:w-12">
                            <div class="flex items-center justify-center relative">
                                            <button
                                                type="button"
                                                class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-1.5 py-1.5 md:px-1.5 md:py-1.5 lg:px-2 lg:py-2 rounded text-md transition-colors"
                                                onclick="toggleQuantityEdit(this, 'hilat')"
                                            >
                                                <span class="quantity-display font-semibold" data-field="hilat">
                                                    {{ $hilatura }}
                                                </span>
                                </button>
                                <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                        <div class="flex space-x-1 min-w-max">
                                                        @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                            <span
                                                                class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $hilatura ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
                                                                data-value="{{ $numIndex }}"
                                                            >
                                                                {{ $numIndex }}
                                                            </span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>

                                    {{-- Maquina --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-12 md:w-10 lg:w-12">
                            <div class="flex items-center justify-center relative">
                                            <button
                                                type="button"
                                                class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-1.5 py-1.5 md:px-1.5 md:py-1.5 lg:px-2 lg:py-2 rounded text-md transition-colors"
                                                onclick="toggleQuantityEdit(this, 'maq')"
                                            >
                                                <span class="quantity-display font-semibold" data-field="maq">
                                                    {{ $maquina }}
                                                </span>
                                </button>
                                <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                        <div class="flex space-x-1 min-w-max">
                                                        @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                            <span
                                                                class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $maquina ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
                                                                data-value="{{ $numIndex }}"
                                                            >
                                                                {{ $numIndex }}
                                                            </span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Operac --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-12 md:w-10 lg:w-12">
                            <div class="flex items-center justify-center relative">
                                <button
                                    type="button"
                                    class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-1.5 py-1.5 md:px-1.5 md:py-1.5 lg:px-2 lg:py-2 rounded text-md transition-colors"
                                    onclick="toggleQuantityEdit(this, 'operac')"
                                >
                                    <span class="quantity-display font-semibold" data-field="operac">
                                        {{ $operac }}
                                    </span>
                                </button>
                                <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                <span
                                                    class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $operac ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
                                                    data-value="{{ $numIndex }}"
                                                >
                                                    {{ $numIndex }}
                                                </span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>

                                    {{-- Transf --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-12 md:w-10 lg:w-12">
                            <div class="flex items-center justify-center relative">
                                <button
                                    type="button"
                                    class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-1.5 py-1.5 md:px-1.5 md:py-1.5 lg:px-2 lg:py-2 rounded text-md transition-colors"
                                    onclick="toggleQuantityEdit(this, 'transf')"
                                >
                                    <span class="quantity-display font-semibold" data-field="transf">
                                        {{ $transf }}
                                    </span>
                                </button>
                                <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                    <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                        <div class="flex space-x-1 min-w-max">
                                            @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                <span
                                                    class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $transf ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
                                                    data-value="{{ $numIndex }}"
                                                >
                                                    {{ $numIndex }}
                                                </span>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        @if($isKarlMayer ?? false)
                        {{-- Vueltas --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-16 md:w-14 lg:w-16">
                            <input
                                type="number"
                                step="0.01"
                                data-field="vueltas"
                                data-campo="Vueltas"
                                class="karl-mayer-input w-full border border-emerald-300 rounded px-1 md:px-2 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-emerald-50"
                                value="{{ $vueltas }}"
                                placeholder="0"
                            >
                        </td>
                        {{-- Diametro --}}
                        <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-16 md:w-14 lg:w-16">
                            <input
                                type="number"
                                step="0.01"
                                data-field="diametro"
                                data-campo="Diametro"
                                class="karl-mayer-input w-full border border-emerald-300 rounded px-1 md:px-2 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-emerald-50"
                                value="{{ $diametro }}"
                                placeholder="0"
                            >
                        </td>
                        @endif

                    </tr>
                            @endfor
                        @else
                            <tr>
                                <td colspan="{{ ($hasFinalizarPermission ? 15 : 14) + (($isKarlMayer ?? false) ? 2 : 0) }}" class="px-2 py-4 text-center text-gray-500 italic">
                                    No hay registros para generar.
                                    @if(isset($julios) && $julios->count() > 0)
                                        <br>Total calculado: {{ $totalRegistros }} | Cantidad de julios: {{ $julios->count() }}
                                        <br>
                                        <small>
                                            Valores de julios encontrados:
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
    <div
        id="modal-oficial"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center"
        style="display: none;"
    >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-3 max-h-[65vh] overflow-y-auto">
            <div class="px-4 md:px-6 py-3 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="text-base md:text-lg font-semibold text-gray-900">Oficiales</h3>
                <button type="button" id="btn-cerrar-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>

            <!-- Tabla de oficiales con inputs -->
            <div id="modal-oficiales-lista" class="p-4 -mt-2 md:p-6">
                <input type="hidden" id="modal-registro-id" name="registro_id">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-3 py-2 text-left text-md font-semibold text-gray-700 border border-gray-300">No Operador</th>
                                <th class="px-3 py-2 text-left text-md font-semibold text-gray-700 border border-gray-300 hidden">Nombre</th>
                                <th class="px-3 py-2 text-left text-md font-semibold text-gray-700 border border-gray-300">Turno</th>
                                <th class="px-3 py-2 text-left text-md font-semibold text-gray-700 border border-gray-300">Metros</th>
                                <th class="px-3 py-2 text-center text-md font-semibold text-gray-700 border border-gray-300 w-20">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody id="oficiales-existentes" class="bg-white">
                            <!-- Las 3 filas se renderizarán aquí -->
                        </tbody>
                    </table>
                </div>

                <div class="flex gap-2 md:gap-3 justify-end mt-4">
                    <button
                        type="button"
                        id="btn-cancelar-modal"
                        class="px-3 md:px-4 py-1.5 w-full md:py-2 text-md font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        id="btn-guardar-oficiales"
                        class="px-3 md:px-4 py-1.5 w-full md:py-2 text-md font-medium text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors"
                    >
                        Guardar
                    </button>
                </div>
        </div>
    </div>
</div>

<script>
        (function () {
    'use strict';

    // ─── helpers de notificación reutilizables ──────────────────
    function mostrarToast(icon, text, timer = 2500) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon, text, timer, showConfirmButton: false, toast: true, position: 'top-end' });
        }
    }

    function mostrarAlerta(icon, title, text) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon, title, text });
        }
    }

            // Calcular Kg. NETO automáticamente
    function calcularNeto(row) {
        const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
        const taraInput = row.querySelector('input[data-field="tara"]');
        const netoInput = row.querySelector('input[data-field="kg_neto"]');

        if (!brutoInput || !taraInput || !netoInput) return;

        const bruto = parseFloat(brutoInput.value) || 0;
        const tara = parseFloat(taraInput.value) || 0;
        const neto = bruto - tara;

        // Si el neto es negativo, marcarlo en rojo, si no, quitar el error
        if (neto < 0) {
            netoInput.value = neto.toFixed(2);
            marcarCampoError(netoInput, true);
        } else {
            netoInput.value = neto.toFixed(2);
            marcarCampoError(netoInput, false);
        }
    }

    // Función para marcar campos con error (borde rojo)
    function marcarCampoError(elemento, tieneError) {
        if (!elemento) return;

        if (tieneError) {
            elemento.classList.add('border-red-500', 'border-2');
            elemento.classList.remove('border-gray-300');
        } else {
            elemento.classList.remove('border-red-500', 'border-2');
            elemento.classList.add('border-gray-300');
        }
    }

    // Función para limpiar todos los errores visuales
    function limpiarErroresVisuales() {
        const tablaBody = document.getElementById('tabla-produccion-body');
        if (!tablaBody) return;

        // Limpiar errores de todos los campos editables
        tablaBody.querySelectorAll('input, select').forEach(el => {
            marcarCampoError(el, false);
        });
    }

            // Toggle editor de cantidad
            window.toggleQuantityEdit = function (element, fieldName) {
            const cell = element.closest('td');
            const editContainer = cell.querySelector('.quantity-edit-container');
            const editBtn = cell.querySelector('.edit-quantity-btn');
            const quantityDisplay = cell.querySelector('.quantity-display');

            closeAllQuantityEditors();

            if (editContainer && editBtn && quantityDisplay) {
                const isHidden = editContainer.classList.contains('hidden');

                // Asegurar que el valor siempre esté visible en el display
                const currentValue = quantityDisplay.textContent.trim();
                if (!currentValue || currentValue === '') {
                    quantityDisplay.textContent = '0';
                }

                editContainer.classList.toggle('hidden');
                editBtn.classList.toggle('hidden');

                // Asegurar que el botón siempre sea visible cuando el editor está oculto
                if (!isHidden) {
                    // Si se está cerrando el editor, mostrar el botón
                    editBtn.classList.remove('hidden');
                    editBtn.style.display = '';
                } else {
                    // Si se está abriendo el editor, ocultar el botón
                    editBtn.classList.add('hidden');
                }

                    if (isHidden && quantityDisplay) {
                        const displayValue = quantityDisplay.textContent.trim() || '0';
                    const allOptions = editContainer.querySelectorAll('.number-option');
                    allOptions.forEach(o => {
                        const value = o.getAttribute('data-value');
                            if (String(value) === String(displayValue)) {
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
                    const cell = container.closest('td');
                    const editBtn = cell ? cell.querySelector('.edit-quantity-btn') : null;
                    const quantityDisplay = cell ? cell.querySelector('.quantity-display') : null;

                    // Asegurar que el valor se mantenga visible
                    if (quantityDisplay && quantityDisplay.textContent.trim() === '') {
                        // Si el valor está vacío, establecer un valor por defecto de 0
                        quantityDisplay.textContent = '0';
                    }

                    container.classList.add('hidden');
                    if (editBtn) {
                        editBtn.classList.remove('hidden');
                        editBtn.style.display = ''; // Asegurar que el botón sea visible
                    }
                }
            });
        }

            document.addEventListener('DOMContentLoaded', function () {
                const tablaBody = document.getElementById('tabla-produccion-body');

                if (tablaBody) {
                    tablaBody.addEventListener('input', function (e) {
                        const row = e.target.closest('tr');
                        if (!row) return;

                        if (e.target.dataset.field === 'kg_bruto' || e.target.dataset.field === 'tara') {
                            calcularNeto(row);

                            if (e.target.dataset.field === 'kg_bruto') {
                                const registroId = row.getAttribute('data-registro-id');
                                const kgBrutoValue = e.target.value;

                                if (!registroId) return;

                                if (!verificarOficialSeleccionado(registroId)) {
                                    if (debounceTimeouts.has(registroId)) {
                                        clearTimeout(debounceTimeouts.get(registroId));
                                        debounceTimeouts.delete(registroId);
                                    }
                                    return;
                                }

                                if (debounceTimeouts.has(registroId)) {
                                    clearTimeout(debounceTimeouts.get(registroId));
                                }

                                const timeoutId = setTimeout(() => {
                                    actualizarKgBruto(registroId, kgBrutoValue);
                                    debounceTimeouts.delete(registroId);
                                }, 6000);

                                debounceTimeouts.set(registroId, timeoutId);
                            }
                        }
                    });

                    // Event listeners para inputs de Karl Mayer (Vueltas / Diámetro)
                    const karlMayerInputs = tablaBody.querySelectorAll('.karl-mayer-input');
                    const karlMayerDebounce = new Map();
                    karlMayerInputs.forEach(input => {
                        input.addEventListener('change', function () {
                            const row = this.closest('tr');
                            if (!row) return;
                            const registroId = row.getAttribute('data-registro-id');
                            const campo = this.dataset.campo;
                            const valor = this.value;
                            if (!registroId || !campo) return;

                            const key = `${registroId}-${campo}`;
                            if (karlMayerDebounce.has(key)) clearTimeout(karlMayerDebounce.get(key));

                            karlMayerDebounce.set(key, setTimeout(() => {
                                actualizarCampoProduccion(registroId, campo, valor);
                                karlMayerDebounce.delete(key);
                            }, 500));
                        });
                    });

                    tablaBody.querySelectorAll('tr').forEach(row => {
                        calcularNeto(row);
                        const hInicioInput = row.querySelector('input[data-field="h_inicio"]');
                        const hFinInput = row.querySelector('input[data-field="h_fin"]');
                        if (hInicioInput) {
                            hInicioInput.setAttribute('data-valor-anterior', hInicioInput.value || '');
                        }
                        if (hFinInput) {
                            hFinInput.setAttribute('data-valor-anterior', hFinInput.value || '');
                        }
                    });

                }

                let catalogosJuliosCompleto = []; // Variable global para almacenar todos los julios
                cargarCatalogosJulios();
                cargarUsuariosUrdido();

                if (tablaBody) {
                    tablaBody.addEventListener('change', function (e) {
                        const target = e.target;

                        // Cambio de No Julio
                        if (target.classList.contains('select-julio')) {
                            const row = target.closest('tr');
                            const registroId = row ? row.getAttribute('data-registro-id') : null;
                            const taraInput = row ? row.querySelector('input[data-field="tara"]') : null;
                            const selectedOption = target.options[target.selectedIndex];
                            const noJulioValue = selectedOption ? selectedOption.value : '';

                            if (!(taraInput && registroId)) return;

                            if (!verificarOficialSeleccionado(registroId)) {
                                if (!target.hasAttribute('data-valor-anterior')) {
                                    const valorInicial = target.getAttribute('data-valor-inicial') || '';
                                    target.setAttribute('data-valor-anterior', valorInicial);
                                }
                                const valorAnteriorCheck = target.getAttribute('data-valor-anterior') || '';
                                target.value = valorAnteriorCheck;
                                mostrarAlertaOficialRequerido();
                                return;
                            }

                            // Si se seleccionó un julio, actualizar todos los selects para ocultar el julio seleccionado
                            if (noJulioValue && noJulioValue !== '') {
                                // Actualizar todos los selects excluyendo el julio seleccionado
                                actualizarTodosLosSelectsJulios();

                                // Asegurar que el select actual tenga el valor seleccionado
                                target.value = noJulioValue;

                                // Obtener la opción seleccionada después de actualizar
                                const updatedOption = target.options[target.selectedIndex];
                                if (updatedOption) {
                                    const taraStr = updatedOption.getAttribute('data-tara');
                                    const tara = taraStr !== null && taraStr !== '' ? parseFloat(taraStr) : null;
                                    taraInput.value = tara !== null ? tara : '';

                                    const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
                                    const netoInput = row.querySelector('input[data-field="kg_neto"]');
                                    let kgNeto = null;
                                    if (brutoInput && netoInput) {
                                        const bruto = parseFloat(brutoInput.value) || 0;
                                        const taraVal = tara !== null ? tara : 0;
                                        kgNeto = bruto - taraVal;
                                        netoInput.value = kgNeto.toFixed(2);
                                        // Marcar en rojo si es negativo
                                        if (kgNeto < 0) {
                                            marcarCampoError(netoInput, true);
                                        } else {
                                            marcarCampoError(netoInput, false);
                                        }
                                    }

                                    actualizarJulioTara(registroId, noJulioValue, tara, kgNeto);
                                }
                            } else {
                                // Si se deseleccionó (valor vacío), volver a mostrar el julio anterior en otros selects
                                actualizarTodosLosSelectsJulios();
                            }

                            target.setAttribute('data-valor-anterior', noJulioValue);
                        }

                        // El oficial ahora es texto, no hay cambio directo en la tabla

                        // Cambio de fecha
                        if (target.classList.contains('input-fecha') && target.getAttribute('data-field') === 'fecha') {
                            const fechaInput = target;
                            const registroId = fechaInput.getAttribute('data-registro-id');
                            const fechaValue = fechaInput.value;
                            const fechaInicial = fechaInput.getAttribute('data-fecha-inicial');

                            const row = fechaInput.closest('tr');
                            if (row && fechaValue) {
                                const fechaDisplayText = row.querySelector('.fecha-display-text');
                                if (fechaDisplayText) {
                                    const parts = fechaValue.split('-');
                                    if (parts.length === 3) {
                                        fechaDisplayText.textContent = `${parts[2]}/${parts[1]}`;
                                    }
                                }
                            }

                            if (registroId && fechaValue && fechaValue !== fechaInicial) {
                                actualizarFecha(registroId, fechaValue);
                                fechaInput.setAttribute('data-fecha-inicial', fechaValue);
                            }
                        }

                        // Cambio de horas (HoraInicial y HoraFinal)
                        const field = target.getAttribute('data-field');
                        if (field === 'h_inicio' || field === 'h_fin') {
                            const row = target.closest('tr');
                            const registroId = row ? row.getAttribute('data-registro-id') : null;
                            const horaValue = target.value || null;

                            if (!registroId) return;

                            // Verificar que haya un oficial seleccionado
                            if (!verificarOficialSeleccionado(registroId)) {
                                mostrarAlertaOficialRequerido();
                                const anterior = target.getAttribute('data-valor-anterior') || '';
                                target.value = anterior;
                                return;
                            }

                            const campoBD = field === 'h_inicio' ? 'HoraInicial' : 'HoraFinal';

                            actualizarHora(registroId, campoBD, horaValue);
                            target.setAttribute('data-valor-anterior', target.value || '');
                        }
                    });
                }

                // ===== CHECKBOX FINALIZAR (Listo) =====
                function bloquearFila(row) {
                    row.classList.add('bg-green-50', 'opacity-75');
                    row.querySelectorAll('input:not(.checkbox-finalizar), select, button:not(.checkbox-finalizar)').forEach(el => {
                        el.disabled = true;
                        el.classList.add('cursor-not-allowed', 'pointer-events-none');
                    });
                    // Bloquear botones de roturas y oficiales
                    row.querySelectorAll('.edit-quantity-btn, .btn-agregar-oficial, .btn-fecha-display, .set-current-time').forEach(el => {
                        el.disabled = true;
                        el.classList.add('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
                    });
                }

                function desbloquearFila(row) {
                    row.classList.remove('bg-green-50', 'opacity-75');
                    row.querySelectorAll('input:not(.checkbox-finalizar), select, button:not(.checkbox-finalizar)').forEach(el => {
                        // Respetar los campos que estaban disabled originalmente (tara, hilos, kg_neto, metros)
                        const field = el.getAttribute('data-field');
                        const esReadonly = field === 'tara' || field === 'hilos' || field === 'metros' || field === 'kg_neto';
                        if (!esReadonly) {
                            el.disabled = false;
                        }
                        el.classList.remove('cursor-not-allowed', 'pointer-events-none');
                    });
                    row.querySelectorAll('.edit-quantity-btn, .btn-agregar-oficial, .btn-fecha-display, .set-current-time').forEach(el => {
                        el.disabled = false;
                        el.classList.remove('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
                    });
                }

                function esFilaBloqueada(registroId) {
                    const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                    if (!row) return false;
                    const checkbox = row.querySelector('.checkbox-finalizar');
                    return checkbox && checkbox.checked;
                }

                // Bloquear filas que ya vienen con el check activo al cargar
                if (tablaBody) {
                    tablaBody.querySelectorAll('.checkbox-finalizar:checked').forEach(checkbox => {
                        const row = checkbox.closest('tr');
                        if (row) bloquearFila(row);
                    });
                }

                // Interceptar clicks en filas bloqueadas (mostrar alerta)
                if (tablaBody) {
                    tablaBody.addEventListener('mousedown', function (e) {
                        const row = e.target.closest('tr[data-registro-id]');
                        if (!row) return;
                        // Permitir click en el propio checkbox de finalizar
                        if (e.target.classList.contains('checkbox-finalizar') || e.target.closest('.checkbox-finalizar')) return;

                        const checkbox = row.querySelector('.checkbox-finalizar');
                        if (checkbox && checkbox.checked) {
                            e.preventDefault();
                            e.stopPropagation();
                            mostrarToast('info', 'Este registro ya está parcialmente finalizado. Desmarca la casilla para editarlo.', 2500);
                        }
                    }, true);
                }

                if (tablaBody) {
                    tablaBody.addEventListener('change', function (e) {
                        if (!e.target.classList.contains('checkbox-finalizar')) return;

                        const checkbox = e.target;
                        const registroId = checkbox.getAttribute('data-registro-id');
                        const listo = checkbox.checked;
                        const ax = parseInt(checkbox.getAttribute('data-ax') || '0', 10);

                        if (!registroId) return;

                        // Si ax = 1, no permitir cambiar
                        if (ax === 1) {
                            checkbox.checked = !listo; // Revertir
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'No modificable',
                                    text: 'Este registro ya fue enviado a AX y no se puede modificar.',
                                    confirmButtonColor: '#2563eb'
                                });
                            }
                            return;
                        }

                        // Si intenta marcar (finalizar), validar que la fila tenga todos los campos llenos
                        if (listo) {
                            const row = checkbox.closest('tr');
                            const validacion = validarFilaParaFinalizar(row);
                            if (!validacion.valido) {
                                checkbox.checked = false; // Revertir el check
                                const camposStr = validacion.camposFaltantes.join(', ');
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'Registro incompleto',
                                        text: 'Completa los campos requeridos antes de finalizar: ' + camposStr,
                                        confirmButtonColor: '#2563eb'
                                    });
                                } else {
                                    alert('Completa los campos requeridos antes de finalizar: ' + camposStr);
                                }
                                return;
                            }
                        }

                        marcarRegistroListo(registroId, listo, checkbox);
                    });
                }

                async function marcarRegistroListo(registroId, listo, checkbox) {
                    const row = checkbox.closest('tr');

                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.marcar.listo') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                registro_id: registroId,
                                listo: listo
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            // Bloquear o desbloquear la fila
                            if (row) {
                                if (listo) {
                                    bloquearFila(row);
                                } else {
                                    desbloquearFila(row);
                                }
                            }

                            mostrarToast('success', listo ? 'Registro parcialmente finalizado' : 'Registro desbloqueado para edición', 1500);
                        } else {
                            checkbox.checked = !listo;
                            mostrarAlerta('error', 'Error', result.error || 'Error al actualizar el registro');
                        }
                    } catch (error) {
                        console.error('Error al marcar como listo:', error);
                        checkbox.checked = !listo;
                        mostrarAlerta('error', 'Error', 'Error al actualizar el registro. Por favor, intenta nuevamente.');
                    }
                }

                // ===== FUNCIONES AUXILIARES =====
                function verificarFilaNoFinalizada(registroId) {
                    if (esFilaBloqueada(registroId)) {
                        mostrarToast('info', 'Este registro ya está parcialmente finalizado. Desmarca la casilla para editarlo.');
                        return false;
                    }
                    return true;
                }

                function verificarOficialSeleccionado(registroId) {
                    const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                    if (!row) return false;

                    const oficialTexto = row.querySelector('.oficial-texto');
                    if (!oficialTexto) return false;

                    const texto = oficialTexto.textContent.trim();
                    return texto !== null && texto !== '' && texto !== 'Sin oficiales';
                }

                function mostrarAlertaOficialRequerido() {
                    mostrarToast('warning', 'Debes seleccionar un oficial antes de actualizar este campo', 3000);
                }

                async function actualizarFecha(registroId, fecha) {
                    if (!verificarFilaNoFinalizada(registroId)) return;
                    if (!verificarOficialSeleccionado(registroId)) {
                        mostrarAlertaOficialRequerido();
                        return;
                    }

                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.fecha') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ registro_id: registroId, fecha })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarToast('success', 'La fecha ha sido actualizada correctamente', 2000);
                        } else {
                            mostrarAlerta('error', 'Error', result.error || 'Error al actualizar la fecha');
                        }
                    } catch (error) {
                        console.error('Error al actualizar fecha:', error);
                        mostrarAlerta('error', 'Error', 'Error al actualizar la fecha. Por favor, intenta nuevamente.');
                    }
                }

                async function actualizarTurnoOficial(registroId, numeroOficial, turno) {
                    if (!verificarFilaNoFinalizada(registroId)) return;
                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.turno.oficial') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                registro_id: registroId,
                                numero_oficial: numeroOficial,
                                turno: turno
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarToast('success', 'El turno ha sido actualizado correctamente', 2000);
                        } else {
                            mostrarAlerta('error', 'Error', result.error || 'Error al actualizar el turno del oficial');
                        }
                    } catch (error) {
                        console.error('Error al actualizar turno del oficial:', error);
                        mostrarAlerta('error', 'Error', 'Error al actualizar el turno. Por favor, intenta nuevamente.');
                    }
                }

                const debounceTimeouts = new Map();

                async function actualizarKgBruto(registroId, kgBruto) {
                    if (!verificarFilaNoFinalizada(registroId)) return;
                    if (!verificarOficialSeleccionado(registroId)) {
                        mostrarAlertaOficialRequerido();
                        return;
                    }

                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.kg.bruto') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                registro_id: registroId,
                                kg_bruto: kgBruto !== null && kgBruto !== '' ? parseFloat(kgBruto) : null
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Actualizado',
                                    text: 'Kg. Bruto actualizado correctamente',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            }

                            const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                            if (row && result.data) {
                                const netoInput = row.querySelector('input[data-field="kg_neto"]');
                                if (netoInput && result.data.kg_neto !== undefined && result.data.kg_neto !== null) {
                                    const kgNetoValue = parseFloat(result.data.kg_neto);
                                    netoInput.value = kgNetoValue.toFixed(2);
                                    // Marcar en rojo si es negativo
                                    if (kgNetoValue < 0) {
                                        marcarCampoError(netoInput, true);
                                    } else {
                                        marcarCampoError(netoInput, false);
                                    }
                                } else if (netoInput && result.data.kg_neto === null) {
                                    netoInput.value = '';
                                    marcarCampoError(netoInput, false);
                                }
                            }
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: result.error || 'Error al actualizar Kg. Bruto'
                                });
                            }
                        }
                    } catch (error) {
                        console.error('Error al actualizar KgBruto:', error);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error al actualizar Kg. Bruto. Por favor, intenta nuevamente.'
                            });
                        }
                    }
                }

                async function actualizarJulioTara(registroId, noJulio, tara) {
                    if (!verificarFilaNoFinalizada(registroId)) return;
                    if (!verificarOficialSeleccionado(registroId)) {
                        mostrarAlertaOficialRequerido();
                        return;
                    }

                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.julio.tara') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                registro_id: registroId,
                                no_julio: noJulio || null,
                                tara: tara !== null && tara !== '' && tara !== undefined ? parseFloat(tara) : null
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Actualizado',
                                    text: 'No. Julio y Tara actualizados correctamente',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            }

                            const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                            if (row && result.data && result.data.kg_neto !== undefined) {
                                const netoInput = row.querySelector('input[data-field="kg_neto"]');
                                if (netoInput && result.data.kg_neto !== null) {
                                    const kgNetoValue = parseFloat(result.data.kg_neto);
                                    netoInput.value = kgNetoValue.toFixed(2);
                                    // Marcar en rojo si es negativo
                                    if (kgNetoValue < 0) {
                                        marcarCampoError(netoInput, true);
                                    } else {
                                        marcarCampoError(netoInput, false);
                                    }
                                } else if (netoInput) {
                                    netoInput.value = '';
                                    marcarCampoError(netoInput, false);
                                }
                            }
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: result.error || 'Error al actualizar No. Julio y Tara'
                                });
                            }
                        }
                    } catch (error) {
                        console.error('Error al actualizar NoJulio y Tara:', error);
                        mostrarAlerta('error', 'Error', 'Error al actualizar No. Julio y Tara. Por favor, intenta nuevamente.');
                    }
                }

                async function actualizarHora(registroId, campo, valor) {
                    if (!verificarFilaNoFinalizada(registroId)) return;
                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.horas') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                registro_id: registroId,
                                campo: campo,
                                valor: valor
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarToast('success', result.message || 'La hora ha sido actualizada correctamente', 2000);
                        } else {
                            mostrarAlerta('error', 'Error', result.error || 'Error al actualizar la hora');
                        }
                    } catch (error) {
                        console.error('Error al actualizar hora:', error);
                        mostrarAlerta('error', 'Error', 'Error al actualizar la hora. Por favor, intenta nuevamente.');
                    }
                }

                // Función para obtener los julios seleccionados en otras filas
                function obtenerJuliosSeleccionados(excluirSelect) {
                    const juliosSeleccionados = new Set();
                    const todosLosSelects = document.querySelectorAll('.select-julio');

                    todosLosSelects.forEach(select => {
                        if (select !== excluirSelect && select.value && select.value !== '') {
                            juliosSeleccionados.add(select.value);
                        }
                    });

                    return juliosSeleccionados;
                }

                // Función para actualizar un select de julios excluyendo los ya seleccionados
                function actualizarSelectJulio(select, excluirJulios = new Set()) {
                    const valorActual = select.value;
                    const valorInicial = select.getAttribute('data-valor-inicial');

                    // Limpiar opciones excepto la primera
                    while (select.options.length > 1) {
                        select.remove(1);
                    }

                    // Agregar opciones disponibles (excluyendo las ya seleccionadas)
                    catalogosJuliosCompleto.forEach(item => {
                        const julioValue = String(item.julio);

                        // Si el julio está seleccionado en otra fila, no agregarlo
                        if (excluirJulios.has(julioValue)) {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = item.julio;
                        option.setAttribute('data-tara', item.tara || '0');
                        option.textContent = item.julio;

                        // Si es el valor inicial o el valor actual, seleccionarlo
                        if ((valorInicial && String(item.julio) === String(valorInicial)) ||
                            (valorActual && String(item.julio) === String(valorActual))) {
                            option.selected = true;
                        }

                        select.appendChild(option);
                    });

                    // Si el valor actual ya no está disponible, limpiar la selección
                    if (valorActual && !excluirJulios.has(valorActual)) {
                        const optionExists = Array.from(select.options).some(opt => opt.value === valorActual);
                        if (!optionExists && valorActual !== '') {
                            select.value = '';
                        }
                    }
                }

                // Función para actualizar todos los selects de julios
                function actualizarTodosLosSelectsJulios() {
                    const todosLosSelects = document.querySelectorAll('.select-julio');

                    todosLosSelects.forEach(select => {
                        const juliosExcluidos = obtenerJuliosSeleccionados(select);
                        actualizarSelectJulio(select, juliosExcluidos);
                    });
                }

                async function cargarCatalogosJulios() {
                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.catalogos.julios') }}');
                        const result = await response.json();

                        if (!(result.success && result.data)) {
                            console.error('Error al cargar catálogo de julios:', result.error || 'Error desconocido');
                            return;
                        }

                        // Guardar el catálogo completo en la variable global
                        catalogosJuliosCompleto = result.data;

                        // Primero, actualizar todos los selects respetando los valores iniciales
                        // Necesitamos hacerlo en dos pasos: primero identificar todos los valores iniciales,
                        // luego actualizar excluyendo esos valores
                        const todosLosSelects = document.querySelectorAll('.select-julio');

                        // Paso 1: Recopilar todos los valores iniciales que deben mantenerse
                        const valoresIniciales = new Map();
                        todosLosSelects.forEach(select => {
                            const valorInicial = select.getAttribute('data-valor-inicial');
                            if (valorInicial && valorInicial !== '') {
                                valoresIniciales.set(select, valorInicial);
                            }
                        });

                        // Paso 2: Actualizar cada select excluyendo los julios seleccionados en otros
                        todosLosSelects.forEach(select => {
                            const valorInicial = valoresIniciales.get(select) || '';
                            const juliosExcluidos = obtenerJuliosSeleccionados(select);

                            // Si este select tiene un valor inicial, excluirlo de los excluidos temporalmente
                            // para que pueda mantener su valor
                            const juliosExcluidosParaEste = new Set(juliosExcluidos);
                            if (valorInicial) {
                                // No excluir el valor inicial de este select
                                // pero sí excluir los valores iniciales de otros selects
                                valoresIniciales.forEach((valIni, otroSelect) => {
                                    if (otroSelect !== select && valIni && valIni !== '') {
                                        juliosExcluidosParaEste.add(valIni);
                                    }
                                });
                            }

                            actualizarSelectJulio(select, juliosExcluidosParaEste);

                            // Restaurar el valor inicial si existe
                            if (valorInicial && valorInicial !== '') {
                                select.value = valorInicial;
                                select.setAttribute('data-valor-anterior', valorInicial);

                                // Configurar tara y calcular neto
                                const row = select.closest('tr');
                                const taraInput = row ? row.querySelector('input[data-field="tara"]') : null;
                                const selectedOption = select.options[select.selectedIndex];

                                if (taraInput && selectedOption) {
                                    const tara = selectedOption.getAttribute('data-tara') || '0';
                                    taraInput.value = tara;

                                    const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
                                    const netoInput = row.querySelector('input[data-field="kg_neto"]');
                                    if (brutoInput && netoInput) {
                                        const bruto = parseFloat(brutoInput.value) || 0;
                                        const taraVal = parseFloat(tara) || 0;
                                        const neto = bruto - taraVal;
                                        netoInput.value = neto.toFixed(2);
                                        // Marcar en rojo si es negativo
                                        if (neto < 0) {
                                            marcarCampoError(netoInput, true);
                                        } else {
                                            marcarCampoError(netoInput, false);
                                        }
                                    }
                                }
                            } else {
                                select.setAttribute('data-valor-anterior', '');
                            }
                        });
                    } catch (error) {
                        console.error('Error al cargar catálogo de julios:', error);
                    }
                }

                document.addEventListener('click', function (event) {
            const isInsideEditor = event.target.closest('.quantity-edit-container');
            const isEditButton = event.target.closest('.edit-quantity-btn');
            const isNumberOption = event.target.closest('.number-option');

            // No cerrar si se está haciendo clic dentro del editor, en el botón o en una opción de número
            if (!isInsideEditor && !isEditButton && !isNumberOption) {
                closeAllQuantityEditors();
            }
        });

                async function actualizarCampoProduccion(registroId, campo, valor) {
                    if (!verificarFilaNoFinalizada(registroId)) return;
                    if (!verificarOficialSeleccionado(registroId)) {
                        mostrarAlertaOficialRequerido();
                        return;
                    }

                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.campos.produccion') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                registro_id: registroId,
                                campo,
                                valor: valor !== null && valor !== '' ? parseInt(valor) : null
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarToast('success', result.message || 'Campo actualizado correctamente', 2000);
                        } else {
                            mostrarAlerta('error', 'Error', result.error || 'Error al actualizar campo');
                        }
                    } catch (error) {
                        console.error('Error al actualizar campo de producción:', error);
                        mostrarAlerta('error', 'Error', 'Error al actualizar campo. Por favor, intenta nuevamente.');
                    }
                }

                const campoMap = {
                    hilat: 'Hilatura',
                    maq: 'Maquina',
                    operac: 'Operac',
                    transf: 'Transf'
                };

                document.addEventListener('click', function (e) {
            const opt = e.target.closest('.number-option');
            if (!opt) return;

            e.preventDefault();
            e.stopPropagation();

            const container = opt.closest('.number-scroll-container');
            const allOptions = container.querySelectorAll('.number-option');
            const cell = opt.closest('td');
                    const row = cell ? cell.closest('tr') : null;
            const selectedValue = opt.getAttribute('data-value');

            allOptions.forEach(o => {
                o.classList.remove('bg-blue-500', 'text-white');
                o.classList.add('bg-gray-100', 'text-gray-700');
            });

            opt.classList.remove('bg-gray-100', 'text-gray-700');
            opt.classList.add('bg-blue-500', 'text-white');

            const quantityDisplay = cell.querySelector('.quantity-display');
            if (quantityDisplay) {
                // Asegurar que siempre haya un valor visible
                quantityDisplay.textContent = selectedValue || '0';

                        const fieldName = quantityDisplay.getAttribute('data-field');
                        const registroId = row ? row.getAttribute('data-registro-id') : null;

                        if (registroId && fieldName && campoMap[fieldName]) {
                            actualizarCampoProduccion(registroId, campoMap[fieldName], selectedValue);
                        }
                    }

            const editContainer = cell.querySelector('.quantity-edit-container');
            const editBtn = cell.querySelector('.edit-quantity-btn');
            if (editContainer) {
                editContainer.classList.add('hidden');
            }
            if (editBtn) {
                editBtn.classList.remove('hidden');
                editBtn.style.display = ''; // Asegurar que el botón sea visible
            }
                });

                document.addEventListener('click', function (e) {
                    const btnFecha = e.target.closest('.btn-fecha-display');
                    if (!btnFecha) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const registroId = btnFecha.getAttribute('data-registro-id');
                    const row = btnFecha.closest('tr');
                    if (!row) return;

                    const fechaInput = row.querySelector('input.input-fecha[data-registro-id="' + registroId + '"]');
                    if (!fechaInput) return;

                    // Hacer el input temporalmente visible y clickeable
                    const originalStyles = {
                        position: fechaInput.style.position,
                        opacity: fechaInput.style.opacity,
                        width: fechaInput.style.width,
                        height: fechaInput.style.height,
                        zIndex: fechaInput.style.zIndex,
                        pointerEvents: fechaInput.style.pointerEvents,
                        cursor: fechaInput.style.cursor,
                        top: fechaInput.style.top,
                        left: fechaInput.style.left
                    };

                    // Posicionar el input sobre el botón
                    const btnRect = btnFecha.getBoundingClientRect();
                    const containerRect = btnFecha.closest('td').getBoundingClientRect();

                    fechaInput.style.position = 'fixed';
                    fechaInput.style.opacity = '0';
                    fechaInput.style.width = btnRect.width + 'px';
                    fechaInput.style.height = btnRect.height + 'px';
                    fechaInput.style.top = btnRect.top + 'px';
                    fechaInput.style.left = btnRect.left + 'px';
                    fechaInput.style.zIndex = '9999';
                    fechaInput.style.pointerEvents = 'auto';
                    fechaInput.style.cursor = 'pointer';

                    // Pequeño delay para asegurar que el navegador procese los cambios
                    setTimeout(() => {
                        fechaInput.focus();
                        fechaInput.showPicker ? fechaInput.showPicker() : fechaInput.click();
                    }, 10);

                    // Restaurar estilos después de un tiempo
                    setTimeout(() => {
                        fechaInput.style.position = originalStyles.position || 'absolute';
                        fechaInput.style.opacity = originalStyles.opacity || '0';
                        fechaInput.style.width = originalStyles.width || '0';
                        fechaInput.style.height = originalStyles.height || '0';
                        fechaInput.style.zIndex = originalStyles.zIndex || '-1';
                        fechaInput.style.pointerEvents = originalStyles.pointerEvents || 'none';
                        fechaInput.style.cursor = originalStyles.cursor || '';
                        fechaInput.style.top = originalStyles.top || '';
                        fechaInput.style.left = originalStyles.left || '';
                    }, 500);
                });

                document.addEventListener('click', function (e) {
            const iconElement = e.target.closest('.set-current-time');
                    if (!iconElement) return;

                e.preventDefault();

                const timeTarget = iconElement.getAttribute('data-time-target');
                const row = iconElement.closest('tr');
                    const timeInput = row ? row.querySelector('input[data-field="' + timeTarget + '"]') : null;
                    const registroId = row ? row.getAttribute('data-registro-id') : null;

                    if (!timeInput) return;
                    if (!registroId) return;
                    if (!verificarOficialSeleccionado(registroId)) {
                        mostrarAlertaOficialRequerido();
                        return;
                    }

                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const currentTime = `${hours}:${minutes}`;

                    timeInput.value = currentTime;
                    timeInput.setAttribute('data-valor-anterior', currentTime);
                    timeInput.dispatchEvent(new Event('change', { bubbles: true }));

                    iconElement.classList.add('text-blue-500');
                    setTimeout(() => {
                        iconElement.classList.remove('text-blue-500');
                    }, 300);
                });

                // ===== LÓGICA DE OFICIALES =====
                const modalOficial = document.getElementById('modal-oficial');
                const btnCerrarModal = document.getElementById('btn-cerrar-modal');
                const btnCancelarModal = document.getElementById('btn-cancelar-modal');
                const modalRegistroId = document.getElementById('modal-registro-id');

                let usuariosUrdido = [];

                async function cargarUsuariosUrdido() {
                    try {
                        const response = await fetch('{{ route('urdido.modulo.produccion.urdido.usuarios.urdido') }}');
                        const result = await response.json();

                        if (result.success && result.data) {
                            usuariosUrdido = result.data;
                        } else {
                            console.error('Error al cargar usuarios:', result.error);
                            usuariosUrdido = [];
                        }
                    } catch (error) {
                        console.error('Error al cargar usuarios de Urdido:', error);
                        usuariosUrdido = [];
                    }
                }

                function poblarSelectUsuarios(selectElement, claveSeleccionada, seleccionarPorDefecto = false) {
                    if (!selectElement || !usuariosUrdido.length) return;

                    // Limpiar opciones existentes excepto la primera
                    while (selectElement.options.length > 1) {
                        selectElement.remove(1);
                    }

                    let usuarioSeleccionado = null;
                    let debeSeleccionar = seleccionarPorDefecto || selectElement.hasAttribute('data-seleccionar-por-defecto');

                    // Agregar usuarios
                    usuariosUrdido.forEach(usuario => {
                        const option = document.createElement('option');
                        option.value = usuario.numero_empleado;
                        option.textContent = usuario.nombre;
                        option.setAttribute('data-numero-empleado', usuario.numero_empleado);
                        option.setAttribute('data-nombre', usuario.nombre);
                        option.setAttribute('data-turno', usuario.turno || '');

                        // Seleccionar si coincide con la clave o si debe seleccionarse por defecto
                        if ((claveSeleccionada && usuario.numero_empleado === claveSeleccionada) ||
                            (debeSeleccionar && !usuarioSeleccionado)) {
                            option.selected = true;
                            usuarioSeleccionado = usuario;
                            debeSeleccionar = false; // Solo seleccionar el primero si es por defecto
                        }

                        selectElement.appendChild(option);
                    });

                    // Si hay un usuario seleccionado, actualizar el nombre en el input readonly
                    if (usuarioSeleccionado) {
                        const numero = selectElement.getAttribute('data-numero');
                        const nombreInput = document.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                        const claveInput = document.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);

                        if (nombreInput) {
                            nombreInput.value = usuarioSeleccionado.nombre;
                        }
                        if (claveInput) {
                            claveInput.value = usuarioSeleccionado.numero_empleado;
                        }

                        // Disparar evento change para actualizar otros campos si es necesario
                        selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                function obtenerClavesOficialesEnModal() {
                    const container = document.getElementById('oficiales-existentes');
                    if (!container) return [];

                    const claves = [];
                    for (let i = 1; i <= 3; i++) {
                        const claveInput = container.querySelector(`input.input-oficial-clave[data-numero="${i}"]`);
                        const clave = claveInput ? (claveInput.value || '').trim() : '';
                        if (clave) claves.push({ numero: i, clave });
                    }
                    return claves;
                }

                function obtenerClavesRepetidasEnModal() {
                    const claves = obtenerClavesOficialesEnModal();
                    const map = new Map();
                    const repetidas = new Map();

                    claves.forEach(item => {
                        if (!map.has(item.clave)) {
                            map.set(item.clave, [item.numero]);
                        } else {
                            const nums = map.get(item.clave);
                            nums.push(item.numero);
                            repetidas.set(item.clave, nums);
                        }
                    });

                    return repetidas;
                }

                function marcarEstadoDuplicadosOficiales(repetidas = new Map()) {
                    const container = document.getElementById('oficiales-existentes');
                    if (!container) return;

                    container.querySelectorAll('select.select-oficial-nombre').forEach(select => {
                        const numero = parseInt(select.getAttribute('data-numero'), 10);
                        const claveInput = container.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);
                        const clave = claveInput ? (claveInput.value || '').trim() : '';
                        const esDuplicado = clave && repetidas.has(clave) && repetidas.get(clave).includes(numero);

                        select.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                        select.classList.add('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');

                        if (esDuplicado) {
                            select.classList.remove('border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500');
                            select.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                        }
                    });
                }

                function validarNoOperadorDuplicadoEnModal(mostrarAlerta = true) {
                    const repetidas = obtenerClavesRepetidasEnModal();
                    marcarEstadoDuplicadosOficiales(repetidas);

                    if (repetidas.size === 0) return true;

                    if (mostrarAlerta) {
                        const [clave, oficiales] = repetidas.entries().next().value;
                        mostrarAlertaErrorModal(`El No. Operador ${clave} está repetido entre oficiales (${oficiales.join(', ')}).`);
                    }
                    return false;
                }

                function renderizarOficialesExistentes(registroId) {
                    const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                    if (!row) return;

                    const oficialTexto = row.querySelector('.oficial-texto');
                    const containerOficiales = document.getElementById('oficiales-existentes');
                    const modalOficialesLista = document.getElementById('modal-oficiales-lista');

                    if (!containerOficiales) return;

                    // Obtener oficiales existentes desde el atributo data-oficiales-json
                    const oficiales = [];
                    if (oficialTexto) {
                        const oficialesJson = oficialTexto.getAttribute('data-oficiales-json');
                        if (oficialesJson) {
                            try {
                                const oficialesArray = JSON.parse(oficialesJson);
                                oficiales.push(...oficialesArray);
                            } catch (e) {
                                console.error('Error al parsear oficiales:', e);
                            }
                        }
                    }

                    // Siempre renderizar 3 filas con inputs
                    containerOficiales.innerHTML = '';

                    for (let i = 1; i <= 3; i++) {
                        const oficial = oficiales.find(o => parseInt(o.numero) === i) || {
                            numero: i,
                            nombre: '',
                            clave: '',
                            metros: '',
                            turno: ''
                        };

                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';
                        row.innerHTML = `
                            <td class="px-3 py-2 border border-gray-300">
                                <select
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 select-oficial-nombre"
                                    data-numero="${i}"
                                >
                                    <option value="">Seleccionar empleado...</option>
                                </select>
                                <input
                                    type="hidden"
                                    class="input-oficial-clave"
                                    data-numero="${i}"
                                    value="${oficial.clave || ''}"
                                >
                            </td>
                            <td class="px-3 py-2 border border-gray-300 hidden">
                                <input
                                    type="text"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-md bg-gray-50 cursor-not-allowed input-oficial-nombre"
                                    data-numero="${i}"
                                    value="${oficial.nombre || ''}"
                                    placeholder="Se selecciona automáticamente"
                                    readonly
                                >
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <select
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-oficial-turno"
                                    data-numero="${i}"
                                >
                                    <option value="">Seleccionar...</option>
                                    <option value="1" ${oficial.turno === '1' ? 'selected' : ''}>1</option>
                                    <option value="2" ${oficial.turno === '2' ? 'selected' : ''}>2</option>
                                    <option value="3" ${oficial.turno === '3' ? 'selected' : ''}>3</option>
                                </select>
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-oficial-metros"
                                    data-numero="${i}"
                                    value="${oficial.metros || ''}"
                                    placeholder="0.00"
                                >
                            </td>
                            <td class="px-3 py-2 border border-gray-300 text-center">
                                <button
                                    type="button"
                                    class="btn-eliminar-oficial px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors ${oficial.nombre ? '' : 'opacity-50 cursor-not-allowed'}"
                                    data-numero="${i}"
                                    title="Eliminar oficial"
                                    ${!oficial.nombre ? 'disabled' : ''}
                                >
                                    <i class="fa-solid fa-trash text-sm"></i>
                                </button>
                            </td>
                        `;
                        containerOficiales.appendChild(row);

                        // Poblar el select de usuarios (solo seleccionar si ya tiene clave asignada, nunca auto-rellenar vacíos)
                        const selectNombre = row.querySelector('.select-oficial-nombre');
                        if (selectNombre) {
                            poblarSelectUsuarios(selectNombre, oficial.clave, false);
                        }
                    }
                    validarNoOperadorDuplicadoEnModal(false);
                    modalOficialesLista.classList.remove('hidden');
                }

                async function abrirModalOficial(registroId) {
                    const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                    if (!row) return;

                    // Asegurar que los usuarios estén cargados antes de renderizar
                    if (usuariosUrdido.length === 0) {
                        await cargarUsuariosUrdido();
                    }

                    renderizarOficialesExistentes(registroId);

                    if (modalRegistroId) modalRegistroId.value = registroId;

                    modalOficial.classList.remove('hidden');
                    modalOficial.style.display = 'flex';
                }

                function mostrarAlertaErrorModal(mensaje) {
                    mostrarAlerta('warning', 'Acción no permitida', mensaje);
                }

                // Event listener para cuando se selecciona un empleado
                document.addEventListener('change', function (e) {
                    if (e.target.classList.contains('select-oficial-nombre')) {
                        const select = e.target;
                        const numero = select.getAttribute('data-numero');
                        const selectedOption = select.options[select.selectedIndex];

                        if (selectedOption && selectedOption.value) {
                            const numeroEmpleado = selectedOption.value;
                            const nombre = selectedOption.getAttribute('data-nombre') || selectedOption.textContent;
                            const turno = selectedOption.getAttribute('data-turno') || '';

                            // Actualizar el input hidden con el número de empleado
                            const claveInput = document.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);
                            if (claveInput) {
                                claveInput.value = numeroEmpleado;
                            }

                            // Actualizar el input de nombre (readonly)
                            const nombreInput = document.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                            if (nombreInput) {
                                nombreInput.value = nombre;
                            }
                            const turnoSelect = document.querySelector(`select.input-oficial-turno[data-numero="${numero}"]`);
                            if (turnoSelect && turno) {
                                turnoSelect.value = turno;
                            }
                            // Habilitar botón eliminar
                            const btnEliminar = document.querySelector(`.btn-eliminar-oficial[data-numero="${numero}"]`);
                            if (btnEliminar) {
                                btnEliminar.disabled = false;
                                btnEliminar.classList.remove('opacity-50', 'cursor-not-allowed');
                            }

                            if (!validarNoOperadorDuplicadoEnModal(true)) {
                                select.value = '';
                                if (claveInput) claveInput.value = '';
                                if (nombreInput) nombreInput.value = '';
                                if (turnoSelect) turnoSelect.value = '';
                                const metrosInput = document.querySelector(`input.input-oficial-metros[data-numero="${numero}"]`);
                                if (metrosInput) metrosInput.value = '';
                                if (btnEliminar) {
                                    btnEliminar.disabled = true;
                                    btnEliminar.classList.add('opacity-50', 'cursor-not-allowed');
                                }
                                validarNoOperadorDuplicadoEnModal(false);
                                return;
                            }
                        } else {
                            // Si se deselecciona, limpiar campos
                            const claveInput = document.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);
                            if (claveInput) {
                                claveInput.value = '';
                            }

                            const nombreInput = document.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                            if (nombreInput) {
                                nombreInput.value = '';
                            }
                            const turnoSelect = document.querySelector(`select.input-oficial-turno[data-numero="${numero}"]`);
                            if (turnoSelect) {
                                turnoSelect.value = '';
                            }
                            // Deshabilitar botón eliminar
                            const btnEliminar = document.querySelector(`.btn-eliminar-oficial[data-numero="${numero}"]`);
                            if (btnEliminar) {
                                btnEliminar.disabled = true;
                                btnEliminar.classList.add('opacity-50', 'cursor-not-allowed');
                            }
                        }

                        validarNoOperadorDuplicadoEnModal(false);
                    }
                });

                document.addEventListener('click', function (e) {
                    const btnEliminar = e.target.closest('.btn-eliminar-oficial');
                    if (!btnEliminar || btnEliminar.disabled) return;

                    e.preventDefault();
                    const numero = btnEliminar.getAttribute('data-numero');
                    const registroId = modalRegistroId ? modalRegistroId.value : null;
                    if (!registroId) return;

                    const ejecutarEliminacion = async () => {
                        try {
                            const response = await fetch('{{ route('urdido.modulo.produccion.urdido.eliminar.oficial') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ registro_id: registroId, numero_oficial: numero })
                            });
                            const data = await response.json();

                            if (!data.success) {
                                const errorMsg = data.error || 'Error al eliminar oficial';
                                mostrarAlertaErrorModal(errorMsg);
                                return;
                            }

                            const containerOficiales = document.getElementById('oficiales-existentes');
                            if (!containerOficiales) return;

                            const selectNombre = containerOficiales.querySelector(`.select-oficial-nombre[data-numero="${numero}"]`);
                            const claveInput = containerOficiales.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);
                            const nombreInput = containerOficiales.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                            const turnoSelect = containerOficiales.querySelector(`select.input-oficial-turno[data-numero="${numero}"]`);
                            const metrosInput = containerOficiales.querySelector(`input.input-oficial-metros[data-numero="${numero}"]`);

                            if (selectNombre) selectNombre.value = '';
                            if (claveInput) claveInput.value = '';
                            if (nombreInput) nombreInput.value = '';
                            if (turnoSelect) turnoSelect.value = '';
                            if (metrosInput) metrosInput.value = '';
                            btnEliminar.disabled = true;
                            btnEliminar.classList.add('opacity-50', 'cursor-not-allowed');

                            const oficialesRestantes = [];
                            for (let i = 1; i <= 3; i++) {
                                if (parseInt(numero) === i) continue;
                                const cl = containerOficiales.querySelector(`input.input-oficial-clave[data-numero="${i}"]`);
                                const nom = containerOficiales.querySelector(`input.input-oficial-nombre[data-numero="${i}"]`);
                                const turno = containerOficiales.querySelector(`select.input-oficial-turno[data-numero="${i}"]`);
                                const met = containerOficiales.querySelector(`input.input-oficial-metros[data-numero="${i}"]`);
                                if (cl && cl.value) {
                                    oficialesRestantes.push({
                                        numero_oficial: i,
                                        cve_empl: cl.value,
                                        nom_empl: nom ? nom.value : '',
                                        turno: turno ? turno.value : null,
                                        metros: met && met.value ? parseFloat(met.value) : null
                                    });
                                }
                            }
                            actualizarOficialesEnTabla(registroId, oficialesRestantes);

                            mostrarToast('success', 'Oficial eliminado', 1500);
                        } catch (err) {
                            console.error(err);
                            mostrarAlertaErrorModal('Error al eliminar el oficial');
                        }
                    };

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: '¿Eliminar oficial?',
                            text: 'Se eliminará este oficial del registro',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Sí, eliminar',
                            cancelButtonText: 'Cancelar'
                        }).then(async (result) => {
                            if (result.isConfirmed) {
                                await ejecutarEliminacion();
                            }
                        });
                    } else if (confirm('¿Eliminar oficial?')) {
                        ejecutarEliminacion();
                    }
                });

                function cerrarModalOficial() {
                    modalOficial.classList.add('hidden');
                    modalOficial.style.display = 'none';
                    const containerOficiales = document.getElementById('oficiales-existentes');
                    if (containerOficiales) {
                        containerOficiales.innerHTML = '';
                    }
                }

                // Función para actualizar oficiales en la tabla sin recargar
                function actualizarOficialesEnTabla(registroId, oficiales, opciones = {}) {
                    const actualizarMetros = opciones.actualizarMetros !== false;
                    const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                    if (!row) return;

                    const oficialTexto = row.querySelector('.oficial-texto');
                    if (!oficialTexto) return;
                    const escapeHtml = (value) => String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');

                    // Actualizar atributo data-oficiales-json
                    const oficialesParaJson = oficiales.map(o => ({
                        numero: o.numero_oficial,
                        nombre: o.nom_empl || null,
                        clave: o.cve_empl || null,
                        metros: o.metros || null,
                        turno: o.turno || null
                    }));
                    oficialTexto.setAttribute('data-oficiales-json', JSON.stringify(oficialesParaJson));

                    // Render compacto: codigos en primer renglon y nombres con turno en renglones inferiores
                    if (oficialesParaJson.length === 0) {
                        oficialTexto.innerHTML = '<div class="text-gray-400 italic">Sin oficiales</div>';
                    } else {
                        const codigos = oficialesParaJson.map(o => (o.clave || '').toString().trim()).filter(Boolean);
                        let html = `<div class="text-gray-800 font-semibold">${escapeHtml(codigos.length ? codigos.join(', ') : '-')}</div>`;
                        oficialesParaJson.forEach(of => {
                            const nombre = (of.nombre || '').toString().trim();
                            if (!nombre) return;
                            const turnoTxt = of.turno !== null && of.turno !== undefined && of.turno !== '' ? of.turno : '-';
                            html += `<div class="text-xs text-gray-600">${escapeHtml(nombre)} <span class="text-amber-600">(T${escapeHtml(turnoTxt)})</span></div>`;
                        });
                        oficialTexto.innerHTML = html;
                    }

                    // Actualizar Metros solo si se indica (al propagar hacia abajo no se actualizan metros)
                    if (actualizarMetros) {
                        const sumaMetros = oficiales.reduce((acc, o) => acc + (parseFloat(o.metros) || 0), 0);
                        const metrosInput = row.querySelector('input[data-field="metros"]');
                        if (metrosInput) {
                            metrosInput.value = sumaMetros > 0 ? sumaMetros : '';
                        }
                    }

                    // Actualizar botón de agregar oficial (siempre habilitado)
                    const btnAgregar = row.querySelector('.btn-agregar-oficial');
                    if (btnAgregar) {
                        btnAgregar.setAttribute('data-cantidad-oficiales', oficiales.length);
                        // Siempre habilitado: con Hora Inicial se puede abrir para editar Metros/Turno (no cambiar oficial)
                        btnAgregar.disabled = oficiales.length >= 3 ? true : false;
                        if (btnAgregar.disabled) {
                            btnAgregar.classList.remove('text-blue-600', 'hover:text-blue-800', 'hover:bg-blue-50');
                            btnAgregar.classList.add('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                        } else {
                            btnAgregar.classList.remove('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                            btnAgregar.classList.add('text-blue-600', 'hover:text-blue-800', 'hover:bg-blue-50');
                        }
                    }
                }

                /**
                 * Propagar oficiales hacia abajo (filas siguientes).
                 * - Si hay Oficial 2 registrado: el segundo oficial pasa a ser Oficial 1 en las filas siguientes,
                 *   el anterior Oficial 1 se elimina. Se detiene al encontrar una fila con H. Inicio.
                 * - Si solo hay Oficial 1: se propagan todos los oficiales con sus mismos números.
                 */
                async function propagarOficialesHaciaAbajo(registroIdActual, oficiales) {
                    const tablaBody = document.getElementById('tabla-produccion-body');
                    if (!tablaBody) return;

                    const todasLasFilas = Array.from(tablaBody.querySelectorAll('tr[data-registro-id]'));
                    const indiceActual = todasLasFilas.findIndex(row => row.getAttribute('data-registro-id') == registroIdActual);

                    if (indiceActual === -1) return;

                    const tieneSegundoOficial = oficiales.some(o => o.numero_oficial === 2);
                    const segundoOficial = tieneSegundoOficial ? oficiales.find(o => o.numero_oficial === 2) : null;

                    for (let i = indiceActual + 1; i < todasLasFilas.length; i++) {
                        const fila = todasLasFilas[i];
                        const registroId = fila.getAttribute('data-registro-id');
                        if (!registroId) continue;

                        const hInicioInput = fila.querySelector('input[data-field="h_inicio"]');
                        const tieneHInicio = hInicioInput && hInicioInput.value && hInicioInput.value.trim() !== '';

                        if (tieneHInicio) {
                            break; // No propagar: esta fila ya tiene H. Inicio
                        }

                        try {
                            if (tieneSegundoOficial && segundoOficial) {
                                // Segundo oficial pasa a primer oficial; eliminar el anterior Oficial 1
                                const resEliminar = await fetch('{{ route('urdido.modulo.produccion.urdido.eliminar.oficial') }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ registro_id: registroId, numero_oficial: 1 })
                                });
                                const elimResult = await resEliminar.json();
                                if (!elimResult.success && elimResult.error && !elimResult.error.includes('Registro no encontrado')) {
                                    continue;
                                }

                                const resGuardar = await fetch('{{ route('urdido.modulo.produccion.urdido.guardar.oficial') }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({
                                        registro_id: registroId,
                                        numero_oficial: 1,
                                        cve_empl: segundoOficial.cve_empl,
                                        nom_empl: segundoOficial.nom_empl,
                                        turno: segundoOficial.turno
                                    })
                                });
                                const guardarResult = await resGuardar.json();
                                if (guardarResult.success) {
                                    actualizarOficialesEnTabla(registroId, [{ ...segundoOficial, numero_oficial: 1 }], { actualizarMetros: false });
                                }
                            } else {
                                // Solo Oficial 1 (o sin segundo): propagar todos los oficiales con sus mismos números
                                let todosGuardados = true;
                                for (const oficial of oficiales) {
                                    const res = await fetch('{{ route('urdido.modulo.produccion.urdido.guardar.oficial') }}', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                        body: JSON.stringify({
                                            registro_id: registroId,
                                            numero_oficial: oficial.numero_oficial,
                                            cve_empl: oficial.cve_empl,
                                            nom_empl: oficial.nom_empl,
                                            turno: oficial.turno
                                        })
                                    });
                                    const result = await res.json();
                                    if (!result.success) todosGuardados = false;
                                }
                                if (todosGuardados) {
                                    actualizarOficialesEnTabla(registroId, oficiales, { actualizarMetros: false });
                                }
                            }
                        } catch (error) {
                            console.error(`Error al propagar oficiales a registro ${registroId}:`, error);
                        }
                    }
                }

                document.addEventListener('click', function (e) {
                    const btnAgregar = e.target.closest('.btn-agregar-oficial');
                    if (!btnAgregar) return;

                    e.preventDefault();
                    if (btnAgregar.disabled) return;
                    const registroId = btnAgregar.getAttribute('data-registro-id');
                    if (registroId) abrirModalOficial(registroId);
                });

                if (btnCerrarModal) {
                    btnCerrarModal.addEventListener('click', cerrarModalOficial);
                }
                if (btnCancelarModal) {
                    btnCancelarModal.addEventListener('click', cerrarModalOficial);
                }

                if (modalOficial) {
                    modalOficial.addEventListener('click', function (e) {
                        if (e.target === modalOficial) {
                            cerrarModalOficial();
                        }
                    });
                }

                // Event listener para el botón guardar oficiales
                const btnGuardarOficiales = document.getElementById('btn-guardar-oficiales');
                if (btnGuardarOficiales) {
                    btnGuardarOficiales.addEventListener('click', async function () {
                        if (!modalRegistroId || !modalRegistroId.value) {
                            alert('Error: No se encontró el registro');
                            return;
                        }

                        const registroId = modalRegistroId.value;
                        const containerOficiales = document.getElementById('oficiales-existentes');
                        if (!containerOficiales) return;

                        // Recopilar datos de las 3 filas
                        const oficiales = [];
                        for (let i = 1; i <= 3; i++) {
                            const claveInput = containerOficiales.querySelector(`input.input-oficial-clave[data-numero="${i}"]`);
                            const nombreInput = containerOficiales.querySelector(`input.input-oficial-nombre[data-numero="${i}"]`);
                            const turnoSelect = containerOficiales.querySelector(`select.input-oficial-turno[data-numero="${i}"]`);
                            const metrosInput = containerOficiales.querySelector(`input.input-oficial-metros[data-numero="${i}"]`);

                            const clave = claveInput ? claveInput.value.trim() : '';
                            const nombre = nombreInput ? nombreInput.value.trim() : '';
                            const turno = turnoSelect ? turnoSelect.value : '';
                            const metros = metrosInput ? metrosInput.value.trim() : '';

                            // Solo agregar si tiene al menos clave o nombre
                            if (clave || nombre) {
                                oficiales.push({
                                    numero_oficial: i,
                                    cve_empl: clave || null,
                                    nom_empl: nombre || null,
                                    turno: turno || null,
                                    metros: metros ? parseFloat(metros) : null
                                });
                            }
                        }

                        if (!validarNoOperadorDuplicadoEnModal(true)) {
                            return;
                        }

                        // Guardar cada oficial
                        try {
                            let guardados = 0;
                            let oficialesGuardados = [];

                            for (const oficial of oficiales) {
                                const data = {
                                    registro_id: registroId,
                                    ...oficial
                                };

                                const response = await fetch('{{ route('urdido.modulo.produccion.urdido.guardar.oficial') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify(data)
                                });

                                const result = await response.json();
                                if (result.success) {
                                    guardados++;
                                    oficialesGuardados.push(oficial);
                                }
                            }

                            if (guardados > 0) {
                                // Actualizar la tabla sin recargar
                                actualizarOficialesEnTabla(registroId, oficialesGuardados);

                                // Cerrar el modal
                                cerrarModalOficial();

                                // Mostrar mensaje de éxito
                                mostrarToast('success', 'Los oficiales han sido guardados correctamente', 2000);

                                // Propagación hacia abajo (excepto si tienen H. Inicio)
                                // Esperar un poco para que el mensaje se muestre antes de propagar
                                setTimeout(() => {
                                    propagarOficialesHaciaAbajo(registroId, oficialesGuardados);
                                }, 500);
                            } else {
                                alert('No se guardaron oficiales. Asegúrate de llenar al menos la clave o nombre.');
                            }
                        } catch (error) {
                            console.error('Error al guardar oficiales:', error);
                            alert('Error al guardar oficiales. Por favor, intenta nuevamente.');
                        }
                    });
                }
            });

            /**
             * Validar que una fila tenga todos los campos requeridos.
             * @param {Element} row - Fila DOM
             * @returns {valido: boolean, camposFaltantes: string[]}
             */
            function validarCamposFila(row) {
                const camposFaltantes = [];

                const fechaInput = row.querySelector('input.input-fecha');
                if (!fechaInput || !fechaInput.value) camposFaltantes.push('Fecha');

                const oficialTexto = row.querySelector('.oficial-texto');
                const textoOficial = oficialTexto ? oficialTexto.textContent.trim() : '';
                if (!textoOficial || textoOficial === 'Sin oficiales') camposFaltantes.push('Oficial');

                const hInicioInput = row.querySelector('input[data-field="h_inicio"]');
                if (!hInicioInput || !hInicioInput.value) camposFaltantes.push('H. Inicio');

                const hFinInput = row.querySelector('input[data-field="h_fin"]');
                if (!hFinInput || !hFinInput.value) camposFaltantes.push('H. Fin');

                const noJulioSelect = row.querySelector('select[data-field="no_julio"]');
                if (!noJulioSelect || !noJulioSelect.value) camposFaltantes.push('No. Julio');

                const kgBrutoInput = row.querySelector('input[data-field="kg_bruto"]');
                if (!kgBrutoInput || !kgBrutoInput.value || kgBrutoInput.value.trim() === '') camposFaltantes.push('Kg. Bruto');

                const taraInput = row.querySelector('input[data-field="tara"]');
                if (!taraInput || !taraInput.value || taraInput.value.trim() === '') camposFaltantes.push('Tara');

                const kgNetoInput = row.querySelector('input[data-field="kg_neto"]');
                if (kgNetoInput && kgNetoInput.value) {
                    const kgNetoValue = parseFloat(kgNetoInput.value);
                    if (!isNaN(kgNetoValue) && kgNetoValue < 0) camposFaltantes.push('Kg. Neto (no puede ser negativo)');
                }

                const metrosInput = row.querySelector('input[data-field="metros"]');
                if (!metrosInput || !metrosInput.value || metrosInput.value.trim() === '') camposFaltantes.push('Metros');

                @if($isKarlMayer ?? false)
                const vueltasInput = row.querySelector('input[data-field="vueltas"]');
                if (!vueltasInput || !vueltasInput.value || vueltasInput.value.trim() === '') camposFaltantes.push('Vueltas');

                const diametroInput = row.querySelector('input[data-field="diametro"]');
                if (!diametroInput || !diametroInput.value || diametroInput.value.trim() === '') camposFaltantes.push('Diámetro');
                @endif

                return { valido: camposFaltantes.length === 0, camposFaltantes };
            }

            function validarFilaParaFinalizar(row) {
                return validarCamposFila(row);
            }

            function validarRegistrosCompletos() {
                limpiarErroresVisuales();

                const tablaBody = document.getElementById('tabla-produccion-body');
                if (!tablaBody) return { valido: false, mensaje: 'No se encontró la tabla de producción' };

                const filas = tablaBody.querySelectorAll('tr[data-registro-id]');
                const registrosIncompletos = [];
                let hayErrores = false;

                // Mapa de campo a selector para marcar errores visuales
                const selectorMap = {
                    'Fecha': 'input.input-fecha',
                    'Oficial': '.oficial-texto',
                    'H. Inicio': 'input[data-field="h_inicio"]',
                    'H. Fin': 'input[data-field="h_fin"]',
                    'No. Julio': 'select[data-field="no_julio"]',
                    'Kg. Bruto': 'input[data-field="kg_bruto"]',
                    'Tara': 'input[data-field="tara"]',
                    'Kg. Neto (no puede ser negativo)': 'input[data-field="kg_neto"]',
                    'Metros': 'input[data-field="metros"]',
                    @if($isKarlMayer ?? false)
                    'Vueltas': 'input[data-field="vueltas"]',
                    'Diámetro': 'input[data-field="diametro"]',
                    @endif
                };

                filas.forEach((fila, index) => {
                    const resultado = validarCamposFila(fila);

                    if (!resultado.valido) {
                        hayErrores = true;
                        resultado.camposFaltantes.forEach(campo => {
                            const selector = selectorMap[campo];
                            if (selector) {
                                const el = fila.querySelector(selector);
                                marcarCampoError(el, true);
                            }
                        });
                        registrosIncompletos.push({ fila: index + 1, campos: resultado.camposFaltantes });
                    }
                });

                if (registrosIncompletos.length > 0 || hayErrores) {
                    return { valido: false, mensaje: 'Completa todos los registros y corrige los errores' };
                }

                return { valido: true };
            }

            // Función para abrir PDF en nueva pestaña y mostrar diálogo de impresión
            function abrirPDFParaImprimir(url) {
                const printWindow = window.open(url, '_blank');

                if (printWindow) {
                    // Esperar a que el PDF se cargue y luego abrir el diálogo de impresión
                    printWindow.onload = function() {
                        setTimeout(() => {
                            printWindow.print();
                        }, 500);
                    };

                    // Fallback: si onload no funciona, intentar después de un tiempo
                    setTimeout(() => {
                        try {
                            printWindow.print();
                        } catch (e) {
                            console.log('Esperando a que el PDF se cargue...');
                            // Intentar nuevamente después de más tiempo
                            setTimeout(() => {
                                try {
                                    printWindow.print();
                                } catch (e2) {
                                    console.error('No se pudo abrir el diálogo de impresión automáticamente');
                                }
                            }, 1000);
                        }
                    }, 1000);
                }
            }

            window.finalizar = async function () {
                // Validar que todos los registros estén completos
                const validacion = validarRegistrosCompletos();

                if (!validacion.valido) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Registros incompletos',
                            text: validacion.mensaje,
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert(validacion.mensaje);
                    }
                    return;
                }

                // Si todos los registros están completos, proceder con la finalización
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '¿Finalizar registro?',
                        text: 'Esta acción marcará el registro como finalizado y generará el PDF',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, finalizar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#2563eb',
                        cancelButtonColor: '#6b7280'
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            @if(isset($orden) && $orden)
                                const ordenId = {{ $orden->Id }};

                                try {
                                    // Mostrar loading
                                    Swal.fire({
                                        title: 'Finalizando...',
                                        text: 'Por favor espera',
                                        allowOutsideClick: false,
                                        didOpen: () => {
                                            Swal.showLoading();
                                        }
                                    });

                                    // Llamar al endpoint para finalizar (cambiar status)
                                    const response = await fetch('{{ route('urdido.modulo.produccion.urdido.finalizar') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({ orden_id: ordenId })
                                    });

                                    const result = await response.json();

                                    if (result.success) {
                                        // Generar PDF automáticamente y abrir para imprimir
                                        const url = '{{ route('urdido.modulo.produccion.urdido.pdf') }}?orden_id=' + ordenId + '&tipo=urdido';
                                        abrirPDFParaImprimir(url);

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Registro finalizado',
                                            text: 'El registro ha sido marcado como finalizado y el PDF se ha generado',
                                            timer: 2000,
                                            showConfirmButton: false,
                                            willClose: () => {
                                                window.location.href = '/produccionProceso';
                                            }
                                        });
                                    } else {
                                        mostrarAlerta('error', 'Error', result.error || 'Error al finalizar el registro');
                                    }
                                } catch (error) {
                                    console.error('Error al finalizar:', error);
                                    mostrarAlerta('error', 'Error', 'Error al finalizar el registro. Por favor, intenta nuevamente.');
                                }
                            @else
                                alert('No hay orden seleccionada');
                            @endif
                        }
                    });
                } else {
                    if (confirm('¿Finalizar registro?')) {
                        @if(isset($orden) && $orden)
                            const ordenId = {{ $orden->Id }};

                            try {
                                const response = await fetch('{{ route('urdido.modulo.produccion.urdido.finalizar') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({ orden_id: ordenId })
                                });

                                const result = await response.json();

                                if (result.success) {
                                    const url = '{{ route('urdido.modulo.produccion.urdido.pdf') }}?orden_id=' + ordenId + '&tipo=urdido';
                                    abrirPDFParaImprimir(url);
                                    alert('Registro finalizado');
                                    window.location.href = '/produccionProceso';
                                } else {
                                    alert('Error: ' + (result.error || 'Error desconocido'));
                                }
                            } catch (error) {
                                console.error('Error al finalizar:', error);
                                alert('Error al finalizar el registro');
                            }
                        @else
                            alert('No hay orden seleccionada');
                        @endif
                    }
                }
            };
})();
</script>
@endsection
