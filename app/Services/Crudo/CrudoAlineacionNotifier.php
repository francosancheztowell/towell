<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Mail\CrudoAlineacionMail;
use App\Models\Crudo\CrudoAuditoria;
use App\Models\Sistema\SYSMensaje;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda el aviso de alineación incorrecta. Lista de correos propia
 * (CRUDO_ALINEACION_CORREOS): esto le toca a Planeación, no a quien recibe el
 * reporte diario del Andon.
 */
final class CrudoAlineacionNotifier
{
    public function notify(CrudoAuditoria $audit): void
    {
        // Solo el tache explícito. null = sin evaluar, no es un hallazgo.
        if ($audit->AlineacionOrden !== false) {
            return;
        }

        $destinatarios = SYSMensaje::soloCorreosValidos(
            (array) config('crudo.alineacion_recipients', []),
        );

        if ($destinatarios === []) {
            return;
        }

        try {
            Mail::to($destinatarios)->send(new CrudoAlineacionMail($audit, $this->rutaLogo()));
        } catch (Throwable $exception) {
            // Corre en defer(): nadie lee la excepción, y el aviso no debe
            // tumbar una auditoría que ya quedó guardada.
            Log::error('No fue posible enviar el aviso de alineación incorrecta', [
                'auditoria' => $audit->getKey(),
                'telar' => $audit->NoTelarId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function rutaLogo(): ?string
    {
        $ruta = public_path('images/fondosTowell/logo.png');

        return is_file($ruta) ? $ruta : null;
    }
}
