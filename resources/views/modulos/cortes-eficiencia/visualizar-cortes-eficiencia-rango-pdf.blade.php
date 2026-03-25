{{-- <!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cortes de Eficiencia - Rango: {{ $fecha_inicio }} a {{ $fecha_fin }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page {
            size: A4 landscape;
            margin: 3mm;
        }
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            background: #ffffff;
            line-height: 1.03;
        }
        .page-break {
            page-break-after: always;
        }
        .titulo {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 1px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }
        .hdr-fixed { background-color: #374151; color: #ffffff; font-weight: bold; }
        .hdr-t1 { background-color: #1e40af; color: #ffffff; }
        .hdr-t2 { background-color: #166534; color: #ffffff; }
        .hdr-t3 { background-color: #92400e; color: #ffffff; }
        .hdr-h1 { background-color: #3b82f6; color: #ffffff; }
        .hdr-h2 { background-color: #22c55e; color: #ffffff; }
        .hdr-h3 { background-color: #eab308; color: #1f2937; }
        .hdr-c1 { background-color: #93c5fd; color: #1e3a8a; }
        .hdr-c2 { background-color: #86efac; color: #14532d; }
        .hdr-c3 { background-color: #fde047; color: #78350f; }
        .cell-h1 { background-color: #eff6ff; }
        .cell-h2 { background-color: #f0fdf4; }
        .cell-h3 { background-color: #fefce8; }
        .td-telar { font-weight: bold; background-color: #f3f4f6; }
        .efi-low { background-color: #fde047 !important; }
        .efi-low-t3 { background-color: #f59e0b !important; }
        .ef-value { font-weight: bold; font-size: 9px; }
        .ef-comment { font-size: 6px; }
        .col-telar { width: 32px; }
        .col-rpm { width: 15px; }
        .col-pef { width: 25px; }
    </style>
</head>
<body>
    @php
        $efi = function ($line, $campo) {
            if (!$line) return '';
            $e = $line->$campo ?? null;
            if ($e === null || $e === '') return '';
            return is_numeric($e) ? (string) round((float) $e) : $e;
        };

        $efiClass = function ($line, $campo, $turno = null) {
            if (!$line) return '';
            $e = $line->$campo ?? null;
            if ($e === null || $e === '' || !is_numeric($e)) return '';
            return ((float) $e) < 70
                ? ($turno === 3 ? 'efi-low-t3' : 'efi-low')
                : '';
        };

        $obsText = function ($line, $campoStatus, $campoText) {
            if (!$line) return '';
            $status = (bool) ($line->$campoStatus ?? false);
            $text   = trim($line->$campoText ?? '');
            if (!$status && $text === '') return '';
            return $text;
        };

        $lastRpmTurno = function ($line) {
            if (!$line) return '';
            foreach (['RpmR3', 'RpmR2', 'RpmR1'] as $campo) {
                $v = $line->$campo ?? null;
                if ($v !== null && $v !== '' && (float) $v != 0) return (int) $v;
            }
            return '';
        };
    @endphp

    @foreach ($datosRango as $index => $info)
        <div class="{{ $index < count($datosRango) - 1 ? 'page-break' : '' }}">
            <div class="titulo">Cortes de Eficiencia &mdash; {{ \Carbon\Carbon::parse($info['fecha'])->format('d/m/Y') }}</div>
            
            <table>
                <thead>
                    <tr>
                        <th rowspan="3" class="hdr-fixed col-telar">Telar</th>
                        @for ($t = 1; $t <= 3; $t++)
                            <th colspan="4" class="hdr-t{{ $t }}">Turno {{ $t }}</th>
                        @endfor
                    </tr>
                    <tr>
                        @for ($t = 1; $t <= 3; $t++)
                            @php $h = $info['horariosPorTurno'][(string)$t] ?? []; @endphp
                            <th rowspan="2" class="hdr-h1 col-rpm">RPM</th>
                            <th class="hdr-h1">{{ $h[1] ?? '--:--' }}</th>
                            <th class="hdr-h2">{{ $h[2] ?? '--:--' }}</th>
                            <th class="hdr-h3">{{ $h[3] ?? '--:--' }}</th>
                        @endfor
                    </tr>
                    <tr>
                        @for ($t = 1; $t <= 3; $t++)
                            <th class="hdr-c1 col-pef">EF</th>
                            <th class="hdr-c2 col-pef">EF</th>
                            <th class="hdr-c3 col-pef">EF</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($info['datos'] as $row)
                        @php $turnos = [1 => $row['t1'], 2 => $row['t2'], 3 => $row['t3']]; @endphp
                        <tr>
                            <td class="td-telar">{{ $row['telar'] }}</td>
                            @foreach ($turnos as $tNum => $tx)
                                <td class="cell-h1 col-rpm">{{ $lastRpmTurno($tx) }}</td>
                                <td class="cell-h1 {{ $efiClass($tx, 'EficienciaR1', $tNum) }}">
                                    <div class="ef-value">{{ $efi($tx, 'EficienciaR1') }}</div>
                                    @if ($obsText($tx, 'StatusOB1', 'ObsR1') !== '')
                                        <div class="ef-comment">{{ $obsText($tx, 'StatusOB1', 'ObsR1') }}</div>
                                    @endif
                                </td>
                                <td class="cell-h2 {{ $efiClass($tx, 'EficienciaR2', $tNum) }}">
                                    <div class="ef-value">{{ $efi($tx, 'EficienciaR2') }}</div>
                                    @if ($obsText($tx, 'StatusOB2', 'ObsR2') !== '')
                                        <div class="ef-comment">{{ $obsText($tx, 'StatusOB2', 'ObsR2') }}</div>
                                    @endif
                                </td>
                                <td class="cell-h3 {{ $efiClass($tx, 'EficienciaR3', $tNum) }}">
                                    <div class="ef-value">{{ $efi($tx, 'EficienciaR3') }}</div>
                                    @if ($obsText($tx, 'StatusOB3', 'ObsR3') !== '')
                                        <div class="ef-comment">{{ $obsText($tx, 'StatusOB3', 'ObsR3') }}</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html> --}}
