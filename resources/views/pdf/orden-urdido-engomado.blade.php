<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORDEN DE URDIDO Y ENGOMADO</title>
    <style>
        @page {
            margin: 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }
        .header {
            position: relative;
            width: 100%;
            margin-bottom: 10px;
        }
        .header-logo {
            position: absolute;
            left: 0;
            top: -10px;
        }
        .header-logo img {
            max-height: 50px;
            max-width: 130px;
            display: block;
        }
        .header-title {
            text-align: center;
            width: 100%;
            margin-bottom: 5px;
            padding-top: 5px;
        }
        .header h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            padding: 0;
        }
        .folio-top-right {
            position: absolute;
            top: -10px;
            right: 0;
            text-align: right;
            font-size: 14pt;
            font-weight: bold;
            padding-top: 0;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-top: 8px;
            margin-bottom: 8px;
        }
        .info-grid-row {
            display: table-row;
        }
        .info-grid-cell {
            display: table-cell;
            width: 33.33%;
            padding: 2px 4px;
            vertical-align: top;
            font-size: 8.5pt;
        }
        .info-grid-item {
            margin-bottom: 3px;
            font-size: 8.5pt;
        }
        .info-grid-label {
            font-weight: bold;
            display: inline;
            font-size: 8.5pt;
        }
        .info-grid-value {
            font-weight: normal;
            display: inline;
            font-size: 8.5pt;
        }
        .construccion-box {
            border: 1px solid #000;
            padding: 6px;
            margin-top: 2px;
            font-size: 7.5pt;
            line-height: 1.3;
            min-height: 65px;
        }
        .info-box {
            border: 1px solid #000;
            padding: 3px;
            margin: 2px 0;
            font-size: 8pt;
        }
        .info-box-label {
            font-weight: bold;
            margin-bottom: 1px;
            font-size: 8pt;
        }
        .info-section {
            margin-bottom: 15px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .info-cell {
            display: table-cell;
            padding: 3px 8px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            white-space: nowrap;
        }
        .table-container {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: center;
        }
        th {
            background-color: #e0e0e0;
            font-weight: bold;
            font-size: 7pt;
            line-height: 1.2;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .observaciones {
            border: 1px solid #000;
            padding: 8px;
            min-height: 50px;
            margin-top: 15px;
            font-size: 9pt;
        }
        .engomado-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        .engomado-table th,
        .engomado-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: left;
        }
        .engomado-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 7.5pt;
            width: 15%;
        }
        .engomado-table td {
            text-align: center;
            width: 10.6%;
        }
        .engomado-table td:first-child {
            text-align: left;
            font-weight: bold;
            width: 15%;
        }
        .engomado-table td:last-child {
            width: 15%;
        }
        .engomado-footer {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #000;
            font-size: 8pt;
        }
        .engomado-footer-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
        }
        .engomado-footer-cell {
            display: table-cell;
            padding: 2px 8px;
            vertical-align: top;
        }
    </style>
</head>
<body>
@php
    $isEngomado = ($tipo === 'engomado');
    $isUrdido = ($tipo === 'urdido');
@endphp

<div class="header">
    <div class="header-logo">
        @if(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" alt="Logo_Towell">
        @endif
    </div>
    @if($isUrdido)
        <div class="folio-top-right">
            No. FOLIO: {{ $orden->Folio ?? '-' }}
            @if(!empty($esReimpresion))
                <div style="font-size: 12pt; color: #dc2626; font-weight: bold; margin-top: 4px;">REIMPRESIÓN</div>
            @endif
        </div>
    @endif
    <div class="header-title mb-4">
        <h1>ORDEN DE URDIDO Y ENGOMADO</h1>
    </div>
</div>

@if($isUrdido)
    {{-- Formato especial para URDIDO --}}
    @php
        $fechaOrden = '-';
        if($registrosProduccion && $registrosProduccion->count() > 0) {
            $primerRegistro = $registrosProduccion->first();
            if($primerRegistro && isset($primerRegistro->Fecha) && $primerRegistro->Fecha) {
                $fechaOrden = date('d/m/Y', strtotime($primerRegistro->Fecha));
            }
        }
    @endphp

    {{-- Todos los campos en 3 columnas --}}
    <div class="info-grid">
        <div class="info-grid-row">
            {{-- Columna 1 --}}
            <div class="info-grid-cell">
                <div class="info-grid-item"><span class="info-grid-label">FECHA:</span> <span class="info-grid-value">{{ $fechaOrden }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">CUENTA:</span> <span class="info-grid-value">{{ $orden->Cuenta ?? '-' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">ORDENADO POR:</span> <span class="info-grid-value">{{ $orden->NomEmpl ?? '-' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">TIPO:</span> <span class="info-grid-value">{{ $orden->RizoPie ?? '-' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">DESTINO:</span> <span class="info-grid-value">{{ $orden->SalonTejidoId ?? '-' }}</span></div>
            </div>
            {{-- Columna 2 --}}
            <div class="info-grid-cell">
                <div class="info-grid-item"><span class="info-grid-label">URDIDO:</span> <span class="info-grid-value">{{ $orden->MaquinaId ?? $orden->MaquinaUrd ?? '-' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">PROVEEDOR:</span> <span class="info-grid-value">{{ $orden->LoteProveedor ?? '-' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">CALIBRE:</span> <span class="info-grid-value">{{ $orden->Calibre ?? '-' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">METROS:</span> <span class="info-grid-value">{{ $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0' }}</span></div>
                <div class="info-grid-item"><span class="info-grid-label">FÓRMULA:</span> <span class="info-grid-value">{{ $orden->BomFormula ?? '-' }}</span></div>
            </div>
            {{-- Columna 3 --}}
            <div class="info-grid-cell">
                <div class="construccion-box">
                    <span class="info-grid-label">CONSTRUCCIÓN:</span>
                    @if($julios && $julios->count() > 0)
                        @php
                            $construccionItems = [];
                            foreach($julios as $julio) {
                                $numJulio = $julio->Julios ?? '';
                                $hilos = $julio->Hilos ?? '';
                                $obs = $julio->Obs ?? '';
                                if($numJulio && $hilos) {
                                    $texto = "{$numJulio} Julio" . ($hilos > 1 ? 's' : '') . " de {$hilos} Hilo" . ($hilos > 1 ? 's' : '');
                                    if($obs) {
                                        $texto .= " - {$obs}";
                                    }
                                    $construccionItems[] = $texto;
                                }
                            }
                        @endphp
                        <span class="info-grid-value">{{ !empty($construccionItems) ? implode(', ', $construccionItems) : '-' }}</span>
                    @else
                        <span class="info-grid-value">-</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Formato normal para ENGOMADO --}}
    <div class="info-section">
        <div class="info-row">
            <div class="info-cell info-label">Folio:</div>
            <div class="info-cell">{{ $orden->Folio ?? '-' }}</div>

            <div class="info-cell info-label">Cuenta:</div>
            <div class="info-cell">{{ $orden->Cuenta ?? '-' }}</div>

            <div class="info-cell info-label">Tipo:</div>
            <div class="info-cell">{{ $orden->RizoPie ?? '-' }}</div>
        </div>

        <div class="info-row">
            <div class="info-cell info-label">Urdido:</div>
            <div class="info-cell">{{ $orden->MaquinaUrd ?? '-' }}</div>

            <div class="info-cell info-label">Engomado:</div>
            <div class="info-cell">{{ $orden->MaquinaEng ?? '-' }}</div>

            <div class="info-cell info-label">Destino:</div>
            <div class="info-cell">{{ $orden->SalonTejidoId ?? '-' }}</div>
        </div>

        <div class="info-row">
            <div class="info-cell info-label">Metros:</div>
            <div class="info-cell">
                {{ $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0' }}
            </div>

            <div class="info-cell info-label">Proveedor:</div>
            <div class="info-cell">{{ $orden->LoteProveedor ?? '-' }}</div>

            <div class="info-cell info-label">Núcleo:</div>
            <div class="info-cell">{{ $orden->Nucleo ?? '-' }}</div>
        </div>

        <div class="info-row">
            <div class="info-cell info-label">No. Telas:</div>
            <div class="info-cell">{{ $orden->NoTelas ?? '-' }}</div>

            <div class="info-cell info-label">Ancho Balonas:</div>
            <div class="info-cell">{{ $orden->AnchoBalonas ?? '-' }}</div>

            <div class="info-cell info-label">Metraje Telas:</div>
            <div class="info-cell">
                {{ $orden->MetrajeTelas ? number_format($orden->MetrajeTelas, 1, '.', ',') : '-' }}
            </div>
        </div>
    </div>
@endif

@if($registrosProduccion && $registrosProduccion->count() > 0)
    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th rowspan="2">Fecha</th>
                <th rowspan="2">Oficial</th>
                <th rowspan="2">Turno</th>
                <th rowspan="2">H. Inic.</th>
                <th rowspan="2">H. Fin.</th>
                <th rowspan="2">No. Julio</th>
                @if(!$isEngomado)
                    <th rowspan="2">Hilos</th>
                @endif
                <th rowspan="2">Kg. Bruto</th>
                <th rowspan="2">Tara</th>
                <th rowspan="2">Kg. Neto</th>
                <th rowspan="2">Metros</th>

                @if($isEngomado)
                    <th rowspan="2">Sol. Can.</th>
                    <th colspan="2">Temperatura</th>
                    <th rowspan="2">Roturas</th>
                @else
                    <th colspan="4">Roturas</th>
                @endif
            </tr>

            @if($isEngomado)
                <tr>
                    <th>Canoa 1</th>
                    <th>Canoa 2</th>
                </tr>
            @else
                <tr>
                    <th>Hilat.</th>
                    <th>Maq.</th>
                    <th>Operac.</th>
                    <th>Transf.</th>
                </tr>
            @endif
            </thead>

            <tbody>
            @foreach($registrosProduccion as $registro)
                @php
                    // Oficiales - extraer solo el primer nombre de cada uno
                    $oficiales = [];
                    for ($i = 1; $i <= 3; $i++) {
                        $nomEmpl = $registro->{"NomEmpl{$i}"} ?? null;
                        if ($nomEmpl) {
                            // Extraer solo el primer nombre (primera palabra antes del espacio)
                            $primerNombre = trim(explode(' ', $nomEmpl)[0]);
                            if ($primerNombre) {
                                $oficiales[] = $primerNombre;
                            }
                        }
                    }

                    // Turnos
                    $turnos = [];
                    for ($i = 1; $i <= 3; $i++) {
                        $turno = $registro->{"Turno{$i}"} ?? null;
                        if ($turno) {
                            $turnos[] = $turno;
                        }
                    }
                    $turnos = array_unique($turnos);

                    // Metros (suma 1,2,3)
                    $m1 = isset($registro->Metros1) && $registro->Metros1 !== null ? (float) $registro->Metros1 : 0;
                    $m2 = isset($registro->Metros2) && $registro->Metros2 !== null ? (float) $registro->Metros2 : 0;
                    $m3 = isset($registro->Metros3) && $registro->Metros3 !== null ? (float) $registro->Metros3 : 0;
                    $sumaMetros = $m1 + $m2 + $m3;
                @endphp

                <tr>
                    <td>
                        {{ $registro->Fecha ? date('d/m/Y', strtotime($registro->Fecha)) : '-' }}
                    </td>

                    <td class="text-left">
                        {{ !empty($oficiales) ? implode(', ', $oficiales) : '-' }}
                    </td>

                    <td>
                        {{ !empty($turnos) ? implode(', ', $turnos) : '-' }}
                    </td>

                    <td>{{ $registro->HoraInicial ? substr($registro->HoraInicial, 0, 5) : '-' }}</td>
                    <td>{{ $registro->HoraFinal ? substr($registro->HoraFinal, 0, 5) : '-' }}</td>

                    <td>{{ $registro->NoJulio ?? '-' }}</td>

                    @if(!$isEngomado)
                        <td>{{ $registro->Hilos ?? '-' }}</td>
                    @endif

                    <td>
                        {{ $registro->KgBruto !== null ? number_format($registro->KgBruto, 2, '.', '') : '-' }}
                    </td>

                    <td>
                        {{ $registro->Tara !== null ? number_format($registro->Tara, 1, '.', '') : '-' }}
                    </td>

                    <td>
                        {{ $registro->KgNeto !== null ? number_format($registro->KgNeto, 2, '.', '') : '-' }}
                    </td>

                    <td>
                        {{ $sumaMetros > 0 ? number_format($sumaMetros, 0, '.', ',') : '-' }}
                    </td>

                    @if($isEngomado)
                        <td>
                            {{ $registro->Solidos !== null ? number_format($registro->Solidos, 2, '.', '') : '-' }}
                        </td>
                        <td>{{ $registro->Canoa1 ?? '0' }}</td>
                        <td>{{ $registro->Canoa2 ?? '0' }}</td>
                        <td>{{ $registro->Roturas ?? '0' }}</td>
                    @else
                        <td>{{ $registro->Hilatura ?? '0' }}</td>
                        <td>{{ $registro->Maquina ?? '0' }}</td>
                        <td>{{ $registro->Operac ?? '0' }}</td>
                        <td>{{ $registro->Transf ?? '0' }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($isUrdido)
        {{-- Tabla de Engomado --}}
        <table class="engomado-table">

            <tbody>
                <tr>
                    <td><strong>COCINERO:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>TURNO:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>OLLAS:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>VOL. INICIAL:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>ALMIDÓN:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>RESINA:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>VOL. FINAL:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>FÓRMULA:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>% SÓLIDOS:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>PRODUCCIÓN:</strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        {{-- Footer de Engomado --}}
        <div class="engomado-footer">
            <div class="engomado-footer-row">
                <div class="engomado-footer-cell"><strong>NÚCLEO:</strong> {{ $ordenEngomado->Nucleo ?? '-' }}</div>
                <div class="engomado-footer-cell"><strong>ANCHO BALONAS:</strong> {{ $ordenEngomado->AnchoBalonas ?? '-' }}</div>
                <div class="engomado-footer-cell"><strong>CUENDEADOS MÍNIMO:</strong> {{ $ordenEngomado->Cuentados ?? '-' }}</div>
                <div class="engomado-footer-cell"><strong>OBSERVACIONES:</strong> {{ $ordenEngomado->Obs ?? 'Texto para mostrar, aqui el usuario podra escribir sus observaciones' }}</div>
            </div>
            <div class="engomado-footer-row">
                <div class="engomado-footer-cell"><strong>NO. TELAS:</strong> {{ $ordenEngomado->NoTelas ?? '-' }}</div>
            </div>
        </div>
    @endif
@endif

</body>
</html>
