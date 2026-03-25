<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Marcas Finales - {{ $fecha }}</title>
    @php
        $totalTelares = isset($telares) ? $telares->count() : 0;

        // Ajuste dinámico para que la tabla complete quepa en una sola hoja.
        if ($totalTelares >= 70) {
            $fontSize = '10.6px';
            $cellPadding = '1px 2px';
            $titleSize = '15.9px';
            $headerSize = '10.6px';
        } elseif ($totalTelares >= 55) {
            $fontSize = '11.1px';
            $cellPadding = '1px 2px';
            $titleSize = '16.9px';
            $headerSize = '11.1px';
        } elseif ($totalTelares >= 45) {
            $fontSize = '11.6px';
            $cellPadding = '1px 3px';
            $titleSize = '17.4px';
            $headerSize = '11.6px';
        } else {
            $fontSize = '12.6px';
            $cellPadding = '2px 3px';
            $titleSize = '17.9px';
            $headerSize = '12.1px';
        }
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: {{ $fontSize }};
            line-height: 1.1;
        }
        h2 {
            text-align: center;
            margin-bottom: 6px;
            font-size: {{ $titleSize }};
            color: #1e40af;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #ddd;
            padding: {{ $cellPadding }};
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }
        thead th {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            font-size: {{ $headerSize }};
        }
        thead tr:first-child th {
            font-size: {{ $headerSize }};
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .telar-col {
            font-weight: bold;
            background-color: #f3f4f6;
            width: 6%;
        }
        .turno-separator {
            border-right: 2px solid #2563eb;
        }
        .page-wrap {
            page-break-inside: avoid;
        }
        .footer {
            margin-top: 4px;
            font-size: {{ $fontSize }};
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
    <h2>Marcas Finales de Turno - {{ $fecha }}</h2>
    
    <table>
        <thead>
            <tr>
                <th rowspan="2">Telar</th>
                <th colspan="6" class="turno-separator">Turno 1</th>
                <th colspan="6" class="turno-separator">Turno 2</th>
                <th colspan="6">Turno 3</th>
            </tr>
            <tr>
                @for ($i=0;$i<3;$i++)
                    <th>% Ef</th>
                    <th>Marcas</th>
                    <th>TRAMA</th>
                    <th>PIE</th>
                    <th>RIZO</th>
                    <th class="{{ $i < 2 ? 'turno-separator' : '' }}">OTROS</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @php
                $fmtEfi = function($linea){
                    if(!$linea) return '';
                    $e = $linea->Eficiencia ?? $linea->EficienciaSTD ?? $linea->EficienciaStd ?? null;
                    if($e === null || $e === '') return '';
                    if(is_numeric($e) && $e <= 1) $e = $e * 100;
                    return intval(round($e)).'%';
                };
                $get = function($turno, $telar) use ($porTurno){
                    return optional(optional($porTurno->get($turno))['lineas'])->get($telar);
                };
                $val = function($l,$c){ return $l ? ($l->$c ?? '') : ''; };
            @endphp
            
            @forelse ($telares as $telar)
                @php
                    $t1 = $get(1, $telar);
                    $t2 = $get(2, $telar);
                    $t3 = $get(3, $telar);
                @endphp
                <tr>
                    <td class="telar-col">{{ $telar }}</td>
                    <!-- Turno 1 -->
                    <td>{{ $fmtEfi($t1) }}</td>
                    <td>{{ $val($t1,'Marcas') }}</td>
                    <td>{{ $val($t1,'Trama') }}</td>
                    <td>{{ $val($t1,'Pie') }}</td>
                    <td>{{ $val($t1,'Rizo') }}</td>
                    <td class="turno-separator">{{ $val($t1,'Otros') }}</td>
                    <!-- Turno 2 -->
                    <td>{{ $fmtEfi($t2) }}</td>
                    <td>{{ $val($t2,'Marcas') }}</td>
                    <td>{{ $val($t2,'Trama') }}</td>
                    <td>{{ $val($t2,'Pie') }}</td>
                    <td>{{ $val($t2,'Rizo') }}</td>
                    <td class="turno-separator">{{ $val($t2,'Otros') }}</td>
                    <!-- Turno 3 -->
                    <td>{{ $fmtEfi($t3) }}</td>
                    <td>{{ $val($t3,'Marcas') }}</td>
                    <td>{{ $val($t3,'Trama') }}</td>
                    <td>{{ $val($t3,'Pie') }}</td>
                    <td>{{ $val($t3,'Rizo') }}</td>
                    <td>{{ $val($t3,'Otros') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="19">Sin datos para la fecha seleccionada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <span>Folio Turno 1: {{ optional($porTurno->get(1))['folio'] ?? '—' }}</span> | 
        <span>Folio Turno 2: {{ optional($porTurno->get(2))['folio'] ?? '—' }}</span> | 
        <span>Folio Turno 3: {{ optional($porTurno->get(3))['folio'] ?? '—' }}</span>
    </div>
    </div>
</body>
</html>
