<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ORDEN ENGOMADO {{ $orden->Folio ?? '' }}</title>
    {{--
        Etiqueta simplificada: una hoja por julio. Sin tamaño fijo: @page size auto
        deja que el diálogo de impresión use la hoja elegida (etiqueta o carta).
        Logo + folio arriba, lote de proveedor en grande y orden/julio y
        cuenta/calibre en dos columnas. Pie con clave de formato, versión y fecha.
    --}}
    <style>
        @page { size: auto; margin: 4mm; }

        html, body {
            margin: 0;
            padding: 0;
            color: #000;
            font-family: Arial, sans-serif;
        }

        .hoja {
            width: 100%;
            page-break-after: always;
            break-after: page;
        }
        .hoja:last-child { page-break-after: auto; break-after: auto; }

        .encabezado { display: table; width: 100%; margin-bottom: 2mm; }
        .encabezado-logo { display: table-cell; width: 50%; vertical-align: middle; }
        .encabezado-logo img { max-height: 14vh; max-width: 45%; }
        .encabezado-folio {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
            text-align: right;
            font-size: 9pt;
        }

        table.etiqueta {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.etiqueta td {
            border: 1px solid #000;
            text-align: center;
            padding: 1mm;
        }

        .rotulo { font-size: clamp(8pt, 3.2vw, 16pt); font-weight: bold; letter-spacing: 1px; }
        .dato { font-size: clamp(10pt, 4vw, 22pt); word-wrap: break-word; }

        .lote {
            font-size: clamp(16pt, 10vw, 64pt);
            font-weight: bold;
            line-height: 1.05;
            word-wrap: break-word;
            padding: 2mm 1mm;
        }

        .pie { display: table; width: 100%; margin-top: 1mm; font-size: 6pt; }
        .pie div { display: table-cell; width: 33.33%; }
        .pie .centro { text-align: center; }
        .pie .derecha { text-align: right; }
    </style>
</head>
<body>
    @php
        $ordenNo = trim((string) ($orden->NoTelarId ?? $orden->Folio ?? '')) ?: '—';
        $cuenta = trim((string) ($orden->Cuenta ?? '')) ?: '—';
        $calibre = trim((string) ($orden->Calibre ?? '')) ?: '—';
        $loteProveedor = trim((string) ($orden->LoteProveedor ?? '')) ?: '—';

        // Una hoja por julio. Sin julios registrados se emite una sola hoja.
        $juliosHoja = collect($registrosPorJulio ?? [])
            ->keys()
            ->map(fn ($julio) => trim((string) $julio))
            ->filter()
            ->values();

        if ($juliosHoja->isEmpty()) {
            $juliosHoja = collect(['—']);
        }
    @endphp

    @foreach ($juliosHoja as $julio)
        <div class="hoja">
            <div class="encabezado">
                <div class="encabezado-logo">
                    @if (! empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="Towell">
                    @endif
                </div>
                <div class="encabezado-folio">Folio: {{ $orden->Folio ?? '—' }}</div>
            </div>

            <table class="etiqueta">
                <tr>
                    <td class="rotulo" colspan="2">LOTE PROVEEDOR</td>
                </tr>
                <tr>
                    <td class="lote" colspan="2">{{ $loteProveedor }}</td>
                </tr>
                <tr>
                    <td class="rotulo">ORDEN</td>
                    <td class="rotulo">JULIO</td>
                </tr>
                <tr>
                    <td class="dato">{{ $ordenNo }}</td>
                    <td class="dato">{{ $julio }}</td>
                </tr>
                <tr>
                    <td class="rotulo">CUENTA</td>
                    <td class="rotulo">CALIBRE</td>
                </tr>
                <tr>
                    <td class="dato">{{ $cuenta }}</td>
                    <td class="dato">{{ $calibre }}</td>
                </tr>
            </table>

            <div class="pie">
                <div>F-PR-70</div>
                <div class="centro">Versión: 0</div>
                <div class="derecha">{{ now()->format('d/m/Y') }}</div>
            </div>
        </div>
    @endforeach
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
