<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Eficiencia {{ $corte->Folio }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 8px 0; }
        h2 { font-size: 14px; margin: 0 0 12px 0; }
        .info { margin-bottom: 12px; }
        .info span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }
        th { background: #f5f5f5; font-weight: 700; }
        .text-left { text-align: left; }
        .small { font-size: 11px; color: #444; }
    </style>
</head>
<body>
    <h1>Corte de Eficiencia</h1>
    <div class="info">
        <span><strong>Folio:</strong> {{ $corte->Folio }}</span>
        <span><strong>Fecha:</strong> {{ $corte->Date ? $corte->Date->format('d/m/Y') : '--' }}</span>
        <span><strong>Turno:</strong> {{ $corte->Turno ?? '--' }}</span>
        <span><strong>Status:</strong> {{ $corte->Status ?? '--' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="text-left">Telar</th>
                <th rowspan="2">STD</th>
                <th rowspan="2">% EF STD</th>
                <th colspan="3">Horario 1</th>
                <th colspan="3">Horario 2</th>
                <th colspan="3">Horario 3</th>
            </tr>
            <tr>
                <th>RPM</th><th>% EF</th><th>Obs</th>
                <th>RPM</th><th>% EF</th><th>Obs</th>
                <th>RPM</th><th>% EF</th><th>Obs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($telares as $telar)
                @php $linea = $lineasPorTelar[$telar] ?? null; @endphp
                <tr>
                    <td class="text-left">{{ $telar }}</td>
                    <td>{{ $linea->RpmStd ?? '' }}</td>
                    <td>{{ isset($linea->EficienciaSTD) ? round($linea->EficienciaSTD) . '%' : '' }}</td>

                    <td>{{ $linea->RpmR1 ?? '' }}</td>
                    <td>{{ isset($linea->EficienciaR1) ? round($linea->EficienciaR1) . '%' : '' }}</td>
                    <td class="small">{{ $linea->ObsR1 ?? '' }}</td>

                    <td>{{ $linea->RpmR2 ?? '' }}</td>
                    <td>{{ isset($linea->EficienciaR2) ? round($linea->EficienciaR2) . '%' : '' }}</td>
                    <td class="small">{{ $linea->ObsR2 ?? '' }}</td>

                    <td>{{ $linea->RpmR3 ?? '' }}</td>
                    <td>{{ isset($linea->EficienciaR3) ? round($linea->EficienciaR3) . '%' : '' }}</td>
                    <td class="small">{{ $linea->ObsR3 ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
