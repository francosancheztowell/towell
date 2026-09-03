@php
    $telar = trim((string) $audit->NoTelarId);
    $orden = trim((string) $audit->OrdenTrabajo);
    $observaciones = trim((string) ($audit->Observaciones ?? ''));

    $datos = [
        ['Telar', $telar === '' ? '—' : $telar],
        ['Salón', trim((string) $audit->Salon) ?: '—'],
        ['Orden de trabajo', $orden === '' ? 'Sin orden capturada' : $orden],
        ['Turno', 'Turno '.(int) $audit->Turno],
        ['Auditó', trim((string) ($audit->NomEmpl ?? '')) ?: '—'],
        ['Fecha y hora', optional($audit->Fecha)->format('d/m/Y H:i') ?? '—'],
    ];

    $tituloSeccion = 'font-size:11px;font-weight:bold;letter-spacing:1.4px;color:#64748b';
@endphp
{{-- Misma plantilla que el reporte diario: tablas anidadas y estilos en línea (Outlook). --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#eef2f7;margin:0;padding:0">
    <tr>
        <td align="center" style="padding:28px 12px">

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
                    <td style="height:3px;background:#d10000;font-size:0;line-height:0">&nbsp;</td>
                </tr>

                <tr>
                    <td align="center" style="background:#1e293b;padding:18px 24px;color:#ffffff">
                        <div style="font-size:19px;font-weight:bold;letter-spacing:0.5px;
                                    font-family:Arial,Helvetica,sans-serif">
                            ALINEACIÓN INCORRECTA
                        </div>
                        <div style="font-size:13px;color:#b9c6d8;padding-top:5px;
                                    font-family:Arial,Helvetica,sans-serif">
                            La alineación no coincide con la orden
                        </div>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:26px 24px 0">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background:#ffe0dd;border:2px solid #d10000;border-radius:7px;
                                      font-family:Arial,Helvetica,sans-serif">
                            <tr>
                                <td align="center" style="padding:16px">
                                    <div style="font-size:26px;font-weight:bold;line-height:1.2;color:#d10000">
                                        Telar {{ $telar === '' ? '—' : $telar }}
                                    </div>
                                    <div style="font-size:13px;font-weight:bold;padding-top:6px;color:#d10000">
                                        {{ $orden === '' ? 'SIN ORDEN CAPTURADA' : 'ORDEN '.$orden }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:26px 24px 12px;font-family:Arial,Helvetica,sans-serif;{{ $tituloSeccion }}">
                        DATOS DE LA AUDITORÍA
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 24px">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="border:1px solid #e2e8f0;border-radius:7px;font-family:Arial,Helvetica,sans-serif">
                            @foreach ($datos as $i => [$etiqueta, $valor])
                                <tr style="background:{{ $i % 2 === 0 ? '#ffffff' : '#f8fafc' }}">
                                    <td style="padding:11px 16px;font-size:14px;color:#475569;
                                               {{ $loop->last ? '' : 'border-bottom:1px solid #eef2f7' }}">
                                        {{ $etiqueta }}
                                    </td>
                                    <td align="right"
                                        style="padding:11px 16px;font-size:15px;font-weight:bold;color:#1e293b;
                                               {{ $loop->last ? '' : 'border-bottom:1px solid #eef2f7' }}">
                                        {{ $valor }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                @if ($observaciones !== '')
                    <tr>
                        <td align="center" style="padding:20px 24px 0">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background:#f1f5f9;border-left:4px solid #4e80bf;border-radius:5px">
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#334155;line-height:1.5;
                                               font-family:Arial,Helvetica,sans-serif">
                                        <strong>Observaciones:</strong> {{ $observaciones }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="height:24px;font-size:0;line-height:0">&nbsp;</td>
                </tr>

                <tr>
                    <td align="center"
                        style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 10px 10px;
                               padding:16px 24px;font-size:11px;color:#64748b;line-height:1.6;
                               font-family:Arial,Helvetica,sans-serif">
                        Generado automáticamente por el tablero ANDON de Crudo · Towell<br>
                        Auditoría #{{ $audit->getKey() }} · Este buzón no recibe respuestas
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
