<?php

declare(strict_types=1);

namespace App\Mail\Monitoreo;

use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de un error nuevo o de una regresión (fila de SYSMonError).
 * El mensaje ya viene saneado por ErrorRecorder; aun así todo se escapa.
 */
final class ErrorSistemaMail extends Mailable
{
    public function __construct(
        private readonly object $error,
        private readonly bool $regresion = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[Towell] %s: %s',
                $this->regresion ? 'REGRESIÓN' : 'ERROR NUEVO',
                mb_substr((string) $this->error->Clase, 0, 120),
            ),
        );
    }

    public function content(): Content
    {
        $filas = [
            'Origen' => $this->error->Origen,
            'Clase' => $this->error->Clase,
            'Mensaje' => mb_substr((string) $this->error->Mensaje, 0, 300),
            'Ruta' => $this->error->Ruta ?: 'N/D',
            'Ocurrencias' => $this->error->Ocurrencias,
            'Primera vez' => $this->fecha($this->error->PrimeraVez),
            'Última vez' => $this->fecha($this->error->UltimaVez),
        ];

        $detalle = url('/admin/errores/'.$this->error->Id);
        $titulo = $this->regresion
            ? 'REGRESIÓN: un error marcado como resuelto volvió a ocurrir'
            : 'ERROR NUEVO en Towell';

        $html = '<h2 style="font-family:sans-serif">'.e($titulo).'</h2><table style="font-family:sans-serif;border-collapse:collapse">';
        foreach ($filas as $etiqueta => $valor) {
            $html .= '<tr><th style="text-align:left;padding:4px 12px 4px 0;vertical-align:top">'.e($etiqueta)
                .'</th><td style="padding:4px 0;word-break:break-word">'.e((string) $valor).'</td></tr>';
        }
        $html .= '</table><p style="font-family:sans-serif"><a href="'.e($detalle).'">Ver detalle</a> · '.e($detalle).'</p>';

        return new Content(htmlString: $html);
    }

    private function fecha(mixed $valor): string
    {
        return $valor ? Carbon::parse($valor)->format('d/m/Y H:i:s') : 'N/D';
    }
}
