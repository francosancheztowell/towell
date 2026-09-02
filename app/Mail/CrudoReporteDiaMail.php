<?php

declare(strict_types=1);

namespace App\Mail;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CrudoReporteDiaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, int|float>  $summary  Resumen del tablero (CrudoDashboardData::$summary).
     * @param  string  $contenido  Bytes del .xlsx ya generado.
     * @param  list<array{telar: string, orden: string, producto: string}>  $sinPesoMuestra
     */
    public function __construct(
        private readonly DateTimeImmutable $day,
        private readonly array $summary,
        private readonly string $contenido,
        private readonly string $nombreArchivo,
        private readonly ?string $rutaLogo = null,
        private readonly array $sinPesoMuestra = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Reporte de telares %s · %d en paro, %d mala calidad, %d bajos kg',
                $this->day->format('d/m/Y'),
                (int) ($this->summary['paro'] ?? 0),
                (int) ($this->summary['bad_quality'] ?? 0),
                (int) ($this->summary['low_kilos'] ?? 0),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.crudo.reporte-dia',
            with: [
                'fecha' => $this->day->format('d/m/Y'),
                'summary' => $this->summary,
                'logo' => $this->rutaLogo,
                'sinPesoMuestra' => $this->sinPesoMuestra,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->contenido, $this->nombreArchivo)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
