@extends('layouts.app')

@section('page-title', 'Producción de Engomado')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            onclick="window.location.href='{{ isset($orden) && $orden && $orden->Folio ? route('engomado.captura-formula', ['folio' => $orden->Folio]) : route('engomado.captura-formula') }}'"
            title="Agregar fórmula"
            icon="fa-flask"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Agregar fórmula"
            bg="bg-blue-500"
        />
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
    $hasFinalizarPermission = true;
    try {
        $moduloRol = \App\Models\Sistema\SYSRoles::where('modulo', 'Programa Engomado')->first();
        $moduleParam = $moduloRol ? $moduloRol->idrol : 'Programa Engomado';
        $hasFinalizarPermission = function_exists('userCan') ? userCan('registrar', $moduleParam) : true;
    } catch (\Exception $e) {
        $hasFinalizarPermission = true;
    }
@endphp

@section('content')

<style>
    @media (min-width: 768px) {
        .grid-produccion-columnas {
            grid-template-columns: 0.75fr 0.75fr 0.75fr 0.9fr 1.2fr !important;
        }
    }
</style>

    <div class="w-full">
        <!-- Sección superior: Información General -->
        <div class="bg-white p-1">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-1 md:gap-1.5 items-stretch grid-produccion-columnas">
                <!-- Columna 1 -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Folio:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $orden ? $orden->Folio : '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Cuenta/Calibre:</span>
                        <span class="text-sm text-gray-900 flex-1">
                            {{ $orden ? ($orden->Cuenta ?? '-') : '-' }}
                            @if($orden && isset($orden->Calibre) && $orden->Calibre !== null)
                                / {{ $orden->Calibre }}
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Urdido:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $urdido ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Destino:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $destino ?? '-' }}</span>
                    </div>
                </div>

                <!-- Columna 2 -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[90px]">Engomado:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $orden ? ($orden->MaquinaEng ?? '-') : '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[90px]">Tipo:</span>
                        @if($orden && $orden->RizoPie)
                            @php
                                $tipo = strtoupper(trim($orden->RizoPie));
                                $isRizo = $tipo === 'RIZO';
                                $isPie = $tipo === 'PIE';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $isRizo ? 'bg-rose-100 text-rose-700' : ($isPie ? 'bg-teal-100 text-teal-700' : 'bg-gray-200 text-gray-800') }}">
                                {{ $orden->RizoPie }}
                            </span>
                        @else
                            <span class="text-sm text-gray-500 italic">-</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[90px]">Núcleo:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $nucleo ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[90px]">No. De Telas:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $noTelas ?? '-' }}</span>
                    </div>
                </div>

                <!-- Columna 3 -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Mts. De Telas:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $metrajeTelas ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Cuendeados Mín.:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $cuendeadosMin ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Proveedor:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $loteProveedor ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap min-w-[100px]">Ancho Balonas:</span>
                        <span class="text-sm text-gray-900 flex-1">{{ $anchoBalonas ?? '-' }}</span>
                    </div>
                </div>

                <!-- Columna 4 - Merma -->
                <div class="flex flex-col space-y-1">
                    <div class="flex items-center gap-1">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap shrink-0">Merma con Goma:</span>
                        <input
                            type="number"
                            step="0.01"
                            data-field="merma_con_goma"
                            class="w-20 border border-gray-300 rounded px-2 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            value="{{ $mermaGoma ?? '' }}"
                        >
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap shrink-0">Merma sin Goma:</span>
                        <input
                            type="number"
                            step="0.01"
                            data-field="merma_sin_goma"
                            class="w-20 border border-gray-300 rounded px-2 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            value="{{ $merma ?? '' }}"
                        >
                    </div>
                </div>

                <!-- Columna 5 - Observaciones -->
                <div class="flex flex-col space-y-2">
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap">Observaciones:</span>
                        <div class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-sm overflow-y-auto h-[50px]">
                            <span class="text-gray-500 whitespace-pre-wrap leading-tight">{{ $observaciones ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección inferior: Tabla de Producción -->
        <div class="bg-white shadow-md overflow-hidden">
            <div class="overflow-x-auto max-h-[62vh] overflow-y-auto" style="min-width: 100%;">
                <table class="divide-y divide-gray-200 text-sm" style="min-width: max-content; width: 100%;">
                    <thead class="bg-blue-500 text-white sticky top-0 z-20">
                        <tr>
                            @if($hasFinalizarPermission)
                            <th class="py-1"></th>
                            @endif
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th class="py-1 hidden lg:table-cell"></th>
                            <th class="py-1 hidden lg:table-cell"></th>
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th class="py-1"></th>
                            <th colspan="6" class="py-1 text-center font-semibold bg-blue-700 text-xs md:text-sm">Temp</th>
                        </tr>
                        <tr>
                            @if($hasFinalizarPermission)
                            <th class="py-2 px-0 text-center font-semibold text-[9px] md:text-[10px]" style="width: 28px; min-width: 28px; max-width: 28px;">Fin</th>
                            @endif
                            <th class="py-2 px-1 text-center font-semibold bg-blue-500 text-white text-xs md:text-sm" style="width: 3rem; min-width: 3rem; max-width: 3rem;">Fecha</th>
                            <th class="py-2 px-1 text-left font-semibold text-xs md:text-sm" style="width: 5rem; min-width: 5rem; max-width: 5rem;">Oficial</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-16 max-w-[50px]">Turno</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm hidden lg:table-cell w-32 max-w-[110px]">H. Inicio</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm hidden lg:table-cell w-32 max-w-[110px]">H. Final</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-24 max-w-[90px]">Julio</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Kg. Bruto</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Tara</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Kg. Neto</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Metros</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Sol. Can.</th>
                            <th class="py-2 px-1 text-center font-semibold bg-blue-700 text-[10px] md:text-xs w-10 md:w-9 lg:w-10 h-10 md:h-12 relative align-bottom">
                                <span style="position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%) rotate(-60deg); transform-origin: left bottom; white-space: nowrap; font-weight: bold;">Canoa 1</span>
                            </th>
                            <th class="py-2 px-1 text-center font-semibold bg-blue-700 text-[10px] md:text-xs w-10 md:w-9 lg:w-10 h-10 md:h-12 relative align-bottom">
                                <span style="position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%) rotate(-60deg); transform-origin: left bottom; white-space: nowrap; font-weight: bold;">Canoa 2</span>
                            </th>
                            <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-xs hidden">Tambor</th>
                            <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm hidden">Humedad</th>
                            <th class="py-2 px-1 text-center font-semibold bg-blue-500 text-white text-xs md:text-sm w-24 max-w-[75px]">Roturas</th>
                            <th class="py-2 px-1 text-center font-semibold bg-blue-500 text-white text-xs md:text-sm w-32 max-w-[120px]">Ubicación</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-produccion-body" class="bg-white divide-y divide-gray-200">
                        @php
                            // El total de registros se basa en No. De Telas
                            $totalRegistros = isset($totalRegistros) ? (int)$totalRegistros : 0;

                            // Si no hay totalRegistros, usar NoTelas de la orden
                            if ($totalRegistros == 0 && isset($orden) && isset($orden->NoTelas)) {
                                $totalRegistros = (int) ($orden->NoTelas ?? 0);
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
                                    $kgBruto = $registro ? ($registro->KgBruto ?? '') : '';
                                    $tara = $registro && $registro->Tara !== null ? number_format((float)$registro->Tara, 1, '.', '') : '';
                                    $kgNeto = $registro ? ($registro->KgNeto ?? '') : '';
                                    $solidos = $registro ? ($registro->Solidos ?? '') : '';

                                    // Metros = suma de Metros1 + Metros2 + Metros3 del registro
                                    $metros = '';
                                    if ($registro) {
                                        $m1 = isset($registro->Metros1) && $registro->Metros1 !== null ? (float)$registro->Metros1 : 0;
                                        $m2 = isset($registro->Metros2) && $registro->Metros2 !== null ? (float)$registro->Metros2 : 0;
                                        $m3 = isset($registro->Metros3) && $registro->Metros3 !== null ? (float)$registro->Metros3 : 0;
                                        $sumaMetros = $m1 + $m2 + $m3;
                                        $metros = $sumaMetros > 0 ? $sumaMetros : '';
                                    }

                                    $tempCanoa1 = $registro && $registro->Canoa1 !== null ? (int)$registro->Canoa1 : 0;
                                    $tempCanoa2 = $registro && $registro->Canoa2 !== null ? (int)$registro->Canoa2 : 0;
                                    // $tambor = $registro && $registro->Tambor !== null ? (int)$registro->Tambor : 0; // Columna no existe en la tabla
                                    $tambor = 0; // Valor por defecto ya que la columna no existe
                                    $humedad = $registro ? ($registro->Humedad ?? '') : '';
                                    $ubicacion = $registro ? ($registro->Ubicacion ?? '') : '';
                                    $roturas = $registro ? ($registro->Roturas ?? '') : '';
                                    $registroId = $registro ? $registro->Id : null;
                                    $listo = $registro ? (int)($registro->Finalizar ?? 0) : 0;
                                    $ax = $registro ? (int)($registro->AX ?? 0) : 0;

                                    $oficiales = [];
                                    $primerOficialNombre = '';
                                    $primerOficialNombreCompleto = '';
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
                                            if ($i === 1 && !$primerOficialNombre) {
                                                $primerOficialNombreCompleto = $nomEmpl;
                                                $primerOficialNombre = mb_strlen($nomEmpl) > 12 ? mb_substr($nomEmpl, 0, 12) . '...' : $nomEmpl;
                                            }
                                        }
                                    }
                                    $tieneOficiales = count($oficiales) > 0;
                                    $textoOficiales = $primerOficialNombre ?: '';
                                    $turnoInicial = $tieneOficiales && isset($oficiales[0]['turno']) ? (string)$oficiales[0]['turno'] : '';
                                @endphp

                                <tr class="hover:bg-gray-50" data-registro-id="{{ $registroId }}">
                                    {{-- Finalizar (checkbox con permiso registrar, bloqueado si AX=1) --}}
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
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap bg-white" style="width: 3rem; min-width: 3rem; max-width: 3rem;">
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
                                    <td class="px-1 py-1 md:py-1.5 text-left whitespace-nowrap" style="width: 5rem; min-width: 5rem; max-width: 5rem;">
                                        <div class="flex items-center justify-start gap-1">
                                            <span
                                                class="oficial-texto w-full text-xs text-gray-900 px-1 py-0.5 truncate text-left {{ !$tieneOficiales ? 'text-gray-400 italic' : '' }}"
                                                data-registro-id="{{ $registroId }}"
                                                data-oficiales-json="{{ $tieneOficiales ? json_encode($oficiales) : '[]' }}"
                                                title="{{ $primerOficialNombreCompleto ?? 'Sin oficiales' }}"
                                            >
                                                {{ $textoOficiales ?: 'Sin oficiales' }}
                                            </span>
                                            @php $cantidadOficiales = count($oficiales); @endphp
                                            <button
                                                type="button"
                                                class="btn-agregar-oficial flex-shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors"
                                                data-registro-id="{{ $registroId }}"
                                                data-cantidad-oficiales="{{ $cantidadOficiales }}"
                                                title="Agregar oficial"
                                            >
                                                <i class="fa-solid fa-plus-circle text-sm"></i>
                                            </button>
                                        </div>
                                    </td>

                                    {{-- Turno --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-8 max-w-[50px]">
                                        <select
                                            data-field="turno"
                                            class="w-full border border-gray-300 rounded px-1 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            min="1"
                                            max="3"
                                        >
                                            <option value="">Seleccionar...</option>
                                            <option value="1" {{ $turnoInicial == '1' ? 'selected' : '' }}>1</option>
                                            <option value="2" {{ $turnoInicial == '2' ? 'selected' : '' }}>2</option>
                                            <option value="3" {{ $turnoInicial == '3' ? 'selected' : '' }}>3</option>
                                        </select>
                                    </td>

                                    {{-- H. INICIO --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell">
                                        <div class="flex items-center justify-center gap-2 md:gap-3">
                                            <input
                                                type="time"
                                                data-field="h_inicio"
                                                class="flex-1 border border-gray-300 rounded px-1 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-20 md:w-24"
                                                value="{{ $horaInicio }}"
                                            >
                                            <i
                                                class="fa-solid fa-clock text-gray-400 text-2xl md:text-3xl cursor-pointer hover:text-blue-500 hover:bg-blue-50 set-current-time flex-shrink-0 inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full transition-colors p-2"
                                                data-time-target="h_inicio"
                                                title="Establecer hora actual"
                                            ></i>
                                        </div>
                                    </td>

                                    {{-- H. FIN --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell">
                                        <div class="flex items-center justify-center gap-2 md:gap-3">
                                            <input
                                                type="time"
                                                data-field="h_fin"
                                                class="flex-1 border border-gray-300 rounded px-1 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-20 md:w-24"
                                                value="{{ $horaFin }}"
                                            >
                                            <i
                                                class="fa-solid fa-clock text-gray-400 text-2xl md:text-3xl cursor-pointer hover:text-blue-500 hover:bg-blue-50 set-current-time flex-shrink-0 inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full transition-colors p-2"
                                                data-time-target="h_fin"
                                                title="Establecer hora actual"
                                            ></i>
                                        </div>
                                    </td>

                                    {{-- No. Julio --}}
                                    <td class="px-0.5 py-1 md:py-1.5 text-center whitespace-nowrap w-16 max-w-[60px]">
                                        <select
                                            data-field="no_julio"
                                            class="w-full border border-gray-300 rounded px-0.5 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500 select-julio"
                                            data-valor-inicial="{{ $noJulio }}"
                                            data-registro-id="{{ $registroId }}"
                                        >
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </td>

                                    {{-- Kg Bruto --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="kg_bruto"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $kgBruto }}"
                                        >
                                    </td>

                                    {{-- Tara --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            step="0.01"
                                            disabled
                                            data-field="tara"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $tara }}"
                                        >
                                    </td>

                                    {{-- Kg Neto --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="kg_neto"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-lg text-center bg-gray-50 text-gray-600 cursor-not-allowed"
                                            value="{{ $kgNeto }}"
                                            readonly
                                        >
                                    </td>

                                    {{-- Metros (valor de Metraje Telas de la orden) --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            disabled
                                            data-field="metros"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $metros }}"
                                        >
                                    </td>

                                    {{-- Sólidos --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="solidos"
                                            data-valor-inicial="{{ $solidos }}"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $solidos }}"
                                        >
                                    </td>

                                    {{-- Temperatura Canoa 1 --}}
                                    <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap bg-blue-50 w-10 md:w-9 lg:w-10">
                                        <div class="flex items-center justify-center relative">
                                            <button
                                                type="button"
                                                class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors"
                                                onclick="toggleQuantityEdit(this, 'temp_canoa1')"
                                            >
                                                <span class="quantity-display font-semibold" data-field="temp_canoa1">
                                                    {{ $tempCanoa1 }}
                                                </span>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                                <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                            <span
                                                                class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $tempCanoa1 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
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

                                    {{-- Temperatura Canoa 2 --}}
                                    <td class="px-1 md:px-1 py-1 md:py-1.5 text-center whitespace-nowrap bg-blue-50 w-10 md:w-9 lg:w-10">
                                        <div class="flex items-center justify-center relative">
                                            <button
                                                type="button"
                                                class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors"
                                                onclick="toggleQuantityEdit(this, 'temp_canoa2')"
                                            >
                                                <span class="quantity-display font-semibold" data-field="temp_canoa2">
                                                    {{ $tempCanoa2 }}
                                                </span>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                                <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                            <span
                                                                class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $tempCanoa2 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
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

                                    {{-- Tambor (oculto) --}}
                                    <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap bg-blue-50 hidden">
                                        <div class="flex items-center justify-center relative">
                                            <button
                                                type="button"
                                                class="edit-quantity-btn bg-gray-100 hover:bg-blue-700 text-black px-2 py-2 rounded text-sm transition-colors"
                                                onclick="toggleQuantityEdit(this, 'tambor')"
                                            >
                                                <span class="quantity-display font-semibold" data-field="tambor">
                                                    {{ $tambor }}
                                                </span>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2">
                                                <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($numIndex = 0; $numIndex <= 100; $numIndex++)
                                                            <span
                                                                class="number-option inline-block w-7 h-7 text-center leading-7 text-xs cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $numIndex == $tambor ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}"
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

                                    {{-- Humedad (oculto) --}}
                                    <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap hidden">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="humedad"
                                            class="w-full border border-gray-300 rounded px-2 md:px-3 py-0.5 md:py-1 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $humedad }}"
                                        >
                                    </td>

                                    {{-- Roturas --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-24 max-w-[75px]">
                                        <input
                                            type="number"
                                            step="1"
                                            min="0"
                                            data-field="roturas"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-lg text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $roturas }}"
                                        >
                                    </td>

                                    {{-- Ubicación --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-32 max-w-[120px]">
                                        <select
                                            data-field="ubicacion"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="">-</option>
                                            @foreach($ubicaciones ?? [] as $ubicacionItem)
                                                <option value="{{ $ubicacionItem->Codigo }}" {{ $ubicacion === $ubicacionItem->Codigo ? 'selected' : '' }}>
                                                    {{ $ubicacionItem->Codigo }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endfor
                        @else
                            <tr>
                                <td colspan="{{ $hasFinalizarPermission ? 17 : 16 }}" class="px-2 py-4 text-center text-gray-500 italic">
                                    No hay registros para generar.
                                    @if(isset($orden) && isset($orden->NoTelas))
                                        <br>Total requerido (No. De Telas): {{ $orden->NoTelas }}
                                    @else
                                        No hay número de telas definido para esta orden.
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Crear Formulación -->
    <div id="createModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <h3 class="text-xl font-semibold">Nueva Formulación de Engomado</h3>
                <button onclick="cerrarModalFormulacion()" class="text-white hover:text-gray-200 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form action="{{ route('eng-formulacion.store') }}" method="POST" class="p-6">
                @csrf

                <!-- Sección 1: Datos principales (3 columnas) -->
                <div class="mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Folio (Programa Engomado) <span class="text-red-600">*</span></label>
                            <select name="FolioProg" id="create_folio_prog" required onchange="cargarDatosPrograma(this)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                <option value="">-- Seleccione un Folio --</option>
                                @foreach($foliosPrograma as $prog)
                                    @if(!isset($orden) || !$orden || $orden->Folio === $prog->Folio)
                                        <option value="{{ $prog->Folio }}"
                                                data-cuenta="{{ $prog->Cuenta }}"
                                                data-calibre="{{ $prog->Calibre }}"
                                                data-tipo="{{ $prog->RizoPie }}"
                                                data-formula="{{ $prog->BomFormula }}"
                                                {{ isset($orden) && $orden && $orden->Folio === $prog->Folio ? 'selected' : '' }}>
                                            {{ $prog->Folio }} - {{ $prog->Cuenta }}
                                        </option>
                                    @endif
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
                            <label class="block text-xs font-medium text-gray-700 mb-1">Olla</label>
                            <select name="Olla" id="create_olla" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                <option value="">Seleccione...</option>
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
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
                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-purple-700 mb-2 pb-2 border-b border-purple-200">Datos de Captura</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition shadow-lg hover:shadow-xl">
                        <i class="fa-solid fa-save mr-1"></i>Crear Formulación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para agregar oficial -->
    <div
        id="modal-oficial"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center"
        style="display: none;"
    >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 max-h-[65vh] overflow-y-auto">
            <div class="px-4 md:px-6 py-3 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="text-base md:text-lg font-semibold text-gray-900">Oficiales</h3>
                <button type="button" id="btn-cerrar-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fa-solid fa-times text-2xl"></i>
                </button>
            </div>

            <div id="modal-oficiales-lista" class="p-4 -mt-2 md:p-6">
                <input type="hidden" id="modal-registro-id" name="registro_id">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700 border border-gray-300">No Operador</th>
                                <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700 border border-gray-300 hidden">Nombre</th>
                                <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700 border border-gray-300">Turno</th>
                                <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700 border border-gray-300">Metros</th>
                                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 border border-gray-300 w-20">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody id="oficiales-existentes" class="bg-white">
                            <!-- Se rellenan por JS -->
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

            const debounceTimeouts = new Map();
            const campoMap = {
                solidos: 'Solidos',
                temp_canoa1: 'Canoa1',
                temp_canoa2: 'Canoa2',
                humedad: 'Humedad',
                ubicacion: 'Ubicacion',
                roturas: 'Roturas'
            };

            const createModal = document.getElementById('createModal');
            const createFolioSelect = document.getElementById('create_folio_prog');

            window.abrirModalFormulacion = function () {
                if (!createModal) return;
                createModal.classList.remove('hidden');
                createModal.style.display = 'flex';

                if (createFolioSelect && createFolioSelect.value) {
                    window.cargarDatosPrograma(createFolioSelect);
                }
            };

            window.cerrarModalFormulacion = function () {
                if (!createModal) return;
                createModal.classList.add('hidden');
                createModal.style.display = 'none';
            };

            window.cargarDatosPrograma = function (select) {
                if (!select) return;

                const option = select.options[select.selectedIndex];
                const setValue = (id, value) => {
                    const input = document.getElementById(id);
                    if (input) input.value = value;
                };

                if (!option || !option.value) {
                    setValue('create_cuenta', '');
                    setValue('create_calibre', '');
                    setValue('create_tipo', '');
                    setValue('create_formula', '');
                    return;
                }

                const cuenta = option.getAttribute('data-cuenta') || '';
                const calibre = option.getAttribute('data-calibre') || '';
                const tipo = option.getAttribute('data-tipo') || '';
                const formula = option.getAttribute('data-formula') || '';

                setValue('create_cuenta', cuenta);
                setValue('create_calibre', calibre);
                setValue('create_tipo', tipo);
                setValue('create_formula', formula);

                @if(Auth::check())
                    setValue('create_nom_empl', '{{ Auth::user()->nombre ?? "" }}');
                    setValue('create_cve_empl', '{{ Auth::user()->numero ?? "" }}');
                @endif
            };

            // ===== Helpers UI =====
            function calcularNeto(row) {
                const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
                const taraInput  = row.querySelector('input[data-field="tara"]');
                const netoInput  = row.querySelector('input[data-field="kg_neto"]');

                if (!brutoInput || !taraInput || !netoInput) return;

                const bruto = parseFloat(brutoInput.value) || 0;
                const tara  = parseFloat(taraInput.value) || 0;
                const neto  = bruto - tara;

                // Si el neto es negativo, marcarlo en rojo, si no, quitar el error
                if (neto < 0) {
                    netoInput.value = neto.toFixed(2);
                    marcarCampoError(netoInput, true);
                } else {
                    netoInput.value = neto.toFixed(2);
                    marcarCampoError(netoInput, false);
                }
            }

            window.toggleQuantityEdit = function (element) {
                const cell = element.closest('td');
                const editContainer = cell.querySelector('.quantity-edit-container');
                const editBtn = cell.querySelector('.edit-quantity-btn');
                const quantityDisplay = cell.querySelector('.quantity-display');

                closeAllQuantityEditors();

                if (!editContainer || !editBtn || !quantityDisplay) return;

                const wasHidden = editContainer.classList.contains('hidden');

                // Asegurar que el valor siempre esté visible en el display
                let currentValue = quantityDisplay.textContent.trim();
                if (!currentValue || currentValue === '') {
                    quantityDisplay.textContent = '0';
                    currentValue = '0';
                }

                editContainer.classList.toggle('hidden');
                editBtn.classList.toggle('hidden');

                // Asegurar que el botón siempre sea visible cuando el editor está oculto
                if (!wasHidden) {
                    // Si se está cerrando el editor, mostrar el botón
                    editBtn.classList.remove('hidden');
                    editBtn.style.display = '';
                } else {
                    // Si se está abriendo el editor, ocultar el botón
                    editBtn.classList.add('hidden');
                }

                if (wasHidden && quantityDisplay) {
                    const fieldName = quantityDisplay.getAttribute('data-field');

                    // Si es Canoa1 o Canoa2 y el valor es 0 o está vacío, mostrar 80 como seleccionado en el scroll
                    let scrollValue = currentValue;
                    if ((fieldName === 'temp_canoa1' || fieldName === 'temp_canoa2') && (currentValue === '0' || currentValue === '')) {
                        scrollValue = '80'; // Mostrar 80 seleccionado en el scroll, pero mantener el display en 0
                    }

                    const allOptions = editContainer.querySelectorAll('.number-option');
                    let selectedOption = null;

                    allOptions.forEach(o => {
                        const value = o.getAttribute('data-value');
                        const isCurrent = String(value) === String(scrollValue);

                        if (isCurrent) {
                            selectedOption = o;
                        }

                        o.classList.remove('bg-blue-500', 'text-white', 'bg-gray-100', 'text-gray-700');
                        if (isCurrent) {
                            o.classList.add('bg-blue-500', 'text-white');
                        } else {
                            o.classList.add('bg-gray-100', 'text-gray-700');
                        }
                    });

                    // Hacer scroll al valor seleccionado (80 si es 0, o el valor actual) para que sea visible
                    if (selectedOption) {
                        selectedOption.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
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

            function verificarOficialSeleccionado(registroId) {
                const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                if (!row) return false;
                const oficialTexto = row.querySelector('.oficial-texto');
                if (!oficialTexto) return false;
                const texto = oficialTexto.textContent.trim();
                return texto && texto !== 'Sin oficiales';
            }

            function mostrarAlertaOficialRequerido() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Oficial requerido',
                        text: 'Debes seleccionar un oficial antes de actualizar este campo',
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    alert('Debes seleccionar un oficial antes de actualizar este campo');
                }
            }

            // ===== Llamadas al backend =====
            async function actualizarFecha(registroId, fecha) {
                if (!verificarFilaNoFinalizada(registroId)) return;
                if (!verificarOficialSeleccionado(registroId)) {
                    mostrarAlertaOficialRequerido();
                    return;
                }

                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.fecha') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ registro_id: registroId, fecha })
                    });

                    const result = await response.json();

                    if (result.success && typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Fecha actualizada',
                            text: 'La fecha ha sido actualizada correctamente',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                } catch (error) {
                    console.error('Error al actualizar fecha:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al actualizar la fecha. Por favor, intenta nuevamente.'
                        });
                    }
                }
            }

            async function actualizarTurnoOficial(registroId, numeroOficial, turno) {
                if (!verificarFilaNoFinalizada(registroId)) return;
                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.turno.oficial') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            registro_id: registroId,
                            numero_oficial: numeroOficial,
                            turno
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Turno actualizado',
                                text: 'El turno ha sido actualizado correctamente',
                                timer: 2000,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end'
                            });
                        }

                        const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                        if (row) {
                            const oficialTexto = row.querySelector('.oficial-texto');
                            if (oficialTexto) {
                                try {
                                    const oficialesJson = oficialTexto.getAttribute('data-oficiales-json');
                                    if (oficialesJson) {
                                        const oficiales = JSON.parse(oficialesJson);
                                        const oficial = oficiales.find(o => parseInt(o.numero) === parseInt(numeroOficial));
                                        if (oficial) {
                                            oficial.turno = turno;
                                            oficialTexto.setAttribute('data-oficiales-json', JSON.stringify(oficiales));
                                        }
                                    }
                                } catch (e) { console.error(e); }
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error al actualizar turno del oficial:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al actualizar el turno. Por favor, intenta nuevamente.'
                        });
                    }
                }
            }

            async function actualizarKgBruto(registroId, kgBruto) {
                if (!verificarFilaNoFinalizada(registroId)) return;
                if (!verificarOficialSeleccionado(registroId)) {
                    mostrarAlertaOficialRequerido();
                    return;
                }

                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.kg.bruto') }}', {
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
                            if (netoInput) {
                                if (result.data.kg_neto !== undefined && result.data.kg_neto !== null) {
                                    netoInput.value = parseFloat(result.data.kg_neto).toFixed(2);
                                } else {
                                    netoInput.value = '';
                                }
                            }
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

            async function actualizarJulioTara(registroId, noJulio, tara, kgNetoCalculado) {
                if (!verificarFilaNoFinalizada(registroId)) return;
                if (!verificarOficialSeleccionado(registroId)) {
                    mostrarAlertaOficialRequerido();
                    return;
                }

                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.julio.tara') }}', {
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
                        if (row) {
                            const netoInput = row.querySelector('input[data-field="kg_neto"]');
                            if (netoInput) {
                                const valorNeto = result.data && result.data.kg_neto !== undefined
                                    ? result.data.kg_neto
                                    : kgNetoCalculado;

                                if (valorNeto !== null && valorNeto !== undefined) {
                                    netoInput.value = parseFloat(valorNeto).toFixed(2);
                                } else {
                                    netoInput.value = '';
                                }
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error al actualizar NoJulio y Tara:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al actualizar No. Julio y Tara. Por favor, intenta nuevamente.'
                        });
                    }
                }
            }

            async function actualizarHora(registroId, campo, valor) {
                if (!verificarFilaNoFinalizada(registroId)) return;
                if (!verificarOficialSeleccionado(registroId)) {
                    mostrarAlertaOficialRequerido();
                    return;
                }

                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.horas') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            registro_id: registroId,
                            campo,
                            valor
                        })
                    });

                    const result = await response.json();

                    if (result.success && typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Hora actualizada',
                            text: result.message || 'La hora ha sido actualizada correctamente',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                } catch (error) {
                    console.error('Error al actualizar hora:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al actualizar la hora. Por favor, intenta nuevamente.'
                        });
                    }
                }
            }

            async function actualizarCampoProduccion(registroId, campo, valor) {
                if (!verificarFilaNoFinalizada(registroId)) return;
                if (!verificarOficialSeleccionado(registroId)) {
                    mostrarAlertaOficialRequerido();
                    return;
                }

                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.campos.produccion') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            registro_id: registroId,
                            campo,
                            valor: valor !== null && valor !== ''
                                ? (campo === 'Ubicacion' ? valor : (campo === 'Roturas' ? parseInt(valor) : parseFloat(valor)))
                                : null
                        })
                    });

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Error al actualizar campo');
                    }

                    const result = await response.json();

                    if (result.success && typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Actualizado',
                            text: result.message || 'Campo actualizado correctamente',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        throw new Error(result.error || 'Error al actualizar campo');
                    }
                } catch (error) {
                    console.error('Error al actualizar campo de producción:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Error al actualizar campo. Por favor, intenta nuevamente.'
                        });
                    }
                }
            }

            async function cargarCatalogosJulios() {
                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.catalogos.julios') }}');
                    const result = await response.json();

                    if (!(result.success && result.data)) {
                        console.error('Error al cargar catálogo de julios:', result.error || 'Error desconocido');
                        return;
                    }

                    // Obtener todos los valores de NoJulio ya usados en otros registros
                    const juliosUsados = new Set();
                    const selectJulios = document.querySelectorAll('.select-julio');
                    selectJulios.forEach(select => {
                        const valorInicial = select.getAttribute('data-valor-inicial');
                        if (valorInicial && valorInicial.trim() !== '') {
                            juliosUsados.add(String(valorInicial).trim());
                        }
                    });

                    const catalogosJulios = result.data;

                    selectJulios.forEach(select => {
                        const valorInicial = select.getAttribute('data-valor-inicial');
                        const registroIdActual = select.getAttribute('data-registro-id');

                        while (select.options.length > 1) {
                            select.remove(1);
                        }

                        catalogosJulios.forEach(item => {
                            const julioValue = String(item.julio).trim();
                            const esValorInicial = valorInicial && String(valorInicial).trim() === julioValue;

                            // Solo mostrar si no está usado en otros registros O es el valor inicial de este registro
                            if (!juliosUsados.has(julioValue) || esValorInicial) {
                                const option = document.createElement('option');
                                option.value = item.julio;
                                option.setAttribute('data-tara', item.tara || '0');
                                option.textContent = item.julio;

                                if (esValorInicial) {
                                    option.selected = true;
                                }

                                select.appendChild(option);
                            }
                        });

                        select.setAttribute('data-valor-anterior', valorInicial || '');

                        if (valorInicial && select.value) {
                            const row = select.closest('tr');
                            const taraInput = row ? row.querySelector('input[data-field="tara"]') : null;
                            const selectedOption = select.options[select.selectedIndex];

                            if (taraInput && selectedOption) {
                                const tara = selectedOption.getAttribute('data-tara') || '0';
                                taraInput.value = tara !== '' && tara !== null ? parseFloat(tara).toFixed(1) : '';

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
                } catch (error) {
                    console.error('Error al cargar catálogo de julios:', error);
                }
            }

            // ===== Usuarios Engomado / Modal oficiales =====
            const modalOficial = document.getElementById('modal-oficial');
            const btnCerrarModal = document.getElementById('btn-cerrar-modal');
            const btnCancelarModal = document.getElementById('btn-cancelar-modal');
            const btnGuardarOficiales = document.getElementById('btn-guardar-oficiales');
            const modalRegistroId = document.getElementById('modal-registro-id');
            let usuariosEngomado = [];

            async function cargarUsuariosEngomado() {
                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.usuarios.engomado') }}');
                    const result = await response.json();

                    if (result.success && result.data) {
                        usuariosEngomado = result.data;
                    } else {
                        console.error('Error al cargar usuarios:', result.error);
                        usuariosEngomado = [];
                    }
                } catch (error) {
                    console.error('Error al cargar usuarios de Engomado:', error);
                    usuariosEngomado = [];
                }
            }

            function poblarSelectUsuarios(selectElement, claveSeleccionada, seleccionarPorDefecto = false) {
                if (!selectElement || !usuariosEngomado.length) return;

                while (selectElement.options.length > 1) {
                    selectElement.remove(1);
                }

                let usuarioSeleccionado = null;
                let debeSeleccionar = seleccionarPorDefecto || selectElement.hasAttribute('data-seleccionar-por-defecto');

                usuariosEngomado.forEach(usuario => {
                    const option = document.createElement('option');
                    option.value = usuario.numero_empleado;
                    option.textContent = usuario.nombre;
                    option.setAttribute('data-numero-empleado', usuario.numero_empleado);
                    option.setAttribute('data-nombre', usuario.nombre);
                    option.setAttribute('data-turno', usuario.turno || '');

                    if ((claveSeleccionada && usuario.numero_empleado === claveSeleccionada) || (debeSeleccionar && !usuarioSeleccionado)) {
                        option.selected = true;
                        usuarioSeleccionado = usuario;
                        debeSeleccionar = false;
                    }

                    selectElement.appendChild(option);
                });

                if (usuarioSeleccionado) {
                    const numero = selectElement.getAttribute('data-numero');
                    const nombreInput = document.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                    const claveInput = document.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);
                    if (nombreInput) nombreInput.value = usuarioSeleccionado.nombre;
                    if (claveInput) claveInput.value = usuarioSeleccionado.numero_empleado;
                    if (usuarioSeleccionado.turno) {
                        const turnoSelect = document.querySelector(`select.input-oficial-turno[data-numero="${numero}"]`);
                        if (turnoSelect) turnoSelect.value = usuarioSeleccionado.turno;
                    }
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

                const oficiales = [];
                if (oficialTexto) {
                    try {
                        const json = oficialTexto.getAttribute('data-oficiales-json');
                        if (json) oficiales.push(...JSON.parse(json));
                    } catch (e) { console.error(e); }
                }

                containerOficiales.innerHTML = '';

                for (let i = 1; i <= 3; i++) {
                    const oficial = oficiales.find(o => parseInt(o.numero) === i) || {
                        numero: i,
                        nombre: '',
                        clave: '',
                        metros: '',
                        turno: ''
                    };

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50';
                    tr.innerHTML = `
                        <td class="px-3 py-2 border border-gray-300">
                            <select
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 select-oficial-nombre"
                                data-numero="${i}"
                            >
                                <option value="">Seleccionar empleado...</option>
                            </select>
                            <input type="hidden" class="input-oficial-clave" data-numero="${i}" value="${oficial.clave || ''}">
                        </td>
                        <td class="px-3 py-2 border border-gray-300 hidden">
                            <input type="text" class="w-full border border-gray-300 rounded px-2 py-1 text-sm bg-gray-50 cursor-not-allowed input-oficial-nombre"
                                data-numero="${i}" value="${oficial.nombre || ''}" placeholder="Se selecciona automáticamente" readonly>
                        </td>
                        <td class="px-3 py-2 border border-gray-300">
                            <select class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 input-oficial-turno" data-numero="${i}">
                                <option value="">Seleccionar...</option>
                                <option value="1" ${oficial.turno === '1' ? 'selected' : ''}>1</option>
                                <option value="2" ${oficial.turno === '2' ? 'selected' : ''}>2</option>
                                <option value="3" ${oficial.turno === '3' ? 'selected' : ''}>3</option>
                            </select>
                        </td>
                        <td class="px-3 py-2 border border-gray-300">
                            <input type="number" step="0.01" min="0"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 input-oficial-metros"
                                data-numero="${i}" value="${oficial.metros || ''}" placeholder="0.00">
                        </td>
                        <td class="px-3 py-2 border border-gray-300 text-center">
                            <button type="button" class="btn-eliminar-oficial px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded ${oficial.nombre ? '' : 'opacity-50 cursor-not-allowed'}"
                                data-numero="${i}" title="Eliminar oficial" ${!oficial.nombre ? 'disabled' : ''}>
                                <i class="fa-solid fa-trash text-sm"></i>
                            </button>
                        </td>
                    `;
                    containerOficiales.appendChild(tr);

                    const selectNombre = tr.querySelector('.select-oficial-nombre');
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

                if (usuariosEngomado.length === 0) {
                    await cargarUsuariosEngomado();
                }

                renderizarOficialesExistentes(registroId);

                if (modalRegistroId) modalRegistroId.value = registroId;

                modalOficial.classList.remove('hidden');
                modalOficial.style.display = 'flex';
            }

            function mostrarAlertaErrorModal(mensaje) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Acción no permitida',
                        text: mensaje
                    });
                } else {
                    alert(mensaje);
                }
            }

            function cerrarModalOficial() {
                modalOficial.classList.add('hidden');
                modalOficial.style.display = 'none';
                const containerOficiales = document.getElementById('oficiales-existentes');
                if (containerOficiales) containerOficiales.innerHTML = '';
            }

            function actualizarOficialesEnTabla(registroId, oficiales) {
                const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                if (!row) return;
                const oficialTexto = row.querySelector('.oficial-texto');
                if (!oficialTexto) return;

                const primerOficial = oficiales.length > 0 ? oficiales[0] : null;
                const nombreCompleto = primerOficial && primerOficial.nom_empl ? primerOficial.nom_empl : '';
                let textoOficiales = 'Sin oficiales';
                if (nombreCompleto) {
                    textoOficiales = nombreCompleto.length > 12 ? nombreCompleto.substring(0, 12) + '...' : nombreCompleto;
                }

                oficialTexto.textContent = textoOficiales;
                oficialTexto.setAttribute('title', nombreCompleto || 'Sin oficiales');
                oficialTexto.className = oficialTexto.className.replace('text-gray-400 italic', '');
                if (!primerOficial || !primerOficial.nom_empl) {
                    oficialTexto.classList.add('text-gray-400', 'italic');
                } else {
                    oficialTexto.classList.remove('text-gray-400', 'italic');
                }

                const oficialesParaJson = oficiales.map(o => ({
                    numero: o.numero_oficial,
                    nombre: o.nom_empl || '',
                    clave: o.cve_empl || '',
                    metros: o.metros || '',
                    turno: o.turno || ''
                }));
                oficialTexto.setAttribute('data-oficiales-json', JSON.stringify(oficialesParaJson));

                if (oficiales.length > 0 && oficiales[0].turno) {
                    const turnoSelect = row.querySelector('select[data-field="turno"]');
                    if (turnoSelect) turnoSelect.value = oficiales[0].turno;
                }

                const sumaMetros = oficiales.reduce((acc, o) => acc + (parseFloat(o.metros) || 0), 0);
                const metrosInput = row.querySelector('input[data-field="metros"]');
                if (metrosInput) metrosInput.value = sumaMetros > 0 ? sumaMetros : '';

                const btnAgregar = row.querySelector('.btn-agregar-oficial');
                if (btnAgregar) {
                    btnAgregar.setAttribute('data-cantidad-oficiales', oficiales.length);
                    btnAgregar.disabled = oficiales.length >= 3;
                    if (btnAgregar.disabled) {
                        btnAgregar.classList.remove('text-blue-600', 'hover:text-blue-800', 'hover:bg-blue-50');
                        btnAgregar.classList.add('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                    } else {
                        btnAgregar.classList.remove('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                        btnAgregar.classList.add('text-blue-600', 'hover:text-blue-800', 'hover:bg-blue-50');
                    }
                }
            }

            async function propagarOficialesHaciaAbajo(registroIdActual, oficiales) {
                const tablaBody = document.getElementById('tabla-produccion-body');
                if (!tablaBody) return;
                const todasLasFilas = Array.from(tablaBody.querySelectorAll('tr[data-registro-id]'));
                const indiceActual = todasLasFilas.findIndex(row => row.getAttribute('data-registro-id') == registroIdActual);
                if (indiceActual === -1) return;

                for (let i = indiceActual + 1; i < todasLasFilas.length; i++) {
                    const fila = todasLasFilas[i];
                    const registroId = fila.getAttribute('data-registro-id');
                    if (!registroId) continue;

                    const hInicioInput = fila.querySelector('input[data-field="h_inicio"]');
                    if (hInicioInput && hInicioInput.value && hInicioInput.value.trim() !== '') break;

                    try {
                        for (const oficial of oficiales) {
                            const response = await fetch('{{ route('engomado.modulo.produccion.engomado.guardar.oficial') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    registro_id: registroId,
                                    numero_oficial: oficial.numero_oficial,
                                    cve_empl: oficial.cve_empl,
                                    nom_empl: oficial.nom_empl,
                                    turno: oficial.turno,
                                    metros: oficial.metros
                                })
                            });
                            const result = await response.json();
                            if (!result.success) break;
                        }
                        actualizarOficialesEnTabla(registroId, oficiales);
                    } catch (err) {
                        console.error('Error propagando oficiales:', err);
                    }
                }
            }

            // ===== DOM Ready =====
            document.addEventListener('DOMContentLoaded', function () {
                const tablaBody = document.getElementById('tabla-produccion-body');

                // ===== CHECKBOX FINALIZAR (Listo) =====
                function bloquearFila(row) {
                    row.classList.add('bg-green-50', 'opacity-75');
                    row.querySelectorAll('input:not(.checkbox-finalizar), select, button').forEach(el => {
                        if (el.classList.contains('checkbox-finalizar')) return;
                        el.disabled = true;
                        el.classList.add('cursor-not-allowed', 'pointer-events-none');
                    });
                    row.querySelectorAll('.edit-quantity-btn, .btn-agregar-oficial, .btn-fecha-display, .set-current-time').forEach(el => {
                        el.disabled = true;
                        el.classList.add('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
                    });
                }

                function desbloquearFila(row) {
                    row.classList.remove('bg-green-50', 'opacity-75');
                    row.querySelectorAll('input:not(.checkbox-finalizar), select, button').forEach(el => {
                        if (el.classList.contains('checkbox-finalizar')) return;
                        const field = el.getAttribute('data-field');
                        const esReadonly = field === 'tara' || field === 'metros' || field === 'kg_neto';
                        if (!esReadonly) el.disabled = false;
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

                if (tablaBody) {
                    tablaBody.querySelectorAll('.checkbox-finalizar:checked').forEach(checkbox => {
                        const row = checkbox.closest('tr');
                        if (row) bloquearFila(row);
                    });
                }

                if (tablaBody) {
                    tablaBody.addEventListener('mousedown', function (e) {
                        const row = e.target.closest('tr[data-registro-id]');
                        if (!row) return;
                        if (e.target.classList.contains('checkbox-finalizar') || e.target.closest('.checkbox-finalizar')) return;
                        const checkbox = row.querySelector('.checkbox-finalizar');
                        if (checkbox && checkbox.checked) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Registro finalizado',
                                    text: 'Este registro ya está parcialmente finalizado. Desmarca la casilla para editarlo.',
                                    timer: 2500,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            }
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
                        if (ax === 1) {
                            checkbox.checked = !listo;
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
                        marcarRegistroListo(registroId, listo, checkbox);
                    });
                }

                async function marcarRegistroListo(registroId, listo, checkbox) {
                    const row = checkbox.closest('tr');
                    try {
                        const response = await fetch('{{ route('engomado.modulo.produccion.engomado.marcar.listo') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ registro_id: registroId, listo: listo })
                        });
                        const result = await response.json();
                        if (result.success) {
                            if (row) {
                                if (listo) bloquearFila(row);
                                else desbloquearFila(row);
                            }
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: listo ? 'Finalizado' : 'Desmarcado',
                                    text: listo ? 'Registro parcialmente finalizado' : 'Registro desbloqueado para edición',
                                    timer: 1500,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            }
                        } else {
                            checkbox.checked = !listo;
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'error', title: 'Error', text: result.error || 'Error al actualizar el registro' });
                            }
                        }
                    } catch (error) {
                        console.error('Error al marcar como listo:', error);
                        checkbox.checked = !listo;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al actualizar el registro. Por favor, intenta nuevamente.' });
                        }
                    }
                }

                function verificarFilaNoFinalizada(registroId) {
                    if (esFilaBloqueada(registroId)) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'info',
                                title: 'Registro finalizado',
                                text: 'Este registro ya está parcialmente finalizado. Desmarca la casilla para editarlo.',
                                timer: 2500,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end'
                            });
                        }
                        return false;
                    }
                    return true;
                }

                // Remover borde rojo cuando el usuario corrige los campos
                function removerErrorAlCambiar(e) {
                    const elemento = e.target;
                    if (elemento.classList.contains('border-red-500')) {
                        elemento.classList.remove('border-red-500', 'border-2');
                        elemento.classList.add('border-gray-300');
                    }
                }

                // Agregar listeners para quitar errores visuales al corregir
                if (tablaBody) {
                    tablaBody.addEventListener('input', removerErrorAlCambiar);
                    tablaBody.addEventListener('change', removerErrorAlCambiar);
                }

                // Para campos de merma
                document.querySelectorAll('input[data-field="merma_con_goma"], input[data-field="merma_sin_goma"]').forEach(el => {
                    el.addEventListener('input', removerErrorAlCambiar);
                    el.addEventListener('change', removerErrorAlCambiar);
                });

                // Para botones de temperatura (Canoa 1 y 2)
                document.addEventListener('click', function(e) {
                    const opt = e.target.closest('.number-option');
                    if (opt) {
                        const cell = opt.closest('td');
                        const btn = cell ? cell.querySelector('.edit-quantity-btn') : null;
                        if (btn && btn.classList.contains('border-red-500')) {
                            btn.classList.remove('border-red-500', 'border-2');
                        }
                    }
                });

                if (tablaBody) {
                    // Precalcular netos
                    tablaBody.querySelectorAll('tr').forEach(calcularNeto);

                    // Inicializar turno desde oficial-texto en cada fila
                    tablaBody.querySelectorAll('tr').forEach(row => {
                        const oficialTexto = row.querySelector('.oficial-texto');
                        if (oficialTexto) {
                            try {
                                const oficialesJson = oficialTexto.getAttribute('data-oficiales-json');
                                if (oficialesJson) {
                                    const oficiales = JSON.parse(oficialesJson);
                                    if (oficiales.length > 0 && oficiales[0].turno) {
                                        const turnoSelect = row.querySelector('select[data-field="turno"]');
                                        if (turnoSelect) turnoSelect.value = oficiales[0].turno;
                                    }
                                }
                            } catch (e) { console.error(e); }
                        }
                        const hInicioInput = row.querySelector('input[data-field="h_inicio"]');
                        const hFinInput = row.querySelector('input[data-field="h_fin"]');
                        if (hInicioInput) {
                            hInicioInput.setAttribute('data-valor-anterior', hInicioInput.value || '');
                        }
                        if (hFinInput) {
                            hFinInput.setAttribute('data-valor-anterior', hFinInput.value || '');
                        }
                    });

                    tablaBody.addEventListener('input', function (e) {
                        const row = e.target.closest('tr');
                        if (!row) return;

                        const campo = e.target.dataset.field;

                        if (campo === 'kg_bruto' || campo === 'tara') {
                            calcularNeto(row);

                            if (campo === 'kg_bruto') {
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

                    // Agregar listeners directamente a los inputs de Sol. Can. para actualizar cuando el usuario sale del campo
                    tablaBody.querySelectorAll('input[data-field="solidos"]').forEach(input => {
                        input.addEventListener('blur', function() {
                            const row = this.closest('tr');
                            const registroId = row ? row.getAttribute('data-registro-id') : null;

                            if (!registroId) return;

                            if (!verificarOficialSeleccionado(registroId)) {
                                mostrarAlertaOficialRequerido();
                                // Revertir el valor si no hay oficial
                                const valorInicial = this.getAttribute('data-valor-inicial') || '';
                                this.value = valorInicial;
                                return;
                            }

                            const valor = (this.value || '').trim();
                            if (valor !== '') {
                                actualizarCampoProduccion(registroId, 'Solidos', valor || null);
                            }
                        });
                    });

                    tablaBody.addEventListener('change', function (e) {
                        const target = e.target;
                        const field = target.getAttribute('data-field');
                        const row = target.closest('tr');
                        const registroId = row ? row.getAttribute('data-registro-id') : null;

                        if (!row || !registroId) return;

                        // No. Julio
                        if (target.classList.contains('select-julio')) {
                            const taraInput = row.querySelector('input[data-field="tara"]');
                            const selectedOption = target.options[target.selectedIndex];
                            const noJulioValue = selectedOption ? selectedOption.value : '';

                            if (!(taraInput && selectedOption)) return;

                            if (!verificarOficialSeleccionado(registroId)) {
                                if (!target.hasAttribute('data-valor-anterior')) {
                                    const valorInicial = target.getAttribute('data-valor-inicial') || '';
                                    target.setAttribute('data-valor-anterior', valorInicial);
                                }
                                const valorAnterior = target.getAttribute('data-valor-anterior') || '';
                                target.value = valorAnterior;
                                mostrarAlertaOficialRequerido();
                                return;
                            }

                            target.setAttribute('data-valor-anterior', noJulioValue);

                            const taraStr = selectedOption.getAttribute('data-tara');
                            const tara = taraStr !== null && taraStr !== '' ? parseFloat(taraStr) : null;
                            taraInput.value = tara !== null ? tara.toFixed(1) : '';

                            const brutoInput = row.querySelector('input[data-field="kg_bruto"]');
                            const netoInput = row.querySelector('input[data-field="kg_neto"]');
                            let kgNeto = null;

                            if (brutoInput && netoInput) {
                                const bruto = parseFloat(brutoInput.value) || 0;
                                const taraVal = tara !== null ? tara : 0;
                                kgNeto = bruto - taraVal;
                                netoInput.value = kgNeto.toFixed(2);
                            }

                            actualizarJulioTara(registroId, noJulioValue, tara, kgNeto)
                                .then(() => {
                                    // Actualizar el valor inicial y recargar los selects para filtrar julios usados
                                    target.setAttribute('data-valor-inicial', noJulioValue);
                                    cargarCatalogosJulios();
                                });
                        }

                        // Cambio manual de turno
                        if (field === 'turno' && target.tagName === 'SELECT') {
                            const turnoValue = target.value;
                            const oficialTexto = row.querySelector('.oficial-texto');

                            if (oficialTexto && turnoValue) {
                                try {
                                    const oficialesJson = oficialTexto.getAttribute('data-oficiales-json');
                                    if (oficialesJson) {
                                        const oficiales = JSON.parse(oficialesJson);
                                        if (oficiales.length > 0) {
                                            actualizarTurnoOficial(registroId, oficiales[0].numero, turnoValue);
                                        }
                                    }
                                } catch (e) { console.error(e); }
                            }
                        }

                        // Fecha
                        if (target.classList.contains('input-fecha') && field === 'fecha') {
                            const fechaInput = target;
                            const fechaValue = fechaInput.value;
                            const fechaInicial = fechaInput.getAttribute('data-fecha-inicial');

                            if (fechaValue) {
                                const fechaDisplayText = row.querySelector('.fecha-display-text');
                                if (fechaDisplayText) {
                                    const parts = fechaValue.split('-');
                                    if (parts.length === 3) {
                                        fechaDisplayText.textContent = `${parts[2]}/${parts[1]}`;
                                    }
                                }
                            }

                            if (fechaValue && fechaValue !== fechaInicial) {
                                actualizarFecha(registroId, fechaValue);
                                fechaInput.setAttribute('data-fecha-inicial', fechaValue);
                            }
                        }

                        // Horas (input type="time")
                        if (field === 'h_inicio' || field === 'h_fin') {
                            const horaValue = target.value || null;
                            if (!verificarOficialSeleccionado(registroId)) {
                                mostrarAlertaOficialRequerido();
                                const anterior = target.getAttribute('data-valor-anterior') || '';
                                target.value = anterior;
                                return;
                            }
                            actualizarHora(registroId, field === 'h_inicio' ? 'HoraInicial' : 'HoraFinal', horaValue);
                            target.setAttribute('data-valor-anterior', target.value || '');
                        }

                        // Campos de producción
                        const camposProduccion = Object.keys(campoMap);
                        if (camposProduccion.includes(field)) {
                            if (!verificarOficialSeleccionado(registroId)) {
                                // Revertir el valor si no hay oficial
                                if (field === 'solidos') {
                                    const valorInicial = target.getAttribute('data-valor-inicial') || '';
                                    target.value = valorInicial;
                                }
                                mostrarAlertaOficialRequerido();
                                return;
                            }

                            const valor = (target.value || '').trim();
                            const campoBD = campoMap[field];
                            if (campoBD) {
                                // Actualizar el valor inicial después de la primera actualización exitosa
                                if (field === 'solidos') {
                                    target.setAttribute('data-valor-inicial', valor || '');
                                }
                                actualizarCampoProduccion(registroId, campoBD, valor || null);
                            }
                        }
                    });
                }

                // Catalogo julios y usuarios
                cargarCatalogosJulios();
                cargarUsuariosEngomado();

                // Cerrar selects de cantidad al hacer click fuera
                document.addEventListener('click', function (event) {
                    const isInsideEditor = event.target.closest('.quantity-edit-container');
                    const isEditButton   = event.target.closest('.edit-quantity-btn');
                    const isNumberOption = event.target.closest('.number-option');

                    // No cerrar si se está haciendo clic dentro del editor, en el botón o en una opción de número
                    if (!isInsideEditor && !isEditButton && !isNumberOption) {
                        closeAllQuantityEditors();
                    }
                });

                // Click en número (Canoa1, Canoa2)
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
                        if (!registroId || !fieldName || !campoMap[fieldName]) return;

                        if (!verificarOficialSeleccionado(registroId)) {
                            mostrarAlertaOficialRequerido();
                            return;
                        }

                        const valorAnterior = quantityDisplay.textContent.trim();
                        quantityDisplay.textContent = selectedValue;

                        // Quitar borde rojo si estaba marcado como error
                        const editBtn = cell.querySelector('.edit-quantity-btn');
                        if (editBtn && editBtn.classList.contains('border-red-500')) {
                            editBtn.classList.remove('border-red-500', 'border-2', 'ring-2', 'ring-red-300');
                            editBtn.style.border = '';
                        }

                        actualizarCampoProduccion(registroId, campoMap[fieldName], selectedValue)
                            .catch(() => {
                                quantityDisplay.textContent = valorAnterior;
                            });
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

                // Click en botón de fecha (abre datepicker nativo)
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

                    const btnRect = btnFecha.getBoundingClientRect();

                    fechaInput.style.position = 'fixed';
                    fechaInput.style.opacity = '0';
                    fechaInput.style.width = btnRect.width + 'px';
                    fechaInput.style.height = btnRect.height + 'px';
                    fechaInput.style.top = btnRect.top + 'px';
                    fechaInput.style.left = btnRect.left + 'px';
                    fechaInput.style.zIndex = '9999';
                    fechaInput.style.pointerEvents = 'auto';
                    fechaInput.style.cursor = 'pointer';

                    setTimeout(() => {
                        if (fechaInput.showPicker) {
                            fechaInput.showPicker();
                        } else {
                            fechaInput.click();
                        }
                    }, 10);

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

                // Botón de hora actual
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

                // Abrir modal oficiales
                document.addEventListener('click', function (e) {
                    const btnAgregar = e.target.closest('.btn-agregar-oficial');
                    if (!btnAgregar) return;

                    e.preventDefault();
                    if (btnAgregar.disabled) return;
                    const registroId = btnAgregar.getAttribute('data-registro-id');
                    if (registroId) abrirModalOficial(registroId);
                });

                // Cerrar modal
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

                // Cambio en select de empleados dentro del modal
                document.addEventListener('change', function (e) {
                    if (!e.target.classList.contains('select-oficial-nombre')) return;

                    const select = e.target;
                    const numero = select.getAttribute('data-numero');
                    const selectedOption = select.options[select.selectedIndex];

                    const claveInput  = document.querySelector(`input.input-oficial-clave[data-numero="${numero}"]`);
                    const nombreInput = document.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                    const turnoSelect = document.querySelector(`select.input-oficial-turno[data-numero="${numero}"]`);
                    const btnEliminar = document.querySelector(`.btn-eliminar-oficial[data-numero="${numero}"]`);

                    if (selectedOption && selectedOption.value) {
                        const numeroEmpleado = selectedOption.value;
                        const nombre = selectedOption.getAttribute('data-nombre') || selectedOption.textContent;
                        const turno  = selectedOption.getAttribute('data-turno') || '';

                        if (claveInput)  claveInput.value  = numeroEmpleado;
                        if (nombreInput) nombreInput.value = nombre;
                        if (turnoSelect && turno) turnoSelect.value = turno;
                        if (btnEliminar) {
                            btnEliminar.disabled = false;
                            btnEliminar.classList.remove('opacity-50', 'cursor-not-allowed');
                        }

                        if (!validarNoOperadorDuplicadoEnModal(true)) {
                            select.value = '';
                            if (claveInput)  claveInput.value  = '';
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
                        if (claveInput)  claveInput.value  = '';
                        if (nombreInput) nombreInput.value = '';
                        if (turnoSelect) turnoSelect.value = '';
                        const metrosInput = document.querySelector(`input.input-oficial-metros[data-numero="${numero}"]`);
                        if (metrosInput) metrosInput.value = '';
                        if (btnEliminar) {
                            btnEliminar.disabled = true;
                            btnEliminar.classList.add('opacity-50', 'cursor-not-allowed');
                        }
                    }

                    validarNoOperadorDuplicadoEnModal(false);
                });

                document.addEventListener('click', function (e) {
                    const btnEliminar = e.target.closest('.btn-eliminar-oficial');
                    if (!btnEliminar || btnEliminar.disabled) return;
                    e.preventDefault();
                    const numero = btnEliminar.getAttribute('data-numero');
                    const registroId = modalRegistroId ? modalRegistroId.value : null;
                    if (!registroId) return;

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
                                try {
                                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.eliminar.oficial') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({ registro_id: registroId, numero_oficial: numero })
                                    });
                                    const data = await response.json();

                                    if (data.success) {
                                        const containerOficiales = document.getElementById('oficiales-existentes');
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

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Oficial eliminado',
                                            timer: 1500,
                                            showConfirmButton: false,
                                            toast: true,
                                            position: 'top-end'
                                        });
                                    } else {
                                        mostrarAlertaErrorModal(data.error || 'Error al eliminar oficial');
                                    }
                                } catch (err) {
                                    console.error(err);
                                    mostrarAlertaErrorModal('Error al eliminar el oficial');
                                }
                            }
                        });
                    }
                });

            // Listener para campos de merma (Merma con Goma y Merma sin Goma)
            document.addEventListener('change', function (e) {
                if (e.target.dataset.field === 'merma_con_goma' || e.target.dataset.field === 'merma_sin_goma') {
                    const campo = e.target.dataset.field;
                    const valor = e.target.value !== '' ? parseFloat(e.target.value) : null;

                    @if(isset($orden) && $orden)
                        actualizarCampoOrden({{ $orden->Id }}, campo, valor);
                    @endif
                }
            });

            // Función para actualizar campos de la orden (no de producción)
            async function actualizarCampoOrden(ordenId, campo, valor) {
                try {
                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.campo.orden') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            orden_id: ordenId,
                            campo,
                            valor: valor
                        })
                    });

                    const result = await response.json();

                    if (result.success && typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Actualizado',
                            text: result.message || 'Campo actualizado correctamente',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                } catch (error) {
                    console.error('Error al actualizar campo de orden:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al actualizar campo. Por favor, intenta nuevamente.'
                        });
                    }
                }
            }

                // Guardar oficiales
                if (btnGuardarOficiales) {
                    btnGuardarOficiales.addEventListener('click', async function () {
                        if (!modalRegistroId || !modalRegistroId.value) {
                            alert('Error: No se encontró el registro');
                            return;
                        }

                        const registroId = modalRegistroId.value;
                        const containerOficiales = document.getElementById('oficiales-existentes');
                        if (!containerOficiales) return;

                        const oficiales = [];
                        for (let i = 1; i <= 3; i++) {
                            const claveInput  = containerOficiales.querySelector(`input.input-oficial-clave[data-numero="${i}"]`);
                            const nombreInput = containerOficiales.querySelector(`input.input-oficial-nombre[data-numero="${i}"]`);
                            const turnoSelect = containerOficiales.querySelector(`select.input-oficial-turno[data-numero="${i}"]`);
                            const metrosInput = containerOficiales.querySelector(`input.input-oficial-metros[data-numero="${i}"]`);

                            const clave  = claveInput ? claveInput.value.trim() : '';
                            const nombre = nombreInput ? nombreInput.value.trim() : '';
                            const turno  = turnoSelect ? turnoSelect.value : '';
                            const metros = metrosInput ? metrosInput.value.trim() : '';

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

                        try {
                            let guardados = 0;
                            let oficialesGuardados = [];
                            for (const oficial of oficiales) {
                                const data = { registro_id: registroId, ...oficial };

                                const response = await fetch('{{ route('engomado.modulo.produccion.engomado.guardar.oficial') }}', {
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
                                actualizarOficialesEnTabla(registroId, oficialesGuardados);
                                cerrarModalOficial();
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Oficiales guardados',
                                        text: 'Los oficiales han sido guardados correctamente',
                                        timer: 2000,
                                        showConfirmButton: false,
                                        toast: true,
                                        position: 'top-end'
                                    });
                                }
                                setTimeout(() => propagarOficialesHaciaAbajo(registroId, oficialesGuardados), 500);
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

            function limpiarErroresVisuales() {
                const tablaBody = document.getElementById('tabla-produccion-body');
                if (tablaBody) {
                    tablaBody.querySelectorAll('input, select').forEach(el => {
                        marcarCampoError(el, false);
                    });
                }

                // Limpiar errores de campos de merma
                document.querySelectorAll('input[data-field="merma_con_goma"], input[data-field="merma_sin_goma"]').forEach(el => {
                    marcarCampoError(el, false);
                });
            }

            function validarRegistrosCompletos() {
                limpiarErroresVisuales();

                const tablaBody = document.getElementById('tabla-produccion-body');
                if (!tablaBody) return { valido: false, mensaje: 'No se encontró la tabla de producción' };

                const filas = tablaBody.querySelectorAll('tr[data-registro-id]');
                const registrosIncompletos = [];
                let hayErrores = false;

                // Validar campos de merma (fuera de la tabla)
                const mermaConGoma = document.querySelector('input[data-field="merma_con_goma"]');
                const mermaSinGoma = document.querySelector('input[data-field="merma_sin_goma"]');

                if (!mermaConGoma || !mermaConGoma.value || mermaConGoma.value.trim() === '') {
                    marcarCampoError(mermaConGoma, true);
                    hayErrores = true;
                }

                if (!mermaSinGoma || !mermaSinGoma.value || mermaSinGoma.value.trim() === '') {
                    marcarCampoError(mermaSinGoma, true);
                    hayErrores = true;
                }

                filas.forEach((fila, index) => {
                    const registroId = fila.getAttribute('data-registro-id');
                    const camposFaltantes = [];

                    // Fecha
                    const fechaInput = fila.querySelector('input.input-fecha');
                    if (!fechaInput || !fechaInput.value) {
                        camposFaltantes.push('Fecha');
                        marcarCampoError(fechaInput, true);
                        hayErrores = true;
                    }

                    // Oficial (requerido)
                    const oficialTexto = fila.querySelector('.oficial-texto');
                    const textoOficial = oficialTexto ? oficialTexto.textContent.trim() : '';
                    const tieneOficial = textoOficial && textoOficial !== 'Sin oficiales';
                    if (!oficialTexto || !tieneOficial) {
                        camposFaltantes.push('Oficial');
                        marcarCampoError(oficialTexto, true);
                        hayErrores = true;
                    }

                    // Turno
                    const turnoSelect = fila.querySelector('select[data-field="turno"]');
                    if (!turnoSelect || !turnoSelect.value) {
                        camposFaltantes.push('Turno');
                        marcarCampoError(turnoSelect, true);
                        hayErrores = true;
                    }

                    // H. Inicio
                    const hInicioInput = fila.querySelector('input[data-field="h_inicio"]');
                    if (!hInicioInput || !hInicioInput.value) {
                        camposFaltantes.push('H. Inicio');
                        marcarCampoError(hInicioInput, true);
                        hayErrores = true;
                    }

                    // H. Fin
                    const hFinInput = fila.querySelector('input[data-field="h_fin"]');
                    if (!hFinInput || !hFinInput.value) {
                        camposFaltantes.push('H. Fin');
                        marcarCampoError(hFinInput, true);
                        hayErrores = true;
                    }

                    // Julio
                    const julioSelect = fila.querySelector('select[data-field="no_julio"]');
                    if (!julioSelect || !julioSelect.value) {
                        camposFaltantes.push('Julio');
                        marcarCampoError(julioSelect, true);
                        hayErrores = true;
                    }

                    // Kg. Bruto
                    const kgBrutoInput = fila.querySelector('input[data-field="kg_bruto"]');
                    if (!kgBrutoInput || !kgBrutoInput.value || kgBrutoInput.value.trim() === '') {
                        camposFaltantes.push('Kg. Bruto');
                        marcarCampoError(kgBrutoInput, true);
                        hayErrores = true;
                    }

                    // Tara
                    const taraInput = fila.querySelector('input[data-field="tara"]');
                    if (!taraInput || !taraInput.value || taraInput.value.trim() === '') {
                        camposFaltantes.push('Tara');
                        marcarCampoError(taraInput, true);
                        hayErrores = true;
                    }

                    // Kg. Neto - Validar que no sea negativo
                    const kgNetoInput = fila.querySelector('input[data-field="kg_neto"]');
                    if (kgNetoInput && kgNetoInput.value) {
                        const kgNetoValue = parseFloat(kgNetoInput.value);
                        if (!isNaN(kgNetoValue) && kgNetoValue < 0) {
                            camposFaltantes.push('Kg. Neto (no puede ser negativo)');
                            marcarCampoError(kgNetoInput, true);
                            hayErrores = true;
                        }
                    }

                    // Metros
                    const metrosInput = fila.querySelector('input[data-field="metros"]');
                    if (!metrosInput || !metrosInput.value || metrosInput.value.trim() === '') {
                        camposFaltantes.push('Metros');
                        marcarCampoError(metrosInput, true);
                        hayErrores = true;
                    }

                    // Sólidos (Sol. Can.) - REQUERIDO
                    const solidosInput = fila.querySelector('input[data-field="solidos"]');
                    if (!solidosInput || !solidosInput.value || solidosInput.value.trim() === '') {
                        camposFaltantes.push('Sólidos');
                        marcarCampoError(solidosInput, true);
                        hayErrores = true;
                    }

                    // Canoa 1 (Temp) - REQUERIDO
                    const canoa1Btn = fila.querySelector('button[onclick*="temp_canoa1"]');
                    const canoa1Display = canoa1Btn ? canoa1Btn.querySelector('.quantity-display[data-field="temp_canoa1"]') : null;
                    const canoa1Value = canoa1Display ? canoa1Display.textContent.trim() : '';
                    if (!canoa1Value || canoa1Value === '0' || canoa1Value === '') {
                        camposFaltantes.push('Temp Canoa 1');
                        if (canoa1Btn) {
                            canoa1Btn.classList.add('border-red-500', 'border-2', 'ring-2', 'ring-red-300');
                            canoa1Btn.style.border = '2px solid #ef4444';
                        }
                        hayErrores = true;
                    }

                    // Canoa 2 (Temp) - REQUERIDO
                    const canoa2Btn = fila.querySelector('button[onclick*="temp_canoa2"]');
                    const canoa2Display = canoa2Btn ? canoa2Btn.querySelector('.quantity-display[data-field="temp_canoa2"]') : null;
                    const canoa2Value = canoa2Display ? canoa2Display.textContent.trim() : '';
                    if (!canoa2Value || canoa2Value === '0' || canoa2Value === '') {
                        camposFaltantes.push('Temp Canoa 2');
                        if (canoa2Btn) {
                            canoa2Btn.classList.add('border-red-500', 'border-2', 'ring-2', 'ring-red-300');
                            canoa2Btn.style.border = '2px solid #ef4444';
                        }
                        hayErrores = true;
                    }

                    // Ubicación - REQUERIDO
                    const ubicacionSelect = fila.querySelector('select[data-field="ubicacion"]');
                    if (!ubicacionSelect || !ubicacionSelect.value || ubicacionSelect.value.trim() === '') {
                        camposFaltantes.push('Ubicación');
                        marcarCampoError(ubicacionSelect, true);
                        hayErrores = true;
                    }

                    // Roturas es OPCIONAL, no se valida

                    if (camposFaltantes.length > 0) {
                        registrosIncompletos.push({
                            fila: index + 1,
                            campos: camposFaltantes
                        });
                    }
                });

                if (hayErrores || registrosIncompletos.length > 0) {
                    let mensaje = 'Por favor completa los siguientes campos:\n\n';

                    // Agregar errores de merma si existen
                    if ((mermaConGoma && (!mermaConGoma.value || mermaConGoma.value.trim() === '')) ||
                        (mermaSinGoma && (!mermaSinGoma.value || mermaSinGoma.value.trim() === ''))) {
                        mensaje += '• Merma con Goma\n';
                        mensaje += '• Merma sin Goma\n\n';
                    }

                    registrosIncompletos.forEach(reg => {
                        mensaje += `Fila ${reg.fila}: ${reg.campos.join(', ')}\n`;
                    });

                    return { valido: false, mensaje: mensaje.trim() };
                }

                return { valido: true };
            }

            // ===== Acción Finalizar =====
            window.finalizar = async function () {
                // Validar que todos los registros estén completos
                const validacion = validarRegistrosCompletos();

                if (!validacion.valido) {
                    // Hacer scroll al primer campo con error
                    const primerError = document.querySelector('.border-red-500');
                    if (primerError) {
                        primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => {
                            primerError.focus();
                        }, 500);
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Registros incompletos',
                            html: '<pre style="text-align: left; white-space: pre-wrap; font-family: inherit;">' + validacion.mensaje.replace(/\n/g, '<br>') + '</pre>',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#2563eb',
                            width: '600px'
                        });
                    } else {
                        alert(validacion.mensaje);
                    }
                    return;
                }

                // Si todos los registros están completos, verificar formulaciones antes de proceder
                @if(isset($orden) && $orden)
                    const ordenId = {{ $orden->Id }};
                    const ordenFolio = '{{ $orden->Folio }}';

                    try {
                        // Verificar si existe al menos una formulación antes de mostrar confirmación
                        const checkResponse = await fetch('{{ route('engomado.modulo.produccion.engomado.verificar.formulaciones') }}?folio=' + encodeURIComponent(ordenFolio), {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const checkResult = await checkResponse.json();

                        if (!checkResult.success || !checkResult.tieneFormulaciones) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'error',
                                title: 'No se puede finalizar',
                                text: 'Debe existir al menos una formulación (EngProduccionFormulacion) con el Folio ' + ordenFolio + ' antes de finalizar.',
                                showConfirmButton: false,
                                timer: 5000,
                                timerProgressBar: true
                            });
                            return;
                        }
                    } catch (error) {
                        console.error('Error al verificar formulaciones:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al verificar formulaciones. Por favor, intenta nuevamente.'
                        });
                        return;
                    }
                @endif

                // Si todos los registros están completos y hay formulaciones, proceder con la finalización
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
                                    const response = await fetch('{{ route('engomado.modulo.produccion.engomado.finalizar') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({ orden_id: ordenId })
                                    });

                                    const result = await response.json();

                                    if (result.success) {
                                        // Generar PDF automáticamente
                                        const url = '{{ route('engomado.modulo.produccion.engomado.pdf') }}?orden_id=' + ordenId + '&tipo=engomado';
                                        window.open(url, '_blank');

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
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: result.error || 'Error al finalizar el registro'
                                        });
                                    }
                                } catch (error) {
                                    console.error('Error al finalizar:', error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Error al finalizar el registro. Por favor, intenta nuevamente.'
                                    });
                                }
                            @else
                                alert('No hay orden seleccionada');
                            @endif
                        }
                    });
                } else {
                    @if(isset($orden) && $orden)
                        const ordenId = {{ $orden->Id }};
                        const ordenFolio = '{{ $orden->Folio }}';

                        try {
                            // Verificar si existe al menos una formulación antes de mostrar confirmación
                            const checkResponse = await fetch('{{ route('engomado.modulo.produccion.engomado.verificar.formulaciones') }}?folio=' + encodeURIComponent(ordenFolio), {
                                method: 'GET',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            });

                            const checkResult = await checkResponse.json();

                            if (!checkResult.success || !checkResult.tieneFormulaciones) {
                                alert('No se puede finalizar. Debe existir al menos una formulación con el Folio ' + ordenFolio + ' antes de finalizar.');
                                return;
                            }
                        } catch (error) {
                            console.error('Error al verificar formulaciones:', error);
                            alert('Error al verificar formulaciones. Por favor, intenta nuevamente.');
                            return;
                        }
                    @endif

                    if (confirm('¿Finalizar registro?')) {
                        @if(isset($orden) && $orden)
                            const ordenId = {{ $orden->Id }};

                            try {
                                const response = await fetch('{{ route('engomado.modulo.produccion.engomado.finalizar') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({ orden_id: ordenId })
                                });

                                const result = await response.json();

                                if (result.success) {
                                    const url = '{{ route('engomado.modulo.produccion.engomado.pdf') }}?orden_id=' + ordenId + '&tipo=engomado';
                                    window.open(url, '_blank');
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
