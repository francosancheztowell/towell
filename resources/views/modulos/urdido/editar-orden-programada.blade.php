@extends('layouts.app')

@section('page-title', 'Editar Orden Urdido')


@section('navbar-right')
    @php
        $statusClass = match($orden->Status ?? '') {
            'Finalizado' => 'bg-green-100 text-green-800',
            'En Proceso' => 'bg-yellow-100 text-yellow-800',
            'Programado' => 'bg-blue-100 text-blue-800 ',
            'Cancelado' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    @endphp
    <div class="flex items-center gap-2">
        <span class="px-3 py-2 text-md font-bold rounded-full {{ $statusClass }}">
            {{ $orden->Status ?? '-' }}
        </span>
        @if(($axUrdido ?? 0) === 1)
            <span class="px-2 py-1 text-sm font-bold rounded-full bg-red-600 text-white">AX Urdido</span>
        @endif
    </div>
@endsection



@section('content')
    <div class="w-full">
        @php
            $statusActual = trim($orden->Status ?? '');
            $esEnProcesoOProgramadoOFinalizado = in_array($statusActual, ['En Proceso', 'Programado', 'Finalizado'], true);
            $esEnProcesoOProgramado = in_array($statusActual, ['En Proceso', 'Programado'], true);
        @endphp

        <!-- Información de la Orden -->
        <div class="bg-white  p-3 mb-4">

            <div class="grid gap-1.5" style="display: grid; grid-template-columns: 0.7fr 1.2fr 0.7fr 0.7fr 0.7fr 0.7fr 0.7fr;">
                <!-- Folio (solo lectura - NO EDITABLE) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        value="{{ $orden->Folio }}"
                        readonly
                        disabled
                        class="w-full px-1.5 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed"
                        title="El folio no se puede editar"
                    >
                </div>

                <!-- Folio Consumo -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio Consumo</label>
                    <input
                        type="text"
                        id="campo_FolioConsumo"
                        data-campo="FolioConsumo"
                        value="{{ $orden->FolioConsumo ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- No Telar -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">No. Telar</label>
                    <input
                        type="text"
                        id="campo_NoTelarId"
                        data-campo="NoTelarId"
                        value="{{ $orden->NoTelarId ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Rizo/Pie o Barras (Karl Mayer) -->
                <div id="contenedor-tipo-barras">
                    <label id="label-tipo-barras" class="block text-sm font-semibold text-gray-700 mb-0.5">{{ ($isKarlMayer ?? false) ? 'Barras' : 'Tipo' }}</label>
                    <select
                        id="campo_RizoPie"
                        data-campo="RizoPie"
                        data-es-karl-mayer="{{ ($isKarlMayer ?? false) ? '1' : '0' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">Seleccionar...</option>
                        @if($isKarlMayer ?? false)
                            @foreach([1, 2, 3, 4] as $v)
                                <option value="{{ $v }}" {{ (string)($orden->RizoPie ?? '') === (string)$v ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        @else
                            <option value="Rizo" {{ $orden->RizoPie === 'Rizo' ? 'selected' : '' }}>Rizo</option>
                            <option value="Pie" {{ $orden->RizoPie === 'Pie' ? 'selected' : '' }}>Pie</option>
                        @endif
                    </select>
                </div>

                <!-- Cuenta -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuenta</label>
                    <input
                        type="number"
                        id="campo_Cuenta"
                        data-campo="Cuenta"
                        value="{{ $orden->Cuenta ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Calibre -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Calibre</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Calibre"
                        data-campo="Calibre"
                        value="{{ $orden->Calibre ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Metros -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metros</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Metros"
                        data-campo="Metros"
                        value="{{ $orden->Metros ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>
            </div>

            <div class="mt-1.5 grid gap-1.5" style="display: grid; grid-template-columns: repeat(9, minmax(0, 1fr));">

                <!-- Kilos (solo lectura - NO EDITABLE) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Kilos</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Kilos"
                        value="{{ $orden->Kilos ?? '' }}"
                        readonly
                        disabled
                        class="w-full px-1.5 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed"
                        title="Kilos no se puede editar"
                    >
                </div>

                <!-- Fibra -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fibra</label>
                    <select
                        id="campo_Fibra"
                        data-campo="Fibra"
                        data-valor-actual="{{ $orden->Fibra ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                    </select>
                </div>

                <!-- Salón de Tejido -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Salón de Tejido</label>
                    <select
                        id="campo_SalonTejidoId"
                        data-campo="SalonTejidoId"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        <option value="JACQUARD" {{ ($orden->SalonTejidoId ?? '') === 'JACQUARD' ? 'selected' : '' }}>JACQUARD</option>
                        <option value="SMIT" {{ ($orden->SalonTejidoId ?? '') === 'SMIT' ? 'selected' : '' }}>SMIT</option>
                        <option value="Karl Mayer" {{ ($orden->SalonTejidoId ?? '') === 'Karl Mayer' ? 'selected' : '' }}>Karl Mayer</option>
                    </select>
                </div>

                <!-- Máquina -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Máquina</label>
                    <select
                        id="campo_MaquinaId"
                        data-campo="MaquinaId"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        @php
                            $maquinaValor = $orden->MaquinaId ?? '';
                            $yaIncluyeKarlMayer = $maquinas->contains(fn($m) => stripos(($m->MaquinaId ?? '').($m->Nombre ?? ''), 'karl') !== false);
                        @endphp
                        @if(!$yaIncluyeKarlMayer)
                            <option value="Karl Mayer" {{ $maquinaValor === 'Karl Mayer' ? 'selected' : '' }}>Karl Mayer</option>
                        @endif
                        @foreach($maquinas as $maquina)
                            @php
                                $esSeleccionada = ($maquinaValor === $maquina->MaquinaId) ||
                                                  ($maquinaValor === ($maquina->Nombre ?? ''));
                            @endphp
                            <option value="{{ $maquina->MaquinaId }}"
                                    {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $maquina->Nombre ?? $maquina->MaquinaId }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Fecha Programada -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fecha Programada</label>
                    <input
                        type="date"
                        id="campo_FechaProg"
                        data-campo="FechaProg"
                        value="{{ $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Tipo Atado -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo Atado</label>
                    <select
                        id="campo_TipoAtado"
                        data-campo="TipoAtado"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        @php
                            $tipoAtadoValor = strtolower(trim($orden->TipoAtado ?? ''));
                        @endphp
                        <option value="Normal" {{ $tipoAtadoValor === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="Especial" {{ $tipoAtadoValor === 'especial' ? 'selected' : '' }}>Especial</option>
                    </select>
                </div>

                <!-- Lote Proveedor -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Lote Proveedor</label>
                    <input
                        type="text"
                        id="campo_LoteProveedor"
                        data-campo="LoteProveedor"
                        value="{{ $orden->LoteProveedor ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Bom Urdido -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Urdido</label>
                    <input
                        type="text"
                        id="campo_BomId"
                        data-campo="BomId"
                        data-bom-autocomplete="urdido"
                        value="{{ $orden->BomId ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        autocomplete="off"
                    >
                </div>

                <!-- Tamaño -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tamaño</label>
                    <input
                        type="text"
                        id="campo_InventSizeId"
                        data-campo="InventSizeId"
                        data-valor-actual="{{ $orden->InventSizeId ?? '' }}"
                        value="{{ $orden->InventSizeId ?? '' }}"
                        list="listaTamanos"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>
            <datalist id="listaTamanos"></datalist>

            @php
                $statusActual = trim($orden->Status ?? '');
                $esFinalizado = $statusActual === 'Finalizado';
                $mostrarTablaProduccion = in_array($statusActual, ['Finalizado', 'En Proceso'], true);
            @endphp

            {{-- Julios, Hilos y Observaciones (arriba; más chico cuando Finalizado) --}}
            <div class="mt-2 grid gap-2 {{ $mostrarTablaProduccion ? 'max-w-2xl' : '' }}" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
                <!-- Julios y Hilos -->
                <div>
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="min-w-full {{ $mostrarTablaProduccion ? 'text-sm' : 'text-sm' }}">
                            <thead class="bg-blue-500 text-white">
                                <tr>
                                    <th class="{{ $mostrarTablaProduccion ? 'px-1 py-0.5 text-sm' : 'px-2 py-1.5' }} text-center font-semibold">No. Julio</th>
                                    <th class="{{ $mostrarTablaProduccion ? 'px-1 py-0.5 text-sm' : 'px-2 py-1.5' }} text-center font-semibold">Hilos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @php $juliosRows = $julios->values(); @endphp
                                @for ($i = 0; $i < 4; $i++)
                                    @php $row = $juliosRows[$i] ?? null; @endphp
                                    <tr data-julio-row="{{ $i }}" data-julio-id="{{ $row->Id ?? '' }}">
                                        <td class="{{ $mostrarTablaProduccion ? 'px-1 py-0.5' : 'px-2 py-1.5' }} text-center">
                                            <input type="number" min="1" step="1" data-field="no_julio" value="{{ $row->Julios ?? '' }}"
                                                class="campo-julio w-full {{ $mostrarTablaProduccion ? 'px-1 py-0.5 text-sm' : 'px-2 py-1.5' }} border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </td>
                                        <td class="{{ $mostrarTablaProduccion ? 'px-1 py-0.5' : 'px-2 py-1.5' }} text-center">
                                            <input type="number" min="1" step="1" data-field="hilos" value="{{ $row->Hilos ?? '' }}"
                                                class="campo-julio w-full {{ $mostrarTablaProduccion ? 'px-1 py-0.5 text-sm' : 'px-2 py-1.5' }} border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Observaciones -->
                <div style="height: 100%; display: flex; flex-direction: column;">
                    <label class="block {{ $mostrarTablaProduccion ? 'text-[10px]' : 'text-sm' }} font-semibold text-gray-700 mb-1">Observaciones</label>
                    <textarea id="campo_Observaciones" data-campo="Observaciones" rows="{{ $mostrarTablaProduccion ? 2 : 3 }}"
                        class="campo-editable w-full {{ $mostrarTablaProduccion ? 'px-1 py-0.5 text-sm' : 'px-2 py-1.5' }} border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        style="flex: 1 1 auto; resize: none;">{{ $orden->Observaciones ?? '' }}</textarea>
                </div>
            </div>

            @if($mostrarTablaProduccion)
                {{-- Tabla de producción UrdProduccionUrdido (abajo, solo cuando Finalizado) --}}
                <div id="tabla-produccion-container" class="mt-2 overflow-x-auto border border-gray-200 rounded">
                    <table class="min-w-full text-sm" id="tabla-produccion">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-1 py-1 text-left font-semibold text-sm">No. Empleado</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">H. Inicio</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">H. Fin</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">No. Julio</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Hilos</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Kg. Bruto</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Tara</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Kg. Neto</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Metros</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Hilat.</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Maq.</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Operac.</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Transf.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @php $registrosProduccion = $registrosProduccion ?? collect(); @endphp
                            @forelse($registrosProduccion as $r)
                                @php
                                    $reg = optional($r);
                                    $metros = (float)($reg->Metros1 ?? 0) + (float)($reg->Metros2 ?? 0) + (float)($reg->Metros3 ?? 0);
                                    $kgNeto = $reg->KgNeto ?? '';
                                    $empleados = array_filter([
                                        trim($reg->CveEmpl1 ?? ''),
                                        trim($reg->CveEmpl2 ?? ''),
                                        trim($reg->CveEmpl3 ?? ''),
                                    ]);
                                    $empleadoTexto = count($empleados) > 0 ? implode(', ', $empleados) : '-';
                                    $infoOficial = [];
                                    if (trim($reg->NomEmpl1 ?? '')) $infoOficial[] = ['nombre' => trim($reg->NomEmpl1 ?? ''), 'turno' => $reg->Turno1 ?? null, 'metros' => $reg->Metros1 ?? null];
                                    if (trim($reg->NomEmpl2 ?? '')) $infoOficial[] = ['nombre' => trim($reg->NomEmpl2 ?? ''), 'turno' => $reg->Turno2 ?? null, 'metros' => $reg->Metros2 ?? null];
                                    if (trim($reg->NomEmpl3 ?? '')) $infoOficial[] = ['nombre' => trim($reg->NomEmpl3 ?? ''), 'turno' => $reg->Turno3 ?? null, 'metros' => $reg->Metros3 ?? null];
                                    $oficialesEdicion = [];
                                    for ($i = 1; $i <= 3; $i++) {
                                        $cve = trim((string) ($reg->{"CveEmpl{$i}"} ?? ''));
                                        $nom = trim((string) ($reg->{"NomEmpl{$i}"} ?? ''));
                                        $turnoVal = $reg->{"Turno{$i}"} ?? null;
                                        $metrosVal = $reg->{"Metros{$i}"} ?? null;
                                        if ($cve !== '' || $nom !== '' || $turnoVal !== null || $metrosVal !== null) {
                                            $oficialesEdicion[] = [
                                                'numero' => $i,
                                                'cve' => $cve !== '' ? $cve : null,
                                                'nombre' => $nom !== '' ? $nom : null,
                                                'turno' => $turnoVal !== null && $turnoVal !== '' ? (int) $turnoVal : null,
                                                'metros' => $metrosVal !== null && $metrosVal !== '' ? (float) $metrosVal : null,
                                            ];
                                        }
                                    }
                                    $horaInicio = $reg->HoraInicial ? substr((string)$reg->HoraInicial, 0, 5) : '';
                                    $horaFin = $reg->HoraFinal ? substr((string)$reg->HoraFinal, 0, 5) : '';
                                @endphp
                                <tr class="hover:bg-gray-50" data-registro-id="{{ $reg->Id ?? '' }}" data-no-julio="{{ trim((string)($reg->NoJulio ?? '')) }}">
                                    <td class="px-1 py-0.5 text-left align-top max-w-[180px]">
                                        <div class="flex items-start justify-between gap-1">
                                            <div class="text-sm leading-tight flex-1 min-w-0" data-empleados-info>
                                                <div class="text-gray-800 font-semibold">{{ $empleadoTexto }}</div>
                                                @if(!empty($infoOficial))
                                                    @foreach($infoOficial as $of)
                                                        <div class="text-sm text-gray-600">{{ $of['nombre'] }} <span class="text-amber-600">(T{{ $of['turno'] ?? '-' }})</span></div>
                                                    @endforeach
                                                @endif
                                            </div>
                                            <button
                                                type="button"
                                                class="btn-editar-empleados shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded"
                                                data-registro-id="{{ $reg->Id ?? '' }}"
                                                data-oficiales='@json($oficialesEdicion)'
                                                title="Editar empleados"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-0.5 py-0.5 text-center">
                                        <input type="time" data-field="h_inicio" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $horaInicio }}"
                                            class="produccion-input w-14 max-w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-0.5 py-0.5 text-center">
                                        <input type="time" data-field="h_fin" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $horaFin }}"
                                            class="produccion-input w-14 max-w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center">{{ $reg->NoJulio ?? '-' }}</td>
                                    <td class="px-1 py-1 text-center">
                                        @php
                                            $noJulioKey = trim((string)($reg->NoJulio ?? ''));
                                            $hilosVal = ($mapaJuliosHilos ?? [])[$noJulioKey] ?? $reg->Hilos ?? '';
                                        @endphp
                                        <input type="number" min="0" data-field="hilos" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $hilosVal }}"
                                            class="produccion-input w-16 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center">{{ ($reg->KgBruto ?? null) !== null && ($reg->KgBruto ?? '') !== '' ? number_format((float)($reg->KgBruto ?? 0), 2) : '-' }}</td>
                                    <td class="px-1 py-1 text-center">{{ ($reg->Tara ?? null) !== null && ($reg->Tara ?? '') !== '' ? number_format((float)($reg->Tara ?? 0), 2) : '-' }}</td>
                                    <td class="px-1 py-1 text-center">{{ $kgNeto !== null && $kgNeto !== '' ? number_format((float)$kgNeto, 2) : '0.00' }}</td>
                                    <td class="px-1 py-1 text-center" data-cell="metros">{{ $metros > 0 ? number_format($metros, 0) : '-' }}</td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="hilatura" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Hilatura ?? 0 }}"
                                            class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="maquina" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Maquina ?? 0 }}"
                                            class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="operac" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Operac ?? 0 }}"
                                            class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="transf" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Transf ?? 0 }}"
                                            class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-2 py-3 text-center text-gray-500 italic">No hay registros de producción</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>


    </div>

    <script>
        (() => {
            const ordenId = {{ $orden->Id }};
            const puedeEditar = {{ $puedeEditar ? 'true' : 'false' }};
            const csrfToken = '{{ csrf_token() }}';
            const routeActualizar = '{{ route('urdido.editar.ordenes.programadas.actualizar') }}';
            const routeActualizarJulios = '{{ route('urdido.editar.ordenes.programadas.actualizar.julios') }}';
            const routeActualizarHilosProduccion = '{{ route('urdido.editar.ordenes.programadas.actualizar.hilos.produccion') }}';
            const RUTA_USUARIOS_URDIDO = '{{ route('urdido.modulo.produccion.urdido.usuarios.urdido') }}';
            const RUTA_GUARDAR_OFICIAL = '{{ route('urdido.modulo.produccion.urdido.guardar.oficial') }}';
            const RUTA_ELIMINAR_OFICIAL = '{{ route('urdido.modulo.produccion.urdido.eliminar.oficial') }}';
            const RUTA_HILOS = '{{ route("programa.urd.eng.hilos") }}';
            const RUTA_TAMANOS = '{{ route("programa.urd.eng.tamanos") }}';
            const RUTA_BOM_URDIDO = '{{ route("programa.urd.eng.buscar.bom.urdido") }}';
            const bloqueaUrdido = {{ $bloqueaUrdido ? 'true' : 'false' }};
            const permiteEditarPorStatus = {{ ($permiteEditarPorStatus ?? false) ? 'true' : 'false' }};
            const esFinalizado = {{ $esFinalizado ? 'true' : 'false' }};
            const mostrarTablaProduccion = {{ ($mostrarTablaProduccion ?? false) ? 'true' : 'false' }};
            const statusOrden = @json(trim($orden->Status ?? ''));
            const ACCION_METROS_SOLO_CAMPO = 'solo_campo';
            const ACCION_METROS_ACTUALIZAR_TODA = 'actualizar_produccion_toda';
            const ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO = 'actualizar_produccion_sin_hora_inicio';

            const cambiosPendientes = new Map();
            let timeoutGuardado = null;
            let opcionesHilos = [];
            let opcionesTamanos = [];
            let usuariosUrdido = null;

            const showToast = (icon, title) => {
                if (typeof Swal === 'undefined') {
                    alert(title);
                    return;
                }
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title,
                    showConfirmButton: false,
                    timer: 2000,
                });
            };

            const showError = (message, title = 'Error') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'error',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Aceptar',
                    width: '500px',
                });
            };

            const showWarning = (message, title = 'Advertencia') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'warning',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: 'Aceptar',
                    width: '500px',
                });
            };

            const showSuccess = (message, title = 'Exito') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'success',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Aceptar',
                    timer: 2000,
                    timerProgressBar: true,
                    width: '500px',
                });
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const normalizarOficiales = (oficiales) => {
                if (!Array.isArray(oficiales)) return [];
                return oficiales
                    .map(of => ({
                        numero: parseInt(of?.numero, 10),
                        cve: (of?.cve ?? '').toString().trim() || null,
                        nombre: (of?.nombre ?? '').toString().trim() || null,
                        turno: of?.turno !== null && of?.turno !== undefined && of?.turno !== '' ? parseInt(of.turno, 10) : null,
                        metros: of?.metros !== null && of?.metros !== undefined && of?.metros !== '' ? parseFloat(of.metros) : null,
                    }))
                    .filter(of => Number.isInteger(of.numero) && of.numero >= 1 && of.numero <= 3);
            };

            const obtenerUsuariosUrdido = async () => {
                if (Array.isArray(usuariosUrdido)) return usuariosUrdido;
                try {
                    const response = await fetch(RUTA_USUARIOS_URDIDO, { headers: { 'Accept': 'application/json' } });
                    const result = await response.json();
                    usuariosUrdido = (result?.success && Array.isArray(result?.data)) ? result.data : [];
                } catch (error) {
                    console.error('Error al cargar usuarios de Urdido:', error);
                    usuariosUrdido = [];
                }
                return usuariosUrdido;
            };

            const resolverEmpleado = (valorCrudo, usuarios) => {
                const valor = String(valorCrudo || '').trim();
                if (!valor) return { cve: null, nombre: null, turno: null };

                const valorLower = valor.toLowerCase();
                const byNumero = usuarios.find(u => String(u.numero_empleado || '').trim() === valor);
                if (byNumero) {
                    return {
                        cve: String(byNumero.numero_empleado || '').trim() || null,
                        nombre: String(byNumero.nombre || '').trim() || null,
                        turno: byNumero.turno ?? null,
                    };
                }

                const matchNumeroNombre = valor.match(/^(\d+)\s*-\s*(.+)$/);
                if (matchNumeroNombre) {
                    const cve = String(matchNumeroNombre[1] || '').trim();
                    const byNumeroMatch = usuarios.find(u => String(u.numero_empleado || '').trim() === cve);
                    if (byNumeroMatch) {
                        return {
                            cve,
                            nombre: String(byNumeroMatch.nombre || '').trim() || null,
                            turno: byNumeroMatch.turno ?? null,
                        };
                    }
                    return {
                        cve,
                        nombre: String(matchNumeroNombre[2] || '').trim() || null,
                        turno: null,
                    };
                }

                const byNombre = usuarios.find(u => String(u.nombre || '').trim().toLowerCase() === valorLower);
                if (byNombre) {
                    return {
                        cve: String(byNombre.numero_empleado || '').trim() || null,
                        nombre: String(byNombre.nombre || '').trim() || null,
                        turno: byNombre.turno ?? null,
                    };
                }

                if (/^\d+$/.test(valor)) {
                    return { cve: valor, nombre: null, turno: null };
                }

                return { cve: null, nombre: valor, turno: null };
            };

            const renderizarInfoEmpleadosFila = (registroId, oficiales) => {
                const row = document.querySelector(`#tabla-produccion tbody tr[data-registro-id="${registroId}"]`);
                if (!row) return;

                const infoContainer = row.querySelector('[data-empleados-info]');
                const btnEditar = row.querySelector('.btn-editar-empleados');
                if (!infoContainer) return;

                const ordenados = normalizarOficiales(oficiales).sort((a, b) => a.numero - b.numero);
                const codigos = ordenados.map(o => o.cve).filter(Boolean);

                let html = `<div class="text-gray-800 font-semibold">${escapeHtml(codigos.length ? codigos.join(', ') : '-')}</div>`;
                ordenados.forEach(of => {
                    if (!of.nombre) return;
                    const turnoTxt = of.turno !== null && of.turno !== undefined ? of.turno : '-';
                    html += `<div class="text-sm text-gray-600">${escapeHtml(of.nombre)} <span class="text-amber-600">(T${escapeHtml(turnoTxt)})</span></div>`;
                });

                infoContainer.innerHTML = html;
                if (btnEditar) {
                    btnEditar.dataset.oficiales = JSON.stringify(ordenados);
                }
            };

            const actualizarMetrosFilaProduccion = (registroId, metrosTotal) => {
                const row = document.querySelector(`#tabla-produccion tbody tr[data-registro-id="${registroId}"]`);
                if (!row) return;
                const celdaMetros = row.querySelector('[data-cell="metros"]');
                if (!celdaMetros) return;
                celdaMetros.textContent = formatearMetrosTabla(metrosTotal);
            };

            const guardarEmpleadosRegistro = async (registroId, oficialesActuales, oficialesNuevos) => {
                const actualesMap = new Map(normalizarOficiales(oficialesActuales).map(o => [o.numero, o]));
                const guardados = [];

                for (let i = 1; i <= 3; i++) {
                    const actual = actualesMap.get(i);
                    const nuevo = oficialesNuevos.find(o => o.numero === i) || { numero: i, cve: null, nombre: null, metros: null, turno: null };
                    const sinDatos = !nuevo.cve && !nuevo.nombre;

                    if (sinDatos) {
                        if (actual && (actual.cve || actual.nombre)) {
                            const respDel = await fetch(RUTA_ELIMINAR_OFICIAL, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                body: JSON.stringify({ registro_id: registroId, numero_oficial: i }),
                            });
                            const resultDel = await respDel.json();
                            if (!resultDel.success) {
                                throw new Error(resultDel.error || `No se pudo eliminar el empleado ${i}`);
                            }
                        }
                        continue;
                    }

                    const payload = {
                        registro_id: registroId,
                        numero_oficial: i,
                        cve_empl: nuevo.cve,
                        nom_empl: nuevo.nombre,
                        metros: nuevo.metros,
                        turno: nuevo.turno,
                    };
                    const respSave = await fetch(RUTA_GUARDAR_OFICIAL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify(payload),
                    });
                    const resultSave = await respSave.json();
                    if (!resultSave.success) {
                        throw new Error(resultSave.error || `No se pudo guardar el empleado ${i}`);
                    }
                    guardados.push({
                        numero: i,
                        cve: nuevo.cve || null,
                        nombre: nuevo.nombre || null,
                        metros: resultSave?.data?.metros ?? nuevo.metros ?? null,
                        turno: nuevo.turno || null,
                    });
                }

                return guardados;
            };

            const abrirModalEditarEmpleados = async (btn) => {
                if (!btn) return;
                if (typeof Swal === 'undefined') {
                    showError('No se pudo abrir el modal de empleados.');
                    return;
                }

                const registroId = parseInt(btn.dataset.registroId || '', 10);
                if (!registroId) return;

                const usuarios = await obtenerUsuariosUrdido();
                const actuales = normalizarOficiales(JSON.parse(btn.dataset.oficiales || '[]'));
                const mapActuales = new Map(actuales.map(o => [o.numero, o]));

                const filasHtml = [1, 2, 3].map(i => {
                    const of = mapActuales.get(i) || {};
                    const turno = of.turno ?? '';
                    const metros = of.metros !== null && of.metros !== undefined && of.metros !== '' ? of.metros : '';
                    const cveActual = String(of.cve || '').trim();
                    const opcionesEmpleado = usuarios.map(u => {
                        const cve = String(u.numero_empleado || '').trim();
                        const nom = String(u.nombre || '').trim();
                        if (!cve) return '';
                        const selected = cveActual !== '' && cve === cveActual ? 'selected' : '';
                        return `<option value="${escapeHtml(cve)}" data-nombre="${escapeHtml(nom)}" data-turno="${escapeHtml(u.turno ?? '')}" ${selected}>${escapeHtml(cve)}</option>`;
                    }).join('');
                    const existeActual = cveActual !== '' && usuarios.some(u => String(u.numero_empleado || '').trim() === cveActual);
                    const opcionActualExtra = (!existeActual && cveActual !== '')
                        ? `<option value="${escapeHtml(cveActual)}" data-nombre="${escapeHtml(of.nombre || '')}" data-turno="${escapeHtml(of.turno ?? '')}" selected>${escapeHtml(cveActual)}</option>`
                        : '';

                    return `
                        <div style="display:grid; grid-template-columns:minmax(140px,1.15fr) minmax(160px,1.2fr) minmax(92px,0.7fr) minmax(58px,0.45fr); column-gap:8px; align-items:center; margin-bottom:8px; width:100%;">
                            <select id="swal_empleado_num_${i}" class="px-2 py-1 border border-gray-300 rounded text-sm" style="width:100%; max-width:100%; min-width:0; box-sizing:border-box; height:36px;">
                                <option value="">No. Empleado</option>
                                ${opcionesEmpleado}
                                ${opcionActualExtra}
                            </select>
                            <input id="swal_empleado_nom_${i}" class="px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100" style="width:100%; max-width:100%; min-width:0; box-sizing:border-box; height:36px;" value="${escapeHtml(of.nombre || '')}" placeholder="Nombre" readonly>
                            <input id="swal_metros_${i}" type="number" min="0" step="0.01" class="px-2 py-1 border border-gray-300 rounded text-sm" style="width:100%; max-width:100%; min-width:0; box-sizing:border-box; height:36px;" value="${escapeHtml(metros)}" placeholder="Metros">
                            <select id="swal_turno_${i}" class="px-2 py-1 border border-gray-300 rounded text-sm" style="width:100%; max-width:100%; min-width:0; box-sizing:border-box; height:36px;">
                                <option value="" ${turno === '' ? 'selected' : ''}>Turno</option>
                                <option value="1" ${String(turno) === '1' ? 'selected' : ''}>1</option>
                                <option value="2" ${String(turno) === '2' ? 'selected' : ''}>2</option>
                                <option value="3" ${String(turno) === '3' ? 'selected' : ''}>3</option>
                            </select>
                        </div>
                    `;
                }).join('');

                const resultado = await Swal.fire({
                    title: 'Editar Empleados',
                    html: `
                        <div class="text-left w-full" style="width:100%; max-width:100%;">
                            <p class="text-sm text-gray-600 mb-2">Puedes agregar o editar hasta 3 empleados.</p>
                            <div style="display:grid; grid-template-columns:minmax(140px,1.15fr) minmax(160px,1.2fr) minmax(92px,0.7fr) minmax(58px,0.45fr); column-gap:8px; margin-bottom:6px;">
                                <div class="text-xs font-semibold text-gray-600">No. Empleado</div>
                                <div class="text-xs font-semibold text-gray-600">Nombre</div>
                                <div class="text-xs font-semibold text-gray-600">Metros</div>
                                <div class="text-xs font-semibold text-gray-600">Turno</div>
                            </div>
                            ${filasHtml}
                        </div>
                    `,
                    width: 920,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    didOpen: () => {
                        const htmlContainer = Swal.getHtmlContainer();
                        if (htmlContainer) {
                            htmlContainer.style.width = '100%';
                            htmlContainer.style.maxWidth = '100%';
                        }

                        for (let i = 1; i <= 3; i++) {
                            const selectEmp = document.getElementById(`swal_empleado_num_${i}`);
                            const inputNom = document.getElementById(`swal_empleado_nom_${i}`);
                            const inputMetros = document.getElementById(`swal_metros_${i}`);
                            const selectTurno = document.getElementById(`swal_turno_${i}`);
                            if (!selectEmp || !inputNom || !inputMetros || !selectTurno) continue;

                            const syncNombre = () => {
                                const option = selectEmp.options[selectEmp.selectedIndex];
                                const nombre = option?.dataset?.nombre || '';
                                const turnoSugerido = option?.dataset?.turno || '';
                                inputNom.value = nombre;
                                if (!selectEmp.value) {
                                    selectTurno.value = '';
                                    inputMetros.value = '';
                                    return;
                                }
                                if (!selectTurno.value && turnoSugerido) {
                                    selectTurno.value = String(turnoSugerido);
                                }
                            };

                            selectEmp.addEventListener('change', syncNombre);
                            syncNombre();
                        }
                    },
                    preConfirm: () => {
                        const nuevos = [];
                        const claves = new Set();
                        const turnosAsignados = new Map();

                        for (let i = 1; i <= 3; i++) {
                            const selectEmp = document.getElementById(`swal_empleado_num_${i}`);
                            const inputNom = document.getElementById(`swal_empleado_nom_${i}`);
                            const inputMetros = document.getElementById(`swal_metros_${i}`);
                            const select = document.getElementById(`swal_turno_${i}`);
                            const cve = String(selectEmp?.value || '').trim();
                            const nombre = String(inputNom?.value || '').trim();
                            const metrosTxt = String(inputMetros?.value || '').trim();
                            const turnoTxt = String(select?.value || '').trim();

                            if (!cve && (turnoTxt || metrosTxt !== '')) {
                                Swal.showValidationMessage(`Selecciona No. Empleado para Empleado ${i} o limpia turno/metros.`);
                                return false;
                            }

                            if (!cve) {
                                nuevos.push({ numero: i, cve: null, nombre: null, metros: null, turno: null });
                                continue;
                            }

                            if (claves.has(cve)) {
                                Swal.showValidationMessage(`El No. Empleado ${cve} esta repetido.`);
                                return false;
                            }
                            claves.add(cve);

                            const turno = parseInt(turnoTxt, 10);
                            if (!Number.isInteger(turno) || ![1, 2, 3].includes(turno)) {
                                Swal.showValidationMessage(`Selecciona un turno valido (1-3) para Empleado ${i}.`);
                                return false;
                            }

                            let metros = null;
                            if (metrosTxt !== '') {
                                metros = parseFloat(metrosTxt);
                                if (!Number.isFinite(metros) || metros < 0) {
                                    Swal.showValidationMessage(`Metros invalido para Empleado ${i}.`);
                                    return false;
                                }
                            }

                            if (turnosAsignados.has(turno)) {
                                Swal.showValidationMessage(`No puede haber dos oficiales en el mismo turno (${turno}).`);
                                return false;
                            }
                            turnosAsignados.set(turno, i);

                            nuevos.push({ numero: i, cve, nombre: nombre || null, metros, turno });
                        }
                        return nuevos;
                    },
                });

                if (!resultado.isConfirmed || !Array.isArray(resultado.value)) return;

                try {
                    const oficialesGuardados = await guardarEmpleadosRegistro(registroId, actuales, resultado.value);
                    renderizarInfoEmpleadosFila(registroId, oficialesGuardados);
                    const metrosTotal = resultado.value.reduce((acum, item) => {
                        const metrosNum = Number(item?.metros);
                        return acum + (Number.isFinite(metrosNum) ? metrosNum : 0);
                    }, 0);
                    actualizarMetrosFilaProduccion(registroId, metrosTotal);
                    showToast('success', 'Empleados actualizados correctamente');
                } catch (error) {
                    console.error('Error al guardar empleados:', error);
                    showError(error.message || 'No se pudieron guardar los empleados');
                }
            };

            const debounce = (fn, ms = 300) => {
                let t;
                return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
            };

            const positionDropdown = (inputEl, container) => {
                const rect = inputEl.getBoundingClientRect();
                container.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                container.style.left = (rect.left + window.scrollX) + 'px';
                container.style.width = rect.width + 'px';
            };

            const setupBomAutocomplete = (inputsSelector, searchRoute, containerId, onSelectExtra) => {
                const inputs = document.querySelectorAll(inputsSelector);
                if (!inputs.length) return;
                let activeInput = null, selectedIndex = -1, list = [], open = false;

                let container = document.getElementById(containerId);
                if (!container) {
                    container = document.createElement('div');
                    container.id = containerId;
                    container.className = 'fixed z-[99999] bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto';
                    document.body.appendChild(container);
                }

                const hide = () => { container.classList.add('hidden'); open = false; selectedIndex = -1; list = []; activeInput = null; };
                const show = (el) => { positionDropdown(el, container); container.classList.remove('hidden'); open = true; };

                const getLabel = (s) => `${s.BOMID} - ${s.NAME || ''}`;
                const render = (items) => {
                    container.innerHTML = '';
                    items.forEach((it, idx) => {
                        const div = document.createElement('div');
                        div.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-gray-100';
                        div.textContent = getLabel(it);
                        div.addEventListener('click', () => {
                            if (activeInput) {
                                activeInput.value = it.BOMID;
                                activeInput.dispatchEvent(new Event('change', { bubbles: true }));
                                if (typeof onSelectExtra === 'function') onSelectExtra(activeInput, it);
                                hide();
                            }
                        });
                        div.addEventListener('mouseenter', () => {
                            container.querySelectorAll('div').forEach(d => d.classList.remove('bg-blue-100'));
                            div.classList.add('bg-blue-100');
                            selectedIndex = idx;
                        });
                        container.appendChild(div);
                    });
                };

                const doSearch = debounce(async (q, inputEl) => {
                    if (!q || String(q).trim() === '') { hide(); return; }
                    try {
                        const url = new URL(searchRoute, window.location.origin);
                        url.searchParams.set('q', String(q).trim());
                        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        const arr = Array.isArray(data) ? data : (data.data || []);
                        if (!arr.length) { hide(); return; }
                        list = arr;
                        activeInput = inputEl;
                        render(list);
                        show(inputEl);
                    } catch (e) { hide(); }
                }, 300);

                const onKey = (e) => {
                    if (!open) return;
                    const items = container.querySelectorAll('div');
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                        items[selectedIndex]?.scrollIntoView({ block: 'nearest' });
                        items.forEach((it, i) => it.classList.toggle('bg-blue-100', i === selectedIndex));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        items.forEach((it, i) => it.classList.toggle('bg-blue-100', i === selectedIndex));
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (selectedIndex >= 0 && items[selectedIndex]) items[selectedIndex].click();
                        else hide();
                    } else if (e.key === 'Escape') { hide(); }
                };

                window.addEventListener('scroll', () => { if (activeInput && open) positionDropdown(activeInput, container); }, true);
                window.addEventListener('resize', () => { if (activeInput && open) positionDropdown(activeInput, container); });
                document.addEventListener('click', (e) => {
                    if (activeInput && !activeInput.contains(e.target) && !container.contains(e.target)) hide();
                }, true);

                inputs.forEach(input => {
                    input.addEventListener('input', e => doSearch(e.target.value, e.target));
                    input.addEventListener('focus', e => { if (e.target.value.trim()) doSearch(e.target.value, e.target); });
                    input.addEventListener('keydown', onKey);
                    input.addEventListener('click', e => e.stopPropagation());
                });
            };

            const cargarHilos = async () => {
                try {
                    const response = await fetch(RUTA_HILOS, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (result && result.success && Array.isArray(result.data)) {
                        opcionesHilos = result.data.map(item => item.ConfigId || '').filter(Boolean);
                    } else {
                        opcionesHilos = [];
                    }
                } catch (error) {
                    console.error('Error al cargar hilos:', error);
                    opcionesHilos = [];
                }
            };

            const cargarTamanos = async () => {
                try {
                    const response = await fetch(RUTA_TAMANOS, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (result && result.success && Array.isArray(result.data)) {
                        opcionesTamanos = result.data.map(item => item.InventSizeId || '').filter(Boolean);
                    } else {
                        opcionesTamanos = [];
                    }
                } catch (error) {
                    console.error('Error al cargar tamaños:', error);
                    opcionesTamanos = [];
                }
            };

            const actualizarSelectHilos = () => {
                const select = document.getElementById('campo_Fibra');
                if (!select) return;
                const valorActual = (select.dataset.valorActual || select.value || '').trim();
                const opciones = opcionesHilos.slice();
                if (valorActual && !opciones.includes(valorActual)) {
                    opciones.unshift(valorActual);
                }
                select.innerHTML = `<option value="">Seleccionar...</option>` + opciones
                    .map(hilo => `<option value="${hilo}" ${hilo === valorActual ? 'selected' : ''}>${hilo}</option>`)
                    .join('');
                if (valorActual) {
                    select.value = valorActual;
                }
            };

            const actualizarListaTamanos = () => {
                const datalist = document.getElementById('listaTamanos');
                if (!datalist) return;
                datalist.innerHTML = opcionesTamanos.map(t => `<option value="${t}"></option>`).join('');
                const tamanoInput = document.getElementById('campo_InventSizeId');
                const valorActual = (tamanoInput?.dataset.valorActual || tamanoInput?.value || '').trim();
                if (valorActual && !Array.from(datalist.options).some(opt => opt.value === valorActual)) {
                    const opt = document.createElement('option');
                    opt.value = valorActual;
                    datalist.appendChild(opt);
                }
            };

            const autocompletarTamano = () => {
                const cuentaInput = document.getElementById('campo_Cuenta');
                const calibreInput = document.getElementById('campo_Calibre');
                const tamanoInput = document.getElementById('campo_InventSizeId');
                if (!cuentaInput || !calibreInput || !tamanoInput) return;

                const cuenta = String(cuentaInput.value || '').trim();
                const calibreRaw = String(calibreInput.value || '').trim();
                if (!cuenta || !calibreRaw) return;

                const calibreNum = parseFloat(calibreRaw);
                const calibreNorm = Number.isFinite(calibreNum)
                    ? calibreNum.toFixed(2).replace(/\.?0+$/, '')
                    : calibreRaw;
                const tamanoEsperado = `${cuenta}-${calibreNorm}/1`;
                const yaExiste = opcionesTamanos.includes(tamanoEsperado);

                // Solo autocompletar si existe en la lista de tamaños (evita valores inválidos/largos)
                if (yaExiste && tamanoInput.value !== tamanoEsperado) {
                    tamanoInput.value = tamanoEsperado;
                    tamanoInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            const actualizarCampo = async (campo, valor, opciones = {}) => {
                if (campo === 'Folio') {
                    showError('El folio no se puede editar. Es un campo de solo lectura.', 'Campo No Editable');
                    return;
                }

                try {
                    const payload = {
                        orden_id: ordenId,
                        campo: campo,
                        valor: valor,
                    };

                    if (campo === 'Metros' && opciones.accionMetros) {
                        payload.accion_metros = opciones.accionMetros;
                    }

                    const response = await fetch(routeActualizar, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar campo');
                    }

                    cambiosPendientes.delete(campo);
                    if (opciones.mostrarToast !== false) {
                        showToast('success', result.message || `${campo} actualizado correctamente`);
                    }
                    return result;
                } catch (error) {
                    console.error('Error al actualizar campo:', error);
                    showError(`Error al actualizar ${campo}: ${error.message}`, 'Error al Guardar');
                    if (opciones.lanzarError) {
                        throw error;
                    }
                    return null;
                }
            };

            const solicitarAccionMetros = async () => {
                if (typeof Swal === 'undefined') {
                    return ACCION_METROS_SOLO_CAMPO;
                }

                const status = String(statusOrden || '').trim();

                if (status === 'Finalizado') {
                    const { value } = await Swal.fire({
                        icon: 'question',
                        title: 'Actualizar metros',
                        text: 'La orden esta finalizada. Como deseas aplicar el cambio de metros?',
                        input: 'radio',
                        inputOptions: {
                            [ACCION_METROS_ACTUALIZAR_TODA]: 'Actualizar metros de la produccion finalizada',
                            [ACCION_METROS_SOLO_CAMPO]: 'Solo actualizar el campo Metros de la orden',
                        },
                        inputValue: ACCION_METROS_SOLO_CAMPO,
                        showCancelButton: true,
                        confirmButtonText: 'Continuar',
                        cancelButtonText: 'Cancelar',
                        inputValidator: (val) => !val ? 'Selecciona una opcion' : null,
                    });
                    return value || null;
                }

                if (status === 'En Proceso') {
                    const { value } = await Swal.fire({
                        icon: 'question',
                        title: 'Actualizar metros',
                        text: 'Selecciona como deseas aplicar el cambio de metros en produccion.',
                        input: 'radio',
                        inputOptions: {
                            [ACCION_METROS_ACTUALIZAR_TODA]: 'Actualizar toda la produccion',
                            [ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO]: 'Actualizar solo registros sin hora inicio',
                            [ACCION_METROS_SOLO_CAMPO]: 'Solo actualizar el campo Metros de la orden',
                        },
                        inputValue: ACCION_METROS_SOLO_CAMPO,
                        showCancelButton: true,
                        confirmButtonText: 'Continuar',
                        cancelButtonText: 'Cancelar',
                        inputValidator: (val) => !val ? 'Selecciona una opcion' : null,
                    });
                    return value || null;
                }

                return ACCION_METROS_SOLO_CAMPO;
            };

            const solicitarConfirmacionImpactoJulios = async ({ esNuevoRegistro = false, noJulioCambio = false } = {}) => {
                if (!esNuevoRegistro && !noJulioCambio) {
                    return true;
                }

                const mensaje = esNuevoRegistro
                    ? 'Vas a agregar un nuevo registro de Julio/Hilos. Esto puede actualizar produccion ya iniciada por un empleado. Deseas continuar?'
                    : 'Vas a actualizar No. Julio. Esto puede actualizar o eliminar produccion ya iniciada por un empleado. Deseas continuar?';

                if (typeof Swal === 'undefined') {
                    return window.confirm(mensaje);
                }

                const result = await Swal.fire({
                    icon: 'warning',
                    title: 'Confirmar actualizacion',
                    text: mensaje,
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                });

                return !!result.isConfirmed;
            };

            const formatearMetrosTabla = (valor) => {
                const n = parseFloat(valor);
                if (!Number.isFinite(n) || n <= 0) {
                    return '-';
                }
                return Math.round(n).toLocaleString('en-US');
            };

            const sincronizarMetrosTablaProduccionEnPantalla = (accionMetros, metrosNuevo) => {
                if (!mostrarTablaProduccion) {
                    return;
                }

                document.querySelectorAll('#tabla-produccion tbody tr[data-registro-id]').forEach(tr => {
                    if (accionMetros === ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO) {
                        const inputHoraInicio = tr.querySelector('input[data-field="h_inicio"]');
                        const tieneHoraInicio = !!(inputHoraInicio && String(inputHoraInicio.value || '').trim() !== '');
                        if (tieneHoraInicio) {
                            return;
                        }
                    }

                    const celdaMetros = tr.querySelector('[data-cell="metros"]');
                    if (celdaMetros) {
                        celdaMetros.textContent = formatearMetrosTabla(metrosNuevo);
                    }
                });
            };

            const bindProduccionInput = (input) => {
                if (!input || input.dataset.listenerProduccion === '1') return;
                input.dataset.listenerProduccion = '1';

                let valorEnviado = input.value;
                const enviarProduccion = function () {
                    const registroId = parseInt(this.dataset.registroId, 10);
                    const field = this.dataset.field;
                    const valor = this.value === '' ? null : this.value;
                    if (valor === valorEnviado) return;
                    valorEnviado = this.value;

                    if (field === 'h_inicio' || field === 'h_fin') {
                        actualizarHoraProduccion(registroId, field === 'h_inicio' ? 'HoraInicial' : 'HoraFinal', valor);
                    } else {
                        const campoMap = { hilos: 'Hilos', hilatura: 'Hilatura', maquina: 'Maquina', operac: 'Operac', transf: 'Transf' };
                        actualizarCampoProduccion(registroId, campoMap[field], valor);
                    }
                };

                input.addEventListener('change', enviarProduccion);
                input.addEventListener('blur', enviarProduccion);
            };

            const agregarRegistrosProduccionATabla = (registros = []) => {
                if (!mostrarTablaProduccion || !Array.isArray(registros) || registros.length === 0) return;

                const tbody = document.querySelector('#tabla-produccion tbody');
                if (!tbody) return;

                const filaVacia = tbody.querySelector('tr td[colspan="13"]');
                if (filaVacia) {
                    filaVacia.closest('tr')?.remove();
                }

                registros.forEach((registro) => {
                    const registroId = parseInt(registro?.id, 10);
                    if (!Number.isInteger(registroId) || registroId <= 0) return;

                    const hilos = parseInt(registro?.hilos, 10);
                    const metrosTxt = formatearMetrosTabla(registro?.metros ?? 0);

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50';
                    tr.dataset.registroId = String(registroId);
                    tr.dataset.noJulio = '';
                    tr.innerHTML = `
                        <td class="px-1 py-0.5 text-left align-top max-w-[180px]">
                            <div class="flex items-start justify-between gap-1">
                                <div class="text-sm leading-tight flex-1 min-w-0" data-empleados-info>
                                    <div class="text-gray-800 font-semibold">-</div>
                                </div>
                                <button type="button" class="btn-editar-empleados shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded" data-registro-id="${registroId}" data-oficiales="[]" title="Editar empleados">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-0.5 py-0.5 text-center">
                            <input type="time" data-field="h_inicio" data-registro-id="${registroId}" value="" class="produccion-input w-14 max-w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-0.5 py-0.5 text-center">
                            <input type="time" data-field="h_fin" data-registro-id="${registroId}" value="" class="produccion-input w-14 max-w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center">
                            <input type="number" min="0" data-field="hilos" data-registro-id="${registroId}" value="${Number.isInteger(hilos) ? hilos : ''}" class="produccion-input w-16 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center">0.00</td>
                        <td class="px-1 py-1 text-center" data-cell="metros">${metrosTxt}</td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="hilatura" data-registro-id="${registroId}" value="0" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="maquina" data-registro-id="${registroId}" value="0" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="operac" data-registro-id="${registroId}" value="0" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="transf" data-registro-id="${registroId}" value="0" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                    `;

                    tbody.appendChild(tr);
                    tr.querySelectorAll('.produccion-input').forEach(bindProduccionInput);
                });
            };

            const eliminarRegistrosProduccionDeTabla = (ids = []) => {
                if (!mostrarTablaProduccion || !Array.isArray(ids) || ids.length === 0) return;

                const tbody = document.querySelector('#tabla-produccion tbody');
                if (!tbody) return;

                ids.forEach((id) => {
                    const registroId = parseInt(id, 10);
                    if (!Number.isInteger(registroId) || registroId <= 0) return;
                    const tr = tbody.querySelector(`tr[data-registro-id="${registroId}"]`);
                    if (tr) {
                        tr.remove();
                    }
                });

                const filasConRegistro = tbody.querySelectorAll('tr[data-registro-id]').length;
                if (filasConRegistro === 0) {
                    const trVacio = document.createElement('tr');
                    trVacio.innerHTML = '<td colspan="13" class="px-3 py-2 text-center text-gray-500">No hay registros de produccion</td>';
                    tbody.appendChild(trVacio);
                }
            };

            const actualizarJulioRow = async (row) => {
                const rowId = row.dataset.julioId || null;
                const noJulio = (row.querySelector('[data-field="no_julio"]')?.value ?? '').trim();
                const hilos = (row.querySelector('[data-field="hilos"]')?.value ?? '').trim();
                const noJulioVacio = noJulio === '';
                const hilosVacio = hilos === '';
                const noJulioPrevio = String(row.dataset.lastSavedNoJulio || '').trim();
                const esNuevoRegistro = !rowId || String(rowId).trim() === '';
                const noJulioCambio = !esNuevoRegistro && noJulio !== '' && noJulio !== noJulioPrevio;

                if ((noJulioVacio || hilosVacio) && !(noJulioVacio && hilosVacio && rowId)) {
                    return;
                }

                if (!noJulioVacio && !hilosVacio && (esNuevoRegistro || noJulioCambio)) {
                    const confirmado = await solicitarConfirmacionImpactoJulios({ esNuevoRegistro, noJulioCambio });
                    if (!confirmado) {
                        const inputNoJulio = row.querySelector('[data-field="no_julio"]');
                        if (inputNoJulio) {
                            inputNoJulio.value = noJulioPrevio;
                        }
                        return;
                    }
                }

                try {
                    const payload = {
                        orden_id: ordenId,
                        id: (rowId && rowId !== '') ? parseInt(rowId, 10) : null,
                        no_julio: noJulio || null,
                        hilos: hilos || null,
                    };

                    let response = await fetch(routeActualizarJulios, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify(payload),
                    });

                    if (!response.ok && esFinalizado && noJulio && hilos) {
                        const resp2 = await fetch(routeActualizarHilosProduccion, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({
                                orden_id: ordenId,
                                no_julio: noJulio,
                                hilos: parseInt(hilos, 10) || 0,
                            }),
                        });
                        if (resp2.ok) response = resp2;
                    }

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar julio');
                    }

                    if (result.data?.deleted) {
                        row.dataset.julioId = '';
                        row.dataset.lastSavedNoJulio = '';
                        row.dataset.lastSavedHilos = '';
                        const idsEliminados = Array.isArray(result?.data?.registros_produccion_eliminados_ids)
                            ? result.data.registros_produccion_eliminados_ids
                            : [];
                        if (idsEliminados.length > 0) {
                            eliminarRegistrosProduccionDeTabla(idsEliminados);
                        }
                        showToast('success', result.message || 'Registro de julio eliminado');
                        return;
                    } else if (result.data?.id) {
                        row.dataset.julioId = String(result.data.id);
                    }
                    row.dataset.lastSavedNoJulio = noJulio;
                    row.dataset.lastSavedHilos = hilos;

                    const idsEliminadosEnActualizacion = Array.isArray(result?.data?.registros_produccion_eliminados_ids)
                        ? result.data.registros_produccion_eliminados_ids
                        : [];
                    if (idsEliminadosEnActualizacion.length > 0) {
                        eliminarRegistrosProduccionDeTabla(idsEliminadosEnActualizacion);
                    }

                    // Sincronizar tabla producción: actualizar Hilos en filas con mismo No. Julio (visible en mi producción)
                    const hilosAnterior = String(result?.data?.hilos_anterior ?? '').trim();
                    const hilosNuevo = String(result?.data?.hilos_nuevo ?? hilos ?? '').trim();
                    if (hilosAnterior !== '' && hilosNuevo !== '' && hilosAnterior !== hilosNuevo) {
                        document.querySelectorAll('#tabla-produccion tbody tr[data-registro-id] input[data-field="hilos"]').forEach(input => {
                            if (String(input.value || '').trim() === hilosAnterior) {
                                input.value = hilosNuevo;
                            }
                        });
                    } else if (noJulio && hilos) {
                        document.querySelectorAll('#tabla-produccion tbody tr[data-registro-id]').forEach(tr => {
                            if (String(tr.dataset.noJulio || '') === String(noJulio)) {
                                const input = tr.querySelector('input[data-field="hilos"]');
                                if (input) input.value = hilos;
                            }
                        });
                    }

                    const registrosProduccionCreados = Array.isArray(result?.data?.registros_produccion_creados)
                        ? result.data.registros_produccion_creados
                        : [];
                    if (registrosProduccionCreados.length > 0) {
                        agregarRegistrosProduccionATabla(registrosProduccionCreados);
                    }

                    showToast('success', result.message || 'Julio actualizado correctamente');
                } catch (error) {
                    console.error('Error al actualizar julio:', error);
                    showError(`Error al actualizar julio: ${error.message}`, 'Error al Guardar');
                }
            };

            document.addEventListener('DOMContentLoaded', () => {
                const camposEditables = document.querySelectorAll('.campo-editable');
                const juliosRows = document.querySelectorAll('[data-julio-row]');
                const juliosTimeouts = new Map();
                // NoTelarId, RizoPie, Metros, FolioConsumo, TipoAtado (Tipo) se pueden editar incluso con AX=1
                const camposBloqueadosPorAx = [
                    'campo_Cuenta',
                    'campo_Calibre',
                    'campo_Fibra',
                    'campo_InventSizeId',
                    'campo_SalonTejidoId',
                    'campo_MaquinaId',
                    'campo_BomId',
                    'campo_FechaProg',
                    'campo_LoteProveedor',
                    'campo_Observaciones',
                ];
                const bloquearCampos = (ids) => {
                    ids.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        el.disabled = true;
                        el.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                    });
                };

                // Solo En Proceso o Programado permiten editar: MaquinaId, BomId, Fibra, Calibre, Cuenta, RizoPie y tabla Julios
                const camposQueDependenDeStatus = [
                    'campo_RizoPie',
                    'campo_Cuenta',
                    'campo_Calibre',
                    'campo_Fibra',
                    'campo_MaquinaId',
                    'campo_BomId',
                ];
                if (!permiteEditarPorStatus) {
                    bloquearCampos(camposQueDependenDeStatus);
                    document.querySelectorAll('.campo-julio').forEach(input => {
                        if (esFinalizado && input.dataset.field === 'hilos') return; // Hilos editable cuando Finalizado (sincroniza UrdProduccionUrdido)
                        input.disabled = true;
                        input.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                    });
                }

                camposEditables.forEach(campo => {
                    const campoNombre = campo.dataset.campo;
                    let valorAnterior = campo.value;
                    let timeoutMetros = null;
                    let guardandoMetros = false;

                    if ((campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') && campoNombre === 'Metros') {
                        const guardarMetros = async () => {
                            const valorActual = campo.value;
                            if (valorActual === valorAnterior || guardandoMetros) {
                                return;
                            }

                            guardandoMetros = true;
                            try {
                                const accionMetros = await solicitarAccionMetros();
                                if (!accionMetros) {
                                    campo.value = valorAnterior;
                                    return;
                                }

                                const result = await actualizarCampo(campoNombre, valorActual, {
                                    accionMetros,
                                    mostrarToast: false,
                                    lanzarError: true,
                                });
                                if (!result || !result.success) {
                                    campo.value = valorAnterior;
                                    return;
                                }

                                valorAnterior = valorActual;
                                cambiosPendientes.delete(campoNombre);

                                const actualizados = parseInt(result?.data?.registros_produccion_actualizados ?? 0, 10) || 0;
                                if (accionMetros === ACCION_METROS_SOLO_CAMPO) {
                                    showToast('success', 'Metros actualizado correctamente');
                                } else {
                                    const metrosSincronizados = result?.data?.valor ?? valorActual;
                                    sincronizarMetrosTablaProduccionEnPantalla(accionMetros, metrosSincronizados);
                                    showToast('success', `Metros actualizado y ${actualizados} registro(s) de produccion sincronizado(s)`);
                                }
                            } catch (error) {
                                campo.value = valorAnterior;
                            } finally {
                                guardandoMetros = false;
                            }
                        };

                        campo.addEventListener('change', () => {
                            if (campo.value === valorAnterior) {
                                return;
                            }
                            if (timeoutMetros) {
                                clearTimeout(timeoutMetros);
                            }
                            timeoutMetros = setTimeout(() => {
                                guardarMetros();
                            }, 400);
                        });

                        campo.addEventListener('blur', () => {
                            if (timeoutMetros) {
                                clearTimeout(timeoutMetros);
                                timeoutMetros = null;
                            }
                            guardarMetros();
                        });

                        return;
                    }

                    if (campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                cambiosPendientes.set(campoNombre, campo.value);
                                valorAnterior = campo.value;

                                if (timeoutGuardado) {
                                    clearTimeout(timeoutGuardado);
                                }
                                timeoutGuardado = setTimeout(() => {
                                    actualizarCampo(campoNombre, campo.value);
                                }, 1000);
                            }
                        });

                        campo.addEventListener('blur', () => {
                            if (cambiosPendientes.has(campoNombre)) {
                                actualizarCampo(campoNombre, campo.value);
                            }
                        });
                    }

                    if (campo.tagName === 'SELECT') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                valorAnterior = campo.value;
                                actualizarCampo(campoNombre, campo.value);
                            }
                        });
                    }
                });

                juliosRows.forEach(row => {
                    const noJulioInputInit = row.querySelector('[data-field="no_julio"]');
                    const hilosInputInit = row.querySelector('[data-field="hilos"]');
                    row.dataset.lastSavedNoJulio = String(noJulioInputInit?.value ?? '').trim();
                    row.dataset.lastSavedHilos = String(hilosInputInit?.value ?? '').trim();

                    const inputs = row.querySelectorAll('.campo-julio');
                    const rowKey = row.dataset.julioRow || '';

                    inputs.forEach(input => {
                        let valorAnterior = input.value;

                        const scheduleUpdate = () => {
                            if (juliosTimeouts.has(rowKey)) {
                                clearTimeout(juliosTimeouts.get(rowKey));
                            }
                            juliosTimeouts.set(rowKey, setTimeout(() => {
                                actualizarJulioRow(row);
                            }, 1000));
                        };

                        input.addEventListener('change', () => {
                            if (input.value !== valorAnterior) {
                                valorAnterior = input.value;
                                scheduleUpdate();
                            }
                        });

                        input.addEventListener('blur', () => {
                            if (juliosTimeouts.has(rowKey)) {
                                clearTimeout(juliosTimeouts.get(rowKey));
                                juliosTimeouts.delete(rowKey);
                            }
                            actualizarJulioRow(row);
                        });
                    });
                });

                // Cargar catálogos de hilos y tamaños y aplicar autocomplete
                Promise.all([cargarHilos(), cargarTamanos()]).then(() => {
                    actualizarSelectHilos();
                    actualizarListaTamanos();
                    autocompletarTamano();
                });

                // Autocomplete BOM Urdido (consulta SQL a otra DB - sqlsrv_ti)
                setupBomAutocomplete('#campo_BomId', RUTA_BOM_URDIDO, 'bom-urdido-suggestions-editar');

                // Actualizar label y opciones Tipo/Barras cuando cambian Salón o Máquina (Karl Mayer = Barras 1-6, else Tipo Rizo/Pie)
                const esKarlMayerDesdeCampos = () => {
                    const salon = String(document.getElementById('campo_SalonTejidoId')?.value ?? '').trim().toLowerCase();
                    const maquina = String(document.getElementById('campo_MaquinaId')?.value ?? '').trim().toLowerCase();
                    return (salon.includes('karl') || salon === 'karl mayer') && (maquina.includes('karl') || maquina === 'karl mayer');
                };
                const actualizarTipoBarras = () => {
                    const label = document.getElementById('label-tipo-barras');
                    const select = document.getElementById('campo_RizoPie');
                    if (!label || !select) return;
                    const isKM = esKarlMayerDesdeCampos();
                    label.textContent = isKM ? 'Barras' : 'Tipo';
                    select.dataset.esKarlMayer = isKM ? '1' : '0';
                    const valorActual = select.value;
                    if (isKM) {
                        const opcionesValidas = ['1', '2', '3', '4', '5', '6'];
                        if (!opcionesValidas.includes(valorActual)) {
                            select.innerHTML = '<option value="">Seleccionar...</option>' + opcionesValidas.map(v => `<option value="${v}">${v}</option>`).join('');
                        } else {
                            select.innerHTML = '<option value="">Seleccionar...</option>' + opcionesValidas.map(v => `<option value="${v}" ${v === valorActual ? 'selected' : ''}>${v}</option>`).join('');
                        }
                    } else {
                        const opcionesValidas = ['Rizo', 'Pie'];
                        if (!opcionesValidas.includes(valorActual)) {
                            select.innerHTML = '<option value="">Seleccionar...</option><option value="Rizo">Rizo</option><option value="Pie">Pie</option>';
                        } else {
                            select.innerHTML = '<option value="">Seleccionar...</option><option value="Rizo" ' + (valorActual === 'Rizo' ? 'selected' : '') + '>Rizo</option><option value="Pie" ' + (valorActual === 'Pie' ? 'selected' : '') + '>Pie</option>';
                        }
                    }
                };
                const salonSelect = document.getElementById('campo_SalonTejidoId');
                const maquinaSelect = document.getElementById('campo_MaquinaId');
                if (salonSelect) salonSelect.addEventListener('change', actualizarTipoBarras);
                if (maquinaSelect) maquinaSelect.addEventListener('change', actualizarTipoBarras);

                const cuentaInput = document.getElementById('campo_Cuenta');
                const calibreInput = document.getElementById('campo_Calibre');
                if (cuentaInput) {
                    cuentaInput.addEventListener('change', autocompletarTamano);
                    cuentaInput.addEventListener('blur', autocompletarTamano);
                }
                if (calibreInput) {
                    calibreInput.addEventListener('change', autocompletarTamano);
                    calibreInput.addEventListener('blur', autocompletarTamano);
                }

                if (bloqueaUrdido) {
                    bloquearCampos(camposBloqueadosPorAx);
                    document.querySelectorAll('.campo-julio').forEach(input => {
                        if (esFinalizado && input.dataset.field === 'hilos') return; // Hilos editable cuando Finalizado (sincroniza UrdProduccionUrdido)
                        input.disabled = true;
                        input.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                    });
                }

                // Producción (Status Finalizado): H. Inicio, H. Fin, Hilos, Hilatura, Maquina, Operac, Transf
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.btn-editar-empleados');
                    if (!btn) return;
                    e.preventDefault();
                    abrirModalEditarEmpleados(btn);
                });

                document.querySelectorAll('.produccion-input').forEach(bindProduccionInput);
            });

            async function actualizarHoraProduccion(registroId, campo, valor) {
                try {
                    const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.horas') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ registro_id: registroId, campo, valor })
                    });
                    const result = await response.json();
                    if (result.success) Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
                    else Swal.fire({ icon: 'error', title: 'Error', text: result.error || 'Error al actualizar' });
                } catch (e) { console.error(e); Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar' }); }
            }

            async function actualizarCampoProduccion(registroId, campo, valor) {
                try {
                    const response = await fetch('{{ route('urdido.modulo.produccion.urdido.actualizar.campos.produccion') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ registro_id: registroId, campo, valor: valor !== null && valor !== '' ? parseInt(valor, 10) : null })
                    });
                    const result = await response.json();
                    if (result.success) Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
                    else Swal.fire({ icon: 'error', title: 'Error', text: result.error || 'Error al actualizar' });
                } catch (e) { console.error(e); Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar' }); }
            }

            window.guardarCambios = async () => {
                if (cambiosPendientes.size === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin Cambios',
                        html: '<p class="text-gray-700">No hay cambios pendientes para guardar.</p>',
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'Aceptar',
                        width: '400px',
                    });
                    return;
                }

                const resultado = await Swal.fire({
                    icon: 'question',
                    title: 'Guardar Cambios',
                    html: `<p class="text-gray-700">Deseas guardar ${cambiosPendientes.size} cambio(s) pendiente(s)?</p>`,
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Si, Guardar',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                });

                if (!resultado.isConfirmed) {
                    return;
                }

                try {
                    const promesas = Array.from(cambiosPendientes.entries()).map(([campo, valor]) => {
                        return actualizarCampo(campo, valor);
                    });

                    await Promise.all(promesas);
                    cambiosPendientes.clear();
                    showSuccess('Todos los cambios se guardaron correctamente.', 'Cambios Guardados');
                } catch (error) {
                    console.error('Error al guardar cambios:', error);
                    showError('Ocurrio un error al guardar algunos cambios. Por favor, intenta nuevamente.', 'Error al Guardar');
                }
            };
        })();
    </script>
@endsection
