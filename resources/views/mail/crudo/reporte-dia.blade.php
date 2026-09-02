@php
    // Mismos colores del semáforo del tablero (resources/css/crudo/dashboard.css).
    $tarjetas = [
        ['Paro', $summary['paro'] ?? 0, '#ffe0dd', '#d10000'],
        ['Mala calidad', $summary['bad_quality'] ?? 0, '#e4eaff', '#0000d6'],
        ['Bajos kg', $summary['low_kilos'] ?? 0, '#fff9d1', '#96700a'],
        ['Sin datos', $summary['no_data'] ?? 0, '#ececec', '#3f3f3f'],
        ['En operación', $summary['operating'] ?? 0, '#ddfbe9', '#009e2b'],
    ];

    $cumplimiento = (float) ($summary['efficiencyPercent'] ?? 0);
    $barra = max(0, min(100, $cumplimiento));
    $colorBarra = $barra >= 95 ? '#009e2b' : ($barra >= 85 ? '#96700a' : '#d10000');

    $indicadores = [
        ['Telares en piso', number_format((float) ($summary['total'] ?? 0)), false],
        ['Kg producidos', number_format((float) ($summary['kilos'] ?? 0), 1).' kg', false],
        ['Kg meta del día', number_format((float) ($summary['expectedKilos'] ?? 0), 1).' kg', false],
        ['Calidad', number_format((float) ($summary['qualityPercent'] ?? 0), 1).'%', false],
        ['Cumplimiento de meta', number_format($cumplimiento, 1).'%', true],
    ];

    $tituloSeccion = 'font-size:11px;font-weight:bold;letter-spacing:1.4px;color:#64748b';
@endphp
{{-- Tablas anidadas, ancho fijo y estilos en línea: es lo único que renderiza igual en Outlook. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#eef2f7;margin:0;padding:0">
    <tr>
        <td align="center" style="padding:28px 12px">

            {{-- align=center en el <td> es lo que centra de verdad; margin:0 auto no funciona en Outlook. --}}
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" align="center"
                   style="width:640px;max-width:100%;background:#ffffff;border:1px solid #dbe3ec;border-radius:10px">

                @if ($logo)
                    <tr>
                        <td align="center" style="padding:24px 24px 16px">
                            <img src="{{ $message->embed($logo) }}" alt="Towell" height="38"
                                 style="display:block;height:38px;border:0">
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="height:3px;background:#4e80bf;font-size:0;line-height:0">&nbsp;</td>
                </tr>

                <tr>
                    <td align="center" style="background:#1e293b;padding:18px 24px;color:#ffffff">
                        <div style="font-size:19px;font-weight:bold;letter-spacing:0.5px;
                                    font-family:Arial,Helvetica,sans-serif">
                            REPORTE DIARIO DE TELARES
                        </div>
                        <div style="font-size:13px;color:#b9c6d8;padding-top:5px;
                                    font-family:Arial,Helvetica,sans-serif">
                            Día de producción {{ $fecha }} · de 06:30 a 06:30 h
                        </div>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:26px 24px 12px;font-family:Arial,Helvetica,sans-serif;{{ $tituloSeccion }}">
                        ESTADO DE LOS TELARES
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:0 18px">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="5" border="0">
                            <tr>
                                @foreach ($tarjetas as [$etiqueta, $valor, $fondo, $texto])
                                    <td width="20%" align="center" valign="middle"
                                        style="background:{{ $fondo }};border:2px solid {{ $texto }};
                                               border-radius:7px;padding:12px 4px;
                                               font-family:Arial,Helvetica,sans-serif">
                                        <div style="font-size:26px;font-weight:bold;line-height:1;color:{{ $texto }}">
                                            {{ (int) $valor }}
                                        </div>
                                        <div style="font-size:10px;font-weight:bold;padding-top:5px;color:{{ $texto }}">
                                            {{ mb_strtoupper($etiqueta) }}
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
                </tr>

                @if (!empty($sinPesoMuestra))
                    <tr>
                        <td align="center" style="padding:26px 12px 0">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background:#ffe0dd;border:2px solid #d10000;border-radius:7px;
                                          font-family:Arial,Helvetica,sans-serif">
                                <tr>
                                    <td align="center" style="padding:16px">
                                        <div style="font-size:26px;font-weight:bold;line-height:1;color:#d10000">
                                            {{ count($sinPesoMuestra) }}
                                        </div>
                                        <div style="font-size:12px;font-weight:bold;padding-top:6px;color:#d10000">
                                            TELARES SIN PESO MUESTRA
                                        </div>
                                        <div style="font-size:11px;padding-top:4px;color:#d10000">
                                            El detalle viene en el Excel adjunto
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td align="center" style="padding:28px 24px 12px;font-family:Arial,Helvetica,sans-serif;{{ $tituloSeccion }}">
                        INDICADORES DEL DÍA
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 24px">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="border:1px solid #e2e8f0;border-radius:7px;font-family:Arial,Helvetica,sans-serif">
                            @foreach ($indicadores as $i => [$etiqueta, $valor, $destacado])
                                <tr style="background:{{ $i % 2 === 0 ? '#ffffff' : '#f8fafc' }}">
                                    <td style="padding:11px 16px;font-size:14px;color:#475569;
                                               {{ $loop->last ? '' : 'border-bottom:1px solid #eef2f7' }}">
                                        {{ $etiqueta }}
                                    </td>
                                    <td align="right"
                                        style="padding:11px 16px;font-size:{{ $destacado ? '17px' : '15px' }};
                                               font-weight:bold;color:{{ $destacado ? $colorBarra : '#1e293b' }};
                                               {{ $loop->last ? '' : 'border-bottom:1px solid #eef2f7' }}">
                                        {{ $valor }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                {{-- Barra de avance dibujada con celdas: ningún cliente de correo soporta <progress>. --}}
                <tr>
                    <td style="padding:14px 24px 0">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background:#e2e8f0;border-radius:5px">
                            <tr>
                                <td width="{{ (int) round($barra) }}%"
                                    style="background:{{ $colorBarra }};height:10px;border-radius:5px;
                                           font-size:0;line-height:0">&nbsp;</td>
                                <td style="font-size:0;line-height:0">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:24px">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background:#f1f5f9;border-left:4px solid #4e80bf;border-radius:5px">
                            <tr>
                                <td style="padding:14px 16px;font-size:13px;color:#334155;line-height:1.5;
                                           font-family:Arial,Helvetica,sans-serif">
                                    Se adjunta el <strong>reporte en Excel</strong> con el detalle de cada telar
                                    agrupado por estado (paro, mala calidad, bajos kg, sin datos y en operación),
                                    sus kilos contra meta, calidad, el resumen por salón y las
                                    <strong>auditorías capturadas por Calidad</strong> en el día.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center"
                        style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 10px 10px;
                               padding:16px 24px;font-size:11px;color:#94a3b8;line-height:1.6;
                               font-family:Arial,Helvetica,sans-serif">
                        Generado automáticamente por el tablero ANDON de Crudo · Towell<br>
                        Envíos diarios a las 06:00, 14:00 y 22:00 h · Este buzón no recibe respuestas
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
