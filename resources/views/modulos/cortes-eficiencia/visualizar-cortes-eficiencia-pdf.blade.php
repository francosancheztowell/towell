<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cortes de Eficiencia - {{ $fecha }}</title>
    @php
        $totalFilas = isset($datos) ? $datos->count() : 0;

        // Escalado dinámico para conservar 1 sola hoja.
        if ($totalFilas >= 44) {
            $bodySize = '6.8px';
            $thSize = '7px';
            $tdSize = '6.8px';
            $efSize = '8.6px';
            $commentSize = '5.6px';
            $telarSize = '8px';
            $rpmSize = '8px';
            $focusCellHeight = '11px';
            $turnoHdrSize = '7.7px';
            $horarioHdrSize = '7.4px';
            $turnoHdrHeight = '14px';
            $horarioHdrHeight = '13px';
            $cellPadding = '0.6px 0.7px';
            $marginPage = '2.8mm';
            $titleSize = '9.8px';
            $fitScale = 0.90;
        } elseif ($totalFilas >= 36) {
            $bodySize = '7.1px';
            $thSize = '7.3px';
            $tdSize = '7.1px';
            $efSize = '9px';
            $commentSize = '5.8px';
            $telarSize = '8.4px';
            $rpmSize = '8.4px';
            $focusCellHeight = '11.6px';
            $turnoHdrSize = '8.1px';
            $horarioHdrSize = '7.8px';
            $turnoHdrHeight = '14.8px';
            $horarioHdrHeight = '13.8px';
            $cellPadding = '0.6px 0.8px';
            $marginPage = '2.8mm';
            $titleSize = '10.2px';
            $fitScale = 0.93;
        } elseif ($totalFilas >= 28) {
            $bodySize = '7.5px';
            $thSize = '7.7px';
            $tdSize = '7.5px';
            $efSize = '9.6px';
            $commentSize = '6.2px';
            $telarSize = '8.8px';
            $rpmSize = '8.8px';
            $focusCellHeight = '12.4px';
            $turnoHdrSize = '8.5px';
            $horarioHdrSize = '8.2px';
            $turnoHdrHeight = '15.6px';
            $horarioHdrHeight = '14.6px';
            $cellPadding = '0.8px 1px';
            $marginPage = '3mm';
            $titleSize = '10.6px';
            $fitScale = 0.96;
        } else {
            $bodySize = '8px';
            $thSize = '8.2px';
            $tdSize = '8px';
            $efSize = '10.2px';
            $commentSize = '6.6px';
            $telarSize = '9.4px';
            $rpmSize = '9.4px';
            $focusCellHeight = '13.2px';
            $turnoHdrSize = '9px';
            $horarioHdrSize = '8.6px';
            $turnoHdrHeight = '16.4px';
            $horarioHdrHeight = '15.2px';
            $cellPadding = '0.9px 1.2px';
            $marginPage = '3mm';
            $titleSize = '11.2px';
            $fitScale = 1;
        }
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: {{ $marginPage }};
        }

        body {
            font-family: Arial, sans-serif;
            font-size: {{ $bodySize }};
            padding: 0;
            color: #111827;
            background: #ffffff;
            line-height: 1.03;
        }
        .page-fit {
            width: calc(100% / {{ $fitScale }});
            transform: scale({{ $fitScale }});
            transform-origin: top left;
        }

        /* ── Título ── */
        .titulo {
            text-align: center;
            font-size: {{ $titleSize }};
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 1px;
        }
        .subtitle {
            text-align: center;
            font-size: 6.8px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        /* ── Badges de folio ── */
        .folios {
            display: block;
            text-align: center;
            font-size: 6.8px;
            margin-bottom: 3px;
            color: #374151;
        }
        .folio-badge {
            display: inline-block;
            border-radius: 8px;
            padding: 1px 4px;
            margin: 0 1px;
            font-size: 6.8px;
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
            padding: {{ $cellPadding }};
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
            line-height: 1.03;
        }
        th {
            font-size: {{ $thSize }};
        }
        td {
            font-size: {{ $tdSize }};
        }

        /* ── Cabeceras fijas (Telar / STD / %EF Std) ── */
        .hdr-fixed {
            background-color: #374151;
            color: #ffffff;
            font-weight: bold;
            font-size: {{ $thSize }};
        }
        .col-telar { width: 32px; }
        .col-fecha { width: 18px; }
        .col-std   { width: 13px; }
        .col-ef    { width: 13px; }

        /* ── Cabeceras de Turno (fila 1) ── */
        .hdr-t1,
        .hdr-t2,
        .hdr-t3 {
            color: #ffffff;
            font-weight: bold;
            font-size: {{ $turnoHdrSize }};
            min-height: {{ $turnoHdrHeight }};
            height: {{ $turnoHdrHeight }};
            line-height: 1.08;
            padding-top: 1px;
            padding-bottom: 1px;
        }
        .hdr-t1 { background-color: #1e40af; }
        .hdr-t2 { background-color: #166534; }
        .hdr-t3 { background-color: #92400e; }

        /* ── Cabeceras de Horario (fila 2) ── */
        .hdr-h1,
        .hdr-h2,
        .hdr-h3 {
            font-weight: bold;
            font-size: {{ $horarioHdrSize }};
            min-height: {{ $horarioHdrHeight }};
            height: {{ $horarioHdrHeight }};
            line-height: 1.08;
            padding-top: 1px;
            padding-bottom: 1px;
        }
        .hdr-h1 { background-color: #3b82f6; color: #ffffff; }
        .hdr-h2 { background-color: #22c55e; color: #ffffff; }
        .hdr-h3 { background-color: #eab308; color: #1f2937; }

        /* ── Cabeceras de columna (fila 3) ── */
        .hdr-c1 { background-color: #93c5fd; color: #1e3a8a; font-weight: bold; }
        .hdr-c2 { background-color: #86efac; color: #14532d; font-weight: bold; }
        .hdr-c3 { background-color: #fde047; color: #78350f; font-weight: bold; }

        /* ── Anchos de columnas de datos ── */
        .col-rpm { width: 15px; }
        .col-pef { width: 25px; }
        th.col-rpm { font-size: {{ $thSize }}; }
        td.col-rpm {
            font-size: {{ $rpmSize }};
            font-weight: 700;
            min-height: {{ $focusCellHeight }};
            height: {{ $focusCellHeight }};
            line-height: 1.08;
        }
        .ef-wrap { line-height: 1; }
        .ef-value { font-size: {{ $efSize }}; font-weight: 700; }
        .ef-comment {
            margin-top: 0;
            font-size: {{ $commentSize }};
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            max-height: none;
            line-height: 1;
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
            font-size: {{ $telarSize }};
            min-height: {{ $focusCellHeight }};
            height: {{ $focusCellHeight }};
            line-height: 1.08;
        }

        /* ── Eficiencia baja ── */
        .efi-low    { background-color: #fde047 !important; color: #111827; font-weight: bold; }
        .efi-low-t3 { background-color: #f59e0b !important; color: #111827; font-weight: bold; }

        /* ── Footer ── */
        .footer {
            margin-top: 3px;
            font-size: 6.3px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="page-fit">

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
                    <th colspan="4" class="{{ $turnoHdr[$t - 1] }}">Turno {{ $t }}</th>
                @endfor
            </tr>

            {{-- ── Fila 2: RPM + Horarios por turno ── --}}
            <tr>
                @for ($t = 1; $t <= 3; $t++)
                    <th rowspan="2" class="{{ $hdrH[0] }} col-rpm">RPM</th>
                    <th class="{{ $hdrH[0] }}">{{ $horariosTurno[$t][1] }}</th>
                    <th class="{{ $hdrH[1] }}">{{ $horariosTurno[$t][2] }}</th>
                    <th class="{{ $hdrH[2] }}">{{ $horariosTurno[$t][3] }}</th>
                @endfor
            </tr>

            {{-- ── Fila 3: EF x3 (incluye comentario debajo) ── --}}
            <tr>
                @for ($t = 1; $t <= 3; $t++)
                    <th class="{{ $hdrC[0] }} col-pef">EF</th>
                    <th class="{{ $hdrC[1] }} col-pef">EF</th>
                    <th class="{{ $hdrC[2] }} col-pef">EF</th>
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

                        {{-- RPM + EFx3 (comentario debajo en cada EF) --}}
                        <td class="{{ $h1 }} col-rpm">{{ $lastRpmTurno($tx) }}</td>
                        <td class="{{ $h1 }} {{ $efiClass($tx, 'EficienciaR1', $tNum) }}">
                            <div class="ef-wrap">
                                <div class="ef-value">{{ $efi($tx, 'EficienciaR1') }}</div>
                                @if ($obsText($tx, 'StatusOB1', 'ObsR1') !== '')
                                    <div class="ef-comment">{{ $obsText($tx, 'StatusOB1', 'ObsR1') }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="{{ $h2 }} {{ $efiClass($tx, 'EficienciaR2', $tNum) }}">
                            <div class="ef-wrap">
                                <div class="ef-value">{{ $efi($tx, 'EficienciaR2') }}</div>
                                @if ($obsText($tx, 'StatusOB2', 'ObsR2') !== '')
                                    <div class="ef-comment">{{ $obsText($tx, 'StatusOB2', 'ObsR2') }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="{{ $h3 }} {{ $efiClass($tx, 'EficienciaR3', $tNum) }}">
                            <div class="ef-wrap">
                                <div class="ef-value">{{ $efi($tx, 'EficienciaR3') }}</div>
                                @if ($obsText($tx, 'StatusOB3', 'ObsR3') !== '')
                                    <div class="ef-comment">{{ $obsText($tx, 'StatusOB3', 'ObsR3') }}</div>
                                @endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="padding: 8px; color: #6b7280;">
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
    </div>

</body>
</html>
