<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cortes de Eficiencia - {{ $fecha }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 portrait;
            margin: 5px 5px 6px 5px;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 6px;
            padding: 0;
            color: #111827;
            background: #ffffff;
        }

        /* ── Título ── */
        .titulo {
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 1px;
        }
        .subtitle {
            text-align: center;
            font-size: 6px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        /* ── Badges de folio ── */
        .folios {
            display: block;
            text-align: center;
            font-size: 6px;
            margin-bottom: 3px;
            color: #374151;
        }
        .folio-badge {
            display: inline-block;
            border-radius: 8px;
            padding: 1px 4px;
            margin: 0 1px;
            font-size: 6px;
            font-weight: bold;
            border: 1px solid;
        }
        .folio-t1 { background-color: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .folio-t2 { background-color: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .folio-t3 { background-color: #fefce8; color: #92400e; border-color: #fde68a; }

        /* ── Tabla ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 0.3px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
            line-height: 0.95;
        }
        th {
            font-size: 5px;
        }
        td {
            font-size: 6.1px;
        }

        /* ── Cabeceras fijas (Telar / STD / %EF Std) ── */
        .hdr-fixed {
            background-color: #374151;
            color: #ffffff;
            font-weight: bold;
            font-size: 6px;
        }
        .col-telar { width: 20px; }
        .col-fecha { width: 22px; }
        .col-std   { width: 16px; }
        .col-ef    { width: 16px; }

        /* ── Cabeceras de Turno (fila 1) ── */
        .hdr-t1 { background-color: #1e40af; color: #ffffff; font-weight: bold; font-size: 6px; }
        .hdr-t2 { background-color: #166534; color: #ffffff; font-weight: bold; font-size: 6px; }
        .hdr-t3 { background-color: #92400e; color: #ffffff; font-weight: bold; font-size: 6px; }

        /* ── Cabeceras de Horario (fila 2) ── */
        .hdr-h1 { background-color: #3b82f6; color: #ffffff; font-weight: bold; font-size: 5.8px; }
        .hdr-h2 { background-color: #22c55e; color: #ffffff; font-weight: bold; font-size: 5.8px; }
        .hdr-h3 { background-color: #eab308; color: #1f2937;  font-weight: bold; font-size: 5.8px; }

        /* ── Cabeceras de columna (fila 3) ── */
        .hdr-c1 { background-color: #93c5fd; color: #1e3a8a; font-weight: bold; }
        .hdr-c2 { background-color: #86efac; color: #14532d; font-weight: bold; }
        .hdr-c3 { background-color: #fde047; color: #78350f; font-weight: bold; }

        /* ── Anchos de columnas de datos ── */
        .col-rpm { width: 10px; }
        .col-pef { width: 8px; }
        .col-obs { width: 10px; text-align: left; }
        tbody td.col-obs {
            font-size: 4.8px;
            line-height: 0.9;
            padding: 0.2px 0.5px;
        }

        /* ── Celdas de datos por horario ── */
        .cell-h1 { background-color: #eff6ff; }
        .cell-h2 { background-color: #f0fdf4; }
        .cell-h3 { background-color: #fefce8; }

        /* ── Filas alternas de datos ── */
        .row-even td { background-color: inherit; }

        /* ── Celda de Telar ── */
        .td-telar {
            font-weight: bold;
            background-color: #f3f4f6;
            color: #111827;
        }

        /* ── Eficiencia baja ── */
        .efi-low    { background-color: #fde047 !important; color: #111827; font-weight: bold; }
        .efi-low-t3 { background-color: #f59e0b !important; color: #111827; font-weight: bold; }

        /* ── Footer ── */
        .footer {
            margin-top: 3px;
            font-size: 5.5px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ── Título ── --}}
    <div class="titulo">Cortes de Eficiencia &mdash; {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</div>
    {{-- <div class="subtitle">Celdas de % EF por debajo de 70% se resaltan</div> --}}

    {{-- ── Badges de folio por turno ── --}}
    {{-- <div class="folios">
        <span class="folio-badge folio-t1">Turno 1: {{ $foliosPorTurno['1'] ?? '—' }}</span>
        <span class="folio-badge folio-t2">Turno 2: {{ $foliosPorTurno['2'] ?? '—' }}</span>
        <span class="folio-badge folio-t3">Turno 3: {{ $foliosPorTurno['3'] ?? '—' }}</span>
    </div> --}}

    @php
        /* ── Helpers ── */
        $val = fn($line, $campo) => $line ? ($line->$campo ?? '') : '';

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

        $horariosBase = $horariosPorTurno ?? [];
        $horariosTurno = [];
        foreach ([1, 2, 3] as $turno) {
            $horariosTurno[$turno] = [
                1 => $horariosBase[(string) $turno][1] ?? '--:--',
                2 => $horariosBase[(string) $turno][2] ?? '--:--',
                3 => $horariosBase[(string) $turno][3] ?? '--:--',
            ];
        }

        $turnoHdr = ['hdr-t1', 'hdr-t2', 'hdr-t3'];
        $hdrH     = ['hdr-h1', 'hdr-h2', 'hdr-h3'];
        $hdrC     = ['hdr-c1', 'hdr-c2', 'hdr-c3'];
        $cellH    = ['cell-h1', 'cell-h2', 'cell-h3'];
    @endphp

    <table>
        <thead>

            {{-- ── Fila 1: Telar + Turno N ── --}}
            <tr>
                <th rowspan="3" class="hdr-fixed col-telar">Telar</th>
                @for ($t = 1; $t <= 3; $t++)
                    <th colspan="7" class="{{ $turnoHdr[$t - 1] }}">Turno {{ $t }}</th>
                @endfor
            </tr>

            {{-- ── Fila 2: RPM + Horarios por turno ── --}}
            <tr>
                @for ($t = 1; $t <= 3; $t++)
                    <th rowspan="2" class="{{ $hdrH[0] }} col-rpm">RPM</th>
                    <th class="{{ $hdrH[0] }}">{{ $horariosTurno[$t][1] }}</th>
                    <th class="{{ $hdrH[1] }}">{{ $horariosTurno[$t][2] }}</th>
                    <th class="{{ $hdrH[2] }}">{{ $horariosTurno[$t][3] }}</th>
                    <th class="{{ $hdrH[0] }}">{{ $horariosTurno[$t][1] }}</th>
                    <th class="{{ $hdrH[1] }}">{{ $horariosTurno[$t][2] }}</th>
                    <th class="{{ $hdrH[2] }}">{{ $horariosTurno[$t][3] }}</th>
                @endfor
            </tr>

            {{-- ── Fila 3: EF x3 + Obs x3 ── --}}
            <tr>
                @for ($t = 1; $t <= 3; $t++)
                    <th class="{{ $hdrC[0] }} col-pef">EF</th>
                    <th class="{{ $hdrC[1] }} col-pef">EF</th>
                    <th class="{{ $hdrC[2] }} col-pef">EF</th>
                    <th class="{{ $hdrC[0] }} col-obs">Obs</th>
                    <th class="{{ $hdrC[1] }} col-obs">Obs</th>
                    <th class="{{ $hdrC[2] }} col-obs">Obs</th>
                @endfor
            </tr>

        </thead>
        <tbody>
            @forelse ($datos as $i => $row)
                @php
                    $t1 = $row['t1'];
                    $t2 = $row['t2'];
                    $t3 = $row['t3'];
                    $turnos = [1 => $t1, 2 => $t2, 3 => $t3];
                @endphp
                <tr>
                    {{-- Telar --}}
                    <td class="td-telar">{{ $row['telar'] }}</td>

                    {{-- ── 3 Turnos ── --}}
                    @foreach ($turnos as $tNum => $tx)
                        @php
                            $h1 = $cellH[0];
                            $h2 = $cellH[1];
                            $h3 = $cellH[2];
                        @endphp

                        {{-- RPM + EFx3 + Obsx3 por turno --}}
                        <td class="{{ $h1 }} col-rpm">{{ $lastRpmTurno($tx) }}</td>
                        <td class="{{ $h1 }} {{ $efiClass($tx, 'EficienciaR1', $tNum) }}">{{ $efi($tx, 'EficienciaR1') }}</td>
                        <td class="{{ $h2 }} {{ $efiClass($tx, 'EficienciaR2', $tNum) }}">{{ $efi($tx, 'EficienciaR2') }}</td>
                        <td class="{{ $h3 }} {{ $efiClass($tx, 'EficienciaR3', $tNum) }}">{{ $efi($tx, 'EficienciaR3') }}</td>
                        <td class="{{ $h1 }} col-obs">{{ $obsText($tx, 'StatusOB1', 'ObsR1') }}</td>
                        <td class="{{ $h2 }} col-obs">{{ $obsText($tx, 'StatusOB2', 'ObsR2') }}</td>
                        <td class="{{ $h3 }} col-obs">{{ $obsText($tx, 'StatusOB3', 'ObsR3') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="22" style="padding: 6px; color: #6b7280;">
                        Sin datos para la fecha seleccionada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Footer ── --}}
    <div class="footer">
        Generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
