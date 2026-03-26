<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cortes de Eficiencia - {{ $fecha }}</title>
    @php
        $totalFilas = isset($datos) ? $datos->count() : 0;
        $turnosActivos = max(1, min(3, (int) ($maxTurno ?? 3)));
        $rows = $datos ?? collect();

        // Medir presión vertical real considerando comentarios visibles.
        $rowsConComentario = 0;
        $celdasConComentario = 0;
        $longitudComentarioMax = 0;

        foreach ($rows as $row) {
            $hayComentarioEnFila = false;

            for ($turno = 1; $turno <= $turnosActivos; $turno++) {
                $linea = $row['t' . $turno] ?? null;
                if (!$linea) {
                    continue;
                }

                foreach ([1, 2, 3] as $slot) {
                    $statusCampo = 'StatusOB' . $slot;
                    $obsCampo = 'ObsR' . $slot;
                    $status = (bool) ($linea->$statusCampo ?? false);
                    $texto = trim((string) ($linea->$obsCampo ?? ''));

                    if (!$status && $texto === '') {
                        continue;
                    }

                    $hayComentarioEnFila = true;
                    $celdasConComentario++;
                    $longitud = function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
                    $longitudComentarioMax = max($longitudComentarioMax, $longitud);
                }
            }

            if ($hayComentarioEnFila) {
                $rowsConComentario++;
            }
        }

        $densidadComentarios = $totalFilas > 0 ? ($rowsConComentario / $totalFilas) : 0;
        $celdasComentarioPromedio = $totalFilas > 0 ? ($celdasConComentario / $totalFilas) : 0;

        // Presión total: filas + comentarios + columnas activas (más turnos = menos ancho útil).
        $presionContenido = $totalFilas
            + ($rowsConComentario * 0.65)
            + ($celdasComentarioPromedio * 0.25)
            + (($turnosActivos - 1) * 2.2)
            + ($longitudComentarioMax >= 18 ? 0.9 : 0.0);

        $clamp = function (float $value, float $min, float $max): float {
            return max($min, min($max, $value));
        };

        // Escala tipográfica principal: maximiza tamaño y reduce cuando la presión crece.
        $scaleMin = 0.68;
        $scaleMax = 1.00;
        $presionMin = 22.0;
        $presionMax = 55.0;

        if ($presionContenido <= $presionMin) {
            $typographyScale = $scaleMax;
        } elseif ($presionContenido >= $presionMax) {
            $typographyScale = $scaleMin;
        } else {
            $progress = ($presionContenido - $presionMin) / ($presionMax - $presionMin);
            $typographyScale = $scaleMax - (($scaleMax - $scaleMin) * $progress);
        }

        // Si casi no hay comentarios, dar un pequeño impulso al tamaño.
        if ($densidadComentarios < 0.18 && $totalFilas > 0) {
            $typographyScale += 0.015;
        }

        $typographyScale = $clamp($typographyScale, $scaleMin, $scaleMax);

        // Hard-fit: fuerza una sola hoja cuando hay mucha carga (filas + comentarios).
        $filasEquivalentes = $totalFilas
            + ($rowsConComentario * 1.00)
            + ($celdasComentarioPromedio * 0.45)
            + (($turnosActivos - 1) * 2.60)
            + ($longitudComentarioMax >= 18 ? 1.35 : 0.0);

        // Capacidad de una hoja A4 horizontal con este layout (aprox.).
        $capacidadUnaHoja = 30.0;
        $hardFitScale = $filasEquivalentes > 0 ? ($capacidadUnaHoja / $filasEquivalentes) : 1.0;
        $hardFitScale = $clamp($hardFitScale, 0.58, 1.00);

        // Escala final real aplicada a tamaños CSS (esto sí afecta paginación en Dompdf).
        $sizeScale = $clamp($typographyScale * $hardFitScale, 0.52, 1.00);

        $toPx = function (float $base, float $scale, float $min, float $max) use ($clamp): string {
            $v = $clamp($base * $scale, $min, $max);
            return number_format($v, 2, '.', '') . 'px';
        };

        $toMm = function (float $value): string {
            return number_format($value, 2, '.', '') . 'mm';
        };

        $bodySize = $toPx(10.40, $sizeScale, 6.40, 10.90);
        $thSize = $toPx(10.60, $sizeScale, 6.60, 11.00);
        $tdSize = $toPx(10.40, $sizeScale, 6.40, 10.90);
        $efSize = $toPx(15.40, $sizeScale, 9.20, 15.80);
        $commentSize = $toPx(10.60, $sizeScale, 6.20, 10.70);
        $telarSize = $toPx(13.80, $sizeScale, 8.60, 14.10);
        $rpmSize = $toPx(13.80, $sizeScale, 8.60, 14.10);
        $focusCellHeight = $toPx(13.20, $sizeScale, 8.20, 13.40);
        $turnoHdrSize = $toPx(9.30, $sizeScale, 6.00, 9.50);
        $horarioHdrSize = $toPx(8.90, $sizeScale, 5.80, 9.20);
        $turnoHdrHeight = $toPx(16.40, $sizeScale, 10.00, 16.70);
        $horarioHdrHeight = $toPx(15.20, $sizeScale, 9.50, 15.50);
        $titleSize = $toPx(11.50, $sizeScale, 7.10, 11.80);

        $padV = number_format($clamp(0.90 * $sizeScale, 0.35, 0.95), 2, '.', '');
        $padH = number_format($clamp(1.20 * $sizeScale, 0.45, 1.20), 2, '.', '');
        $cellPadding = $padV . 'px ' . $padH . 'px';

        $marginBase = $clamp(3.0 - ((1 - $sizeScale) * 2.70), 1.2, 3.0);
        $marginPage = $toMm($marginBase);

    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: {{ $marginPage }} {{ $marginPage }} 0mm {{ $marginPage }};
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
            width: 100%;
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
            font-size: 7.1px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        /* ── Badges de folio ── */
        .folios {
            display: block;
            text-align: center;
            font-size: 7.1px;
            margin-bottom: 3px;
            color: #374151;
        }
        .folio-badge {
            display: inline-block;
            border-radius: 8px;
            padding: 1px 4px;
            margin: 0 1px;
            font-size: 7.1px;
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
            display: none;
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
                ? ($turno == 3 ? 'efi-low-t3' : 'efi-low')
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
                @for ($t = 1; $t <= ($maxTurno ?? 3); $t++)
                    <th colspan="4" class="{{ $turnoHdr[$t - 1] }}">Turno {{ $t }}</th>
                @endfor
            </tr>

            {{-- ── Fila 2: RPM + Horarios por turno ── --}}
            <tr>
                @for ($t = 1; $t <= ($maxTurno ?? 3); $t++)
                    <th rowspan="2" class="{{ $hdrH[0] }} col-rpm">RPM</th>
                    <th class="{{ $hdrH[0] }}">{{ $horariosTurno[$t][1] }}</th>
                    <th class="{{ $hdrH[1] }}">{{ $horariosTurno[$t][2] }}</th>
                    <th class="{{ $hdrH[2] }}">{{ $horariosTurno[$t][3] }}</th>
                @endfor
            </tr>

            {{-- ── Fila 3: EF x3 (incluye comentario debajo) ── --}}
            <tr>
                @for ($t = 1; $t <= ($maxTurno ?? 3); $t++)
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
                    $turnos = [];
                    for ($j = 1; $j <= ($maxTurno ?? 3); $j++) {
                        $turnos[$j] = ${"t$j"};
                    }
                @endphp
                <tr>
                    {{-- Telar --}}
                    <td class="td-telar">{{ $row['telar'] }}</td>

                    {{-- ── Turnos Visibles ── --}}
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

    {{-- ── Footer (oculto para ganar espacio) ── --}}
    </div>

</body>
</html>
