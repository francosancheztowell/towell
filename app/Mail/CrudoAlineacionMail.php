<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Crudo\CrudoAuditoria;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso puntual: una auditoría marcó en tache la alineación contra la orden.
 * Sin adjunto: es un evento, no un reporte; el cuerpo trae el dato completo.
 */
final class CrudoAlineacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly CrudoAuditoria $audit,
        private readonly ?string $rutaLogo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Alineación incorrecta · Telar %s%s',
                trim((string) $this->audit->NoTelarId),
                trim((string) $this->audit->OrdenTrabajo) === ''
                    ? ''
                    : ' · Orden '.trim((string) $this->audit->OrdenTrabajo),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.crudo.alineacion',
            with: [
                'audit' => $this->audit,
                'logo' => $this->rutaLogo,
            ],
        );
    }
}
