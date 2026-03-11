{{-- ============================================================
     _tabla-registros.blade.php
     Tabla principal de registros de producción de urdido con todos
     los campos editables: Fin, Fecha, No. Empleado (oficiales),
     H. Inicio, H. Fin, No. Julio, Hilos, Kg. Bruto, Tara,
     Kg. Neto, Metros, Roturas (Hilat., Maq., Operac., Transf.),
     y campos Karl Mayer (Vueltas, Diámetro).
     Variables requeridas: $hasFinalizarPermission, $canEdit,
     $isKarlMayer, $totalRegistros, $julios, $registrosProduccion
     ============================================================ --}}
@php
    $canEdit = $canEdit ?? false;
@endphp
@once
<style>
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
@endonce
    <!-- Sección inferior: Tabla de Producción -->
    <div class="bg-white shadow-md overflow-hidden {{ $canEdit ? '' : 'produccion-solo-lectura' }}" data-can-edit="{{ $canEdit ? '1' : '0' }}">
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
                                            min="0"
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
                                min="0"
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
                                min="0"
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
