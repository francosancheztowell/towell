<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAPELETA VIAJERA DE TELA ENGOMADA</title>
    <style>
        @page { margin: 8mm; }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            color: #000;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .header-cell { display: table-cell; vertical-align: middle; }
        .header-left { width: 28%; }
        .header-center { width: 44%; text-align: center; font-weight: bold; font-size: 11pt; }
        .header-right { width: 28%; text-align: right; }
        .header-logo img { max-height: 36px; }
        .folio {
            color: #c00000;
            font-size: 16pt;
            font-weight: bold;
        }
        .row {
            display: block;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
        }
        .row.atador {
            margin-bottom: 10px;
            overflow: visible;
            padding-bottom: 6px;
        }
        .row.first {
            margin-bottom: 12px;
        }
        .row.second {
            margin-top: 6px;
        }
        .group {
            display: inline-block;
            margin-right: 10px;
            vertical-align: bottom;
        }
        .label {
            font-weight: bold;
        }
        .line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 60px;
            padding: 0 4px;
            height: 12px;
            line-height: 12px;
        }
        .line-sm { min-width: 40px; }
        .line-md { min-width: 80px; }
        .line-lg { min-width: 120px; }
        .line-xl { min-width: 160px; }
        .check {
            display: inline-block;
            border: 1px solid #000;
            width: 12px;
            height: 12px;
            line-height: 12px;
            text-align: center;
            margin: 0 4px;
            font-weight: bold;
            font-size: 9pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        th, td {
            border: 1px solid #000;
            padding: 2px 4px;
            text-align: center;
            font-size: 8.5pt;
        }
        th { font-weight: bold; }
        .small { font-size: 8pt; }
        .spacer { height: 6px; }
        .box-small {
            border: 1px solid #000;
            width: 110px;
            height: 32px;
            text-align: center;
            vertical-align: middle;
            display: inline-block;
            line-height: 32px;
            font-weight: bold;
        }
        .clave-atador {
            float: right;
            text-align: center;
            margin-left: 10px;
        }
        .clave-atador .label-box {
            border: 1px solid #000;
            width: 120px;
            height: 14px;
            line-height: 14px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .clave-atador .value-box {
            border: 1px solid #000;
            width: 120px;
            height: 34px;
            display: block;
            margin-top: 0;
        }
        .triple-row {
            display: table;
            width: 100%;
            margin-top: 6px;
        }
        .triple-cell {
            display: table-cell;
            vertical-align: top;
        }
        .triple-left { width: 45%; }
        .triple-middle { width: 35%; text-align: left; }
        .triple-right { width: 20%; text-align: right; }
        .triple-row table th,
        .triple-row table td {
            padding-top: 7px;
            padding-bottom: 7px;
        }
        .footer {
            display: table;
            width: 100%;
            margin-top: 6px;
            font-size: 8pt;
        }
        .footer-cell { display: table-cell; }
        .footer-left { text-align: left; }
        .footer-center { text-align: center; }
        .footer-right { text-align: right; }
    </style>
</head>
<body>
@php
    $produccion = null;
    if ($registrosProduccion && $registrosProduccion->count() > 0) {
        foreach ($registrosProduccion as $registro) {
            $hasInfo = ($registro->Fecha || $registro->HoraInicial || $registro->HoraFinal ||
                $registro->NoJulio || $registro->KgBruto !== null || $registro->KgNeto !== null ||
                $registro->Canoa1 !== null || $registro->Canoa2 !== null || $registro->Tambor !== null ||
                $registro->Humedad !== null || $registro->Solidos !== null || $registro->Roturas !== null ||
                $registro->NomEmpl1 || $registro->NomEmpl2 || $registro->NomEmpl3 ||
                $registro->Turno1 || $registro->Turno2 || $registro->Turno3);
            if ($hasInfo) {
                $produccion = $registro;
                break;
            }
        }
    }

    $fechaProd = $produccion && $produccion->Fecha
        ? date('d/m/Y', strtotime($produccion->Fecha))
        : ($orden->FechaProg ? $orden->FechaProg->format('d/m/Y') : '');

    $horaInicial = $produccion && $produccion->HoraInicial
        ? substr($produccion->HoraInicial, 0, 5)
        : '';

    $horaFinal = $produccion && $produccion->HoraFinal
        ? substr($produccion->HoraFinal, 0, 5)
        : '';

    $turno = $produccion->Turno1 ?? $produccion->Turno2 ?? $produccion->Turno3 ?? '';

    $engomador = $produccion->NomEmpl1 ?? $produccion->NomEmpl2 ?? $produccion->NomEmpl3 ?? '';

    $totalMetros = 0;
    if ($registrosProduccion) {
        foreach ($registrosProduccion as $registro) {
            $m1 = isset($registro->Metros1) ? (float) $registro->Metros1 : 0;
            $m2 = isset($registro->Metros2) ? (float) $registro->Metros2 : 0;
            $m3 = isset($registro->Metros3) ? (float) $registro->Metros3 : 0;
            $totalMetros += ($m1 + $m2 + $m3);
        }
    }

    $esRizo = isset($orden->RizoPie) && strtoupper(trim($orden->RizoPie)) === 'RIZO';
    $esPie = isset($orden->RizoPie) && strtoupper(trim($orden->RizoPie)) === 'PIE';

    $ordenNo = $orden->Folio ?? '';
    $ordenOrden = $orden->NoTelarId ?? $orden->Folio ?? '';
    $destino = 'JZ JS S15 S IN IV';
@endphp

<div class="header">
    <div class="header-cell header-left header-logo">
        @if(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" alt="Logo Towell">
        @endif
    </div>
    <div class="header-cell header-center">
        PAPELETA VIAJERA DE TELA ENGOMADA
    </div>
    <div class="header-cell header-right">
        <span class="folio">No. {{ $ordenNo }}</span>
    </div>
</div>

<div class="row first">
    <span class="group label">ENGOMADO</span>
    <span class="group line line-md">{{ $orden->MaquinaEng ?? '' }}</span>

    <span class="group label">Fecha:</span>
    <span class="group line line-md">{{ $fechaProd }}</span>

    <span class="group label">Turno:</span>
    <span class="group line line-sm">{{ $turno }}</span>

    <span class="group label">ORDEN:</span>
    <span class="group line line-md">{{ $ordenOrden }}</span>

    <span class="group label">PAREJA</span>
    <span class="group line line-sm"></span>
</div>

<div class="row second">
    <span class="group label">URDIDO</span>
    <span class="group line line-md">{{ $orden->MaquinaUrd ?? '' }}</span>

    <span class="group label">URDIDOR</span>
    <span class="group line line-md">{{ $orden->NomEmpl ?? '' }}</span>

    <span class="group label">Cuenta:</span>
    <span class="group line line-sm">{{ $orden->Cuenta ?? '' }}</span>

    <span style="float: right;">
        <span class="group label">Pie</span>
        <span class="check">{{ $esPie ? 'X' : '' }}</span>
        <span class="group label">Rizo</span>
        <span class="check">{{ $esRizo ? 'X' : '' }}</span>
    </span>
</div>

<div class="row">
    <span class="group label">Ancho Balonas:</span>
    <span class="group line line-md">{{ $orden->AnchoBalonas ?? '' }}</span>

    <span class="group label">Cal.</span>
    <span class="group line line-sm">{{ $orden->Calibre ?? '' }}</span>

    <span class="group label">Prov.</span>
    <span class="group line line-md">{{ $orden->LoteProveedor ?? '' }}</span>

    <span class="group label">Solidos:</span>
    <span class="group line line-sm">{{ $produccion && $produccion->Solidos !== null ? number_format($produccion->Solidos, 2, '.', '') : '' }}</span>

    <span class="group label">Color:</span>
    <span class="group line line-md"></span>
</div>

<table>
    <thead>
        <tr>
            <th>FECHA</th>
            <th>H. Inic.</th>
            <th>H. Final</th>
            <th>Metros</th>
            <th>Roturas</th>
            <th>Engomador</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $fechaProd ?: '-' }}</td>
            <td>{{ $horaInicial ?: '-' }}</td>
            <td>{{ $horaFinal ?: '-' }}</td>
            <td>{{ $totalMetros > 0 ? number_format($totalMetros, 0, '.', ',') : '-' }}</td>
            <td>{{ $produccion && $produccion->Roturas !== null ? $produccion->Roturas : '-' }}</td>
            <td>{{ $engomador ?: '-' }}</td>
            <td>{{ $orden->Observaciones ?? '-' }}</td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th>No Julio</th>
            <th>Kg. Bruto</th>
            <th>Tara</th>
            <th>Kg. Neto</th>
            <th>Sol. Can.</th>
            <th>Temp. Canoa 1</th>
            <th>Temp. Canoa 2</th>
            <th>Temp. Tamb.</th>
            <th>Humedad</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $produccion->NoJulio ?? '-' }}</td>
            <td>{{ $produccion && $produccion->KgBruto !== null ? number_format($produccion->KgBruto, 2, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->Tara !== null ? number_format($produccion->Tara, 1, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->KgNeto !== null ? number_format($produccion->KgNeto, 2, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->Solidos !== null ? number_format($produccion->Solidos, 2, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->Canoa1 !== null ? number_format($produccion->Canoa1, 0, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->Canoa2 !== null ? number_format($produccion->Canoa2, 0, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->Tambor !== null ? number_format($produccion->Tambor, 0, '.', '') : '-' }}</td>
            <td>{{ $produccion && $produccion->Humedad !== null ? number_format($produccion->Humedad, 0, '.', '') : '-' }}</td>
        </tr>
    </tbody>
</table>

<div class="row atador" style="margin-top: 6px;">
    <div class="clave-atador">
        <div class="label-box">Clave atador</div>
        <div class="value-box"></div>
    </div>

    <span class="group label">Fecha de atado:</span>
    <span class="group line line-md"></span>

    <span class="group label">Telar:</span>
    <span class="group line line-md">{{ $orden->NoTelarId ?? '' }}</span>

    <span class="group label">Turno:</span>
    <span class="group line line-sm">{{ $turno }}</span>
</div>

<div class="spacer"></div>

<div class="triple-row">
    <div class="triple-cell triple-left">
        <span class="group label">Destino:</span>
        <span class="group">{{ $destino }}</span>
        <span class="group label">Merma:</span>
        <span class="group line line-md"></span>
    </div>
    <div class="triple-cell triple-middle">
        <table style="width: 100%; display: inline-table;">
            <thead>
                <tr>
                    <th>H. Paro</th>
                    <th>H. Inicio</th>
                    <th>H. Final</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="triple-cell triple-right"></div>
</div>

<div class="row" style="margin-top: 6px;">
    <span class="group label">Firma del Supervisor:</span>
    <span class="group line line-md"></span>

    <span class="group label">Observaciones:</span>
    <span class="group line line-md">{{ $orden->Observaciones ?? '' }}</span>

    <span style="float: right;">
        <span class="group label">Bajado por:</span>
        <span class="group line line-md"></span>
    </span>
</div>

<div class="footer">
    <div class="footer-cell footer-left">F-PR-53</div>
    <div class="footer-cell footer-center">{{ date('d.m.y') }}</div>
    <div class="footer-cell footer-right">Version 02</div>
</div>

</body>
</html>
