<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Órdenes de Trabajo Diarias</title>
    <style>
        @page { margin: 12px; }
        body { font-family: Arial, sans-serif; font-size: 7px; color: #111827; }
        .encabezado { margin-bottom: 8px; }
        .encabezado img { height: 36px; display: block; margin-bottom: 4px; }
        .titulo { font-size: 12px; font-weight: bold; margin: 0 0 2px 0; }
        .subtitulo { font-size: 8px; margin: 0; }
        .ot-matriz { border-collapse: collapse; }
        .ot-matriz th, .ot-matriz td {
            border: 0.5px solid #4b5563;
            padding: 2px 3px;
            text-align: center;
            vertical-align: middle;
            font-size: 7px;
        }
        .ot-matriz .font-bold { font-weight: bold; }
        .ot-matriz .text-white { color: #ffffff; }
        .ot-matriz .text-left { text-align: left; }
        .ot-matriz .sticky { position: static; }
    </style>
</head>
<body>
    <div class="encabezado">
        @if ($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Towell">
        @endif
        <p class="titulo">ÓRDENES DE TRABAJO DIARIAS</p>
        <p class="subtitulo">
            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
        </p>
    </div>
    @include('modulos.mecanicos.reportes._ot-diarias-tabla', ['reporte' => $reporte, 'editable' => false])
</body>
</html>
