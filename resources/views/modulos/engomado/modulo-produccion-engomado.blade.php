@extends('layouts.app')

@section('page-title', 'Producción de Engomado')

@php
    $canEdit = $canEdit ?? false;
@endphp
@section('navbar-right')
    <div class="flex items-center gap-2">
        @if($canEdit && isset($orden) && $orden && $registrosProduccion && $registrosProduccion->count() > 0)
        <button
            type="button"
            id="btn-imprimir-parcial"
            data-accion="imprimir-parcial"
            @if(!($tieneRegistrosParciales ?? false)) disabled @endif
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-500"
            title="{{ ($tieneRegistrosParciales ?? false) ? 'Imprimir producción parcial' : 'No hay registros pendientes de imprimir' }}"
        >
            <i class="fa-solid fa-print"></i>
            <span>Imprimir producción parcial</span>
        </button>
        @endif
        <x-navbar.button-create
            data-accion="ir"
            data-url="{{ isset($orden) && $orden && $orden->Folio ? route('engomado.captura-formula', ['folio' => $orden->Folio]) : route('engomado.captura-formula') }}"
            title="Agregar fórmula"
            icon="fa-flask"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Agregar fórmula"
            bg="bg-blue-500"
        />
        @if(isset($orden) && $orden)
        <button type="button" data-calificar-julios-abrir="urdido"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center gap-2"
            title="Calificar julios de urdido (folio de la orden)">
            <i class="fa-solid fa-clipboard-check"></i>
            <span>Calificar julios</span>
        </button>
        @endif
        @if($canEdit)
        <x-navbar.button-create
            data-accion="finalizar"
            title="Finalizar"
            icon="fa-check-circle"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Finalizar"
            bg="bg-blue-500"
        />
        @endif
    </div>
@endsection

@section('content')

<style>
    @media (min-width: 768px) {
        .grid-produccion-columnas {
            grid-template-columns: 0.75fr 0.75fr 0.75fr 0.9fr 1.2fr !important;
        }
    }
    .produccion-solo-lectura input, .produccion-solo-lectura select,
    .produccion-solo-lectura .btn-agregar-oficial, .produccion-solo-lectura .btn-fecha-display,
    .produccion-solo-lectura .set-current-time, .produccion-solo-lectura .checkbox-finalizar,
    .produccion-solo-lectura .edit-quantity-btn, .produccion-solo-lectura .quantity-edit-container,
    .produccion-solo-lectura .number-option {
        pointer-events: none;
        opacity: 0.7;
        cursor: not-allowed;
    }
</style>

    @php
        $rutaProd = fn (string $sufijo) => route('engomado.modulo.produccion.engomado.'.$sufijo);
        $paginaProduccion = [
            'rutas' => [
                'catalogosJulios' => $rutaProd('catalogos.julios'),
                'usuarios' => $rutaProd('usuarios.engomado'),
                'guardarOficial' => $rutaProd('guardar.oficial'),
                'eliminarOficial' => $rutaProd('eliminar.oficial'),
                'actualizarTurnoOficial' => $rutaProd('actualizar.turno.oficial'),
                'actualizarFecha' => $rutaProd('actualizar.fecha'),
                'actualizarJulioTara' => $rutaProd('actualizar.julio.tara'),
                'actualizarKgBruto' => $rutaProd('actualizar.kg.bruto'),
                'actualizarCamposProduccion' => $rutaProd('actualizar.campos.produccion'),
                'actualizarCampoOrden' => $rutaProd('actualizar.campo.orden'),
                'actualizarHoras' => $rutaProd('actualizar.horas'),
                'verificarFormulaciones' => $rutaProd('verificar.formulaciones'),
                'finalizar' => $rutaProd('finalizar'),
                'marcarListo' => $rutaProd('marcar.listo'),
                'pdf' => $rutaProd('pdf'),
                'salida' => route('produccion.index'),
            ],
            'orden' => isset($orden) && $orden ? ['id' => (int) $orden->Id, 'folio' => (string) $orden->Folio] : null,
            'maxKgBruto' => $maxKgBruto ?? null,
            'puedeEditar' => (bool) $canEdit,
            'usuario' => [
                'nombre' => (string) (auth()->user()->nombre ?? ''),
                'numero' => (string) (auth()->user()->numero_empleado ?? (auth()->user()->numero ?? '')),
            ],
        ];
    @endphp
    <div class="w-full" id="produccion-engomado" data-pagina='@json($paginaProduccion)'>
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
                            @php
                                $tipo = strtoupper(trim($orden->RizoPie ?? ''));
                                $isRizo = $tipo === 'RIZO';
                                $isPie = $tipo === 'PIE';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $isRizo ? 'bg-rose-100 text-rose-700' : ($isPie ? 'bg-teal-100 text-teal-700' : 'bg-gray-200 text-gray-800') }}">
                                {{ $orden->RizoPie ?? '-' }}
                            </span>
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
                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap shrink-0">Hilo:</span>
                        <span class="text-sm text-gray-900 truncate">{{ $hiloFibra ?? '-' }}</span>
                    </div>
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
        <div class="bg-white shadow-md overflow-hidden {{ $canEdit ? '' : 'produccion-solo-lectura' }}" data-can-edit="{{ $canEdit ? '1' : '0' }}">
            <div class="overflow-x-auto max-h-[62vh] overflow-y-auto" style="min-width: 100%;">
                <table class="divide-y divide-gray-200 text-sm" style="min-width: max-content; width: 100%;">
                    <thead class="bg-blue-500 text-white sticky top-0 z-20">
                        <tr>
                            @if($hasFinalizarPermission)
                            <th class="py-1"></th>
                            @endif
                            <th class="py-1"></th>
                            <th class="py-1" colspan="2"></th>
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
                            <th class="py-2 px-1 text-left font-semibold text-xs md:text-sm bg-blue-500 text-white" style="width: 7rem; min-width: 7rem; max-width: 7rem;">No. Empleado</th>
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

                            {{-- Pintar SIEMPRE todas las filas que existen: iterar solo
                                 hasta NoTelas dejaba invisibles las sobrantes, que seguian
                                 vivas en la tabla y en los reportes. --}}
                        @php
                            $totalFilas = max((int) $totalRegistros, isset($registrosProduccion) ? $registrosProduccion->count() : 0);
                        @endphp
                        @if($totalFilas > 0)
                            @for($rowIndex = 1; $rowIndex <= $totalFilas; $rowIndex++)
                                @php
                                    $registro = isset($registrosProduccion) && $registrosProduccion->count() > 0
                                        ? $registrosProduccion->get($rowIndex - 1)
                                        : null;

                                    $fecha = $registro && $registro->Fecha ? date('Y-m-d', strtotime($registro->Fecha)) : date('Y-m-d');
                                    $horaInicio = $registro && $registro->HoraInicial ? substr($registro->HoraInicial, 0, 5) : '';
                                    $horaFin = $registro && $registro->HoraFinal ? substr($registro->HoraFinal, 0, 5) : '';
                                    $noJulio = $registro ? ($registro->NoJulio ?? '') : '';
                                    $kgBruto = $registro && $registro->KgBruto !== null ? number_format((float)$registro->KgBruto, 2, '.', '') : '';
                                    $tara = $registro && $registro->Tara !== null ? number_format((float)$registro->Tara, 1, '.', '') : '';
                                    $kgNeto = $registro ? ($registro->KgNeto ?? '') : '';
                                    $solidos = $registro && $registro->Solidos !== null ? number_format((float)$registro->Solidos, 2, '.', '') : '';

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
                                    $primerOficialNombreCompleto = '';
                                    $textoOficiales = '';
                                    for ($i = 1; $i <= 3; $i++) {
                                        $nomEmpl = $registro ? ($registro->{"NomEmpl{$i}"} ?? null) : null;
                                        $cveEmpl = $registro ? ($registro->{"CveEmpl{$i}"} ?? null) : null;
                                        if ($nomEmpl || $cveEmpl) {
                                            $oficiales[] = [
                                                'numero' => $i,
                                                'nombre' => $nomEmpl,
                                                'clave' => $cveEmpl,
                                                'metros' => $registro->{"Metros{$i}"} ?? null,
                                                'turno' => $registro->{"Turno{$i}"} ?? null,
                                            ];
                                            if ($i === 1 && !$textoOficiales) {
                                                $primerOficialNombreCompleto = trim($nomEmpl ?? '') ?: trim($cveEmpl ?? '');
                                                if ($primerOficialNombreCompleto !== '') {
                                                    $textoOficiales = mb_strlen($primerOficialNombreCompleto) > 12
                                                        ? mb_substr($primerOficialNombreCompleto, 0, 12) . '...'
                                                        : $primerOficialNombreCompleto;
                                                }
                                            }
                                        }
                                    }
                                    $tieneOficiales = count($oficiales) > 0;
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

                                    {{-- Oficial: formato 2 líneas como imagen (clave + nombre (T1)) --}}
                                    <td class="px-1 py-1 md:py-1.5 text-left" style="width: 7rem; min-width: 7rem; max-width: 7rem;">
                                        <div class="flex items-start gap-1">
                                            <div class="oficial-texto flex-1 min-w-0 space-y-0.5 {{ !$tieneOficiales ? 'text-gray-400 italic' : '' }}"
                                                data-registro-id="{{ $registroId }}"
                                                data-oficiales-json="{{ $tieneOficiales ? json_encode($oficiales) : '[]' }}"
                                                title="{{ $tieneOficiales ? implode(', ', array_filter(array_map(function($o) { $n = trim($o['nombre'] ?? '') ?: trim($o['clave'] ?? ''); $t = $o['turno'] ?? ''; return $n ? $n . ($t ? ' (T'.$t.')' : '') : null; }, $oficiales))) : 'Sin oficiales' }}"
                                            >
                                                @if($tieneOficiales)
                                                    @foreach($oficiales as $of)
                                                        @php
                                                            $claveOf = $of['clave'] ?? '';
                                                            $nombreOf = trim($of['nombre'] ?? '') ?: trim($of['clave'] ?? '');
                                                            $turnoOf = $of['turno'] ?? '';
                                                            $nombreConTurno = ($nombreOf ?: '-') . ($turnoOf ? ' (T' . $turnoOf . ')' : '');
                                                        @endphp
                                                        <div class="oficial-item">
                                                            <div class="font-semibold text-gray-900 text-sm leading-tight">{{ $claveOf ?: '-' }}</div>
                                                            <div class="text-gray-600 text-xs leading-tight truncate">{{ $nombreConTurno }}</div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="text-xs">Sin oficiales</div>
                                                @endif
                                            </div>
                                            @php $cantidadOficiales = count($oficiales); @endphp
                                            <button
                                                type="button"
                                                class="btn-agregar-oficial flex-shrink-0 p-0.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors"
                                                data-registro-id="{{ $registroId }}"
                                                data-cantidad-oficiales="{{ $cantidadOficiales }}"
                                                title="Agregar oficial"
                                                aria-label="Agregar oficial"
                                            >
                                                <i class="fa-solid fa-pencil text-xs"></i>
                                            </button>
                                            <select
                                                data-field="turno"
                                                class="hidden"
                                                aria-hidden="true"
                                                tabindex="-1"
                                            >
                                                <option value="">-</option>
                                                <option value="1" {{ $turnoInicial == '1' ? 'selected' : '' }}>1</option>
                                                <option value="2" {{ $turnoInicial == '2' ? 'selected' : '' }}>2</option>
                                                <option value="3" {{ $turnoInicial == '3' ? 'selected' : '' }}>3</option>
                                                <option value="4" {{ $turnoInicial == '4' ? 'selected' : '' }}>4</option>
                                            </select>
                                        </div>
                                    </td>

                                    {{-- H. INICIO --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell">
                                        <div class="flex items-center justify-center gap-2 md:gap-3">
                                            <input
                                                type="time"
                                                data-field="h_inicio"
                                                class="flex-1 border border-gray-300 rounded px-1 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-24 md:w-28"
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
                                                class="flex-1 border border-gray-300 rounded px-1 py-1 text-base focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-24 md:w-28"
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
                                            min="0"
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
                                                data-accion="editar-cantidad"
                                                data-campo="temp_canoa1"
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
                                                data-accion="editar-cantidad"
                                                data-campo="temp_canoa2"
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
                                                data-accion="editar-cantidad"
                                                data-campo="tambor"
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
                                <td colspan="{{ $hasFinalizarPermission ? 16 : 15 }}" class="px-2 py-4 text-center text-gray-500 italic">
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
    <div id="createModal" class="hidden fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
                <h3 class="text-xl font-semibold">Nueva Formulación de Engomado</h3>
                <button type="button" data-accion="cerrar-formulacion" aria-label="Cerrar" class="text-white hover:text-gray-200 transition">
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
                            <select name="FolioProg" id="create_folio_prog" required data-accion-cambio="folio-formulacion" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
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
        class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center"
        style="display: none;"
    >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-3 max-h-[65vh] overflow-y-auto">
            <div class="px-3 md:px-4 py-2 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="text-sm md:text-base font-semibold text-gray-900">Oficiales</h3>
                <button type="button" id="btn-cerrar-modal" aria-label="Cerrar" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                    <i class="fa-solid fa-times text-lg"></i>
                </button>
            </div>

            <div id="modal-oficiales-lista" class="p-3 md:p-4">
                <input type="hidden" id="modal-registro-id" name="registro_id">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-2 py-1 text-left text-xs font-semibold text-gray-700 border border-gray-300 min-w-[120px]">No Operador</th>
                                <th class="px-2 py-1 text-left text-xs font-semibold text-gray-700 border border-gray-300 hidden">Nombre</th>
                                <th class="px-2 py-1 text-left text-xs font-semibold text-gray-700 border border-gray-300 w-16">Turno</th>
                                <th class="px-2 py-1 text-left text-xs font-semibold text-gray-700 border border-gray-300 w-20">Metros</th>
                                <th class="px-2 py-1 text-center text-xs font-semibold text-gray-700 border border-gray-300 w-14">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody id="oficiales-existentes" class="bg-white">
                            <!-- Se rellenan por JS -->
                        </tbody>
                    </table>
                </div>

                <div class="flex gap-2 justify-end mt-3">
                    <button
                        type="button"
                        id="btn-cancelar-modal"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        id="btn-guardar-oficiales"
                        class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors"
                    >
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Fila del modal de oficiales (una por oficial 1..3); la llena oficiales.ts. --}}
    <template id="tpl-fila-oficial">
        <tr class="hover:bg-gray-50">
            <td class="px-2 py-1 border border-gray-300 align-top">
                <div class="oficial-cell-wrapper flex items-start gap-1.5 min-h-[2.5rem]">
                    <div class="oficial-display flex-1 min-w-0">
                        <div class="font-semibold text-gray-900 text-xs leading-tight" data-parte="clave"></div>
                        <div class="text-gray-600 text-xs leading-tight truncate" data-parte="nombre"></div>
                    </div>
                    <select class="select-oficial-nombre w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500" aria-label="No. Operador">
                        <option value="">Seleccionar...</option>
                    </select>
                    <button type="button" class="btn-editar-oficial flex-shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded" title="Editar oficial" aria-label="Editar oficial">
                        <i class="fa-solid fa-pencil text-xs" aria-hidden="true"></i>
                    </button>
                </div>
                <input type="hidden" class="input-oficial-clave">
            </td>
            <td class="px-2 py-1 border border-gray-300 hidden">
                <input type="text" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs bg-gray-50 cursor-not-allowed input-oficial-nombre" readonly>
            </td>
            <td class="px-2 py-1 border border-gray-300">
                <select class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs focus:ring-1 focus:ring-blue-500 input-oficial-turno" aria-label="Turno">
                    <option value="">-</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </td>
            <td class="px-2 py-1 border border-gray-300">
                <input type="number" step="0.01" min="0" aria-label="Metros"
                    class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-blue-500 input-oficial-metros" placeholder="0">
            </td>
            <td class="px-2 py-1 border border-gray-300 text-center">
                <button type="button" class="btn-eliminar-oficial p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded" title="Eliminar oficial" aria-label="Eliminar oficial">
                    <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                </button>
            </td>
        </tr>
    </template>

    @push('scripts')
        @vite('resources/js/modulos/engomado/produccion/index.ts')
    @endpush

    @if(isset($orden) && $orden)
        @include('modulos.engomado.partials.modal-calificar-julios')
    @endif
@endsection
