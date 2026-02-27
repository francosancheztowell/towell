<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cortes de Eficiencia - {{ $fecha }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 7.5px;
            padding: 10px 12px;
            color: #111827;
            background: #ffffff;
        }

        /* ── Título ── */
        .titulo {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 3px;
        }
        .subtitle {
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        /* ── Badges de folio ── */
        .folios {
            display: block;
            text-align: center;
            font-size: 8px;
            margin-bottom: 8px;
            color: #374151;
        }
        .folio-badge {
            display: inline-block;
            border-radius: 10px;
            padding: 2px 8px;
            margin: 0 3px;
            font-size: 8px;
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
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
            font-size: 7px;
            word-break: break-word;
        }

        /* ── Cabeceras fijas (Telar / STD / %EF Std) ── */
        .hdr-fixed {
            background-color: #374151;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5px;
        }
        .col-telar { width: 42px; }
        .col-std   { width: 32px; }
        .col-ef    { width: 32px; }

        /* ── Cabeceras de Turno (fila 1) ── */
        .hdr-t1 { background-color: #1e40af; color: #ffffff; font-weight: bold; font-size: 8px; }
        .hdr-t2 { background-color: #166534; color: #ffffff; font-weight: bold; font-size: 8px; }
        .hdr-t3 { background-color: #92400e; color: #ffffff; font-weight: bold; font-size: 8px; }

        /* ── Cabeceras de Horario (fila 2) ── */
        .hdr-h1 { background-color: #3b82f6; color: #ffffff; font-weight: bold; font-size: 7px; }
        .hdr-h2 { background-color: #22c55e; color: #ffffff; font-weight: bold; font-size: 7px; }
        .hdr-h3 { background-color: #eab308; color: #1f2937;  font-weight: bold; font-size: 7px; }

        /* ── Cabeceras de columna (fila 3) ── */
        .hdr-c1 { background-color: #93c5fd; color: #1e3a8a; font-weight: bold; }
        .hdr-c2 { background-color: #86efac; color: #14532d; font-weight: bold; }
        .hdr-c3 { background-color: #fde047; color: #78350f; font-weight: bold; }

        /* ── Anchos de columnas de datos ── */
        .col-rpm { width: 30px; }
        .col-pef { width: 28px; }
        .col-obs { width: 50px; text-align: left; }

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

        /* ── Observación ── */
        .obs-check { color: #16a34a; font-weight: bold; }

        /* ── Footer ── */
        .footer {
            margin-top: 8px;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ── Título ── --}}
    <div class="titulo">Cortes de Eficiencia &mdash; {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</div>
    <div class="subtitle">Celdas de % EF por debajo de 70% se resaltan</div>

    {{-- ── Badges de folio por turno ── --}}
    <div class="folios">
        <span class="folio-badge folio-t1">Turno 1: {{ $foliosPorTurno['1'] ?? '—' }}</span>
        <span class="folio-badge folio-t2">Turno 2: {{ $foliosPorTurno['2'] ?? '—' }}</span>
        <span class="folio-badge folio-t3">Turno 3: {{ $foliosPorTurno['3'] ?? '—' }}</span>
    </div>

    @php
        /* ── Helpers ── */
        $val = fn($line, $campo) => $line ? ($line->$campo ?? '') : '';

        $efi = function ($line, $campo) {
            if (!$line) return '';
            $e = $line->$campo ?? null;
            if ($e === null || $e === '') return '';
            return is_numeric($e) ? number_format((float) $e, 2) : $e;
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
            if ($text !== '') return '✓ ' . $text;
            return '✓';
        };

        $turnoHdr = ['hdr-t1', 'hdr-t2', 'hdr-t3'];
        $hdrH     = ['hdr-h1', 'hdr-h2', 'hdr-h3'];
        $hdrC     = ['hdr-c1', 'hdr-c2', 'hdr-c3'];
        $cellH    = ['cell-h1', 'cell-h2', 'cell-h3'];
    @endphp

    <table>
        <thead>

            {{-- ── Fila 1: Telar / STD / %EF Std + Turno N ── --}}
            <tr>
                <th rowspan="3" class="hdr-fixed col-telar">Telar</th>
                <th rowspan="3" class="hdr-fixed col-std">STD</th>
                <th rowspan="3" class="hdr-fixed col-ef">% EF<br>Std</th>
                @for ($t = 1; $t <= 3; $t++)
                    {{-- colspan 7 = H1(3) + H2(2) + H3(2) --}}
                    <th colspan="7" class="{{ $turnoHdr[$t - 1] }}">Turno {{ $t }}</th>
                @endfor
            </tr>

            {{-- ── Fila 2: Horario 1 / 2 / 3 por cada turno ── --}}
            <tr>
                @for ($t = 1; $t <= 3; $t++)
                    <th colspan="3" class="{{ $hdrH[0] }}">Horario 1</th>
                    <th colspan="2" class="{{ $hdrH[1] }}">Horario 2</th>
                    <th colspan="2" class="{{ $hdrH[2] }}">Horario 3</th>
                @endfor
            </tr>

            {{-- ── Fila 3: nombres de columna ── --}}
            <tr>
                @for ($t = 1; $t <= 3; $t++)
                    {{-- H1: RPM · % EF · Obs --}}
                    <th class="{{ $hdrC[0] }} col-rpm">RPM</th>
                    <th class="{{ $hdrC[0] }} col-pef">% EF</th>
                    <th class="{{ $hdrC[0] }} col-obs">Obs</th>
                    {{-- H2: % EF · Obs --}}
                    <th class="{{ $hdrC[1] }} col-pef">% EF</th>
                    <th class="{{ $hdrC[1] }} col-obs">Obs</th>
                    {{-- H3: % EF · Obs --}}
                    <th class="{{ $hdrC[2] }} col-pef">% EF</th>
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

                    {{-- STD (del turno 1) --}}
                    <td>{{ $val($t1, 'RpmStd') }}</td>
                    <td class="{{ $efiClass($t1, 'EficienciaSTD') }}">{{ $efi($t1, 'EficienciaSTD') }}</td>

                    {{-- ── 3 Turnos ── --}}
                    @foreach ($turnos as $tNum => $tx)
                        @php
                            $h1 = $cellH[0];
                            $h2 = $cellH[1];
                            $h3 = $cellH[2];
                        @endphp

                        {{-- Horario 1: RPM · % EF · Obs --}}
                        <td class="{{ $h1 }} col-rpm">{{ $val($tx, 'RpmR1') }}</td>
                        <td class="{{ $h1 }} {{ $efiClass($tx, 'EficienciaR1', $tNum) }}">{{ $efi($tx, 'EficienciaR1') }}</td>
                        <td class="{{ $h1 }} col-obs">{{ $obsText($tx, 'StatusOB1', 'ObsR1') }}</td>

                        {{-- Horario 2: % EF · Obs --}}
                        <td class="{{ $h2 }} {{ $efiClass($tx, 'EficienciaR2', $tNum) }}">{{ $efi($tx, 'EficienciaR2') }}</td>
                        <td class="{{ $h2 }} col-obs">{{ $obsText($tx, 'StatusOB2', 'ObsR2') }}</td>

                        {{-- Horario 3: % EF · Obs --}}
                        <td class="{{ $h3 }} {{ $efiClass($tx, 'EficienciaR3', $tNum) }}">{{ $efi($tx, 'EficienciaR3') }}</td>
                        <td class="{{ $h3 }} col-obs">{{ $obsText($tx, 'StatusOB3', 'ObsR3') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="24" style="padding: 12px; color: #6b7280;">
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
