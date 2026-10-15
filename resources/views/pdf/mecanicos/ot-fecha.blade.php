@php
    use App\Services\Mecanicos\ReporteOtFechaService;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>OT x Fecha</title>
    <style>
        @page { margin: 18px 22px; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111827; }
        .encabezado { margin-bottom: 10px; }
        .encabezado img { height: 40px; }
        h1 { font-size: 15px; margin: 4px 0 2px; letter-spacing: .5px; }
        .periodo { color: #4b5563; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 3px 5px; vertical-align: top; }
        thead th { background: #1f2937; color: #fff; text-align: left; }
        .mecanico td { background: #dbeafe; font-weight: bold; }
        .total td { background: #e5e7eb; font-weight: bold; }
        .derecha { text-align: right; white-space: nowrap; }
        .nowrap { white-space: nowrap; }
        .cruza { color: #92400e; font-size: 9px; }
    </style>
</head>
<body>
    <div class="encabezado">
        @if ($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Towell">
        @endif
        <h1>ÓRDENES DE TRABAJO POR FECHA</h1>
        <div class="periodo">
            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
            · {{ $reporte['mecanico_nombre'] !== '' ? $reporte['mecanico_nombre'] : 'Todos los mecánicos' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th><th>Folio</th><th>Telar</th><th>Falla</th><th>Trabajo realizado</th>
                <th>Inicio</th><th>Fin</th><th>Duración</th><th>Estatus</th><th>Comentarios</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporte['mecanicos'] as $grupo)
                <tr class="mecanico">
                    <td colspan="7">{{ $grupo['nombre'] }} ({{ $grupo['cve'] }}) · {{ $grupo['total_ots'] }} OT · {{ $grupo['total_trabajos'] }} trabajos</td>
                    <td class="derecha">{{ ReporteOtFechaService::duracion($grupo['total_minutos']) }}</td>
                    <td colspan="2"></td>
                </tr>
                @foreach ($grupo['renglones'] as $fila)
                    <tr>
                        <td class="nowrap">{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                        <td class="nowrap">{{ $fila['folio'] }}</td>
                        <td class="nowrap">{{ $fila['telar'] }}</td>
                        <td>{{ $fila['falla'] }}</td>
                        <td>{{ $fila['trabajo'] }}</td>
                        <td class="nowrap">{{ $fila['hora_inicio'] }}</td>
                        <td class="nowrap">{{ $fila['hora_fin'] }}@if ($fila['cruza_dia']) <span class="cruza">(+1 día)</span>@endif</td>
                        <td class="derecha">{{ ReporteOtFechaService::duracion($fila['minutos']) }}</td>
                        <td class="nowrap">{{ $fila['estatus'] }}</td>
                        <td>{{ $fila['comentarios'] }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="10">No hay órdenes de trabajo en el rango seleccionado.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="7">Total · {{ count($reporte['mecanicos']) }} mecánicos · {{ $reporte['total_ots'] }} OT · {{ $reporte['total_trabajos'] }} trabajos</td>
                <td class="derecha">{{ ReporteOtFechaService::duracion($reporte['total_minutos']) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
