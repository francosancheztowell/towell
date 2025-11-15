@extends('layouts.app')

@section('page-title', 'Producción de Engomado')

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

    <div class="w-full">
        <!-- Sección superior: Información General -->
        <div class="bg-white p-1">
            <div class="grid grid-cols-12 gap-2 items-stretch">
                <!-- Columna 1 -->
                <div class="col-span-12 md:col-span-3 flex flex-col space-y-2">
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
                <div class="col-span-12 md:col-span-3 flex flex-col space-y-2">
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
                <div class="col-span-12 md:col-span-3 flex flex-col space-y-2">
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

                <!-- Columna 4 -->
                <div class="col-span-12 md:col-span-3 flex flex-col space-y-2">
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
            <div class="overflow-x-auto max-h-[62vh] overflow-y-auto">
                <table class="divide-y divide-gray-200 text-sm w-full">
                    <thead class="bg-blue-500 text-white sticky top-0 z-20">
                        <tr>
                            <th class="py-2 px-1 text-center font-semibold sticky left-0 bg-blue-500 z-30 text-xs md:text-sm w-24 max-w-[60px]">Fecha</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-32 max-w-[120px]">Oficial</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-16 max-w-[50px]">Turno</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm hidden lg:table-cell w-32 max-w-[110px]">H. Inicio</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm hidden lg:table-cell w-32 max-w-[110px]">H. Final</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-24 max-w-[90px]">Julio</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Kg. Bruto</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Tara</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Kg. Neto</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Metros</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-28 max-w-[90px]">Sol. Can.</th>
                            <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-xs">Canoa 1</th>
                            <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-xs">Canoa 2</th>
                            <th class="py-2 px-1 md:px-2 text-center font-semibold bg-blue-700 text-xs md:text-xs hidden">Tambor</th>
                            <th class="py-2 px-1 md:px-2 text-center font-semibold text-xs md:text-sm hidden">Humedad</th>
                            <th class="py-2 px-1 text-center font-semibold text-xs md:text-sm w-24 max-w-[75px]">Roturas</th>
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

                                    $metros = '';
                                    if ($registro) {
                                        $metros1 = isset($registro->Metros1) && $registro->Metros1 !== null ? (float)$registro->Metros1 : 0;
                                        $metros2 = isset($registro->Metros2) && $registro->Metros2 !== null ? (float)$registro->Metros2 : 0;
                                        $metros3 = isset($registro->Metros3) && $registro->Metros3 !== null ? (float)$registro->Metros3 : 0;
                                        $sumaMetros = $metros1 + $metros2 + $metros3;
                                        $metros = $sumaMetros > 0 ? $sumaMetros : '';
                                    }

                                    $tempCanoa1 = $registro && $registro->Canoa1 !== null ? (int)$registro->Canoa1 : 0;
                                    $tempCanoa2 = $registro && $registro->Canoa2 !== null ? (int)$registro->Canoa2 : 0;
                                    $tambor = $registro && $registro->Tambor !== null ? (int)$registro->Tambor : 0;
                                    $humedad = $registro ? ($registro->Humedad ?? '') : '';
                                    $roturas = $registro ? ($registro->Roturas ?? '') : '';
                                    $registroId = $registro ? $registro->Id : null;

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
                                    $turnoInicial = '';
                                @endphp

                                <tr class="hover:bg-gray-50" data-registro-id="{{ $registroId }}">
                                    {{-- Fecha --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap sticky left-0 bg-white z-10 border-r border-gray-200 w-24 max-w-[60px]">
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
                                                class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs bg-white hover:bg-gray-50 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 btn-fecha-display flex items-center justify-center cursor-pointer"
                                                data-registro-id="{{ $registroId }}"
                                            >
                                                <span class="fecha-display-text text-gray-900 font-medium">
                                                    {{ $fechaMostrar }}
                                                </span>
                                            </button>
                                        </div>
                                    </td>

                                    {{-- Oficial --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-32 max-w-[120px]">
                                        <div class="flex items-center justify-center gap-1">
                                            @if($tieneOficiales)
                                                <select
                                                    data-field="oficial"
                                                    class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500 oficial-select"
                                                    data-registro-id="{{ $registroId }}"
                                                >
                                                    <option value="">Seleccionar oficial...</option>
                                                    @foreach($oficiales as $index => $oficial)
                                                        <option
                                                            value="{{ $oficial['numero'] }}"
                                                            data-numero="{{ $oficial['numero'] }}"
                                                            data-nombre="{{ $oficial['nombre'] }}"
                                                            data-clave="{{ $oficial['clave'] }}"
                                                            data-metros="{{ $oficial['metros'] }}"
                                                            data-turno="{{ $oficial['turno'] }}"
                                                            {{ $index === 0 ? 'selected' : '' }}
                                                        >
                                                            {{ $oficial['nombre'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <div class="w-full flex items-center justify-center text-gray-400">
                                                    <span class="text-xs italic">Sin oficiales</span>
                                                </div>
                                            @endif

                                            @php
                                                $cantidadOficiales = count($oficiales);
                                                $puedeAgregar = $cantidadOficiales < 3;
                                            @endphp
                                            <button
                                                type="button"
                                                class="btn-agregar-oficial flex-shrink-0 p-1.5 {{ $puedeAgregar ? 'text-blue-600 hover:text-blue-800 hover:bg-blue-50' : 'text-gray-400 cursor-not-allowed opacity-50' }} rounded transition-colors"
                                                data-registro-id="{{ $registroId }}"
                                                data-cantidad-oficiales="{{ $cantidadOficiales }}"
                                                title="{{ $puedeAgregar ? 'Agregar oficial' : 'Máximo de 3 oficiales alcanzado' }}"
                                                {{ !$puedeAgregar ? 'disabled' : '' }}
                                            >
                                                <i class="fa-solid fa-plus-circle text-lg"></i>
                                            </button>
                                        </div>
                                    </td>

                                    {{-- Turno --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-16 max-w-[50px]">
                                        <select
                                            data-field="turno"
                                            class="w-full border border-gray-300 rounded px-1 py-0.5 text-sm text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
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
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell w-32 max-w-[115px]">
                                        <div class="flex items-center justify-center gap-1">
                                            <input
                                                type="time"
                                                data-field="h_inicio"
                                                class="flex-1 border border-gray-300 rounded px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                value="{{ $horaInicio }}"
                                            >
                                            <i
                                                class="fa-solid fa-clock text-gray-400 text-sm cursor-pointer hover:text-blue-500 set-current-time flex-shrink-0"
                                                data-time-target="h_inicio"
                                                title="Establecer hora actual"
                                            ></i>
                                        </div>
                                    </td>

                                    {{-- H. FIN --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap hidden lg:table-cell w-32 max-w-[115px]">
                                        <div class="flex items-center justify-center gap-1">
                                            <input
                                                type="time"
                                                data-field="h_fin"
                                                class="flex-1 border border-gray-300 rounded px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                value="{{ $horaFin }}"
                                            >
                                            <i
                                                class="fa-solid fa-clock text-gray-400 text-sm cursor-pointer hover:text-blue-500 set-current-time flex-shrink-0"
                                                data-time-target="h_fin"
                                                title="Establecer hora actual"
                                            ></i>
                                        </div>
                                    </td>

                                    {{-- No. Julio --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-24 max-w-[90px]">
                                        <select
                                            data-field="no_julio"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500 select-julio"
                                            data-valor-inicial="{{ $noJulio }}"
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
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
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
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $tara }}"
                                        >
                                    </td>

                                    {{-- Kg Neto --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            step="0.01"
                                            data-field="kg_neto"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center bg-gray-50 text-gray-600 cursor-not-allowed"
                                            value="{{ $kgNeto }}"
                                            readonly
                                        >
                                    </td>

                                    {{-- Metros --}}
                                    <td class="px-1 py-1 md:py-1.5 text-center whitespace-nowrap w-28 max-w-[90px]">
                                        <input
                                            type="number"
                                            disabled
                                            data-field="metros"
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
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
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $solidos }}"
                                        >
                                    </td>

                                    {{-- Temperatura Canoa 1 --}}
                                    <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap bg-blue-50">
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
                                    <td class="px-1 md:px-2 py-1 md:py-1.5 text-center whitespace-nowrap bg-blue-50">
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
                                            class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            value="{{ $roturas }}"
                                        >
                                    </td>
                                </tr>
                            @endfor
                        @else
                            <tr>
                                <td colspan="16" class="px-2 py-4 text-center text-gray-500 italic">
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
                tambor: 'Tambor',
                humedad: 'Humedad',
                roturas: 'Roturas'
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

                netoInput.value = neto.toFixed(2);
            }

            window.toggleQuantityEdit = function (element) {
                const cell = element.closest('td');
                const editContainer = cell.querySelector('.quantity-edit-container');
                const editBtn = cell.querySelector('.edit-quantity-btn');
                const quantityDisplay = cell.querySelector('.quantity-display');

                closeAllQuantityEditors();

                if (!editContainer || !editBtn) return;

                const wasHidden = editContainer.classList.contains('hidden');
                editContainer.classList.toggle('hidden');
                editBtn.classList.toggle('hidden');

                if (wasHidden && quantityDisplay) {
                    let currentValue = quantityDisplay.textContent.trim();
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
                        const editBtn = row ? row.querySelector('.edit-quantity-btn') : null;
                        container.classList.add('hidden');
                        if (editBtn) editBtn.classList.remove('hidden');
                    }
                });
            }

            function verificarOficialSeleccionado(registroId) {
                const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                if (!row) return false;
                const oficialSelect = row.querySelector('.oficial-select');
                if (!oficialSelect) return false;

                return !!oficialSelect.value;
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
                            const oficialSelect = row.querySelector('.oficial-select');
                            if (oficialSelect && oficialSelect.value === String(numeroOficial)) {
                                const selectedOption = oficialSelect.options[oficialSelect.selectedIndex];
                                if (selectedOption) {
                                    selectedOption.setAttribute('data-turno', turno);
                                }
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
                                ? (campo === 'Roturas' ? parseInt(valor) : parseFloat(valor))
                                : null
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
                    console.error('Error al actualizar campo de producción:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al actualizar campo. Por favor, intenta nuevamente.'
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

                    const catalogosJulios = result.data;
                    const selectJulios = document.querySelectorAll('.select-julio');

                    selectJulios.forEach(select => {
                        const valorInicial = select.getAttribute('data-valor-inicial');

                        while (select.options.length > 1) {
                            select.remove(1);
                        }

                        catalogosJulios.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.julio;
                            option.setAttribute('data-tara', item.tara || '0');
                            option.textContent = item.julio;

                            if (valorInicial && String(item.julio) === String(valorInicial)) {
                                option.selected = true;
                            }

                            select.appendChild(option);
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

            function poblarSelectUsuarios(selectElement, claveSeleccionada) {
                if (!selectElement || !usuariosEngomado.length) return;

                while (selectElement.options.length > 1) {
                    selectElement.remove(1);
                }

                let usuarioSeleccionado = null;

                usuariosEngomado.forEach(usuario => {
                    const option = document.createElement('option');
                    option.value = usuario.numero_empleado;
                    option.textContent = usuario.nombre;
                    option.setAttribute('data-numero-empleado', usuario.numero_empleado);
                    option.setAttribute('data-nombre', usuario.nombre);
                    option.setAttribute('data-turno', usuario.turno || '');

                    if (claveSeleccionada && usuario.numero_empleado === claveSeleccionada) {
                        option.selected = true;
                        usuarioSeleccionado = usuario;
                    }

                    selectElement.appendChild(option);
                });

                if (usuarioSeleccionado) {
                    const numero = selectElement.getAttribute('data-numero');
                    const nombreInput = document.querySelector(`input.input-oficial-nombre[data-numero="${numero}"]`);
                    if (nombreInput) {
                        nombreInput.value = usuarioSeleccionado.nombre;
                    }

                    if (usuarioSeleccionado.turno) {
                        const turnoSelect = document.querySelector(`select.input-oficial-turno[data-numero="${numero}"]`);
                        if (turnoSelect) {
                            turnoSelect.value = usuarioSeleccionado.turno;
                        }
                    }
                }
            }

            function renderizarOficialesExistentes(registroId) {
                const row = document.querySelector(`tr[data-registro-id="${registroId}"]`);
                if (!row) return;

                const selectOficial = row.querySelector('.oficial-select');
                const containerOficiales = document.getElementById('oficiales-existentes');
                const modalOficialesLista = document.getElementById('modal-oficiales-lista');
                if (!containerOficiales) return;

                const oficiales = [];
                if (selectOficial && selectOficial.options.length > 1) {
                    Array.from(selectOficial.options).forEach(option => {
                        if (option.value) {
                            oficiales.push({
                                numero: option.value,
                                nombre: option.getAttribute('data-nombre') || option.textContent,
                                clave: option.getAttribute('data-clave') || '',
                                metros: option.getAttribute('data-metros') || '',
                                turno: option.getAttribute('data-turno') || ''
                            });
                        }
                    });
                }

                containerOficiales.innerHTML = '';

                for (let i = 1; i <= 3; i++) {
                    const oficial = oficiales.find(o => parseInt(o.numero) === i) || {
                        numero: i,
                        nombre: '',
                        clave: i === 1 ? '{{ $usuarioClave }}' : '',
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
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm bg-gray-50 cursor-not-allowed input-oficial-nombre"
                                data-numero="${i}"
                                value="${oficial.nombre || ''}"
                                placeholder="Se selecciona automáticamente"
                                readonly
                            >
                        </td>
                        <td class="px-3 py-2 border border-gray-300">
                            <select
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-oficial-turno"
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
                                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-oficial-metros"
                                data-numero="${i}"
                                value="${oficial.metros || ''}"
                                placeholder="0.00"
                            >
                        </td>
                    `;
                    containerOficiales.appendChild(tr);

                    const selectNombre = tr.querySelector('.select-oficial-nombre');
                    if (selectNombre) {
                        poblarSelectUsuarios(selectNombre, oficial.clave);
                    }
                }

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

            function cerrarModalOficial() {
                modalOficial.classList.add('hidden');
                modalOficial.style.display = 'none';
                const containerOficiales = document.getElementById('oficiales-existentes');
                if (containerOficiales) {
                    containerOficiales.innerHTML = '';
                }
            }

            // ===== DOM Ready =====
            document.addEventListener('DOMContentLoaded', function () {
                const tablaBody = document.getElementById('tabla-produccion-body');

                if (tablaBody) {
                    // Precalcular netos
                    tablaBody.querySelectorAll('tr').forEach(calcularNeto);

                    // Inicializar valor anterior del oficial en cada fila y actualizar turno
                    tablaBody.querySelectorAll('tr').forEach(row => {
                        const oficialSelect = row.querySelector('.oficial-select');
                        if (oficialSelect && oficialSelect.value) {
                            oficialSelect.setAttribute('data-oficial-anterior', oficialSelect.value);

                            // Actualizar turno automáticamente si hay un oficial seleccionado
                            const selectedOption = oficialSelect.options[oficialSelect.selectedIndex];
                            if (selectedOption && selectedOption.value) {
                                const turnoSelect = row.querySelector('select[data-field="turno"]');
                                if (turnoSelect) {
                                    const turno = selectedOption.getAttribute('data-turno');
                                    if (turno) {
                                        turnoSelect.value = turno;
                                    }
                                }
                            }
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

                            actualizarJulioTara(registroId, noJulioValue, tara, kgNeto);
                        }

                        // Oficial -> actualiza turno y limpia campos
                        if (target.classList.contains('oficial-select')) {
                            const turnoSelect = row.querySelector('select[data-field="turno"]');
                            const selectedOption = target.options[target.selectedIndex];
                            if (!turnoSelect) return;

                            // Guardar el valor anterior del oficial
                            const valorAnterior = target.getAttribute('data-oficial-anterior') || '';
                            const valorActual = selectedOption ? selectedOption.value : '';

                            // Si cambió de oficial (y no es la primera carga), limpiar campos
                            if (valorAnterior && valorAnterior !== valorActual) {
                                // Limpiar campos relacionados
                                const hInicioInput = row.querySelector('input[data-field="h_inicio"]');
                                const hFinInput = row.querySelector('input[data-field="h_fin"]');
                                const julioSelect = row.querySelector('select.select-julio');
                                const kgBrutoInput = row.querySelector('input[data-field="kg_bruto"]');
                                const taraInput = row.querySelector('input[data-field="tara"]');
                                const kgNetoInput = row.querySelector('input[data-field="kg_neto"]');
                                const solidosInput = row.querySelector('input[data-field="solidos"]');
                                const roturasInput = row.querySelector('input[data-field="roturas"]');
                                const canoa1Btn = row.querySelector('button[onclick*="temp_canoa1"]');
                                const canoa2Btn = row.querySelector('button[onclick*="temp_canoa2"]');

                                if (hInicioInput) hInicioInput.value = '';
                                if (hFinInput) hFinInput.value = '';
                                if (julioSelect) {
                                    const valorInicial = julioSelect.getAttribute('data-valor-inicial') || '';
                                    julioSelect.value = valorInicial;
                                }
                                if (kgBrutoInput) kgBrutoInput.value = '';
                                if (taraInput) taraInput.value = '';
                                if (kgNetoInput) kgNetoInput.value = '';
                                if (solidosInput) solidosInput.value = '';
                                if (roturasInput) roturasInput.value = '';
                                if (canoa1Btn) {
                                    const display1 = canoa1Btn.querySelector('.quantity-display');
                                    if (display1) display1.textContent = '0';
                                }
                                if (canoa2Btn) {
                                    const display2 = canoa2Btn.querySelector('.quantity-display');
                                    if (display2) display2.textContent = '0';
                                }

                                // Recalcular neto
                                calcularNeto(row);
                            }

                            // Actualizar el valor anterior
                            target.setAttribute('data-oficial-anterior', valorActual);

                            if (selectedOption && selectedOption.value) {
                                const turno = selectedOption.getAttribute('data-turno');
                                turnoSelect.value = turno || '';
                            } else {
                                turnoSelect.value = '';
                            }
                        }

                        // Cambio manual de turno
                        if (field === 'turno' && target.tagName === 'SELECT') {
                            const turnoValue = target.value;
                            const oficialSelect = row.querySelector('.oficial-select');

                            if (oficialSelect && oficialSelect.value && turnoValue) {
                                const numeroOficial = oficialSelect.value;
                                actualizarTurnoOficial(registroId, numeroOficial, turnoValue);
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
                            actualizarHora(registroId, field === 'h_inicio' ? 'HoraInicial' : 'HoraFinal', horaValue);
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
                    if (!isInsideEditor && !isEditButton) {
                        closeAllQuantityEditors();
                    }
                });

                // Click en número (Canoa1, Canoa2, Tambor)
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
                        const fieldName = quantityDisplay.getAttribute('data-field');
                        const registroId = row ? row.getAttribute('data-registro-id') : null;
                        if (!registroId || !fieldName || !campoMap[fieldName]) return;

                        if (!verificarOficialSeleccionado(registroId)) {
                            mostrarAlertaOficialRequerido();
                            return;
                        }

                        const valorAnterior = quantityDisplay.textContent.trim();
                        quantityDisplay.textContent = selectedValue;

                        actualizarCampoProduccion(registroId, campoMap[fieldName], selectedValue)
                            .catch(() => {
                                quantityDisplay.textContent = valorAnterior;
                            });
                    }

                    const editContainer = cell.querySelector('.quantity-edit-container');
                    const editBtn = cell.querySelector('.edit-quantity-btn');
                    if (editContainer) editContainer.classList.add('hidden');
                    if (editBtn) editBtn.classList.remove('hidden');
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
                    if (!timeInput) return;

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
                });

                // Abrir modal oficiales
                document.addEventListener('click', function (e) {
                    const btnAgregar = e.target.closest('.btn-agregar-oficial');
                    if (!btnAgregar) return;

                    e.preventDefault();
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

                    if (selectedOption && selectedOption.value) {
                        const numeroEmpleado = selectedOption.value;
                        const nombre = selectedOption.getAttribute('data-nombre') || selectedOption.textContent;
                        const turno  = selectedOption.getAttribute('data-turno') || '';

                        if (claveInput)  claveInput.value  = numeroEmpleado;
                        if (nombreInput) nombreInput.value = nombre;
                        if (turnoSelect && turno) turnoSelect.value = turno;
                    } else {
                        if (claveInput)  claveInput.value  = '';
                        if (nombreInput) nombreInput.value = '';
                        if (turnoSelect) turnoSelect.value = '';
                    }
                });

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

                        try {
                            let guardados = 0;
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
                                }
                            }

                            if (guardados > 0) {
                                window.location.reload();
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

            function validarRegistrosCompletos() {
                const tablaBody = document.getElementById('tabla-produccion-body');
                if (!tablaBody) return { valido: false, mensaje: 'No se encontró la tabla de producción' };

                const filas = tablaBody.querySelectorAll('tr[data-registro-id]');
                const registrosIncompletos = [];

                filas.forEach((fila, index) => {
                    const registroId = fila.getAttribute('data-registro-id');
                    const camposFaltantes = [];

                    // Fecha
                    const fechaInput = fila.querySelector('input.input-fecha');
                    if (!fechaInput || !fechaInput.value) {
                        camposFaltantes.push('Fecha');
                    }

                    // Oficial (requerido)
                    const oficialSelect = fila.querySelector('.oficial-select');
                    if (!oficialSelect || !oficialSelect.value) {
                        camposFaltantes.push('Oficial');
                    }

                    // Turno
                    const turnoSelect = fila.querySelector('select[data-field="turno"]');
                    if (!turnoSelect || !turnoSelect.value) {
                        camposFaltantes.push('Turno');
                    }

                    // H. Inicio
                    const hInicioInput = fila.querySelector('input[data-field="h_inicio"]');
                    if (!hInicioInput || !hInicioInput.value) {
                        camposFaltantes.push('H. Inicio');
                    }

                    // H. Fin
                    const hFinInput = fila.querySelector('input[data-field="h_fin"]');
                    if (!hFinInput || !hFinInput.value) {
                        camposFaltantes.push('H. Fin');
                    }

                    // Julio
                    const julioSelect = fila.querySelector('select[data-field="no_julio"]');
                    if (!julioSelect || !julioSelect.value) {
                        camposFaltantes.push('Julio');
                    }

                    // Kg. Bruto
                    const kgBrutoInput = fila.querySelector('input[data-field="kg_bruto"]');
                    if (!kgBrutoInput || !kgBrutoInput.value || kgBrutoInput.value.trim() === '') {
                        camposFaltantes.push('Kg. Bruto');
                    }

                    // Tara
                    const taraInput = fila.querySelector('input[data-field="tara"]');
                    if (!taraInput || !taraInput.value || taraInput.value.trim() === '') {
                        camposFaltantes.push('Tara');
                    }

                    // Metros
                    const metrosInput = fila.querySelector('input[data-field="metros"]');
                    if (!metrosInput || !metrosInput.value || metrosInput.value.trim() === '') {
                        camposFaltantes.push('Metros');
                    }

                    // Sólidos, Canoa 1, Canoa 2 y Roturas son opcionales, no se validan

                    if (camposFaltantes.length > 0) {
                        registrosIncompletos.push({
                            fila: index + 1,
                            campos: camposFaltantes
                        });
                    }
                });

                if (registrosIncompletos.length > 0) {
                    return { valido: false, mensaje: 'Completa todos los registros' };
                }

                return { valido: true };
            }

            // ===== Acción Finalizar =====
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
