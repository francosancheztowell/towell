<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hoja de verificación estado máquina</title>
    <style>
        @page { margin: 16px; }
        body { font-family: Arial, sans-serif; font-size: 8px; color: #111827; }
        .encabezado { margin-bottom: 12px; }
        .encabezado img { height: 42px; display: block; margin-bottom: 6px; }
        .titulo { font-size: 13px; font-weight: bold; margin: 0 0 2px 0; }
        .subtitulo { font-size: 9px; margin: 0; }
        table.matriz { border-collapse: collapse; }
        table.matriz th, table.matriz td {
            border: 0.6px solid #4b5563;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .control { text-align: left; width: 130px; }
        .prioridad { width: 42px; }
        .telar-th { width: 28px; height: 92px; vertical-align: bottom; padding: 2px 1px; }
        .telar-label { font-weight: bold; font-size: 8px; }
        .telar-num { font-weight: bold; font-size: 10px; }
        .calif {
            writing-mode: tb-rl;
            -webkit-writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-size: 7px;
            font-weight: normal;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="encabezado">
        @if ($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Towell">
        @endif
        <p class="titulo">HOJA DE VERIFICACIÓN ESTADO MÁQUINA</p>
        <p class="subtitulo">
            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
        </p>
    </div>

    <table class="matriz">
        <thead>
            <tr>
                <th rowspan="2" class="control">Control</th>
                <th rowspan="2" class="prioridad">Prioridad</th>
                @foreach ($reporte['salones'] as $salon)
                    <th colspan="{{ count($salon['telares']) }}" style="background-color: #{{ $salon['color'] }}">
                        {{ $salon['nombre'] }}
                    </th>
                @endforeach
            </tr>
            <tr>
                @foreach ($reporte['salones'] as $salon)
                    @foreach ($salon['telares'] as $telar)
                        <th class="telar-th" style="background-color: #{{ $salon['color'] }}">
                            <div class="telar-label">Telar</div>
                            <div class="telar-num">{{ $telar['id'] }}</div>
                            <div class="calif">calificación</div>
                        </th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($reporte['actividades'] as $actividad)
                <tr>
                    <td class="control">{{ $actividad['nombre'] }}</td>
                    <td>{{ $actividad['prioridad'] }}</td>
                    @foreach ($reporte['salones'] as $salon)
                        @foreach ($salon['telares'] as $telar)
                            @php $valor = $actividad['valores'][$telar['id']] ?? 0; @endphp
                            <td @if ($valor > 0) style="background-color: #{{ \App\Services\Mecanicos\ReporteEstadoMaquinaService::COLOR_CELDA[$valor] }}" @endif>
                                {{ $valor }}
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
