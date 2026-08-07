<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ORDEN ENGOMADO {{ $orden->Folio ?? '' }}</title>
    {{--
        Formato simplificado de reimpresión: una hoja por julio con solo cinco
        datos, a tamaño grande para leerse desde el piso. Dos columnas, salvo el
        lote de proveedor, que ocupa el ancho completo.
    --}}
    <style>
        @page { margin: 10mm; }

        body {
            margin: 0;
            padding: 0;
            color: #000;
            font-family: Arial, sans-serif;
        }

        .hoja {
            width: 100%;
            page-break-after: always;
        }

        .hoja:last-child {
            page-break-after: auto;
        }

        .encabezado {
            display: table;
            width: 100%;
            border-bottom: 3px solid #000;
            padding-bottom: 4mm;
            margin-bottom: 8mm;
        }

        .encabezado-logo { display: table-cell; width: 30%; vertical-align: middle; }
        .encabezado-logo img { max-height: 46px; }

        .encabezado-titulo {
            display: table-cell;
            width: 70%;
            vertical-align: middle;
            text-align: right;
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .encabezado-titulo small {
            display: block;
            color: #c00000;
            font-size: 11pt;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.datos td {
            width: 50%;
            border: 2px solid #000;
            padding: 6mm 5mm;
            vertical-align: top;
        }

        td.completo { width: 100%; }

        .etiqueta {
            display: block;
            margin-bottom: 3mm;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .valor {
            display: block;
            font-size: 52pt;
            font-weight: bold;
            line-height: 1;
            word-wrap: break-word;
        }

        /* El lote suele ser texto largo: entra completo, aunque más chico. */
        .valor-lote { font-size: 40pt; }
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
                <div class="encabezado-titulo">
                    ORDEN ENGOMADO
                    <small>Folio {{ $orden->Folio ?? '—' }}@if (! empty($esReimpresion)) · REIMPRESIÓN @endif</small>
                </div>
            </div>

            <table class="datos">
                <tr>
                    <td>
                        <span class="etiqueta">ORDEN</span>
                        <span class="valor">{{ $ordenNo }}</span>
                    </td>
                    <td>
                        <span class="etiqueta">JULIO</span>
                        <span class="valor">{{ $julio }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="etiqueta">CUENTA</span>
                        <span class="valor">{{ $cuenta }}</span>
                    </td>
                    <td>
                        <span class="etiqueta">CALIBRE</span>
                        <span class="valor">{{ $calibre }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="completo" colspan="2">
                        <span class="etiqueta">LOTE PROVEEDOR</span>
                        <span class="valor valor-lote">{{ $loteProveedor }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
