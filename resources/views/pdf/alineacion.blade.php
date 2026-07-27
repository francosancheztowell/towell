<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alineación</title>
    <style>
        @page { margin: 8px; }
        body { font-family: Arial, sans-serif; font-size: 5.5px; color: #111827; }
        .logo { height: 45px; margin-bottom: 4px; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .subtitulo { font-size: 9px; color: #4b5563; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 1px 2px; text-align: center; overflow: hidden; word-wrap: break-word; }
        thead th { background-color: #c6e0b4; color: #000; }
        td.col-destacada { background-color: #ccecff; font-weight: bold; }
        td.col-blanca { background-color: #ffffff; }
    </style>
</head>
<body>
    @if ($logoBase64)
        <img src="{{ $logoBase64 }}" class="logo" alt="Towell">
    @endif

    <h1>Alineación — Programa de tejido en proceso</h1>
    <p class="subtitulo">Generado: {{ $generadoEn }}</p>

    @php
        // Observaciones no entra en el PDF: es texto libre largo, deforma la tabla impresa.
        $columnas = array_values(array_diff($columnas, ['Observaciones']));

        // Grupos con encabezado combinado (fila 1); columnas sueltas usan rowspan=2.
        $groupFirstCol = [];
        $colInGroup = [];
        foreach ($headerGroups as $parent => $cols) {
            $groupFirstCol[$cols[0]] = ['parent' => $parent, 'colspan' => count($cols)];
            foreach ($cols as $c) {
                $colInGroup[$c] = true;
            }
        }
        // Columnas de Telar a Tolerancia: destacadas (azul + negritas).
        $indiceUltimaDestacada = array_search($ultimaColumnaDestacada, $columnas, true);

        // Modelo lleva texto largo: se lleva la mayor parte del ancho. El resto se
        // reparte por peso: valores de una letra/dos dígitos quedan muy angostos,
        // números cortos angostos, y el resto (fechas, claves, tipos) normal.
        $anchoModelo = 25.0;
        $columnasMuyAngostas = ['Tolerancia', 'RazSN'];
        $columnasAngostas = [
            'NoTelarId', 'CalibreRizo', 'Ancho', 'LargoCrudo', 'PesoCrudo',
            'Luchaje', 'MedidaPlano', 'NoTiras', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4',
            'AnchoToalla', 'PesoGRM2', 'PesoMin', 'PesoMax', 'MuestraMin', 'MuestraMax',
            'Produccion', 'SaldoPedido', 'DiasEficiencia',
        ];
        $pesoMuyAngosto = 0.2;
        $pesoAngosto = 0.35;
        $pesoNormal = 0.9;

        $pesoColumna = function (string $columna) use ($columnasMuyAngostas, $columnasAngostas, $pesoMuyAngosto, $pesoAngosto, $pesoNormal) {
            if (in_array($columna, $columnasMuyAngostas, true)) {
                return $pesoMuyAngosto;
            }
            if (in_array($columna, $columnasAngostas, true)) {
                return $pesoAngosto;
            }

            return $pesoNormal;
        };

        $pesoTotal = 0.0;
        foreach ($columnas as $columna) {
            if ($columna === 'NombreProducto') {
                continue;
            }
            $pesoTotal += $pesoColumna($columna);
        }
        $anchoPorPeso = $pesoTotal > 0 ? (100 - $anchoModelo) / $pesoTotal : 0;

        $anchos = [];
        foreach ($columnas as $columna) {
            $anchos[$columna] = $columna === 'NombreProducto'
                ? $anchoModelo
                : $pesoColumna($columna) * $anchoPorPeso;
        }
    @endphp

    <table>
        <colgroup>
            @foreach ($columnas as $columna)
                <col style="width: {{ $anchos[$columna] }}%">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    @if (isset($groupFirstCol[$columna]))
                        <th colspan="{{ $groupFirstCol[$columna]['colspan'] }}">{{ $groupFirstCol[$columna]['parent'] }}</th>
                    @elseif (!empty($colInGroup[$columna]))
                        @continue
                    @else
                        <th rowspan="2">{{ $columnLabels[$columna] ?? $columna }}</th>
                    @endif
                @endforeach
            </tr>
            <tr>
                @foreach ($columnas as $columna)
                    @if (!empty($colInGroup[$columna]))
                        <th>{{ $subColumnLabels[$columna] ?? '' }}</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    @foreach ($columnas as $idx => $col)
                        <td class="{{ $indiceUltimaDestacada !== false && $idx <= $indiceUltimaDestacada ? 'col-destacada' : 'col-blanca' }}">{{ $item[$col] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columnas) }}">No hay datos para mostrar</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
