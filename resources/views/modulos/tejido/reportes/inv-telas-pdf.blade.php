<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Inventario Telas</title>
    <style>
        @page {
            margin: 6px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 7px;
            color: #111827;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 2px;
        }
        .header td {
            vertical-align: middle;
        }
        .header-logo {
            width: 26%;
            text-align: left;
        }
        .header-logo img {
            max-height: 32px;
            max-width: 150px;
        }
        .header-title-wrap {
            width: 74%;
            text-align: center;
        }
        .titulo {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 2px;
        }
        .subtitulo {
            font-size: 7px;
            margin-bottom: 2px;
        }
        .tabla-principal {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 0;
        }
        .tabla-principal th,
        .tabla-principal td {
            border: 1px solid #1f2937;
            padding: 1px 2px;
            line-height: 1.05;
            word-wrap: break-word;
        }
        .tabla-principal thead th {
            text-align: center;
            font-weight: bold;
        }
        .row-dias th {
            background: #111827;
            color: #ffffff;
            font-size: 6.7px;
        }
        .row-columnas th {
            background: #f3f4f6;
            color: #111827;
            font-size: 6.7px;
        }
        .row-columnas th.th-dia-fecha {
            background: #111827;
            color: #ffffff;
        }
        .row-seccion td {
            background: #e9e4ad;
            font-weight: bold;
            text-transform: uppercase;
        }
        .col-no {
            width: 4%;
            text-align: center;
            font-weight: bold;
        }
        .col-fibra {
            width: 12%;
        }
        .col-calibre {
            width: 5%;
            text-align: center;
        }
        .col-cuenta {
            width: 9%;
            text-align: center;
        }
        .col-dia {
            text-align: center;
            width: 12.8%;
        }
        .layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .layout td {
            vertical-align: top;
        }
        .col-principal {
            width: 62%;
            padding-right: 3px;
        }
        .col-resumen {
            width: 38%;
            padding-left: 3px;
        }
        .vacio {
            color: #9ca3af;
        }
        .bloque-telares {
            border: 1px solid #1f2937;
            margin-bottom: 0;
            page-break-inside: avoid;
        }
        .bloque-telares-titulo {
            background: #111827;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 7px;
            padding: 2px;
            border-bottom: 1px solid #1f2937;
        }
        .resumen-box {
            width: 100%;
            border: 1px solid #1f2937;
            margin-bottom: 4px;
            page-break-inside: avoid;
        }
        .resumen-titulo {
            background: #f3f4f6;
            border-bottom: 1px solid #1f2937;
            font-weight: bold;
            font-size: 7px;
            padding: 2px;
            text-transform: uppercase;
        }
        .resumen-tabla {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .resumen-tabla th,
        .resumen-tabla td {
            border: 1px solid #c7cbd1;
            padding: 1px 2px;
            font-size: 6.6px;
            line-height: 1.1;
            word-wrap: break-word;
        }
        .resumen-tabla th {
            background: #eef2f7;
            text-align: center;
            font-weight: bold;
        }
        .resumen-centro {
            text-align: center;
        }
        .pie {
            margin-top: 4px;
            text-align: right;
            color: #6b7280;
            font-size: 6.5px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="header-logo">
                @if (!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo Towell">
                @endif
            </td>
            <td class="header-title-wrap">
                <div class="titulo">REPORTE INVENTARIO TELAS EN PISO</div>
                <div class="subtitulo">
                    RANGO: {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} AL {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="layout">
        <tr>
            <td class="col-principal">
                <table class="tabla-principal">
                    <thead>
                        <tr class="row-dias">
                            <th class="col-no" colspan="4">TELARES</th>
                            @foreach ($dias as $dia)
                                <th class="col-dia">{{ $dia['dia_nombre'] ?? $dia['label'] }}</th>
                            @endforeach
                        </tr>
                        <tr class="row-columnas">
                            <th class="col-no">No. Telar</th>
                            <th class="col-fibra">FIBRA</th>
                            <th class="col-calibre">CALIBRE</th>
                            <th class="col-cuenta">CUENTA R/P</th>
                            @foreach ($dias as $dia)
                                <th class="col-dia th-dia-fecha">{{ $dia['fecha_excel'] ?? $dia['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secciones as $seccion)
                            <tr class="row-seccion">
                                <td colspan="{{ 4 + count($dias) }}">{{ $seccion['nombre'] }}</td>
                            </tr>
                            @foreach ($seccion['filas'] as $fila)
                                @php
                                    $fibra = trim((string) ($fila['fibra'] ?? ''));
                                    $calibre = trim((string) ($fila['calibre'] ?? ''));
                                    $cuentaRizo = trim((string) ($fila['cuenta_rizo'] ?? ''));
                                    $cuentaPie = trim((string) ($fila['cuenta_pie'] ?? ''));
                                    if ($cuentaRizo !== '' && $cuentaPie !== '') {
                                        $cuentaRP = 'R: ' . $cuentaRizo . ' | P: ' . $cuentaPie;
                                    } elseif ($cuentaRizo !== '') {
                                        $cuentaRP = 'R: ' . $cuentaRizo;
                                    } elseif ($cuentaPie !== '') {
                                        $cuentaRP = 'P: ' . $cuentaPie;
                                    } else {
                                        $cuentaRP = '-';
                                    }
                                @endphp
                                <tr>
                                    <td class="col-no">{{ $fila['no_telar'] }}</td>
                                    <td class="col-fibra">{{ $fibra !== '' ? $fibra : '-' }}</td>
                                    <td class="col-calibre">{{ $calibre !== '' ? $calibre : '-' }}</td>
                                    <td class="col-cuenta">{{ $cuentaRP }}</td>
                                    @foreach ($dias as $dia)
                                        @php $valorDia = trim((string) ($fila['por_dia'][$dia['fecha']] ?? '')); @endphp
                                        <td class="col-dia">{{ $valorDia }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td class="col-resumen">
                <div class="bloque-telares">
                    <div class="bloque-telares-titulo">TELARES</div>
                    @foreach ($resumenSecciones as $resumen)
                        <div class="resumen-box">
                            <div class="resumen-titulo">
                                {{ $resumen['nombre'] }}
                                @if (!empty($resumen['telares']))
                                    {{ implode('-', $resumen['telares']) }}
                                @endif
                            </div>
                            <table class="resumen-tabla">
                                <thead>
                                    <tr>
                                        <th style="width: 38%;">FIBRA</th>
                                        <th style="width: 16%;">CALIBRE</th>
                                        <th style="width: 46%;">CUENTA R/P</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($resumen['lineas'] as $linea)
                                        <tr>
                                            <td>{{ $linea['fibra'] !== '' ? $linea['fibra'] : '-' }}</td>
                                            <td class="resumen-centro">{{ $linea['calibre'] !== '' ? $linea['calibre'] : '-' }}</td>
                                            <td>{{ $linea['cuenta_unificada'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="resumen-centro vacio">SIN DATOS</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <div class="pie">Generado: {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
