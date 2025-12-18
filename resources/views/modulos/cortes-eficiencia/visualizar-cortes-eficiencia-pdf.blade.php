<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cortes de Eficiencia - {{ $fecha }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            padding: 12px;
            color: #111827;
        }
        h2 {
            text-align: center;
            margin-bottom: 12px;
            font-size: 16px;
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 3px 2px;
            text-align: center;
            font-size: 9px;
        }
        thead th {
            background-color: #1d4ed8;
            color: #fff;
            font-weight: bold;
            font-size: 9px;
        }
        thead tr:nth-child(2) th {
            background-color: #1e40af;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .telar-col {
            font-weight: bold;
            background-color: #f3f4f6;
        }
        .telar-header, .telar-col {
            width: 60px;
        }
        .narrow {
            width: 38px;
        }
        .turno-1 { background-color: #dbeafe; }
        .turno-2 { background-color: #dcfce7; }
        .turno-3 { background-color: #fef3c7; }
        .footer {
            font-size: 8px;
            color: #6b7280;
            margin-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>Cortes de Eficiencia - {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h2>

    <table>
        <thead>
            <tr>
                <th rowspan="3" class="telar-header">Telar</th>
                <th rowspan="2" class="narrow">RPM Std</th>
                <th rowspan="2" class="narrow">% EF Std</th>
                <th colspan="6">Turno 1</th>
                <th colspan="6">Turno 2</th>
                <th colspan="6">Turno 3</th>
            </tr>
            <tr>
                @for ($turno = 1; $turno <= 3; $turno++)
                    @for ($horario = 1; $horario <= 3; $horario++)
                        <th colspan="2" class="narrow">Horario {{ $horario }}</th>
                    @endfor
                @endfor
            </tr>
            <tr>
                @for ($turno = 1; $turno <= 3; $turno++)
                    @for ($horario = 1; $horario <= 3; $horario++)
                        <th class="narrow">RPM</th>
                        <th class="narrow">% EF</th>
                    @endfor
                @endfor
            </tr>
        </thead>
        <tbody>
            @php
                $val = function($line,$campo){ return $line ? ($line->$campo ?? '') : ''; };
                $fmt = function($valor){
                    if($valor === null || $valor === '') return '';
                    if(is_numeric($valor)) {
                        return (string) intval(round($valor));
                    }
                    return $valor;
                };
                $efi = function($line, $campo) use ($fmt){
                    if(!$line) return '';
                    $e = $line->$campo;
                    if($e === null) return '';
                    return $fmt($e);
                };
            @endphp
            @forelse ($datos as $row)
                @php
                    $t1 = $row['t1'];
                    $t2 = $row['t2'];
                    $t3 = $row['t3'];
                @endphp
                <tr>
                    <td class="telar-col">{{ $row['telar'] }}</td>
                    <td class="narrow">{{ $fmt($val($t1,'RpmStd')) }}</td>
                    <td class="narrow">{{ $efi($t1,'EficienciaSTD') }}</td>

                    <td class="turno-1 narrow">{{ $fmt($val($t1,'RpmR1')) }}</td>
                    <td class="turno-1 narrow">{{ $efi($t1,'EficienciaR1') }}</td>
                    <td class="turno-1 narrow">{{ $fmt($val($t1,'RpmR2')) }}</td>
                    <td class="turno-1 narrow">{{ $efi($t1,'EficienciaR2') }}</td>
                    <td class="turno-1 narrow">{{ $fmt($val($t1,'RpmR3')) }}</td>
                    <td class="turno-1 narrow">{{ $efi($t1,'EficienciaR3') }}</td>

                    <td class="turno-2 narrow">{{ $fmt($val($t2,'RpmR1')) }}</td>
                    <td class="turno-2 narrow">{{ $efi($t2,'EficienciaR1') }}</td>
                    <td class="turno-2 narrow">{{ $fmt($val($t2,'RpmR2')) }}</td>
                    <td class="turno-2 narrow">{{ $efi($t2,'EficienciaR2') }}</td>
                    <td class="turno-2 narrow">{{ $fmt($val($t2,'RpmR3')) }}</td>
                    <td class="turno-2 narrow">{{ $efi($t2,'EficienciaR3') }}</td>

                    <td class="turno-3 narrow">{{ $fmt($val($t3,'RpmR1')) }}</td>
                    <td class="turno-3 narrow">{{ $efi($t3,'EficienciaR1') }}</td>
                    <td class="turno-3 narrow">{{ $fmt($val($t3,'RpmR2')) }}</td>
                    <td class="turno-3 narrow">{{ $efi($t3,'EficienciaR2') }}</td>
                    <td class="turno-3 narrow">{{ $fmt($val($t3,'RpmR3')) }}</td>
                    <td class="turno-3 narrow">{{ $efi($t3,'EficienciaR3') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="19">Sin datos para la fecha seleccionada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>Folio Turno 1: {{ $foliosPorTurno['1'] ?? '—' }}</span> |
        <span>Folio Turno 2: {{ $foliosPorTurno['2'] ?? '—' }}</span> |
        <span>Folio Turno 3: {{ $foliosPorTurno['3'] ?? '—' }}</span>
    </div>
</body>
</html>
